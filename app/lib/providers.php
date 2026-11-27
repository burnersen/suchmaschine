<?php
/* Suchmaschine – zwei Suchdienste: Google über Bright Data und Brave.
   Alle liefern dasselbe Trefferformat. Pro Dienst: eigener Key, Stichtag, Monatslimit, Zähler, Prognose,
   Zwischenspeicher und Zustand (gestörte Dienste pausieren kurz). Dazu Lastverteilung, Ausweichen bei Fehlern,
   gezieltes Ansteuern (!tv, !exa …), Multi-Quellen-Modus und Kontingente pro Konto. */
defined('SM_VERSION') || exit;

/** Wenn wirklich nichts mehr geht: alle Dienste leer, gestört oder nicht erlaubt */
class SmSearchExhausted extends SmSearchError
{
}

/**
 * Die bekannten Suchdienste.
 * free = Freikontingent pro Monat, limit = Standardlimit (50 Credits Puffer), cost = geschätzte Credits pro Suche,
 * unit = worin gezählt wird, kinds = welche Reiter der Dienst kann.
 */
function smProviders(): array
{
    return [
        'brightdata' => [
            'name' => 'Google (Bright Data)', 'badge' => 'Google', 'free' => 5000, 'limit' => 4950, 'cost' => 1, 'unit' => t('Anfragen'),
            'kinds' => ['web', 'images', 'news', 'videos'], 'bangs' => ['bd', 'brightdata'], 'paging' => true, 'google' => true,
            'site' => 'https://brightdata.com/cp/zones', 'key_hint' => '1a2b3c4d-…',
        ],
        'brave' => [
            'name' => 'Brave Search', 'badge' => 'Brave', 'free' => 1000, 'limit' => 950, 'cost' => 1, 'unit' => t('Suchen'),
            'kinds' => ['web', 'images', 'news', 'videos'], 'bangs' => ['brave'], 'paging' => true, 'google' => false,
            'site' => 'https://api-dashboard.search.brave.com', 'key_hint' => 'BSA…',
        ],
    ];
}

/** Bangs, die gezielt einen Suchdienst ansteuern: Kürzel => Dienst (oder "multi") */
function smProviderBangs(): array
{
    $b = ['multi' => 'multi', 'alle' => 'multi', 'all' => 'multi'];
    foreach (smProviders() as $id => $p) foreach ($p['bangs'] as $k) $b[$k] = $id;
    return $b;
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
        foreach (['key', 'start', 'limit', 'cost', 'cache', 'on', 'zone', 'unlocker'] as $f) {
            if ($id !== 'brightdata' && in_array($f, ['zone', 'unlocker'], true)) continue;
            if ($id === 'brave' && in_array($f, ['key', 'limit', 'cache'], true)) continue;
            $k[] = 'p_' . $id . '_' . $f;
        }
    }
    return $k;
}

/** Hat der Dienst einen Key und ist eingeschaltet? */
function smProvActive(string $id): bool
{
    return isset(smProviders()[$id]) && trim(smProvGet($id, 'key')) !== '' && smProvGet($id, 'on') !== '0'
        && ($id !== 'brightdata' || smBdZone() !== '');
}

/** Dienste mit Key in der eingestellten Reihenfolge */
function smProvOrder(bool $activeOnly = true): array
{
    $all = array_keys(smProviders());
    $order = array_values(array_unique(array_merge(array_intersect(explode(',', smSetting('prov_order')), $all), $all)));
    return $activeOnly ? array_values(array_filter($order, 'smProvActive')) : $order;
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
    // Tempo: 1,0 = genau im Plan, darüber wird schneller verbraucht, als das Kontingent erlaubt
    $share = max($c['elapsed'] / $c['days'], min(1.0, 3 / $c['days']));   // am Anfang: mindestens 3 Tage Budget, sonst greift die Lastverteilung schon nach ein paar Suchen
    $pace = $limit > 0 ? $used / ($limit * $share) : 0.0;
    $left = $limit > 0 ? max(0.0, $limit - $used) : INF;
    $apiLeft = smProvApiLeft($id);
    if ($apiLeft !== null) $left = min($left, $apiLeft);
    return [
        'used' => $used, 'limit' => $limit, 'left' => $left, 'api_left' => $apiLeft, 'today' => $today,
        'avg' => $avg, 'projected' => $projected, 'fits' => $fits, 'empty_at' => $empty, 'pace' => $pace,
        'pct' => $limit > 0 ? min(1.0, $used / $limit) : 0.0, 'cycle' => $c,
        'per_day' => is_finite($left) && $c['left'] > 0 ? $left / max(1.0, ceil($c['left'])) : INF,
    ];
}

