<?php
/* Suchmaschine – Werkzeuge rund um die Suche: Dateitypen, Bangs, Linsen, eigene Domain-Listen, Sichern und Wiederherstellen.
   Alles Persönliche (Domain-Listen, eigene Bangs und Linsen) gehört dem jeweiligen Konto und liegt in users.prefs.
   Mitgeliefert werden nur ein paar Standard-Bangs und -Linsen, die jedes Konto für sich abschalten kann. */
defined('SM_VERSION') || exit;

const SM_MAX_DOMAINS = 300;    // pro Liste
const SM_MAX_BANGS = 200;
const SM_MAX_LENSES = 50;
const SM_LENS_SITES = 12;      // Brave nimmt höchstens 400 Zeichen pro Suche

/* ---------- Dateitypen ---------- */

/** Die Auswahl im Menü. Andere Endungen kann man selbst eintippen. */
function smFileTypes(): array
{
    return ['pdf' => 'PDF', 'docx' => 'DOCX', 'xlsx' => 'XLSX', 'pptx' => 'PPTX', 'txt' => 'TXT', 'epub' => 'EPUB', 'csv' => 'CSV',
            'odt' => 'ODT', 'ods' => 'ODS', 'odp' => 'ODP', 'rtf' => 'RTF', 'doc' => 'DOC', 'xls' => 'XLS', 'ppt' => 'PPT'];
}

/** Dateityp aus der Adresse: nur Buchstaben und Ziffern, ohne Punkt. '' = alle. */
function smCleanFileType($ft): string
{
    $ft = strtolower(ltrim(trim(is_string($ft) ? $ft : ''), '.'));
    return preg_match('/^[a-z0-9]{1,10}$/', $ft) ? $ft : '';
}

/* ---------- Domains ---------- */

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
        'b'    => ['Brave Search', 'https://search.brave.com/search?q={q}'],
        'g'    => ['Google', 'https://www.google.com/search?q={q}'],
    ];
}

