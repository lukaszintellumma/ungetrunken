<?php
declare(strict_types=1);
$u = require_role('admin', 'user');

$status = preg_replace('/[^a-z]/', '', (string)($_GET['status'] ?? ''));
$type   = preg_replace('/[^a-z]/', '', (string)($_GET['type'] ?? ''));

$w = []; $par = [];
if ($status) { $w[] = 'b.status = :s'; $par['s'] = $status; }
if ($type && isset(BOOKING_TYPES[$type])) { $w[] = 'b.type = :t'; $par['t'] = $type; }
$sql = 'SELECT b.*, uf.name AS from_name, ut.name AS to_name, us.display_name,
               (SELECT COUNT(*) FROM booking_items i WHERE i.booking_id = b.id) AS n
          FROM bookings b
          LEFT JOIN outlets uf ON uf.id = b.from_outlet_id
          LEFT JOIN outlets ut ON ut.id = b.to_outlet_id
          JOIN users us ON us.id = b.user_id'
     . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
     . ' ORDER BY b.id DESC LIMIT 300';
$rows = all($sql, $par);

render_head('Belege', $u, 'belege');
?>
<h1>Belege</h1>
<form method="get" class="card">
  <input type="hidden" name="p" value="belege">
  <div class="grid grid-3">
    <label class="field"><span>Status</span>
      <select name="status" onchange="this.form.submit()">
        <option value="">alle</option>
        <?php foreach (['entwurf'=>'Entwürfe','gebucht'=>'gebucht','storniert'=>'storniert'] as $k=>$l): ?>
          <option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select></label>
    <label class="field"><span>Buchungstyp</span>
      <select name="type" onchange="this.form.submit()">
        <option value="">alle</option>
        <?php foreach (BOOKING_TYPES as $k=>$s): ?>
          <option value="<?= $k ?>" <?= $type===$k?'selected':'' ?>><?= e($s['label']) ?></option>
        <?php endforeach; ?>
      </select></label>
    <div style="display:flex;align-items:flex-end"><a class="btn btn-primary" href="?p=buchung">Neue Buchung</a></div>
  </div>
</form>

<div class="table-wrap">
<table>
  <thead><tr><th>Beleg</th><th>Datum</th><th>Typ</th><th>Von</th><th>Nach</th>
             <th class="num">Pos.</th><th>Erfasst von</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['beleg'] ?: '—') ?></td>
      <td><?= date('d.m.Y', strtotime($r['booking_date'])) ?></td>
      <td><?= e(type_label($r['type'])) ?></td>
      <td><?= e($r['from_name'] ?? '—') ?></td>
      <td><?= e($r['to_name'] ?? '—') ?></td>
      <td class="num"><?= (int)$r['n'] ?></td>
      <td><?= e($r['display_name']) ?></td>
      <td><span class="pill pill-<?= $r['status']==='gebucht'?'ok':($r['status']==='entwurf'?'warn':'grey') ?>"><?= e($r['status']) ?></span></td>
      <td style="white-space:nowrap">
        <a class="btn btn-ghost btn-sm" href="?p=<?= $r['status']==='entwurf'?'buchung':'beleg' ?>&amp;id=<?= $r['id'] ?>">
          <?= $r['status']==='entwurf'?'bearbeiten':'ansehen' ?></a>
        <a class="btn btn-ghost btn-sm" href="?p=druck&amp;id=<?= $r['id'] ?>" target="_blank" rel="noopener">drucken</a>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="9" style="color:var(--muted)">Noch keine Belege.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php render_foot();