/* ---------- Kontingente pro Konto ---------- */

/** Kontingent eines Kontos (0 = Besucher ohne Anmeldung): ['allow' => [Dienste] oder null = alle, 'lim' => [Dienst => Credits]] */
function smQuotaOf(int $userId): array
{
    if ($userId === 0) {
        $raw = smSetting('anon_quota');
    } else {
        $raw = (string)smQ('SELECT quota FROM users WHERE id = ?', [$userId])->fetchColumn();
    }
    return smCleanQuota(json_decode($raw, true));
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
    $json = json_encode(smCleanQuota($q));
    if ($userId === 0) smSetSetting('anon_quota', $json); else smQ('UPDATE users SET quota = ? WHERE id = ?', [$json, $userId]);
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
        case 'pace':   return t('wird geschont (Lastverteilung)');
        case 'off':    return t('nicht eingerichtet');
        case 'kind':   return t('kann diese Art der Suche nicht');
        case 'filter': return t('kann diesen Filter nicht');
        case 'paging': return t('kennt keine weiteren Seiten');
    }
    return $why;
}

/**
 * Wählt die Dienste für eine Suche, in der Reihenfolge, in der sie probiert werden.
 * Liefert [Liste, übersprungene Dienste mit Grund]. Mit Lastverteilung rücken Dienste, die schneller verbrauchen,
 * als ihr Kontingent bis zum Reset erlaubt, nach hinten.
 */
function smProvPick(string $kind, int $userId, ?string $forced, int $page, int $older = 0, bool $google = false): array
{
    $list = [];
    $skip = [];
    $ids = $forced !== null ? [$forced] : smProvOrder(false);
    // Ältere News (über eine Woche): Brave hat dort fast nichts mehr im Index. Google kann einen genauen Zeitraum.
    if ($forced === null && $older >= 7) {
        $range = array_values(array_intersect(['brightdata'], $ids));
        $ids = array_values(array_unique(array_merge($range, $ids)));
    }
    foreach ($ids as $id) {
        $why = smProvBlock($id, $kind, $userId);
        if ($why === '' && $page > 1 && !smProviders()[$id]['paging']) $why = 'paging';
        // Filter, die nur Google kann (Bildfarbe, Bildtyp, Lizenz): andere Dienste würden sie stillschweigend übergehen
        if ($why === '' && $google && empty(smProviders()[$id]['google'])) $why = 'filter';
        if ($why === '') { $list[] = $id; continue; }
        if (!in_array($why, ['off', 'kind'], true) || $forced !== null) $skip[$id] = $why;
    }
    if ($forced === null && count($list) > 1 && smSetting('balance') === '1' && $older < 7) {
        $pace = [];
        foreach ($list as $i => $id) $pace[$id] = [smProvForecast($id)['pace'] > 1.15 ? 1 : 0, $i];   // etwas Spielraum, die eigene Reihenfolge soll gelten
        $first = $list[0];
        usort($list, function ($a, $b) use ($pace) {
            if ($pace[$a][0] !== $pace[$b][0]) return $pace[$a][0] <=> $pace[$b][0];
            return $pace[$a][1] <=> $pace[$b][1];
        });
        if ($list[0] !== $first) $skip[$first] = 'pace';
    }
    return [$list, $skip];
}

/* ---------- Die eigentliche Suche ---------- */

