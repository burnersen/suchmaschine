<?php
/**
 * Suchmaschine – automatische Tests (nur Kommandozeile, ohne Abhängigkeiten)
 *
 *   php tests/run.php
 *
 * Lädt das Programm wie index.php, aber ohne eine Seite auszugeben, und prüft Funktionen, die ohne Netz und
 * ohne Suchdienst auskommen: Rechner, Einheiten, Datum, Feiertage, Zufall, Sicherheitsprüfungen, Übersetzungen,
 * das Format der Installationsdatei. Die Datenbank liegt dabei in einem Wegwerf-Ordner.
 * Endet mit Code 0, wenn alles stimmt, sonst mit 1 und einer Liste der Fehler.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$tmp = sys_get_temp_dir() . '/sm-test-' . bin2hex(random_bytes(4));
define('SM_DATA_DIR', $tmp);
define('SM_NO_RUN', true);
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';
require dirname(__DIR__) . '/index.php';

$fails = [];
$count = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $fails, $count;
    $count++;
    if (!$ok) $fails[] = $name . ($detail !== '' ? '  → ' . $detail : '');
}

function same(string $name, $want, $got): void
{
    check($name, $want === $got, 'erwartet ' . var_export($want, true) . ', bekommen ' . var_export($got, true));
}

function card(string $q): array
{
    return smInstant($q, null) ?? [];
}

/* ---------- Rechner ---------- */
same('Rechner: Punkt vor Strich', '14', card('2+3*4')['result'] ?? null);
same('Rechner: Klammern', '58,5', card('(12,5 + 7) * 3')['result'] ?? null);
same('Rechner: Prozent aufschlagen', '238', card('200 + 19%')['result'] ?? null);
same('Rechner: Prozent von', '12', card('15% von 80')['result'] ?? null);
same('Rechner: Wurzel', '4', card('sqrt 16')['result'] ?? null);
same('Rechner: Potenz (mit Tausenderpunkt)', '1.024', card('2^10')['result'] ?? null);
check('Rechner: Division durch null gibt keine Zahl', !isset(card('1/0')['result']) || card('1/0')['result'] !== '0');
same('Rechner: normaler Suchbegriff ist keine Rechnung', null, smInstant('katzen videos', null));

/* ---------- Einheiten ---------- */
$u = card('10 km in meilen');
same('Einheiten: Ziel', 'mi', $u['to'] ?? null);
check('Einheiten: 10 km ≈ 6,214 mi', abs(($u['result'] ?? 0) - 6.2137) < 0.001);
same('Einheiten: 20 °C = 68 °F', 68, (int)round(card('20 °c in f')['result'] ?? 0));

/* ---------- Datum, Kalenderwoche, Feiertage ---------- */
$d = card('tage zwischen 2026-01-01 und 2026-12-31');
same('Datum: Tage zwischen', 364, $d['days'] ?? null);
same('Kalenderwoche: 2026 hat 53 Wochen', 53, card('kw 1 2026')['weeks'] ?? null);
same('Ostern 2026', '2026-04-05', date('Y-m-d', smEaster(2026)));
same('Ostern 2027', '2027-03-28', date('Y-m-d', smEaster(2027)));
$names = array_map(function ($h) { return date('m-d', $h[0]) . ' ' . $h[1]; }, smHolidays(2026));
check('Feiertage 2026: Karfreitag am 3. April', in_array('04-03 Karfreitag', $names, true), implode(', ', $names));
check('Feiertage 2026: Buß- und Bettag am 18. November', in_array('11-18 Buß- und Bettag', $names, true));

/* ---------- Zufall ---------- */
for ($i = 0; $i < 50; $i++) {
    $w = card('würfel 3w6');
    if (count($w['rolls'] ?? []) !== 3 || min($w['rolls']) < 1 || max($w['rolls']) > 6 || array_sum($w['rolls']) !== $w['sum']) {
        check('Würfel: 3 Würfe zwischen 1 und 6', false, json_encode($w));
        break;
    }
}

