<?php
/* Suchmaschine – Bangs (!w Berlin) und Linsen (Suche auf bestimmte Seiten eingeschränkt).
   Bangs kommen in drei Schichten: die große Liste von Kagi (über 10.000, MIT-Lizenz) plus ein paar deutsche Extras,
   darüber was der Administrator für alle anlegt, ändert oder löscht, ganz oben was jedes Konto für sich anlegt, ändert oder löscht.
   Die Liste liegt als app/lists/bangs.tsv.gz bei und wird (abschaltbar) einmal pro Woche frisch von GitHub geholt.
   Ob die Zielseiten erreichbar sind, prüft die Suchmaschine nach und nach im Hintergrund. Beides kostet keine Suchanfragen. */
defined('SM_VERSION') || exit;

const SM_MAX_BANGS = 500;        // eigene Bangs pro Konto (und für alle)
const SM_MAX_BANG_OFF = 20000;   // gelöschte (ausgeblendete) mitgelieferte
const SM_MAX_LENSES = 50;
const SM_LENS_SITES = 12;        // Brave nimmt höchstens 400 Zeichen pro Suche
const SM_BANG_SRC = 'https://raw.githubusercontent.com/kagisearch/bangs/main/data/bangs.json';
const SM_BANG_DEAD = 3;          // so oft hintereinander nicht erreichbar = „nicht erreichbar“

/* ---------- Bangs: Grundlagen ---------- */

/**
 * Deutsche Extras: Kürzel => [Name, Adresse mit {q}]. Sie gehen der Kagi-Liste vor (z. B. !a = amazon.de statt amazon.com).
 */
function smBuiltinBangs(): array
{
    $de = smLang() === 'de';
    return [
        'w'     => ['Wikipedia', $de ? 'https://de.wikipedia.org/w/index.php?search={q}' : 'https://en.wikipedia.org/w/index.php?search={q}'],
        'wde'   => ['Wikipedia (Deutsch)', 'https://de.wikipedia.org/w/index.php?search={q}'],
        'wen'   => ['Wikipedia (English)', 'https://en.wikipedia.org/w/index.php?search={q}'],
        'wikt'  => ['Wiktionary', $de ? 'https://de.wiktionary.org/w/index.php?search={q}' : 'https://en.wiktionary.org/w/index.php?search={q}'],
        'gh'    => ['GitHub', 'https://github.com/search?q={q}'],
        'yt'    => ['YouTube', 'https://www.youtube.com/results?search_query={q}'],
        'osm'   => ['OpenStreetMap', 'https://www.openstreetmap.org/search?query={q}'],
        'so'    => ['Stack Overflow', 'https://stackoverflow.com/search?q={q}'],
        'r'     => ['Reddit', 'https://www.reddit.com/search/?q={q}'],
        'mdn'   => ['MDN Web Docs', 'https://developer.mozilla.org/search?q={q}'],
        'php'   => ['PHP', 'https://www.php.net/search.php?pattern={q}'],
        'imdb'  => ['IMDb', 'https://www.imdb.com/find/?q={q}'],
        'leo'   => ['LEO', 'https://dict.leo.org/englisch-deutsch/{q}'],
        'dict'  => ['dict.cc', 'https://www.dict.cc/?s={q}'],
        'linguee' => ['Linguee', 'https://www.linguee.de/deutsch-englisch/search?query={q}'],
        'dl'    => ['DeepL', $de ? 'https://www.deepl.com/translator#auto/de/{q}' : 'https://www.deepl.com/translator#auto/en/{q}'],
        'duden' => ['Duden', 'https://www.duden.de/suchen/dudenonline/{q}'],
        'dwds'  => ['DWDS', 'https://www.dwds.de/?q={q}'],
        'ot'    => ['OpenThesaurus', 'https://www.openthesaurus.de/synonyme/{q}'],
        'a'     => ['Amazon', $de ? 'https://www.amazon.de/s?k={q}' : 'https://www.amazon.com/s?k={q}'],
        'ebay'  => ['eBay', $de ? 'https://www.ebay.de/sch/i.html?_nkw={q}' : 'https://www.ebay.com/sch/i.html?_nkw={q}'],
        'ka'    => ['Kleinanzeigen', 'https://www.kleinanzeigen.de/s-{q}/k0'],
        'idealo' => ['idealo', 'https://www.idealo.de/preisvergleich/MainSearchProductCategory.html?q={q}'],
        'gz'    => ['Geizhals', 'https://geizhals.de/?fs={q}'],
        'otto'  => ['OTTO', 'https://www.otto.de/suche/{q}/'],
        'ck'    => ['Chefkoch', 'https://www.chefkoch.de/rs/s0/{q}/Rezepte.html'],
        'heise' => ['heise online', 'https://www.heise.de/suche/?q={q}'],
        'spiegel' => ['DER SPIEGEL', 'https://www.spiegel.de/suche/?suchbegriff={q}'],
        'zeit'  => ['ZEIT ONLINE', 'https://www.zeit.de/suche/index?q={q}'],
        'ard'   => ['ARD Mediathek', 'https://www.ardmediathek.de/suche/{q}'],
        'zdf'   => ['ZDF', 'https://www.zdf.de/suche?q={q}'],
        'wa'    => ['Wolfram|Alpha', 'https://www.wolframalpha.com/input?i={q}'],
        'ia'    => ['Internet Archive', 'https://web.archive.org/web/*/{q}'],
        'ddg'   => ['DuckDuckGo', 'https://duckduckgo.com/?q={q}'],
        'sp'    => ['Startpage', 'https://www.startpage.com/do/search?q={q}'],
        'mj'    => ['Mojeek', 'https://www.mojeek.com/search?q={q}'],
        'eco'   => ['Ecosia', 'https://www.ecosia.org/search?q={q}'],
        'b'     => ['Brave Search', 'https://search.brave.com/search?q={q}'],
        'g'     => ['Google', 'https://www.google.com/search?q={q}'],
    ];
}

