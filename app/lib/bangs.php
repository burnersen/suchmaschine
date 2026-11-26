<?php
/* Suchmaschine – Bangs (!w Berlin) und Linsen (Suche auf bestimmte Seiten eingeschränkt). Mitgeliefert werden ein paar Standards, die jedes Konto abschalten kann, dazu eigene pro Konto. */
defined('SM_VERSION') || exit;

const SM_MAX_BANGS = 200;
const SM_MAX_LENSES = 50;
const SM_LENS_SITES = 12;      // Brave nimmt höchstens 400 Zeichen pro Suche

/* ---------- Bangs ---------- */

/** Mitgelieferte Bangs: Kürzel => [Name, Adresse mit {q}] */
function smBuiltinBangs(): array
{
    $de = smLang() === 'de';
    return [
        'w'    => ['Wikipedia', $de ? 'https://de.wikipedia.org/w/index.php?search={q}' : 'https://en.wikipedia.org/w/index.php?search={q}'],
        'wde'  => ['Wikipedia (Deutsch)', 'https://de.wikipedia.org/w/index.php?search={q}'],
        'wen'  => ['Wikipedia (English)', 'https://en.wikipedia.org/w/index.php?search={q}'],
        'gh'   => ['GitHub', 'https://github.com/search?q={q}'],
        'yt'   => ['YouTube', 'https://www.youtube.com/results?search_query={q}'],
        'osm'  => ['OpenStreetMap', 'https://www.openstreetmap.org/search?query={q}'],
        'so'   => ['Stack Overflow', 'https://stackoverflow.com/search?q={q}'],
        'r'    => ['Reddit', 'https://www.reddit.com/search/?q={q}'],
        'mdn'  => ['MDN Web Docs', 'https://developer.mozilla.org/search?q={q}'],
        'php'  => ['PHP', 'https://www.php.net/search.php?pattern={q}'],
        'imdb' => ['IMDb', 'https://www.imdb.com/find/?q={q}'],
        'leo'  => ['LEO', 'https://dict.leo.org/englisch-deutsch/{q}'],
        'dl'   => ['DeepL', $de ? 'https://www.deepl.com/translator#auto/de/{q}' : 'https://www.deepl.com/translator#auto/en/{q}'],
        'a'    => ['Amazon', $de ? 'https://www.amazon.de/s?k={q}' : 'https://www.amazon.com/s?k={q}'],
        'ebay' => ['eBay', $de ? 'https://www.ebay.de/sch/i.html?_nkw={q}' : 'https://www.ebay.com/sch/i.html?_nkw={q}'],
        'wa'   => ['Wolfram|Alpha', 'https://www.wolframalpha.com/input?i={q}'],
        'ia'   => ['Internet Archive', 'https://web.archive.org/web/*/{q}'],
        'ddg'  => ['DuckDuckGo', 'https://duckduckgo.com/?q={q}'],
        'sp'   => ['Startpage', 'https://www.startpage.com/do/search?q={q}'],
        'mj'   => ['Mojeek', 'https://www.mojeek.com/search?q={q}'],
        'eco'  => ['Ecosia', 'https://www.ecosia.org/search?q={q}'],
        'b'    => ['Brave Search', 'https://search.brave.com/search?q={q}'],
        'g'    => ['Google', 'https://www.google.com/search?q={q}'],
    ];
}

/** Eingebaute Bangs, die nur den Reiter wechseln (kosten auch keine Suche bis zur eigentlichen Suche) */
function smTabBangs(): array
{
    return ['i' => 'images', 'n' => 'news', 'v' => 'videos'];
}

/** Kürzel, die die Suchmaschine selbst braucht (Reiter und Suchdienste) */
function smBangReserved(string $t): bool
{
    return isset(smTabBangs()[$t]) || isset(smProviderBangs()[$t]);
}

function smCleanBangTrigger($t): string
{
    $t = strtolower(ltrim(trim(is_string($t) ? $t : ''), '!'));
    return preg_match('/^[a-z0-9][a-z0-9._-]{0,23}$/', $t) ? $t : '';
}

function smCleanBangUrl($u): string
{
    $u = trim(is_string($u) ? $u : '');
    if (strlen($u) > 500 || !preg_match('~^https?://[^\s/?#]+~i', $u) || preg_match('/[\x00-\x20"<>\\\\]/', $u)) return '';
    return $u;
}

/** Eigene Bangs eines Kontos, sauber: [['t' => Kürzel, 'n' => Name, 'u' => Adresse], …] */
function smCleanBangs($in): array
{
    $out = [];
    foreach (is_array($in) ? $in : [] as $b) {
        if (!is_array($b)) continue;
        $t = smCleanBangTrigger($b['t'] ?? '');
        $u = smCleanBangUrl($b['u'] ?? '');
        if ($t === '' || $u === '' || smBangReserved($t)) continue;
        $n = mb_substr(smPlain((string)($b['n'] ?? '')), 0, 40);
        $out[$t] = ['t' => $t, 'n' => $n !== '' ? $n : $t, 'u' => $u];
        if (count($out) >= SM_MAX_BANGS) break;
    }
    ksort($out, SORT_STRING);
    return array_values($out);
}

/** Alle Bangs, die für dieses Konto gelten: Kürzel => [Name, Adresse, eigener?]. Eigene gehen vor. */
function smBangs(?array $me): array
{
    $p = $me['prefs'] ?? [];
    $off = array_flip(is_array($p['bang_off'] ?? null) ? $p['bang_off'] : []);
    $all = [];
    foreach (smBuiltinBangs() as $t => $b) {
        if (!isset($off[$t])) $all[$t] = [$b[0], $b[1], false];
    }
    foreach (smCleanBangs($p['bangs'] ?? []) as $b) $all[$b['t']] = [$b['n'], $b['u'], true];
    ksort($all, SORT_STRING);
    return $all;
}

