<?php
/**
 * Private Suche, Einstiegspunkt.
 *
 * PRIVATE_DIR zeigt auf den Ordner mit Konfiguration, Code und Daten.
 * Am sichersten liegt er AUSSERHALB des Webroots, z. B. neben httpdocs.
 * Liegt index.php in httpdocs/suche/:
 *     define('PRIVATE_DIR', dirname(__DIR__, 2) . '/suche-private');
 * Liegt index.php direkt in httpdocs/:
 *     define('PRIVATE_DIR', dirname(__DIR__) . '/suche-private');
 * Bleibt er hier drin, sperrt ihn die .htaccess für Zugriffe von außen.
 */
declare(strict_types=1);

define('PRIVATE_DIR', __DIR__ . '/private');

require PRIVATE_DIR . '/src/bootstrap.php';
app_run();
