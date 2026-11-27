<?php
/* Suchmaschine – der Suchdienst: Brave Search.
   Key, Stichtag, Monatslimit, Zähler, Prognose, Zwischenspeicher und Zustand (ein gestörter Dienst pausiert kurz),
   dazu Kontingente pro Konto. Geht Brave nicht, leitet die Suche zur Ausweich-Suchmaschine weiter (smFallback…). */
defined('SM_VERSION') || exit;

/** Wenn Brave nicht kann: kein Key, Limit erreicht, gestört oder für das Konto nicht erlaubt */
class SmSearchExhausted extends SmSearchError
{
}

/**
 * Der Suchdienst (eine Liste, damit Verbrauch, Kontingente und Verwaltung einheitlich bleiben).
 * free = Freikontingent pro Monat, limit = Standardlimit (50 Suchen Puffer), cost = Credits pro Suche,
 * unit = worin gezählt wird, kinds = welche Reiter der Dienst kann.
 */
function smProviders(): array
{
    return [
        'brave' => [
            'name' => 'Brave Search', 'badge' => 'Brave', 'free' => 1000, 'limit' => 950, 'cost' => 1, 'unit' => t('Suchen'),
            'kinds' => ['web', 'images', 'news', 'videos'],
            'site' => 'https://api-dashboard.search.brave.com', 'key_hint' => 'BSA…',
        ],
    ];
}

/* ---------- Einstellungen pro Dienst ---------- */

/**
 * Einstellung eines Dienstes. Brave nutzt aus Kompatibilität die alten Namen (api_key, monthly_limit, cache_min).
 * Felder: key, start (Stichtag Y-m-d), limit, cost, cache (Brave: Minuten, sonst Stunden), on
 */
function smProvGet(string $id, string $field): string
{
    if ($id === 'brave') {
        $legacy = ['key' => 'api_key', 'limit' => 'monthly_limit', 'cache' => 'cache_min'];
        if (isset($legacy[$field])) return smSetting($legacy[$field]);
    }
    $v = smSetting('p_' . $id . '_' . $field);
    if ($v !== '') return $v;
    $p = smProviders()[$id] ?? [];
    switch ($field) {
        case 'limit': return (string)($p['limit'] ?? 0);
        case 'cost':  return (string)($p['cost'] ?? 1);
        case 'cache': return '6';      // Stunden
        case 'on':    return '1';
    }
    return '';
}

function smProvSet(string $id, string $field, string $v): void
{
    if ($id === 'brave') {
        $legacy = ['key' => 'api_key', 'limit' => 'monthly_limit', 'cache' => 'cache_min'];
        if (isset($legacy[$field])) { smSetSetting($legacy[$field], $v); return; }
    }
    smSetSetting('p_' . $id . '_' . $field, $v);
}

/** Alle Einstellungsnamen der Dienste (für die Sicherung der Installation) */
function smProvSettingKeys(): array
{
    $k = [];
    foreach (array_keys(smProviders()) as $id) {
        foreach (['key', 'start', 'limit', 'cost', 'cache', 'on'] as $f) {
            if ($id === 'brave' && in_array($f, ['key', 'limit', 'cache'], true)) continue;
            $k[] = 'p_' . $id . '_' . $f;
        }
    }
    return $k;
}

/** Hat der Dienst einen Key und ist eingeschaltet? */
function smProvActive(string $id): bool
{
    return isset(smProviders()[$id]) && trim(smProvGet($id, 'key')) !== '' && smProvGet($id, 'on') !== '0';
}

/** Eingerichtete Dienste (mit Key und eingeschaltet), mit $activeOnly = false alle bekannten */
function smProvOrder(bool $activeOnly = true): array
{
    $all = array_keys(smProviders());
    return $activeOnly ? array_values(array_filter($all, 'smProvActive')) : $all;
}

/** Zwischenspeicher in Sekunden. Brave erlaubt nur kurzes Speichern (höchstens 60 Minuten). */
function smProvTtl(string $id): int
{
    if ($id === 'brave') return max(0, min(60, (int)smProvGet('brave', 'cache'))) * 60;
    return max(0, min(168, (int)smProvGet($id, 'cache'))) * 3600;
}

/* ---------- Abrechnungszeitraum, Verbrauch, Prognose ---------- */

/* ---------- Stand direkt vom Dienst: Reset-Datum und Restguthaben ---------- */

/**
 * Was der Dienst selbst über sein Kontingent meldet (Brave in jeder Antwort):
 * end = nächster Reset (Unix-Zeit), left = Rest laut Dienst, base = eigener Zähler beim Abruf, from = Zeitraum beim Abruf, at = Abrufzeit.
 * Angaben, die über 40 Tage nicht bestätigt wurden, gelten nicht mehr.
 */
function smProvApi(string $id): array
{
    $a = json_decode(smSetting('p_' . $id . '_api'), true);
    if (!is_array($a) || (int)($a['at'] ?? 0) < time() - 40 * 86400) return [];
    return $a;
}

function smProvApiSave(string $id, array $a): void
{
    $a['at'] = time();
    smProvSet($id, 'api', json_encode($a));
}

