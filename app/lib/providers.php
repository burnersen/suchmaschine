<?php
/* Suchmaschine – mehrere Suchdienste: Brave, Tavily, Exa, parallel.ai, Firecrawl.
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
        'brave' => [
            'name' => 'Brave Search', 'badge' => 'Brave', 'free' => 1000, 'limit' => 950, 'cost' => 1, 'unit' => t('Suchen'),
            'kinds' => ['web', 'images', 'news', 'videos'], 'bangs' => ['brave'], 'paging' => true,
            'site' => 'https://api-dashboard.search.brave.com', 'key_hint' => 'BSA…',
        ],
        'tavily' => [
            'name' => 'Tavily', 'badge' => 'Tavily', 'free' => 1000, 'limit' => 950, 'cost' => 1, 'unit' => t('Credits'),
            'kinds' => ['web', 'news'], 'bangs' => ['tv', 'tavily'], 'paging' => false,
            'site' => 'https://app.tavily.com', 'key_hint' => 'tvly-…',
        ],
        'exa' => [
            'name' => 'Exa', 'badge' => 'Exa', 'free' => 1000, 'limit' => 950, 'cost' => 2, 'unit' => t('Cent'),
            'kinds' => ['web', 'news'], 'bangs' => ['exa'], 'paging' => false,
            'site' => 'https://dashboard.exa.ai', 'key_hint' => '',
        ],
        'parallel' => [
            'name' => 'parallel.ai', 'badge' => 'Parallel', 'free' => 5000, 'limit' => 4950, 'cost' => 1, 'unit' => t('Anfragen'),
            'kinds' => ['web'], 'bangs' => ['pa', 'parallel'], 'paging' => false,
            'site' => 'https://platform.parallel.ai', 'key_hint' => '',
        ],
        'firecrawl' => [
            'name' => 'Firecrawl', 'badge' => 'Firecrawl', 'free' => 1000, 'limit' => 950, 'cost' => 2, 'unit' => t('Credits'),
            'kinds' => ['web', 'news'], 'bangs' => ['fc', 'firecrawl'], 'paging' => false,
            'site' => 'https://www.firecrawl.dev/app', 'key_hint' => 'fc-…',
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
        case 'cache': return '8';      // Stunden
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

/**
 * Der laufende Abrechnungszeitraum. Der Stichtag legt den Tag im Monat fest, an dem das Kontingent neu startet
 * (ohne Stichtag: Monatserster). Tage, die es in einem Monat nicht gibt (31.), rücken auf den letzten Tag.
 */
function smProvCycle(string $id, ?int $now = null): array
{
    $now = $now ?? time();
    $anchor = preg_match('/^\d{4}-\d{2}-(\d{2})$/', smProvGet($id, 'start'), $m) ? max(1, min(31, (int)$m[1])) : 1;
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
    $share = max($c['elapsed'] / $c['days'], 1 / $c['days']);
    $pace = $limit > 0 ? $used / ($limit * $share) : 0.0;
    return [
        'used' => $used, 'limit' => $limit, 'left' => $limit > 0 ? max(0.0, $limit - $used) : INF, 'today' => $today,
        'avg' => $avg, 'projected' => $projected, 'fits' => $fits, 'empty_at' => $empty, 'pace' => $pace,
        'pct' => $limit > 0 ? min(1.0, $used / $limit) : 0.0, 'cycle' => $c,
        'per_day' => $limit > 0 && $c['left'] > 0 ? max(0.0, $limit - $used) / max(1.0, ceil($c['left'])) : INF,
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
        case 'paging': return t('kennt keine weiteren Seiten');
    }
    return $why;
}

/**
 * Wählt die Dienste für eine Suche, in der Reihenfolge, in der sie probiert werden.
 * Liefert [Liste, übersprungene Dienste mit Grund]. Mit Lastverteilung rücken Dienste, die schneller verbrauchen,
 * als ihr Kontingent bis zum Reset erlaubt, nach hinten.
 */
