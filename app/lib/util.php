<?php
/* Suchmaschine – kleine Helfer: Ausgabe, Header, Adressen, Vorlagen */
defined('SM_VERSION') || exit;

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Text von Brave sicher ausgeben: alles escapen, nur <strong> (Treffer-Hervorhebung) bleibt */
function smHl(string $text): string
{
    $plain = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return str_replace(['&lt;strong&gt;', '&lt;/strong&gt;'], ['<strong>', '</strong>'], h($plain));
}

/** Nur Klartext (Hervorhebungen entfernt) */
function smPlain(string $text): string
{
    return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function smIsHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/** Verzeichnis der Suchmaschine mit Schrägstrich am Ende, z. B. "/suche/" */
function smBase(): string
{
    $dir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    return rtrim($dir, '/') . '/';
}

/** Absolute Adresse der Suchmaschine, z. B. "https://example.org/suche/" */
function smOrigin(): string
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) $host = 'localhost';
    return (smIsHttps() ? 'https' : 'http') . '://' . $host . smBase();
}

/** Adresse mit Parametern, leere Werte fallen weg */
function smUrl(array $params = []): string
{
    $params = array_filter($params, function ($v) { return $v !== null && $v !== '' && $v !== false; });
    return smBase() . ($params ? '?' . http_build_query($params) : '');
}

function smAsset(string $file): string
{
    return smUrl(['a' => 'asset', 'f' => $file, 'v' => SM_VERSION]);
}

function smRedirect(string $to): void
{
    header('Cache-Control: no-store');
    header('Location: ' . $to, true, 303);
    exit;
}

/** Aktuelle Adresse (Pfad + Query) für die Rückkehr nach einem Formular */
function smSelf(): string
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    if ($uri === '' || $uri[0] !== '/' || strpos($uri, '//') === 0) return smBase();
    return $uri;
}

