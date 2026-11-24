<?php
declare(strict_types=1);
defined('PRIVATE_DIR') || exit;

const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

/** JSON-Datei lesen (mit Lesesperre) */
function json_read(string $file, array $default = []): array
{
    if (!is_file($file)) {
        return $default;
    }
    $fp = @fopen($file, 'rb');
    if (!$fp) {
        return $default;
    }
    flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : $default;
}

/**
 * JSON-Datei atomar ändern: Sperre holen, lesen, $fn anwenden, zurückschreiben.
 * Gibt den neuen Inhalt zurück.
 */
function json_update(string $file, callable $fn, array $default = []): array
{
    $fp = @fopen($file, 'c+');
    if (!$fp) {
        throw new RuntimeException('Datei kann nicht geschrieben werden: ' . basename($file));
    }
    try {
        flock($fp, LOCK_EX);
        $raw  = stream_get_contents($fp);
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            $data = $default;
        }
        $new = $fn($data);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($new, JSON_FLAGS));
        fflush($fp);
        return $new;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/* ---------- Cache ---------- */

function cache_file(string $key): string
{
    return CACHE_DIR . '/' . hash('sha256', $key) . '.json';
}

function cache_get(string $key): ?array
{
    $ttl = (int) cfg('cache_ttl');
    if ($ttl <= 0) {
        return null;
    }
    $file = cache_file($key);
    if (!is_file($file) || filemtime($file) < time() - $ttl) {
        return null;
    }
    $data = json_decode((string) @file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

function cache_set(string $key, array $data): void
{
    if ((int) cfg('cache_ttl') <= 0) {
        return;
    }
    $file = cache_file($key);
    $tmp  = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false) {
        @rename($tmp, $file);
    }
}

/* ---------- Nutzungszähler ---------- */

function usage_file(): string
{
    return DATA_DIR . '/usage.json';
}

function usage_this_month(): int
{
    $data = json_read(usage_file());
    return (int) ($data[date('Y-m')] ?? 0);
}

function usage_history(int $months = 6): array
{
    $data = json_read(usage_file());
    krsort($data);
    return array_slice($data, 0, $months, true);
}

/**
 * Reserviert einen API-Call, falls das Monatslimit es erlaubt.
 * Zählt vor dem Request, damit auch fehlgeschlagene Calls nicht übers Limit führen.
 */
function usage_reserve(): bool
{
    $ok    = false;
    $limit = (int) cfg('monthly_limit');
    json_update(usage_file(), function (array $d) use (&$ok, $limit) {
        $m = date('Y-m');
        $n = (int) ($d[$m] ?? 0);
        if ($limit > 0 && $n >= $limit) {
            return $d;
        }
        $d[$m] = $n + 1;
        $ok = true;
        return $d;
    });
    return $ok;
}

/* ---------- Aufräumen (läuft gelegentlich) ---------- */

function maintenance(): void
{
    if (random_int(1, 50) !== 1) {
        return;
    }
    $now = time();
    $ttl = max(60, (int) cfg('cache_ttl'));
    foreach (glob(CACHE_DIR . '/*') ?: [] as $f) {
        if (is_file($f) && filemtime($f) < $now - $ttl) {
            @unlink($f);
        }
    }
    $sessionMax = (int) cfg('session_days') * 86400;
    foreach (glob(SESSION_DIR . '/sess_*') ?: [] as $f) {
        if (is_file($f) && filemtime($f) < $now - $sessionMax) {
            @unlink($f);
        }
    }
}
