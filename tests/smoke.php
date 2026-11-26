<?php
/**
 * Suchmaschine – Rauchtest: startet die Suchmaschine wirklich und ruft alle Seiten auf (nur Kommandozeile)
 *
 *   php tests/smoke.php                       testet den Quellcode in diesem Ordner
 *   php tests/smoke.php dist/suchmaschine-install.php   testet die fertige Installationsdatei
 *
 * Ablauf: Kopie in einen Wegwerf-Ordner, eingebauten PHP-Webserver starten, Administrator anlegen, anmelden,
 * jede Seite und jedes Werkzeug abrufen. Fehlschlag, wenn eine Seite nicht mit 200 antwortet oder PHP eine Warnung,
 * einen Hinweis oder einen Fehler meldet. Braucht kein Netz und keinen API-Key.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!function_exists('curl_init')) {
    fwrite(STDERR, "Die PHP-Erweiterung curl fehlt.\n");
    exit(1);
}

$root = dirname(__DIR__);
$work = sys_get_temp_dir() . '/sm-smoke-' . bin2hex(random_bytes(4));
mkdir($work, 0700, true);
$installer = $argv[1] ?? '';

if ($installer !== '') {
    // Die Installationsdatei wie ein Nutzer verwenden: hochladen (hier: kopieren) und im Browser aufrufen
    if (!is_file($installer)) { fwrite(STDERR, "Datei nicht gefunden: $installer\n"); exit(1); }
    copy($installer, $work . '/suchmaschine-install.php');
} else {
    // Quellcode kopieren: index.php, LICENSE, app/
    copy($root . '/index.php', $work . '/index.php');
    copy($root . '/LICENSE', $work . '/LICENSE');
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $to = $work . '/' . substr($f->getPathname(), strlen($root) + 1);
        if (!is_dir(dirname($to))) mkdir(dirname($to), 0755, true);
        copy($f->getPathname(), $to);
    }
}

// Freien Port suchen und den eingebauten Webserver starten. Alle PHP-Meldungen landen im Protokoll.
$sock = stream_socket_server('tcp://127.0.0.1:0');
$port = (int)substr(strrchr(stream_socket_get_name($sock, false), ':'), 1);
fclose($sock);
$log = $work . '/server.log';
$cmd = escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -d log_errors=1 -d error_log=' . escapeshellarg($log)
     . ' -d error_reporting=' . E_ALL . ' -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($work);
$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', $work . '/stderr.log', 'w']], $pipes);
$base = 'http://127.0.0.1:' . $port . '/';
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) usleep(100000);

$jar = $work . '/cookies.txt';
$fails = [];
$count = 0;

/** Abruf mit Cookies. Liefert [Status, Inhalt, Weiterleitung]. */
function req(string $url, ?array $post = null, array $headers = []): array
{
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => array_merge(['Accept-Language: de', 'Sec-Fetch-Site: same-origin'], $headers)]);
    if ($post !== null) curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
    $body = (string)curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $loc = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return [$status, $body, $loc];
}

function check(string $name, bool $ok, string $detail = ''): void
{
    global $fails, $count;
    $count++;
    if (!$ok) $fails[] = $name . ($detail !== '' ? '  → ' . $detail : '');
}

function csrf(string $html): string
{
    return preg_match('/name="csrf" value="([0-9a-f]+)"/', $html, $m) ? $m[1] : '';
}

if ($installer !== '') {
    [$st, $html] = req($base . 'suchmaschine-install.php');
    check('Installer: Seite lädt', $st === 200 && stripos($html, '<form') !== false, 'HTTP ' . $st);
    preg_match_all('/<input type="hidden" name="([^"]+)" value="([^"]*)"/', $html, $m, PREG_SET_ORDER);
    $fields = [];
    foreach ($m as $x) $fields[$x[1]] = html_entity_decode($x[2]);
    [$st] = req($base . 'suchmaschine-install.php', $fields + ['smi_go' => '1']);
    check('Installer: entpackt', is_file($work . '/index.php') && is_dir($work . '/app/lib'), 'HTTP ' . $st . ', index.php fehlt');
}

// Einrichten, dann sind wir als Administrator angemeldet
[$st, $html] = req($base);
check('Einrichtungsseite', $st === 200 && strpos($html, 'name="pass2"') !== false, 'HTTP ' . $st);
[$st, , $loc] = req($base . '?a=setup', ['name' => 'Admin', 'pass' => 'geheim-123', 'pass2' => 'geheim-123', 'key' => '']);
check('Einrichten', $st === 303 && strpos($loc, 'a=admin') !== false, 'HTTP ' . $st . ' ' . $loc);