/* ---------- Sicherheit: Ausgabe, Domains, Bangs, Tokens ---------- */
same('Escapen: nur <strong> bleibt', 'a <strong>b</strong> &lt;script&gt;', smHl('a <strong>b</strong> <script>'));
same('Escapen: Anführungszeichen', '&quot;&#039;&lt;&gt;&amp;', h('"\'<>&'));
same('Domain säubern', 'example.org', smCleanDomain('https://www.Example.org/pfad?x=1'));
same('Domain: Unsinn wird leer', '', smCleanDomain('javascript:alert(1)'));
same('Bang-Adresse: nur http(s)', '', smCleanBangUrl('javascript:alert({q})'));
check('Bang-Adresse: https mit {q} geht', smCleanBangUrl('https://example.org/?q={q}') !== '');
check('Token: zu kurz', smTokenCheck('abc') !== '');
check('Token: Muster wird abgelehnt', smTokenCheck('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa') !== '');
same('Token: erzeugter Token ist gültig', '', smTokenCheck(smTokenGenerate()));
same('Weiterleitung nach dem Login: fremde Seite', smBase(), smSafeNext('//evil.example/'));
same('Weiterleitung nach dem Login: eigene Seite', smBase() . '?a=tools', smSafeNext(smBase() . '?a=tools'));
check('IP: privat ist nicht öffentlich', !smPublicIp('192.168.1.1') && !smPublicIp('127.0.0.1') && !smPublicIp('10.0.0.1') && !smPublicIp('100.64.1.1'));
check('IP: öffentlich', smPublicIp('1.1.1.1'));

/* ---------- Verschlüsseln (Login-Token in der Datenbank) ---------- */
same('Verschlüsseln und zurück', 'geheim-123', smUnseal(smSeal('geheim-123')));

/* ---------- QR-Code ---------- */
$svg = SmQr::encode('https://example.org/')->svg();
check('QR: liefert SVG', strpos($svg, '<svg') === 0 && strpos($svg, '</svg>') !== false);

/* ---------- Werkzeuge: jedes Werkzeug hat eine Anfrage oder ein eigenes Formular ---------- */
foreach (smToolGroups() as $items) {
    foreach (array_keys($items) as $id) {
        check('Werkzeug „' . $id . '“: Name nur aus Kleinbuchstaben', (bool)preg_match('/^[a-z]{2,12}$/', $id));
    }
}
$_GET = ['n' => '100', 'from' => 'EUR', 'to' => 'USD'];
same('Werkzeug Währungen: Anfrage', '100 eur in usd', smToolQuery('waehrung'));
$_GET = ['m' => 'add', 'd1' => '2026-01-31', 'k' => '1', 'u' => 'monate'];
same('Werkzeug Datum: Anfrage', '2026-01-31 + 1 monate', smToolQuery('datum'));
$_GET = [];

/* ---------- 2.0: neue Sofort-Antworten ---------- */
for ($i = 0; $i < 30; $i++) {
    $l = card('lotto');
    if (count($l['main'] ?? []) !== 6 || count(array_unique($l['main'])) !== 6 || min($l['main']) < 1 || max($l['main']) > 49 || $l['extra'][0] < 0 || $l['extra'][0] > 9) {
        check('Lotto: 6 verschiedene Zahlen von 1 bis 49 und Superzahl', false, json_encode($l));
        break;
    }
}
$ej = card('eurojackpot');
check('Eurojackpot: 5 + 2 Zahlen', count($ej['main'] ?? []) === 5 && count($ej['extra'] ?? []) === 2 && max($ej['extra']) <= 12);
same('Wochentag: 24.12.2027 ist ein Freitag', '5', date('N', card('wochentag 24.12.2027')['a'] ?? 0));
same('Arbeitstage Dezember 2026 in Bayern', 22, card('arbeitstage zwischen 1.12.2026 und 31.12.2026 bayern')['work'] ?? null);
same('Arbeitstage addieren: Freitag 18.12.2026 + 1 → Montag 21.12.', '2026-12-21', date('Y-m-d', card('18.12.2026 + 1 arbeitstage')['b'] ?? 0));
same('Arbeitstage addieren überspringt Weihnachten', '2026-12-28', date('Y-m-d', card('24.12.2026 + 1 arbeitstage')['b'] ?? 0));
$br = card('brückentage 2027 nrw');
check('Brückentage 2027: Vorschläge da, höchstens 5 Urlaubstage', !empty($br['list']) && max(array_column($br['list'], 'vacation')) <= 5 && min(array_column($br['list'], 'total')) >= 4);
same('Jahreskalender: 2027 Neujahr ist Feiertag', 'Neujahr', card('jahreskalender 2027')['hol']['2027-01-01'] ?? null);
same('„kalender“ allein ist eine normale Suche', null, smInstant('kalender', null));