/** Eingebaute Bangs, die nur den Reiter wechseln (kosten auch keine Suche bis zur eigentlichen Suche) */
function smTabBangs(): array
{
    return ['i' => 'images', 'n' => 'news', 'v' => 'videos'];
}

/** Kürzel, die die Suchmaschine selbst braucht (Reiter) */
function smBangReserved(string $t): bool
{
    return isset(smTabBangs()[$t]);
}

/** Kürzel der Werkzeuge (!qr, !pw, !dns …): gehen der großen Liste vor, nur eigene Bangs gehen ihnen vor */
function smBangIsTool(string $t): bool
{
    static $c = [];
    if (!isset($c[$t])) $c[$t] = smToolBang($t) !== null;
    return $c[$t];
}

/** Kürzel säubern: Buchstaben (auch Umlaute), Ziffern, Punkt, Minus, Unterstrich, Plus, &; höchstens 24 Zeichen */
function smCleanBangTrigger($t): string
{
    $t = mb_strtolower(ltrim(trim(is_string($t) ? $t : ''), '!'));
    return preg_match('/^[\p{L}\p{N}][\p{L}\p{N}._+&-]{0,23}$/u', $t) ? $t : '';
}

/** Zieladresse: http(s) mit {q}, oder /search?q=… für eine Suche hier (wie bei Kagi für Bangs mit site:) */
function smCleanBangUrl($u): string
{
    $u = trim(is_string($u) ? $u : '');
    if (strlen($u) > 800 || preg_match('/[\x00-\x20"<>\\\\]/', $u)) return '';
    if (preg_match('~^/search\?q=~', $u)) return $u;
    if (!preg_match('~^https?://[^\s/?#]+~i', $u)) return '';
    return $u;
}

/** Domain einer Bang-Adresse (für die Anzeige und die Erreichbarkeit) */
function smBangHost(string $u): string
{
    if (strpos($u, '/search?') === 0) return '';
    return strtolower((string)parse_url(str_replace('{q}', 'x', $u), PHP_URL_HOST));
}

/** Eigene Bangs (eines Kontos oder für alle), sauber: [['t', 'n', 'u', 'e' => 1 wenn es ein mitgeliefertes ersetzt], …] */
function smCleanBangs($in): array
{
    $out = [];
    foreach (is_array($in) ? $in : [] as $b) {
        if (!is_array($b)) continue;
        $t = smCleanBangTrigger($b['t'] ?? '');
        $u = smCleanBangUrl($b['u'] ?? '');
        if ($t === '' || $u === '' || smBangReserved($t)) continue;
        $n = mb_substr(smPlain((string)($b['n'] ?? '')), 0, 60);
        $out[$t] = ['t' => $t, 'n' => $n !== '' ? $n : $t, 'u' => $u] + (!empty($b['e']) ? ['e' => 1] : []);
        if (count($out) >= SM_MAX_BANGS) break;
    }
    ksort($out, SORT_STRING);
    return array_values($out);
}

/** Gelöschte (ausgeblendete) Kürzel, sauber */
function smCleanBangOff($in): array
{
    $out = [];
    foreach (is_array($in) ? $in : [] as $t) {
        $t = smCleanBangTrigger($t);
        if ($t !== '') $out[$t] = true;
        if (count($out) >= SM_MAX_BANG_OFF) break;
    }
    return array_keys($out);
}

/** Was der Administrator für alle festgelegt hat: ['own' => eigene/geänderte, 'off' => gelöschte] */
function smBangGlobal(): array
{
    $g = json_decode(smSetting('bang_global'), true);
    return ['own' => smCleanBangs($g['own'] ?? []), 'off' => smCleanBangOff($g['off'] ?? [])];
}

