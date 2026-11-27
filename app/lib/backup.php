<?php
/* Suchmaschine – Komplettsicherung: alle Einstellungen, API-Keys, Konten (mit Passwort-Prüfsummen), Login-Tokens,
   persönliche Einstellungen, Kontingente, Kurzlinks, Verbrauch und Logo in einer Datei. Auf Wunsch mit Passwort
   verschlüsselt (AES-256-GCM, Schlüssel per PBKDF2-SHA256 aus dem Passwort). */
defined('SM_VERSION') || exit;

const SM_BACKUP_ITER = 300000;
const SM_BACKUP_MAX = 25 * 1048576;

/** Einstellungen, die nur den Augenblick betreffen und nicht in die Sicherung gehören */
function smBackupSkipKeys(): array
{
    return ['upd_info', 'upd_last_try', 'protect', 'prev_version'];
}

/** Alle Daten als Array */
function smBackupData(): array
{
    $settings = [];
    foreach (smQ('SELECT k, v FROM settings ORDER BY k')->fetchAll() as $r) {
        if (!in_array($r['k'], smBackupSkipKeys(), true)) $settings[$r['k']] = $r['v'];
    }
    $users = [];
    foreach (smQ('SELECT id, name, pass, is_admin, prefs, created_at, quota, token_enc FROM users ORDER BY id')->fetchAll() as $u) {
        $users[] = [
            'id' => (int)$u['id'], 'name' => $u['name'], 'pass' => $u['pass'], 'is_admin' => (int)$u['is_admin'],
            'prefs' => json_decode((string)$u['prefs'], true) ?: [], 'created_at' => (int)$u['created_at'],
            'quota' => json_decode((string)$u['quota'], true) ?: [],
            'token' => $u['token_enc'] !== '' ? smUnseal((string)$u['token_enc']) : '',
        ];
    }
    $files = [];
    foreach (['logo.bin', 'appicon.png'] as $f) {
        if (is_file(SM_DATA_DIR . '/' . $f)) $files[$f] = base64_encode((string)file_get_contents(SM_DATA_DIR . '/' . $f));
    }
    return [
        'settings'   => $settings,
        'users'      => $users,
        'shortlinks' => smQ('SELECT code, url, user_id, created, hits, title, expires, max_hits, pass, active, updated, note, last_hit FROM shortlinks ORDER BY created')->fetchAll(),
        'short_days' => smQ('SELECT code, day, n FROM short_days ORDER BY day')->fetchAll(),
        'usage'      => smQ('SELECT day, provider, kind, user_id, credits, req FROM usage_log ORDER BY day')->fetchAll(),
        'files'      => $files,
    ];
}

