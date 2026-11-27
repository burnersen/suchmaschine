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

/* ---------- Brave: aktiv, Limit, Gäste ---------- */
same('Ohne Key ist Brave nicht aktiv', [], smProvOrder());
smProvSet('brave', 'key', 'BSAtest');
same('Mit Key ist Brave aktiv', ['brave'], smProvOrder());
same('Gäste dürfen Brave nie nutzen', 'denied', smProvBlock('brave', 'web', 0));
same('Konto ohne Kontingent darf Brave nutzen', '', smProvBlock('brave', 'web', 1));
smProvSet('brave', 'limit', '5');
smUsageAdd('brave', 'web', 1, 5);
same('Limit erreicht: Brave bremst sich selbst, bevor es Geld kostet', 'limit', smProvBlock('brave', 'web', 1));
$thrown = '';
try { smSearch('web', 'test', ['page' => 1], 1); } catch (SmSearchExhausted $e) { $thrown = 'exhausted'; }
same('Limit erreicht: Suche geht zur Ausweich-Suchmaschine (statt zu Brave)', 'exhausted', $thrown);
smProvSet('brave', 'limit', '950');
smProvSet('brave', 'key', '');
$thrown = '';
try { smSearch('web', 'test2', ['page' => 1], 1); } catch (SmSearchExhausted $e) { $thrown = $e->getMessage(); }
check('Ohne Key: Ausweich-Suchmaschine', strpos($thrown, 'kein Suchdienst eingerichtet') !== false, $thrown);

/* ---------- Adresse im Suchfeld ---------- */
same('Adresse mit https: Lesemodus', ['https://www.chefkoch.de/rezepte/123/kuchen.html', true], smQueryUrl('https://www.chefkoch.de/rezepte/123/kuchen.html'));
same('Adresse ohne https, mit Pfad', ['https://chefkoch.de/rezepte/123', true], smQueryUrl('chefkoch.de/rezepte/123'));
same('Bloße Domain mit https: Seite öffnen', ['https://www.ostsee-zeitung.de', false], smQueryUrl('https://www.ostsee-zeitung.de'));
same('www-Domain ohne Pfad: Seite öffnen', ['https://www.spiegel.de', false], smQueryUrl('www.spiegel.de'));
same('„node.js“ bleibt eine Suche', null, smQueryUrl('node.js'));
same('Suche mit Leerzeichen bleibt eine Suche', null, smQueryUrl('chefkoch.de rezepte'));
same('Rechnung bleibt eine Suche', null, smQueryUrl('2.5/3'));
check('Lesezeichen „Lesen & Drucken“ zeigt auf den Lesemodus', strpos(rawurldecode(smReadBookmarklet()), '?a=read&u=') !== false);

/* ---------- Ausweich-Suchmaschine ---------- */
same('Standard ist Startpage', 'Startpage', smFallbackName(null));
same('Gast ohne Wahl: Standard', 'https://www.startpage.com/do/search?q=a%20b', smFallbackUrl('a b', null));
$_COOKIE['smfb'] = 'https://duckduckgo.com/?q={q}';
same('Gast mit eigener Wahl (Cookie)', 'https://duckduckgo.com/?q=katze', smFallbackUrl('katze', null));
same('Bildersuche landet in der Bildersuche', 'https://duckduckgo.com/?q=katze&iax=images&ia=images', smFallbackTabUrl('katze', 'images', null));
$_COOKIE['smfb'] = 'https://evil.example/?q={q}';
same('Gäste können keine fremde Adresse unterschieben', 'Startpage', smFallbackName(null));
unset($_COOKIE['smfb']);
$fbMe = ['prefs' => ['fallback' => 'https://example.org/suche?q={q}']];
same('Konto mit eigener Adresse', 'https://example.org/suche?q=x', smFallbackUrl('x', $fbMe));
same('Eigene Adresse: bei Bildern die Websuche', 'https://example.org/suche?q=x', smFallbackTabUrl('x', 'images', $fbMe));
same('Eigene Adresse ohne https wird abgelehnt', false, smFallbackValid('http://example.org/?q={q}'));
same('Eigene Adresse ohne {q} wird abgelehnt', false, smFallbackValid('https://example.org/'));
smSetSetting('fallback_url', 'https://www.bing.com/search?q={q}');
same('Standard der Installation geändert', 'https://www.bing.com/images/search?q=x', smFallbackTabUrl('x', 'images', null));
smSetSetting('fallback_url', 'https://www.startpage.com/do/search?q={q}');
same('Brave-Bang ist wieder eine normale Bang-Seite', false, smBangReserved('brave'));

