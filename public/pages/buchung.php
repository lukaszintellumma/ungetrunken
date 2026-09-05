<?php
declare(strict_types=1);

$u = require_role('admin', 'user');

$rowCount = max(3, min(40, (int)($_POST['row_count'] ?? $_GET['rows'] ?? 5)));
$draftId  = (int)($_POST['booking_id'] ?? $_GET['id'] ?? 0);

$outlets  = all('SELECT * FROM outlets WHERE active = 1 ORDER BY is_central DESC, name');
$products = all('SELECT p.id, p.artikelnr, p.name, p.base_unit, p.ek_price
                   FROM products p WHERE p.active = 1 ORDER BY p.name');
$packs    = all('SELECT * FROM packagings ORDER BY product_id, factor');

$packsByProduct = [];
foreach ($packs as $pk) { $packsByProduct[(int)$pk['product_id']][] = $pk; }

// ---------- Formularzustand ----------
$head = [
    'type'           => 'zubuchung',
    'booking_date'   => date('Y-m-d'),
    'beleg'          => '',
    'beleg_text'     => '',
    'issued_by'      => $u['display_name'],
    'received_by'    => '',
    'from_outlet_id' => '',
    'to_outlet_id'   => '',
];
$items = [];

if ($draftId && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $b = one('SELECT * FROM bookings WHERE id = ?', [$draftId]);
    if (!$b) { flash('Diesen Beleg gibt es nicht.', 'error'); redirect('?p=belege'); }
    if ($b['status'] !== 'entwurf') { redirect('?p=beleg&id=' . $draftId); }
    foreach ($head as $k => $_) { $head[$k] = (string)($b[$k] ?? ''); }
    $items = all('SELECT * FROM booking_items WHERE booking_id = ? ORDER BY pos', [$draftId]);
    $rowCount = max($rowCount, count($items) + 1);
}

// ---------- Vorbelegung aus einer Bestellung ----------
$fromOrder = (int)($_GET['from_order'] ?? 0);
if ($fromOrder && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $ord = one('SELECT * FROM orders WHERE id = ?', [$fromOrder]);
    if ($ord) {
        $central = one('SELECT id FROM outlets WHERE is_central = 1 LIMIT 1');
        $head['type']           = 'ausgabe';
        $head['from_outlet_id'] = (string)($central['id'] ?? '');
        $head['to_outlet_id']   = (string)$ord['outlet_id'];
        $head['beleg_text']     = 'Bestellung #' . $fromOrder;
        $items = all('SELECT product_id, packaging_id, qty_packs FROM order_items WHERE order_id = ?', [$fromOrder]);
        $rowCount = max($rowCount, count($items) + 1);
    }
}

$error = null;

// ---------- Speichern / Buchen ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? 'save');

    foreach ($head as $k => $_) { $head[$k] = trim((string)($_POST[$k] ?? '')); }
    $postedItems = $_POST['it'] ?? [];

    if ($action === 'addrow')  { $rowCount++; }
    elseif ($action === 'delrow') { $rowCount = max(1, $rowCount - 1); }
    elseif ($action === 'reset')  { redirect('?p=buchung'); }

    // Positionen aufbereiten
    $clean = [];
    $pos = 0;
    foreach ($postedItems as $row) {
        $pid = (int)($row['product_id'] ?? 0);
        $qtyRaw = str_replace(',', '.', trim((string)($row['qty'] ?? '')));
        if (!$pid || $qtyRaw === '' || !is_numeric($qtyRaw)) continue;   // leere Zeile
        $qty = (float)$qtyRaw;
        if (abs($qty) < 0.0001 && $head['type'] !== 'inventur') continue;

        $packId = (int)($row['packaging_id'] ?? 0) ?: null;
        $factor = 1.0;
        if ($packId) {
            foreach ($packsByProduct[$pid] ?? [] as $pk) {
                if ((int)$pk['id'] === $packId) { $factor = (float)$pk['factor']; break; }
            }
        }
        $ek = 0.0;
        foreach ($products as $p) { if ((int)$p['id'] === $pid) { $ek = (float)$p['ek_price']; break; } }

        $clean[] = [
            'pos' => ++$pos, 'product_id' => $pid, 'packaging_id' => $packId,
            'qty_packs' => $qty, 'qty_base' => round($qty * $factor, 3), 'ek_price' => $ek,
        ];
    }
    $items = $postedItems;   // Eingaben für die Neuanzeige behalten

    if (in_array($action, ['save', 'book'], true)) {
        if (!isset(BOOKING_TYPES[$head['type']])) {
            $error = 'Bitte einen Buchungstyp wählen.';
        } elseif (!$clean) {
            $error = 'Trag mindestens eine Position mit Menge ein.';
        } else {
            try {
                db()->beginTransaction();
                if ($draftId) {
                    q('UPDATE bookings SET type=?, booking_date=?, from_outlet_id=?, to_outlet_id=?,
                            issued_by=?, received_by=?, beleg_text=? WHERE id = ? AND status = "entwurf"',
                      [$head['type'], $head['booking_date'] ?: date('Y-m-d'),
                       $head['from_outlet_id'] ?: null, $head['to_outlet_id'] ?: null,
                       $head['issued_by'], $head['received_by'], $head['beleg_text'], $draftId]);
                    q('DELETE FROM booking_items WHERE booking_id = ?', [$draftId]);
                } else {
                    q('INSERT INTO bookings (type, booking_date, from_outlet_id, to_outlet_id,
                            issued_by, received_by, beleg_text, user_id, status)
                       VALUES (?,?,?,?,?,?,?,?,"entwurf")',
                      [$head['type'], $head['booking_date'] ?: date('Y-m-d'),
                       $head['from_outlet_id'] ?: null, $head['to_outlet_id'] ?: null,
                       $head['issued_by'], $head['received_by'], $head['beleg_text'], $u['id']]);
                    $draftId = (int)db()->lastInsertId();
                }
                $stI = db()->prepare('INSERT INTO booking_items
                        (booking_id, pos, product_id, packaging_id, qty_packs, qty_base, ek_price)
                        VALUES (?,?,?,?,?,?,?)');
                foreach ($clean as $c) {
                    $stI->execute([$draftId, $c['pos'], $c['product_id'], $c['packaging_id'],
                                   $c['qty_packs'], $c['qty_base'], $c['ek_price']]);
                }
                db()->commit();

                if ($action === 'save') {
                    flash('Entwurf gespeichert.');
                    redirect('?p=buchung&id=' . $draftId);
                }
                $beleg = post_booking($draftId);
                flash("Gebucht. Beleg $beleg ist im Bestand.");
                redirect('?p=beleg&id=' . $draftId);
            } catch (Throwable $ex) {
                if (db()->inTransaction()) db()->rollBack();
                $error = $ex->getMessage();
            }
        }
    }
}

