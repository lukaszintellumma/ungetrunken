<?php
declare(strict_types=1);
$u = require_login();
$lockedOutlet = ($u['role'] === 'theke') ? (int)$u['outlet_id'] : null;

$from = (string)($_GET['from'] ?? date('Y-m-d', strtotime('-14 days')));
$to   = (string)($_GET['to']   ?? date('Y-m-d'));

$outletCond = $lockedOutlet ? ' AND m.outlet_id = ' . $lockedOutlet : '';

// Verbrauch: alles was eine Stelle verlassen hat (negative Bewegungen)
// GROUP BY nennt bewusst alle ausgewaehlten Spalten und HAVING/ORDER BY
// wiederholen den Ausdruck statt den Alias zu benutzen: Aliase an diesen
// Stellen lehnt MySQL mit ONLY_FULL_GROUP_BY ab.
$verbrauch = all("
  SELECT p.artikelnr, p.name, p.base_unit, p.ek_price, o.name AS outlet,
         SUM(-m.qty_base) AS menge
    FROM stock_movements m
    JOIN bookings b ON b.id = m.booking_id
    JOIN products p ON p.id = m.product_id
    JOIN outlets  o ON o.id = m.outlet_id
   WHERE b.status = 'gebucht'
     AND m.qty_base < 0
     AND b.booking_date BETWEEN ? AND ? $outletCond
   GROUP BY p.id, p.artikelnr, p.name, p.base_unit, p.ek_price, o.id, o.name
   HAVING SUM(-m.qty_base) > 0
   ORDER BY SUM(-m.qty_base) * p.ek_price DESC
   LIMIT 100", [$from, $to]);

// Bestand je Stelle
$perOutlet = all("
  SELECT o.id, o.name, COUNT(DISTINCT s.product_id) AS artikel,
         SUM(s.qty_base * p.ek_price) AS wert
    FROM v_stock s
    JOIN outlets  o ON o.id = s.outlet_id
    JOIN products p ON p.id = s.product_id
   WHERE s.qty_base <> 0 " . ($lockedOutlet ? 'AND o.id = ' . $lockedOutlet : '') . "
   GROUP BY o.id, o.name
   ORDER BY SUM(s.qty_base * p.ek_price) DESC");

// Wareneingang im Zeitraum
$eingang = (float)scalar("
  SELECT COALESCE(SUM(bi.qty_base * bi.ek_price),0)
    FROM bookings b JOIN booking_items bi ON bi.booking_id = b.id
   WHERE b.type = 'zubuchung' AND b.status = 'gebucht' AND b.booking_date BETWEEN ? AND ?",
  [$from, $to]);

$verbrauchWert = 0.0;
foreach ($verbrauch as $v) { $verbrauchWert += (float)$v['menge'] * (float)$v['ek_price']; }

render_head('Auswertungen', $u, 'auswertung');
?>
<h1>Auswertungen</h1>

<form method="get" class="card">
  <input type="hidden" name="p" value="auswertung">
  <div class="grid grid-3">
    <label class="field"><span>Von</span><input type="date" name="from" value="<?= e($from) ?>"></label>
    <label class="field"><span>Bis</span><input type="date" name="to" value="<?= e($to) ?>"></label>
    <div style="display:flex;align-items:flex-end"><button class="btn btn-primary">Zeitraum anzeigen</button></div>
  </div>
</form>

<div class="grid grid-3" style="margin-bottom:22px">
  <div class="kpi"><div class="n"><?= money($eingang) ?></div><div class="l">Wareneingang im Zeitraum</div></div>
  <div class="kpi"><div class="n"><?= money($verbrauchWert) ?></div><div class="l">Abgänge zum EK</div></div>
  <div class="kpi"><div class="n"><?= count($verbrauch) ?></div><div class="l">Artikel mit Bewegung</div></div>
</div>

<h2>Bestand je Ausgabestelle</h2>
<div class="table-wrap">
<table>
  <thead><tr><th>Stelle</th><th class="num">Artikel</th><th class="num">Warenwert</th></tr></thead>
  <tbody>
  <?php foreach ($perOutlet as $r): ?>
    <tr><td><?= e($r['name']) ?></td><td class="num"><?= (int)$r['artikel'] ?></td><td class="num"><?= money($r['wert']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$perOutlet): ?><tr><td colspan="3" style="color:var(--muted)">Noch keine Bestände.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<h2>Abgänge im Zeitraum</h2>
<p class="hint" style="margin-top:-6px">Alles, was eine Stelle verlassen hat: Ausgaben ans Gelände, Verbrauch, Bruch und Inventurdifferenzen.</p>
<div class="table-wrap">
<table>
  <thead><tr><th>Art.-Nr.</th><th>Artikel</th><th>Stelle</th><th class="num">Menge</th><th class="num">EK-Wert</th></tr></thead>
  <tbody>
  <?php foreach ($verbrauch as $r): ?>
    <tr>
      <td><?= e($r['artikelnr']) ?></td>
      <td><?= e($r['name']) ?></td>
      <td><?= e($r['outlet']) ?></td>
      <td class="num"><?= num($r['menge'], 2) ?> <?= e($r['base_unit']) ?></td>
      <td class="num"><?= money((float)$r['menge'] * (float)$r['ek_price']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$verbrauch): ?><tr><td colspan="5" style="color:var(--muted)">In diesem Zeitraum gab es keine Abgänge.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php render_foot();
