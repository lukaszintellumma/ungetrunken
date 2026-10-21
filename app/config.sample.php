<?php
/**
 * Für Webhosting ohne Umgebungsvariablen:
 * Diese Datei nach app/config.php kopieren und ausfüllen.
 * Wenn Umgebungsvariablen gesetzt sind (Docker), haben die Vorrang.
 */
return [
    'DB_HOST'   => 'localhost',
    'DB_PORT'   => '3306',
    'DB_NAME'   => 'dein_datenbankname',
    'DB_USER'   => 'dein_datenbankuser',
    'DB_PASS'   => 'dein_datenbankpasswort',
    'CAMP_NAME' => 'Zeltlager',

    // Auf '1' setzen, um Fehlermeldungen im Browser zu sehen.
    // Danach unbedingt wieder auf '0'.
    'DEBUG'     => '0',
];