/** Sicherung herunterladen. Mit Passwort verschlüsselt, ohne als lesbares JSON. Liefert Fehlertext oder beendet. */
function smServeBackup(string $pass, string $pass2): string
{
    if ($pass !== $pass2) return t('Die beiden Passwörter stimmen nicht überein.');
    if ($pass !== '' && strlen($pass) < 8) return t('Das Passwort für die Sicherung muss mindestens 8 Zeichen lang sein.');
    $json = json_encode(['format' => 'suchmaschine-backup', 'version' => 1, 'app' => SM_VERSION, 'exported' => date('c'), 'data' => smBackupData()],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return t('Die Sicherung konnte nicht erstellt werden.');
    $name = 'suchmaschine-komplett-' . date('Y-m-d');
    if ($pass !== '') {
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_pbkdf2')) return t('Zum Verschlüsseln braucht PHP die Erweiterung OpenSSL.');
        $salt = random_bytes(16);
        $iv = random_bytes(12);
        $key = openssl_pbkdf2($pass, $salt, 32, SM_BACKUP_ITER, 'sha256');
        $tag = '';
        $ct = openssl_encrypt((string)gzcompress($json, 6), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'suchmaschine-backup-v1');
        $json = json_encode(['format' => 'suchmaschine-backup-enc', 'version' => 1, 'app' => SM_VERSION, 'exported' => date('c'),
            'kdf' => 'pbkdf2-sha256', 'iter' => SM_BACKUP_ITER, 'salt' => base64_encode($salt), 'iv' => base64_encode($iv),
            'tag' => base64_encode($tag), 'data' => base64_encode((string)$ct)], JSON_UNESCAPED_SLASHES);
        $name .= '-verschluesselt';
    }
    smHeaders();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Content-Disposition: attachment; filename="' . $name . '.json"');
    echo $json;
    exit;
}

/** Hochgeladene Sicherung lesen (und entschlüsseln). Liefert [Daten, Fehlertext]. */
function smBackupRead(string $field, string $pass): array
{
    $f = $_FILES[$field] ?? null;
    if (!is_array($f) || empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) return [null, t('Bitte eine Sicherungsdatei auswählen.')];
    if ((int)$f['size'] > SM_BACKUP_MAX) return [null, t('Die Datei ist zu groß.')];
    $j = json_decode((string)file_get_contents($f['tmp_name']), true);
    if (!is_array($j)) return [null, t('Das ist keine passende Sicherungsdatei der Suchmaschine.')];
    if (($j['format'] ?? '') === 'suchmaschine-backup-enc') {
        if ($pass === '') return [null, t('Diese Sicherung ist verschlüsselt. Bitte das Passwort eingeben.')];
        if (!function_exists('openssl_decrypt') || !function_exists('openssl_pbkdf2')) return [null, t('Zum Entschlüsseln braucht PHP die Erweiterung OpenSSL.')];
        $iter = (int)($j['iter'] ?? 0);
        $salt = base64_decode((string)($j['salt'] ?? ''), true);
        $iv = base64_decode((string)($j['iv'] ?? ''), true);
        $tag = base64_decode((string)($j['tag'] ?? ''), true);
        $ct = base64_decode((string)($j['data'] ?? ''), true);
        if ($iter < 10000 || $iter > 5000000 || !$salt || !$iv || !$tag || $ct === false) return [null, t('Die Sicherungsdatei ist beschädigt.')];
        $key = openssl_pbkdf2($pass, $salt, 32, $iter, 'sha256');
        $plain = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'suchmaschine-backup-v1');
        if (!is_string($plain)) return [null, t('Das Passwort stimmt nicht, oder die Datei wurde verändert.')];
        $plain = @gzuncompress($plain, 100 * 1048576);
        $j = is_string($plain) ? json_decode($plain, true) : null;
        if (!is_array($j)) return [null, t('Die Sicherungsdatei ist beschädigt.')];
    }
    if (($j['format'] ?? '') !== 'suchmaschine-backup' || !is_array($j['data'] ?? null)) return [null, t('Das ist keine passende Sicherungsdatei der Suchmaschine.')];
    return [$j['data'], ''];
}

