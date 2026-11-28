<?php
/* Suchmaschine – Konten, Anmeldung, Sitzungen, Bremse gegen Durchprobieren */
defined('SM_VERSION') || exit;

const SM_MAX_USERS = 100;

function smHasUsers(): bool
{
    return (int)smQ('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
}

/** Prüft einen Namen, liefert Fehlertext oder '' */
function smCheckName(string $name): string
{
    $len = mb_strlen($name);
    if ($len < 2 || $len > 30) return t('Der Name muss 2 bis 30 Zeichen lang sein.');
    if (preg_match('/[\x00-\x1f<>"\'&]/', $name)) return t('Der Name enthält unzulässige Zeichen.');
    return '';
}

function smCheckPass(string $pass): string
{
    if (strlen($pass) < 8) return t('Das Passwort muss mindestens 8 Zeichen lang sein.');
    if (strlen($pass) > 200) return t('Das Passwort ist zu lang.');
    return '';
}

/* ---------- Sitzungen: zufälliges Kennzeichen im Cookie, in der Datenbank steht nur dessen Prüfsumme ---------- */

function smCookie(string $value, int $expires): void
{
    setcookie(SM_COOKIE, $value, ['expires' => $expires, 'path' => smBase(), 'secure' => smIsHttps(), 'httponly' => true, 'samesite' => 'Lax']);
}

/** Neue Sitzung. Nach dem Login per Token bleibt das Gerät 365 Tage angemeldet (verlängert sich bei Benutzung). */
function smStartSession(int $userId, int $days = SM_SESSION_DAYS): void
{
    $token = bin2hex(random_bytes(32));
    $days = max(1, min(400, $days));
    $exp = time() + $days * 86400;
    smQ('INSERT INTO sessions (hash, user_id, csrf, expires, touched, days) VALUES (?, ?, ?, ?, ?, ?)',
        [hash('sha256', $token), $userId, bin2hex(random_bytes(24)), $exp, time(), $days]);
    smCookie($token, $exp);
}

/** Angemeldeter Benutzer oder null: id, name, is_admin, prefs, csrf, hash */
function smUser(): ?array
{
    static $done = false, $user = null;
    if ($done) return $user;
    $done = true;
    $token = $_COOKIE[SM_COOKIE] ?? '';
    if (!is_string($token) || !preg_match('/^[0-9a-f]{64}$/', $token)) return null;
    $hash = hash('sha256', $token);
    $row = smQ('SELECT s.csrf, s.touched, s.days, u.id, u.name, u.is_admin, u.prefs FROM sessions s JOIN users u ON u.id = s.user_id
                WHERE s.hash = ? AND s.expires > ?', [$hash, time()])->fetch();
    if (!$row) return null;
    // Höchstens einmal am Tag verlängern
    if (time() - (int)$row['touched'] > 86400) {
        $exp = time() + max(1, (int)$row['days']) * 86400;
        smQ('UPDATE sessions SET expires = ?, touched = ? WHERE hash = ?', [$exp, time(), $hash]);
        smCookie($token, $exp);
    }
    $prefs = json_decode((string)$row['prefs'], true);
    $user = [
        'id' => (int)$row['id'], 'name' => $row['name'], 'is_admin' => (int)$row['is_admin'] === 1,
        'prefs' => is_array($prefs) ? $prefs : [], 'csrf' => $row['csrf'], 'hash' => $hash,
    ];
    return $user;
}

function smLogout(array $me): void
{
    smQ('DELETE FROM sessions WHERE hash = ?', [$me['hash']]);
    smCookie('', time() - 3600);
}

/** Alle anderen Geräte dieses Kontos abmelden */
function smLogoutOthers(array $me): int
{
    return smQ('DELETE FROM sessions WHERE user_id = ? AND hash != ?', [$me['id'], $me['hash']])->rowCount();
}

/* ---------- Geheimer Login-Token: einmal mit ?token=… aufrufen, dann bleibt das Gerät angemeldet ---------- */

const SM_TOKEN_DAYS = 365;

/** Schlüssel zum Verschlüsseln gespeicherter Geheimnisse. Liegt in data/config.php, nicht in der Datenbank. */
function smSecretKey(): string
{
    $cfg = smConfig();
    if (empty($cfg['secret']) || strlen((string)$cfg['secret']) < 64) {
        $cfg['secret'] = bin2hex(random_bytes(32));
        smConfigWrite($cfg);
        smConfig(true);
    }
    return hex2bin(substr((string)$cfg['secret'], 0, 64));
}

/** Verschlüsselt mit AES-256-GCM (OpenSSL). Ohne OpenSSL bleibt es lesbar, die Datenbank ist ja trotzdem geschützt. */
function smSeal(string $plain): string
{
    if (!function_exists('openssl_encrypt')) return 'p:' . base64_encode($plain);
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', smSecretKey(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'g1:' . base64_encode($iv . $tag . $ct);
}

function smUnseal(string $blob): string
{
    if (strpos($blob, 'p:') === 0) return (string)base64_decode(substr($blob, 2), true);
    if (strpos($blob, 'g1:') !== 0 || !function_exists('openssl_decrypt')) return '';
    $raw = (string)base64_decode(substr($blob, 3), true);
    if (strlen($raw) < 29) return '';
    $out = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', smSecretKey(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return is_string($out) ? $out : '';
}

/** Zufälliger Token: 48 Zeichen aus Buchstaben und Ziffern (rund 285 Bit) */
function smTokenGenerate(): string
{
    $abc = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $t = '';
    for ($i = 0; $i < 48; $i++) $t .= $abc[random_int(0, 61)];
    return $t;
}

/** Prüft einen selbst gewählten Token. Liefert Fehlertext oder ''. */
function smTokenCheck(string $t): string
{
    if (strlen($t) < 32) return t('Der Token muss mindestens 32 Zeichen lang sein.');
    if (strlen($t) > 200) return t('Der Token darf höchstens 200 Zeichen lang sein.');
    if (!preg_match('/^[A-Za-z0-9._~-]+$/', $t)) return t('Erlaubt sind nur Buchstaben (ohne Umlaute), Ziffern und . _ ~ - (damit der Token in jeder Adresse funktioniert).');
    if (count(array_unique(str_split($t))) < 12) return t('Der Token ist zu eintönig. Bitte mindestens 12 verschiedene Zeichen verwenden.');
    $classes = (int)preg_match('/[a-z]/', $t) + (int)preg_match('/[A-Z]/', $t) + (int)preg_match('/[0-9]/', $t) + (int)preg_match('/[._~-]/', $t);
    if ($classes < 3) return t('Bitte mindestens drei Zeichenarten mischen: Kleinbuchstaben, Großbuchstaben, Ziffern, Sonderzeichen.');
    if (preg_match('/(.)\1{5,}|0123456|1234567|abcdefg|qwertz|qwerty|passwort|password/i', $t)) return t('Der Token enthält ein leicht zu erratendes Muster.');
    return '';
}

/** Token setzen ('' = löschen). In der Datenbank steht nur die Prüfsumme zum Suchen und eine verschlüsselte Kopie zum Anzeigen. */
function smTokenSet(int $uid, string $token): string
{
    if ($token === '') {
        smQ("UPDATE users SET token_hash = NULL, token_enc = '' WHERE id = ?", [$uid]);
        return '';
    }
    if (($e = smTokenCheck($token)) !== '') return $e;
    $hash = hash('sha256', $token);
    $other = smQ('SELECT id FROM users WHERE token_hash = ? AND id != ?', [$hash, $uid])->fetchColumn();
    if ($other) return t('Dieser Token ist schon vergeben. Bitte einen anderen wählen.');
    smQ('UPDATE users SET token_hash = ?, token_enc = ? WHERE id = ?', [$hash, smSeal($token), $uid]);
    return '';
}

/** Token des Kontos zum Anzeigen ('' = keiner) */
function smTokenGet(int $uid): string
{
    $enc = (string)smQ('SELECT token_enc FROM users WHERE id = ?', [$uid])->fetchColumn();
    return $enc !== '' ? smUnseal($enc) : '';
}

/** Konto zu einem Token oder 0. Vergleich in konstanter Zeit, Bremse gegen Durchprobieren. */
function smTokenLogin(string $token): array
{
    $wait = smThrottleWait('#token');
    if ($wait > 0) return [0, t('Zu viele Fehlversuche. Bitte in %s noch einmal versuchen.', $wait > 90 ? t('%d Minuten', (int)ceil($wait / 60)) : t('%d Sekunden', $wait))];
    $hash = hash('sha256', $token);
    $row = strlen($token) >= 32 && strlen($token) <= 200 ? smQ('SELECT id, token_hash FROM users WHERE token_hash = ?', [$hash])->fetch() : false;
    if (!$row || !hash_equals((string)$row['token_hash'], $hash)) {
        smThrottleFail('#token');
        return [0, t('Dieser Login-Token ist ungültig.')];
    }
    smThrottleClear('#token');
    return [(int)$row['id'], ''];
}

/** Formular-Schutz: Token der Sitzung muss passen, und die Anfrage muss von hier kommen */
function smCsrfOk(?array $me): bool
{
    if (!smSameOrigin()) return false;
    if ($me === null) return true;   // Anmelden/Einrichten: dafür gibt es noch keine Sitzung
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && hash_equals($me['csrf'], $t);
}

function smSameOrigin(): bool
{
    // Moderne Browser sagen direkt, woher die Anfrage kommt
    $site = (string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
    if ($site !== '') return $site === 'same-origin' || $site === 'none';
    // Sonst: Origin prüfen. Bei „Referrer-Policy: no-referrer“ schicken manche Browser „null“, das ist dann kein Beweis.
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin === '' || $origin === 'null') return true;
    $host = parse_url($origin, PHP_URL_HOST);
    $port = parse_url($origin, PHP_URL_PORT);
    $own = (string)($_SERVER['HTTP_HOST'] ?? '');
    return $own !== '' && strcasecmp($host . ($port ? ':' . $port : ''), $own) === 0;
}

function smCsrfField(?array $me): string
{
    return $me ? '<input type="hidden" name="csrf" value="' . h($me['csrf']) . '">' : '';
}

/* ---------- Bremse: nach 5 Fehlversuchen wächst die Wartezeit, gemerkt wird nur eine Prüfsumme ---------- */

function smThrottleKeys(string $name): array
{
    $ip = smClientIp();
    return [hash('sha256', 'ip|' . $ip), hash('sha256', 'name|' . mb_strtolower($name) . '|' . $ip)];
}

/** Sekunden, die noch gewartet werden muss (0 = frei) */
function smThrottleWait(string $name): int
{
    $wait = 0;
    foreach (smThrottleKeys($name) as $i => $k) {
        $row = smQ('SELECT fails, last FROM throttle WHERE k = ?', [$k])->fetch();
        if (!$row) continue;
        $over = (int)$row['fails'] - ($i === 0 ? 20 : 5);
        if ($over < 0) continue;
        $lock = min(3600, 30 * (2 ** min($over, 7)));
        $wait = max($wait, (int)$row['last'] + $lock - time());
    }
    return max(0, $wait);
}

function smThrottleFail(string $name): void
{
    foreach (smThrottleKeys($name) as $k) {
        smQ('INSERT INTO throttle (k, fails, last) VALUES (?, 1, ?) ON CONFLICT(k) DO UPDATE SET fails = fails + 1, last = excluded.last', [$k, time()]);
    }
}

function smThrottleClear(string $name): void
{
    smQ('DELETE FROM throttle WHERE k = ?', [smThrottleKeys($name)[1]]);
}

/** Wetter für Gäste: höchstens 30 Abfragen pro Stunde und Adresse. true = erlaubt (und gezählt). */
function smPublicQuota(): bool
{
    $max = 30;   // Wetter-Abfragen pro Stunde und Adresse für Gäste (schützt den Server vor Missbrauch)
    $k = hash('sha256', 'q|' . smClientIp() . '|' . date('YmdH'));
    $row = smQ('SELECT fails FROM throttle WHERE k = ?', [$k])->fetch();
    if ($row && (int)$row['fails'] >= $max) return false;
    smQ('INSERT INTO throttle (k, fails, last) VALUES (?, 1, ?) ON CONFLICT(k) DO UPDATE SET fails = fails + 1, last = excluded.last', [$k, time()]);
    return true;
}

/* ---------- Einrichtung, Anmeldung, Konten ---------- */

/** Erstes Konto = Administrator, dazu der API-Key. Liefert Fehlertext oder ''. */
function smSetup(string $name, string $pass, string $pass2, string $key): string
{
    $name = trim($name);
    if (($e = smCheckName($name)) !== '') return $e;
    if (($e = smCheckPass($pass)) !== '') return $e;
    if ($pass !== $pass2) return t('Die beiden Passwörter stimmen nicht überein.');
    $db = smDb();
    $db->exec('BEGIN IMMEDIATE');
    if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
        $db->exec('ROLLBACK');
        return t('Die Suchmaschine ist schon eingerichtet.');
    }
    smQ('INSERT INTO users (name, pass, is_admin, created_at) VALUES (?, ?, 1, ?)', [$name, password_hash($pass, PASSWORD_DEFAULT), time()]);
    $uid = (int)$db->lastInsertId();
    foreach (smDefaults() as $k => $v) {
        smQ('INSERT OR IGNORE INTO settings (k, v) VALUES (?, ?)', [$k, $v]);
    }
    smQ('INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v', ['api_key', trim($key)]);
    $db->exec('COMMIT');
    unset($GLOBALS['sm_settings']);
    smStartSession($uid);
    return '';
}

function smLogin(string $name, string $pass): string
{
    $name = trim($name);
    if ($name === '' || $pass === '') return t('Bitte Name und Passwort eingeben.');
    $wait = smThrottleWait($name);
    if ($wait > 0) {
        return t('Zu viele Fehlversuche. Bitte in %s noch einmal versuchen.', $wait > 90 ? t('%d Minuten', (int)ceil($wait / 60)) : t('%d Sekunden', $wait));
    }
    $row = smQ('SELECT id, pass FROM users WHERE name = ?', [$name])->fetch();
    // Auch ohne Treffer rechnen, damit die Antwortzeit nichts verrät
    $ok = password_verify($pass, $row ? $row['pass'] : '$2y$10$abcdefghijklmnopqrstuuJ7i0kE5cH3f8QZ6y0K3d0a8mQ9oQe1e');
    if (!$row || !$ok) {
        smThrottleFail($name);
        return t('Name oder Passwort stimmt nicht.');
    }
    smThrottleClear($name);
    if (password_needs_rehash($row['pass'], PASSWORD_DEFAULT)) {
        smQ('UPDATE users SET pass = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $row['id']]);
    }
    smStartSession((int)$row['id']);
    return '';
}

function smUsers(): array
{
    return smQ('SELECT id, name, is_admin, created_at FROM users ORDER BY name COLLATE NOCASE')->fetchAll();
}

function smUserCreate(string $name, string $pass, bool $admin): string
{
    $name = trim($name);
    if (($e = smCheckName($name)) !== '') return $e;
    if (($e = smCheckPass($pass)) !== '') return $e;
    if (count(smUsers()) >= SM_MAX_USERS) return t('Es gibt schon zu viele Konten.');
    if (smQ('SELECT 1 FROM users WHERE name = ?', [$name])->fetchColumn()) return t('Diesen Namen gibt es schon.');
    smQ('INSERT INTO users (name, pass, is_admin, created_at) VALUES (?, ?, ?, ?)', [$name, password_hash($pass, PASSWORD_DEFAULT), $admin ? 1 : 0, time()]);
    return '';
}

/** Neues Passwort setzen. Alle Sitzungen des Kontos enden, außer der eigenen ($keep). */
function smUserSetPass(int $id, string $pass, string $keep = ''): string
{
    if (($e = smCheckPass($pass)) !== '') return $e;
    smQ('UPDATE users SET pass = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
    smQ('DELETE FROM sessions WHERE user_id = ? AND hash != ?', [$id, $keep]);
    return '';
}

function smUserDelete(int $id, array $me): string
{
    if ($id === $me['id']) return t('Du kannst dich nicht selbst löschen.');
    smQ('DELETE FROM sessions WHERE user_id = ?', [$id]);
    smQ('DELETE FROM picsets WHERE user_id = ?', [$id]);
    smQ('DELETE FROM users WHERE id = ?', [$id]);
    return '';
}

function smUserToggleAdmin(int $id): string
{
    $row = smQ('SELECT is_admin FROM users WHERE id = ?', [$id])->fetch();
    if (!$row) return t('Dieses Konto gibt es nicht.');
    if ((int)$row['is_admin'] === 1 && (int)smQ('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn() <= 1) {
        return t('Es muss immer mindestens einen Administrator geben.');
    }
    smQ('UPDATE users SET is_admin = 1 - is_admin WHERE id = ?', [$id]);
    return '';
}

function smUserSavePrefs(int $id, array $prefs): void
{
    smQ('UPDATE users SET prefs = ? WHERE id = ?', [json_encode($prefs, JSON_UNESCAPED_UNICODE), $id]);
}