$pages = [
    '', '?a=help', '?a=tools', '?a=settings', '?a=admin', '?q=2%2B3*4', '?q=10+km+in+meilen', '?q=feiertage+2026', '?q=w%C3%BCrfel', '?q=passwort',
    '?q=timer+5+min', '?q=stoppuhr', '?q=kw', '?q=m%C3%BCnze', '?q=qr+hallo', '?a=asset&f=app.css', '?a=asset&f=app.js', '?a=manifest', '?a=opensearch',
    '?a=appicon&s=192', '?a=qr&d=hallo', '?a=export',
];
foreach (array_keys(smokeSettingsTabs()) as $t) $pages[] = '?a=settings&tab=' . $t;
foreach (['users', 'providers', 'usage', 'config', 'look', 'backup', 'update'] as $t) $pages[] = '?a=admin&tab=' . $t;
// Jedes Werkzeug aus der Übersicht, dazu jede Datei, die eine Werkzeugseite lädt (Bausteine, Apps, Stylesheet)
[, $overview] = req($base . '?a=tools');
preg_match_all('/data-tool="([a-z]+)"/', $overview, $tm);
check('Werkzeuge: Übersicht listet mehr als 40 Werkzeuge', count(array_unique($tm[1])) > 40, (string)count(array_unique($tm[1])));
$assets = [];
foreach (array_unique($tm[1]) as $t) {
    $pages[] = '?a=tools&tool=' . $t;
    [, $th] = req($base . '?a=tools&tool=' . $t);
    if (preg_match_all('/\?a=asset&amp;f=([a-z0-9\/.]+)&amp;v=/', $th, $am)) foreach ($am[1] as $f) $assets[$f] = true;
}
foreach (array_keys($assets) as $f) $pages[] = '?a=asset&f=' . $f;
$pages[] = '?a=asset&f=vendor/zxing.js';
$pages[] = '?a=tools&tool=feiertage&ics=1&y=2027&st=BY';
[$st] = req($base . '?a=tools&tool=losen');
check('Altes Werkzeug Losen leitet zur Übersicht', $st === 303, 'HTTP ' . $st);
$pages[] = '?a=tools&tool=rechner&x=(12%2C5%2B7)*3';
$pages[] = '?a=tools&tool=datum&m=diff&d1=2026-01-01&d2=2026-12-24';
$pages[] = '?a=tools&tool=datum&m=work&d1=2026-12-01&d2=2026-12-31&st=BY';
$pages[] = '?a=tools&tool=feiertage&m=bridge&y=2027&st=NW';
$pages[] = '?a=tools&tool=kalender&year=1&y=2027';
$pages[] = '?a=tools&tool=zufall&z=lotto';
$pages[] = '?a=tools&tool=zufall&z=card';
$pages[] = '?a=tools&tool=qr&qr=https%3A%2F%2Fexample.org';

function smokeSettingsTabs(): array
{
    return ['search' => 1, 'look' => 1, 'domains' => 1, 'bangs' => 1, 'lenses' => 1, 'browser' => 1, 'account' => 1];
}

$admin = '';
foreach ($pages as $p) {
    [$st, $html] = req($base . $p);
    check('Seite ' . ($p === '' ? '/' : $p), $st === 200, 'HTTP ' . $st);
    if (preg_match('/<b>(Warning|Notice|Fatal error|Deprecated)<\/b>|Uncaught /', $html)) check('Seite ' . $p . ' ohne PHP-Meldung', false, 'PHP-Meldung im HTML');
    if ($p === '?a=admin&tab=config') $admin = $html;
}

// Ein Formular abschicken: Einstellungen speichern und Kurzlink anlegen
[, $html] = req($base . '?a=settings');
[$st, , $loc] = req($base . '?a=settings', ['csrf' => csrf($html), 'do' => 'prefs', 'country' => 'DE', 'search_lang' => 'de', 'safesearch' => 'moderate', 'city' => 'Berlin']);
check('Einstellungen speichern', $st === 303, 'HTTP ' . $st);
[$st, , $loc] = req($base . '?a=tools', ['csrf' => csrf($html), 'do' => 'short_add', 'url' => 'https://example.org/ein/langer/pfad']);
check('Kurzlink anlegen', $st === 303 && strpos($loc, 'tool=kurzlinks') !== false, 'HTTP ' . $st . ' ' . $loc);
[$st, $html] = req($base . '?a=tools&tool=kurzlinks');
check('Kurzlink in der Liste', strpos($html, 'example.org/ein/langer/pfad') !== false);
// Ohne gültiges Formular-Kennzeichen wird nichts gespeichert
[$st, , $loc] = req($base . '?a=settings', ['csrf' => 'falsch', 'do' => 'prefs']);
check('Schutz gegen fremde Formulare', $st === 303 && strpos($loc, 'a=settings') !== false);

// Abmelden: danach ist alles außer der Anmeldung gesperrt
[$st] = req($base . '?a=logout', ['csrf' => csrf($admin)]);
[$st, , $loc] = req($base . '?a=settings');
check('Nach dem Abmelden gesperrt', $st === 303 && strpos($loc, 'a=login') !== false, 'HTTP ' . $st . ' ' . $loc);

// Server beenden und das Protokoll prüfen
proc_terminate($proc);
proc_close($proc);
$logText = is_file($log) ? (string)file_get_contents($log) : '';
check('Keine PHP-Warnungen oder Fehler im Protokoll', !preg_match('/PHP (Warning|Notice|Fatal|Deprecated|Parse)/', $logText), mb_substr($logText, 0, 1500));

// Aufräumen
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($work, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
    $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
}
@rmdir($work);

if ($fails) {
    fwrite(STDERR, "\n" . count($fails) . ' von ' . $count . " Prüfungen fehlgeschlagen:\n  ✗ " . implode("\n  ✗ ", $fails) . "\n\n");
    exit(1);
}
echo 'Rauchtest' . ($installer !== '' ? ' (Installationsdatei)' : '') . ': alle ' . $count . " Prüfungen bestanden.\n";
