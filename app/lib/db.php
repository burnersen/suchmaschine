<?php
/* Suchmaschine – Datenordner, Datenbank (SQLite), Einstellungen, Cache, Zähler */
defined('SM_VERSION') || exit;

define('SM_SCHEMA', 6);

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
function smConfig(bool $reload = false): array
{
    static $cfg = null;
    if ($cfg !== null && !$reload) return $cfg;
    $file = SM_DATA_DIR . '/config.php';
    if (is_file($file)) {
        $c = require $file;
        if ($reload && function_exists('opcache_invalidate')) @opcache_invalidate($file, true);
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
         . "// Optional: 'update_url' => '' schaltet die Update-Suche ab. Eigene Releases (Fork): 'update_url' und 'update_pubkey', siehe BUILD.md.\nreturn " . var_export($c, true) . ";\n";
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
    if ($from < 2) {
        // Zwischenspeicher mit eigener Haltbarkeit pro Eintrag (Brave kurz, andere Dienste länger, Wechselkurse einen Tag)
        $pdo->exec("DROP TABLE IF EXISTS cache");
        $pdo->exec("CREATE TABLE cache (k TEXT PRIMARY KEY, exp INTEGER NOT NULL, v TEXT NOT NULL)");
        // Verbrauch pro Tag, Suchdienst, Art und Konto (0 = ohne Anmeldung). Credits als Kommazahl.
        $pdo->exec("CREATE TABLE IF NOT EXISTS usage_log (day TEXT NOT NULL, provider TEXT NOT NULL, kind TEXT NOT NULL, user_id INTEGER NOT NULL DEFAULT 0,
            credits REAL NOT NULL DEFAULT 0, req INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (day, provider, kind, user_id))");
        $pdo->exec("CREATE INDEX IF NOT EXISTS usage_prov ON usage_log (provider, day, user_id)");
        $pdo->exec("INSERT OR IGNORE INTO usage_log (day, provider, kind, user_id, credits, req) SELECT month || '-01', 'brave', kind, 0, n, n FROM usage");
        $pdo->exec("DROP TABLE IF EXISTS usage");
        // Zustand der Suchdienste: letzter Erfolg, letzter Fehler, Pause nach Störungen, Antwortzeit
        $pdo->exec("CREATE TABLE IF NOT EXISTS pstat (provider TEXT PRIMARY KEY, ok_at INTEGER NOT NULL DEFAULT 0, err_at INTEGER NOT NULL DEFAULT 0,
            err TEXT NOT NULL DEFAULT '', pause_until INTEGER NOT NULL DEFAULT 0, ms_avg REAL NOT NULL DEFAULT 0, ms_n INTEGER NOT NULL DEFAULT 0, fails INTEGER NOT NULL DEFAULT 0)");
        // Kontingente pro Konto, geheimer Login-Token (nur Prüfsumme zum Suchen, dazu verschlüsselt zum Anzeigen)
        $pdo->exec("ALTER TABLE users ADD COLUMN quota TEXT NOT NULL DEFAULT ''");
        $pdo->exec("ALTER TABLE users ADD COLUMN token_hash TEXT");
        $pdo->exec("ALTER TABLE users ADD COLUMN token_enc TEXT NOT NULL DEFAULT ''");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS users_token ON users (token_hash)");
        $pdo->exec("ALTER TABLE sessions ADD COLUMN days INTEGER NOT NULL DEFAULT 90");
        $pdo->exec("CREATE TABLE IF NOT EXISTS shortlinks (code TEXT PRIMARY KEY, url TEXT NOT NULL, user_id INTEGER NOT NULL, created INTEGER NOT NULL, hits INTEGER NOT NULL DEFAULT 0)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS shortlinks_user ON shortlinks (user_id)");
    }
    if ($from < 3) {
        // 2.0: Kurzlinks mit Wunschname, Ablauf, Aufruf-Grenze, Passwort, an/aus, Titel des Ziels, Zustand des Ziels, Statistik pro Tag
        foreach (['title TEXT NOT NULL DEFAULT \'\'', 'expires INTEGER NOT NULL DEFAULT 0', 'max_hits INTEGER NOT NULL DEFAULT 0', 'pass TEXT NOT NULL DEFAULT \'\'',
                  'active INTEGER NOT NULL DEFAULT 1', 'updated INTEGER NOT NULL DEFAULT 0', 'checked INTEGER NOT NULL DEFAULT 0', 'dead INTEGER NOT NULL DEFAULT 0',
                  'note TEXT NOT NULL DEFAULT \'\'', 'last_hit INTEGER NOT NULL DEFAULT 0'] as $col) {
            $pdo->exec('ALTER TABLE shortlinks ADD COLUMN ' . $col);
        }
        // Nur ein Zähler pro Kurzlink und Tag, keine IP, kein Cookie
        $pdo->exec("CREATE TABLE IF NOT EXISTS short_days (code TEXT NOT NULL, day TEXT NOT NULL, n INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (code, day))");
        // Text-Schnipsel: nur verschlüsselt (Schlüssel bleibt im Link hinter dem #, der Server sieht ihn nie)
        $pdo->exec("CREATE TABLE IF NOT EXISTS notes (id TEXT PRIMARY KEY, user_id INTEGER NOT NULL, data TEXT NOT NULL, created INTEGER NOT NULL,
            expires INTEGER NOT NULL, views_left INTEGER NOT NULL DEFAULT 1, has_pass INTEGER NOT NULL DEFAULT 0, label TEXT NOT NULL DEFAULT '',
            auth TEXT NOT NULL DEFAULT '')");
        $pdo->exec("CREATE INDEX IF NOT EXISTS notes_user ON notes (user_id)");
    }
    if ($from < 4) {
        // 2.1: die große Bang-Liste (Kagi), eine Zeile pro Kürzel; Zweitkürzel zeigen über main auf das Hauptkürzel
        $pdo->exec("CREATE TABLE IF NOT EXISTS bang_list (t TEXT PRIMARY KEY, main TEXT NOT NULL, a TEXT NOT NULL DEFAULT '', n TEXT NOT NULL,
            u TEXT NOT NULL, d TEXT NOT NULL DEFAULT '', c TEXT NOT NULL DEFAULT '', f TEXT NOT NULL DEFAULT '', x TEXT NOT NULL DEFAULT '')");
        $pdo->exec("CREATE INDEX IF NOT EXISTS bang_list_d ON bang_list (d)");
        // Erreichbarkeit pro Domain: wann geprüft, wie oft hintereinander nicht erreichbar
        $pdo->exec("CREATE TABLE IF NOT EXISTS bang_check (d TEXT PRIMARY KEY, checked INTEGER NOT NULL DEFAULT 0, fails INTEGER NOT NULL DEFAULT 0,
            status INTEGER NOT NULL DEFAULT 0, err TEXT NOT NULL DEFAULT '', ok_at INTEGER NOT NULL DEFAULT 0)");
    }
    if ($from >= 1 && $from < 5) {
        // 2.2: Tavily, Exa, parallel.ai und Firecrawl sind raus, Google über Bright Data kommt dazu und steht vorne
        $get = $pdo->prepare('SELECT v FROM settings WHERE k = ?');
        $set = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v');
        foreach (['prov_order' => 'brightdata,brave', 'multi_set' => 'brightdata,brave'] as $k => $def) {
            $get->execute([$k]);
            $old = $get->fetchColumn();
            if ($old === false) continue;
            $keep = array_values(array_intersect(explode(',', (string)$old), ['brave']));
            $set->execute([$k, implode(',', array_merge(['brightdata'], $keep))]);
        }
        $pdo->exec("DELETE FROM settings WHERE k LIKE 'p\_tavily\_%' ESCAPE '\' OR k LIKE 'p\_exa\_%' ESCAPE '\'
            OR k LIKE 'p\_parallel\_%' ESCAPE '\' OR k LIKE 'p\_firecrawl\_%' ESCAPE '\'");
        $pdo->exec("DELETE FROM pstat WHERE provider IN ('tavily', 'exa', 'parallel', 'firecrawl')");
    }
    if ($from >= 1 && $from < 6) {
        // 3.0: nur noch Brave. Google über Bright Data, Reihenfolge, Lastverteilung, „Alle zusammen“, Wettlauf, Warteseite,
        // Karten und Suchvorschläge sind raus. Gäste dürfen die Seite benutzen, ihre Suchen gehen zur Ausweich-Suchmaschine.
        $pdo->exec("DELETE FROM settings WHERE k LIKE 'p\_brightdata\_%' ESCAPE '\' OR k IN ('prov_order', 'balance', 'multi_set', 'answers', 'wiki',
            'stream', 'race', 'suggest', 'fallback_auto', 'anon_quota', 'public_hour')");
        $pdo->exec("DELETE FROM pstat WHERE provider <> 'brave'");
        $pdo->exec("INSERT INTO settings (k, v) VALUES ('public', '1') ON CONFLICT(k) DO UPDATE SET v = '1'");
        $pdo->exec("DELETE FROM cache");
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
        'sort_web'      => 'date',  // Sortierung bei „Alle“: date = neueste zuerst, rel = Relevanz
        'sort_news'     => 'date',  // Sortierung bei News
        'sort_videos'   => 'date',  // Sortierung bei Videos
        'news_fresh'    => 'pd',    // erste Zeitstufe bei „Neueste zuerst“ (Alle, News, Videos): pd, pw, pm, py oder all
        'news_expand'   => '1',     // am Ende einer Zeitstufe automatisch mit der nächsten weitermachen
        'logo_type'     => '',
        'logo_v'        => '0',
        'monthly_limit' => '950',
        'cache_min'     => '15',
        'country'       => smLang() === 'de' ? 'DE' : 'ALL',
        'search_lang'   => smLang() === 'de' ? 'de' : 'en',
        'safesearch'    => 'moderate',
        'per_page'      => '10',
        'public'        => '1',     // Seite ohne Anmeldung nutzbar? (Gäste suchen nie bei Brave, sondern bei der Ausweich-Suchmaschine)
        'auto_update'   => '0',
        'img_proxy'     => '1',     // große Bilder über den eigenen Server laden
        // Ausweich-Suchmaschine: Standard für Gäste und für Konten ohne eigene Wahl, wenn Brave nicht kann
        'fallback_url'  => 'https://www.startpage.com/do/search?q={q}',
        'warn_pct'      => '80',    // Warnhinweis für Administratoren ab diesem Verbrauch
        'weather_src'   => 'brightsky',
        'shortener'     => '1',
        'ipv4_host'     => '',      // eigene Subdomain nur mit IPv4 (A-Eintrag), um die IPv4 auch bei IPv6-Verbindung zu zeigen
        'bang_auto'     => '1',     // Bang-Liste einmal pro Woche frisch von GitHub (Kagi) holen
        'bang_check'    => '1',     // Zielseiten der Bangs nach und nach auf Erreichbarkeit prüfen
        'bang_ref'      => '1',     // bei Bangs die Adresse der Suchmaschine als Herkunft mitschicken (nie den Suchbegriff)
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

/* ---------- Zwischenspeicher: jeder Eintrag hat seine eigene Haltbarkeit (Sekunden) ---------- */

function smCacheGet(string $key): ?array
{
    $row = smQ('SELECT exp, v FROM cache WHERE k = ?', [hash('sha256', $key)])->fetch();
    if (!$row || (int)$row['exp'] < time()) return null;
    $v = json_decode($row['v'], true);
    return is_array($v) ? $v : null;
}

function smCacheSet(string $key, array $data, int $ttl): void
{
    if ($ttl <= 0) return;
    smQ('INSERT INTO cache (k, exp, v) VALUES (?, ?, ?) ON CONFLICT(k) DO UPDATE SET exp = excluded.exp, v = excluded.v',
        [hash('sha256', $key), time() + $ttl, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
}

/* ---------- Zähler: Credits pro Tag, Suchdienst, Art und Konto ---------- */

/** Summe der Credits eines Suchdienstes ab einem Tag (Y-m-d), wahlweise nur für ein Konto */
function smUsageSum(string $provider, string $fromDay, ?int $userId = null, ?string $toDay = null): float
{
    $sql = 'SELECT COALESCE(SUM(credits), 0) FROM usage_log WHERE provider = ? AND day >= ?';
    $args = [$provider, $fromDay];
    if ($toDay !== null) { $sql .= ' AND day < ?'; $args[] = $toDay; }
    if ($userId !== null) { $sql .= ' AND user_id = ?'; $args[] = $userId; }
    return (float)smQ($sql, $args)->fetchColumn();
}

/** Credits buchen (negativ = gutschreiben, z. B. wenn die Anfrage gar nicht ankam) */
function smUsageAdd(string $provider, string $kind, int $userId, float $credits, int $req = 1): void
{
    smQ('INSERT INTO usage_log (day, provider, kind, user_id, credits, req) VALUES (?, ?, ?, ?, ?, ?)
         ON CONFLICT(day, provider, kind, user_id) DO UPDATE SET credits = MAX(0, credits + excluded.credits), req = MAX(0, req + excluded.req)',
        [date('Y-m-d'), $provider, $kind, $userId, $credits, $req]);
}

/** Verlauf nach Kalendermonaten: ['2026-11' => ['brave' => 12.0], …] */
function smUsageHistory(int $months = 6): array
{
    $out = [];
    foreach (smQ("SELECT substr(day, 1, 7) AS m, provider, SUM(credits) AS c FROM usage_log GROUP BY m, provider ORDER BY m DESC")->fetchAll() as $r) {
        if (!isset($out[$r['m']]) && count($out) >= $months) break;
        $out[$r['m']][$r['provider']] = (float)$r['c'];
    }
    return $out;
}

/** Gelegentlich aufräumen: alter Cache, abgelaufene Sitzungen, alte Sperren */
function smMaintenance(): void
{
    if (random_int(1, 40) !== 1) return;
    smQ('DELETE FROM cache WHERE exp < ?', [time()]);
    smQ('DELETE FROM sessions WHERE expires < ?', [time()]);
    smQ('DELETE FROM throttle WHERE last < ?', [time() - 86400]);
    smQ('DELETE FROM usage_log WHERE day < ?', [date('Y-m-d', strtotime('-25 months'))]);
}

function smHasUsersSafe(): bool
{
    try {
        return smHasUsers();
    } catch (Throwable $e) {
        return false;
    }
}
