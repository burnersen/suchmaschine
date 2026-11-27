<?php
/**
 * Suchmaschine – deine eigene, werbefreie Suchmaschine zum Selbsthosten
 *
 * Diese Datei ist nur der Einstieg. Der eigentliche Code liegt in app/:
 *   app/lib/     Funktionen, eine Datei pro Thema (Liste unten), die Seiten in page_*.php
 *   app/views/   HTML-Vorlagen, Teile mit _ am Anfang werden in andere eingebunden
 *   app/lang/    Übersetzungen (en.php)
 *   app/assets/  CSS, JavaScript, Schriften, Töne, Symbole (werden über diese Datei ausgeliefert)
 *
 * Wie aus diesem Quellcode die eine Installationsdatei wird, steht in BUILD.md.
 *
 * PHP mit SQLite auf dem Server, schlichtes JavaScript und CSS im Browser. Keine Frameworks,
 * keine Abhängigkeiten, nichts wird von fremden Servern nachgeladen (außer Vorschaubildern über Braves Bild-Proxy,
 * Googles Vorschaubilder holt der eigene Server).
 * Der Code enthält KEINE Zugangsdaten. Alles Geheime (API-Keys, Login-Tokens) entsteht beim Einrichten
 * und liegt im Ordner data/. Den Ordner data/ niemals veröffentlichen oder ins Git legen.
 *
 * Lizenz: GNU Affero General Public License v3.0, siehe Datei LICENSE.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Europe/Berlin');

define('SM_VERSION', '2.4.0');
define('SM_ROOT', __DIR__);
define('SM_APP', SM_ROOT . '/app');
defined('SM_DATA_DIR') || define('SM_DATA_DIR', SM_ROOT . '/data');   // die Tests nehmen einen eigenen Ordner
define('SM_COOKIE', 'SMSESS');
define('SM_SESSION_DAYS', 90);      // so lange bleibt ein Gerät angemeldet, solange es benutzt wird
define('SM_REPO', 'burnersen/suchmaschine');

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Suchmaschine braucht PHP 8.0 oder neuer / needs PHP 8.0 or newer.\n");
}

// Reihenfolge egal, die Dateien enthalten nur Funktionen und Konstanten
foreach ([
    'lang', 'util', 'db', 'auth',                          // Grundlagen: Sprache, Helfer, Datenbank, Konten
    'brave', 'brightdata', 'providers', 'filters', 'domains', 'bangs',   // Suchen: Suchdienste (Brave, Google über Bright Data), Filter, Domain-Listen, Bangs und Linsen
    'instant', 'instant_more', 'qr', 'imgproxy', 'reader',                // Sofort-Antworten, QR-Codes, Bild-Proxy, Lesemodus
    'net', 'shortlinks', 'tools_api',                      // Werkzeuge: sicherer Abruf, Kurzlinks und Schnipsel, Schnittstelle der Werkzeug-Apps
    'prefs', 'look', 'branding', 'backup', 'update',       // Einstellungen, Aussehen, Logo, Sicherung, Update
    'serve', 'router',                                     // Dateien ausliefern, Ablauf
    'page_search', 'page_tools', 'page_read', 'page_settings', 'page_admin',   // die einzelnen Seiten
] as $smLib) require SM_APP . '/lib/' . $smLib . '.php';
unset($smLib);

// Für die automatischen Tests (tests/run.php): nur laden, nichts ausgeben
if (!defined('SM_NO_RUN')) smRun();
