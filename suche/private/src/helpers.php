<?php
declare(strict_types=1);
defined('PRIVATE_DIR') || exit;

function cfg(string $key)
{
    return $GLOBALS['config'][$key] ?? null;
}

/** HTML-Escaping */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Text von Brave sicher ausgeben: alles escapen, nur <strong> (Treffer-Hervorhebung) erlauben.
 */
function highlight(string $text): string
{
    $plain = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safe  = e($plain);
    return str_replace(['&lt;strong&gt;', '&lt;/strong&gt;'], ['<strong>', '</strong>'], $safe);
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    // Hinter einem Proxy (z. B. nginx vor Apache)
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** Pfad des Skripts, z. B. "/suche/index.php" */
function script_path(): string
{
    return (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
}

/** Verzeichnis der Suche mit Schrägstrich am Ende, z. B. "/suche/" */
function base_path(): string
{
    $dir = str_replace('\\', '/', dirname(script_path()));
    return rtrim($dir, '/') . '/';
}

/** URL zur Suche mit Parametern */
function url(array $params = []): string
{
    $params = array_filter($params, fn($v) => $v !== null && $v !== '');
    return base_path() . ($params ? '?' . http_build_query($params) : '');
}

function asset(string $file): string
{
    // Dateidatum als Version, damit der Browser nach Updates neu lädt
    $path = dirname((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) . '/assets/' . $file;
    $v = is_file($path) ? (string) filemtime($path) : '1';
    return base_path() . 'assets/' . $file . '?v=' . $v;
}

function redirect(string $to): void
{
    header('Location: ' . $to, true, 303);
    exit;
}

/** Aktuelle URL (nur Pfad + Query) für Weiterleitungen nach POST */
function current_url(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    if ($uri === '' || $uri[0] !== '/' || str_starts_with($uri, '//')) {
        return base_path();
    }
    return $uri;
}

function flash(?string $type = null, ?string $msg = null): array
{
    if ($type !== null) {
        $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
        return [];
    }
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

function send_security_headers(): void
{
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://imgs.search.brave.com; "
        . "style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
    header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-Robots-Tag: noindex, nofollow');
    header('Permissions-Policy: interest-cohort=(), camera=(), microphone=(), geolocation=()');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

/** Theme aus Cookie: auto | light | dark */
function current_theme(): string
{
    $t = $_COOKIE['theme'] ?? 'auto';
    return in_array($t, ['light', 'dark'], true) ? $t : 'auto';
}

function render(string $view, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    $viewFile = PRIVATE_DIR . '/views/' . $view . '.php';
    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    require PRIVATE_DIR . '/views/layout.php';
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}
