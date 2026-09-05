<?php
declare(strict_types=1);

$u = require_login();

// Theken-Benutzer sehen ausschließlich ihre eigene Stelle
$lockedOutlet = ($u['role'] === 'theke') ? (int)$u['outlet_id'] : null;
$outlets = all('SELECT * FROM outlets WHERE active = 1 ORDER BY is_central DESC, name');
$sel = $lockedOutlet ?? (int)($_GET['outlet'] ?? 0);   // 0 = alle Stellen

$catFilter = (int)($_GET['cat'] ?? 0);
$search    = trim((string)($_GET['q'] ?? ''));

$where  = ['p.active = 1'];
$params = [];
if ($sel)       { $params['o'] = $sel; }   // wirkt im JOIN, nicht im WHERE
if ($catFilter) { $where[] = 'p.category_id = :c'; $params['c'] = $catFilter; }
if ($search !== '') {
    $where[] = '(p.name LIKE :q OR p.artikelnr LIKE :q)';
    $params['q'] = '%' . $search . '%';
}
$whereSql = implode(' AND ', $where);

$rows = all("
  SELECT p.id, p.artikelnr, p.name, p.base_unit, p.ek_price, p.min_stock, p.is_asset,
         c.name AS cat,
         COALESCE(SUM(s.qty_base), 0) AS qty
    FROM products p
    LEFT JOIN categories c  ON c.id = p.category_id
    LEFT JOIN v_stock    s  ON s.product_id = p.id " . ($sel ? 'AND s.outlet_id = :o' : '') . "
   WHERE $whereSql
   GROUP BY p.id, p.artikelnr, p.name, p.base_unit, p.ek_price,
            p.min_stock, p.is_asset, c.name, c.sort
   ORDER BY c.sort, p.name", $params);

$totalValue = 0.0; $lowCount = 0;
foreach ($rows as $r) {
    $totalValue += (float)$r['qty'] * (float)$r['ek_price'];
    if (!$r['is_asset'] && $r['min_stock'] > 0 && $r['qty'] < $r['min_stock']) $lowCount++;
}
$openOrders = (int)scalar('SELECT COUNT(*) FROM orders WHERE status = "offen"');

render_head('Bestand', $u, 'bestand');
?>
<h1><?= $sel ? e((string)scalar('SELECT name FROM outlets WHERE id=?', [$sel])) : 'Bestand auf dem Gelände' ?></h1>

<div class="grid grid-4" style="margin-bottom:22px">
  <div class="kpi"><div class="n"><?= count($rows) ?></div><div class="l">Artikel</div></div>
  <div class="kpi"><div class="n"><?= money($totalValue) ?></div><div class="l">Warenwert zum EK</div></div>
  <div class="kpi"><div class="n" style="color:<?= $lowCount ? 'var(--amber)' : 'inherit' ?>"><?= $lowCount ?></div><div class="l">unter Meldebestand</div></div>
  <div class="kpi"><div class="n"><?= $openOrders ?></div><div class="l">offene Bestellungen</div></div>
</div>

<form method="get" class="card">
  <input type="hidden" name="p" value="bestand">
  <div class="grid grid-3">
    <?php if (!$lockedOutlet): ?>
    <label class="field"><span>Ausgabestelle</span>
      <select name="outlet" onchange="this.form.submit()">
        <option value="0">Alle Stellen zusammen</option>
        <?php foreach ($outlets as $o): ?>
          <option value="<?= $o['id'] ?>" <?= $sel === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
        <?php endforeach; ?>
      </select></label>
    <?php endif; ?>
    <label class="field"><span>Warengruppe</span>
      <select name="cat" onchange="this.form.submit()">
        <option value="0">Alle</option>
        <?php foreach (all('SELECT * FROM categories ORDER BY sort') as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $catFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></label>
    <label class="field"><span>Suche</span>
      <input name="q" value="<?= e($search) ?>" placeholder="Artikelnummer oder Name"></label>
  </div>
</form>

<div class="table-wrap">
<table>
  <thead><tr>
    <th>Art.-Nr.</th><th>Artikel</th><th>Warengruppe</th>
    <th class="num">Bestand</th><th>Einheit</th><th class="num">EK-Wert</th><th>Status</th>
  </tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="7" style="color:var(--muted)">Noch keine Artikel erfasst. Lege sie unter „Artikel“ an.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $r):
      $qty = (float)$r['qty'];
      $low = !$r['is_asset'] && $r['min_stock'] > 0 && $qty < (float)$r['min_stock'];
  ?>
    <tr>
      <td><?= e($r['artikelnr']) ?></td>
      <td><a href="?p=artikelkonto&amp;id=<?= $r['id'] ?>"><?= e($r['name']) ?></a></td>
      <td><?= e($r['cat'] ?? '—') ?></td>
      <td class="num"><strong><?= num($qty, $qty == (int)$qty ? 0 : 2) ?></strong></td>
      <td><?= e($r['base_unit']) ?></td>
      <td class="num"><?= money($qty * (float)$r['ek_price']) ?></td>
      <td>
        <?php if ($r['is_asset']): ?><span class="pill pill-grey">Inventar</span>
        <?php elseif ($qty < 0): ?><span class="pill pill-bad">negativ</span>
        <?php elseif ($low): ?><span class="pill pill-warn">unter <?= num($r['min_stock']) ?></span>
        <?php else: ?><span class="pill pill-ok">ok</span><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php render_foot();
