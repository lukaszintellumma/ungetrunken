<?php
declare(strict_types=1);

/**
 * Rollen
 *  admin  – Stammdaten, Benutzer, alle Buchungen, Storno
 *  user   – Buchungen erfassen und buchen, Artikel pflegen, Auswertungen
 *  theke  – nur eigene Auswertung und Bestellungen
 */
const ROLE_LABELS = [
    'admin' => 'Administration',
    'user'  => 'Lagerteam',
    'theke' => 'Theke',
];

function session_start_once(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $https,
        ]);
        session_start();
    }
}

function current_user(): ?array {
    session_start_once();
    if (empty($_SESSION['uid'])) return null;
    static $u = null;
    if ($u === null) {
        $u = one('SELECT u.*, o.name AS outlet_name
                    FROM users u LEFT JOIN outlets o ON o.id = u.outlet_id
                   WHERE u.id = ? AND u.active = 1', [$_SESSION['uid']]);
        if (!$u) { logout(); return null; }
    }
    return $u;
}

function login(string $username, string $password): bool {
    $u = one('SELECT * FROM users WHERE username = ? AND active = 1', [$username]);
    if (!$u || !password_verify($password, $u['password_hash'])) {
        usleep(300000); // Bruteforce leicht ausbremsen
        return false;
    }
    session_start_once();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    return true;
}

function logout(): void {
    session_start_once();
    $_SESSION = [];
    session_destroy();
}

function require_login(): array {
    $u = current_user();
    if (!$u) { header('Location: ?p=login'); exit; }
    return $u;
}

function has_role(string ...$roles): bool {
    $u = current_user();
    return $u !== null && in_array($u['role'], $roles, true);
}

function require_role(string ...$roles): array {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        echo '<p class="flash flash-error">Dafür fehlen dir die Rechte.</p>';
        exit;
    }
    return $u;
}

/** CSRF */
function csrf_token(): string {
    session_start_once();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void {
    session_start_once();
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(400);
        exit('Sitzung abgelaufen. Bitte die Seite neu laden und noch einmal versuchen.');
    }
}

/** Kurzmeldung für die nächste Seite hinterlegen */
function flash(string $msg, string $type = 'ok'): void {
    session_start_once();
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function take_flashes(): array {
    session_start_once();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}