/** Schlüssel für den Zwischenspeicher: alles, was das Ergebnis beeinflusst */
function smSearchParams(string $kind, string $q, array $o): array
{
    $p = [
        'kind' => $kind, 'q' => $q, 'page' => $kind === 'images' ? 1 : max(1, min(10, (int)($o['page'] ?? 1))),
        'country' => (string)($o['country'] ?? 'ALL'), 'lang' => (string)($o['search_lang'] ?? 'en'),
        'safe' => (string)($o['safesearch'] ?? 'moderate'), 'fresh' => isset(smFreshness()[$o['freshness'] ?? '']) ? (string)($o['freshness'] ?? '') : '',
        'exact' => !empty($o['exact']), 'count' => !empty($o['endless']) ? 20 : max(5, min(20, (int)smSetting('per_page'))),
        'answer' => $kind === 'web' && smSetting('answers') === '1' && max(1, (int)($o['page'] ?? 1)) === 1,
        'ui' => smLang(),
        // Zeitstufen bei „Neueste zuerst“: nur Treffer, die mindestens so viele Tage alt sind (genauer Zeitraum bei Google)
        'older' => $kind !== 'images' ? max(0, min(365, (int)($o['older'] ?? 0))) : 0,
        // Eigener Zeitraum (Y-m-d), Wortgenau, ausdrücklich gewählte Sprache
        'from' => (string)($o['from'] ?? ''), 'to' => (string)($o['to'] ?? ''), 'verb' => !empty($o['verb']), 'lr' => (string)($o['lr'] ?? ''),
        // Bildfilter (bei Google direkt in der Suche) und Videolänge
        'img' => $kind === 'images' ? array_filter(['io' => (string)($o['io'] ?? ''), 'is' => (string)($o['is'] ?? ''), 'ic' => (string)($o['ic'] ?? ''),
                 'it' => (string)($o['it'] ?? ''), 'il' => (string)($o['il'] ?? ''), 'iq' => (string)($o['iq'] ?? '')]) : [],
        'vdur' => $kind === 'videos' ? (string)($o['vdur'] ?? '') : '',
    ];
    // Seite 1 bei „Alle“ ohne Zeitfilter: diese Suche liefert auch die Karten (Spiele, Kurse, Infobox …), dafür mit Googles Quelltext
    $p['html'] = $kind === 'web' && $p['page'] === 1 && $p['fresh'] === '' && $p['older'] === 0 && $p['from'] === '' && $p['to'] === '';
    return $p;
}

/** Sind Filter gesetzt, die nur Google kann? */
function smNeedsGoogle(array $params): bool
{
    return !empty($params['img']['ic']) || !empty($params['img']['it']) || !empty($params['img']['il']);
}

/**
 * Sucht bei einem passenden Dienst (oder im Zwischenspeicher).
 * $o: country, search_lang, safesearch, page, freshness, exact, endless, src ('' = automatisch, Dienst-ID oder 'multi')
 * Das Ergebnis enthält zusätzlich: source (Dienst), sources (bei Multi), notice (warum ausgewichen wurde), answer.
 */
function smSearch(string $kind, string $q, array $o, int $userId = 0): array
{
    $src = (string)($o['src'] ?? '');
    $params = smSearchParams($kind, $q, $o);
    if ($src === 'multi') return smSearchMulti($kind, $q, $params, $userId);
    $forced = isset(smProviders()[$src]) ? $src : null;
    $ck = json_encode($params);
    // „Automatisch“ merkt sich auch, welche Reihenfolge galt: nach dem Umsortieren fragt die nächste Suche gleich den neuen ersten Dienst
    $auto = 'auto' . substr(md5(smSetting('prov_order') . '|' . smSetting('balance')), 0, 8);

    // Automatisch: zuerst der Zwischenspeicher, egal welcher Dienst geliefert hat
    if ($hit = smCacheGet(($forced ?? $auto) . '|' . $ck)) {
        $hit['cached'] = true;
        return $hit;
    }

    [$list, $skip] = smProvPick($kind, $userId, $forced, $params['page'], $params['older'], smNeedsGoogle($params));
    $errors = [];
    // Wettlauf: Antwortet der erste Dienst nicht rasch, fragt die Suchmaschine den zweiten dazu (Bilder dürfen doppelt so lange)
    $race = isset($o['race']) && $o['race'] !== '' && $o['race'] !== null ? (int)$o['race'] : (int)smSetting('race');
    $first = null;
    if ($forced === null && $race > 0 && count($list) >= 2) {
        [$wid, $wout, $errors, $bStarted] = smProvRace($list[0], $list[1], $params, $userId, $kind === 'images' ? $race * 2 : $race);
        $list = array_slice($list, $bStarted ? 2 : 1);   // wurde der zweite gar nicht gefragt, kommt er jetzt normal dran
        if ($wid !== null) $first = [$wid, $wout];
    }
    foreach (array_merge($first ? [$first[0]] : [], $list) as $id) {
        if ($first && $id === $first[0]) {
            $out = $first[1];
        } else {
            try {
                $out = smProvRun($id, $params, $userId);
            } catch (SmSearchError $e) {
                $errors[$id] = $e->getMessage();
                continue;
            }
        }
        $out['source'] = $id;
        $out['sources'] = [$id];
        $out['notice'] = smNoticeText($id, $skip, $errors);
        $ttl = smProvTtl($id);
        // News und Treffer der letzten Stunde/24 Stunden veralten schnell: höchstens 1 Stunde
        if ($kind === 'news' || in_array($params['fresh'], ['ph', 'pd'], true)) $ttl = min($ttl, 3600);
        // Spielstände und Kurse ändern sich laufend: höchstens 5 Minuten speichern
        if (!empty($out['sports']) || !empty($out['stock'])) $ttl = min($ttl, 300);
        smCacheSet($id . '|' . $ck, $out, $ttl);
        if ($forced === null) smCacheSet($auto . '|' . $ck, $out, min($ttl, 3600));
        $out['cached'] = false;
        return $out;
    }
    throw new SmSearchExhausted(smExhaustedText($kind, $skip, $errors, $forced));
}