function smProvPick(string $kind, int $userId, ?string $forced, int $page): array
{
    $list = [];
    $skip = [];
    $ids = $forced !== null ? [$forced] : smProvOrder(false);
    foreach ($ids as $id) {
        $why = smProvBlock($id, $kind, $userId);
        if ($why === '' && $page > 1 && !smProviders()[$id]['paging']) $why = 'paging';
        if ($why === '') { $list[] = $id; continue; }
        if (!in_array($why, ['off', 'kind'], true) || $forced !== null) $skip[$id] = $why;
    }
    if ($forced === null && count($list) > 1 && smSetting('balance') === '1') {
        $pace = [];
        foreach ($list as $i => $id) $pace[$id] = [smProvForecast($id)['pace'] > 1.0 ? 1 : 0, $i];
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
    return [
        'kind' => $kind, 'q' => $q, 'page' => max(1, min(10, (int)($o['page'] ?? 1))),
        'country' => (string)($o['country'] ?? 'ALL'), 'lang' => (string)($o['search_lang'] ?? 'en'),
        'safe' => (string)($o['safesearch'] ?? 'moderate'), 'fresh' => isset(smFreshness()[$o['freshness'] ?? '']) ? (string)($o['freshness'] ?? '') : '',
        'exact' => !empty($o['exact']), 'count' => !empty($o['endless']) ? 20 : max(5, min(20, (int)smSetting('per_page'))),
        'answer' => $kind === 'web' && smSetting('answers') === '1' && max(1, (int)($o['page'] ?? 1)) === 1,
        'ui' => smLang(),
    ];
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

    // Automatisch: zuerst der Zwischenspeicher, egal welcher Dienst geliefert hat
    if ($hit = smCacheGet(($forced ?? 'auto') . '|' . $ck)) {
        $hit['cached'] = true;
        return $hit;
    }

    [$list, $skip] = smProvPick($kind, $userId, $forced, $params['page']);
    $errors = [];
    foreach ($list as $id) {
        try {
            $out = smProvRun($id, $params, $userId);
        } catch (SmSearchError $e) {
            $errors[$id] = $e->getMessage();
            continue;
        }
        $out['source'] = $id;
        $out['sources'] = [$id];
        $out['notice'] = smNoticeText($id, $skip, $errors);
        $ttl = smProvTtl($id);
        smCacheSet($id . '|' . $ck, $out, $ttl);
        if ($forced === null) smCacheSet('auto|' . $ck, $out, min($ttl, 3600));
        $out['cached'] = false;
        return $out;
    }
    throw new SmSearchExhausted(smExhaustedText($kind, $skip, $errors, $forced));
}

/** „Ergebnisse von Tavily – Brave: Limit erreicht“ */
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
        if (in_array($kind, ['images', 'videos'], true)) return t('Diese Art der Suche liefert nur Brave, und Brave ist nicht eingerichtet.');
        return t('Es ist noch kein Suchdienst eingerichtet. Ein Administrator kann unter Verwaltung → Suchdienste einen API-Key hinterlegen.');
    }
    $lead = in_array($kind, ['images', 'videos'], true)
        ? t('Diese Art der Suche liefert nur Brave, und Brave ist gerade nicht verfügbar.')
        : t('Gerade ist kein Suchdienst verfügbar.');
    return $lead . ' ' . implode(' · ', $parts);
}

