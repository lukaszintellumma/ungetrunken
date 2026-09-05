<?php
declare(strict_types=1);

/**
 * Rollen gelten je Mandant (Tabelle user_tenants):
 *  admin  – Stammdaten, Benutzer, alle Buchungen, Storno
 *  user   – Buchungen erfassen und buchen, Artikel pflegen, Auswertungen
 *  theke  – nur eigene Auswertung und Bestellungen
 *
 * Daneben gibt es users.is_superadmin: der Betreiber der Instanz, der
 * Mandanten anlegt und in jedem Mandanten Adminrechte hat.
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
        $u = one('SELECT * FROM users WHERE id = ? AND active = 1', [$_SESSION['uid']]);
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
    unset($_SESSION['tenant_id']);
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

/** Rolle im aktiven Mandanten prüfen */
function has_role(string ...$roles): bool {
    return current_user() !== null && in_array(tenant_role(), $roles, true);
}

function require_role(string ...$roles): array {
    $u = require_login();
    if (!in_array(tenant_role(), $roles, true)) {
        http_response_code(403);
        echo '<p class="flash flash-error">Dafür fehlen dir die Rechte in diesem Mandanten.</p>';
        exit;
    }
    return $u;
}

function require_superadmin(): array {
    $u = require_login();
    if (empty($u['is_superadmin'])) {
        http_response_code(403);
        echo '<p class="flash flash-error">Das darf nur der Betreiber der Instanz.</p>';
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