$rowCount = max($rowCount, count($items) + 1);

render_head('Buchung erfassen', $u, 'buchung');
?>
<h1>Buchung erfassen</h1>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" id="bform">
  <?= csrf_field() ?>
  <input type="hidden" name="booking_id" value="<?= $draftId ?>">
  <input type="hidden" name="row_count" value="<?= $rowCount ?>">

  <div class="card">
    <div class="grid grid-4">
      <label class="field"><span>Buchungstyp</span>
        <select name="type" id="btype" required>
          <?php foreach (BOOKING_TYPES as $k => $spec): ?>
            <option value="<?= e($k) ?>" <?= $head['type'] === $k ? 'selected' : '' ?>><?= e($spec['label']) ?></option>
          <?php endforeach; ?>
        </select></label>

      <label class="field"><span>Beleg</span>
        <input value="<?= e($head['beleg'] ?: 'wird beim Buchen vergeben') ?>" readonly></label>

      <label class="field"><span>Beleg-Text</span>
        <input name="beleg_text" value="<?= e($head['beleg_text']) ?>" placeholder="z. B. Lieferung Getränkemarkt"></label>

      <label class="field"><span>Buchungsdatum</span>
        <input type="date" name="booking_date" value="<?= e($head['booking_date']) ?>" required></label>
    </div>

    <div class="grid grid-4" style="margin-top:18px">
      <label class="field"><span>Ausgabe durch</span>
        <input name="issued_by" value="<?= e($head['issued_by']) ?>"></label>

      <label class="field"><span>Ausgabe an</span>
        <input name="received_by" value="<?= e($head['received_by']) ?>" placeholder="Name der abholenden Person"></label>

      <label class="field" id="wrap-from"><span>Von</span>
        <select name="from_outlet_id">
          <option value="">---</option>
          <?php foreach ($outlets as $o): ?>
            <option value="<?= $o['id'] ?>" <?= (string)$head['from_outlet_id'] === (string)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
          <?php endforeach; ?>
        </select></label>

      <label class="field" id="wrap-to"><span>Nach</span>
        <select name="to_outlet_id">
          <option value="">---</option>
          <?php foreach ($outlets as $o): ?>
            <option value="<?= $o['id'] ?>" <?= (string)$head['to_outlet_id'] === (string)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
          <?php endforeach; ?>
        </select></label>
    </div>
    <p class="hint" id="typehint"></p>
  </div>

  <div class="card">
    <?php for ($i = 0; $i < $rowCount; $i++):
        $it   = $items[$i] ?? [];
        $pid  = (int)($it['product_id'] ?? 0);
        $pack = (int)($it['packaging_id'] ?? 0);
        $qty  = $it['qty'] ?? ($it['qty_packs'] ?? '');
        if (is_numeric($qty)) { $qty = rtrim(rtrim(number_format((float)$qty, 3, ',', ''), '0'), ','); }
        $artnr = '';
        foreach ($products as $p) { if ((int)$p['id'] === $pid) { $artnr = $p['artikelnr']; break; } }
    ?>
      <div class="pos-row" data-row="<?= $i ?>">
        <div class="stack">
          <span class="tag">#<?= $i + 1 ?></span>
          <input class="artnr" value="<?= e($artnr) ?>" placeholder="Nr. / Scan" autocomplete="off">
        </div>

        <select name="it[<?= $i ?>][product_id]" class="prod">
          <option value="">--- Artikel wählen ---</option>
          <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>" data-artnr="<?= e($p['artikelnr']) ?>" <?= $pid === (int)$p['id'] ? 'selected' : '' ?>>
              <?= e($p['artikelnr'] . ' · ' . $p['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <select name="it[<?= $i ?>][packaging_id]" class="pack">
          <option value="">Basiseinheit</option>
          <?php foreach ($packsByProduct[$pid] ?? [] as $pk): ?>
            <option value="<?= $pk['id'] ?>" <?= $pack === (int)$pk['id'] ? 'selected' : '' ?>>
              <?= e($pk['name']) ?> (× <?= num($pk['factor']) ?>)
            </option>
          <?php endforeach; ?>
        </select>

        <div class="stack">
          <span class="tag">Mge</span>
          <input name="it[<?= $i ?>][qty]" value="<?= e((string)$qty) ?>" inputmode="decimal" autocomplete="off">
        </div>
      </div>
    <?php endfor; ?>

    <div class="actions">
      <button class="btn btn-save"    name="action" value="save">Entwurf speichern</button>
      <button class="btn btn-primary" name="action" value="book">Buchen</button>
      <button class="btn btn-ghost"   name="action" value="addrow" formnovalidate>+ Zeile</button>
      <button class="btn btn-ghost"   name="action" value="delrow" formnovalidate>− Zeile</button>
      <button class="btn btn-danger"  name="action" value="reset"  formnovalidate>Verwerfen</button>
    </div>
    <p class="hint">Zum Leeren einer Zeile die Menge löschen. Barcodes kannst du direkt in das Feld „Nr. / Scan“ scannen.</p>
  </div>
</form>

<script>
const PACKS = <?= json_encode($packsByProduct, JSON_UNESCAPED_UNICODE) ?>;
const TYPES = <?= json_encode(BOOKING_TYPES, JSON_UNESCAPED_UNICODE) ?>;

function fillPacks(row) {
  const prod = row.querySelector('.prod'), pack = row.querySelector('.pack');
  const list = PACKS[prod.value] || [];
  const keep = pack.value;
  pack.innerHTML = '<option value="">Basiseinheit</option>';
  list.forEach(p => {
    const o = document.createElement('option');
    o.value = p.id;
    o.textContent = p.name + ' (× ' + p.factor.replace('.000','') + ')';
    if (String(p.id) === keep || (!keep && p.is_default == 1)) o.selected = true;
    pack.appendChild(o);
  });
}

document.querySelectorAll('.pos-row').forEach(row => {
  const artnr = row.querySelector('.artnr'), prod = row.querySelector('.prod');

  prod.addEventListener('change', () => {
    const opt = prod.selectedOptions[0];
    artnr.value = opt ? (opt.dataset.artnr || '') : '';
    fillPacks(row);
  });

  // Scanner und Tastatureingabe: Artikelnummer oder EAN
  const lookup = () => {
    const code = artnr.value.trim();
    if (!code) return;
    const hit = [...prod.options].find(o => o.dataset.artnr === code);
    if (hit) { prod.value = hit.value; fillPacks(row); return; }
    fetch('?p=lookup&code=' + encodeURIComponent(code))
      .then(r => r.json())
      .then(d => {
        if (!d || !d.product_id) { artnr.style.borderColor = 'var(--red)'; return; }
        artnr.style.borderColor = '';
        prod.value = d.product_id;
        fillPacks(row);
        if (d.packaging_id) row.querySelector('.pack').value = d.packaging_id;
        row.querySelector('[name$="[qty]"]').focus();
      });
  };
  artnr.addEventListener('change', lookup);
  artnr.addEventListener('keydown', ev => { if (ev.key === 'Enter') { ev.preventDefault(); lookup(); } });
});

function applyType() {
  const spec = TYPES[document.getElementById('btype').value];
  if (!spec) return;
  document.getElementById('wrap-from').style.opacity = spec.from ? 1 : .4;
  document.getElementById('wrap-to').style.opacity   = spec.to   ? 1 : .4;
  document.querySelector('[name=from_outlet_id]').disabled = !spec.from;
  document.querySelector('[name=to_outlet_id]').disabled   = !spec.to;
  const hints = {
    zubuchung:    'Wareneingang vom Lieferanten. Es zählt nur das Ziel.',
    ausgabe:      'Lieferschein: Ware wandert vom Lager an eine Theke.',
    rueckbuchung: 'Theke gibt Ware zurück ans Lager.',
    umbuchung:    'Verschiebung zwischen zwei Stellen.',
    abbuchung:    'Verbrauch, Bruch oder Schwund. Es zählt nur die Quelle.',
    inventur:     'Gezählter Bestand. Die Menge ist der Endstand, nicht die Differenz.'
  };
  document.getElementById('typehint').textContent = hints[document.getElementById('btype').value] || '';
}
document.getElementById('btype').addEventListener('change', applyType);
applyType();

// deaktivierte Selects würden nicht mitgesendet: vor dem Absenden freigeben
document.getElementById('bform').addEventListener('submit', () => {
  document.querySelectorAll('#bform select[disabled]').forEach(s => { s.value = ''; s.disabled = false; });
});
</script>
<?php render_foot();