/** Bucht Credits vorab, falls Limit und Kontingent es erlauben (gleichzeitige Suchen zählen sauber mit) */
function smProvReserve(string $id, string $kind, int $userId, float $cost): bool
{
    $db = smDb();
    $db->exec('BEGIN IMMEDIATE');
    try {
        $limit = (int)smProvGet($id, 'limit');
        if (($limit > 0 && smUsageSum($id, smProvCycle($id)['from']) + $cost > $limit) || smQuotaCheck($id, $userId, $cost) !== '') {
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
    foreach (smHttpMulti($reqs, 15) as $id => $r) {
        $cost = $reqs[$id]['cost'];
        $data = json_decode((string)$r['body'], true);
        if ($r['status'] !== 200 || !is_array($data)) {
            smUsageAdd($id, $params['kind'], $userId, -$cost, -1);
            [$msg, $pause] = smProvErrorText($id, $r, is_array($data) ? $data : null);
            smProvFail($id, $msg, $pause);
            error_log('Suchmaschine: ' . $id . ' HTTP ' . $r['status'] . ' ' . $r['error'] . ' ' . substr((string)$r['body'], 0, 300));
            $out[$id] = ['error' => $msg];
            continue;
        }
        smProvOk($id, $r['ms']);
        // Echte Kosten, falls der Dienst sie mitteilt (Exa in Dollar, Firecrawl und Tavily in Credits)
        $real = smProvRealCost($id, $data);
        if ($real !== null && abs($real - $cost) > 0.0001) smUsageAdd($id, $params['kind'], $userId, $real - $cost, 0);
        $out[$id] = smProvNormalize($id, $params, $data);
    }
    return $out;
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
        if ($why === '') $ids[] = $id; elseif (!in_array($why, ['off', 'kind'], true)) $skip[$id] = $why;
    }
    $res = smProvRunMany($ids, $params, $userId);
    $errors = [];
    $ok = [];
    foreach ($res as $id => $r) {
        if (isset($r['error'])) $errors[$id] = $r['error']; else $ok[$id] = $r;
    }
    if (!$ok) throw new SmSearchExhausted(smExhaustedText($kind, $skip, $errors, null));

    // Grundgerüst: Brave liefert Beiwerk (Infobox, News, Fragen …), sonst der erste Dienst
    $base = $ok['brave'] ?? reset($ok);
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

/** $reqs: id => ['url', 'method', 'headers', 'body']. Liefert id => ['status', 'body', 'errno', 'error', 'ms']. */
function smHttpMulti(array $reqs, int $timeout): array
{
    if (!$reqs) return [];
    $mh = curl_multi_init();
    $hs = [];
    foreach ($reqs as $id => $r) {
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
        curl_setopt_array($ch, $opt);
        curl_multi_add_handle($mh, $ch);
        $hs[$id] = [$ch, microtime(true)];
    }
    do {
        $st = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 0.5);
    } while ($running && $st === CURLM_OK);
    $out = [];
    foreach ($hs as $id => [$ch, $t0]) {
        $body = curl_multi_getcontent($ch);
        $out[$id] = [
            'status' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'body' => (string)$body,
            'errno' => curl_errno($ch), 'error' => curl_error($ch),
            'ms' => (int)round((float)curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000) ?: (int)round((microtime(true) - $t0) * 1000),
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
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
    $json = ['Accept: application/json', 'Content-Type: application/json'];
    $days = ['pd' => 1, 'pw' => 7, 'pm' => 31, 'py' => 365][$p['fresh']] ?? 0;
    $country = $p['country'] !== 'ALL' && $p['country'] !== '' ? $p['country'] : '';
    switch ($id) {
        case 'brave':
            $q = ['q' => $p['q'], 'country' => $country ?: 'ALL', 'search_lang' => $p['lang'], 'ui_lang' => $p['ui'] === 'de' ? 'de-DE' : 'en-US', 'safesearch' => $p['safe']];
            switch ($p['kind']) {
                case 'images':
                    $path = 'images/search';
                    $q['count'] = 100;
                    $q['safesearch'] = $p['safe'] === 'off' ? 'off' : 'strict';   // Bilder kennen nur off/strict
                    unset($q['ui_lang']);
                    break;
                case 'news':
                case 'videos':
                    $path = $p['kind'] . '/search';
                    $q['count'] = 20;
                    $q['offset'] = $p['page'] - 1;
                    break;
                default:
                    $path = 'web/search';
                    $q['count'] = $p['count'];
                    $q['offset'] = $p['page'] - 1;
                    if ($p['exact']) $q['spellcheck'] = '0';
            }
            if ($p['fresh'] !== '' && $p['kind'] !== 'images') $q['freshness'] = $p['fresh'];
            return ['url' => smProvBase('brave', SM_API) . $path . '?' . http_build_query($q), 'method' => 'GET',
                    'headers' => ['Accept: application/json', 'X-Subscription-Token: ' . $key]];

        case 'tavily':
            $b = ['query' => $p['q'], 'search_depth' => 'basic', 'max_results' => 10, 'topic' => $p['kind'] === 'news' ? 'news' : 'general',
                  'include_answer' => $p['answer'] ? 'basic' : false, 'include_published_date' => true];
            if ($days) $b['time_range'] = ['1' => 'day', '7' => 'week', '31' => 'month', '365' => 'year'][(string)$days];
            if ($country !== '' && $p['kind'] !== 'news' && ($cn = smTavilyCountry($country)) !== '') $b['country'] = $cn;
            if ($p['safe'] === 'strict') $b['safe_search'] = true;
            return ['url' => smProvBase('tavily', 'https://api.tavily.com/') . 'search', 'method' => 'POST',
                    'headers' => array_merge($json, ['Authorization: Bearer ' . $key]), 'body' => json_encode($b)];

        case 'exa':
            $b = ['query' => $p['q'], 'type' => 'auto', 'numResults' => 10, 'contents' => ['text' => ['maxCharacters' => 320]]];
            if ($p['kind'] === 'news') $b['category'] = 'news';
            if ($days) $b['startPublishedDate'] = gmdate('Y-m-d\TH:i:s\Z', time() - $days * 86400);
            if ($country !== '') $b['userLocation'] = $country;
            return ['url' => smProvBase('exa', 'https://api.exa.ai/') . 'search', 'method' => 'POST',
                    'headers' => array_merge($json, ['x-api-key: ' . $key]), 'body' => json_encode($b)];

        case 'parallel':
            $adv = ['max_results' => 10];
            if ($country !== '') $adv['location'] = strtolower($country);
            if ($days) $adv['source_policy'] = ['after_date' => date('Y-m-d', time() - $days * 86400)];
            $b = ['objective' => $p['q'], 'search_queries' => [$p['q']], 'mode' => 'fast', 'max_chars_total' => 4000, 'advanced_settings' => $adv];
            return ['url' => smProvBase('parallel', 'https://api.parallel.ai/') . 'v1/search', 'method' => 'POST',
                    'headers' => array_merge($json, ['x-api-key: ' . $key]), 'body' => json_encode($b)];

        case 'firecrawl':
            $b = ['query' => mb_substr($p['q'], 0, 500), 'limit' => 10, 'sources' => [['type' => $p['kind'] === 'news' ? 'news' : 'web']], 'timeout' => 14000];
            if ($days) $b['tbs'] = 'qdr:' . ['1' => 'd', '7' => 'w', '31' => 'm', '365' => 'y'][(string)$days];
            if ($country !== '') $b['country'] = $country;
            if ($p['safe'] === 'strict') $b['safe'] = true;
            return ['url' => smProvBase('firecrawl', 'https://api.firecrawl.dev/') . 'v2/search', 'method' => 'POST',
                    'headers' => array_merge($json, ['Authorization: Bearer ' . $key]), 'body' => json_encode($b)];
    }
    throw new SmSearchError('unknown provider');
}

/** Tavily nimmt Ländernamen statt Codes (nur zum Gewichten) */
function smTavilyCountry(string $code): string
{
    $m = ['AR' => 'argentina', 'AU' => 'australia', 'AT' => 'austria', 'BE' => 'belgium', 'BR' => 'brazil', 'CA' => 'canada', 'CL' => 'chile',
          'CN' => 'china', 'DK' => 'denmark', 'FI' => 'finland', 'FR' => 'france', 'DE' => 'germany', 'IN' => 'india', 'ID' => 'indonesia',
          'IT' => 'italy', 'JP' => 'japan', 'KR' => 'south korea', 'MY' => 'malaysia', 'MX' => 'mexico', 'NL' => 'netherlands',
          'NZ' => 'new zealand', 'NO' => 'norway', 'PH' => 'philippines', 'PL' => 'poland', 'PT' => 'portugal', 'SA' => 'saudi arabia',
          'ZA' => 'south africa', 'ES' => 'spain', 'SE' => 'sweden', 'CH' => 'switzerland', 'TW' => 'taiwan', 'TR' => 'turkey',
          'GB' => 'united kingdom', 'US' => 'united states'];
    return $m[$code] ?? '';
}

/** Echte Kosten aus der Antwort, falls vorhanden */
function smProvRealCost(string $id, array $d): ?float
{
    if ($id === 'exa' && isset($d['costDollars']['total']) && is_numeric($d['costDollars']['total'])) return round((float)$d['costDollars']['total'] * 100, 3);
    if ($id === 'firecrawl' && isset($d['creditsUsed']) && is_numeric($d['creditsUsed'])) return (float)$d['creditsUsed'];
    if ($id === 'tavily' && isset($d['usage']['credits']) && is_numeric($d['usage']['credits'])) return (float)$d['usage']['credits'];
    return null;
}

/** Verständliche Fehlermeldung und Pause in Sekunden */
function smProvErrorText(string $id, array $r, ?array $data): array
{
    $name = smProviders()[$id]['name'];
    $st = $r['status'];
    if ($st === 0) {
        return [$r['errno'] === CURLE_OPERATION_TIMEDOUT ? t('%s hat zu lange gebraucht.', $name) : t('%s ist gerade nicht erreichbar.', $name), 600];
    }
    $detail = '';
    if ($data) {
        foreach ([$data['error']['detail'] ?? null, $data['error']['message'] ?? null, $data['detail']['error'] ?? null, $data['detail'] ?? null, $data['error'] ?? null, $data['message'] ?? null] as $c) {
            if (is_string($c) && $c !== '') { $detail = $c; break; }
        }
    }
    if ($st === 401 || $st === 403) { $msg = t('%s nimmt den API-Key nicht an.', $name); $pause = 3600; }
    elseif ($st === 402 || $st === 432 || $st === 433) { $msg = t('Das Guthaben bei %s ist aufgebraucht.', $name); $pause = 3600; }
    elseif ($st === 429) { $msg = t('%s bremst gerade (zu viele Anfragen oder Kontingent erschöpft).', $name); $pause = 300; }
    elseif ($st >= 500) { $msg = t('%s hat gerade technische Probleme.', $name); $pause = 600; }
    elseif ($st === 400 || $st === 422) { $msg = t('%s hat die Anfrage abgelehnt.', $name); $pause = 0; }
    elseif ($st === 200) { $msg = t('%s hat unverständlich geantwortet.', $name); $pause = 600; }
    else { $msg = t('Unerwartete Antwort von %s (HTTP %d).', $name, $st); $pause = 600; }
    if ($detail !== '') $msg .= ' (' . mb_substr(smPlain($detail), 0, 160) . ')';
    return [$msg, $pause];
}

/** Prüft einen Key mit einer echten Suche (kostet eine Suche). Liefert Fehlertext oder ''. */
function smProvTest(string $id, string $key): string
{
    $p = smSearchParams('web', 'test', ['page' => 1]);
    $p['count'] = 5;
    $p['answer'] = false;
    $r = smHttpMulti(['x' => smProvRequest($id, $p, trim($key))], 15)['x'];
    $data = json_decode($r['body'], true);
    if ($r['status'] === 200 && is_array($data)) {
        // Auch der Test kostet beim Dienst eine Suche: mitzählen
        smUsageAdd($id, 'test', 0, smProvRealCost($id, $data) ?? (float)smProvGet($id, 'cost'));
        return '';
    }
    return smProvErrorText($id, $r, is_array($data) ? $data : null)[0];
}

/* ---------- Antworten vereinheitlichen ---------- */

function smProvNormalize(string $id, array $p, array $d): array
{
    if ($id === 'brave') {
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
    $items = [];
    switch ($id) {
        case 'tavily':
            foreach ((array)($d['results'] ?? []) as $r) {
                if (is_array($r)) $items[] = [$r['url'] ?? '', $r['title'] ?? '', $r['content'] ?? '', $r['published_date'] ?? ''];
            }
            break;
        case 'exa':
            foreach ((array)($d['results'] ?? []) as $r) {
                if (!is_array($r)) continue;
                $txt = is_string($r['text'] ?? null) ? $r['text'] : (is_array($r['highlights'] ?? null) ? implode(' … ', array_filter($r['highlights'], 'is_string')) : '');
                $items[] = [$r['url'] ?? '', $r['title'] ?? '', $txt, $r['publishedDate'] ?? ''];
            }
            break;
        case 'parallel':
            foreach ((array)($d['results'] ?? []) as $r) {
                if (!is_array($r)) continue;
                $ex = is_array($r['excerpts'] ?? null) ? implode(' … ', array_filter($r['excerpts'], 'is_string')) : '';
                $items[] = [$r['url'] ?? '', $r['title'] ?? '', smStripMarkdown($ex), $r['publish_date'] ?? ''];
            }
            break;
        case 'firecrawl':
            $list = $d['data'][$p['kind'] === 'news' ? 'news' : 'web'] ?? ($d['data'] ?? []);
            foreach ((array)$list as $r) {
                if (is_array($r)) $items[] = [$r['url'] ?? '', $r['title'] ?? '', $r['description'] ?? $r['snippet'] ?? '', $r['date'] ?? ''];
            }
            break;
    }
    $res = [];
    foreach ($items as [$url, $title, $desc, $date]) {
        $url = smLink($url);
        if ($url === '') continue;
        $res[] = smMkResult($id, $url, (string)$title, (string)$desc, (string)$date);
        if (count($res) >= 20) break;
    }
    $answer = is_string($d['answer'] ?? null) ? trim($d['answer']) : '';
    if ($p['kind'] === 'news') {
        $news = array_map(function ($r) {
            return ['title' => $r['title'], 'url' => $r['url'], 'desc' => $r['desc'], 'host' => $r['host'], 'source' => '', 'favicon' => '',
                    'age' => '', 'ts' => $r['ts'], 'thumb' => '', 'breaking' => false, 'via' => $r['via']];
        }, $res);
        return ['results' => $news, 'more' => false, 'altered' => null];
    }
    $order = [];
    foreach ($res as $i => $_) $order[] = ['web', $i];
    return [
        'results' => $res, 'news' => [], 'videos' => [], 'faq' => [], 'discussions' => [], 'infobox' => null,
        'order' => $order, 'more' => false, 'altered' => null, 'answer' => mb_substr($answer, 0, 1500),
    ];
}

/** Ein Treffer im gemeinsamen Format (Text ohne HTML, Hervorhebungen gibt es nur bei Brave) */
function smMkResult(string $id, string $url, string $title, string $desc, string $date): array
{
    $host = smHost((string)parse_url($url, PHP_URL_HOST));
    $title = trim(preg_replace('/\s+/u', ' ', smPlain($title)));
    $desc = trim(preg_replace('/\s+/u', ' ', smPlain($desc)));
    if (mb_strlen($desc) > 300) $desc = rtrim(mb_substr($desc, 0, 300), " ,.;:-") . ' …';
    $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
    $segs = $path === '' ? [] : array_slice(array_map('rawurldecode', explode('/', $path)), 0, 3);
    return [
        'title' => $title !== '' ? $title : $host, 'url' => $url, 'desc' => $desc, 'host' => $host, 'site' => '',
        'path' => $segs ? '› ' . mb_substr(implode(' › ', $segs), 0, 80) : '', 'favicon' => '', 'age' => '', 'ts' => smParseTime($date),
        'thumb' => '', 'links' => [], 'via' => [$id],
    ];
}

/** Markdown-Reste (Überschriften, Links, Hervorhebungen) aus Textauszügen entfernen */
function smStripMarkdown(string $s): string
{
    $s = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $s);
    $s = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $s);
    $s = preg_replace('/^#{1,6}\s*/m', '', $s);
    return str_replace(['**', '__', '`'], '', (string)$s);
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

/** Kommazahl hübsch (Credits von Exa sind Cent mit Nachkommastellen) */
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