function smHeaders(bool $html = false): void
{
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    // Kamera nur auf den Seiten, die den Scanner anbieten (Werkzeuge, Startseite und Ergebnisse mit dem Scan-Knopf im Suchfeld)
    header('Permissions-Policy: geolocation=(), microphone=(), camera=' . (!empty($GLOBALS['sm_camera']) ? '(self)' : '()') . ', payment=(), usb=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    if (smIsHttps()) header('Strict-Transport-Security: max-age=31536000');
    if ($html) {
        // Für „IPv4/IPv6 auch anzeigen“: eigene IPv4-Subdomain und ipify.org (wird nur auf Klick gefragt)
        $v4 = smHasUsersSafe() ? smSetting('ipv4_host') : '';
        $connect = "'self' https://api4.ipify.org https://api6.ipify.org" . ($v4 !== '' ? ' https://' . $v4 : '');
        // wasm-unsafe-eval nur, wo die Scan-Engine (WebAssembly) läuft: Scanner, QR-Studio, Nummern-Detektiv, Scan-Knopf im Suchfeld
        header("Content-Security-Policy: default-src 'none'; script-src 'self'" . (!empty($GLOBALS['sm_csp_wasm']) ? " 'wasm-unsafe-eval'" : '') . "; style-src 'self'; worker-src 'self'; "
            . "img-src 'self' data: blob: https://imgs.search.brave.com; font-src 'self'; media-src 'self'; connect-src " . $connect . "; manifest-src 'self'; "
            . "frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
    } else {
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    }
}

/** Kurzmeldung für die nächste Seite (nach einer Weiterleitung) */
function smFlash(?string $type = null, ?string $msg = null): array
{
    if ($type !== null) {
        $list = smFlashRead();
        $list[] = [$type, (string)$msg];
        setcookie('sm_flash', base64_encode(json_encode($list)), ['expires' => 0, 'path' => smBase(), 'secure' => smIsHttps(), 'httponly' => true, 'samesite' => 'Lax']);
        $_COOKIE['sm_flash'] = base64_encode(json_encode($list));
        return [];
    }
    $list = smFlashRead();
    if ($list) setcookie('sm_flash', '', ['expires' => time() - 3600, 'path' => smBase(), 'secure' => smIsHttps(), 'httponly' => true, 'samesite' => 'Lax']);
    return $list;
}

function smFlashRead(): array
{
    $raw = $_COOKIE['sm_flash'] ?? '';
    if (!is_string($raw) || $raw === '' || strlen($raw) > 3000) return [];
    $list = json_decode((string)base64_decode($raw, true), true);
    $out = [];
    foreach (is_array($list) ? $list : [] as $m) {
        if (is_array($m) && count($m) === 2 && in_array($m[0], ['ok', 'error'], true)) $out[] = [$m[0], mb_substr((string)$m[1], 0, 300)];
    }
    return $out;
}

/** Design dieses Geräts: auto | light | dark */
/** Hell, dunkel oder wie das Gerät: der Knopf oben (Cookie) gewinnt, sonst das Aussehen des Kontos */
function smTheme(): string
{
    $t = $_COOKIE['sm_theme'] ?? '';
    if (in_array($t, ['light', 'dark', 'auto'], true)) return $t;
    try {
        return smLook(smHasUsersSafe() ? smUser() : null)['mode'];
    } catch (Throwable $e) {
        return 'auto';
    }
}

/** Vorlage aus app/views/ ausgeben, eingebettet in das Grundgerüst */
function smRender(string $view, array $vars = [], bool $layout = true): void
{
    smHeaders(true);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    extract($vars, EXTR_SKIP);
    ob_start();
    require SM_APP . '/views/' . $view . '.php';
    $content = (string)ob_get_clean();
    if ($layout) {
        require SM_APP . '/views/layout.php';
    } else {
        echo $content;
    }
}

// PHP 8.0 kennt array_is_list noch nicht (der Lesemodus braucht es für JSON-LD)
if (!function_exists('array_is_list')) {
    function array_is_list(array $a): bool
    {
        $i = 0;
        foreach ($a as $k => $_) if ($k !== $i++) return false;
        return true;
    }
}

function smClientIp(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

/** Hostname wie „v4.example.org“ (klein, ohne Schema, Pfad, Port) oder '' */
function smCleanHostname(string $h): string
{
    $h = strtolower(trim($h));
    return strlen($h) <= 253 && preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $h) ? $h : '';
}

/**
 * Die eigene Adresse in der anderen Version (IPv4 bei einer IPv6-Verbindung und umgekehrt) kann der Server nicht sehen.
 * Der Browser fragt dafür eine Adresse, die nur über diese Version erreichbar ist:
 * zuerst die eigene IPv4-Subdomain (Verwaltung → Einstellungen), sonst auf Klick ipify.org.
 */
function smIpOtherSources(bool $v6): array
{
    $own = $v6 && smHasUsersSafe() ? smSetting('ipv4_host') : '';
    return [
        'own' => $own !== '' ? 'https://' . $own . smBase() . '?a=myip' : '',
        'ext' => $v6 ? 'https://api4.ipify.org?format=json' : 'https://api6.ipify.org?format=json',
    ];
}

/** ?a=myip: nur die eigene IP als JSON. Für die IPv4-Subdomain, darf von der Hauptadresse aus abgerufen werden. */
function smServeMyIp(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Access-Control-Allow-Origin: *');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex');
    echo json_encode(['ip' => smClientIp()]);
    exit;
}

/** Ein Treffer-Ort (Hostname) ohne „www.“ */
function smHost(string $host): string
{
    return preg_replace('/^www\./i', '', $host);
}

function smInitial(string $s): string
{
    $s = smHost($s);
    return mb_strtoupper(mb_substr($s, 0, 1));
}

/** Name der Suchmaschine: selbst gewählt oder „Suchmaschine ohne Werbung“ in der Sprache des Besuchers */
function smSiteName(): string
{
    $n = smHasUsersSafe() ? trim(smSetting('site_name')) : '';
    return $n !== '' ? $n : t('Suchmaschine ohne Werbung');
}

/** Name als HTML fürs Logo: der Standardname in zwei Zeilen, ein eigener in einer */
function smSiteNameHtml(): string
{
    $n = smHasUsersSafe() ? trim(smSetting('site_name')) : '';
    if ($n !== '') return '<span class="brand__t1">' . h($n) . '</span>';
    return '<span class="brand__t1">' . h(t('Suchmaschine')) . '</span><span class="brand__t2">' . h(t('ohne Werbung')) . '</span>';
}

/** Eigenes Logo (Adresse) oder '' */
function smLogoUrl(): string
{
    if (!smHasUsersSafe() || smSetting('logo_type') === '' || !is_file(SM_DATA_DIR . '/logo.bin')) return '';
    return smUrl(['a' => 'logo', 'v' => smSetting('logo_v')]);
}

/** Logo-Bild: eigenes oder das eingebaute Symbol */
function smLogoHtml(bool $big = false): string
{
    $u = smLogoUrl();
    $cls = 'brand__mark' . ($big ? ' brand__mark--big' : '');
    if ($u !== '') return '<img class="' . $cls . ' brand__mark--img" src="' . h($u) . '" alt="">';
    return '<span class="' . $cls . '" aria-hidden="true"></span>';
}

/** Akzentfarben, aus denen der Administrator wählen kann */
function smAccents(): array
{
    return ['blue' => t('Blau'), 'green' => t('Grün'), 'teal' => t('Petrol'), 'orange' => t('Orange'), 'red' => t('Rot'), 'purple' => t('Lila'), 'gray' => t('Grau')];
}

function smAccent(): string
{
    $a = smHasUsersSafe() ? smSetting('accent') : 'blue';
    return isset(smAccents()[$a]) ? $a : 'blue';
}

/** Zahl hübsch: 1234567 → 1,2 Mio. */
function smNum(int $n): string
{
    if ($n >= 1000000) return str_replace('.', smLang() === 'de' ? ',' : '.', (string)round($n / 1000000, 1)) . t(' Mio.');
    if ($n >= 1000) return str_replace('.', smLang() === 'de' ? ',' : '.', (string)round($n / 1000, 1)) . t(' Tsd.');
    return (string)$n;
}

/** „Mittwoch, 25. November 2026“ bzw. „Wednesday, November 25, 2026“ */
/** Name eines Monats (1 bis 12) in der Sprache der Oberfläche */
function smMonthName(int $m): string
{
    $months = smLang() === 'de' ? ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember']
        : ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    return $months[max(1, min(12, $m)) - 1];
}

function smLongDate(int $ts, bool $weekday = true): string
{
    $days = smLang() === 'de' ? ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'] : ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $months = smLang() === 'de' ? ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember']
        : ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $d = (int)date('j', $ts);
    $m = $months[(int)date('n', $ts) - 1];
    $s = smLang() === 'de' ? $d . '. ' . $m . ' ' . date('Y', $ts) : $m . ' ' . $d . ', ' . date('Y', $ts);
    return $weekday ? $days[(int)date('w', $ts)] . ', ' . $s : $s;
}

