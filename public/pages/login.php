<?php
declare(strict_types=1);

if (current_user()) redirect('?p=bestand');

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login(trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''))) {
        redirect('?p=bestand');
    }
    $err = 'Benutzername oder Passwort stimmt nicht.';
}

render_head('Anmelden', null, 'login');
?>
<div class="login-box">
  <h1>ungetrunken</h1>
  <p class="sub">Was noch da ist, im Blick: <?= e(cfg('CAMP_NAME', 'Zeltlager')) ?></p>
  <?php if ($err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endif; ?>
  <form method="post" class="card">
    <div class="grid">
      <label class="field"><span>Benutzername</span>
        <input name="username" required autofocus autocomplete="username"></label>
      <label class="field"><span>Passwort</span>
        <input type="password" name="password" required autocomplete="current-password"></label>
    </div>
    <div class="actions"><button class="btn btn-primary">Anmelden</button></div>
  </form>
</div>
<?php render_foot();
