<?php
declare(strict_types=1);
$u = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    switch ((string)($_POST['action'] ?? '')) {

        case 'outlet_save':
            $id = (int)($_POST['id'] ?? 0);
            $vals = [trim((string)$_POST['name']), (string)$_POST['kind'],
                     isset($_POST['is_central']) ? 1 : 0, isset($_POST['active']) ? 1 : 0];
            if (isset($_POST['is_central'])) { q('UPDATE outlets SET is_central = 0'); }
            if ($id) { q('UPDATE outlets SET name=?, kind=?, is_central=?, active=? WHERE id=?', array_merge($vals, [$id])); }
            else     { q('INSERT INTO outlets (name, kind, is_central, active) VALUES (?,?,?,?)', $vals); }
            flash('Ausgabestelle gespeichert.');
            redirect('?p=stammdaten');

        case 'user_save':
            $id   = (int)($_POST['id'] ?? 0);
            $role = (string)$_POST['role'];
            $out  = ((int)($_POST['outlet_id'] ?? 0)) ?: null;
            if ($role === 'theke' && !$out) {
                flash('Ein Theken-Benutzer braucht eine zugeordnete Stelle.', 'error');
                redirect('?p=stammdaten');
            }
            $pw = (string)($_POST['password'] ?? '');
            try {
                if ($id) {
                    q('UPDATE users SET display_name=?, role=?, outlet_id=?, active=? WHERE id=?',
                      [trim((string)$_POST['display_name']), $role, $out, isset($_POST['active']) ? 1 : 0, $id]);
                    if ($pw !== '') {
                        if (strlen($pw) < 8) { flash('Passwort zu kurz, mindestens 8 Zeichen.', 'error'); redirect('?p=stammdaten'); }
                        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
                    }
                } else {
                    if (strlen($pw) < 8) { flash('Passwort zu kurz, mindestens 8 Zeichen.', 'error'); redirect('?p=stammdaten'); }
                    q('INSERT INTO users (username, display_name, password_hash, role, outlet_id, active)
                       VALUES (?,?,?,?,?,1)',
                      [trim((string)$_POST['username']), trim((string)$_POST['display_name']),
                       password_hash($pw, PASSWORD_DEFAULT), $role, $out]);
                }
                flash('Benutzer gespeichert.');
            } catch (PDOException $ex) {
                flash('Diesen Benutzernamen gibt es schon.', 'error');
            }
            redirect('?p=stammdaten');

        case 'cat_save':
            q('INSERT INTO categories (name, kind, sort) VALUES (?,?,?)',
              [trim((string)$_POST['name']), (string)$_POST['kind'], (int)$_POST['sort']]);
            flash('Warengruppe angelegt.');
            redirect('?p=stammdaten');
    }
}

$editUser   = (int)($_GET['user'] ?? 0);
$eu         = $editUser ? one('SELECT * FROM users WHERE id = ?', [$editUser]) : null;
$editOutlet = (int)($_GET['outlet'] ?? 0);
$eo         = $editOutlet ? one('SELECT * FROM outlets WHERE id = ?', [$editOutlet]) : null;
$outlets    = all('SELECT * FROM outlets ORDER BY is_central DESC, name');

render_head('Stammdaten', $u, 'stammdaten');
?>
<h1>Stammdaten</h1>

