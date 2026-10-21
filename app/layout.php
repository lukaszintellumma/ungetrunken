<?php
declare(strict_types=1);

function nav_items(?array $u): array {
    if (!$u) return [];
    if ($u['role'] === 'theke') {
        return [
            'bestand'      => 'Meine Theke',
            'bestellungen' => 'Bestellen',
            'auswertung'   => 'Auswertungen',
        ];
    }
    $items = [
        'bestand'      => 'Bestand',
        'buchung'      => 'Buchung erfassen',
        'belege'       => 'Belege',
        'bestellungen' => 'Bestellungen',
        'produkte'     => 'Artikel',
        'auswertung'   => 'Auswertungen',
    ];
    if ($u['role'] === 'admin') {
        $items['stammdaten'] = 'Stammdaten';
    }
    return $items;
}

function render_head(string $title, ?array $u, string $page): void {
    ?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · ungetrunken</title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<?php if ($u): ?>
<nav class="topbar">
  <span class="brand">ungetrunken</span>
  <?php foreach (nav_items($u) as $key => $label): ?>
    <a href="?p=<?= e($key) ?>" class="<?= $page === $key ? 'active' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
  <span class="spacer"></span>
  <span class="who"><?= e($u['display_name']) ?> · <?= e(ROLE_LABELS[$u['role']]) ?><?=
      $u['outlet_name'] ? ' · ' . e($u['outlet_name']) : '' ?></span>
  <a href="?p=logout">Abmelden</a>
</nav>
<?php endif; ?>
<div class="wrap">
<?php
    foreach (take_flashes() as $f) {
        printf('<div class="flash flash-%s">%s</div>', e($f['type']), e($f['msg']));
    }
}

function render_foot(): void {
    ?>
</div>
<div class="footer">ungetrunken · <?= e(cfg('CAMP_NAME', 'Zeltlager')) ?> · <?= date('Y') ?></div>
</body>
</html><?php
}
