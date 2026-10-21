<?php
declare(strict_types=1);

function app_config(): array {
    static $conf = null;
    if ($conf === null) {
        $file = __DIR__ . '/config.php';
        $conf = is_file($file) ? (array)require $file : [];
    }
    return $conf;
}

function cfg(string $key, string $default = ''): string {
    // Umgebungsvariable schlaegt Konfigurationsdatei (Docker vor Webhosting)
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;

    $conf = app_config();
    if (isset($conf[$key]) && $conf[$key] !== '') return (string)$conf[$key];

    return $default;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        cfg('DB_HOST', 'db'),
        cfg('DB_PORT', '3306'),
        cfg('DB_NAME', 'ungetrunken')
    );
    $pdo = new PDO($dsn, cfg('DB_USER', 'ungetrunken'), cfg('DB_PASS', ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function one(string $sql, array $params = []): ?array {
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function all(string $sql, array $params = []): array {
    return q($sql, $params)->fetchAll();
}

function scalar(string $sql, array $params = []) {
    return q($sql, $params)->fetchColumn();
}

/** HTML-Escaping */
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Zahl deutsch formatieren */
function num($v, int $dec = 0): string {
    return number_format((float)$v, $dec, ',', '.');
}

function money($v): string {
    return number_format((float)$v, 2, ',', '.') . ' €';
}