/* ---------- 2.0: Kurzlinks und Schnipsel ---------- */
same('Tracking-Parameter entfernen', 'https://example.org/a?x=1#top', smStripTracking('https://example.org/a?utm_source=nl&x=1&fbclid=abc#top'));
check('Wunschname: zu kurz', !smShortCodeOk('ab') && smShortCodeOk('urlaub-2027') && !smShortCodeOk('-x1'));
smQ("INSERT OR IGNORE INTO users (id, name, pass, is_admin, created_at) VALUES (77, 'Test77', 'x', 0, 0)");
[$c1, $e1] = smShortCreate(77, 'https://example.org/eins', ['code' => 'Urlaub']);
same('Kurzlink mit Wunschname', 'Urlaub', $c1);
[, $e2] = smShortCreate(77, 'https://example.org/zwei', ['code' => 'urlaub']);
check('Wunschname doppelt (ohne Groß/klein)', $e2 !== '');
same('Kurzlink finden ohne Groß/klein', 'Urlaub', smShortFind('URLAUB')['code'] ?? null);
same('Kurzlink: Ziel ändern', '', smShortUpdate(77, 'Urlaub', ['url' => 'https://example.org/neu']));
same('Kurzlink: neues Ziel', 'https://example.org/neu', smShortFind('Urlaub')['url'] ?? null);
smShortCount('Urlaub');
$st30 = smShortStats(77, 'Urlaub', 30);
same('Kurzlink: Statistik zählt den Tag', 1, end($st30) ?: 0);
same('Kurzlink: Statistik hat 30 Tage', 30, count(smShortStats(77, 'Urlaub', 30)));
[$c3] = smShortCreate(77, 'https://example.org/limit', ['max_hits' => 1]);
smQ('UPDATE shortlinks SET hits = 1 WHERE code = ?', [$c3]);
same('Kurzlink: Grenze erreicht', 'used', smShortPublic(smShortFind($c3))['state']);
check('Kurzlink auf Kurzlink wird abgelehnt', smCleanShortTarget(smOrigin() . '?s=abc') === '');
smShortDelete(77, 'Urlaub');
same('Kurzlink gelöscht', null, smShortFind('Urlaub'));
[, $ne] = smNoteCreate(77, '{"iv":"<x>","ct":"a"}', 24, 1, false, '');
check('Schnipsel: kaputter Inhalt wird abgelehnt', $ne !== '');
[$nid] = smNoteCreate(77, '{"v":1,"iv":"AAAA","ct":"BBBB"}', 24, 2, false, 'Test');
same('Schnipsel: erster Abruf, noch 1 übrig', 1, smNoteFetch($nid)['left'] ?? null);
same('Schnipsel: zweiter Abruf, danach weg', 0, smNoteFetch($nid)['left'] ?? null);
same('Schnipsel: ist gelöscht', null, smNoteFetch($nid));
$auth = str_repeat('ab', 32);
[$pid] = smNoteCreate(77, '{"v":1,"iv":"AAAA","ct":"BBBB","salt":"CCCC"}', 24, 1, true, '', $auth);
same('Schnipsel mit Passwort: falscher Prüfwert verbraucht nichts', 'pass', smNoteFetch($pid, str_repeat('00', 32)));
check('Schnipsel mit Passwort: richtiger Prüfwert', is_array(smNoteFetch($pid, $auth)));