/** Spielt eine Komplettsicherung ein. Alles wird geprüft und ersetzt. Liefert Fehlertext oder ''. */
function smBackupRestore(array $d): string
{
    $users = [];
    foreach ((array)($d['users'] ?? []) as $u) {
        if (!is_array($u)) continue;
        $name = trim((string)($u['name'] ?? ''));
        $pass = (string)($u['pass'] ?? '');
        $id = (int)($u['id'] ?? 0);
        if ($id < 1 || smCheckName($name) !== '' || !preg_match('/^\$(2y|argon2i|argon2id)\$[\x21-\x7e]{20,200}$/', $pass)) continue;
        $users[$id] = $u + ['name' => $name];
        if (count($users) >= SM_MAX_USERS) break;
    }
    if (!$users) return t('Die Sicherung enthält keine gültigen Konten.');
    if (!array_filter($users, function ($u) { return (int)($u['is_admin'] ?? 0) === 1; })) return t('Die Sicherung enthält keinen Administrator.');

    $db = smDb();
    $db->exec('BEGIN IMMEDIATE');
    try {
        // Einstellungen
        $db->exec('DELETE FROM settings');
        foreach ((array)($d['settings'] ?? []) as $k => $v) {
            if (!is_string($k) || !preg_match('/^[a-z0-9_]{1,60}$/', $k) || !is_scalar($v) || strlen((string)$v) > 200000) continue;
            if (in_array($k, smBackupSkipKeys(), true)) continue;
            smQ('INSERT INTO settings (k, v) VALUES (?, ?)', [$k, (string)$v]);
        }
        // Konten
        $db->exec('DELETE FROM sessions');
        $db->exec('DELETE FROM users');
        $names = [];
        $hashes = [];
        foreach ($users as $id => $u) {
            if (isset($names[mb_strtolower($u['name'])])) continue;
            $names[mb_strtolower($u['name'])] = true;
            $tok = (string)($u['token'] ?? '');
            $th = null;
            $te = '';
            if ($tok !== '' && smTokenCheck($tok) === '' && !isset($hashes[hash('sha256', $tok)])) {
                $th = hash('sha256', $tok);
                $hashes[$th] = true;
                $te = smSeal($tok);
            }
            smQ('INSERT INTO users (id, name, pass, is_admin, prefs, created_at, quota, token_hash, token_enc) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $id, $u['name'], $u['pass'], (int)($u['is_admin'] ?? 0) === 1 ? 1 : 0,
                json_encode(smCleanPrefs(is_array($u['prefs'] ?? null) ? $u['prefs'] : []), JSON_UNESCAPED_UNICODE),
                max(0, (int)($u['created_at'] ?? time())), json_encode(smCleanQuota($u['quota'] ?? [])), $th, $te,
            ]);
        }
        // Kurzlinks
        $db->exec('DELETE FROM shortlinks');
        foreach ((array)($d['shortlinks'] ?? []) as $s) {
            if (!is_array($s)) continue;
            $code = (string)($s['code'] ?? '');
            $url = smCleanShortTarget((string)($s['url'] ?? ''));
            $uid = (int)($s['user_id'] ?? 0);
            if (!preg_match('/^[A-Za-z0-9_-]{3,40}$/', $code) || $url === '' || !isset($users[$uid])) continue;
            $pass = (string)($s['pass'] ?? '');
            smQ('INSERT OR IGNORE INTO shortlinks (code, url, user_id, created, hits, title, expires, max_hits, pass, active, updated, note, last_hit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$code, $url, $uid, max(0, (int)($s['created'] ?? 0)), max(0, (int)($s['hits'] ?? 0)), mb_substr((string)($s['title'] ?? ''), 0, 200),
                 max(0, (int)($s['expires'] ?? 0)), max(0, (int)($s['max_hits'] ?? 0)), preg_match('/^\$2[aby]?\$|^\$argon2/', $pass) ? $pass : '',
                 (int)($s['active'] ?? 1) === 0 ? 0 : 1, max(0, (int)($s['updated'] ?? 0)), mb_substr((string)($s['note'] ?? ''), 0, 200), max(0, (int)($s['last_hit'] ?? 0))]);
        }
        $db->exec('DELETE FROM short_days');
        foreach ((array)($d['short_days'] ?? []) as $s) {
            if (!is_array($s) || !preg_match('/^[A-Za-z0-9_-]{3,40}$/', (string)($s['code'] ?? '')) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($s['day'] ?? ''))) continue;
            smQ('INSERT OR IGNORE INTO short_days (code, day, n) VALUES (?, ?, ?)', [$s['code'], $s['day'], max(0, (int)($s['n'] ?? 0))]);
        }
        // Verbrauch
        $db->exec('DELETE FROM usage_log');
        $ids = array_keys(smProviders());
        foreach ((array)($d['usage'] ?? []) as $r) {
            if (!is_array($r) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($r['day'] ?? '')) || !in_array($r['provider'] ?? '', $ids, true)) continue;
            if (!preg_match('/^[a-z]{1,10}$/', (string)($r['kind'] ?? ''))) continue;
            smQ('INSERT OR IGNORE INTO usage_log (day, provider, kind, user_id, credits, req) VALUES (?, ?, ?, ?, ?, ?)',
                [$r['day'], $r['provider'], $r['kind'], max(-1, (int)($r['user_id'] ?? 0)), max(0.0, (float)($r['credits'] ?? 0)), max(0, (int)($r['req'] ?? 0))]);
        }
        $db->exec('DELETE FROM cache');
        $db->exec('DELETE FROM pstat');
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        error_log('Suchmaschine: Wiederherstellung: ' . $e);
        return t('Die Sicherung konnte nicht eingespielt werden. Es wurde nichts verändert.');
    }
    unset($GLOBALS['sm_settings']);

    // Logo und App-Symbol: nur echte Bilder
    @unlink(SM_DATA_DIR . '/logo.bin');
    smAppIconClear();
    $files = (array)($d['files'] ?? []);
    $logo = isset($files['logo.bin']) ? base64_decode((string)$files['logo.bin'], true) : false;
    if (is_string($logo) && $logo !== '' && strlen($logo) <= 1048576) {
        $tmp = SM_DATA_DIR . '/restore-' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmp, $logo) !== false) {
            if (smSaveLogo($tmp, strlen($logo)) !== '') smSetSetting('logo_type', '');
            @unlink($tmp);
        }
    } else {
        smSetSetting('logo_type', '');
    }
    $icon = isset($files['appicon.png']) ? base64_decode((string)$files['appicon.png'], true) : false;
    if (is_string($icon) && $icon !== '' && smLogoUrl() !== '') smSaveAppIconPng(base64_encode($icon));
    return '';
}

/* ---------- Einstellungen der Installation sichern (Administrator) ---------- */

