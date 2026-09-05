# ungetrunken

Warenwirtschaft für Zeltlager: ein Zentrallager, beliebig viele Theken, Bars und Cafés,
Buchungen in Gebinden, EK-Erfassung und Auswertungen. PHP 8.3 + MariaDB, komplett self-hosted.

## Starten

```bash
cp .env.example .env      # Passwörter eintragen
docker compose up -d --build
```

Danach `http://<host>:8080` aufrufen. Beim ersten Start legst du den Administrator selbst an –
es gibt bewusst kein voreingestelltes Passwort. Die Beispielartikel und ‑stellen aus `db/02_seed.sql`
sind schon da; wenn du leer starten willst, lösche die Datei vor dem ersten `up`.

Auf einem Proxmox-LXC mit Docker läuft das genauso wie auf einer VM. Für den Betrieb im Lager
gehört ein Reverse Proxy mit TLS davor (Caddy oder Traefik) – die App setzt `httponly`- und
`samesite`-Cookies, aber kein `secure`-Flag, weil sie nicht wissen kann, ob sie hinter HTTPS steht.

## Installation auf klassischem Webhosting (Febas, Hetzner, All-Inkl …)

Docker ist nicht Pflicht. Die App ist reines PHP 8.1+ mit PDO/MySQL, ohne Composer, ohne Build-Schritt.
Voraussetzungen: PHP 8.1 oder neuer mit `pdo_mysql`, eine MySQL- oder MariaDB-Datenbank, Apache mit `.htaccess`.
Das erfüllt praktisch jedes Standardpaket.

1. **Datenbank anlegen** im Panel (LiveConfig bei Febas, konsoleH bei Hetzner). Zugangsdaten notieren.
2. **Schema einspielen**: in phpMyAdmin `db/01_schema.sql` importieren, danach optional `db/02_seed.sql`
   für die Beispielartikel.
3. **Konfiguration**: `app/config.sample.php` nach `app/config.php` kopieren und die Zugangsdaten eintragen.
   Auf Webhosting gibt es keine Umgebungsvariablen – die Datei ersetzt sie. Bei Docker hat die Umgebung Vorrang,
   dieselbe Codebasis läuft also in beiden Welten.
4. **Dateien hochladen** und das **Zielverzeichnis der Domain auf `public/` setzen**. Beide Panels können das
   (LiveConfig: Website → Verzeichnis; konsoleH: Domain → Einstellungen). Dann liegen `app/` und `db/` außerhalb
   des Webroots und sind von außen gar nicht erreichbar.
5. **Falls sich das Zielverzeichnis nicht ändern lässt**: lade alles direkt in den Webroot. Die mitgelieferte
   `.htaccess` im Hauptordner leitet dann nach `public/` um, und die `.htaccess`-Dateien in `app/` und `db/`
   sperren den Zugriff. Das funktioniert, ist aber die zweitbeste Lösung.
6. `https://deine-domain/` aufrufen und den Administrator anlegen.

### Worauf du achten solltest

* **PHP-Version prüfen.** Manche Pakete laufen noch auf 8.0 oder älter. Bei Hetzner lässt sich die Version
  per `.htaccess` je Ordner setzen, bei Febas im LiveConfig.
* **HTTPS aktivieren.** Beide Hoster liefern Let's-Encrypt-Zertifikate mit. Die App setzt das `secure`-Flag
  auf dem Session-Cookie automatisch, sobald sie über HTTPS läuft – ohne TLS gehen Passwörter im Klartext
  über die Leitung, und auf einem Lagerplatz-WLAN ist das keine theoretische Sorge.
* **Erreichbarkeit auf dem Platz.** Ein gehostetes Setup braucht Internet am Lager. Wenn die Mobilfunkabdeckung
  wackelig ist, ist eine Kiste vor Ort (Docker auf einem Mini-PC im Lager-WLAN) die robustere Wahl –
  dann tippt niemand Bestände in ein Formular, das gerade nicht lädt.

### Was ohne eigenen Server fehlt

Automatische Datenbank-Backups musst du selbst einrichten. Beide Panels bieten Backups an, aber ein
`mysqldump` per Cronjob in einen Ordner außerhalb des Webroots kostet fünf Minuten und rettet dir das Lager,
falls jemand eine Inventur verbockt.

## Versionierung mit GitLab

```bash
cd ungetrunken
git init -b main
git add .
git commit -m "ungetrunken: erste lauffähige Fassung"
git remote add origin git@gitlab.deine-domain.de:lukas/ungetrunken.git
git push -u origin main
```

**Was nicht ins Repository gehört:** `app/config.php` und `.env` stehen in der `.gitignore`.
Die Vorlage `app/config.sample.php` wird versioniert, die ausgefüllte Datei nicht – sonst liegen
Datenbankpasswörter in der Historie, und aus einer Git-Historie bekommt man sie nur mit
`filter-repo` und einem Force-Push wieder heraus.

Wenn versehentlich doch ein Passwort im ersten Commit gelandet ist: nicht nur die Datei löschen
und neu committen, sondern das Passwort in der Datenbank ändern. Die alte Version bleibt sonst
in jedem Klon des Repositories lesbar.

### Arbeit am Mandanten-Umbau

Der begonnene Umbau liegt in `_wip_mandanten/`. Sauberer ist ein eigener Branch:

```bash
git switch -c feature/mandanten
git mv _wip_mandanten/tenant.php app/tenant.php
```

So bleibt `main` jederzeit der Stand, den du auf ein laufendes Lager spielen kannst.