/** Tag im Monat, an dem das Kontingent neu startet, und woher er kommt: api (vom Dienst gemeldet), manual (Stichtag), default (Monatserster) */
function smProvAnchor(string $id): array
{
    $a = smProvApi($id);
    if (!empty($a['end'])) return [(int)date('j', (int)$a['end']), 'api'];
    if (preg_match('/^\d{4}-\d{2}-(\d{2})$/', smProvGet($id, 'start'), $m)) return [max(1, min(31, (int)$m[1])), 'manual'];
    return [1, 'default'];
}

/** Rest laut Dienst, heruntergerechnet mit allem, was seit dem Abruf gezählt wurde, oder null (unbekannt) */
function smProvApiLeft(string $id): ?float
{
    $a = smProvApi($id);
    if (!isset($a['left']) || !is_numeric($a['left'])) return null;
    $c = smProvCycle($id);
    if (($a['from'] ?? '') !== $c['from']) return null;     // inzwischen neuer Zeitraum: alte Angabe passt nicht mehr
    return max(0.0, (float)$a['left'] - max(0.0, smUsageSum($id, $c['from']) - (float)($a['base'] ?? 0)));
}

/** Meldet der Dienst seinen Verbrauch selbst? Dann braucht es kein Feld „schon verbraucht“. */
function smProvApiKnowsUsage(string $id): bool
{
    return smProvApiLeft($id) !== null;
}

/**
 * Korrektur im laufenden Zeitraum: Credits, die schon verbraucht waren, bevor die Suchmaschine mitgezählt hat
 * (oder der Abgleich mit dem Dienst). Steht als eigene Zeile (Konto -1) am ersten Tag des Zeitraums und verfällt mit ihm.
 */
function smProvAdjust(string $id): float
{
    return (float)smQ("SELECT COALESCE(SUM(credits), 0) FROM usage_log WHERE provider = ? AND user_id = -1 AND day >= ?", [$id, smProvCycle($id)['from']])->fetchColumn();
}

/** Korrekturzeile setzen. Darf auch negativ sein, wenn das Dashboard weniger zeigt, als hier gezählt wurde. */
function smProvSetAdjust(string $id, float $credits): void
{
    $from = smProvCycle($id)['from'];
    smQ("DELETE FROM usage_log WHERE provider = ? AND user_id = -1 AND day >= ?", [$id, $from]);
    if (abs($credits) >= 0.0005) smQ("INSERT INTO usage_log (day, provider, kind, user_id, credits, req) VALUES (?, ?, 'adjust', -1, ?, 0)", [$from, $id, round($credits, 3)]);
}

/**
 * Zähler auf den Stand laut Dashboard setzen: Danach ist der Verbrauch im laufenden Zeitraum genau $used,
 * und alles, was ab jetzt gezählt wird, kommt dazu. Für Dienste, die ihren Verbrauch nicht selbst melden.
 */
function smProvSetUsed(string $id, float $used): void
{
    $now = smUsageSum($id, smProvCycle($id)['from']);
    smProvSetAdjust($id, smProvAdjust($id) + ($used - $now));
    smProvSet($id, 'usedset', (string)time());
}

/** Brave schickt in jeder Antwort mit, wann das Monatskontingent neu startet (X-RateLimit-Reset, Sekunden) */
function smProvBraveHeaders(array $h): void
{
    $reset = array_map('trim', explode(',', (string)($h['x-ratelimit-reset'] ?? '')));
    $policy = array_map('trim', explode(',', (string)($h['x-ratelimit-policy'] ?? '')));
    $limit = array_map('trim', explode(',', (string)($h['x-ratelimit-limit'] ?? '')));
    $remain = array_map('trim', explode(',', (string)($h['x-ratelimit-remaining'] ?? '')));
    // Das Fenster mit der längsten Laufzeit ist das Monatskontingent (das erste ist das pro Sekunde)
    $best = -1;
    $win = 0;
    foreach ($policy as $i => $p) {
        if (preg_match('/w=(\d+)/', $p, $m) && (int)$m[1] > $win) { $win = (int)$m[1]; $best = $i; }
    }
    if ($best < 0 && count($reset) > 1) $best = count($reset) - 1;
    if ($best < 0 || $win < 86400 && $win !== 0 || !isset($reset[$best]) || !ctype_digit($reset[$best])) return;
    $secs = (int)$reset[$best];
    if ($secs <= 0 || $secs > 32 * 86400) return;
    $a = ['end' => time() + $secs];
    // Monatliches Limit nur, wenn es eins gibt (0 = ohne Grenze, z. B. beim Guthaben-Plan)
    if (isset($limit[$best], $remain[$best]) && ctype_digit($limit[$best]) && (int)$limit[$best] > 0 && ctype_digit($remain[$best])) {
        $a['left'] = (float)$remain[$best];
    }
    $old = smProvApi('brave');
    // Nicht bei jeder Suche schreiben: nur wenn sich etwas ändert oder die letzte Bestätigung über eine Stunde her ist
    if ($old && abs((int)($old['end'] ?? 0) - $a['end']) < 600 && !isset($a['left']) && !isset($old['left']) && (int)$old['at'] > time() - 3600) return;
    $cyc = smProvCycleFor($a['end']);
    $a['from'] = $cyc;
    $a['base'] = smUsageSum('brave', $cyc);
    smProvApiSave('brave', $a);
}