<h2>Ausgabestellen</h2>
<div class="card">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="outlet_save">
    <input type="hidden" name="id" value="<?= (int)($eo['id'] ?? 0) ?>">
    <div class="grid grid-3">
      <label class="field"><span>Name</span>
        <input name="name" value="<?= e($eo['name'] ?? '') ?>" placeholder="Bar Lagerfeuer" required></label>
      <label class="field"><span>Art</span>
        <select name="kind">
          <?php foreach (['zentrallager'=>'Zentrallager','theke'=>'Theke','bar'=>'Bar','cafe'=>'Café','extern'=>'extern'] as $k=>$l): ?>
            <option value="<?= $k ?>" <?= ($eo['kind'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select></label>
      <div style="display:flex;align-items:flex-end;gap:18px;padding-bottom:9px">
        <label><input type="checkbox" name="is_central" style="width:auto" <?= !empty($eo['is_central']) ? 'checked' : '' ?>> Zentrallager</label>
        <label><input type="checkbox" name="active" style="width:auto" <?= ($eo === null || $eo['active']) ? 'checked' : '' ?>> aktiv</label>
      </div>
    </div>
    <div class="actions">
      <?php if ($eo): ?><a class="btn btn-ghost" href="?p=stammdaten">Neue Stelle</a><?php endif; ?>
      <button class="btn btn-primary">Speichern</button>
    </div>
  </form>
</div>
<div class="table-wrap">
<table><thead><tr><th>Name</th><th>Art</th><th>Zentrallager</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($outlets as $o): ?>
  <tr><td><?= e($o['name']) ?></td><td><?= e($o['kind']) ?></td>
      <td><?= $o['is_central'] ? 'ja' : '—' ?></td>
      <td><?= $o['active'] ? '<span class="pill pill-ok">aktiv</span>' : '<span class="pill pill-grey">inaktiv</span>' ?></td>
      <td><a class="btn btn-ghost btn-sm" href="?p=stammdaten&amp;outlet=<?= $o['id'] ?>">bearbeiten</a></td></tr>
<?php endforeach; ?>
</tbody></table></div>

<h2>Benutzer</h2>
<div class="card">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="user_save">
    <input type="hidden" name="id" value="<?= (int)($eu['id'] ?? 0) ?>">
    <div class="grid grid-4">
      <label class="field"><span>Benutzername</span>
        <input name="username" value="<?= e($eu['username'] ?? '') ?>" <?= $eu ? 'readonly' : 'required' ?>></label>
      <label class="field"><span>Anzeigename</span>
        <input name="display_name" value="<?= e($eu['display_name'] ?? '') ?>" required></label>
      <label class="field"><span>Rolle</span>
        <select name="role">
          <?php foreach (ROLE_LABELS as $k=>$l): ?>
            <option value="<?= $k ?>" <?= ($eu['role'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select></label>
      <label class="field"><span>Zugeordnete Stelle</span>
        <select name="outlet_id">
          <option value="">—</option>
          <?php foreach ($outlets as $o): ?>
            <option value="<?= $o['id'] ?>" <?= (int)($eu['outlet_id'] ?? 0) === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
          <?php endforeach; ?>
        </select></label>
      <label class="field"><span>Passwort <?= $eu ? '(leer lassen = unverändert)' : '' ?></span>
        <input type="password" name="password" autocomplete="new-password"></label>
      <div style="display:flex;align-items:flex-end;padding-bottom:9px">
        <label><input type="checkbox" name="active" style="width:auto" <?= ($eu === null || $eu['active']) ? 'checked' : '' ?>> aktiv</label>
      </div>
    </div>
    <p class="hint">Theken-Benutzer sehen nur ihre eigene Stelle und können ausschließlich Auswertungen ansehen und bestellen.</p>
    <div class="actions">
      <?php if ($eu): ?><a class="btn btn-ghost" href="?p=stammdaten">Neuer Benutzer</a><?php endif; ?>
      <button class="btn btn-primary">Speichern</button>
    </div>
  </form>
</div>
<div class="table-wrap">
<table><thead><tr><th>Benutzer</th><th>Name</th><th>Rolle</th><th>Stelle</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach (all('SELECT u.*, o.name AS oname FROM users u LEFT JOIN outlets o ON o.id=u.outlet_id ORDER BY u.username') as $r): ?>
  <tr><td><?= e($r['username']) ?></td><td><?= e($r['display_name']) ?></td>
      <td><?= e(ROLE_LABELS[$r['role']]) ?></td><td><?= e($r['oname'] ?? '—') ?></td>
      <td><?= $r['active'] ? '<span class="pill pill-ok">aktiv</span>' : '<span class="pill pill-grey">gesperrt</span>' ?></td>
      <td><a class="btn btn-ghost btn-sm" href="?p=stammdaten&amp;user=<?= $r['id'] ?>">bearbeiten</a></td></tr>
<?php endforeach; ?>
</tbody></table></div>

<h2>Warengruppen</h2>
<div class="card">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="cat_save">
    <div class="grid grid-3">
      <label class="field"><span>Name</span><input name="name" required></label>
      <label class="field"><span>Art</span>
        <select name="kind">
          <?php foreach (['getraenk'=>'Getränk','lebensmittel'=>'Lebensmittel','technik'=>'Technik','gas'=>'Gas','geraet'=>'Gerät','verbrauch'=>'Verbrauchsmaterial','sonstiges'=>'Sonstiges'] as $k=>$l): ?>
            <option value="<?= $k ?>"><?= $l ?></option>
          <?php endforeach; ?>
        </select></label>
      <label class="field"><span>Sortierung</span><input name="sort" value="100"></label>
    </div>
    <div class="actions"><button class="btn btn-primary">Warengruppe anlegen</button></div>
  </form>
</div>
<div class="table-wrap">
<table><thead><tr><th>Name</th><th>Art</th><th class="num">Sortierung</th><th class="num">Artikel</th></tr></thead><tbody>
<?php foreach (all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) n FROM categories c ORDER BY c.sort') as $c): ?>
  <tr><td><?= e($c['name']) ?></td><td><?= e($c['kind']) ?></td>
      <td class="num"><?= (int)$c['sort'] ?></td><td class="num"><?= (int)$c['n'] ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php render_foot();