/** Eingebaute Bangs, die nur den Reiter wechseln (kosten auch keine Suche bis zur eigentlichen Suche) */
function smTabBangs(): array
{
    return ['i' => 'images', 'n' => 'news', 'v' => 'videos'];
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
        if ($t === '' || $u === '' || isset(smTabBangs()[$t])) continue;
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

/* ---------- Weiterleitung nach außen (Bangs, Auf gut Glück) ---------- */

/** Kein 303 nach außen: Browser blockieren das nach einem Formular (CSP form-action). Darum eine Zwischenseite. */
function smExternalRedirect(string $url, string $title): void
{
    smHeaders();
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!DOCTYPE html><meta charset="utf-8"><meta name="referrer" content="no-referrer">'
       . '<meta http-equiv="refresh" content="0;url=' . h($url) . '"><title>' . h($title) . '</title>'
       . '<p><a href="' . h($url) . '" rel="noopener noreferrer">' . h($url) . '</a></p>';
    exit;
}

/* ---------- Persönliche Einstellungen sichern und wiederherstellen ---------- */

/** Alle persönlichen Einstellungen geprüft und bereinigt (für Import und zum Speichern) */
function smCleanPrefs(array $p): array
{
    $out = [];
    if (isset($p['country']) && isset(smCountries()[$p['country']])) $out['country'] = $p['country'];
    if (isset($p['search_lang']) && isset(smSearchLangs()[$p['search_lang']])) $out['search_lang'] = $p['search_lang'];
    if (in_array($p['safesearch'] ?? '', ['off', 'moderate', 'strict'], true)) $out['safesearch'] = $p['safesearch'];
    if (in_array($p['scroll'] ?? '', ['pages', 'endless'], true)) $out['scroll'] = $p['scroll'];
    foreach (['newtab', 'history'] as $k) if (isset($p[$k])) $out[$k] = !empty($p[$k]);
    foreach (['block', 'raise', 'lower'] as $k) $out[$k] = smCleanDomains($p[$k] ?? []);
    $out['bangs'] = smCleanBangs($p['bangs'] ?? []);
    $out['lenses'] = smCleanLenses($p['lenses'] ?? []);
    $out['bang_off'] = array_values(array_intersect(array_keys(smBuiltinBangs()), is_array($p['bang_off'] ?? null) ? $p['bang_off'] : []));
    $out['lens_off'] = array_values(array_intersect(array_keys(smBuiltinLenses()), is_array($p['lens_off'] ?? null) ? $p['lens_off'] : []));
    return $out;
}

/** Datei zum Herunterladen mit allen persönlichen Einstellungen */
function smServeExport(array $me): void
{
    $data = [
        'format'   => 'suchmaschine-prefs',
        'version'  => 1,
        'app'      => SM_VERSION,
        'exported' => date('c'),
        'account'  => $me['name'],
        'prefs'    => smCleanPrefs($me['prefs']),
    ];
    smHeaders();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Content-Disposition: attachment; filename="suchmaschine-' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $me['name']) . '-' . date('Y-m-d') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Hochgeladene Sicherung einlesen. Liefert [Einstellungen, Fehlertext]. */
function smReadImport(string $field, string $format): array
{
    $f = $_FILES[$field] ?? null;
    if (!is_array($f) || empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) return [null, t('Bitte eine Sicherungsdatei auswählen.')];
    if ((int)$f['size'] > 1048576) return [null, t('Die Datei ist zu groß.')];
    $j = json_decode((string)file_get_contents($f['tmp_name']), true);
    if (!is_array($j) || ($j['format'] ?? '') !== $format || !is_array($j['prefs'] ?? null)) {
        return [null, t('Das ist keine passende Sicherungsdatei der Suchmaschine.')];
    }
    return [$j['prefs'], ''];
}

/* ---------- Einstellungen der Installation sichern (Administrator) ---------- */

/** Welche Einstellungen der Installation in eine Sicherung gehören, und wie sie geprüft werden */
function smConfigKeys(): array
{
    $int = function (int $min, int $max) { return function ($v) use ($min, $max) { return is_numeric($v) ? (string)max($min, min($max, (int)$v)) : null; }; };
    $in = function (array $ok) { return function ($v) use ($ok) { return in_array((string)$v, $ok, true) ? (string)$v : null; }; };
    return [
        'site_name'     => function ($v) { return is_string($v) ? trim(mb_substr($v, 0, 40)) : null; },
        'accent'        => $in(array_keys(smAccents())),
        'scroll'        => $in(['pages', 'endless']),
        'monthly_limit' => $int(0, 1000000),
        'cache_min'     => $int(0, 60),
        'per_page'      => $int(5, 20),
        'country'       => $in(array_keys(smCountries())),
        'search_lang'   => $in(array_keys(smSearchLangs())),
        'safesearch'    => $in(['off', 'moderate', 'strict']),
        'public'        => $in(['0', '1']),
        'public_hour'   => $int(1, 1000),
        'auto_update'   => $in(['0', '1']),
        'img_proxy'     => $in(['0', '1']),
        'api_key'       => function ($v) { return is_string($v) && preg_match('/^[\x21-\x7e]{8,200}$/', trim($v)) ? trim($v) : null; },
    ];
}

function smServeConfigExport(bool $withKey): void
{
    $s = [];
    foreach (array_keys(smConfigKeys()) as $k) {
        if ($k === 'api_key' && !$withKey) continue;
        $s[$k] = smSetting($k);
    }
    smHeaders();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Content-Disposition: attachment; filename="suchmaschine-installation-' . date('Y-m-d') . '.json"');
    echo json_encode(['format' => 'suchmaschine-config', 'version' => 1, 'app' => SM_VERSION, 'exported' => date('c'), 'prefs' => $s],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Übernimmt eine Sicherung der Installation. Unbekanntes und Ungültiges wird übergangen. Liefert die Zahl der Werte. */
function smConfigImport(array $in): int
{
    $n = 0;
    foreach (smConfigKeys() as $k => $check) {
        if (!array_key_exists($k, $in)) continue;
        $v = $check($in[$k]);
        if ($v === null) continue;
        smSetSetting($k, $v);
        $n++;
    }
    return $n;
}