### Automatischer Upload zu All-Inkl

`.gitlab-ci.yml` bringt drei Jobs mit: PHP-Syntaxprüfung, ein Testlauf von Schema und Seed gegen
eine frische MariaDB, und einen Deploy-Job per FTP. Der Deploy läuft **nur manuell und nur auf
`main`** – während eines Lagers soll kein Push die Instanz überschreiben, auf der gerade gebucht wird.

Dafür in GitLab unter *Settings → CI/CD → Variables* anlegen, jeweils **Masked** und **Protected**:

| Variable | Inhalt |
|---|---|
| `FTP_HOST` | FTP-Server aus dem KAS |
| `FTP_USER` | FTP-Benutzer |
| `FTP_PASS` | FTP-Passwort |
| `FTP_TARGET` | Zielpfad, z. B. `/ungetrunken` |

Der Job spiegelt mit `--delete`, räumt auf dem Server also auch auf. `app/config.php` und
`public/ping.php` sind davon ausgenommen und bleiben stehen. Vor dem ersten Lauf lohnt ein Blick
auf `FTP_TARGET`: ein falscher Pfad plus `--delete` räumt das falsche Verzeichnis leer.

Wenn dein All-Inkl-Tarif SSH kann, ist `rsync` über SSH die bessere Wahl als FTP – dann
Deploy-Key statt Passwort und verschlüsselte Übertragung ohne `ftp:ssl-allow`-Gefummel.

## Rollen

| Rolle | Darf |
|---|---|
| **Administration** | alles, zusätzlich Stammdaten, Benutzer und Storno |
| **Lagerteam** | Buchungen erfassen und buchen, Artikel und Gebinde pflegen, Bestellungen bearbeiten |
| **Theke** | nur die eigene Stelle sehen, Auswertungen ansehen, bestellen |

Theken-Benutzer brauchen zwingend eine zugeordnete Ausgabestelle. Sie sehen ausschließlich
Bestände und Bewegungen dieser Stelle – die Filterung passiert serverseitig, nicht nur in der Oberfläche.

## Datenmodell in Kürze

Bestände werden **nie** als Feld gespeichert, sondern aus `stock_movements` summiert. Jede Bewegung
hängt an einem Beleg, dadurch ist jeder Bestand bis zur Ursprungsbuchung nachvollziehbar und
ein Storno ist einfach ein gespiegelter Gegenbeleg statt einer Korrektur am Bestand.

```
outlets ──┬── stock_movements ──── bookings ──── booking_items
          │         │                                  │
products ─┴─────────┘                                  │
    └── packagings ─────────────────────────────────────┘
```

Alle Mengen und Preise beziehen sich intern auf die **Basiseinheit** eines Artikels
(1 Flasche, 1 Stück, 1 kg). Gebinde sind nur Umrechnungsfaktoren darauf:

| Gebinde | factor |
|---|---|
| Einzelflasche | 1 |
| Kasten 12 × 0,5 l | 12 |
| Palette (60 Kästen) | 720 |

Dadurch kannst du eine Palette zubuchen, kastenweise an die Bar ausgeben und flaschenweise
abbuchen, ohne dass die Bestände auseinanderlaufen.

## Buchungstypen

| Typ | Wirkung |
|---|---|
| Zubuchung | Wareneingang, `+` auf das Ziel |
| Ausgabe / Lieferschein | `−` Lager, `+` Theke |
| Rückbuchung | `−` Theke, `+` Lager |
| Umbuchung | zwischen zwei Stellen |
| Abbuchung | Verbrauch, Bruch, Schwund: nur `−` |
| Inventur | Menge ist der **gezählte Endstand**, gebucht wird die Differenz |

Ein Beleg entsteht zuerst als Entwurf und lässt sich beliebig oft speichern. Erst „Buchen“ schreibt
die Lagerbewegungen, vergibt die Belegnummer (`26-AUS-0042`) und friert den Beleg ein. Buchungen,
die einen Bestand ins Minus ziehen würden, werden abgelehnt und nennen den betroffenen Artikel.

## Barcode

Das Feld „Nr. / Scan“ in jeder Positionszeile nimmt Artikelnummer **oder** EAN. Ein USB-Scanner
verhält sich wie eine Tastatur mit Enter am Ende – die Zeile springt danach direkt ins Mengenfeld,
sodass sich Wareneingang scannen-tippen-scannen-tippen erfassen lässt. EANs hinterlegst du je
Gebinde unter *Artikel → Gebinde*, damit der Scan eines Kastens auch als Kasten gebucht wird.

## Inventar vs. Verbrauchsware

Kühlschränke, Zapfanlagen, CO2-Flaschen, Grills und Bräter legst du als Artikel mit dem Haken
*Inventar* an. Sie laufen über dieselben Buchungen (so weißt du jederzeit, welcher Kühlschrank
an welcher Theke steht), werden aber bei Meldebeständen und Verbrauchsauswertungen ausgeklammert.

## Was ich bewusst weggelassen habe

* **Kein Kassensystem.** Verkaufspreise, Umsätze und der „Coins“-Teil aus deinem Screenshot fehlen –
  falls Abrechnung dazukommen soll, ist der saubere Anschluss eine `sales`-Tabelle, die Abbuchungen erzeugt.
* **Kein Leergut-Konto.** Pfand ist am Artikel erfasst, aber Leergut wird nicht getrennt geführt.
  Für ein Lager mit vielen Kästen lohnt sich das schnell.
* **Kein Mehrmandanten-Betrieb.** Eine Instanz = ein Lager.
