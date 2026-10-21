<?php
declare(strict_types=1);
$u = require_role('admin', 'user');

$edit = (int)($_GET['edit'] ?? 0);
$prod = $edit ? one('SELECT * FROM products WHERE id = ?', [$edit]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = (string)($_POST['action'] ?? '');

    if ($act === 'save_product') {
        $id   = (int)($_POST['id'] ?? 0);
        $data = [
            trim((string)$_POST['artikelnr']), trim((string)$_POST['name']),
            ((int)$_POST['category_id']) ?: null, trim((string)$_POST['base_unit']) ?: 'Stück',
            (float)str_replace(',', '.', (string)$_POST['ek_price']),
            (float)str_replace(',', '.', (string)$_POST['deposit']),
            (float)str_replace(',', '.', (string)$_POST['min_stock']),
            isset($_POST['is_asset']) ? 1 : 0,
            isset($_POST['active']) ? 1 : 0,
        ];
        try {
            if ($id) {
                q('UPDATE products SET artikelnr=?, name=?, category_id=?, base_unit=?,
                        ek_price=?, deposit=?, min_stock=?, is_asset=?, active=? WHERE id=?',
                  array_merge($data, [$id]));
                flash('Artikel gespeichert.');
            } else {
                q('INSERT INTO products (artikelnr, name, category_id, base_unit,
                        ek_price, deposit, min_stock, is_asset, active) VALUES (?,?,?,?,?,?,?,?,?)', $data);
                $id = (int)db()->lastInsertId();
                q('INSERT INTO packagings (product_id, name, factor, is_default) VALUES (?,?,1,1)',
                  [$id, 'Einzeln (' . $data[3] . ')']);
                flash('Artikel angelegt. Ergänze jetzt die Gebinde.');
            }
            redirect('?p=produkte&edit=' . $id);
        } catch (PDOException $ex) {
            flash('Diese Artikelnummer ist schon vergeben.', 'error');
        }
    }

    if ($act === 'add_pack') {
        $pid    = (int)$_POST['product_id'];
        $factor = (float)str_replace(',', '.', (string)$_POST['factor']);
        if ($factor <= 0) {
            flash('Der Umrechnungsfaktor muss größer als 0 sein.', 'error');
        } else {
            q('INSERT INTO packagings (product_id, name, factor, ean, ek_price, deposit) VALUES (?,?,?,?,?,?)',
              [$pid, trim((string)$_POST['pname']), $factor,
               trim((string)$_POST['ean']) ?: null,
               $_POST['pek'] !== '' ? (float)str_replace(',', '.', (string)$_POST['pek']) : null,
               (float)str_replace(',', '.', (string)($_POST['pdeposit'] ?? '0'))]);
            flash('Gebinde hinzugefügt.');
        }
        redirect('?p=produkte&edit=' . $pid);
    }

    if ($act === 'del_pack') {
        $pid = (int)$_POST['product_id'];
        q('DELETE FROM packagings WHERE id = ? AND product_id = ?', [(int)$_POST['pack_id'], $pid]);
        flash('Gebinde entfernt.');
        redirect('?p=produkte&edit=' . $pid);
    }
}

/**
 * Gängige Gebinde als Vorlage. Der Faktor ist die Zahl der Basiseinheiten,
 * bei Getränken also die Zahl der Flaschen im Gebinde.
 */
const PACK_PRESETS = [
    'Einzeln' => [
        ['Einzelflasche',        1],
        ['Einzelstück',          1],
        ['Fass 30 l',            1],
        ['Fass 50 l',            1],
    ],
    'Kästen' => [
        ['Kasten 24 × 0,33 l',  24],
        ['Kasten 20 × 0,5 l',   20],
        ['Kasten 12 × 0,5 l',   12],
        ['Kasten 12 × 0,7 l',   12],
        ['Kasten 12 × 1,0 l',   12],
        ['Kasten 6 × 1,5 l',     6],
        ['Sixpack 6 × 0,33 l',   6],
        ['Sixpack 6 × 0,5 l',    6],
    ],
    'Großgebinde' => [
        ['Karton 100 Stück',            100],
        ['Palette 40 × Kasten 20er',    800],
        ['Palette 36 × Kasten 24er',    864],
    ],
];

$cats = all('SELECT * FROM categories ORDER BY sort');
render_head('Artikel', $u, 'produkte');
?>
<h1>Artikel</h1>