/* ---------- 2.0: Netz und Werkzeuge ---------- */
check('IPv6: privat, lokal und versteckte IPv4 sind nicht öffentlich', !smNetIpPublic('::1') && !smNetIpPublic('fd00::1') && !smNetIpPublic('fe80::1') && !smNetIpPublic('::ffff:192.168.1.1') && !smNetIpPublic('64:ff9b::a00:1') && !smNetIpPublic('2002:c0a8:0101::1'));
check('IPv6: öffentlich', smNetIpPublic('2606:4700:4700::1111') && smNetIpPublic('1.1.1.1'));
same('Adresse ergänzen', 'https://example.org/', smNetUrl('example.org'));
same('Adresse: Zugangsdaten werden abgelehnt', '', smNetUrl('https://user:pw@example.org/'));
same('Adresse zusammensetzen', 'https://example.org/a/c', smNetAbs('https://example.org/a/b', 'c'));
same('Werkzeug-Abkürzung !qr', ['qr', 'text'], smToolBang('qr'));
same('Werkzeug-Abkürzung !json', ['json', 'text'], smToolBang('json'));
same('Unbekannte Abkürzung', null, smToolBang('gibtsnicht'));
$sec = smSecurityHeaders([['Strict-Transport-Security', 'max-age=31536000'], ['X-Content-Type-Options', 'nosniff']], 'https://example.org/');
same('Sicherheits-Kopfzeilen: HSTS gut', 'ok', $sec[0][1]);
same('Sicherheits-Kopfzeilen: CSP fehlt', 'bad', $sec[1][1]);
require_once dirname(__DIR__) . '/tools/js-strings.php';
$jsMiss = smJsMissing(SM_APP . '/assets');
check('Werkzeug-Apps: alle Texte haben eine englische Fassung', !$jsMiss, count($jsMiss) . ' fehlen, z. B. „' . implode('“, „', array_slice(array_keys($jsMiss), 0, 5)) . '“');

/* ---------- Bangs: große Liste, Schichten, Formatierung ---------- */
check('Bangs: Liste aus der Datei geladen', smBangSysCount() > 10000, (string)smBangSysCount());
same('Bang !w (Extra) geht zu Wikipedia', 'https://de.wikipedia.org/w/index.php?search=Berlin%20Mitte', smBangTarget('!w Berlin Mitte', null)['to'] ?? null);
same('Bang hinten', 'https://de.wikipedia.org/w/index.php?search=Berlin', smBangTarget('Berlin !w', null)['to'] ?? null);
same('Bang aus Kagi: Leerzeichen als +', 'https://caniuse.com/?search=css%20grid', smBangTarget('!caniuse css grid', null)['to'] ?? null);
same('Bang aus Kagi: ohne Suchbegriff zur Startseite', 'https://github.com/', smBangTarget('!gh', null)['to'] ?? null);
check('Bang aus Kagi mit Zweitkürzel', strpos(smBangTarget('!bom avatar', null)['to'] ?? '', 'boxofficemojo.com') !== false);
check('Bang mit Muster ($1, $2)', (smBangTarget('!rsr php hello world', null)['to'] ?? '') === 'https://reddit.com/r/php/search?q=hello+world&restrict_sr=on');
$int = smBangTarget('!4chan test', null);
check('Bang mit site: sucht hier', ($int['internal'] ?? false) && strpos($int['to'], 'site%3A4chan.org') !== false, json_encode($int));
check('Werkzeug geht der Kagi-Liste vor (!qr)', strpos(smBangTarget('!qr hallo', null)['to'] ?? '', 'tool=qr') !== false);
same('Unbekanntes Kürzel bleibt Suchtext', null, smBangTarget('!gibtsganzsichernicht x', null));
same('Kürzel mit Umlaut erlaubt', 'tåg', smCleanBangTrigger('!TÅG'));
same('Kürzel mit Schrägstrich abgelehnt', '', smCleanBangTrigger('/.j'));
$me = ['prefs' => ['bangs' => [['t' => 'qr', 'n' => 'Mein QR', 'u' => 'https://example.org/qr?d={q}']], 'bang_off' => ['yt']]];
same('Eigenes Bang geht dem Werkzeug vor', 'https://example.org/qr?d=hallo', smBangTarget('!qr hallo', $me)['to'] ?? null);
same('Gelöschtes Bang ist aus', null, smBangTarget('!yt katzen', $me));
$lower = function (string $t): bool { return smBangSys($t) !== null; };
[$l] = smBangEdit(['own' => [], 'off' => []], 'bang_save', ['orig' => 'gh', 't' => 'gh', 'n' => 'GH', 'u' => 'https://example.org/{q}'], $lower);
check('Bearbeitetes Mitgeliefertes ist „geändert“', !empty($l['own'][0]['e']));
[$l] = smBangEdit($l, 'bang_save', ['t' => 'neu', 'n' => 'Neu', 'u' => 'https://example.org/{q}'], $lower);
[$l] = smBangEdit($l, 'bang_del', ['t' => 'yt'], $lower);
[$l] = smBangEdit($l, 'bang_save', ['orig' => 'w', 't' => 'wiki2', 'n' => 'W', 'u' => 'https://example.org/{q}'], $lower);
check('Kürzel umbenannt: altes ausgeblendet', in_array('w', $l['off'], true) && in_array('yt', $l['off'], true));
[$l] = smBangEdit($l, 'bang_restore', [], $lower);
same('Grundzustand: nur selbst angelegte bleiben', ['neu', 'wiki2'], array_column($l['own'], 't'));
same('Grundzustand: nichts mehr gelöscht', [], $l['off']);
$r = smBangSearch('youtube', null, 'mine', '', 5);
check('Bang-Suche nach Name findet YouTube', in_array('yt', array_column($r['rows'], 't'), true));
check('Bang-Suche ohne Begriff zeigt die Extras', count(smBangSearch('', null)['rows']) >= 20);
smQ("INSERT OR REPLACE INTO bang_check (d, checked, fails) VALUES ('www.youtube.com', 1, 5), ('www.google.com', 1, 1)");
$dead = array_column(smBangSearch('', null, 'mine', 'dead', 300)['rows'], 't');
check('Nicht erreichbar: nur wirklich ausgefallene', in_array('yt', $dead, true) && !in_array('g', $dead, true) && !in_array('a', $dead, true), implode(',', $dead));
smQ("DELETE FROM bang_check");
same('Nicht erreichbar: leer, wenn alles antwortet', [], smBangSearch('', null, 'mine', 'dead', 300)['rows']);

