<?php
declare(strict_types=1);
$u = require_role('admin', 'user');
$id = (int)($_GET['id'] ?? 0);

$b = one('SELECT b.*, uf.name AS from_name, ut.name AS to_name, us.display_name
            FROM bookings b
            LEFT JOIN outlets uf ON uf.id = b.from_outlet_id
            LEFT JOIN outlets ut ON ut.id = b.to_outlet_id
            JOIN users us ON us.id = b.user_id WHERE b.id = ?', [$id]);
if (!$b) { flash('Diesen Beleg gibt es nicht.', 'error'); redirect('?p=belege'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    require_role('admin');
    try {
        $neu = cancel_booking($id, (int)$u['id']);
        flash("Storniert. Gegenbeleg $neu wurde gebucht.");
    } catch (Throwable $ex) {
        flash($ex->getMessage(), 'error');
    }
    redirect('?p=beleg&id=' . $id);
}

$items = all('SELECT bi.*, p.artikelnr, p.name, p.base_unit, pk.name AS pack_name
                FROM booking_items bi
                JOIN products p ON p.id = bi.product_id
                LEFT JOIN packagings pk ON pk.id = bi.packaging_id
               WHERE bi.booking_id = ? ORDER BY bi.pos', [$id]);
$sum = 0.0;
foreach ($items as $i) { $sum += (float)$i['qty_base'] * (float)$i['ek_price']; }

render_head('Beleg ' . ($b['beleg'] ?? ''), $u, 'belege');
?>
<h1>Beleg <?= e($b['beleg'] ?: 'Entwurf') ?></h1>
<p class="sub"><?= e(type_label($b['type'])) ?> ·
  <span class="pill pill-<?= $b['status']==='gebucht'?'ok':($b['status']==='entwurf'?'warn':'grey') ?>"><?= e($b['status']) ?></span></p>

<div class="card grid grid-4">
  <div><strong>Datum</strong><br><?= date('d.m.Y', strtotime($b['booking_date'])) ?></div>
  <div><strong>Von</strong><br><?= e($b['from_name'] ?? '—') ?></div>
  <div><strong>Nach</strong><br><?= e($b['to_name'] ?? '—') ?></div>
  <div><strong>Erfasst von</strong><br><?= e($b['display_name']) ?></div>
  <div><strong>Ausgabe durch</strong><br><?= e($b['issued_by'] ?: '—') ?></div>
  <div><strong>Ausgabe an</strong><br><?= e($b['received_by'] ?: '—') ?></div>
  <div style="grid-column: span 2"><strong>Beleg-Text</strong><br><?= e($b['beleg_text'] ?: '—') ?></div>
</div>

<div class="table-wrap">
<table>
  <thead><tr><th>Pos</th><th>Art.-Nr.</th><th>Artikel</th><th>Gebinde</th>
             <th class="num">Menge</th><th class="num">Basiseinheiten</th><th class="num">EK-Wert</th></tr></thead>
  <tbody>
  <?php foreach ($items as $i): ?>
    <tr>
      <td><?= (int)$i['pos'] ?></td>
      <td><?= e($i['artikelnr']) ?></td>
      <td><?= e($i['name']) ?></td>
      <td><?= e($i['pack_name'] ?: $i['base_unit']) ?></td>
      <td class="num"><?= num($i['qty_packs'], 2) ?></td>
      <td class="num"><?= num($i['qty_base'], 2) ?> <?= e($i['base_unit']) ?></td>
      <td class="num"><?= money((float)$i['qty_base'] * (float)$i['ek_price']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr><th colspan="6" class="num">Warenwert</th><th class="num"><?= money($sum) ?></th></tr></tfoot>
</table>
</div>

<div class="actions">
  <a class="btn btn-ghost" href="?p=belege">Zurück</a>
  <a class="btn btn-save" href="?p=druck&amp;id=<?= $id ?>" target="_blank" rel="noopener">Drucken (A4)</a>
  <?php if ($b['status'] === 'gebucht' && $u['role'] === 'admin'): ?>
    <form method="post" onsubmit="return confirm('Beleg stornieren? Es wird ein Gegenbeleg gebucht.')">
      <?= csrf_field() ?><button class="btn btn-danger">Stornieren</button>
    </form>
  <?php endif; ?>
</div>
<?php render_foot();
