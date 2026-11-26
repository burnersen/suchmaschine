<?php
/* Suchmaschine – Updates per Knopfdruck: neuestes Release von GitHub holen, prüfen, einspielen, zurück zur vorigen Version */
defined('SM_VERSION') || exit;

/**
 * Öffentlicher Schlüssel (Ed25519, Base64) für die Unterschrift der Releases. Ist er gesetzt, spielt die Suchmaschine nur
 * Updates ein, deren Datei suchmaschine-install.php.sig dazu passt. So hilft es nichts, ein Release auf GitHub
 * auszutauschen: ohne den geheimen Schlüssel gibt es keine gültige Unterschrift.
 * Leer = keine Unterschrift nötig, nur die Prüfsummen in der Datei. Wie man das einrichtet, steht in BUILD.md.
 */
const SM_UPDATE_PUBKEY = 'QPO+CSP5Y7S4S5WblMGZRnMl1VT9ypbCJhEVtM3zNoY=';

/** Woher die Updates kommen. In data/config.php schaltet 'update_url' => '' sie ab. */
/**
 * Öffentlicher Schlüssel, mit dem Updates geprüft werden. Normal der des Projekts (oben).
 * Wer eigene Releases baut (Fork), trägt in data/config.php seinen eigenen ein: 'update_pubkey' => '…',
 * zusammen mit 'update_url' => 'https://api.github.com/repos/NAME/REPO/releases/latest'.
 * 'update_pubkey' => '' heißt: keine Unterschrift verlangen, nur Prüfsummen (nicht empfohlen).
 * Absichtlich nur in der Datei und nicht in der Verwaltung: Wer nur ein Admin-Passwort erbeutet, kann so keine fremden Updates einschleusen.
 */
function smUpdPubkey(): string
{
    $cfg = smConfig();
    return array_key_exists('update_pubkey', $cfg) ? trim((string)$cfg['update_pubkey']) : SM_UPDATE_PUBKEY;
}

function smUpdUrl(): string
{
    $cfg = smConfig();
    return array_key_exists('update_url', $cfg) ? (string)$cfg['update_url'] : 'https://api.github.com/repos/' . SM_REPO . '/releases/latest';
}