/** Welche Einstellungen der Installation in eine Sicherung gehören, und wie sie geprüft werden */
function smConfigKeys(): array
{
    $int = function (int $min, int $max) { return function ($v) use ($min, $max) { return is_numeric($v) ? (string)max($min, min($max, (int)$v)) : null; }; };
    $in = function (array $ok) { return function ($v) use ($ok) { return in_array((string)$v, $ok, true) ? (string)$v : null; }; };
    $key = function ($v) { return is_string($v) && ($v === '' || preg_match('/^[\x21-\x7e]{8,300}$/', trim($v))) ? trim($v) : null; };
    return [
        'site_name'     => function ($v) { return is_string($v) ? trim(mb_substr($v, 0, 40)) : null; },
        'accent'        => $in(array_keys(smAccents())),
        'scroll'        => $in(['pages', 'endless']),
        'monthly_limit' => $int(0, 1000000),
        'cache_min'     => $int(0, 60),
        'per_page'      => $int(5, 20),
        'country'       => $in(array_keys(smCountries())),
        'search_lang'   => $in(array_keys(smSearchLangs())),
        'safesearch'    => $in(['off', 'moderate', 'strict']),
        'public'        => $in(['0', '1']),
        'auto_update'   => $in(['0', '1']),
        'img_proxy'     => $in(['0', '1']),
        'shortener'     => $in(['0', '1']),
        'ipv4_host'     => function ($v) { return is_string($v) ? smCleanHostname($v) : null; },
        'sort_videos'   => $in(['date', 'rel']),
        'fallback_url'  => function ($v) { return is_string($v) && smFallbackValid($v) ? $v : null; },
        'warn_pct'      => $int(10, 100),
        'weather_src'   => $in(array_keys(smWeatherSources())),
        'look'          => function ($v) { $a = is_string($v) ? json_decode($v, true) : null; return is_array($a) ? json_encode(smLookClean($a)) : null; },
        'api_key'       => $key,
    ] + smProvConfigKeys($int, $key) + smExConfigKeys($key);
}

/** Prüfregeln für die Einstellungen des Suchdienstes (p_brave_start …) */
function smProvConfigKeys(callable $int, callable $key): array
{
    $out = [];
    foreach (smProvSettingKeys() as $k) {
        $f = substr($k, strrpos($k, '_') + 1);
        if ($f === 'key') $out[$k] = $key;
        elseif ($f === 'start') $out[$k] = function ($v) { return is_string($v) && ($v === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) ? $v : null; };
        elseif ($f === 'cost') $out[$k] = function ($v) { return is_numeric($v) ? (string)max(0.01, min(1000, (float)$v)) : null; };
        elseif ($f === 'on') $out[$k] = function ($v) { return in_array((string)$v, ['0', '1'], true) ? (string)$v : null; };
        else $out[$k] = $int(0, 10000000);
    }
    return $out;
}

/** Prüfregeln für die Zusatzdienste (x_suggest_key …) */
function smExConfigKeys(callable $key): array
{
    $out = [];
    foreach (smExSettingKeys() as $k) {
        $f = substr($k, strrpos($k, '_') + 1);
        if ($f === 'key') $out[$k] = $key;
        elseif ($f === 'on') $out[$k] = function ($v) { return in_array((string)$v, ['0', '1'], true) ? (string)$v : null; };
        else $out[$k] = function ($v) { return is_numeric($v) ? (string)max(0, min(10000000, (float)$v)) : null; };
    }
    return $out;
}

function smServeConfigExport(bool $withKey): void
{
    $s = [];
    foreach (array_keys(smConfigKeys()) as $k) {
        if (($k === 'api_key' || substr($k, -4) === '_key') && !$withKey) continue;
        $s[$k] = smSetting($k);
    }
    smHeaders();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Content-Disposition: attachment; filename="suchmaschine-installation-' . date('Y-m-d') . '.json"');
    echo json_encode(['format' => 'suchmaschine-config', 'version' => 1, 'app' => SM_VERSION, 'exported' => date('c'), 'prefs' => $s],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Übernimmt eine Sicherung der Installation. Unbekanntes und Ungültiges wird übergangen. Liefert die Zahl der Werte. */
function smConfigImport(array $in): int
{
    $n = 0;
    foreach (smConfigKeys() as $k => $check) {
        if (!array_key_exists($k, $in)) continue;
        $v = $check($in[$k]);
        if ($v === null) continue;
        smSetSetting($k, $v);
        $n++;
    }
    return $n;
}