/** Beginn (Y-m-d) des Zeitraums, der zu einem Reset-Zeitpunkt gehört (ein Monat davor, gleicher Tag) */
function smProvCycleFor(int $end): string
{
    $d = (int)date('j', $end);
    $y = (int)date('Y', $end);
    $mo = (int)date('n', $end) - 1;
    if ($mo < 1) { $mo = 12; $y--; }
    return date('Y-m-d', mktime(0, 0, 0, $mo, min($d, (int)date('t', mktime(0, 0, 0, $mo, 1, $y))), $y));
}

/**
 * Der laufende Abrechnungszeitraum. Der Stichtag legt den Tag im Monat fest, an dem das Kontingent neu startet
 * (ohne Stichtag: Monatserster). Tage, die es in einem Monat nicht gibt (31.), rücken auf den letzten Tag.
 */
function smProvCycle(string $id, ?int $now = null): array
{
    $now = $now ?? time();
    $anchor = smProvAnchor($id)[0];
    $at = function (int $y, int $mo) use ($anchor): int {
        return mktime(0, 0, 0, $mo, min($anchor, (int)date('t', mktime(0, 0, 0, $mo, 1, $y))), $y);
    };
    $y = (int)date('Y', $now);
    $mo = (int)date('n', $now);
    $start = $at($y, $mo);
    if ($start > $now) {
        if (--$mo < 1) { $mo = 12; $y--; }
        $start = $at($y, $mo);
    }
    $ny = $y;
    $nm = $mo + 1;
    if ($nm > 12) { $nm = 1; $ny++; }
    $end = $at($ny, $nm);
    return [
        'start' => $start, 'end' => $end, 'from' => date('Y-m-d', $start), 'to' => date('Y-m-d', $end),
        'days' => max(1, (int)round(($end - $start) / 86400)),
        'elapsed' => max(0.0, ($now - $start) / 86400), 'left' => max(0.0, ($end - $now) / 86400),
    ];
}

/** Verbrauch und Prognose eines Dienstes im laufenden Zeitraum */
function smProvForecast(string $id): array
{
    $c = smProvCycle($id);
    $used = smUsageSum($id, $c['from']);
    $limit = (int)smProvGet($id, 'limit');
    $today = smUsageSum($id, date('Y-m-d'));
    $avg = $used / max(1.0, $c['elapsed']);                 // Durchschnitt pro Tag (mindestens über einen Tag gerechnet)
    $projected = $used + $avg * $c['left'];
    $fits = $limit <= 0 || $projected <= $limit;
    $empty = null;
    if ($limit > 0 && !$fits && $avg > 0) $empty = time() + (int)(max(0, $limit - $used) / $avg * 86400);
    $left = $limit > 0 ? max(0.0, $limit - $used) : INF;
    $apiLeft = smProvApiLeft($id);
    if ($apiLeft !== null) $left = min($left, $apiLeft);
    return [
        'used' => $used, 'limit' => $limit, 'left' => $left, 'api_left' => $apiLeft, 'today' => $today,
        'avg' => $avg, 'projected' => $projected, 'fits' => $fits, 'empty_at' => $empty,
        'pct' => $limit > 0 ? min(1.0, $used / $limit) : 0.0, 'cycle' => $c,
        'per_day' => is_finite($left) && $c['left'] > 0 ? $left / max(1.0, ceil($c['left'])) : INF,
    ];
}

/* ---------- Kontingente pro Konto ---------- */

/** Kontingent eines Kontos: ['allow' => [Dienste] oder null = alle, 'lim' => [Dienst => Credits]]. Gäste suchen nie bei Brave. */
function smQuotaOf(int $userId): array
{
    if ($userId <= 0) return ['allow' => [], 'lim' => []];
    return smCleanQuota(json_decode((string)smQ('SELECT quota FROM users WHERE id = ?', [$userId])->fetchColumn(), true));
}

function smCleanQuota($q): array
{
    $q = is_array($q) ? $q : [];
    $ids = array_keys(smProviders());
    $out = ['allow' => null, 'lim' => []];
    if (isset($q['allow']) && is_array($q['allow'])) $out['allow'] = array_values(array_intersect($ids, $q['allow']));
    foreach ((array)($q['lim'] ?? []) as $k => $v) {
        if (in_array($k, $ids, true) && is_numeric($v) && (int)$v >= 0) $out['lim'][$k] = (int)$v;
    }
    return $out;
}

function smQuotaSave(int $userId, array $q): void
{
    if ($userId > 0) smQ('UPDATE users SET quota = ? WHERE id = ?', [json_encode(smCleanQuota($q)), $userId]);
}

/** Darf das Konto diesen Dienst nutzen? Liefert '' oder den Grund (denied, quota). */
function smQuotaCheck(string $id, int $userId, float $cost = 0.0): string
{
    $q = smQuotaOf($userId);
    if ($q['allow'] !== null && !in_array($id, $q['allow'], true)) return 'denied';
    if (isset($q['lim'][$id])) {
        $used = smUsageSum($id, smProvCycle($id)['from'], $userId);
        if ($used + max($cost, 0.001) > $q['lim'][$id]) return 'quota';
    }
    return '';
}