function smHttpGet(string $url, int $max): string
{
    $ua = 'Suchmaschine/' . SM_VERSION;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 60, CURLOPT_USERAGENT => $ua,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json, application/octet-stream;q=0.9, */*;q=0.5'],
        CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => function ($ch, $dlTotal, $dl) use ($max) { return $dl > $max ? 1 : 0; },
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($body === false || $status !== 200) throw new RuntimeException(t('GitHub ist gerade nicht erreichbar (HTTP %d).', $status));
    return (string)$body;
}

/** Fragt GitHub nach dem neuesten Release und merkt sich das Ergebnis. Liefert ['v', 'url', 'notes', 'at'] oder wirft. */
function smUpdCheck(): array
{
    $url = smUpdUrl();
    if ($url === '') throw new RuntimeException(t('Die Update-Suche ist in data/config.php abgeschaltet.'));
    $j = json_decode(smHttpGet($url, 2000000), true);
    if (!is_array($j) || empty($j['tag_name'])) throw new RuntimeException(t('Die Antwort von GitHub war unverständlich.'));
    $dl = '';
    $sig = '';
    foreach ((array)($j['assets'] ?? []) as $a) {
        if (($a['name'] ?? '') === 'suchmaschine-install.php') $dl = (string)($a['browser_download_url'] ?? '');
        if (($a['name'] ?? '') === 'suchmaschine-install.php.sig') $sig = (string)($a['browser_download_url'] ?? '');
    }
    if ($dl === '') throw new RuntimeException(t('Im neuesten Release fehlt die Datei suchmaschine-install.php.'));
    $info = [
        'v'     => preg_replace('/^[^0-9]*/', '', (string)$j['tag_name']),
        'url'   => $dl,
        'sig'   => $sig,
        'page'  => (string)($j['html_url'] ?? ''),
        'notes' => mb_substr((string)($j['body'] ?? ''), 0, 4000),
        'at'    => time(),
    ];
    smSetSetting('upd_info', json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $info;
}

function smUpdInfo(): ?array
{
    $i = json_decode(smSetting('upd_info'), true);
    return is_array($i) && !empty($i['v']) ? $i : null;
}

function smUpdNewer(?array $i): bool
{
    return $i !== null && version_compare($i['v'], SM_VERSION, '>');
}

/** Prüft die Unterschrift einer heruntergeladenen Installationsdatei. '' = in Ordnung (oder keine nötig), sonst Grund. */
function smUpdVerify(string $bin, string $sigB64, ?string $pubB64 = null): string
{
    $pubB64 = $pubB64 ?? smUpdPubkey();
    if ($pubB64 === '') return '';
    if (!function_exists('sodium_crypto_sign_verify_detached')) return t('Die PHP-Erweiterung sodium fehlt, darum lässt sich die Unterschrift des Updates nicht prüfen.');
    $pub = base64_decode($pubB64, true);
    $sig = base64_decode(trim($sigB64), true);
    if ($pub === false || strlen($pub) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || $sig === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES
        || !sodium_crypto_sign_verify_detached($sig, $bin, $pub)) {
        return t('Die Unterschrift des Updates stimmt nicht. Es wurde nichts verändert.');
    }
    return '';
}

/** Liest die eingepackten Dateien eines Installers (oder einer Sicherung). Liefert [Pfad => Inhalt] oder null. */
function smPayloadParse(string $raw): ?array
{
    $pos = strpos($raw, "\n__halt_compiler();");
    if ($pos === false) return null;
    $raw = substr($raw, $pos + strlen("\n__halt_compiler();"));
    $files = [];
    foreach (explode("\n", $raw) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $p = explode("\t", $line);
        if (count($p) !== 3) return null;
        if (!preg_match('~^[A-Za-z0-9._\-]+(/[A-Za-z0-9._\-]+)*$~', $p[0]) || strpos($p[0], '..') !== false) return null;
        if ($p[0] !== 'index.php' && $p[0] !== 'LICENSE' && strpos($p[0], 'app/') !== 0) return null;
        $bin = base64_decode($p[2], true);
        if ($bin === false || !hash_equals($p[1], hash('sha256', $bin))) return null;
        $files[$p[0]] = $bin;
    }
    return isset($files['index.php']) ? $files : null;
}

/** Die laufenden Programmdateien als Sicherung (gleiches Format wie der Installer) */
function smPayloadOfCurrent(): string
{
    $files = ['index.php', 'LICENSE'];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SM_APP, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile()) $files[] = str_replace('\\', '/', substr($f->getPathname(), strlen(SM_ROOT) + 1));
    }
    $out = "<?php exit; ?>\nSuchmaschine " . SM_VERSION . "\n__halt_compiler();\n";
    foreach ($files as $path) {
        if (!is_file(SM_ROOT . '/' . $path)) continue;
        $bin = (string)file_get_contents(SM_ROOT . '/' . $path);
        $out .= $path . "\t" . hash('sha256', $bin) . "\t" . base64_encode($bin) . "\n";
    }
    return $out;
}

/** Schreibt alles erst unter Zwischennamen und tauscht dann um. Wirft bei Fehlern, dann ist nichts verändert. */
function smPayloadInstall(array $files): void
{
    $tmp = [];
    try {
        foreach ($files as $path => $bin) {
            $target = SM_ROOT . '/' . $path;
            $dir = dirname($target);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException($path);
            $t = $target . '.tmp';
            if (@file_put_contents($t, $bin) !== strlen($bin)) throw new RuntimeException($path);
            $tmp[$t] = $target;
        }
    } catch (RuntimeException $e) {
        foreach ($tmp as $t => $_) @unlink($t);
        throw new RuntimeException(t('Diese Datei ließ sich nicht schreiben: %s. Es wurde nichts verändert.', $e->getMessage()));
    }
    foreach ($tmp as $t => $target) {
        @rename($t, $target);
        @chmod($target, 0644);
        if (function_exists('opcache_invalidate')) @opcache_invalidate($target, true);
    }
    // Programmteile, die es in dieser Version nicht mehr gibt, wegräumen (nur in app/)
    foreach (['lib', 'views', 'assets', 'lang'] as $sub) {
        foreach ((array)glob(SM_APP . '/' . $sub . '/*') as $old) {
            if (is_file($old) && !isset($files['app/' . $sub . '/' . basename($old)])) @unlink($old);
        }
    }
}