function smBangSetGlobal(array $g): void
{
    smSetSetting('bang_global', json_encode(['own' => smCleanBangs($g['own'] ?? []), 'off' => smCleanBangOff($g['off'] ?? [])], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Die Schichten, wie sie für ein Konto gelten. $scope 'all' = die Sicht „für alle“ (ohne die eigenen des Kontos).
 * ['own' => [t => Bang], 'off' => [t => true], 'gown' => [t => Bang], 'goff' => [t => true]]
 */
function smBangLayers(?array $me, string $scope = 'mine'): array
{
    $g = smBangGlobal();
    $p = $scope === 'all' ? [] : ($me['prefs'] ?? []);
    $own = [];
    foreach (smCleanBangs($p['bangs'] ?? []) as $b) $own[$b['t']] = $b;
    $gown = [];
    foreach ($g['own'] as $b) $gown[$b['t']] = $b;
    return ['own' => $own, 'off' => array_flip(smCleanBangOff($p['bang_off'] ?? [])), 'gown' => $gown, 'goff' => array_flip($g['off'])];
}

/* ---------- Die große Liste (Kagi) ---------- */

function smBangFile(): string
{
    return SM_APP . '/lists/bangs.tsv.gz';
}

/**
 * Formatierung wie bei Kagi: b = ohne Suchbegriff zur Startseite, e = Suchbegriff kodieren, p = Leerzeichen als +,
 * s = ohne Suchbegriff zur Domain. Fehlt „fmt“, gelten b, e und p.
 */
function smBangFlags($fmt): string
{
    if (!is_array($fmt)) return 'bep';
    $m = ['open_base_path' => 'b', 'url_encode_placeholder' => 'e', 'url_encode_space_to_plus' => 'p', 'open_snap_domain' => 's'];
    $f = '';
    foreach ($m as $k => $c) if (in_array($k, $fmt, true)) $f .= $c;
    return $f;
}

/** Eine Zeile der Liste prüfen: ['t', 'a' => [Zweitkürzel], 'n', 'u', 'd', 'c', 'f', 'x'] oder null */
function smBangRowClean(array $r): ?array
{
    $t = smCleanBangTrigger($r['t'] ?? '');
    $u = str_replace('{{{s}}}', '{q}', (string)($r['u'] ?? ''));
    $u = smCleanBangUrl($u);
    if ($t === '' || $u === '' || strpos($u, '{q}') === false && empty($r['x'])) return null;
    $a = [];
    foreach ((array)($r['a'] ?? []) as $x) {
        $x = smCleanBangTrigger($x);
        if ($x !== '' && $x !== $t) $a[$x] = $x;
    }
    $n = mb_substr(trim(preg_replace('/\s+/u', ' ', smPlain((string)($r['n'] ?? '')))), 0, 80);
    $d = strtolower(trim((string)($r['d'] ?? '')));
    if (!preg_match('/^[a-z0-9.-]+\.[a-z0-9-]+$/', $d)) $d = smBangHost($u);
    $x = (string)($r['x'] ?? '');
    if ($x !== '' && (strlen($x) > 200 || @preg_match('~' . str_replace('~', '\~', $x) . '~u', '') === false)) return null;
    return ['t' => $t, 'a' => array_values($a), 'n' => $n !== '' ? $n : $t, 'u' => $u, 'd' => $d,
            'c' => mb_substr(smPlain((string)($r['c'] ?? '')), 0, 40), 'f' => preg_replace('/[^beps]/', '', (string)($r['f'] ?? 'bep')), 'x' => $x];
}

/** Kagis bangs.json lesen */
function smBangParseKagi(string $json): array
{
    $in = json_decode($json, true);
    $out = [];
    foreach (is_array($in) ? $in : [] as $b) {
        if (!is_array($b)) continue;
        $r = smBangRowClean(['t' => $b['t'] ?? '', 'a' => $b['ts'] ?? [], 'n' => $b['s'] ?? '', 'u' => $b['u'] ?? '', 'd' => $b['d'] ?? '',
                             'c' => $b['c'] ?? '', 'f' => smBangFlags($b['fmt'] ?? null), 'x' => $b['x'] ?? '']);
        if ($r) $out[$r['t']] = $r;
    }
    return array_values($out);
}

/** Die mitgelieferte Datei lesen (eine Zeile pro Bang, Felder mit Tab getrennt) */
function smBangParseTsv(string $tsv): array
{
    $out = [];
    foreach (explode("\n", $tsv) as $line) {
        if ($line === '' || $line[0] === '#') continue;
        $p = explode("\t", $line);
        if (count($p) < 8) continue;
        $r = smBangRowClean(['t' => $p[0], 'a' => $p[1] !== '' ? explode(',', $p[1]) : [], 'n' => $p[2], 'u' => $p[3], 'd' => $p[4], 'c' => $p[5], 'f' => $p[6], 'x' => $p[7]]);
        if ($r) $out[] = $r;
    }
    return $out;
}

/** Zeilen als Datei (für tools/bangs-import.php) */
function smBangToTsv(array $rows): string
{
    $s = "# Bangs aus https://github.com/kagisearch/bangs (MIT-Lizenz, Copyright (c) 2024 Kagi Search). Felder: Kürzel, Zweitkürzel, Name, Adresse, Domain, Bereich, Format, Muster\n";
    usort($rows, function ($a, $b) { return strcmp($a['t'], $b['t']); });
    foreach ($rows as $r) {
        $s .= implode("\t", array_map(function ($v) { return str_replace(["\t", "\n", "\r"], ' ', (string)$v); },
            [$r['t'], implode(',', $r['a']), $r['n'], $r['u'], $r['d'], $r['c'], $r['f'], $r['x']])) . "\n";
    }
    return $s;
}

/** Liste in die Datenbank schreiben (ersetzt die alte). Liefert [neu, weggefallen, gesamt]. */
function smBangStore(array $rows): array
{
    $pdo = smDb();
    $old = array_flip(smQ('SELECT t FROM bang_list WHERE t = main')->fetchAll(PDO::FETCH_COLUMN));
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM bang_list');
        $st = $pdo->prepare('INSERT OR IGNORE INTO bang_list (t, main, a, n, u, d, c, f, x) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $new = 0;
        foreach ($rows as $r) {
            $a = $r['a'] ? ',' . implode(',', $r['a']) . ',' : '';
            $st->execute([$r['t'], $r['t'], $a, $r['n'], $r['u'], $r['d'], $r['c'], $r['f'], $r['x']]);
            if (!isset($old[$r['t']])) $new++;
            unset($old[$r['t']]);
        }
        // Zweitkürzel zeigen auf das Hauptkürzel (Hauptkürzel gehen vor)
        foreach ($rows as $r) foreach ($r['a'] as $x) $st->execute([$x, $r['t'], '', $r['n'], $r['u'], $r['d'], $r['c'], $r['f'], $r['x']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return [$new, count($old), count($rows)];
}

/** Sorgt dafür, dass die Liste in der Datenbank steht (beim ersten Mal und nach einem Update mit neuerer Datei) */
function smBangSeed(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $file = smBangFile();
    if (!is_file($file)) return;
    $key = SM_VERSION . ':' . filesize($file);
    if (smSetting('bang_seed') === $key) return;
    $upd = json_decode(smSetting('bang_upd'), true);
    $fresh = is_array($upd) && empty($upd['err']) && ($upd['src'] ?? '') === 'online' && (int)($upd['at'] ?? 0) > time() - 8 * 86400;
    $count = (int)smQ('SELECT COUNT(*) FROM bang_list')->fetchColumn();
    if ($count === 0 || !$fresh) {
        $raw = @gzdecode((string)file_get_contents($file));
        if (!is_string($raw)) return;
        [$new, $gone, $n] = smBangStore(smBangParseTsv($raw));
        if (!$fresh) smSetSetting('bang_upd', json_encode(['at' => time(), 'n' => $n, 'add' => $count ? $new : 0, 'del' => $count ? $gone : 0, 'src' => 'file', 'err' => '']));
    }
    smSetSetting('bang_seed', $key);
}

/** Anzahl der Bangs in der großen Liste (ohne Zweitkürzel) */
function smBangSysCount(): int
{
    smBangSeed();
    return (int)smQ('SELECT COUNT(*) FROM bang_list WHERE t = main')->fetchColumn();
}

/** Eine Zeile aus Extras oder Datenbank in die einheitliche Form bringen */
function smBangRow(array $r, string $layer): array
{
    $u = (string)$r['u'];
    return ['t' => $r['t'], 'main' => $r['main'] ?? $r['t'], 'a' => array_values(array_filter(explode(',', (string)($r['a'] ?? '')))), 'n' => (string)$r['n'], 'u' => $u,
            'd' => (string)($r['d'] ?? '') !== '' ? (string)$r['d'] : smBangHost($u), 'c' => (string)($r['c'] ?? ''), 'f' => (string)($r['f'] ?? 'be'),
            'x' => (string)($r['x'] ?? ''), 'layer' => $layer, 'e' => !empty($r['e'])];
}

/** Ein mitgeliefertes Bang (Extras vor Kagi), ohne die Kürzel, die die Suchmaschine selbst braucht */
function smBangSys(string $t): ?array
{
    if ($t === '' || smBangReserved($t) || smBangIsTool($t)) return null;
    $ex = smBuiltinBangs();
    if (isset($ex[$t])) return smBangRow(['t' => $t, 'n' => $ex[$t][0], 'u' => $ex[$t][1], 'c' => 'Extra'], 'sys');
    smBangSeed();
    $r = smQ('SELECT * FROM bang_list WHERE t = ?', [$t])->fetch();
    if (!$r || isset($ex[$r['main']])) return null;
    if ($r['main'] !== $t) {
        $m = smQ('SELECT * FROM bang_list WHERE t = ?', [$r['main']])->fetch();
        if ($m) $r = ['t' => $t] + $m;
    }
    return smBangRow($r, 'sys');
}

/** Das Bang, das für dieses Konto unter einem Kürzel gilt (null = keins oder gelöscht) */
function smBangFind(string $t, ?array $me, string $scope = 'mine', ?array $L = null): ?array
{
    $L = $L ?? smBangLayers($me, $scope);
    if (isset($L['own'][$t])) return smBangRow($L['own'][$t], 'own');
    if (isset($L['off'][$t])) return null;
    if (isset($L['gown'][$t])) return smBangRow($L['gown'][$t], 'global');
    if (isset($L['goff'][$t])) return null;
    $s = smBangSys($t);
    if ($s && (isset($L['off'][$s['main']]) || isset($L['goff'][$s['main']]))) return null;
    return $s;
}

/** Ein Bang mit Suchbegriff zur Zieladresse machen: ['to', 'internal'] */
function smBangUrl(array $b, string $rest): array
{
    $u = $b['u'];
    $f = $b['layer'] === 'sys' && $b['c'] !== 'Extra' ? $b['f'] : 'be';
    $enc = function (string $s) use ($f): string {
        if (strpos($f, 'e') !== false) return strpos($f, 'p') !== false ? urlencode($s) : rawurlencode($s);
        return strpos($f, 'p') !== false ? str_replace(' ', '+', $s) : $s;
    };
    if ($rest === '') {
        if (strpos($u, '/search?') === 0) return ['to' => smBase(), 'internal' => true];
        $p = parse_url(str_replace('{q}', '', $u));
        if (strpos($f, 's') !== false && $b['d'] !== '') return ['to' => 'https://' . $b['d'] . '/', 'internal' => false];
        if (strpos($f, 'b') !== false || $b['x'] !== '') {
            return ['to' => ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '') . '/', 'internal' => false];
        }
        $url = str_replace('{q}', '', $u);
    } elseif ($b['x'] !== '' && preg_match('~' . str_replace('~', '\~', $b['x']) . '~u', $rest, $m)) {
        $url = preg_replace_callback('/\$(\d)/', function ($x) use ($m, $enc) { return $enc((string)($m[(int)$x[1]] ?? '')); }, $u);
    } else {
        $url = str_replace('{q}', $enc($rest), $u);
    }
    if (strpos($url, '/search?') === 0) {
        parse_str((string)parse_url($url, PHP_URL_QUERY), $qs);
        return ['to' => smUrl(['q' => mb_substr(trim((string)($qs['q'] ?? '')), 0, 400)]), 'internal' => true];
    }
    return ['to' => $url, 'internal' => false];
}

/**
 * Die bekannten Bangs zum Anzeigen (Hilfe, leere Suche): eigene, für alle und die Extras. Kürzel => [Name, Adresse, eigenes?]
 */
function smBangs(?array $me): array
{
    $L = smBangLayers($me);
    $all = [];
    foreach (smBuiltinBangs() as $t => $b) {
        if (!isset($L['off'][$t]) && !isset($L['goff'][$t]) && !smBangReserved($t) && !smBangIsTool($t)) $all[$t] = [$b[0], $b[1], false];
    }
    foreach ($L['gown'] as $t => $b) if (!isset($L['off'][$t])) $all[$t] = [$b['n'], $b['u'], false];
    foreach ($L['own'] as $t => $b) $all[$t] = [$b['n'], $b['u'], true];
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
    foreach (array_unique([0, count($words) - 1]) as $pos) {
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
        $b = smBangFind($t, $me);
        // Werkzeuge: „!qr Hallo“, „!pw“, „!kurz https://…“, „!json {…}“, „!dns example.org“ (eigene Bangs gleichen Namens gehen vor)
        if ((!$b || $b['layer'] === 'sys') && ($tb = smToolBang($t)) !== null) {
            return ['to' => smToolUrl($tb[0], $rest !== '' && $tb[1] !== '' ? [$tb[1] => mb_substr($rest, 0, 2000)] : []), 'internal' => true];
        }
        if (!$b) continue;
        return smBangUrl($b, $rest);
    }
    return null;
}

/* ---------- Bangs suchen (Einstellungen und Vorschläge im Suchfeld) ---------- */

/**
 * Bangs suchen. $filter: '' = alle aktiven, 'own' = eigene und geänderte, 'off' = gelöschte, 'dead' = nicht erreichbare.
 * Liefert ['rows' => [...], 'more' => bool]. Jede Zeile wie smBangRow plus 'hidden', 'edited', 'dead'.
 */
function smBangSearch(string $q, ?array $me, string $scope = 'mine', string $filter = '', int $limit = 50): array
{
    smBangSeed();
    $L = smBangLayers($me, $scope);
    $q = mb_strtolower(trim(ltrim(trim($q), '!')));
    $ex = smBuiltinBangs();
    $match = function (array $r) use ($q): ?int {
        if ($q === '') return 9;
        if ($r['t'] === $q) return 0;
        if (strpos($r['t'], $q) === 0) return 1;
        foreach ($r['a'] as $a) if (strpos($a, $q) === 0) return 2;
        $n = mb_strtolower($r['n']);
        // Eigene, für alle und die Extras sind die bekannten: beim Namen genauso gut wie ein passendes Kürzel (!yo → YouTube)
        if (strpos($n, $q) === 0) return $r['layer'] !== 'sys' || $r['c'] === 'Extra' ? 1 : 3;
        if (strpos($n, $q) !== false) return 4;
        if (strpos($r['d'], $q) !== false) return 5;
        return null;
    };
    $cand = [];   // t => Zeile, obere Schicht gewinnt
    $add = function (array $r) use (&$cand) { if (!isset($cand[$r['t']])) $cand[$r['t']] = $r; };
    foreach ($L['own'] as $b) $add(smBangRow($b, 'own'));
    foreach ($L['gown'] as $b) $add(smBangRow($b, 'global'));
    foreach ($ex as $t => $b) if (!smBangReserved($t) && !smBangIsTool($t)) $add(smBangRow(['t' => $t, 'n' => $b[0], 'u' => $b[1], 'c' => 'Extra'], 'sys'));
    // Aus der großen Liste: passende Hauptkürzel, gelöschte oder nicht erreichbare
    $sql = 'SELECT * FROM bang_list WHERE t = main';
    $args = [];
    if ($filter === 'off') {
        $offs = array_keys($L['off'] + $L['goff']);
        foreach (array_chunk($offs, 400) as $chunk) {
            foreach (smQ($sql . ' AND t IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')', $chunk)->fetchAll() as $r) $add(smBangRow($r, 'sys'));
        }
    } elseif ($filter === 'dead') {
        foreach (smQ($sql . ' AND d IN (SELECT d FROM bang_check WHERE fails >= ?) ORDER BY t LIMIT 1000', [SM_BANG_DEAD])->fetchAll() as $r) $add(smBangRow($r, 'sys'));
    } elseif ($filter === '' && $q !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $pre = substr($like, 1);
        $sql .= " AND (t LIKE ? ESCAPE '\\' OR a LIKE ? ESCAPE '\\' OR n LIKE ? ESCAPE '\\' OR d LIKE ? ESCAPE '\\')
                  ORDER BY (t = ?) DESC, (t LIKE ? ESCAPE '\\') DESC, (n LIKE ? ESCAPE '\\') DESC, length(t), t LIMIT 600";
        foreach (smQ($sql, [$pre, $like, $like, $like, $q, $pre, $pre])->fetchAll() as $r) $add(smBangRow($r, 'sys'));
    }
    // Geändert = eigenes Bang, das ein darunterliegendes ersetzt
    $lower = function (array $r) use ($L, $ex): bool {
        if ($r['layer'] === 'own') return isset($L['gown'][$r['t']]) || smBangSys($r['t']) !== null;
        if ($r['layer'] === 'global') return smBangSys($r['t']) !== null;
        return false;
    };
    $rows = [];
    foreach ($cand as $t => $r) {
        $score = $match($r);
        if ($score === null) continue;
        $hidden = $r['layer'] === 'own' ? false
            : ($r['layer'] === 'global' ? isset($L['off'][$t]) : (isset($L['off'][$r['main']]) || isset($L['goff'][$r['main']])));
        $own = $r['layer'] === ($scope === 'all' ? 'global' : 'own');
        if ($filter === 'off' && !$hidden) continue;
        if ($filter !== 'off' && $hidden) continue;
        if ($filter === 'own' && !$own) continue;
        if ($filter === '' && $q === '' && $r['layer'] === 'sys' && $r['c'] !== 'Extra') continue;   // ohne Suchbegriff: nur eigene, für alle und die Extras
        $r['hidden'] = $hidden;
        $r['edited'] = $own && $lower($r);
        $r['dead'] = false;
        $r['score'] = $score;
        $rows[] = $r;
    }
    $rank = function (array $r): int { return $r['layer'] === 'own' ? 0 : ($r['layer'] === 'global' ? 1 : ($r['c'] === 'Extra' ? 2 : 3)); };
    usort($rows, function ($a, $b) use ($rank) {
        return [$a['score'], $rank($a), mb_strlen($a['t']), $a['t']] <=> [$b['score'], $rank($b), mb_strlen($b['t']), $b['t']];
    });
    // „Nicht erreichbar“: nur Bangs, deren Domain wirklich mehrmals nicht geantwortet hat.
    // (Eigene, für alle und die Extras stehen immer in der Kandidatenliste und müssen hier genauso geprüft werden.)
    if ($filter === 'dead') {
        $dead = [];
        foreach (array_chunk(array_values(array_unique(array_filter(array_column($rows, 'd')))), 400) as $chunk) {
            foreach (smQ('SELECT d FROM bang_check WHERE fails >= ? AND d IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')', array_merge([SM_BANG_DEAD], $chunk))->fetchAll(PDO::FETCH_COLUMN) as $d) $dead[$d] = true;
        }
        $rows = array_values(array_filter($rows, function ($r) use ($dead) { return isset($dead[$r['d']]); }));
    }
    $more = count($rows) > $limit;
    $rows = array_slice($rows, 0, $limit);
    // Erreichbarkeit dazuschreiben
    $ds = array_values(array_unique(array_filter(array_column($rows, 'd'))));
    if ($ds) {
        $dead = array_flip(smQ('SELECT d FROM bang_check WHERE fails >= ? AND d IN (' . implode(',', array_fill(0, count($ds), '?')) . ')', array_merge([SM_BANG_DEAD], $ds))->fetchAll(PDO::FETCH_COLUMN));
        foreach ($rows as $i => $r) $rows[$i]['dead'] = isset($dead[$r['d']]);
    }
    return ['rows' => $rows, 'more' => $more];
}

/* ---------- Bangs ändern (Einstellungen): für das eigene Konto oder, als Administrator, für alle ---------- */

/**
 * Eine Änderung an einer Schicht ['own' => [...], 'off' => [...]]. $lower(t) sagt, ob es das Kürzel darunter gibt.
 * Liefert [neue Schicht, Fehler, Erfolgsmeldung].
 */
function smBangEdit(array $layer, string $do, array $in, callable $lower): array
{
    $own = [];
    foreach (smCleanBangs($layer['own'] ?? []) as $b) $own[$b['t']] = $b;
    $off = array_flip(smCleanBangOff($layer['off'] ?? []));
    $t = smCleanBangTrigger($in['t'] ?? '');
    $err = '';
    $ok = t('Gespeichert.');
    switch ($do) {
        case 'bang_save':
            $u = smCleanBangUrl($in['u'] ?? '');
            $orig = smCleanBangTrigger($in['orig'] ?? '');
            if ($t === '') return [$layer, t('Das Kürzel darf nur Buchstaben, Ziffern, Punkt, Minus, Plus und Unterstrich enthalten (höchstens 24 Zeichen).'), ''];
            if (smBangReserved($t)) return [$layer, t('Das Kürzel !%s ist für die Reiter oder Suchdienste reserviert.', $t), ''];
            if ($u === '') return [$layer, t('Die Adresse muss mit https:// oder http:// beginnen. {q} steht für den Suchbegriff.'), ''];
            if (!isset($own[$t]) && count($own) >= SM_MAX_BANGS) return [$layer, t('Es sind schon zu viele eigene Bangs.'), ''];
            // Kürzel beim Bearbeiten geändert: das alte verschwindet (eigenes gelöscht, mitgeliefertes ausgeblendet)
            // „geändert“ (e) heißt: ersetzt ein mitgeliefertes und fliegt beim Wiederherstellen raus. Neu angelegte bleiben immer.
            if ($orig !== '' && $orig === $t) {
                $e = isset($own[$t]) ? !empty($own[$t]['e']) : $lower($t);
            } else {
                $e = !empty($own[$t]['e']);
                if ($orig !== '') {
                    unset($own[$orig]);
                    if ($lower($orig)) $off[$orig] = true;
                }
            }
            $own[$t] = ['t' => $t, 'n' => (string)($in['n'] ?? ''), 'u' => $u] + ($e ? ['e' => 1] : []);
            unset($off[$t]);
            $ok = t('Bang !%s gespeichert.', $t);
            break;
        case 'bang_del':
            if ($t === '') break;
            if (isset($own[$t])) {
                $wasEdit = !empty($own[$t]['e']);
                unset($own[$t]);
                if ($wasEdit && $lower($t)) $off[$t] = true;
            } elseif ($lower($t)) {
                $off[$t] = true;
            }
            $ok = t('Bang !%s gelöscht.', $t);
            break;
        case 'bang_revert':
            unset($own[$t]);
            $ok = t('Bang !%s ist wieder im Original.', $t);
            break;
        case 'bang_unhide':
            unset($off[$t]);
            $ok = t('Bang !%s ist wieder da.', $t);
            break;
        case 'bang_restore':
            $n = count($off);
            $m = 0;
            foreach ($own as $k => $b) if (!empty($b['e'])) { unset($own[$k]); $m++; }
            $off = [];
            $ok = t('Grundzustand wiederhergestellt: %d gelöschte zurückgeholt, %d geänderte zurückgesetzt. Selbst angelegte bleiben.', $n, $m);
            break;
        default:
            $err = t('Unbekannte Aktion.');
    }
    return [['own' => array_values($own), 'off' => array_keys($off)], $err, $ok];
}

/* ---------- Bangs im Hintergrund: Liste aktualisieren, Erreichbarkeit prüfen ---------- */

/** Woher die Liste kommt. In data/config.php schaltet 'bangs_url' => '' das Nachladen ab. */
function smBangSrcUrl(): string
{
    $cfg = smConfig();
    return array_key_exists('bangs_url', $cfg) ? (string)$cfg['bangs_url'] : SM_BANG_SRC;
}

/** Liste frisch holen. Liefert '' oder eine Fehlermeldung. */
function smBangRefresh(): string
{
    $url = smBangSrcUrl();
    if ($url === '') return t('Das Nachladen der Bang-Liste ist in data/config.php abgeschaltet.');
    smSetSetting('bang_upd_try', (string)time());
    try {
        $rows = smBangParseKagi(smHttpGet($url, 16000000));
        // Eine kaputte oder halbe Datei soll die gute Liste nicht ersetzen
        if (count($rows) < 1000) throw new RuntimeException(t('Die heruntergeladene Liste war unvollständig.'));
        smBangSeed();
        [$new, $gone, $n] = smBangStore($rows);
        smSetSetting('bang_upd', json_encode(['at' => time(), 'n' => $n, 'add' => $new, 'del' => $gone, 'src' => 'online', 'err' => '']));
        return '';
    } catch (Throwable $e) {
        $u = json_decode(smSetting('bang_upd'), true);
        $u = is_array($u) ? $u : [];
        $u['err'] = $e->getMessage();
        $u['err_at'] = time();
        smSetSetting('bang_upd', json_encode($u));
        return $e->getMessage();
    }
}

/** Ein paar Domains auf Erreichbarkeit prüfen (die am längsten nicht geprüften zuerst, frisch ausgefallene bald noch einmal) */
function smBangCheck(int $n = 25): array
{
    smBangSeed();
    $now = time();
    $ds = smQ("SELECT l.d FROM (SELECT DISTINCT d FROM bang_list WHERE t = main AND d <> '') l LEFT JOIN bang_check c ON c.d = l.d
               ORDER BY CASE WHEN c.fails > 0 AND c.checked < ? THEN 0 ELSE 1 END, COALESCE(c.checked, 0), l.d LIMIT ?", [$now - 6 * 3600, $n])->fetchAll(PDO::FETCH_COLUMN);
    if (!$ds) return [0, 0];
    $res = smNetCheckMany(array_map(function ($d) { return 'https://' . $d . '/'; }, $ds), 8);
    $bad = 0;
    foreach ($ds as $i => $d) {
        $r = $res[$i] ?? ['status' => 0, 'error' => 'connect'];
        $st = (int)$r['status'];
        if ($st > 0 || in_array($r['error'], ['blocked', 'self', 'port', 'scheme'], true)) {
            smQ('INSERT INTO bang_check (d, checked, fails, status, err, ok_at) VALUES (?, ?, 0, ?, \'\', ?)
                 ON CONFLICT(d) DO UPDATE SET checked = excluded.checked, fails = 0, status = excluded.status, err = \'\', ok_at = excluded.ok_at', [$d, $now, $st, $now]);
        } else {
            $bad++;
            smQ('INSERT INTO bang_check (d, checked, fails, status, err, ok_at) VALUES (?, ?, 1, 0, ?, 0)
                 ON CONFLICT(d) DO UPDATE SET checked = excluded.checked, fails = fails + 1, status = 0, err = excluded.err', [$d, $now, (string)$r['error']]);
        }
    }
    return [count($ds), $bad];
}

/** Steht Hintergrundarbeit an? (dann holt die Seite per JavaScript ?a=bangtick ab, ohne dass jemand darauf wartet) */
function smBangDue(): bool
{
    if ((int)smSetting('bang_tick_at') > time() - 600) return false;
    return smBangRefreshDue() || smSetting('bang_check') === '1';
}

function smBangRefreshDue(): bool
{
    if (smSetting('bang_auto') !== '1' || smBangSrcUrl() === '') return false;
    $u = json_decode(smSetting('bang_upd'), true);
    $at = is_array($u) && ($u['src'] ?? '') === 'online' ? (int)$u['at'] : 0;
    return $at < time() - 7 * 86400 && (int)smSetting('bang_upd_try') < time() - 86400;
}

/** ?a=bangtick: eine Portion Hintergrundarbeit, höchstens alle 10 Minuten */
function smServeBangTick(): void
{
    smHeaders();
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    if (!smBangDue()) exit('0');
    smSetSetting('bang_tick_at', (string)time());
    ignore_user_abort(true);
    @set_time_limit(120);
    if (smBangRefreshDue()) {
        smBangRefresh();
        exit('1');
    }
    if (smSetting('bang_check') === '1') smBangCheck();
    exit('1');
}

/** Zahlen für die Übersicht: [geprüfte Domains, alle Domains, nicht erreichbare] */
function smBangCheckStats(): array
{
    smBangSeed();
    $all = (int)smQ("SELECT COUNT(DISTINCT d) FROM bang_list WHERE t = main AND d <> ''")->fetchColumn();
    $done = (int)smQ('SELECT COUNT(*) FROM bang_check')->fetchColumn();
    $dead = (int)smQ('SELECT COUNT(*) FROM bang_check WHERE fails >= ?', [SM_BANG_DEAD])->fetchColumn();
    return [min($done, $all), $all, $dead];
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

/** Abkürzung für ein Werkzeug: [Werkzeug, Name des Feldes für den Rest der Suche] oder null */
function smToolBang(string $t): ?array
{
    $alias = ['qr' => ['qr', 'text'], 'scan' => ['scanner', ''], 'scanner' => ['scanner', ''], 'pw' => ['passwort', ''], 'passwort' => ['passwort', ''], 'password' => ['passwort', ''],
        'kurz' => ['kurzlinks', 'url'], 'short' => ['kurzlinks', 'url'], 'notiz' => ['schnipsel', 'text'], 'note' => ['schnipsel', 'text'], 'tools' => ['', ''], 'werkzeuge' => ['', ''],
        'calc' => ['rechner', 'x'], 'rechner' => ['rechner', 'x'], 'mwst' => ['prozent', ''], 'vat' => ['prozent', ''], 'timer' => ['timer', 'min'], 'stoppuhr' => ['timer', ''],
        'wetter' => ['wetter', 'ort'], 'weather' => ['wetter', 'ort'], 'ip' => ['ip', ''], 'iban' => ['nummern', 'q'], 'ean' => ['nummern', 'q'], 'isbn' => ['nummern', 'q'],
        'farbe' => ['farben', ''], 'color' => ['farben', ''], 'ts' => ['zeitstempel', ''], 'unix' => ['zeitstempel', ''], 'b64' => ['kodieren', ''], 'base64' => ['kodieren', ''],
        'tz' => ['weltzeit', ''], 'zeitzone' => ['weltzeit', '']];
    if (isset($alias[$t])) return $alias[$t][0] === '' ? ['', ''] : $alias[$t];
    $info = smToolInfo($t);
    if ($info === null) return null;
    $field = in_array($t, ['json', 'jwt', 'cron', 'linkcheck'], true) ? 'text' : (in_array($t, ['down', 'header', 'ssl', 'dns', 'whois', 'vorschau'], true) ? 'q' : '');
    return [$t, $field];
}


/* ---------- Vorschläge im Suchfeld ---------- */

/**
 * ?a=bangs&q=…: passende Bangs für das Suchfeld (JSON). 'exact' = wohin das Kürzel genau führt (auch Reiter, Suchdienste, Werkzeuge),
 * 'list' = Vorschläge zum Vervollständigen.
 */
function smServeBangSuggest(?array $me): void
{
    $q = mb_substr(trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''), 0, 40);
    $t = smCleanBangTrigger($q);
    $item = function (array $b): array {
        return ['t' => $b['t'], 'n' => $b['n'], 'd' => $b['d'] !== '' ? $b['d'] : t('Suche hier'), 'dead' => !empty($b['dead'])];
    };
    $exact = null;
    if ($t !== '') {
        if (isset(smTabBangs()[$t])) {
            $exact = ['t' => $t, 'n' => ['images' => t('Bilder'), 'news' => t('News'), 'videos' => t('Videos')][smTabBangs()[$t]], 'd' => t('Reiter hier'), 'dead' => false];
        } else {
            $b = smBangFind($t, $me);
            if ((!$b || $b['layer'] === 'sys') && ($tb = smToolBang($t)) !== null) {
                $info = $tb[0] !== '' ? smToolInfo($tb[0]) : null;
                $exact = ['t' => $t, 'n' => $info ? (string)$info[2] : t('Werkzeuge'), 'd' => t('Werkzeug hier'), 'dead' => false];
            } elseif ($b) {
                $dead = $b['d'] !== '' && (int)smQ('SELECT fails FROM bang_check WHERE d = ?', [$b['d']])->fetchColumn() >= SM_BANG_DEAD;
                $exact = $item($b + ['dead' => $dead]);
            }
        }
    }
    $list = [];
    if ($exact) $list[] = $exact;
    foreach (smBangSearch($q, $me, 'mine', '', 8)['rows'] as $b) {
        if ($exact && $b['t'] === $exact['t']) continue;
        $list[] = $item($b);
    }
    smHeaders();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, max-age=60');
    echo json_encode(['exact' => $exact, 'list' => array_slice($list, 0, 8)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