/* ---------- Reihenfolge der Suchdienste ---------- */
foreach (['brave' => 'BSAtest', 'tavily' => 'tvly-test', 'exa' => 'exa-test'] as $pid => $pk) smProvSet($pid, 'key', $pk);
smSetSetting('prov_order', 'exa,tavily,brave');
smSetSetting('balance', '1');
same('Reihenfolge: Websuche fragt zuerst den obersten Dienst', ['exa', 'tavily', 'brave'], smProvPick('web', 0, null, 1)[0]);
same('Reihenfolge: Bilder nur bei Diensten, die Bilder können', ['brave'], smProvPick('images', 0, null, 1)[0]);
smUsageAdd('exa', 'web', 0, 20);
same('Lastverteilung: ein paar Suchen am Anfang ändern die Reihenfolge nicht', 'exa', smProvPick('web', 0, null, 1)[0][0] ?? null);
smSetSetting('prov_order', 'tavily,exa,brave');
same('Reihenfolge: Umsortieren wirkt sofort', 'tavily', smProvPick('web', 0, null, 1)[0][0] ?? null);
foreach (['brave', 'tavily', 'exa'] as $pid) smProvSet($pid, 'key', '');

/* ---------- Aussehen ---------- */
$lk = smLookClean(['fs' => 999, 'mode' => 'x', 'pl' => 'gibtsnicht']);
same('Aussehen: Größe begrenzt', 150, $lk['fs']);
same('Aussehen: unbekanntes Design → Standard', 'standard', $lk['pl']);
check('Aussehen: CSS enthält zoom', strpos(smLookCss(smLookClean(['fs' => 120])), '--zoom:1.2') !== false);
same('Import behält das Aussehen', 'dark', smCleanPrefs(['look' => ['mode' => 'dark']])['look']['mode'] ?? null);

/* ---------- Übersetzungen ---------- */
$en = require SM_APP . '/lang/en.php';
foreach ($en as $de => $tr) {
    foreach (['%s', '%d'] as $ph) {
        if (substr_count($de, $ph) !== substr_count($tr, $ph)) {
            check('Übersetzung: Platzhalter ' . $ph . ' passen', false, $de);
        }
    }
}
check('Übersetzung: es gibt englische Texte', count($en) > 500);
// Jeder t('…')-Text im Code sollte eine englische Fassung haben (sonst sehen englische Besucher Deutsch)
$missing = [];
$code = '';
foreach (array_merge(glob(SM_APP . '/lib/*.php'), glob(SM_APP . '/views/*.php')) as $f) $code .= file_get_contents($f);
preg_match_all("~\\bt\\('((?:[^'\\\\]|\\\\.)*)'~", $code, $m);
foreach (array_unique($m[1]) as $s) {
    $s = stripslashes($s);
    if (!isset($en[$s]) && preg_match('/[a-zäöüß]/i', $s)) $missing[] = $s;
}
check('Übersetzung: alle Texte haben eine englische Fassung', !$missing, count($missing) . ' fehlen, z. B. „' . implode('“, „', array_slice($missing, 0, 5)) . '“');

