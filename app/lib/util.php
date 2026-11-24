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
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    if (smIsHttps()) header('Strict-Transport-Security: max-age=31536000');
    if ($html) {
        header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; "
            . "img-src 'self' data: https://imgs.search.brave.com; connect-src 'self'; manifest-src 'self'; "
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
function smTheme(): string
{
    $t = $_COOKIE['sm_theme'] ?? 'auto';
    return in_array($t, ['light', 'dark'], true) ? $t : 'auto';
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

function smClientIp(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
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

/** Zahl hübsch: 1234567 → 1,2 Mio. */
function smNum(int $n): string
{
    if ($n >= 1000000) return str_replace('.', smLang() === 'de' ? ',' : '.', (string)round($n / 1000000, 1)) . t(' Mio.');
    if ($n >= 1000) return str_replace('.', smLang() === 'de' ? ',' : '.', (string)round($n / 1000, 1)) . t(' Tsd.');
    return (string)$n;
}
