<?php
/**
 * Suchmaschine – deine eigene, werbefreie Suchmaschine zum Selbsthosten
 *
 * Diese Datei ist nur der Einstieg. Der eigentliche Code liegt in app/:
 *   app/lib/     Funktionen (Sprache, Datenbank, Konten, Brave-Anbindung, Seiten, Update)
 *   app/views/   HTML-Vorlagen
 *   app/assets/  CSS, JavaScript, Symbole (werden über diese Datei ausgeliefert)
 *
 * PHP mit SQLite auf dem Server, schlichtes JavaScript und CSS im Browser. Keine Frameworks,
 * keine Abhängigkeiten, nichts wird von fremden Servern nachgeladen (außer Vorschaubildern über Braves Bild-Proxy).
 * Der Code enthält KEINE Zugangsdaten. Alles Geheime (auch der Brave API-Key) entsteht beim Einrichten
 * und liegt im Ordner data/. Den Ordner data/ niemals veröffentlichen oder ins Git legen.
 *
 * Lizenz: GNU Affero General Public License v3.0, siehe Datei LICENSE.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Europe/Berlin');

define('SM_VERSION', '1.0.1');
define('SM_ROOT', __DIR__);
define('SM_APP', SM_ROOT . '/app');
define('SM_DATA_DIR', SM_ROOT . '/data');
define('SM_COOKIE', 'SMSESS');
define('SM_SESSION_DAYS', 90);      // so lange bleibt ein Gerät angemeldet, solange es benutzt wird
define('SM_REPO', 'burnersen/suchmaschine');

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Suchmaschine braucht PHP 8.0 oder neuer / needs PHP 8.0 or newer.\n");
}

require SM_APP . '/lib/lang.php';
require SM_APP . '/lib/util.php';
require SM_APP . '/lib/db.php';
require SM_APP . '/lib/auth.php';
require SM_APP . '/lib/brave.php';
require SM_APP . '/lib/update.php';
require SM_APP . '/lib/pages.php';

smRun();