/* ---------- Zustand: letzter Erfolg, letzter Fehler, Pause bei Störungen ---------- */

function smProvStat(string $id): array
{
    $r = smQ('SELECT * FROM pstat WHERE provider = ?', [$id])->fetch();
    return $r ?: ['provider' => $id, 'ok_at' => 0, 'err_at' => 0, 'err' => '', 'pause_until' => 0, 'ms_avg' => 0, 'ms_n' => 0, 'fails' => 0];
}

function smProvOk(string $id, int $ms): void
{
    smQ('INSERT INTO pstat (provider, ok_at, ms_avg, ms_n) VALUES (?, ?, ?, 1)
         ON CONFLICT(provider) DO UPDATE SET ok_at = excluded.ok_at, fails = 0, pause_until = 0,
         ms_avg = (ms_avg * MIN(ms_n, 49) + excluded.ms_avg) / (MIN(ms_n, 49) + 1), ms_n = ms_n + 1', [$id, time(), $ms]);
}

/** Fehler merken. Ab dem ersten Fehler pausiert der Dienst eine Weile, damit nicht jede Suche auf ihn wartet. */
function smProvFail(string $id, string $msg, int $pause): void
{
    smQ('INSERT INTO pstat (provider, err_at, err, pause_until, fails) VALUES (?, ?, ?, ?, 1)
         ON CONFLICT(provider) DO UPDATE SET err_at = excluded.err_at, err = excluded.err, pause_until = excluded.pause_until, fails = fails + 1',
        [$id, time(), mb_substr($msg, 0, 300), $pause > 0 ? time() + $pause : 0]);
}

function smProvUnpause(string $id): void
{
    smQ('UPDATE pstat SET pause_until = 0 WHERE provider = ?', [$id]);
}

/**
 * Kann dieser Dienst für dieses Konto und diesen Reiter jetzt suchen?
 * Liefert '' oder den Grund: kind, off, paused, limit, denied, quota
 */
function smProvBlock(string $id, string $kind, int $userId): string
{
    $p = smProviders()[$id] ?? null;
    if (!$p || !in_array($kind, $p['kinds'], true)) return 'kind';
    if (!smProvActive($id)) return 'off';
    if ((int)smProvStat($id)['pause_until'] > time()) return 'paused';
    $limit = (int)smProvGet($id, 'limit');
    $cost = (float)smProvGet($id, 'cost');
    if ($limit > 0 && smUsageSum($id, smProvCycle($id)['from']) + $cost > $limit) return 'limit';
    if (($al = smProvApiLeft($id)) !== null && $al < $cost) return 'limit';
    return smQuotaCheck($id, $userId, $cost);
}

/** Verständlicher Grund für die Oberfläche */
function smBlockText(string $id, string $why): string
{
    switch ($why) {
        case 'limit':  return t('Limit für diesen Zeitraum erreicht (neu ab %s)', smDate(smProvCycle($id)['end']));
        case 'quota':  return t('dein Kontingent ist aufgebraucht');
        case 'denied': return t('für dein Konto nicht freigegeben');
        case 'paused': return t('gestört, Pause bis %s Uhr', date('H:i', (int)smProvStat($id)['pause_until']));
        case 'off':    return t('nicht eingerichtet');
        case 'kind':   return t('kann diese Art der Suche nicht');
    }
    return $why;
}

/* ---------- Die eigentliche Suche ---------- */

/** Schlüssel für den Zwischenspeicher: alles, was die Anfrage an Brave beeinflusst (Filter danach gehören nicht dazu) */
function smSearchParams(string $kind, string $q, array $o): array
{
    return [
        'kind' => $kind, 'q' => $q, 'page' => $kind === 'images' ? 1 : max(1, min(10, (int)($o['page'] ?? 1))),
        'country' => (string)($o['country'] ?? 'ALL'), 'lang' => (string)($o['search_lang'] ?? 'en'),
        'safe' => (string)($o['safesearch'] ?? 'moderate'), 'fresh' => isset(smFreshness()[$o['freshness'] ?? '']) ? (string)($o['freshness'] ?? '') : '',
        'exact' => !empty($o['exact']), 'count' => !empty($o['endless']) ? 20 : max(5, min(20, (int)smSetting('per_page'))),
        'ui' => smLang(),
        // Eigener Zeitraum (Y-m-d), Wortgenau, ausdrücklich gewählte Sprache
        'from' => (string)($o['from'] ?? ''), 'to' => (string)($o['to'] ?? ''), 'verb' => !empty($o['verb']), 'lr' => (string)($o['lr'] ?? ''),
    ];
}

/**
 * Sucht bei Brave (oder im Zwischenspeicher).
 * $o: country, search_lang, safesearch, page, freshness, exact, endless, from, to, verb, lr
 * Kann Brave gerade nicht (kein Key, Limit, Kontingent, Störung), kommt SmSearchExhausted: dann geht es zur Ausweich-Suchmaschine.
 */
function smSearch(string $kind, string $q, array $o, int $userId = 0): array
{
    $params = smSearchParams($kind, $q, $o);
    $ck = 'brave|' . json_encode($params);
    if ($hit = smCacheGet($ck)) {
        $hit['cached'] = true;
        return $hit;
    }
    $why = smProvBlock('brave', $kind, $userId);
    if ($why !== '') throw new SmSearchExhausted(smExhaustedText($why));
    try {
        $out = smProvRun('brave', $params, $userId);
    } catch (SmSearchError $e) {
        throw new SmSearchExhausted($e->getMessage());
    }
    $out['source'] = 'brave';
    $ttl = smProvTtl('brave');
    // News und Treffer der letzten Stunde/24 Stunden veralten schnell
    if ($kind === 'news' || in_array($params['fresh'], ['ph', 'pd'], true)) $ttl = min($ttl, 1800);
    smCacheSet($ck, $out, $ttl);
    $out['cached'] = false;
    return $out;
}

/** Warum Brave gerade nicht sucht, als Satz */
function smExhaustedText(string $why): string
{
    if ($why === 'off') return t('Es ist noch kein Suchdienst eingerichtet. Ein Administrator kann unter Verwaltung → Suchdienste einen API-Key hinterlegen.');
    return t('Brave Search: %s.', smBlockText('brave', $why));
}

/** Bucht Credits vorab, falls Limit und Kontingent es erlauben (gleichzeitige Suchen zählen sauber mit) */
function smProvReserve(string $id, string $kind, int $userId, float $cost): bool
{
    $db = smDb();
    $db->exec('BEGIN IMMEDIATE');
    try {
        $limit = (int)smProvGet($id, 'limit');
        $al = smProvApiLeft($id);
        if (($limit > 0 && smUsageSum($id, smProvCycle($id)['from']) + $cost > $limit) || ($al !== null && $al < $cost) || smQuotaCheck($id, $userId, $cost) !== '') {
            $db->exec('ROLLBACK');
            return false;
        }
        smUsageAdd($id, $kind, $userId, $cost);
        $db->exec('COMMIT');
        return true;
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
}

/** Eine Suche bei einem Dienst: buchen, fragen, Zustand merken, Treffer vereinheitlichen. Fehler kommen als SmSearchError. */
function smProvRun(string $id, array $params, int $userId): array
{
    $cost = (float)smProvGet($id, 'cost');
    if (!smProvReserve($id, $params['kind'], $userId, $cost)) throw new SmSearchError(smBlockText($id, smProvBlock($id, $params['kind'], $userId) ?: 'limit'));
    $r = smHttpMulti(['x' => smProvRequest($id, $params, trim(smProvGet($id, 'key')))], 15)['x'];
    $out = smProvHandle($id, $r, $params, $userId, $cost);
    if (isset($out['error'])) throw new SmSearchError($out['error']);
    return $out;
}

/** Antwort eines Dienstes auswerten: Fehler gutschreiben und merken, sonst Treffer vereinheitlichen */
function smProvHandle(string $id, array $r, array $params, int $userId, float $cost): array
{
    $data = json_decode((string)$r['body'], true);
    if ($r['status'] !== 200 || !is_array($data)) {
        smUsageAdd($id, $params['kind'], $userId, -$cost, -1);
        [$msg, $pause] = smProvErrorText($id, $r, is_array($data) ? $data : null);
        smProvFail($id, $msg, $pause);
        error_log('Suchmaschine: ' . $id . ' HTTP ' . $r['status'] . ' ' . $r['error'] . ' ' . substr((string)$r['body'], 0, 300));
        return ['error' => $msg];
    }
    smProvOk($id, $r['ms']);
    if ($id === 'brave') smProvBraveHeaders($r['headers']);
    return smProvNormalize($id, $params, $data);
}

/* ---------- HTTP: Anfragen (auch mehrere gleichzeitig) ---------- */

/** Ein curl-Griff für eine Anfrage. Kopfzeilen zum Kontingent und zu den Kosten (Brave: X-RateLimit-…, X-Request-…) landen in $hdr. */
function smCurlNew(array $r, int $timeout, array &$hdr)
{
    $ch = curl_init($r['url']);
    $opt = [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => '', CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $r['headers'], CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        CURLOPT_USERAGENT => 'Suchmaschine/' . SM_VERSION . ' (+https://github.com/' . SM_REPO . ')',
    ];
    if (($r['method'] ?? 'GET') === 'POST') {
        $opt[CURLOPT_POST] = true;
        $opt[CURLOPT_POSTFIELDS] = $r['body'];
    }
    $hdr = [];
    $opt[CURLOPT_HEADERFUNCTION] = function ($c, $line) use (&$hdr) {
        $p = strpos($line, ':');
        if ($p !== false) {
            $k = strtolower(trim(substr($line, 0, $p)));
            if (strpos($k, 'x-ratelimit-') === 0 || strpos($k, 'x-request-') === 0 || $k === 'retry-after') $hdr[$k] = trim(substr($line, $p + 1));
        }
        return strlen($line);
    };
    curl_setopt_array($ch, $opt);
    return $ch;
}

/** Ergebnis eines fertigen curl-Griffs */
function smCurlResult($ch, float $t0, array $hdr): array
{
    return [
        'status' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'body' => (string)curl_multi_getcontent($ch),
        'errno' => curl_errno($ch), 'error' => curl_error($ch), 'headers' => $hdr,
        'ms' => (int)round((float)curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000) ?: (int)round((microtime(true) - $t0) * 1000),
    ];
}

/** $reqs: id => ['url', 'method', 'headers', 'body']. Liefert id => ['status', 'body', 'errno', 'error', 'ms']. */
function smHttpMulti(array $reqs, int $timeout): array
{
    if (!$reqs) return [];
    $mh = curl_multi_init();
    $hs = [];
    $hdr = [];
    foreach ($reqs as $id => $r) {
        $hdr[$id] = [];
        $ch = smCurlNew($r, $timeout, $hdr[$id]);
        curl_multi_add_handle($mh, $ch);
        $hs[$id] = [$ch, microtime(true)];
    }
    do {
        $st = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 0.5);
    } while ($running && $st === CURLM_OK);
    $out = [];
    foreach ($hs as $id => [$ch, $t0]) {
        $out[$id] = smCurlResult($ch, $t0, $hdr[$id]);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

/** Basisadresse eines Dienstes. Nur zum Testen per Umgebungsvariable umlenkbar (SM_API_BASE). */
function smProvBase(string $id, string $default): string
{
    $env = getenv('SM_API_BASE_' . strtoupper($id));
    if ($id === 'brave' && !$env) $env = getenv('SM_API_BASE');
    return $env ?: $default;
}

/** Baut die Anfrage für einen Dienst */
function smProvRequest(string $id, array $p, string $key): array
{
    if ($id !== 'brave') throw new SmSearchError('unknown provider');
    $country = $p['country'] !== 'ALL' && $p['country'] !== '' ? $p['country'] : '';
    $lang = ($p['lr'] ?? '') !== '' && isset(smSearchLangs()[$p['lr']]) ? $p['lr'] : $p['lang'];
    $q = ['q' => $p['q'], 'country' => $country ?: 'ALL', 'search_lang' => $lang, 'ui_lang' => $p['ui'] === 'de' ? 'de-DE' : 'en-US', 'safesearch' => $p['safe']];
    switch ($p['kind']) {
        case 'images':
            $path = 'images/search';
            $q['count'] = 200;   // mehr gibt Brave nicht, Blättern kann die Bildersuche nicht
            $q['safesearch'] = $p['safe'] === 'off' ? 'off' : 'strict';   // Bilder kennen nur off/strict
            unset($q['ui_lang']);
            break;
        case 'news':
        case 'videos':
            $path = $p['kind'] . '/search';
            // News: 50 auf einmal, das ist meist alles vom Tag in einer einzigen Anfrage
            $q['count'] = $p['kind'] === 'news' ? 50 : 20;
            $q['offset'] = $p['page'] - 1;
            break;
        default:
            $path = 'web/search';
            $q['count'] = $p['count'];
            $q['offset'] = $p['page'] - 1;
            if ($p['exact'] || !empty($p['verb'])) $q['spellcheck'] = '0';
    }
    if ($p['kind'] !== 'images') {
        // Eigener Zeitraum: Brave versteht 2026-01-01to2026-03-31. Die letzte Stunde gibt es nicht, dann der letzte Tag (wird danach gefiltert).
        if (($p['from'] ?? '') !== '' || ($p['to'] ?? '') !== '') {
            $q['freshness'] = ($p['from'] !== '' ? $p['from'] : '1990-01-01') . 'to' . ($p['to'] !== '' ? $p['to'] : date('Y-m-d'));
        } elseif ($p['fresh'] !== '') {
            $q['freshness'] = $p['fresh'] === 'ph' ? 'pd' : $p['fresh'];
        }
    }
    return ['url' => smProvBase('brave', SM_API) . $path . '?' . http_build_query($q), 'method' => 'GET',
            'headers' => ['Accept: application/json', 'X-Subscription-Token: ' . $key]];
}

/** Verständliche Fehlermeldung und Pause in Sekunden */
function smProvErrorText(string $id, array $r, ?array $data): array
{
    $name = smProviders()[$id]['name'];
    $st = $r['status'];
    // Kurze Pausen: Brave ist meist schnell wieder da, und bis dahin übernimmt die Ausweich-Suchmaschine
    if ($st === 0) {
        return [$r['errno'] === CURLE_OPERATION_TIMEDOUT ? t('%s hat zu lange gebraucht.', $name) : t('%s ist gerade nicht erreichbar.', $name), 120];
    }
    $detail = '';
    if ($data) {
        foreach ([$data['error']['detail'] ?? null, $data['error']['message'] ?? null, $data['detail']['error'] ?? null, $data['detail'] ?? null, $data['error'] ?? null, $data['message'] ?? null] as $c) {
            if (is_string($c) && $c !== '') { $detail = $c; break; }
        }
    }
    if ($st === 401 || $st === 403 || $st === 407) { $msg = t('%s nimmt den API-Key nicht an.', $name); $pause = 3600; }
    elseif ($st === 402 || $st === 432 || $st === 433) { $msg = t('Das Guthaben bei %s ist aufgebraucht.', $name); $pause = 3600; }
    elseif ($st === 429) { $msg = t('%s bremst gerade (zu viele Anfragen oder Kontingent erschöpft).', $name); $pause = 300; }
    elseif ($st >= 500) { $msg = t('%s hat gerade technische Probleme.', $name); $pause = 120; }
    elseif ($st === 400 || $st === 422) { $msg = t('%s hat die Anfrage abgelehnt.', $name); $pause = 0; }
    elseif ($st === 200) { $msg = t('%s hat unverständlich geantwortet.', $name); $pause = 120; }
    else { $msg = t('Unerwartete Antwort von %s (HTTP %d).', $name, $st); $pause = 600; }
    if ($detail !== '') $msg .= ' (' . mb_substr(smPlain($detail), 0, 160) . ')';
    return [$msg, $pause];
}

/** Prüft einen Key mit einer echten Suche (kostet eine Suche). Liefert Fehlertext oder ''. */
function smProvTest(string $id, string $key): string
{
    $p = smSearchParams('web', 'test', ['page' => 1]);
    $p['count'] = 5;
    $r = smHttpMulti(['x' => smProvRequest($id, $p, trim($key))], 15)['x'];
    $data = json_decode($r['body'], true);
    if ($r['status'] === 200 && is_array($data)) {
        // Auch der Test kostet beim Dienst eine Suche: mitzählen
        smUsageAdd($id, 'test', 0, (float)smProvGet($id, 'cost'));
        if ($id === 'brave') smProvBraveHeaders($r['headers']);   // der getestete Key wird gleich gespeichert
        return '';
    }
    return smProvErrorText($id, $r, is_array($data) ? $data : null)[0];
}

/* ---------- Antworten vereinheitlichen ---------- */

function smProvNormalize(string $id, array $p, array $d): array
{
    switch ($p['kind']) {
        case 'images': return smNormImages($d);
        case 'news':   return smNormNews($d);
        case 'videos': return smNormVideos($d);
    }
    return smNormWeb($d);
}

/** Zeitstempel aus einem Datum (ISO oder YYYY-MM-DD), 0 = unbekannt */
function smParseTime($s): int
{
    if (!is_string($s) || trim($s) === '') return 0;
    $t = strtotime($s);
    return $t !== false && $t > 0 && $t < time() + 86400 * 2 ? $t : 0;
}

/* ---------- Warnhinweis für Administratoren ---------- */

/** Dienste, bei denen es knapp wird: [Text, …] */
function smProvWarnings(): array
{
    $pct = max(1, min(100, (int)smSetting('warn_pct'))) / 100;
    $w = [];
    foreach (smProvOrder() as $id) {
        $f = smProvForecast($id);
        if ($f['limit'] <= 0) continue;
        $name = smProviders()[$id]['name'];
        if ($f['used'] >= $f['limit'] - (float)smProvGet($id, 'cost')) {
            $w[] = t('%s: Limit erreicht, neu ab %s.', $name, smDate($f['cycle']['end']));
        } elseif ($f['pct'] >= $pct) {
            $w[] = t('%s: %d %% verbraucht, noch %s bis zum Reset am %s.', $name, (int)round($f['pct'] * 100), smNumF($f['left']), smDate($f['cycle']['end']));
        } elseif (!$f['fits'] && $f['empty_at']) {
            $w[] = t('%s: reicht beim jetzigen Tempo nur bis etwa %s (Reset am %s).', $name, smDate($f['empty_at']), smDate($f['cycle']['end']));
        }
    }
    return $w;
}

/** Datum kurz: 25.11.2026 / 2026-11-25 */
function smDate(int $ts): string
{
    return date(smLang() === 'de' ? 'd.m.Y' : 'Y-m-d', $ts);
}

/** Kommazahl hübsch */
function smNumF(float $n, int $dec = 0): string
{
    if (is_infinite($n)) return '∞';
    if ($dec === 0 && abs($n - round($n)) > 0.001 && $n < 100) $dec = 1;
    return number_format($n, $dec, smLang() === 'de' ? ',' : '.', smLang() === 'de' ? '.' : ',');
}

/* ---------- Ausweich-Suchmaschine: Gäste immer, Angemeldete, wenn Brave nicht kann ---------- */

/**
 * Zur Auswahl stehende Suchmaschinen: Adresse der Websuche mit {q} => [Name, Bilder, News, Videos].
 * Die Reiter-Adressen sorgen dafür, dass eine Bildersuche auch bei der anderen Suchmaschine in deren Bildersuche landet.
 */
function smFallbackEngines(): array
{
    return [
        'https://www.startpage.com/do/search?q={q}' => ['Startpage', 'https://www.startpage.com/do/search?cat=images&q={q}',
            'https://www.startpage.com/do/search?cat=news&q={q}', 'https://www.startpage.com/do/search?cat=video&q={q}'],
        'https://duckduckgo.com/?q={q}' => ['DuckDuckGo', 'https://duckduckgo.com/?q={q}&iax=images&ia=images',
            'https://duckduckgo.com/?q={q}&iar=news&ia=news', 'https://duckduckgo.com/?q={q}&iax=videos&ia=videos'],
        'https://search.brave.com/search?q={q}' => ['Brave Search', 'https://search.brave.com/images?q={q}',
            'https://search.brave.com/news?q={q}', 'https://search.brave.com/videos?q={q}'],
        'https://www.ecosia.org/search?q={q}' => ['Ecosia', 'https://www.ecosia.org/images?q={q}',
            'https://www.ecosia.org/news?q={q}', 'https://www.ecosia.org/videos?q={q}'],
        'https://www.qwant.com/?q={q}' => ['Qwant', 'https://www.qwant.com/?q={q}&t=images',
            'https://www.qwant.com/?q={q}&t=news', 'https://www.qwant.com/?q={q}&t=videos'],
        'https://www.mojeek.com/search?q={q}' => ['Mojeek', 'https://www.mojeek.com/search?q={q}&fmt=images',
            'https://www.mojeek.com/search?q={q}&fmt=news', ''],
        'https://www.google.com/search?q={q}' => ['Google', 'https://www.google.com/search?q={q}&udm=2',
            'https://www.google.com/search?q={q}&tbm=nws', 'https://www.google.com/search?q={q}&udm=7'],
        'https://www.bing.com/search?q={q}' => ['Bing', 'https://www.bing.com/images/search?q={q}',
            'https://www.bing.com/news/search?q={q}', 'https://www.bing.com/videos/search?q={q}'],
    ];
}

/** Zur Auswahl stehende Suchmaschinen: Adresse mit {q} => Name */
function smFallbackPresets(): array
{
    return array_map(function ($e) { return $e[0]; }, smFallbackEngines());
}

/** Taugt die Adresse als Ziel? https://, mit {q}. Gäste dürfen nur aus der Liste wählen. */
function smFallbackValid(string $u, bool $presetOnly = false): bool
{
    if (isset(smFallbackPresets()[$u])) return true;
    return !$presetOnly && strpos($u, '{q}') !== false && smCleanBangUrl($u) !== '' && strpos($u, 'https://') === 0;
}

/** Standard der Installation (Verwaltung), sonst Startpage */
function smFallbackDefault(): string
{
    $u = smSetting('fallback_url');
    return smFallbackValid($u) ? $u : array_key_first(smFallbackPresets());
}

/**
 * Welche Suchmaschine gilt für diesen Besucher: eigene Wahl des Kontos, bei Gästen die Wahl im Browser (Cookie smfb),
 * sonst der Standard der Installation. Liefert die Adresse mit {q}.
 */
function smFallbackFor(?array $me): string
{
    $own = $me ? (string)($me['prefs']['fallback'] ?? '') : (string)($_COOKIE['smfb'] ?? '');
    return $own !== '' && smFallbackValid($own, !$me) ? $own : smFallbackDefault();
}

/** Fertige Adresse für eine Suche */
function smFallbackUrl(string $q, ?array $me = null, ?string $tpl = null): string
{
    $u = smCleanBangUrl($tpl ?? smFallbackFor($me));
    if ($u === '' || $q === '') return '';
    return str_replace('{q}', rawurlencode($q), $u);
}

/** Adresse passend zum Reiter (Bilder, News, Videos), soweit die Suchmaschine das kennt, sonst die Websuche */
function smFallbackTabUrl(string $q, string $tab, ?array $me): string
{
    $tpl = smFallbackFor($me);
    $i = ['images' => 1, 'news' => 2, 'videos' => 3][$tab] ?? 0;
    if ($i && ($alt = smFallbackEngines()[$tpl][$i] ?? '') !== '') $tpl = $alt;
    return smFallbackUrl($q, $me, $tpl);
}

/** Name der Suchmaschine zu einer Adresse mit {q} */
function smFallbackName(?array $me = null, ?string $tpl = null): string
{
    $u = $tpl ?? smFallbackFor($me);
    return smFallbackPresets()[$u] ?? smHost((string)parse_url($u, PHP_URL_HOST));
}

/**
 * Lesezeichen „Bilder groß“ (app/assets/bookmarklet.js) als javascript:-Adresse. Für Bildersuchen anderer Suchmaschinen:
 * sammelt dort die Originalbilder und zeigt sie randlos im Vollbild. Kommentare fallen weg, sonst bleibt der Code lesbar.
 */
function smBookmarklet(): string
{
    $js = (string)file_get_contents(SM_APP . '/assets/bookmarklet.js');
    $js = preg_replace('~/\*.*?\*/~s', '', $js);
    $js = preg_replace('~^\s+|\s+$~m', '', $js);
    $js = preg_replace('~\n+~', "\n", trim($js));
    $texts = ['none' => t('Auf dieser Seite habe ich keine großen Bilder gefunden.'), 'prev' => t('Vorheriges Bild'), 'next' => t('Nächstes Bild'),
              'close' => t('Schließen'), 'full' => t('Vollbild (Taste F)'), 'open' => t('Bild in Originalgröße'),
              'broken' => t('Dieses Bild lässt die Seite nicht laden. Weiter mit dem Pfeil.')];
    $js = str_replace('__SM_TEXTS__', json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $js);
    return 'javascript:' . rawurlencode($js);
}

