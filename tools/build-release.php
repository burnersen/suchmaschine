<?php
/**
 * Suchmaschine – Release bauen (nur für die Kommandozeile, Nutzer brauchen das nie)
 *
 *   php tools/build-release.php
 *
 * Legt im Ordner dist/ zwei Dateien an:
 *   suchmaschine-install.php     eine einzige Datei zum Hochladen, entpackt sich im Browser selbst
 *   suchmaschine-<Version>.zip   alle Dateien zum Entpacken von Hand
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$dist = $root . '/dist';

$index = (string)file_get_contents($root . '/index.php');
if (!preg_match("~define\\('SM_VERSION',\\s*'([0-9][0-9A-Za-z.\\-]*)'\\)~", $index, $m)) {
    fwrite(STDERR, "SM_VERSION nicht in index.php gefunden.\n");
    exit(1);
}
$version = $m[1];

// Was zum Programm gehört: index.php, LICENSE und alles unter app/
$files = ['index.php', 'LICENSE'];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile()) $files[] = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
}
sort($files, SORT_STRING);

// Kurz prüfen, ob alles syntaktisch sauber ist
foreach ($files as $path) {
    if (substr($path, -4) !== '.php') continue;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/' . $path) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        fwrite(STDERR, "Syntaxfehler in $path:\n" . implode("\n", $out) . "\n");
        exit(1);
    }
}

if (!is_dir($dist) && !mkdir($dist, 0755, true)) {
    fwrite(STDERR, "dist/ konnte nicht angelegt werden.\n");
    exit(1);
}

// 1) Installer: Vorlage + eingepackte Dateien (Pfad, SHA-256, Base64 pro Zeile)
$tpl = (string)file_get_contents(__DIR__ . '/installer-template.php');
if (substr_count($tpl, '__halt_compiler();') !== 1) {
    fwrite(STDERR, "Die Installer-Vorlage muss genau einmal mit __halt_compiler(); enden.\n");
    exit(1);
}
$out = str_replace('@@VERSION@@', $version, rtrim($tpl)) . "\n";
foreach ($files as $path) {
    $bin = (string)file_get_contents($root . '/' . $path);
    $out .= $path . "\t" . hash('sha256', $bin) . "\t" . base64_encode($bin) . "\n";
}
$installer = $dist . '/suchmaschine-install.php';
file_put_contents($installer, $out);
printf("%s  (%d Dateien, %d KB)\n", 'dist/suchmaschine-install.php', count($files), (int)round(strlen($out) / 1024));

// 2) ZIP zum Entpacken von Hand, alles in einem Ordner suchmaschine/
if (class_exists('ZipArchive')) {
    $zipPath = $dist . '/suchmaschine-' . $version . '.zip';
    if (is_file($zipPath)) unlink($zipPath);
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        fwrite(STDERR, "ZIP konnte nicht angelegt werden.\n");
        exit(1);
    }
    foreach (array_merge($files, ['README.md', 'README.en.md']) as $path) {
        if (is_file($root . '/' . $path)) $zip->addFile($root . '/' . $path, 'suchmaschine/' . $path);
    }
    $zip->close();
    printf("%s  (%d KB)\n", 'dist/suchmaschine-' . $version . '.zip', (int)round(filesize($zipPath) / 1024));
} else {
    echo "Hinweis: PHP-Erweiterung zip fehlt, das ZIP wurde ausgelassen.\n";
}
