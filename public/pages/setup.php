<?php
declare(strict_types=1);

$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string)($_POST['username'] ?? ''));
    $name = trim((string)($_POST['display_name'] ?? ''));
    $pw   = (string)($_POST['password'] ?? '');
    $pw2  = (string)($_POST['password2'] ?? '');

    if ($user === '' || $name === '') {
        $err = 'Benutzername und Anzeigename dürfen nicht leer sein.';
    } elseif (strlen($pw) < 8) {
        $err = 'Das Passwort braucht mindestens 8 Zeichen.';
    } elseif ($pw !== $pw2) {
        $err = 'Die beiden Passwörter stimmen nicht überein.';
    } else {
        q('INSERT INTO users (username, display_name, password_hash, role) VALUES (?,?,?,"admin")',
          [$user, $name, password_hash($pw, PASSWORD_DEFAULT)]);
        flash('Administrator angelegt. Melde dich jetzt an.');
        redirect('?p=login');
    }
}

render_head('Einrichtung', null, 'setup');
?>
<div class="login-box">
  <h1>ungetrunken einrichten</h1>
  <p class="sub">Lege den ersten Administrator an. Weitere Benutzer und Theken ergänzt du danach in den Stammdaten.</p>
  <?php if ($err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endif; ?>
  <form method="post" class="card">
    <div class="grid">
      <label class="field"><span>Benutzername</span>
        <input name="username" required autofocus autocomplete="username"></label>
      <label class="field"><span>Anzeigename</span>
        <input name="display_name" placeholder="z. B. Lagerleitung" required></label>
      <label class="field"><span>Passwort</span>
        <input type="password" name="password" required autocomplete="new-password"></label>
      <label class="field"><span>Passwort wiederholen</span>
        <input type="password" name="password2" required autocomplete="new-password"></label>
    </div>
    <div class="actions"><button class="btn btn-primary">Administrator anlegen</button></div>
  </form>
</div>
<?php render_foot();
