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
    '?q=timer+5+min', '?q=stoppuhr', '?q=kw', '?q=m%C3%BCnze', '?q=qr+hallo', '?a=asset&f=app.css', '?a=asset&f=app.js', '?a=manifest', '?a=sw', '?a=offline', '?a=opensearch',
    '?a=appicon&s=192', '?a=qr&d=hallo', '?a=export',
];
foreach (array_keys(smokeSettingsTabs()) as $t) $pages[] = '?a=settings&tab=' . $t;
foreach (['users', 'providers', 'usage', 'config', 'backup', 'update'] as $t) $pages[] = '?a=admin&tab=' . $t;
// Aussehen und Bangs: eigene Sicht, Sicht „für alle“, Bang-Suche, Teilliste, Vorschläge fürs Suchfeld
foreach (['?a=settings&tab=look&for=all', '?a=settings&tab=bangs&for=all', '?a=settings&tab=bangs&bq=wiki', '?a=settings&tab=bangs&bf=off',
          '?a=settings&tab=bangs&bf=dead&for=all', '?a=settings&tab=bangs&bq=yt&frag=1', '?a=settings&tab=bangs&edit=w', '?a=bangs&q=w', '?a=bangs&q=', '?a=settings&tab=bangs&bf=all&bl=xx&bp=2', '?a=settings&tab=bangs&bl=deen&frag=1', '?a=printcss&s=A6&m=0'] as $p) $pages[] = $p;
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

// Verwaltung → Aussehen gibt es nur noch unter Einstellungen
[$st, , $loc] = req($base . '?a=admin&tab=look');
check('Verwaltung → Aussehen leitet weiter', $st === 303 && strpos($loc, 'tab=look') !== false && strpos($loc, 'for=all') !== false, 'HTTP ' . $st . ' ' . $loc);

// Bangs: Vorschläge, anlegen, löschen, Grundzustand, für alle
$j = json_decode(req($base . '?a=bangs&q=yt')[1], true);
check('Bang-Vorschlag: !yt ist YouTube', ($j['exact']['n'] ?? '') === 'YouTube', json_encode($j));
$j = json_decode(req($base . '?a=bangs&q=wikip')[1], true);
check('Bang-Vorschläge: Liste', count($j['list'] ?? []) > 0, json_encode($j));
$j = json_decode(req($base . '?a=bangs&q=qr')[1], true);
check('Bang-Vorschlag: !qr ist das Werkzeug', ($j['exact']['d'] ?? '') !== '' && strpos(json_encode($j['exact']), 'QR') !== false, json_encode($j));
[, $html] = req($base . '?a=settings&tab=bangs');
// Ziel eines Bangs: Weiterleitung (intern) oder die kleine Weiter-Seite ohne Referrer (extern)
$go = function (string $q) use ($base): string {
    [$st, $body, $loc] = req($base . '?q=' . rawurlencode($q));
    if ($st === 200 && preg_match('/http-equiv="refresh" content="\d;url=([^"]+)"/', $body, $m)) return html_entity_decode($m[1], ENT_QUOTES);
    return (string)$loc;
};
$bang = function (array $post, string $for = '') use ($base, $html) {
    return req($base . '?a=settings&tab=bangs' . ($for !== '' ? '&for=' . $for : ''), ['csrf' => csrf($html)] + $post);
};
$bang(['do' => 'bang_cfg', 'bang_auto' => '', 'bang_check' => ''], 'all');
check('Hintergrund ohne Arbeit antwortet', req($base . '?a=bangtick')[0] === 200);
[$st] = $bang(['do' => 'bang_save', 't' => 'smk', 'n' => 'Smoke', 'u' => 'https://example.org/?q={q}']);
check('Eigenes Bang anlegen', $st === 303);
$loc = $go('!smk hallo welt');
check('Eigenes Bang leitet weiter', $loc === 'https://example.org/?q=hallo%20welt', $loc);
$bang(['do' => 'bang_del', 't' => 'yt']);
$loc = $go('!yt katzen');
check('Gelöschtes Bang ist normale Suche', strpos($loc, 'youtube') === false, $loc);
$bang(['do' => 'bang_save', 'orig' => 'g', 't' => 'g', 'n' => 'Geändert', 'u' => 'https://example.com/s?x={q}']);
$loc = $go('!g test');
check('Geändertes Bang', $loc === 'https://example.com/s?x=test', (string)$loc);
$bang(['do' => 'bang_restore']);
$loc = $go('!yt katzen');
check('Grundzustand: !yt wieder da', strpos((string)$loc, 'youtube.com') !== false, (string)$loc);
$loc = $go('!g test');
check('Grundzustand: !g wieder Google', strpos((string)$loc, 'google.com') !== false, (string)$loc);
$loc = $go('!smk x');
check('Grundzustand: eigenes Bang bleibt', $loc === 'https://example.org/?q=x', (string)$loc);
$bang(['do' => 'bang_save', 't' => 'alle', 'n' => 'Reserviert', 'u' => 'https://example.org/?q={q}'], 'all');
$bang(['do' => 'bang_save', 't' => 'fuer-alle', 'n' => 'Für alle', 'u' => 'https://example.net/?q={q}'], 'all');
$loc = $go('!fuer-alle x');
check('Bang für alle', $loc === 'https://example.net/?q=x', (string)$loc);
$bang(['do' => 'bang_del', 't' => 'ddg'], 'all');
$loc = $go('!ddg x');
check('Für alle gelöscht', strpos((string)$loc, 'duckduckgo') === false, (string)$loc);
[, $list] = req($base . '?a=settings&tab=bangs&bf=off&for=all');
check('Gelöschte für alle werden angezeigt', strpos($list, '!ddg') !== false);
$bang(['do' => 'bang_restore'], 'all');
$loc = $go('!ddg x');
check('Für alle wiederhergestellt', strpos((string)$loc, 'duckduckgo') !== false, (string)$loc);

