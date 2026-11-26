<?php
/* Suchmaschine – Eigene Domain-Listen: blockieren, hochstufen, herabstufen. Gehören dem jeweiligen Konto (users.prefs). */
defined('SM_VERSION') || exit;

const SM_MAX_DOMAINS = 300;    // pro Liste

/** Macht aus Eingaben wie "https://www.Example.org/pfad" eine saubere Domain ("example.org") oder '' */
function smCleanDomain(string $d): string
{
    $d = trim(mb_strtolower($d));
    $d = preg_replace('~^[a-z][a-z0-9+.\-]*://~', '', $d);
    $d = preg_replace('~^\*\.~', '', $d);
    $d = (string)preg_replace('~[/?#:].*$~s', '', $d);
    $d = trim(preg_replace('/^www\./', '', $d), '.');
    if ($d !== '' && preg_match('/[^\x00-\x7f]/', $d) && function_exists('idn_to_ascii')) {
        $a = @idn_to_ascii($d, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if (is_string($a)) $d = $a;
    }
    if (strlen($d) > 253 || !preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/', $d)) return '';
    return $d;
}

/** Liste aus Text (eine Domain pro Zeile, auch Komma oder Leerzeichen) oder Array */
function smCleanDomains($in, int $max = SM_MAX_DOMAINS): array
{
    $items = is_array($in) ? $in : preg_split('/[\s,;]+/', (string)$in);
    $out = [];
    foreach ($items as $d) {
        if (!is_string($d) || ($d = smCleanDomain($d)) === '') continue;
        $out[$d] = true;
        if (count($out) >= $max) break;
    }
    return array_keys($out);
}

/** Gehört der Host zu einer Domain der Liste? (Unterdomains zählen mit) */
function smHostIn(string $host, array $list): bool
{
    $host = strtolower(smHost($host));
    if ($host === '') return false;
    foreach ($list as $d) {
        if ($host === $d || substr($host, -strlen($d) - 1) === '.' . $d) return true;
    }
    return false;
}

/** Die drei persönlichen Listen eines Kontos */
function smDomainLists(?array $me): array
{
    $p = $me['prefs'] ?? [];
    $l = [];
    foreach (['block', 'raise', 'lower'] as $k) $l[$k] = isset($p[$k]) && is_array($p[$k]) ? $p[$k] : [];
    return $l;
}

/** -1 = herabgestuft, 0 = normal, 1 = hochgestuft, null = blockiert */
function smDomainRank(string $host, array $lists): ?int
{
    if (smHostIn($host, $lists['block'])) return null;
    if (smHostIn($host, $lists['raise'])) return 1;
    if (smHostIn($host, $lists['lower'])) return -1;
    return 0;
}

/**
 * Blockieren und Umsortieren nach den Listen des Kontos. Passiert erst nach Brave bzw. dem Cache,
 * der Cache bleibt also für alle gleich. Jeder Treffer bekommt 'rank', dazu zählt 'hidden' die ausgeblendeten.
 */
function smApplyDomainLists(array $res, array $lists): array
{
    $res['hidden'] = 0;
    if (!$lists['block'] && !$lists['raise'] && !$lists['lower']) return $res;

    // Haupttreffer: blockierte raus, hochgestufte nach vorn, herabgestufte nach hinten (sonst Reihenfolge wie bei Brave)
    $keep = [];
    foreach ($res['results'] ?? [] as $i => $r) {
        $rank = smDomainRank((string)($r['host'] ?? ''), $lists);
        if ($rank === null) { $res['hidden']++; continue; }
        $r['rank'] = $rank;
        $keep[$i] = $r;
    }
    $idx = array_keys($keep);
    usort($idx, function ($a, $b) use ($keep) { return [$keep[$b]['rank'], $a] <=> [$keep[$a]['rank'], $b]; });
    $res['results'] = array_map(function ($i) use ($keep) { return $keep[$i]; }, $idx);

    // Mischung der Webseite: Plätze der Webtreffer der Reihe nach neu besetzen
    if (isset($res['order'])) {
        $order = [];
        $n = 0;
        foreach ($res['order'] as $o) {
            if ($o[0] !== 'web') { $order[] = $o; continue; }
            if (isset($keep[$o[1]])) $order[] = ['web', $n++];
        }
        $res['order'] = $order;
    }

    // Beiwerk: nur ausblenden
    foreach (['news', 'videos', 'discussions', 'faq'] as $k) {
        if (empty($res[$k]) || !is_array($res[$k])) continue;
        $before = count($res[$k]);
        $res[$k] = array_values(array_filter($res[$k], function ($r) use ($lists) {
            return !smHostIn((string)($r['host'] ?? ''), $lists['block']);
        }));
        $res['hidden'] += $before - count($res[$k]);
    }
    return $res;
}
