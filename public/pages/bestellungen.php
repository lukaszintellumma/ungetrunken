<?php
declare(strict_types=1);
$u = require_login();
$isTheke = $u['role'] === 'theke';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = (string)($_POST['action'] ?? '');

    if ($act === 'create') {
        $outletId = $isTheke ? (int)$u['outlet_id'] : (int)($_POST['outlet_id'] ?? 0);
        if (!$outletId) { flash('Bitte eine Ausgabestelle wählen.', 'error'); redirect('?p=bestellungen'); }

        $lines = [];
        foreach (($_POST['it'] ?? []) as $row) {
            $pid = (int)($row['product_id'] ?? 0);
            $qty = (float)str_replace(',', '.', (string)($row['qty'] ?? ''));
            if ($pid && $qty > 0) $lines[] = [$pid, ((int)($row['packaging_id'] ?? 0)) ?: null, $qty];
        }
        if (!$lines) { flash('Die Bestellung enthält keine Positionen.', 'error'); redirect('?p=bestellungen'); }

        q('INSERT INTO orders (outlet_id, user_id, note, needed_at) VALUES (?,?,?,?)',
          [$outletId, $u['id'], trim((string)($_POST['note'] ?? '')) ?: null,
           ($_POST['needed_at'] ?? '') ?: null]);
        $oid = (int)db()->lastInsertId();
        $st = db()->prepare('INSERT INTO order_items (order_id, product_id, packaging_id, qty_packs) VALUES (?,?,?,?)');
        foreach ($lines as $l) { $st->execute([$oid, $l[0], $l[1], $l[2]]); }
        flash('Bestellung abgeschickt. Das Lager sieht sie sofort.');
        redirect('?p=bestellungen');
    }

    if ($act === 'status' && !$isTheke) {
        q('UPDATE orders SET status = ? WHERE id = ?',
          [(string)$_POST['status'], (int)$_POST['order_id']]);
        flash('Status aktualisiert.');
        redirect('?p=bestellungen');
    }
}

$products = all('SELECT id, artikelnr, name, base_unit FROM products WHERE active = 1 ORDER BY name');
$packsByProduct = [];
foreach (all('SELECT * FROM packagings ORDER BY factor') as $pk) {
    $packsByProduct[(int)$pk['product_id']][] = $pk;
}

$where = $isTheke ? 'WHERE o.outlet_id = ' . (int)$u['outlet_id'] : '';
$orders = all("SELECT o.*, ol.name AS outlet_name, us.display_name
                 FROM orders o JOIN outlets ol ON ol.id = o.outlet_id
                 JOIN users us ON us.id = o.user_id $where
                ORDER BY FIELD(o.status,'offen','in_arbeit','geliefert','abgelehnt'), o.id DESC LIMIT 100");

render_head('Bestellungen', $u, 'bestellungen');
?>
<h1>Bestellungen</h1>

<div class="card">
  <h2 style="margin-top:0">Neue Bestellung<?= $isTheke ? ' für ' . e((string)$u['outlet_name']) : '' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="grid grid-3">
      <?php if (!$isTheke): ?>
      <label class="field"><span>Für welche Stelle</span>
        <select name="outlet_id" required>
          <option value="">---</option>
          <?php foreach (all('SELECT * FROM outlets WHERE active=1 AND is_central=0 ORDER BY name') as $o): ?>
            <option value="<?= $o['id'] ?>"><?= e($o['name']) ?></option>
          <?php endforeach; ?>
        </select></label>
      <?php endif; ?>
      <label class="field"><span>Gebraucht bis</span><input type="date" name="needed_at"></label>
      <label class="field"><span>Anmerkung</span><input name="note" placeholder="z. B. vor dem Abendprogramm"></label>
    </div>

    <div style="margin-top:18px">
    <?php for ($i = 0; $i < 6; $i++): ?>
      <div class="pos-row order-row">
        <span class="tag">#<?= $i + 1 ?></span>
        <select name="it[<?= $i ?>][product_id]" class="prod">
          <option value="">--- Artikel wählen ---</option>
          <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>"><?= e($p['artikelnr'] . ' · ' . $p['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="it[<?= $i ?>][packaging_id]" class="pack"><option value="">Basiseinheit</option></select>
        <div class="stack"><span class="tag">Mge</span>
          <input name="it[<?= $i ?>][qty]" inputmode="decimal" autocomplete="off"></div>
      </div>
    <?php endfor; ?>
    </div>
    <div class="actions"><button class="btn btn-primary">Bestellung abschicken</button></div>
  </form>
</div>

<h2>Eingegangene Bestellungen</h2>
<?php foreach ($orders as $o):
  $items = all('SELECT oi.*, p.name, p.artikelnr, p.base_unit, pk.name AS pack_name
                  FROM order_items oi JOIN products p ON p.id = oi.product_id
                  LEFT JOIN packagings pk ON pk.id = oi.packaging_id
                 WHERE oi.order_id = ?', [$o['id']]);
  $pill = ['offen'=>'warn','in_arbeit'=>'grey','geliefert'=>'ok','abgelehnt'=>'bad'][$o['status']];
?>
  <div class="card">
    <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:center">
      <div>
        <strong>#<?= $o['id'] ?> · <?= e($o['outlet_name']) ?></strong>
        <span class="pill pill-<?= $pill ?>"><?= e($o['status']) ?></span><br>
        <span class="hint"><?= e($o['display_name']) ?> · <?= date('d.m.Y H:i', strtotime($o['created_at'])) ?>
          <?= $o['needed_at'] ? ' · gebraucht bis ' . date('d.m.Y', strtotime($o['needed_at'])) : '' ?>
          <?= $o['note'] ? ' · ' . e($o['note']) : '' ?></span>
      </div>
      <?php if (!$isTheke): ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-primary btn-sm" href="?p=buchung&amp;from_order=<?= $o['id'] ?>">Als Lieferschein buchen</a>
        <?php foreach (['in_arbeit'=>'in Arbeit','geliefert'=>'geliefert','abgelehnt'=>'ablehnen'] as $s=>$l): ?>
          <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
            <input type="hidden" name="status" value="<?= $s ?>">
            <button class="btn btn-ghost btn-sm"><?= $l ?></button></form>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <table style="margin-top:12px">
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr><td style="width:120px"><?= e($it['artikelnr']) ?></td><td><?= e($it['name']) ?></td>
            <td><?= e($it['pack_name'] ?: $it['base_unit']) ?></td>
            <td class="num" style="width:120px"><strong><?= num($it['qty_packs'], 0) ?></strong></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endforeach; ?>
<?php if (!$orders): ?><p class="hint">Noch keine Bestellungen.</p><?php endif; ?>

<script>
const PACKS = <?= json_encode($packsByProduct, JSON_UNESCAPED_UNICODE) ?>;
document.querySelectorAll('.order-row').forEach(row => {
  const prod = row.querySelector('.prod'), pack = row.querySelector('.pack');
  prod.addEventListener('change', () => {
    pack.innerHTML = '<option value="">Basiseinheit</option>';
    (PACKS[prod.value] || []).forEach(p => {
      const o = document.createElement('option');
      o.value = p.id; o.textContent = p.name + ' (× ' + p.factor.replace('.000','') + ')';
      if (p.is_default == 1) o.selected = true;
      pack.appendChild(o);
    });
  });
});
</script>
<?php render_foot();
