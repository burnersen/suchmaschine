<?php
/**
 * Suchmaschine – aus dem Quellcode die Release-Dateien bauen (nur Kommandozeile, Nutzer brauchen das nie)
 *
 *   php tools/build-release.php                       prüfen, testen, bauen
 *   php tools/build-release.php --skip-tests          ohne die Tests (schneller, nur zum Ausprobieren)
 *   php tools/build-release.php --require-signature   Abbruch, wenn nicht unterschrieben werden kann (für Releases)
 *
 * Ergebnis im Ordner dist/:
 *   suchmaschine-install.php       DIE eine Datei für Nutzer: hochladen, im Browser aufrufen, fertig
 *   suchmaschine-install.php.sig   Unterschrift dazu (nur mit geheimem Schlüssel, siehe BUILD.md)
 *   suchmaschine-<Version>.zip     dieselben Dateien zum Entpacken von Hand
 *   SHA256SUMS                     Prüfsummen, damit jeder seinen Nachbau mit dem Release vergleichen kann
 *
 * Die Schritte im Einzelnen (ausführlich in BUILD.md):
 *   1. Version aus index.php lesen (define('SM_VERSION', …)).
 *   2. Dateiliste: index.php, LICENSE und alles unter app/, nach Namen sortiert.
 *   3. Jede PHP-Datei mit „php -l“ auf Syntaxfehler prüfen.
 *   4. Tests: tests/run.php (Funktionen) und tests/smoke.php (Suchmaschine wirklich starten und alle Seiten aufrufen).
 *   5. Installer = tools/installer-template.php + nach __halt_compiler(); eine Zeile pro Datei: „Pfad TAB SHA-256 TAB Base64“.
 *   6. Den fertigen Installer zurücklesen und jede Prüfsumme kontrollieren, dann mit tests/smoke.php echt installieren.
 *   7. Unterschreiben, falls die Umgebungsvariable SM_SIGN_KEY gesetzt ist (Ed25519, siehe tools/sign-key.php).
 *   8. ZIP und SHA256SUMS schreiben.
 *
 * Der Bau ist wiederholbar: Gleicher Quellcode ergibt Byte für Byte denselben Installer (keine Zeitstempel, feste Reihenfolge).
 * Nur das ZIP enthält Dateidaten und kann darum abweichen.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$args = array_slice($argv, 1);
$skipTests = in_array('--skip-tests', $args, true);
$needSig = in_array('--require-signature', $args, true);
$root = dirname(__DIR__);
$dist = $root . '/dist';

function step(string $msg): void { echo "\n▸ " . $msg . "\n"; }
function fail(string $msg): void { fwrite(STDERR, "\n✗ " . $msg . "\n"); exit(1); }
function run(string $cmd): void
{
    passthru($cmd, $code);
    if ($code !== 0) fail('Fehlgeschlagen: ' . $cmd);
}

// 1. Version
$index = (string)file_get_contents($root . '/index.php');
if (!preg_match("~define\\('SM_VERSION',\\s*'([0-9][0-9A-Za-z.\\-]*)'\\)~", $index, $m)) fail('SM_VERSION nicht in index.php gefunden.');
$version = $m[1];
echo "Suchmaschine $version bauen\n";

// 2. Dateiliste
$files = ['index.php', 'LICENSE'];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile()) $files[] = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
}
sort($files, SORT_STRING);
foreach ($files as $path) {
    if (!preg_match('~^[A-Za-z0-9._\-]+(/[A-Za-z0-9._\-]+)*$~', $path)) fail("Ungültiger Dateiname (nur Buchstaben, Ziffern, . _ -): $path");
}

// 3. Syntax
step('Syntax prüfen (php -l)');
$n = 0;
foreach (array_merge($files, ['tools/installer-template.php', 'tests/run.php', 'tests/smoke.php']) as $path) {
    if (substr($path, -4) !== '.php') continue;
    $out = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/' . $path) . ' 2>&1', $out, $code);
    if ($code !== 0) fail("Syntaxfehler in $path:\n" . implode("\n", $out));
    $n++;
}
echo "  $n Dateien ohne Fehler\n";

// 4. Tests
if ($skipTests) {
    step('Tests übersprungen (--skip-tests)');
} else {
    step('Tests: Funktionen');
    run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/run.php'));
    step('Tests: Suchmaschine starten und alle Seiten aufrufen');
    run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/smoke.php'));
}

// 5. Installer zusammensetzen
step('Installer bauen');
if (!is_dir($dist) && !mkdir($dist, 0755, true)) fail('dist/ konnte nicht angelegt werden.');
$tpl = (string)file_get_contents(__DIR__ . '/installer-template.php');
if (substr_count($tpl, '__halt_compiler();') !== 1) fail('Die Installer-Vorlage muss genau einmal mit __halt_compiler(); enden.');
$out = str_replace('@@VERSION@@', $version, rtrim($tpl)) . "\n";
$sums = [];
foreach ($files as $path) {
    $bin = (string)file_get_contents($root . '/' . $path);
    $sums[$path] = hash('sha256', $bin);
    $out .= $path . "\t" . $sums[$path] . "\t" . base64_encode($bin) . "\n";
}
$installer = $dist . '/suchmaschine-install.php';
file_put_contents($installer, $out);
printf("  dist/suchmaschine-install.php  (%d Dateien, %d KB)\n", count($files), (int)round(strlen($out) / 1024));

// 6. Zurücklesen und kontrollieren: genau die Dateien, genau die Inhalte
$raw = (string)file_get_contents($installer);
$back = [];
foreach (explode("\n", substr($raw, strpos($raw, "\n__halt_compiler();") + strlen("\n__halt_compiler();"))) as $line) {
    if (trim($line) === '') continue;
    [$p, $h, $b] = explode("\t", trim($line));
    $bin = base64_decode($b, true);
    if ($bin === false || hash('sha256', $bin) !== $h || ($sums[$p] ?? '') !== $h) fail("Installer kaputt bei $p");
    $back[] = $p;
}
if ($back !== $files) fail('Im Installer fehlen Dateien oder es sind zu viele.');
echo "  zurückgelesen, alle Prüfsummen stimmen\n";
if (!$skipTests) {
    step('Tests: die fertige Installationsdatei wirklich installieren');
    run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/smoke.php') . ' ' . escapeshellarg($installer));
}

// 7. Unterschrift
step('Unterschrift');
@unlink($installer . '.sig');
$pub = preg_match("~const SM_UPDATE_PUBKEY = '([A-Za-z0-9+/=]*)';~", (string)file_get_contents($root . '/app/lib/update.php'), $pm) ? $pm[1] : '';
$secret = (string)getenv('SM_SIGN_KEY');
if ($secret !== '') {
    if (!function_exists('sodium_crypto_sign_detached')) fail('Zum Unterschreiben fehlt die PHP-Erweiterung sodium.');
    $sk = base64_decode(trim($secret), true);
    if ($sk === false || strlen($sk) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) fail('SM_SIGN_KEY ist kein gültiger geheimer Schlüssel (siehe tools/sign-key.php).');
    $sig = sodium_crypto_sign_detached($raw, $sk);
    if ($pub !== '' && !sodium_crypto_sign_verify_detached($sig, $raw, (string)base64_decode($pub))) {
        fail('Der geheime Schlüssel passt nicht zu SM_UPDATE_PUBKEY in app/lib/update.php.');
    }
    file_put_contents($installer . '.sig', base64_encode($sig) . "\n");
    echo "  dist/suchmaschine-install.php.sig geschrieben\n";
} elseif ($needSig && $pub !== '') {
    fail('SM_UPDATE_PUBKEY ist gesetzt, aber SM_SIGN_KEY fehlt. Ohne Unterschrift würden alle Installationen das Update ablehnen.');
} else {
    echo $pub !== '' ? "  Hinweis: ohne SM_SIGN_KEY nicht unterschrieben. Zum Installieren reicht das, für automatische Updates nicht.\n"
                     : "  keine (SM_UPDATE_PUBKEY ist leer, Updates prüfen nur die Prüfsummen)\n";
}

// 8. ZIP zum Entpacken von Hand, alles in einem Ordner suchmaschine/
step('ZIP und Prüfsummen');
$zipName = 'suchmaschine-' . $version . '.zip';
foreach ((array)glob($dist . '/suchmaschine-*.zip') as $old) @unlink($old);
if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($dist . '/' . $zipName, ZipArchive::CREATE) !== true) fail('ZIP konnte nicht angelegt werden.');
    foreach (array_merge($files, ['README.md', 'README.en.md']) as $path) {
        if (is_file($root . '/' . $path)) $zip->addFile($root . '/' . $path, 'suchmaschine/' . $path);
    }
    $zip->close();
    printf("  dist/%s  (%d KB)\n", $zipName, (int)round(filesize($dist . '/' . $zipName) / 1024));
} else {
    echo "  Hinweis: PHP-Erweiterung zip fehlt, das ZIP wurde ausgelassen.\n";
}
$list = '';
foreach (['suchmaschine-install.php', 'suchmaschine-install.php.sig', $zipName] as $f) {
    if (is_file($dist . '/' . $f)) $list .= hash_file('sha256', $dist . '/' . $f) . '  ' . $f . "\n";
}
file_put_contents($dist . '/SHA256SUMS', $list);
echo "  dist/SHA256SUMS\n";
echo "\nFertig. Für Nutzer zählt nur: dist/suchmaschine-install.php\n";