/**
 * Sucht ein Bang ganz vorne oder ganz hinten in der Suche ("!w Berlin" oder "Berlin !w").
 * Liefert ['to' => Adresse, 'internal' => bool] oder null. Unbekannte Kürzel bleiben ganz normaler Suchtext.
 */
function smBangTarget(string $q, ?array $me): ?array
{
    $words = preg_split('/\s+/u', trim($q));
    if (!$words || $words[0] === '') return null;
    foreach ([0, count($words) - 1] as $pos) {
        $w = $words[$pos];
        if (strlen($w) < 2 || $w[0] !== '!') continue;
        $t = smCleanBangTrigger($w);
        if ($t === '') continue;
        $rest = $words;
        unset($rest[$pos]);
        $rest = trim(implode(' ', $rest));
        if (isset(smTabBangs()[$t])) {
            return ['to' => smUrl(['q' => $rest !== '' ? $rest : null, 't' => smTabBangs()[$t]]), 'internal' => true];
        }
        // Gezielt einen Suchdienst ansteuern: !brave, !tv, !exa, !pa, !fc, !multi
        if (isset(smProviderBangs()[$t])) {
            $tab = isset($_GET['t']) && in_array($_GET['t'], ['news', 'images', 'videos'], true) ? $_GET['t'] : null;
            return ['to' => smUrl(['q' => $rest !== '' ? $rest : null, 't' => $tab, 'src' => smProviderBangs()[$t]]), 'internal' => true];
        }
        $bangs = smBangs($me);
        if (!isset($bangs[$t])) continue;
        $url = $bangs[$t][1];
        if ($rest === '') {
            // Ohne Suchbegriff: zur Startseite des Dienstes
            $p = parse_url($url);
            $url = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '') . '/';
        } else {
            $url = str_replace('{q}', rawurlencode($rest), $url);
        }
        return ['to' => $url, 'internal' => false];
    }
    return null;
}

/* ---------- Linsen ---------- */

/** Mitgelieferte Linsen: id => [Name, Domains, zusätzliche Operatoren] */
function smBuiltinLenses(): array
{
    return [
        'forum'   => [t('Foren und Diskussionen'), ['reddit.com', 'stackexchange.com', 'news.ycombinator.com', 'gutefrage.net', 'lemmy.world'], ''],
        'code'    => [t('Programmieren'), ['github.com', 'stackoverflow.com', 'developer.mozilla.org', 'docs.python.org', 'php.net', 'gitlab.com'], ''],
        'wiki'    => [t('Wikis und Nachschlagewerke'), ['wikipedia.org', 'wiktionary.org', 'wikibooks.org', 'wikivoyage.org'], ''],
        'science' => [t('Wissenschaft'), ['arxiv.org', 'nature.com', 'sciencedirect.com', 'ncbi.nlm.nih.gov', 'researchgate.net', 'semanticscholar.org'], ''],
    ];
}

function smCleanLensExtra($x): string
{
    $x = trim(preg_replace('/[\x00-\x1f]+/', ' ', is_string($x) ? $x : ''));
    return mb_substr($x, 0, 150);
}

/** Eigene Linsen eines Kontos, sauber: [['id', 'n' => Name, 's' => [Domains], 'x' => Operatoren], …] */
function smCleanLenses($in): array
{
    $out = [];
    foreach (is_array($in) ? $in : [] as $l) {
        if (!is_array($l)) continue;
        $id = (string)($l['id'] ?? '');
        if (!preg_match('/^u[0-9a-f]{8}$/', $id)) $id = 'u' . bin2hex(random_bytes(4));
        $n = mb_substr(smPlain((string)($l['n'] ?? '')), 0, 40);
        $s = smCleanDomains($l['s'] ?? [], SM_LENS_SITES);
        $x = smCleanLensExtra($l['x'] ?? '');
        if ($n === '' || (!$s && $x === '')) continue;
        $out[$id] = ['id' => $id, 'n' => $n, 's' => $s, 'x' => $x];
        if (count($out) >= SM_MAX_LENSES) break;
    }
    return array_values($out);
}

/** Alle Linsen dieses Kontos: id => [Name, Domains, Operatoren, eigene?] */
function smLenses(?array $me): array
{
    $p = $me['prefs'] ?? [];
    $off = array_flip(is_array($p['lens_off'] ?? null) ? $p['lens_off'] : []);
    $all = [];
    foreach (smBuiltinLenses() as $id => $l) {
        if (!isset($off[$id])) $all[$id] = [$l[0], $l[1], $l[2], false];
    }
    foreach (smCleanLenses($p['lenses'] ?? []) as $l) $all[$l['id']] = [$l['n'], $l['s'], $l['x'], true];
    return $all;
}

/** Was an die Suche angehängt wird: "site:a OR site:b" plus eigene Operatoren */
function smLensQuery(array $lens): string
{
    $sites = array_map(function ($d) { return 'site:' . $d; }, $lens[1]);
    return trim(implode(' OR ', $sites) . ' ' . $lens[2]);
}

/** Die Suche, wie sie an Brave geht: eigener Text plus Linse und Dateityp, zusammen höchstens 400 Zeichen */
function smApiQuery(string $q, string $ft, ?array $lens): string
{
    $extra = trim(($lens ? smLensQuery($lens) : '') . ($ft !== '' ? ' filetype:' . $ft : ''));
    if ($extra === '') return $q;
    $room = 399 - mb_strlen($extra);
    return trim(mb_substr($q, 0, max(20, $room))) . ' ' . $extra;
}