/* ---------- Zusatzdienste von Brave: Vorschläge, Rechtschreibhilfe, KI-Antwort ---------- */
$xme = ['id' => 1, 'prefs' => []];
same('Vorschläge ohne Key aus', false, smExCan('suggest', $xme));
smExSet('suggest', 'key', 'BSAtest');
same('Vorschläge mit Key an', true, smExCan('suggest', $xme));
same('Gäste bekommen keine Vorschläge', false, smExCan('suggest', null));
same('Konto hat Vorschläge abgeschaltet', false, smExCan('suggest', ['id' => 1, 'prefs' => ['x_suggest' => 'off']]));
smExSet('suggest', 'limit', '3');
smExBook('suggest', 1, 3);
same('Vorschläge: Limit erreicht, die Bremse greift', false, smExCan('suggest', $xme));
smExSet('suggest', 'limit', '');
same('Vorschläge: Standardlimit mit Puffer', 9950.0, smExLimit('suggest'));
check('Vorschläge: Anfrage mit Land und Sprache', strpos(smExRequest('suggest', 'berlin', 'k', ['country' => 'DE', 'search_lang' => 'de'])['url'], 'country=DE&lang=de') !== false);
smExSet('suggest', 'key', '');
smExSet('answer', 'key', 'BSAtest');
smExSet('answer', 'limit', '0.10');
same('KI-Antwort: Limit in Dollar', true, smExCan('answer', $xme, 0.07));
smExBook('answer', 1, 0.06);
same('KI-Antwort: nächste Antwort würde das Limit reißen, also keine', false, smExCan('answer', $xme, 0.07));
smExSetUsed('answer', 0.62);
same('KI-Antwort: Stand laut Dashboard gesetzt', 0.62, round(smExUsed('answer'), 4));
smExBook('answer', 1, 0.06);
same('KI-Antwort: zählt danach weiter', 0.68, round(smExUsed('answer'), 4));
smExSetUsed('answer', null, 3);
same('KI-Antwort: Requests laut Dashboard, Betrag bleibt', [3, 0.68], [smExRequests('answer'), round(smExUsed('answer'), 4)]);
smExSetUsed('suggest', 1234);
same('Vorschläge: Stand laut Dashboard gesetzt', 1234.0, smExUsed('suggest'));
same('Zahl aus dem Feld: 0,62 $', 0.62, smParseAmount('0,62 $'));
same('Zahl aus dem Feld: 1.234', 1234.0, smParseAmount('1.234'));
same('Zahl aus dem Feld: keine Zahl', null, smParseAmount('viel'));
same('KI-Antwort: echter Preis aus der Kopfzeile', 0.0617, smExCost('answer', ['headers' => ['x-request-total-cost' => '0.0617']]));
smExSet('answer', 'key', '');
smExSet('answer', 'limit', '');
same('Markdown der Antwort: fett und Liste, HTML bleibt Text', '<p>Ist <strong>330 m</strong> &lt;b&gt;</p><ul><li>eins</li><li>zwei</li></ul>', smMiniMarkdown("Ist **330 m** <b>\n\n*   eins\n- zwei"));
smCacheSet('spell|' . smPrefs($xme)['country'] . '|' . smPrefs($xme)['search_lang'] . '|wetter berln', ['s' => 'wetter berlin'], 60);
same('Rechtschreibhilfe: Vorschlag (aus dem Zwischenspeicher)', 'wetter berlin', smSpellcheck('wetter berln', $xme));
same('Rechtschreibhilfe: ohne Key und ohne Zwischenspeicher nichts', '', smSpellcheck('katzn', $xme));
same('Rechtschreibhilfe: Gäste nie', '', smSpellcheck('wetter berln', null));

/* ---------- Neueste zuerst mit Zeitstufen ---------- */
same('Zeitstufen: nach dem Jahr kommt alles Ältere', ['pw', 'all', ''], [smNextFresh('pd'), smNextFresh('py'), smNextFresh('all')]);
$tp = ['news_fresh' => 'pd', 'news_expand' => true, 'sort_web' => 'date', 'sort_news' => 'date', 'sort_videos' => 'date'] + smPrefs(null);
$_GET = [];
$tf = smSearchFilters('web', $tp);
same('Alle: Neueste zuerst startet mit 24 Stunden und erweitert', ['pd', true, true], [$tf['fresh'], $tf['auto'], $tf['casc']]);
$_GET = ['sort' => 'rel'];
$tf = smSearchFilters('videos', $tp);
same('Videos nach Relevanz: kein Zeitraum', ['', false], [$tf['fresh'], $tf['casc']]);
$_GET = ['f' => 'all', 'xf' => 'py'];
$tf = smSearchFilters('web', $tp);
same('Letzte Stufe: älter als ein Jahr', ['', 'all', 'py'], [$tf['fresh'], $tf['f_q'], $tf['xf']]);
same('Nächste Stufe: Woche nach 24 Stunden', ['f' => 'pw', 'xf' => 'pd', 'p' => null], smNextTier('news', ['casc' => true, 'auto' => true], 'pd', null)[0] ?? null);
$_GET = [];
same('Bilder: keine Zeitstufen', null, smNextTier('images', ['casc' => true, 'auto' => true], 'pd', null));

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
$from = smProvCycle('brave')['from'];
smQ("DELETE FROM usage_log WHERE provider = 'brave'");
smUsageAdd('brave', 'web', 1, 10);
smProvSetUsed('brave', 4);
same('Dashboard-Stand: niedriger als gezählt', 4.0, round(smUsageSum('brave', $from), 3));
smUsageAdd('brave', 'web', 1, 1.5);
same('Dashboard-Stand: zählt danach weiter', 5.5, round(smUsageSum('brave', $from), 3));
smProvSetUsed('brave', 250);
same('Dashboard-Stand: höher als gezählt', 250.0, round(smUsageSum('brave', $from), 3));
same('Dashboard-Stand: eigener Verbrauch des Kontos bleibt', 11.5, round(smUsageSum('brave', $from, 1), 3));

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