/** Kurzer Wochentag: „Mo“ / „Mon“ */
function smShortDay(int $ts): string
{
    $d = smLang() === 'de' ? ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'] : ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    return $d[(int)date('w', $ts)];
}

/* ---------- Weiterleitung nach außen (Bangs, Auf gut Glück) ---------- */

/**
 * Kein 303 nach außen: Browser blockieren das nach einem Formular (CSP form-action). Darum eine Zwischenseite.
 * Ohne Hinweis (Bangs, „Auf gut Glück“) ist sie leer und in der Farbe der Seite, die Zieladresse blitzt also nicht auf.
 * Erst wenn es nach zwei Sekunden noch nicht weitergegangen ist, erscheint ein Knopf zum Weiterklicken.
 */
function smExternalRedirect(string $url, string $title, string $msg = '', int $delay = 0, bool $origin = false): void
{
    smHeaders(true);   // HTML-Regeln, sonst blockiert die CSP das eigene Stylesheet und die Seite blitzt weiß und ungestaltet auf
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    // $origin: nur die Adresse der Suchmaschine mitschicken (nie den Suchbegriff), wie DuckDuckGo und Kagi bei Bangs.
    // Manche Seiten (z. B. Reddit) halten Aufrufe ganz ohne Herkunft sonst für Bots. Ein Skript leitet weiter, weil
    // Chrome bei „refresh“ gar keine Herkunft schickt; ohne JavaScript greift das refresh eine Sekunde später.
    if ($origin) header('Referrer-Policy: origin');
    $quiet = $msg === '' && $delay === 0;
    echo '<!DOCTYPE html><html lang="' . h(smLang()) . '" data-theme="' . h(smTheme()) . '" data-accent="' . h(smAccent()) . '">'
       . '<meta charset="utf-8"><meta name="referrer" content="' . ($origin ? 'origin' : 'no-referrer') . '">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta http-equiv="refresh" content="' . max($origin ? 1 : 0, $delay) . ';url=' . h($url) . '"><title>' . h($quiet ? smSiteName() : $title) . '</title>'
       . '<link rel="stylesheet" href="' . h(smAsset('app.css')) . '"><link rel="stylesheet" href="' . h(smLookUrl(smLook(smUser()))) . '">'
       . ($origin && $delay === 0 ? '<script src="' . h(smAsset('tools/weiter.js')) . '" data-go="' . h($url) . '"></script>' : '')
       . '<main class="redir' . ($quiet ? ' redir--quiet' : '') . '">' . ($msg !== '' ? '<p class="redir__msg">' . h($msg) . '</p>' : '')
       . '<p><a class="btn btn--primary" href="' . h($url) . '" rel="noopener noreferrer">' . h($msg !== '' ? t('Jetzt weiter') : t('Weiter')) . '</a>'
       . ($msg !== '' ? ' <a class="btn" href="' . h(smBase()) . '">' . h(t('Abbrechen')) . '</a>' : '') . '</p></main>';
    exit;
}