/** Prüft, ob PHP die Programmdateien überschreiben darf. '' = ja, sonst Grund. */
function smUpdWritable(): string
{
    foreach ([SM_ROOT, SM_APP, SM_APP . '/lib', SM_APP . '/views', SM_APP . '/assets', SM_APP . '/lang', SM_ROOT . '/index.php'] as $p) {
        if (file_exists($p) && !is_writable($p)) return t('PHP darf %s nicht überschreiben.', substr($p, strlen(SM_ROOT) + 1) ?: '/');
    }
    return '';
}

/** Spielt das neueste Release ein. Die laufende Version wird vorher gesichert. Liefert Fehlertext oder ''. */
function smUpdInstall(): string
{
    try {
        if (($w = smUpdWritable()) !== '') return $w;
        $info = smUpdCheck();
        if (!smUpdNewer($info)) return t('Es ist schon die neueste Version installiert.');
        $bin = smHttpGet($info['url'], 20000000);
        if (smUpdPubkey() !== '') {
            if (($info['sig'] ?? '') === '') return t('Im neuesten Release fehlt die Unterschrift (suchmaschine-install.php.sig). Es wurde nichts verändert.');
            if (($e = smUpdVerify($bin, smHttpGet($info['sig'], 10000))) !== '') return $e;
        }
        $files = smPayloadParse($bin);
        if ($files === null) return t('Die heruntergeladene Datei ist beschädigt. Es wurde nichts verändert.');
        if (@file_put_contents(SM_DATA_DIR . '/prev.php', smPayloadOfCurrent(), LOCK_EX) === false) {
            return t('Die laufende Version konnte nicht gesichert werden. Es wurde nichts verändert.');
        }
        smSetSetting('prev_version', SM_VERSION);
        smPayloadInstall($files);
        return '';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

/** Version, zu der man zurück kann, oder '' */
function smUpdPrev(): string
{
    return is_file(SM_DATA_DIR . '/prev.php') ? smSetting('prev_version') : '';
}

function smUpdRollback(): string
{
    $files = smPayloadParse((string)@file_get_contents(SM_DATA_DIR . '/prev.php'));
    if ($files === null) return t('Es gibt keine gesicherte Version zum Zurückholen.');
    try {
        smPayloadInstall($files);
    } catch (Throwable $e) {
        return $e->getMessage();
    }
    @unlink(SM_DATA_DIR . '/prev.php');
    smSetSetting('prev_version', '');
    return '';
}

/** Automatische Updates (wenn eingeschaltet): höchstens einmal am Tag, nachdem die Seite ausgeliefert ist */
function smAutoUpdate(): void
{
    if (smSetting('auto_update') !== '1' || smUpdUrl() === '') return;
    if ((int)smSetting('upd_last_try') > time() - 86400) return;
    smSetSetting('upd_last_try', (string)time());
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    smUpdInstall();
}

/**
 * Selbsttest: Sind data/ und app/ von außen wirklich gesperrt? Ruft die eigene Seite übers Netz auf.
 * Liefert ['ok' => bool|null, 'at' => Zeit, 'leak' => welche Datei]. null = Test nicht möglich.
 */
function smProtectionCheck(bool $force = false): array
{
    $last = json_decode(smSetting('protect'), true);
    if (!$force && is_array($last) && (int)$last['at'] > time() - 86400) return $last;
    $base = smOrigin();
    $cfg = smConfig();
    $res = ['ok' => true, 'at' => time(), 'leak' => ''];
    foreach (['data/' . $cfg['db'], 'app/lib/db.php'] as $f) {
        $ch = curl_init($base . $f);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'Suchmaschine-Selbsttest']);
        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($status === 0) { $res['ok'] = null; break; }
        if ($status === 200) { $res = ['ok' => false, 'at' => time(), 'leak' => $f === 'app/lib/db.php' ? 'app/' : 'data/']; break; }
    }
    smSetSetting('protect', json_encode($res));
    return $res;
}