/** Liegt diese Suche schon im Zwischenspeicher? (Dann braucht es keine Warteseite.) */
function smSearchPeek(string $kind, string $q, array $o): bool
{
    $src = (string)($o['src'] ?? '');
    $ck = json_encode(smSearchParams($kind, $q, $o));
    if ($src === 'multi') return smCacheGet('multi|' . $ck) !== null;
    $forced = isset(smProviders()[$src]) ? $src : null;
    $auto = 'auto' . substr(md5(smSetting('prov_order') . '|' . smSetting('balance')), 0, 8);
    return smCacheGet(($forced ?? $auto) . '|' . $ck) !== null;
}

/** „Ergebnisse von Brave – Google: Limit erreicht“ */
function smNoticeText(string $used, array $skip, array $errors): array
{
    $why = [];
    foreach ($skip as $id => $w) if ($w !== 'denied') $why[] = smProviders()[$id]['badge'] . ': ' . smBlockText($id, $w);
    foreach ($errors as $id => $e) $why[] = smProviders()[$id]['badge'] . ': ' . $e;
    return $why;
}

function smExhaustedText(string $kind, array $skip, array $errors, ?string $forced): string
{
    if ($forced !== null && ($skip[$forced] ?? '') === 'denied') return t('%s ist für dein Konto nicht freigegeben.', smProviders()[$forced]['name']);
    $parts = smNoticeText('', $skip, $errors);
    if (!$parts) {
        if ($forced !== null) return t('%s kann diese Art der Suche nicht oder ist nicht eingerichtet.', smProviders()[$forced]['name']);
        return t('Es ist noch kein Suchdienst eingerichtet. Ein Administrator kann unter Verwaltung → Suchdienste einen API-Key hinterlegen.');
    }
    return t('Gerade ist kein Suchdienst verfügbar.') . ' ' . implode(' · ', $parts);
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

/** Eine Suche bei einem Dienst: buchen, fragen, Zustand merken, Treffer vereinheitlichen */
function smProvRun(string $id, array $params, int $userId): array
{
    $res = smProvRunMany([$id], $params, $userId);
    if (isset($res[$id]['error'])) throw new SmSearchError($res[$id]['error']);
    return $res[$id];
}

/**
 * Fragt mehrere Dienste gleichzeitig (curl_multi). Liefert Dienst => Ergebnis oder ['error' => Text].
 * Fehlgeschlagene Anfragen werden wieder gutgeschrieben, der Dienst pausiert kurz.
 */
function smProvRunMany(array $ids, array $params, int $userId): array
{
    $out = [];
    $reqs = [];
    foreach ($ids as $id) {
        $cost = (float)smProvGet($id, 'cost');
        if (!smProvReserve($id, $params['kind'], $userId, $cost)) {
            $out[$id] = ['error' => smBlockText($id, smProvBlock($id, $params['kind'], $userId) ?: 'limit')];
            continue;
        }
        $reqs[$id] = smProvRequest($id, $params, trim(smProvGet($id, 'key')));
        $reqs[$id]['cost'] = $cost;
    }
    // Google über Bright Data braucht meist 2 bis 5 Sekunden, Bildersuchen manchmal deutlich länger
    $res = smHttpMulti($reqs, isset($reqs['brightdata']) ? 30 : 15);
    // Bright Data antwortet ab und zu mit 502/504 oder leer: einmal neu versuchen, wenn noch Zeit ist (kostet nur Erfolge)
    if (isset($res['brightdata']) && smBdRetry($res['brightdata'])) {
        $res['brightdata'] = smHttpMulti(['brightdata' => smProvRequest('brightdata', ['retry' => true] + $params, trim(smProvGet('brightdata', 'key'))) + ['cost' => $reqs['brightdata']['cost']]], 20)['brightdata'];
    }
    foreach ($res as $id => $r) $out[$id] = smProvHandle($id, $r, $params, $userId, $reqs[$id]['cost']);
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

/**
 * Wettlauf zwischen den ersten beiden Diensten: $a fängt an, braucht er länger als $delay Sekunden, fragt die Suchmaschine $b dazu.
 * Die schnellere gute Antwort gewinnt. Liefert [Dienst, Ergebnis, Fehler je Dienst]. Der abgebrochene Verlierer bleibt gezählt
 * (die Anfrage ist beim Dienst ja angekommen).
 */
function smProvRace(string $a, string $b, array $params, int $userId, float $delay): array
{
    $errors = [];
    $costA = (float)smProvGet($a, 'cost');
    if (!smProvReserve($a, $params['kind'], $userId, $costA)) {
        $errors[$a] = smBlockText($a, smProvBlock($a, $params['kind'], $userId) ?: 'limit');
        return [null, null, $errors, false];
    }
    $costB = (float)smProvGet($b, 'cost');
    $timeout = function (string $id) { return $id === 'brightdata' ? 30 : 15; };
    $results = [];
    $good = function (string $id, array $r) use (&$results, $params, $userId, $costA, $costB, $a) {
        $results[$id] = smProvHandle($id, $r, $params, $userId, $id === $a ? $costA : $costB);
        return !isset($results[$id]['error']);
    };
    $startB = function () use ($b, $params, $userId, $costB) {
        if (smProvBlock($b, $params['kind'], $userId) !== '' || !smProvReserve($b, $params['kind'], $userId, $costB)) return null;
        return smProvRequest($b, $params, trim(smProvGet($b, 'key')));
    };
    // Kurzer Aussetzer bei Bright Data (leer, 502 …) in den ersten Sekunden: noch ein Versuch, gleichzeitig mit dem zweiten Dienst
    $retryA = function (array $r) use ($a, $params, $userId, $costA) {
        if ($a !== 'brightdata' || !smBdRetry($r) || !smProvReserve($a, $params['kind'], $userId, $costA)) return null;
        return smProvRequest($a, ['retry' => true] + $params, trim(smProvGet($a, 'key')));
    };
    [$win, , $bStarted] = smHttpRace($a, smProvRequest($a, $params, trim(smProvGet($a, 'key'))), $timeout($a), $b, $startB, $timeout($b), $delay, $good, $retryA);
    foreach ($results as $id => $r) if (isset($r['error'])) $errors[$id] = $r['error'];
    return [$win, $win !== null ? $results[$win] : null, $errors, $bStarted];
}

/** Vorübergehender Fehler bei Bright Data, der einen zweiten Versuch lohnt? */
function smBdRetry(array $r): bool
{
    if ($r['ms'] > 12000) return false;
    return in_array($r['status'], [0, 502, 503, 504], true) && $r['errno'] !== CURLE_OPERATION_TIMEDOUT
        || ($r['status'] === 200 && (trim($r['body']) === '' || stripos(substr($r['body'], 0, 200), 'recently failed') !== false));
}

/** Mehrere Dienste gleichzeitig, Treffer abwechselnd gemischt, doppelte Adressen zusammengelegt (mit allen Quellen) */
function smSearchMulti(string $kind, string $q, array $params, int $userId): array
{
    $ck = 'multi|' . json_encode($params);
    if ($hit = smCacheGet($ck)) {
        $hit['cached'] = true;
        return $hit;
    }
    $set = array_intersect(smProvOrder(), explode(',', smSetting('multi_set')));
    if (!$set) $set = smProvOrder();
    $ids = [];
    $skip = [];
    foreach ($set as $id) {
        $why = smProvBlock($id, $kind, $userId);
        if ($why === '' && $params['page'] > 1 && !smProviders()[$id]['paging']) $why = 'paging';
        if ($why === '' && smNeedsGoogle($params) && empty(smProviders()[$id]['google'])) $why = 'filter';
        if ($why === '') $ids[] = $id; elseif (!in_array($why, ['off', 'kind'], true)) $skip[$id] = $why;
    }
    $res = smProvRunMany($ids, $params, $userId);
    $errors = [];
    $ok = [];
    foreach ($res as $id => $r) {
        if (isset($r['error'])) $errors[$id] = $r['error']; else $ok[$id] = $r;
    }
    if (!$ok) throw new SmSearchExhausted(smExhaustedText($kind, $skip, $errors, null));

    // Grundgerüst: Google liefert das meiste Beiwerk (Infobox, KI-Übersicht, Spielstände …), sonst Brave
    $base = $ok['brightdata'] ?? $ok['brave'] ?? reset($ok);
    $merged = [];
    $index = [];
    $lists = array_map(function ($r) { return $r['results']; }, $ok);
    for ($i = 0; $i < 30; $i++) {
        $any = false;
        foreach ($lists as $id => $l) {
            if (!isset($l[$i])) continue;
            $any = true;
            $r = $l[$i];
            $k = smUrlKey($r['url']);
            if (isset($index[$k])) {
                $m = &$merged[$index[$k]];
                $m['via'] = array_values(array_unique(array_merge($m['via'], $r['via'])));
                if ($m['desc'] === '' && $r['desc'] !== '') $m['desc'] = $r['desc'];
                if (empty($m['ts']) && !empty($r['ts'])) $m['ts'] = $r['ts'];
                unset($m);
                continue;
            }
            $index[$k] = count($merged);
            $merged[] = $r;
        }
        if (!$any) break;
    }
    $base['results'] = $merged;
    $order = [];
    foreach ($merged as $i => $_) {
        $order[] = ['web', $i];
        if ($i === 2) foreach (['news', 'videos', 'faq', 'discussions'] as $t) if (!empty($base[$t])) $order[] = [$t];
    }
    if (count($merged) <= 2) foreach (['news', 'videos', 'faq', 'discussions'] as $t) if (!empty($base[$t])) $order[] = [$t];
    $base['order'] = $order;
    $base['more'] = false;
    if (empty($base['answer'])) foreach ($ok as $r) if (!empty($r['answer'])) { $base['answer'] = $r['answer']; break; }
    $base['source'] = 'multi';
    $base['sources'] = array_keys($ok);
    $base['notice'] = smNoticeText('multi', $skip, $errors);
    $ttl = min(array_map('smProvTtl', array_keys($ok)));
    if (!empty($base['sports']) || !empty($base['stock'])) $ttl = min($ttl, 300);
    smCacheSet($ck, $base, min($ttl, 3600));
    $base['cached'] = false;
    return $base;
}

/** Adresse zum Vergleichen: ohne Protokoll, www., Anker und Schrägstrich am Ende */
function smUrlKey(string $url): string
{
    $p = parse_url($url);
    $host = smHost(strtolower((string)($p['host'] ?? '')));
    $path = rtrim((string)($p['path'] ?? ''), '/');
    return $host . $path . (isset($p['query']) ? '?' . $p['query'] : '');
}

/* ---------- HTTP: mehrere Anfragen gleichzeitig ---------- */

/** Ein curl-Griff für eine Anfrage. Kopfzeilen zum Kontingent (Brave: X-RateLimit-…) landen in $hdr. */
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
            if (strpos($k, 'x-ratelimit-') === 0 || $k === 'retry-after') $hdr[$k] = trim(substr($line, $p + 1));
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

/**
 * Wettlauf: Anfrage $a startet sofort. Ist sie nach $delay Sekunden noch nicht zurück, startet $b dazu ($startB() bucht vorher
 * und liefert die Anfrage oder null, wenn $b nicht darf). $good(id, Antwort) prüft eine fertige Antwort. Die erste gute gewinnt,
 * die andere wird abgebrochen. Liefert [Gewinner oder null, id => Antwort aller fertigen, ob $b gestartet wurde].
 */
function smHttpRace(string $a, array $reqA, int $timeoutA, string $b, callable $startB, int $timeoutB, float $delay, callable $good, ?callable $retryA = null): array
{
    $mh = curl_multi_init();
    $hdr = [$a => [], $b => []];
    $hs = [$a => [smCurlNew($reqA, $timeoutA, $hdr[$a]), microtime(true)]];
    curl_multi_add_handle($mh, $hs[$a][0]);
    $t0 = microtime(true);
    $done = [];
    $winner = null;
    $bStarted = false;
    $bTried = false;
    while (true) {
        $st = curl_multi_exec($mh, $running);
        while ($info = curl_multi_info_read($mh)) {
            foreach ($hs as $id => [$ch, $ts]) {
                if ($ch !== $info['handle']) continue;
                $done[$id] = smCurlResult($ch, $ts, $hdr[$id]);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                unset($hs[$id]);
                $base = $id === $a . '#2' ? $a : $id;
                if ($winner === null && $good($base, $done[$id])) { $winner = $base; continue; }
                // A kam schnell mit einem Aussetzer zurück: gleich noch einmal fragen (B startet parallel)
                if ($id === $a && $retryA !== null && ($reqR = $retryA($done[$id])) !== null) {
                    $hdr[$a . '#2'] = [];
                    $hs[$a . '#2'] = [smCurlNew($reqR, $timeoutA, $hdr[$a . '#2']), microtime(true)];
                    curl_multi_add_handle($mh, $hs[$a . '#2'][0]);
                }
            }
        }
        if ($winner !== null) break;
        // B dazunehmen: nach der Wartezeit, oder sofort, wenn A schon gescheitert ist
        if (!$bTried && (isset($done[$a]) || microtime(true) - $t0 >= $delay)) {
            $bTried = true;
            $reqB = $startB();
            if ($reqB !== null) {
                $hs[$b] = [smCurlNew($reqB, $timeoutB, $hdr[$b]), microtime(true)];
                curl_multi_add_handle($mh, $hs[$b][0]);
                $bStarted = true;
                continue;
            }
        }
        if (!$hs || $st !== CURLM_OK) break;
        curl_multi_select($mh, 0.2);
    }
    foreach ($hs as [$ch]) { curl_multi_remove_handle($mh, $ch); curl_close($ch); }   // Verlierer abbrechen
    curl_multi_close($mh);
    return [$winner, $done, $bStarted];
}

/** Basisadresse eines Dienstes. Nur zum Testen per Umgebungsvariable umlenkbar (SM_API_BASE_TAVILY …). */
function smProvBase(string $id, string $default): string
{
    $env = getenv('SM_API_BASE_' . strtoupper($id));
    if ($id === 'brave' && !$env) $env = getenv('SM_API_BASE');
    return $env ?: $default;
}

/** Baut die Anfrage für einen Dienst */
function smProvRequest(string $id, array $p, string $key): array
{
    if ($id === 'brightdata') return smBdRequest($p, $key);
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
    // Bright Data hat kurze Aussetzer: nur kurz pausieren
    $short = $id === 'brightdata';
    if ($st === 0) {
        return [$r['errno'] === CURLE_OPERATION_TIMEDOUT ? t('%s hat zu lange gebraucht.', $name) : t('%s ist gerade nicht erreichbar.', $name), $short ? 30 : 600];
    }
    $detail = '';
    if ($data) {
        foreach ([$data['error']['detail'] ?? null, $data['error']['message'] ?? null, $data['detail']['error'] ?? null, $data['detail'] ?? null, $data['error'] ?? null, $data['message'] ?? null] as $c) {
            if (is_string($c) && $c !== '') { $detail = $c; break; }
        }
    } elseif (strlen(trim((string)$r['body'])) < 300 && strpos((string)$r['body'], '<') === false) {
        $detail = trim((string)$r['body']);   // Bright Data antwortet bei Fehlern mit einer Textzeile
    }
    if ($id === 'brightdata' && stripos($detail, 'recently failed') !== false) {
        // Bright Data sperrt eine Suche kurz, die eben schiefging. Betrifft nur diese eine Suche, also keine Pause für den Dienst.
        return [t('Google konnte diese Suche gerade nicht abrufen. In ein paar Sekunden klappt es meist wieder.'), 0];
    }
    if ($id === 'brightdata' && $st === 400 && stripos($detail, 'zone') !== false) {
        $msg = t('%s kennt die eingetragene Zone nicht. Den Namen der API findest du im Dashboard unter „Web Access“.', $name);
        $pause = 3600;
    } elseif ($st === 401 || $st === 403 || $st === 407) { $msg = t('%s nimmt den API-Key nicht an.', $name); $pause = 3600; }
    elseif ($st === 402 || $st === 432 || $st === 433) { $msg = t('Das Guthaben bei %s ist aufgebraucht.', $name); $pause = 3600; }
    elseif ($st === 429) { $msg = t('%s bremst gerade (zu viele Anfragen oder Kontingent erschöpft).', $name); $pause = 300; }
    elseif ($st >= 500) { $msg = t('%s hat gerade technische Probleme.', $name); $pause = $short ? 15 : 600; }
    elseif ($st === 400 || $st === 422) { $msg = t('%s hat die Anfrage abgelehnt.', $name); $pause = 0; }
    elseif ($st === 200) { $msg = $short ? t('%s hat diesmal nichts Brauchbares geliefert.', $name) : t('%s hat unverständlich geantwortet.', $name); $pause = $short ? 0 : 600; }
    else { $msg = t('Unerwartete Antwort von %s (HTTP %d).', $name, $st); $pause = 600; }
    if ($detail !== '') $msg .= ' (' . mb_substr(smPlain($detail), 0, 160) . ')';
    return [$msg, $pause];
}

/** Prüft einen Key mit einer echten Suche (kostet eine Suche). Liefert Fehlertext oder ''. */
function smProvTest(string $id, string $key): string
{
    if ($id === 'brightdata' && smBdZone() === '') return t('Bitte zuerst den Namen der API (Zone) eintragen.');
    $p = smSearchParams('web', 'test', ['page' => 1]);
    $p['count'] = 5;
    $p['answer'] = false;
    $req = smProvRequest($id, $p, trim($key));
    $r = smHttpMulti(['x' => $req], 25)['x'];
    if ($id === 'brightdata' && smBdRetry($r)) $r = smHttpMulti(['x' => $req], 25)['x'];
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
    if ($id === 'brightdata') return smBdNormalize($p, $d);
    switch ($p['kind']) {
        case 'images': $out = smNormImages($d); break;
        case 'news':   $out = smNormNews($d); break;
        case 'videos': $out = smNormVideos($d); break;
        default:       $out = smNormWeb($d);
    }
    foreach ($out['results'] as &$r) $r['via'] = ['brave'];
    unset($r);
    return $out;
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

/* ---------- Wenn gar nichts mehr geht: Ausweichziel ---------- */

/** Vorschläge für das Ausweichziel */
function smFallbackPresets(): array
{
    return [
        'https://www.startpage.com/do/search?q={q}' => 'Startpage',
        'https://duckduckgo.com/?q={q}' => 'DuckDuckGo',
        'https://www.mojeek.com/search?q={q}' => 'Mojeek',
        'https://www.ecosia.org/search?q={q}' => 'Ecosia',
        'https://search.brave.com/search?q={q}' => 'Brave Search (Website)',
        'https://www.qwant.com/?q={q}' => 'Qwant',
    ];
}

/** Adresse des Ausweichziels für eine Suche oder '' */
function smFallbackUrl(string $q): string
{
    $u = smCleanBangUrl(smSetting('fallback_url'));
    if ($u === '' || $q === '') return '';
    return str_replace('{q}', rawurlencode($q), $u);
}

function smFallbackName(): string
{
    $u = smSetting('fallback_url');
    return smFallbackPresets()[$u] ?? smHost((string)parse_url($u, PHP_URL_HOST));
}