// Aussehen: für mich, als Standard, für alle erzwingen
[, $html] = req($base . '?a=settings&tab=look');
$look = ['csrf' => csrf($html), 'do' => 'look', 'mode' => 'dark', 'pl' => 'papier', 'pd' => 'schiefer', 'font' => 'inter', 'fs' => '130', 'ft' => '100', 'fd' => '100', 'lh' => '158', 'gap' => 'normal', 'width' => 'normal'];
req($base . '?a=settings&tab=look', $look);
[, $page] = req($base);
check('Eigenes Aussehen: größere Schrift im CSS', preg_match('/a=look&amp;l=([^&"]+)/', $page, $lm) && strpos((string)base64_decode(strtr($lm[1], '-_', '+/')), '"fs":130') !== false);
req($base . '?a=settings&tab=look&for=all', ['scope' => 'default', 'fs' => '90'] + $look);
[, $page] = req($base);
check('Standard für alle: Administrator sieht ihn sofort', preg_match('/a=look&amp;l=([^&"]+)/', $page, $lm) && strpos((string)base64_decode(strtr($lm[1], '-_', '+/')), '"fs":90') !== false);

// Ein Formular abschicken: Einstellungen speichern und Kurzlink anlegen
[, $html] = req($base . '?a=settings');
[$st, , $loc] = req($base . '?a=settings', ['csrf' => csrf($html), 'do' => 'prefs', 'country' => 'DE', 'search_lang' => 'de', 'safesearch' => 'moderate', 'city' => 'Berlin']);
check('Einstellungen speichern', $st === 303, 'HTTP ' . $st);
[$st, , $loc] = req($base . '?a=tools', ['csrf' => csrf($html), 'do' => 'short_add', 'url' => 'https://example.org/ein/langer/pfad']);
check('Kurzlink anlegen', $st === 303 && strpos($loc, 'tool=kurzlinks') !== false, 'HTTP ' . $st . ' ' . $loc);

// Schnittstelle der Werkzeug-Apps (ohne Netz): Kurzlinks, Schnipsel, .htpasswd, unbekannte Aktion
$api = function (string $action, array $data) use ($base, $html): array {
    [$st, $body] = req($base . '?a=tools&api=' . $action, ['csrf' => csrf($html), 'data' => json_encode($data)]);
    $j = json_decode($body, true);
    return [$st, is_array($j) ? $j : []];
};
[$st, $j] = $api('short_create', ['url' => 'https://example.org/api?utm_source=x', 'code' => 'smoke-test', 'strip' => 1]);
check('API: Kurzlink mit Wunschname', ($j['ok'] ?? false) && ($j['link']['url'] ?? '') === 'https://example.org/api', 'HTTP ' . $st . ' ' . json_encode($j));
[$st, $loc] = [req($base . '?s=smoke-test')[0], req($base . '?s=smoke-test')[2]];
check('Kurzlink leitet weiter', $st === 302 && $loc === 'https://example.org/api', 'HTTP ' . $st . ' ' . $loc);
check('Kurzlink-Vorschau mit +', req($base . '?s=smoke-test%2B')[0] === 200);
[, $j] = $api('short_list', []);
check('API: Kurzlinks auflisten', count($j['links'] ?? []) >= 2);
[, $j] = $api('short_stats', ['code' => 'smoke-test', 'days' => 30]);
check('API: Statistik zählt die Aufrufe', array_sum($j['days'] ?? []) >= 2, json_encode($j));
[, $j] = $api('note_create', ['data' => '{"v":1,"iv":"AAAA","ct":"BBBB"}', 'hours' => 1, 'views' => 1]);
check('API: Schnipsel ohne Passwort abgelehnt', !($j['ok'] ?? true), json_encode($j));
$nauth = str_repeat('cd', 32);
[, $j] = $api('note_create', ['data' => '{"v":2,"iv":"AAAA","ct":"BBBB","salt":"CCCC"}', 'hours' => 1, 'views' => 1, 'pass' => 1, 'auth' => $nauth]);
check('API: Schnipsel anlegen (kurzer Link)', ($j['ok'] ?? false) && preg_match('/[?&]n=[A-Za-z0-9]{8}$/', $j['url'] ?? ''), json_encode($j));
$nid = substr((string)($j['url'] ?? ''), -8);
check('Schnipsel: Abholseite', req($base . '?n=' . $nid)[0] === 200);
$g0 = json_decode(req($base . '?a=noteget', ['id' => $nid, 'auth' => str_repeat('00', 32)])[1], true);
check('Schnipsel: falsches Passwort abgelehnt', !($g0['ok'] ?? true) && !empty($g0['pass']));
$g1 = json_decode(req($base . '?a=noteget', ['id' => $nid, 'auth' => $nauth])[1], true);
$g2 = json_decode(req($base . '?a=noteget', ['id' => $nid, 'auth' => $nauth])[1], true);
check('Schnipsel: einmal abholen, danach weg', ($g1['ok'] ?? false) && !($g2['ok'] ?? true));
[, $j] = $api('htpasswd', ['user' => 'anna', 'pass' => 'geheim']);
check('API: .htpasswd mit bcrypt', strpos((string)($j['line'] ?? ''), 'anna:$2y$') === 0, json_encode($j));
[$st, $j] = $api('gibtsnicht', []);
check('API: unbekannte Aktion', $st === 404 && ($j['ok'] ?? true) === false);
[$st] = req($base . '?a=tools&api=short_list', ['data' => '{}']);
check('API: ohne CSRF-Token abgelehnt', $st === 303, 'HTTP ' . $st);
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
