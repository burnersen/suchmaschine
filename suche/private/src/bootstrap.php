<?php
declare(strict_types=1);
defined('PRIVATE_DIR') || exit;

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('Diese Suche braucht PHP 8.0 oder neuer. Bitte im Hosting-Panel umstellen.');
}
if (!function_exists('curl_init')) {
    http_response_code(500);
    exit('Die PHP-Erweiterung cURL fehlt. Bitte im Hosting-Panel aktivieren.');
}

$configFile = PRIVATE_DIR . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('config.php fehlt. Bitte private/config.example.php nach private/config.php kopieren und den API-Key eintragen.');
}

$GLOBALS['config'] = array_merge([
    'brave_api_key'    => '',
    'api_url'          => 'https://api.search.brave.com/res/v1/web/search',
    'cache_ttl'        => 900,
    'monthly_limit'    => 900,
    'country'          => 'DE',
    'search_lang'      => 'de',
    'ui_lang'          => 'de-DE',
    'safesearch'       => 'moderate',
    'results_per_page' => 10,
    'max_pages'        => 5,
    'open_in_new_tab'  => false,
    'session_days'     => 30,
    'site_name'        => 'Suche',
], (array) require $configFile);

date_default_timezone_set('Europe/Berlin');

define('DATA_DIR', PRIVATE_DIR . '/data');
define('CACHE_DIR', PRIVATE_DIR . '/cache');
define('SESSION_DIR', PRIVATE_DIR . '/sessions');

foreach ([DATA_DIR, CACHE_DIR, SESSION_DIR] as $dir) {
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        http_response_code(500);
        exit('Ordner ' . basename($dir) . ' kann nicht angelegt werden. Bitte Schreibrechte für den private-Ordner prüfen.');
    }
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/storage.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/brave.php';
require __DIR__ . '/app.php';

session_bootstrap();
