<?php
/* Suchmaschine – Datenordner, Datenbank (SQLite), Einstellungen, Cache, Zähler */
defined('SM_VERSION') || exit;

define('SM_SCHEMA', 1);

function smFatal(string $msg): void
{
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit($msg . "\n");
}

/** Eine kleine Datei, die alle Zugriffe von außen abweist (Apache, LiteSpeed) */
function smDenyFile(string $dir, string $why): void
{
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', '# Suchmaschine: ' . $why . "\n"
            . "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\nOptions -Indexes\n");
    }
    if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
}

/**
 * Legt data/ an und sperrt den Ordner nach außen. In data/config.php steht der zufällige Name der
 * Datenbank-Datei. Eine .php-Datei kann man von außen nicht herunterladen, und ohne den Namen findet
 * niemand die Datenbank, falls die .htaccess einmal nicht greift (z. B. unter nginx).
 */
function smConfig(): array
{
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $file = SM_DATA_DIR . '/config.php';
    if (is_file($file)) {
        $c = require $file;
        if (is_array($c) && !empty($c['db'])) return $cfg = $c;
    }
    if (!is_dir(SM_DATA_DIR) && !@mkdir(SM_DATA_DIR, 0700, true) && !is_dir(SM_DATA_DIR)) {
        smFatal(t('Der Ordner data/ konnte nicht angelegt werden. PHP braucht Schreibrechte im Verzeichnis der Suchmaschine.'));
    }
    smDenyFile(SM_DATA_DIR, 'hier liegt alles Geheime, nichts davon darf von außen abrufbar sein.');
    $c = ['db' => 'sm_' . bin2hex(random_bytes(12)) . '.sqlite'];
    smConfigWrite($c);
    return $cfg = $c;
}

function smConfigWrite(array $c): void
{
    $file = SM_DATA_DIR . '/config.php';
    $php = "<?php\n// Suchmaschine: automatisch angelegt. Nicht löschen, nicht weitergeben.\n"
         . "// Optional: 'update_url' => '' schaltet die Update-Suche ab.\nreturn " . var_export($c, true) . ";\n";
    if (@file_put_contents($file, $php, LOCK_EX) === false) {
        smFatal(t('In den Ordner data/ kann nicht geschrieben werden. Bitte die Schreibrechte prüfen.'));
    }
    @chmod($file, 0600);
    if (function_exists('opcache_invalidate')) @opcache_invalidate($file, true);
}