<div class="card">
  <h2 style="margin-top:0"><?= $prod ? 'Artikel bearbeiten' : 'Neuen Artikel anlegen' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_product">
    <input type="hidden" name="id" value="<?= (int)($prod['id'] ?? 0) ?>">
    <div class="grid grid-4">
      <label class="field"><span>Artikelnummer</span>
        <input name="artikelnr" value="<?= e($prod['artikelnr'] ?? '') ?>" required></label>
      <label class="field" style="grid-column: span 2"><span>Bezeichnung</span>
        <input name="name" value="<?= e($prod['name'] ?? '') ?>" required></label>
      <label class="field"><span>Warengruppe</span>
        <select name="category_id">
          <option value="">—</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (int)($prod['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select></label>
      <label class="field"><span>Basiseinheit</span>
        <input name="base_unit" value="<?= e($prod['base_unit'] ?? 'Stück') ?>" placeholder="Flasche, Stück, kg"></label>
      <label class="field"><span>EK je Basiseinheit</span>
        <input name="ek_price" value="<?= e(number_format((float)($prod['ek_price'] ?? 0), 4, ',', '')) ?>"></label>
      <label class="field"><span>Pfand je Basiseinheit</span>
        <input name="deposit" value="<?= e(number_format((float)($prod['deposit'] ?? 0), 2, ',', '')) ?>"></label>
      <label class="field"><span>Meldebestand</span>
        <input name="min_stock" value="<?= e(number_format((float)($prod['min_stock'] ?? 0), 0, ',', '')) ?>"></label>
    </div>
    <div style="margin-top:14px; display:flex; gap:24px">
      <label><input type="checkbox" name="is_asset" style="width:auto" <?= !empty($prod['is_asset']) ? 'checked' : '' ?>> Inventar statt Verbrauchsware</label>
      <label><input type="checkbox" name="active" style="width:auto" <?= ($prod === null || $prod['active']) ? 'checked' : '' ?>> aktiv</label>
    </div>
    <p class="hint">Inventar sind Dinge wie Kühlschränke, Zapfanlagen oder Gasflaschen: sie wandern hin und her, werden aber nicht verbraucht.</p>
    <div class="actions">
      <?php if ($prod): ?><a class="btn btn-ghost" href="?p=produkte">Neuer Artikel</a><?php endif; ?>
      <button class="btn btn-primary">Speichern</button>
    </div>
  </form>
</div>

<?php if ($prod): $packs = all('SELECT * FROM packagings WHERE product_id = ? ORDER BY factor', [$prod['id']]); ?>
<div class="card">
  <h2 style="margin-top:0">Gebinde für <?= e($prod['name']) ?></h2>
  <div class="table-wrap" style="margin-bottom:16px">
    <table>
      <thead><tr><th>Gebinde</th><th class="num">= <?= e($prod['base_unit']) ?></th><th>Barcode</th><th class="num">EK je Gebinde</th><th class="num">Pfand je Gebinde</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($packs as $pk): ?>
        <tr>
          <td><?= e($pk['name']) ?></td>
          <td class="num"><?= num($pk['factor']) ?></td>
          <td><?= e($pk['ean'] ?: '—') ?></td>
          <td class="num"><?= $pk['ek_price'] !== null ? money($pk['ek_price']) : money((float)$pk['factor'] * (float)$prod['ek_price']) ?></td>
          <td class="num"><?= (float)$pk['deposit'] > 0 ? money($pk['deposit']) : '—' ?></td>
          <td><form method="post" onsubmit="return confirm('Gebinde entfernen?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="del_pack">
              <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">
              <input type="hidden" name="pack_id" value="<?= $pk['id'] ?>">
              <button class="btn btn-ghost btn-sm">entfernen</button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_pack">
    <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">
    <div class="grid grid-4">
      <label class="field"><span>Vorlage</span>
        <select id="packPreset">
          <option value="">--- eigenes Gebinde ---</option>
          <?php foreach (PACK_PRESETS as $grp => $list): ?>
            <optgroup label="<?= e($grp) ?>">
            <?php foreach ($list as $pr): ?>
              <option value="<?= (int)$pr[1] ?>" data-name="<?= e($pr[0]) ?>"><?= e($pr[0]) ?></option>
            <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select></label>
      <label class="field"><span>Bezeichnung</span>
        <input name="pname" id="packName" placeholder="Kasten 20 × 0,5 l" required></label>
      <label class="field"><span>Entspricht wie vielen <?= e($prod['base_unit']) ?>?</span>
        <input name="factor" id="packFactor" placeholder="20" required></label>
      <label class="field"><span>Barcode (optional)</span>
        <input name="ean" placeholder="EAN scannen"></label>
      <label class="field"><span>EK je Gebinde (optional)</span>
        <input name="pek"></label>
      <label class="field"><span>Pfand je Gebinde (optional)</span>
        <input name="pdeposit" placeholder="z. B. Kastenpfand"></label>
    </div>
    <p class="hint">Der Faktor zählt immer in <?= e($prod['base_unit']) ?>, nicht in Zwischengebinden.
       Eine Palette mit 40 Kästen zu 20 Flaschen hat also den Faktor 800.
       Das Pfand je Gebinde ist das Pfand auf das Gebinde selbst (z. B. den Kasten) und kommt
       zusätzlich zum Pfand je <?= e($prod['base_unit']) ?> oben hinzu.</p>
    <div class="actions"><button class="btn btn-save">Gebinde hinzufügen</button></div>
  </form>

  <script>
  document.getElementById('packPreset').addEventListener('change', function () {
    const opt = this.selectedOptions[0];
    if (!this.value) return;
    document.getElementById('packName').value   = opt.dataset.name || '';
    document.getElementById('packFactor').value = this.value;
  });
  </script>
</div>
<?php endif; ?>

<h2>Alle Artikel</h2>
<div class="table-wrap">
<table>
  <thead><tr><th>Art.-Nr.</th><th>Bezeichnung</th><th>Warengruppe</th><th>Einheit</th>
             <th class="num">EK</th><th class="num">Gebinde</th><th></th></tr></thead>
  <tbody>
  <?php foreach (all('SELECT p.*, c.name AS cat,
                        (SELECT COUNT(*) FROM packagings k WHERE k.product_id = p.id) AS np
                        FROM products p LEFT JOIN categories c ON c.id = p.category_id
                        ORDER BY p.artikelnr') as $r): ?>
    <tr style="<?= $r['active'] ? '' : 'opacity:.5' ?>">
      <td><?= e($r['artikelnr']) ?></td>
      <td><?= e($r['name']) ?></td>
      <td><?= e($r['cat'] ?? '—') ?></td>
      <td><?= e($r['base_unit']) ?></td>
      <td class="num"><?= money($r['ek_price']) ?></td>
      <td class="num"><?= (int)$r['np'] ?></td>
      <td><a class="btn btn-ghost btn-sm" href="?p=produkte&amp;edit=<?= $r['id'] ?>">bearbeiten</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php render_foot();
