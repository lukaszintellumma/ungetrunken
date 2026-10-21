<?php
declare(strict_types=1);

$u  = require_login();
$id = (int)($_GET['id'] ?? 0);

$b = one('SELECT b.*, uf.name AS from_name, ut.name AS to_name, us.display_name
            FROM bookings b
            LEFT JOIN outlets uf ON uf.id = b.from_outlet_id
            LEFT JOIN outlets ut ON ut.id = b.to_outlet_id
            JOIN users us ON us.id = b.user_id
           WHERE b.id = ?', [$id]);
if (!$b) { http_response_code(404); exit('Beleg nicht gefunden.'); }

// Theken-Benutzer duerfen nur Belege drucken, die ihre eigene Stelle betreffen
if ($u['role'] === 'theke') {
    $mine = (int)$u['outlet_id'];
    if ((int)$b['from_outlet_id'] !== $mine && (int)$b['to_outlet_id'] !== $mine) {
        http_response_code(403); exit('Dieser Beleg gehört nicht zu deiner Stelle.');
    }
}

$items = all('SELECT bi.*, p.artikelnr, p.name, p.base_unit, p.deposit, pk.name AS pack_name, pk.deposit AS pack_deposit
                FROM booking_items bi
                JOIN products p ON p.id = bi.product_id
                LEFT JOIN packagings pk ON pk.id = bi.packaging_id
               WHERE bi.booking_id = ? ORDER BY bi.pos', [$id]);

$sumWare  = 0.0;
$sumPfand = 0.0;
foreach ($items as $i) {
    $sumWare  += (float)$i['qty_base'] * (float)$i['ek_price'];
    $sumPfand += (float)$i['qty_base'] * (float)$i['deposit'];
    if ($i['packaging_id']) {
        $sumPfand += (float)$i['qty_packs'] * (float)$i['pack_deposit'];
    }
}

$costs = [
    'Lieferpauschale' => (float)$b['cost_lieferpauschale'],
    'Energie'         => (float)$b['cost_energie'],
    (string)($b['cost_frei_label'] ?: 'Sonstiges') => (float)$b['cost_frei_amount'],
];
$sumCosts = 0.0;
foreach ($costs as $amount) { $sumCosts += $amount; }

// Belegart bestimmt die Beschriftung der Unterschriftsfelder
$sigLeft  = 'Ausgegeben durch';
$sigRight = 'Empfang bestätigt';
if ($b['type'] === 'zubuchung')       { $sigLeft = 'Ware angenommen durch'; $sigRight = 'Lieferant / Fahrer'; }
elseif ($b['type'] === 'rueckbuchung'){ $sigLeft = 'Zurückgegeben durch';   $sigRight = 'Rücknahme bestätigt'; }
elseif ($b['type'] === 'inventur')    { $sigLeft = 'Gezählt durch';         $sigRight = 'Geprüft durch'; }
elseif ($b['type'] === 'abbuchung')   { $sigLeft = 'Erfasst durch';         $sigRight = 'Freigegeben durch'; }

$auto = isset($_GET['auto']);
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title><?= e(type_label($b['type'])) ?> <?= e($b['beleg'] ?? '') ?></title>
<link rel="stylesheet" href="assets/print.css">
</head>
<body>

<div class="toolbar">
  <a class="ghost" href="?p=beleg&amp;id=<?= $id ?>">Zurück</a>
  <button onclick="window.print()">Drucken</button>
</div>

<div class="sheet">
  <?php if ($b['status'] === 'storniert'): ?><div class="storno">STORNIERT</div><?php endif; ?>

  <div class="head">
    <div class="org">
      <?= e(cfg('CAMP_NAME', 'Zeltlager')) ?>
      <small>Warenwirtschaft ungetrunken</small>
    </div>
    <div class="doc">
      <div class="kind"><?= e(type_label($b['type'])) ?></div>
      <div class="nr"><?= e($b['beleg'] ?: 'ENTWURF') ?></div>
    </div>
  </div>

  <hr class="rule">

  <div class="meta">
    <div><div class="k">Buchungsdatum</div><div class="v"><?= date('d.m.Y', strtotime($b['booking_date'])) ?></div></div>
    <div><div class="k">Von</div><div class="v"><?= e($b['from_name'] ?? '—') ?></div></div>
    <div><div class="k">Nach</div><div class="v"><?= e($b['to_name'] ?? '—') ?></div></div>
    <div><div class="k">Erfasst von</div><div class="v"><?= e($b['display_name']) ?></div></div>

    <div><div class="k">Ausgabe durch</div><div class="v"><?= e($b['issued_by'] ?: '—') ?></div></div>
    <div><div class="k">Ausgabe an</div><div class="v"><?= e($b['received_by'] ?: '—') ?></div></div>
    <div class="wide"><div class="k">Beleg-Text</div><div class="v"><?= e($b['beleg_text'] ?: '—') ?></div></div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:8mm">Pos</th>
        <th style="width:22mm">Art.-Nr.</th>
        <th>Bezeichnung</th>
        <th style="width:38mm">Gebinde</th>
        <th class="num" style="width:20mm">Menge</th>
        <th class="num" style="width:28mm">Einzeln</th>
        <th class="num" style="width:24mm">Wert</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $i): ?>
      <tr>
        <td><?= (int)$i['pos'] ?></td>
        <td class="artnr"><?= e($i['artikelnr']) ?></td>
        <td><?= e($i['name']) ?></td>
        <td><?= e($i['pack_name'] ?: $i['base_unit']) ?></td>
        <td class="num"><?= num($i['qty_packs'], 2) ?></td>
        <td class="num"><?= num($i['qty_base'], 2) ?> <?= e($i['base_unit']) ?></td>
        <td class="num"><?= money((float)$i['qty_base'] * (float)$i['ek_price']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?>
      <tr><td colspan="7" style="color:#5a655e">Keine Positionen.</td></tr>
    <?php endif; ?>
    </tbody>
    <tfoot>
      <tr><td colspan="6" class="num">Warenwert (EK, netto)</td><td class="num"><?= money($sumWare) ?></td></tr>
      <?php if ($sumPfand > 0): ?>
      <tr><td colspan="6" class="num" style="font-weight:400">davon Pfand</td>
          <td class="num" style="font-weight:400"><?= money($sumPfand) ?></td></tr>
      <?php endif; ?>
      <?php foreach ($costs as $label => $amount): if ($amount <= 0) continue; ?>
      <tr><td colspan="6" class="num" style="font-weight:400"><?= e($label) ?></td>
          <td class="num" style="font-weight:400"><?= money($amount) ?></td></tr>
      <?php endforeach; ?>
      <?php if ($sumCosts > 0): ?>
      <tr><td colspan="6" class="num">Gesamtsumme</td>
          <td class="num"><?= money($sumWare + $sumPfand + $sumCosts) ?></td></tr>
      <?php endif; ?>
    </tfoot>
  </table>

  <div class="signatures">
    <div class="sig"><?= e($sigLeft) ?>, Datum</div>
    <div class="sig"><?= e($sigRight) ?>, Datum</div>
  </div>

  <?php if ($b['status'] === 'entwurf'): ?>
    <p class="note">Dieser Beleg ist noch ein Entwurf und wirkt sich nicht auf die Bestände aus.</p>
  <?php endif; ?>

  <div class="footline">
    <span><?= e(cfg('CAMP_NAME', 'Zeltlager')) ?> · <?= e($b['beleg'] ?: 'Entwurf') ?></span>
    <span>Gedruckt am <?= date('d.m.Y H:i') ?> von <?= e($u['display_name']) ?></span>
  </div>
</div>

<?php if ($auto): ?><script>window.addEventListener('load', () => window.print());</script><?php endif; ?>
</body>
</html>
