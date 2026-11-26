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
