<?php
declare(strict_types=1);

require __DIR__ . '/../app/db.php';
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/booking.php';
require __DIR__ . '/../app/layout.php';

// Fehleranzeige nur, wenn in app/config.php ausdruecklich eingeschaltet.
// Auf einer produktiven Instanz bleibt das aus - Stacktraces verraten Pfade
// und Abfragen, die niemanden ausserhalb etwas angehen.
if (cfg('DEBUG') === '1') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
    set_exception_handler(function (Throwable $ex): void {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Fehler: ", $ex->getMessage(), "\n\n",
             $ex->getFile(), ':', $ex->getLine(), "\n\n",
             $ex->getTraceAsString();
    });
}

$page = preg_replace('/[^a-z_]/', '', (string)($_GET['p'] ?? 'bestand'));
if ($page === '') $page = 'bestand';

// Erster Start: solange es keinen Benutzer gibt, geht es nur zur Einrichtung.
$hasUsers = (int)scalar('SELECT COUNT(*) FROM users') > 0;
if (!$hasUsers && $page !== 'setup') {
    redirect('?p=setup');
}
if ($hasUsers && $page === 'setup') {
    redirect('?p=bestand');
}

if ($page === 'logout') {
    logout();
    redirect('?p=login');
}

$file = __DIR__ . '/pages/' . $page . '.php';
if (!is_file($file)) {
    http_response_code(404);
    $file = __DIR__ . '/pages/bestand.php';
    $page = 'bestand';
}

require $file;
