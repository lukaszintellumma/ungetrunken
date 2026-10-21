<?php
declare(strict_types=1);
$u = require_login();
$id = (int)($_GET['id'] ?? 0);
$p  = one('SELECT p.*, c.name AS cat FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?', [$id]);
if (!$p) { flash('Diesen Artikel gibt es nicht.', 'error'); redirect('?p=bestand'); }

$outletCond = ($u['role'] === 'theke') ? ' AND m.outlet_id = ' . (int)$u['outlet_id'] : '';

$stock = all("SELECT o.name, SUM(m.qty_base) AS qty
                FROM stock_movements m JOIN outlets o ON o.id = m.outlet_id
               WHERE m.product_id = ? $outletCond GROUP BY o.id HAVING qty <> 0 ORDER BY o.name", [$id]);

$moves = all("SELECT m.*, o.name AS outlet, b.beleg, b.type, b.booking_date, us.display_name
                FROM stock_movements m
                JOIN outlets o ON o.id = m.outlet_id
                JOIN bookings b ON b.id = m.booking_id
                JOIN users us ON us.id = b.user_id
               WHERE m.product_id = ? $outletCond
               ORDER BY m.id DESC LIMIT 200", [$id]);

render_head($p['name'], $u, 'bestand');
?>
<h1><?= e($p['name']) ?></h1>
<p class="sub">Art.-Nr. <?= e($p['artikelnr']) ?> · <?= e($p['cat'] ?? 'ohne Warengruppe') ?> ·
   EK <?= money($p['ek_price']) ?> je <?= e($p['base_unit']) ?><?= $p['deposit'] > 0 ? ' · Pfand ' . money($p['deposit']) : '' ?></p>

<div class="grid grid-4" style="margin-bottom:22px">
<?php foreach ($stock as $s): ?>
  <div class="kpi"><div class="n"><?= num($s['qty'], 2) ?></div><div class="l"><?= e($s['name']) ?></div></div>
<?php endforeach; ?>
<?php if (!$stock): ?><div class="kpi"><div class="n">0</div><div class="l">nirgends auf Bestand</div></div><?php endif; ?>
</div>

<h2>Bewegungen</h2>
<div class="table-wrap">
<table>
  <thead><tr><th>Datum</th><th>Beleg</th><th>Typ</th><th>Stelle</th><th class="num">Menge</th><th>Erfasst von</th></tr></thead>
  <tbody>
  <?php foreach ($moves as $m): $q = (float)$m['qty_base']; ?>
    <tr>
      <td><?= date('d.m.Y', strtotime($m['booking_date'])) ?></td>
      <td><a href="?p=beleg&amp;id=<?= $m['booking_id'] ?>"><?= e($m['beleg'] ?: '—') ?></a></td>
      <td><?= e(type_label($m['type'])) ?></td>
      <td><?= e($m['outlet']) ?></td>
      <td class="num" style="color:<?= $q < 0 ? 'var(--red)' : 'var(--green)' ?>">
        <?= $q > 0 ? '+' : '' ?><?= num($q, 2) ?></td>
      <td><?= e($m['display_name']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$moves): ?><tr><td colspan="6" style="color:var(--muted)">Für diesen Artikel gab es noch keine Buchung.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<div class="actions"><a class="btn btn-ghost" href="?p=bestand">Zurück zum Bestand</a></div>
<?php render_foot();