function smDb(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    if (!extension_loaded('pdo_sqlite')) smFatal(t('Die PHP-Erweiterung pdo_sqlite fehlt. Ohne sie kann die Suchmaschine nichts speichern.'));
    $cfg = smConfig();
    try {
        $pdo = new PDO('sqlite:' . SM_DATA_DIR . '/' . $cfg['db']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $ver = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
        if ($ver < SM_SCHEMA) {
            try { $pdo->query('PRAGMA journal_mode = WAL')->fetchAll(); } catch (Throwable $e) {}
            smMigrate($pdo, $ver);
        }
    } catch (Throwable $e) {
        smFatal(t('Die Datenbank konnte nicht geöffnet werden: ') . $e->getMessage());
    }
    return $pdo;
}

function smMigrate(PDO $pdo, int $from): void
{
    $pdo->beginTransaction();
    if ($from < 1) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE COLLATE NOCASE,
            pass TEXT NOT NULL, is_admin INTEGER NOT NULL DEFAULT 0, prefs TEXT NOT NULL DEFAULT '', created_at INTEGER NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (hash TEXT PRIMARY KEY, user_id INTEGER NOT NULL, csrf TEXT NOT NULL,
            expires INTEGER NOT NULL, touched INTEGER NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS throttle (k TEXT PRIMARY KEY, fails INTEGER NOT NULL, last INTEGER NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS cache (k TEXT PRIMARY KEY, at INTEGER NOT NULL, v TEXT NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS usage (month TEXT NOT NULL, kind TEXT NOT NULL, n INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (month, kind))");
    }
    $pdo->exec('PRAGMA user_version = ' . SM_SCHEMA);
    $pdo->commit();
}

function smQ(string $sql, array $args = []): PDOStatement
{
    $st = smDb()->prepare($sql);
    $st->execute($args);
    return $st;
}

/* ---------- Einstellungen (gelten für die ganze Installation) ---------- */

/** Standardwerte. Land und Sprache richten sich beim Einrichten nach dem Browser. */
function smDefaults(): array
{
    return [
        'api_key'       => '',
        'site_name'     => '',      // leer = „Suchmaschine ohne Werbung“ in der Sprache des Besuchers
        'accent'        => 'blue',
        'scroll'        => 'pages', // pages = Seitenweise, endless = endlos scrollen
        'logo_type'     => '',
        'logo_v'        => '0',
        'monthly_limit' => '950',
        'cache_min'     => '15',
        'country'       => smLang() === 'de' ? 'DE' : 'ALL',
        'search_lang'   => smLang() === 'de' ? 'de' : 'en',
        'safesearch'    => 'moderate',
        'per_page'      => '10',
        'public'        => '0',     // Suchen ohne Anmeldung erlaubt?
        'public_hour'   => '20',    // dann höchstens so viele Suchen pro Stunde und Adresse
        'auto_update'   => '0',
    ];
}

function smSetting(string $k): string
{
    if (!isset($GLOBALS['sm_settings'])) {
        $all = smDefaults();
        foreach (smQ('SELECT k, v FROM settings')->fetchAll() as $r) $all[$r['k']] = $r['v'];
        $GLOBALS['sm_settings'] = $all;
    }
    return (string)($GLOBALS['sm_settings'][$k] ?? '');
}

function smSetSetting(string $k, string $v): void
{
    smQ('INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v', [$k, $v]);
    smSetting($k);
    $GLOBALS['sm_settings'][$k] = $v;
}

/* ---------- Cache: kurz, wie es Braves Bedingungen erlauben ---------- */

function smCacheGet(string $key): ?array
{
    $min = (int)smSetting('cache_min');
    if ($min <= 0) return null;
    $row = smQ('SELECT at, v FROM cache WHERE k = ?', [hash('sha256', $key)])->fetch();
    if (!$row || (int)$row['at'] < time() - $min * 60) return null;
    $v = json_decode($row['v'], true);
    return is_array($v) ? $v : null;
}

function smCacheSet(string $key, array $data): void
{
    if ((int)smSetting('cache_min') <= 0) return;
    smQ('INSERT INTO cache (k, at, v) VALUES (?, ?, ?) ON CONFLICT(k) DO UPDATE SET at = excluded.at, v = excluded.v',
        [hash('sha256', $key), time(), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
}

/* ---------- Zähler: echte Anfragen an Brave pro Monat ---------- */

function smUsageMonth(?string $month = null): int
{
    return (int)smQ('SELECT COALESCE(SUM(n), 0) FROM usage WHERE month = ?', [$month ?? date('Y-m')])->fetchColumn();
}

/** Reserviert eine Anfrage, falls das Monatslimit es erlaubt. Gezählt wird vorher, auch Fehlschläge zählen. */
function smUsageReserve(string $kind): bool
{
    $db = smDb();
    $db->exec('BEGIN IMMEDIATE');
    try {
        $limit = (int)smSetting('monthly_limit');
        if ($limit > 0 && smUsageMonth() >= $limit) {
            $db->exec('ROLLBACK');
            return false;
        }
        smQ('INSERT INTO usage (month, kind, n) VALUES (?, ?, 1) ON CONFLICT(month, kind) DO UPDATE SET n = n + 1', [date('Y-m'), $kind]);
        $db->exec('COMMIT');
        return true;
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
}

/** Letzte Monate: ['2026-11' => ['web' => 12, 'images' => 3, …], …] */
function smUsageHistory(int $months = 6): array
{
    $out = [];
    foreach (smQ('SELECT month, kind, n FROM usage ORDER BY month DESC')->fetchAll() as $r) {
        if (!isset($out[$r['month']]) && count($out) >= $months) break;
        $out[$r['month']][$r['kind']] = (int)$r['n'];
    }
    return $out;
}

/** Gelegentlich aufräumen: alter Cache, abgelaufene Sitzungen, alte Sperren */
function smMaintenance(): void
{
    if (random_int(1, 40) !== 1) return;
    $min = max(1, (int)smSetting('cache_min'));
    smQ('DELETE FROM cache WHERE at < ?', [time() - $min * 60]);
    smQ('DELETE FROM sessions WHERE expires < ?', [time()]);
    smQ('DELETE FROM throttle WHERE last < ?', [time() - 86400]);
}
