<?php
/**
 * Suchmaschine – holt die Bang-Liste von Kagi (https://github.com/kagisearch/bangs, MIT-Lizenz) und schreibt sie als
 * app/lists/bangs.tsv.gz. Diese Datei liegt jeder Installation bei; laufende Installationen holen sich die Liste
 * außerdem einmal pro Woche selbst (abschaltbar unter Einstellungen → Bangs → Für alle).
 *
 *   php tools/bangs-import.php                 von GitHub laden
 *   php tools/bangs-import.php bangs.json      aus einer heruntergeladenen Datei
 */

if (PHP_SAPI !== 'cli') exit;
define('SM_DATA_DIR', sys_get_temp_dir() . '/sm-bangs-' . bin2hex(random_bytes(4)));
define('SM_NO_RUN', true);
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';
require dirname(__DIR__) . '/index.php';

$src = $argv[1] ?? '';
$json = $src !== '' ? (string)file_get_contents($src) : smHttpGet(SM_BANG_SRC, 16000000);
$rows = smBangParseKagi($json);
if (count($rows) < 1000) {
    fwrite(STDERR, "Zu wenige Bangs gelesen (" . count($rows) . ").\n");
    exit(1);
}
$gz = gzencode(smBangToTsv($rows), 9);
file_put_contents(SM_APP . '/lists/bangs.tsv.gz', $gz);
echo count($rows), " Bangs, ", round(strlen($gz) / 1024), " KB → app/lists/bangs.tsv.gz\n";