/* ---------- Installationsdatei: Format, das auch der Updater versteht ---------- */
$payload = "<?php exit; ?>\nTest\n__halt_compiler();\n";
foreach (['index.php' => "<?php echo 1;\n", 'app/lib/x.php' => "<?php\n", 'app/lang/en.php' => "<?php return [];\n"] as $p => $bin) {
    $payload .= $p . "\t" . hash('sha256', $bin) . "\t" . base64_encode($bin) . "\n";
}
$parsed = smPayloadParse($payload);
same('Installer-Format: drei Dateien gelesen', 3, is_array($parsed) ? count($parsed) : 0);
same('Installer-Format: falsche Prüfsumme wird abgelehnt', null, smPayloadParse(str_replace(hash('sha256', "<?php\n"), str_repeat('0', 64), $payload)));
same('Installer-Format: Pfad außerhalb von app/ wird abgelehnt', null, smPayloadParse($payload . "../x.php\t" . hash('sha256', 'x') . "\t" . base64_encode('x') . "\n"));

/* ---------- Zähler auf den Stand laut Dashboard setzen ---------- */
smUsageAdd('exa', 'web', 1, 10);
$from = smProvCycle('exa')['from'];
smProvSetUsed('exa', 4);
same('Dashboard-Stand: niedriger als gezählt', 4.0, round(smUsageSum('exa', $from), 3));
smUsageAdd('exa', 'web', 1, 1.5);
same('Dashboard-Stand: zählt danach weiter', 5.5, round(smUsageSum('exa', $from), 3));
smProvSetUsed('exa', 250);
same('Dashboard-Stand: höher als gezählt', 250.0, round(smUsageSum('exa', $from), 3));
same('Dashboard-Stand: eigener Verbrauch des Kontos bleibt', 11.5, round(smUsageSum('exa', $from, 1), 3));

/* ---------- Unterschrift der Updates (Ed25519) ---------- */
if (function_exists('sodium_crypto_sign_keypair')) {
    $kp = sodium_crypto_sign_keypair();
    $pub = base64_encode(sodium_crypto_sign_publickey($kp));
    $sig = base64_encode(sodium_crypto_sign_detached('Inhalt', sodium_crypto_sign_secretkey($kp)));
    same('Update-Unterschrift: passt', '', smUpdVerify('Inhalt', $sig, $pub));
    check('Update-Unterschrift: veränderter Inhalt fällt auf', smUpdVerify('Inhalt!', $sig, $pub) !== '');
    check('Update-Unterschrift: fehlende Unterschrift fällt auf', smUpdVerify('Inhalt', '', $pub) !== '');
}
same('Update-Unterschrift: ohne Schlüssel keine Prüfung', '', smUpdVerify('egal', '', ''));
$cfg = smConfig();
smConfigWrite($cfg + ['update_pubkey' => 'eigener']);
smConfig(true);
same('Eigener Update-Schlüssel aus data/config.php', 'eigener', smUpdPubkey());
smConfigWrite($cfg);
smConfig(true);
same('Ohne Eintrag gilt der Schlüssel des Projekts', SM_UPDATE_PUBKEY, smUpdPubkey());
check('Update-Schlüssel im Code ist leer oder gültig', SM_UPDATE_PUBKEY === '' || strlen((string)base64_decode(SM_UPDATE_PUBKEY, true)) === 32);

/* ---------- Aufräumen ---------- */
if (is_dir($tmp)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($tmp);
}

if ($fails) {
    fwrite(STDERR, "\n" . count($fails) . ' von ' . $count . " Prüfungen fehlgeschlagen:\n  ✗ " . implode("\n  ✗ ", $fails) . "\n\n");
    exit(1);
}
echo 'Alle ' . $count . " Prüfungen bestanden.\n";
