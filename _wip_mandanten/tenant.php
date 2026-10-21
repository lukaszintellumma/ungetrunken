<?php
declare(strict_types=1);

/**
 * Mandantenkontext.
 *
 * Grundregel für den gesamten Code: jede Abfrage auf outlets, categories,
 * products, packagings, bookings, orders und stock_movements filtert auf
 * tid(). Keine Ausnahme. Wo ein Datensatz per ID aus einem Formular kommt,
 * wird zusätzlich geprüft, dass er zum aktiven Mandanten gehört – IDs aus
 * der URL sind Benutzereingaben, keine Berechtigung.
 */

/** Alle Mandanten, in denen der Benutzer arbeiten darf */
function my_tenants(): array {
    $u = current_user();
    if (!$u) return [];
    static $cache = null;
    if ($cache !== null) return $cache;

    if ($u['is_superadmin']) {
        $cache = all('SELECT t.*, "admin" AS role, NULL AS outlet_id
                        FROM tenants t WHERE t.active = 1 ORDER BY t.name');
    } else {
        $cache = all('SELECT t.*, ut.role, ut.outlet_id
                        FROM user_tenants ut JOIN tenants t ON t.id = ut.tenant_id
                       WHERE ut.user_id = ? AND t.active = 1 ORDER BY t.name', [$u['id']]);
    }
    return $cache;
}

/** Aktiver Mandant inklusive Rolle des Benutzers darin */
function current_tenant(): ?array {
    session_start_once();
    $list = my_tenants();
    if (!$list) return null;

    $wanted = (int)($_SESSION['tenant_id'] ?? 0);
    foreach ($list as $t) {
        if ((int)$t['id'] === $wanted) return $t;
    }
    $_SESSION['tenant_id'] = (int)$list[0]['id'];
    return $list[0];
}

/** ID des aktiven Mandanten. Ohne Mandant bricht die Anfrage ab. */
function tid(): int {
    $t = current_tenant();
    if (!$t) {
        http_response_code(403);
        exit('Für dieses Konto ist kein Mandant freigeschaltet.');
    }
    return (int)$t['id'];
}

/** Rolle des Benutzers im aktiven Mandanten */
function tenant_role(): string {
    $t = current_tenant();
    return $t ? (string)$t['role'] : '';
}

function switch_tenant(int $tenantId): bool {
    foreach (my_tenants() as $t) {
        if ((int)$t['id'] === $tenantId) {
            session_start_once();
            $_SESSION['tenant_id'] = $tenantId;
            return true;
        }
    }
    return false;
}

/** Theken-Benutzer: die Stelle, auf die er festgelegt ist (sonst null) */
function my_outlet_id(): ?int {
    $t = current_tenant();
    if (!$t || $t['role'] !== 'theke') return null;
    return $t['outlet_id'] ? (int)$t['outlet_id'] : null;
}

/**
 * Prüft, dass ein Datensatz zum aktiven Mandanten gehört.
 * Wird für jede ID aus URL oder Formular aufgerufen.
 */
function owned(string $table, int $id): bool {
    static $allowed = ['outlets','categories','products','packagings','bookings','orders','stock_movements'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Tabelle nicht für Mandantenprüfung freigegeben.');
    }
    if ($id <= 0) return false;
    return (bool)scalar("SELECT 1 FROM `$table` WHERE id = ? AND tenant_id = ?", [$id, tid()]);
}

/** Wie owned(), bricht aber ab statt false zu liefern */
function require_owned(string $table, int $id): void {
    if (!owned($table, $id)) {
        http_response_code(404);
        exit('Dieser Datensatz gehört nicht zum aktiven Mandanten.');
    }
}
