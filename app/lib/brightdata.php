<?php
/* Suchmaschine – Google-Ergebnisse über die SERP API von Bright Data (brightdata.com).
   Bright Data ruft die Google-Suche auf und schickt die Seite fertig zerlegt als JSON zurück (brd_json=1).
   Damit gibt es Web, Bilder, News und Videos, dazu Googles eigene Filter (tbs), Infobox, Öffnungszeiten,
   Spielstände, Kurse und die KI-Übersicht. Kostet 1 Anfrage pro Seite, im kostenlosen Plan 5.000 im Monat.
   Google packt viele Links in eigene Weiterleitungen (google.com/goto?url=…). Die löst der Server selbst auf,
   damit Besucher direkt auf der Zielseite landen und Google nicht sieht, was angeklickt wird. */
defined('SM_VERSION') || exit;

const SM_BD_API = 'https://api.brightdata.com/';

/* ---------- Filter, die nur Google kann ---------- */

/** Bildfarbe: Wert in der Adresse => Bezeichnung */
function smImageColors(): array
{
    return ['' => t('Alle Farben'), 'color' => t('Farbig'), 'gray' => t('Schwarzweiß'), 'trans' => t('Transparent'),
            'red' => t('Rot'), 'orange' => t('Orange'), 'yellow' => t('Gelb'), 'green' => t('Grün'), 'teal' => t('Türkis'),
            'blue' => t('Blau'), 'purple' => t('Lila'), 'pink' => t('Rosa'), 'white' => t('Weiß'), 'black' => t('Schwarz'), 'brown' => t('Braun')];
}

/** Bildtyp */
function smImageTypes(): array
{
    return ['' => t('Alle Typen'), 'photo' => t('Foto'), 'clipart' => t('Clipart'), 'lineart' => t('Strichzeichnung'), 'animated' => t('Animiert (GIF)'), 'face' => t('Gesicht')];
}

/** Nutzungsrechte */
function smImageLicenses(): array
{
    return ['' => t('Alle Lizenzen'), 'cl' => t('Creative Commons'), 'ol' => t('Kommerzielle und andere Lizenzen')];
}

/** Videolänge: Wert => [Bezeichnung, von Sekunden, bis Sekunden] */
function smVideoDurations(): array
{
    return ['' => [t('Beliebige Länge'), 0, 0], 's' => [t('Kurz (unter 4 Minuten)'), 0, 240], 'm' => [t('Mittel (4 bis 20 Minuten)'), 240, 1200], 'l' => [t('Lang (über 20 Minuten)'), 1200, 0]];
}

/** Sprachcodes der Einstellungen => Googles lr-Werte */
function smBdLang(string $code): string
{
    $m = ['jp' => 'ja', 'zh-hans' => 'zh-CN', 'zh-hant' => 'zh-TW', 'pt-br' => 'pt', 'pt-pt' => 'pt', 'en-gb' => 'en', 'nb' => 'no', 'ms' => 'ms', 'he' => 'iw'];
    return $m[$code] ?? (preg_match('/^[a-z]{2}$/', $code) ? $code : '');
}

/** Googles Zusatzfilter (tbs) aus den Suchparametern */
function smBdTbs(array $p): string
{
    $tbs = [];
    $older = (int)($p['older'] ?? 0);
    if (($p['from'] ?? '') !== '' || ($p['to'] ?? '') !== '') {
        // Eigener Zeitraum: Google erwartet M/T/JJJJ
        $md = function (string $d): string { return $d === '' ? '' : date('n/j/Y', (int)strtotime($d)); };
        $tbs[] = 'cdr:1,cd_min:' . $md((string)$p['from']) . ',cd_max:' . $md((string)$p['to']);
    } elseif ($older > 0) {
        // Nächste Zeitstufe ohne die schon gezeigte: bis vor $older Tagen, ab dem Anfang der Stufe (bei „älter als ein Jahr“ ohne Anfang)
        $days = ['pd' => 1, 'pw' => 7, 'pm' => 31, 'py' => 365][$p['fresh']] ?? 0;
        $hour = intdiv(time(), 3600) * 3600;
        $tbs[] = 'cdr:1,cd_min:' . ($days > 0 ? date('n/j/Y', $hour - max($days, $older + 1) * 86400) : '') . ',cd_max:' . date('n/j/Y', $hour - $older * 86400);
    } elseif (($q = ['ph' => 'h', 'pd' => 'd', 'pw' => 'w', 'pm' => 'm', 'py' => 'y'][$p['fresh']] ?? '') !== '') {
        $tbs[] = 'qdr:' . $q;
    }
    if (!empty($p['verb'])) $tbs[] = 'li:1';
    if ($p['kind'] === 'images') {
        $im = (array)($p['img'] ?? []);
        $size = ['xl' => 'isz:lt,islt:2mp', 'large' => 'isz:l', 'medium' => 'isz:m', 'small' => 'isz:i'][$im['is'] ?? ''] ?? '';
        // Beste Qualität zuerst: Stufen ab 4 MP, ab 2 MP, groß, alle
        if ($size === '') $size = ['q4' => 'isz:lt,islt:4mp', 'q2' => 'isz:lt,islt:2mp', 'ql' => 'isz:l'][$im['iq'] ?? ''] ?? '';
        if ($size !== '') $tbs[] = $size;
        if (($ar = ['land' => 'w', 'port' => 't', 'square' => 's'][$im['io'] ?? ''] ?? '') !== '') $tbs[] = 'iar:' . $ar;
        $ic = (string)($im['ic'] ?? '');
        if (in_array($ic, ['color', 'gray', 'trans'], true)) $tbs[] = 'ic:' . $ic;
        elseif ($ic !== '' && isset(smImageColors()[$ic])) $tbs[] = 'ic:specific,isc:' . $ic;
        if (($it = (string)($im['it'] ?? '')) !== '' && isset(smImageTypes()[$it])) $tbs[] = 'itp:' . $it;
        if (($il = (string)($im['il'] ?? '')) !== '' && isset(smImageLicenses()[$il])) $tbs[] = 'il:' . $il;
    }
    if ($p['kind'] === 'videos' && in_array($p['vdur'] ?? '', ['s', 'm', 'l'], true)) $tbs[] = 'dur:' . $p['vdur'];
    return implode(',', $tbs);
}

/** Suchbegriff für Google: Braves Schreibweise der Operatoren angleichen (NOT → -, lang: → lr) */
function smBdQuery(string $q): array
{
    $lr = '';
    if (preg_match('/(?:^|\s)lang:([a-z]{2})(?=\s|$)/i', $q, $m)) {
        $lr = strtolower($m[1]);
        $q = trim(preg_replace('/(?:^|\s)lang:[a-z]{2}(?=\s|$)/i', ' ', $q));
    }
    $q = preg_replace('/(?:^|\s)NOT\s+(?=\S)/', ' -', $q);
    $q = preg_replace('/(?:^|\s)loc:[a-z]{2}(?=\s|$)/i', ' ', (string)$q);
    return [trim(preg_replace('/\s+/', ' ', (string)$q)), $lr];
}

/** Die Google-Adresse, die Bright Data abrufen soll */
function smBdGoogleUrl(array $p): string
{
    [$q, $lr] = smBdQuery((string)$p['q']);
    $g = ['q' => $q, 'hl' => $p['ui'] === 'de' ? 'de' : 'en'];
    if ($p['country'] !== '' && $p['country'] !== 'ALL') $g['gl'] = strtolower($p['country']);
    $lang = ($p['lr'] ?? '') !== '' ? smBdLang((string)$p['lr']) : smBdLang($lr);
    if ($lang !== '') $g['lr'] = 'lang_' . $lang;
    $tbm = ['images' => 'isch', 'news' => 'nws', 'videos' => 'vid'][$p['kind']] ?? '';
    if ($tbm !== '') $g['tbm'] = $tbm;
    if ($p['kind'] !== 'images' && (int)$p['page'] > 1) $g['start'] = ((int)$p['page'] - 1) * 10;
    if (($tbs = smBdTbs($p)) !== '') {
        $g['tbs'] = $tbs;
        // Mit Zeitfilter zeigt Google sonst eine andere Seitenart, die sich nicht zerlegen lässt: dann nur den Reiter „Web“
        if ($p['kind'] === 'web') $g['udm'] = '14';
    }
    if ($p['safe'] === 'strict') $g['safe'] = 'active';
    elseif ($p['safe'] === 'off') $g['safe'] = 'off';
    if (!empty($p['exact']) || !empty($p['verb'])) $g['nfpr'] = '1';
    // Zweiter Versuch nach einem Aussetzer: eine leicht andere Adresse, sonst sperrt Bright Data dieselbe Suche 15 Sekunden
    if (!empty($p['retry'])) $g['pws'] = '0';
    // Mit dem Seitenquelltext (für die Sport-Karte, die Bright Data nicht selbst zerlegt)
    $g['brd_json'] = !empty($p['html']) ? 'html' : '1';
    return 'https://www.google.com/search?' . http_build_query($g);
}

/** Zone der SERP API (so heißt die API in Bright Data, z. B. serp_api1) */
function smBdZone(): string
{
    $z = trim(smProvGet('brightdata', 'zone'));
    return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $z) ? $z : '';
}

/** Zone des Web Unlockers (für den Lesemodus), '' = nicht eingerichtet */
function smBdUnlockerZone(): string
{
    $z = trim(smProvGet('brightdata', 'unlocker'));
    return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $z) ? $z : '';
}

/** Anfrage an Bright Data (eine Google-Seite) */
function smBdRequest(array $p, string $key): array
{
    return ['url' => smProvBase('brightdata', SM_BD_API) . 'request', 'method' => 'POST',
            'headers' => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $key],
            'body' => json_encode(['zone' => smBdZone(), 'url' => smBdGoogleUrl($p), 'format' => 'raw'] + smBdCountry($p))];
}

/** Aus welchem Land Bright Data Google abruft: das Land aus den Sucheinstellungen (Region), sonst irgendwo */
function smBdCountry(array $p): array
{
    $c = strtolower((string)($p['country'] ?? ''));
    return preg_match('/^[a-z]{2}$/', $c) ? ['country' => $c] : [];
}

/** Holt eine fremde Seite über den Web Unlocker (Lesemodus). Liefert [HTML oder null, Fehlertext]. */
function smBdUnlock(string $url): array
{
    $zone = smBdUnlockerZone();
    $key = trim(smProvGet('brightdata', 'key'));
    if ($zone === '' || $key === '') return [null, t('Für den Lesemodus ist keine Web-Unlocker-Zone eingetragen.')];
    $r = smHttpMulti(['x' => ['url' => smProvBase('brightdata', SM_BD_API) . 'request', 'method' => 'POST',
        'headers' => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
        'body' => json_encode(['zone' => $zone, 'url' => $url, 'format' => 'raw'])]], 45)['x'];
    if ($r['status'] === 200 && trim($r['body']) !== '') return [$r['body'], ''];
    return [null, smProvErrorText('brightdata', $r, null)[0]];
}

/* ---------- Googles Weiterleitungen auflösen ---------- */

/** Ist das eine Google-Weiterleitung (google.com/goto?url=…, google.de/url?q=…)? */
function smBdIsRedirect(string $u): bool
{
    return (bool)preg_match('~^https://(www\.)?google\.[a-z.]{2,8}/(goto|url)\?~i', $u);
}

/** Weiterleitungen mit dem Ziel im Klartext (/url?q=…) gleich auspacken */
function smBdUnwrap(string $u): string
{
    // Light JSON schreibt Googles Weiterleitungen ohne Domain („/goto?url=…“)
    if (preg_match('~^/(goto|url)\?~', $u)) $u = 'https://www.google.com' . $u;
    if (!preg_match('~^https://(www\.)?google\.[a-z.]{2,8}/url\?~i', $u)) return $u;
    parse_str((string)parse_url($u, PHP_URL_QUERY), $qs);
    $t = smLink($qs['q'] ?? $qs['url'] ?? '');
    return $t !== '' ? $t : $u;
}

/**
 * Löst Google-Weiterleitungen gleichzeitig auf (nur die Kopfzeile Location, kein Seitenabruf beim Ziel).
 * Liefert Adresse => Ziel. Was nicht rechtzeitig klappt, fehlt in der Liste.
 */
function smBdResolve(array $urls, int $timeout = 4): array
{
    $urls = array_values(array_unique(array_filter($urls, 'smBdIsRedirect')));
    if (!$urls || !function_exists('curl_multi_init')) return [];
    $base = getenv('SM_BD_GOTO_BASE');   // nur für die Tests
    $mh = curl_multi_init();
    $hs = [];
    $loc = [];
    foreach (array_slice($urls, 0, 40) as $i => $u) {
        $ch = curl_init($base ? $base . '?u=' . rawurlencode($u) : $u);
        $loc[$i] = '';
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => $timeout,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP, CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Suchmaschine/' . SM_VERSION . ')',
            CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$loc, $i) {
                if (stripos($line, 'location:') === 0) $loc[$i] = trim(substr($line, 9));
                return strlen($line);
            },
        ]);
        curl_multi_add_handle($mh, $ch);
        $hs[$i] = $ch;
    }
    do {
        $st = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 0.3);
    } while ($running && $st === CURLM_OK);
    $out = [];
    foreach ($hs as $i => $ch) {
        $to = smLink($loc[$i]);
        if ($to !== '' && !smBdIsRedirect($to)) $out[$urls[$i]] = $to;
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

/** Signierter Link über den eigenen Server, der die Weiterleitung erst beim Klick auflöst (z. B. bei 100 Bildern) */
function smBdGoUrl(string $u): string
{
    return smUrl(['a' => 'go', 'u' => $u, 's' => substr(hash_hmac('sha256', 'go|' . $u, smImgSecret()), 0, 32)]);
}

/** ?a=go: Google-Weiterleitung auflösen und direkt zum Ziel (Google sieht nur den Server) */
function smServeGo(): void
{
    $u = isset($_GET['u']) && is_string($_GET['u']) ? $_GET['u'] : '';
    $s = isset($_GET['s']) && is_string($_GET['s']) ? $_GET['s'] : '';
    if ($u === '' || !smBdIsRedirect($u) || !hash_equals(substr(hash_hmac('sha256', 'go|' . $u, smImgSecret()), 0, 32), $s)) {
        http_response_code(403);
        exit;
    }
    $to = smBdUnwrap($u);
    if ($to === $u) $to = smBdResolve([$u], 3)[$u] ?? $u;   // klappt das nicht (Google bremst den Server), macht der Browser die Weiterleitung
    smHeaders(false);
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: private, max-age=3600');
    header('Location: ' . $to, true, 302);
    exit;
}

/* ---------- Bilder ---------- */

/** Vorschaubild: eingebettete Daten (data:) direkt, Googles Vorschau-Server über den eigenen Server, alles andere nicht */
function smBdImg($s): string
{
    $s = is_string($s) ? $s : '';
    if (strpos($s, 'data:image/') === 0) {
        return strlen($s) < 300000 && preg_match('~^data:image/(png|jpe?g|webp|gif);base64,[A-Za-z0-9+/=]+$~', $s) ? $s : '';
    }
    return smThumbUrl($s);
}

/** Signierte Adresse für ein Vorschaubild von Google (encrypted-tbn0.gstatic.com …) über den eigenen Server */
function smThumbUrl(string $src): string
{
    if (!preg_match('~^https://[a-z0-9-]+\.gstatic\.com/~i', $src) || strlen($src) > 1500) return '';
    return smUrl(['a' => 'img', 'k' => 't', 'u' => $src, 's' => substr(hash_hmac('sha256', 'thumb|' . $src, smImgSecret()), 0, 32)]);
}

/* ---------- Text und Datum ---------- */

/** Zeitstempel aus Googles Datumsangaben: „vor 3 Stunden“, „3 hours ago“, „22.08.2026“, „Nov 3, 2026“ … */
function smBdTime($s): int
{
    $s = trim(is_string($s) ? $s : '');
    if ($s === '') return 0;
    $units = ['sek' => 1, 'sec' => 1, 'min' => 60, 'std' => 3600, 'stund' => 3600, 'hour' => 3600, 'hr' => 3600, 'tag' => 86400, 'day' => 86400,
              'woche' => 604800, 'week' => 604800, 'monat' => 2592000, 'month' => 2592000, 'jahr' => 31536000, 'year' => 31536000];
    if (preg_match('/(?:vor\s+)?(\d+|ein(?:e[mnrs]?)?|an?|one)\s+([a-zä]+)/iu', $s, $m) && (stripos($s, 'vor') !== false || stripos($s, 'ago') !== false)) {
        $n = ctype_digit($m[1]) ? (int)$m[1] : 1;
        foreach ($units as $k => $sec) if (stripos($m[2], $k) === 0) return time() - $n * $sec;
    }
    if (preg_match('/\b(gestern|yesterday)\b/i', $s)) return time() - 86400;
    if (preg_match('/(?<![\d.,])(\d{1,2})\.(\d{1,2})\.?(\d{2,4})?(?![\d,])/', $s, $m)) {
        $y = isset($m[3]) && $m[3] !== '' ? (int)$m[3] : (int)date('Y');
        if ($y < 100) $y += 2000;
        if (checkdate((int)$m[2], (int)$m[1], $y)) {
            $t = mktime(12, 0, 0, (int)$m[2], (int)$m[1], $y);
            if ($t > time() + 86400 && !isset($m[3])) $t = mktime(12, 0, 0, (int)$m[2], (int)$m[1], $y - 1);
            return $t <= time() + 86400 ? $t : 0;
        }
    }
    return smParseTime($s);
}

/** Domain aus Googles Anzeige-Adresse („https://www.zeit.de › thema › …“) */
function smBdDisplayHost(string $disp): array
{
    $parts = array_map('trim', explode('›', $disp));
    $first = (string)array_shift($parts);
    $host = (string)parse_url(preg_match('~^https?://~i', $first) ? $first : 'https://' . $first, PHP_URL_HOST);
    $host = preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $host) ? strtolower($host) : '';
    $path = array_filter($parts, function ($x) { return $x !== '' && $x !== '...'; });
    return [smHost($host), $path ? '› ' . mb_substr(implode(' › ', $path), 0, 80) : ''];
}

/** Treffertext mit Googles Hervorhebungen (die Ansicht kennt nur <strong>) */
function smBdDesc(string $desc, $words): string
{
    $desc = trim(preg_replace('/\s+/u', ' ', str_replace(['<', '>'], ['‹', '›'], $desc)));
    foreach (array_slice(array_unique(array_filter((array)$words, 'is_string')), 0, 6) as $w) {
        $w = trim(str_replace(['<', '>'], '', $w));
        if (mb_strlen($w) < 2) continue;
        $desc = preg_replace('/(?<![\p{L}\p{N}>])(' . preg_quote($w, '/') . ')(?![\p{L}\p{N}<])/iu', '<strong>$1</strong>', $desc, 1);
    }
    return (string)$desc;
}

/** Datum aus den Zusatzangaben eines Treffers */
function smBdExtDate(array $o): string
{
    foreach ((array)($o['extensions'] ?? []) as $e) {
        if (is_array($e) && ($e['type'] ?? '') === 'text' && is_string($e['text'] ?? null) && smBdTime($e['text']) > 0) return trim($e['text']);
    }
    return is_string($o['date'] ?? null) ? trim($o['date']) : '';
}

/* ---------- Antworten vereinheitlichen ---------- */

/** Welches Datenformat die Zone liefert: full (Full JSON) oder light (Light JSON, ohne Karten und Zusatzinfos) */
function smBdFormat(array $d): string
{
    return isset($d['general']['search_engine']) || isset($d['general']['results_cnt']) ? 'full' : 'light';
}

function smBdNormalize(array $p, array $d): array
{
    // Merken, was die Zone gerade liefert (nur wenn es sich ändert), damit die Verwaltung darauf hinweisen kann
    if (isset($d['general']) && smProvGet('brightdata', 'fmt') !== ($fmt = smBdFormat($d))) smProvSet('brightdata', 'fmt', $fmt);
    switch ($p['kind']) {
        case 'images': return smBdImages($d);
        case 'news':   $out = smBdNews($d); break;
        case 'videos': $out = smBdVideos((array)($d['organic'] ?? [])); $out = ['results' => $out, 'more' => smBdMore($d), 'altered' => smBdAltered($d)]; break;
        default:       $out = smBdWeb($p, $d);
    }
    return smBdFixLinks($out);
}

function smBdMore(array $d): bool
{
    return !empty($d['pagination']['next_page_start']) || !empty($d['pagination']['next_page_link']) || !empty($d['pagination']['next_page']);
}

function smBdAltered(array $d): ?string
{
    $q = trim((string)($d['general']['query'] ?? ''));
    $dq = trim((string)($d['general']['detected_query'] ?? ''));
    return $dq !== '' && $q !== '' && mb_strtolower($dq) !== mb_strtolower($q) ? $dq : null;
}

function smBdWeb(array $p, array $d): array
{
    $web = [];
    foreach ((array)($d['organic'] ?? []) as $o) {
        if (!is_array($o) || ($url = smLink(smBdUnwrap((string)($o['link'] ?? '')))) === '') continue;
        [$dhost, $dpath] = smBdDisplayHost((string)($o['display_link'] ?? ''));
        $host = smBdIsRedirect($url) ? $dhost : smHost((string)parse_url($url, PHP_URL_HOST));
        if ($host === '') $host = $dhost;
        $links = [];
        foreach ((array)($o['extensions'] ?? []) as $e) {
            if (!is_array($e) || ($e['type'] ?? '') !== 'site_link' || ($lu = smLink(smBdUnwrap((string)($e['link'] ?? '')))) === '') continue;
            $lt = smPlain((string)($e['text'] ?? ''));
            if ($lt === '' || preg_match('/^(Weitere Ergebnisse|More results)/i', $lt)) continue;
            $links[] = ['title' => $lt, 'url' => $lu];
            if (count($links) >= 4) break;
        }
        $date = smBdExtDate($o);
        $site = smPlain((string)($o['source'] ?? ''));
        $web[] = [
            'title'   => smPlain((string)($o['title'] ?? $host)),
            'url'     => $url,
            'desc'    => smBdDesc(smPlain((string)($o['description'] ?? '')), $o['snippet_highlighted_words'] ?? []),
            'host'    => $host,
            'site'    => $site !== '' && mb_strtolower($site) !== mb_strtolower($host) && mb_strlen($site) <= 60 ? $site : '',
            'path'    => $dpath,
            'favicon' => smBdImg($o['icon'] ?? ''),
            'age'     => $date,
            'ts'      => smBdTime($date),
            'thumb'   => smBdImg($o['image'] ?? ''),
            'links'   => $links,
            'via'     => ['brightdata'],
        ];
    }
    $videos = smBdVideos((array)($d['videos'] ?? []));
    $out = [
        'results' => $web, 'news' => [], 'videos' => $videos, 'faq' => [], 'discussions' => [],
        'infobox' => smBdInfobox((array)($d['knowledge'] ?? []), (string)$p['q']),
        'more' => smBdMore($d), 'altered' => smBdAltered($d),
        'answer' => '', 'answer_links' => [], 'sports' => null, 'stock' => null, 'related' => [],
        'cards' => !empty($p['html']),   // diese Antwort bringt die Karten schon mit
    ];
    if (!empty($p['answer']) && is_array($d['ai_overview'] ?? null)) {
        [$out['answer'], $out['answer_links']] = smBdAnswer($d['ai_overview']);
    }
    // Sport-Karte am liebsten aus Googles Seite selbst (mit dem Spiel von heute, Wappen und richtiger Uhrzeit)
    if (is_string($d['html'] ?? null)) $out['sports'] = smBdSportsHtml($d['html']);
    foreach ((array)($d['featured_snippets'] ?? []) as $fs) {
        if (!is_array($fs)) continue;
        if ($out['sports'] !== null && $out['sports']['title'] === '' && !empty($fs['events'])) $out['sports']['title'] = smPlain((string)($fs['title'] ?? ''));
        if ($out['sports'] === null && !empty($fs['events'])) $out['sports'] = smBdSports($fs);
        if ($out['stock'] === null && isset($fs['price'])) $out['stock'] = smBdStock($fs, (string)$p['q']);
    }
    $rel = [];
    foreach ((array)($d['people_also_ask'] ?? []) as $x) if (is_array($x) && is_string($x['question'] ?? null)) $rel[] = smPlain($x['question']);
    foreach ((array)($d['related'] ?? []) as $x) if (is_array($x) && is_string($x['text'] ?? null)) $rel[] = smPlain($x['text']);
    $out['related'] = array_slice(array_values(array_unique(array_filter($rel, function ($s) { return $s !== '' && mb_strlen($s) <= 120; }))), 0, 10);
    // Reihenfolge: Webtreffer, nach dem dritten die Videos
    $order = [];
    foreach ($web as $i => $_) {
        $order[] = ['web', $i];
        if ($i === 2 && $videos) $order[] = ['videos'];
    }
    if (count($web) < 3 && $videos) $order[] = ['videos'];
    $out['order'] = $order;
    return $out;
}

function smBdVideos(array $list): array
{
    $out = [];
    foreach ($list as $o) {
        if (!is_array($o) || ($url = smLink(smBdUnwrap((string)($o['link'] ?? '')))) === '') continue;
        [$dhost] = smBdDisplayHost((string)($o['display_link'] ?? ''));
        $host = smBdIsRedirect($url) ? $dhost : smHost((string)parse_url($url, PHP_URL_HOST));
        // „YouTube · Kanalname“
        $src = smPlain((string)($o['source'] ?? ''));
        $creator = strpos($src, '·') !== false ? trim(substr($src, strpos($src, '·') + 2)) : '';
        if ($host === '' && $src !== '') $host = trim(explode('·', $src)[0]);
        $date = smBdExtDate($o);
        $out[] = [
            'title' => smPlain((string)($o['title'] ?? $url)), 'url' => $url, 'desc' => smPlain((string)($o['description'] ?? '')),
            'host' => $host, 'favicon' => '', 'age' => $date, 'ts' => smBdTime($date),
            'thumb' => smBdImg($o['image'] ?? ''), 'duration' => smPlain((string)($o['duration'] ?? '')),
            'dur_s' => (int)($o['duration_sec'] ?? 0), 'creator' => mb_substr($creator, 0, 60), 'views' => 0, 'via' => ['brightdata'],
        ];
    }
    return $out;
}

function smBdNews(array $d): array
{
    $out = [];
    foreach ((array)($d['news'] ?? []) as $o) {
        if (!is_array($o) || ($url = smLink(smBdUnwrap((string)($o['link'] ?? '')))) === '') continue;
        $src = smPlain((string)($o['source'] ?? ''));
        $date = trim((string)($o['date'] ?? ''));
        $out[] = [
            'title' => smPlain((string)($o['title'] ?? $url)), 'url' => $url, 'desc' => smPlain((string)($o['description'] ?? '')),
            'host' => smBdIsRedirect($url) ? '' : smHost((string)parse_url($url, PHP_URL_HOST)), 'source' => $src,
            'favicon' => smBdImg($o['source_logo'] ?? ''), 'age' => $date, 'ts' => smBdTime($date),
            'thumb' => smBdImg($o['image'] ?? ''), 'breaking' => false, 'via' => ['brightdata'],
        ];
    }
    return ['results' => $out, 'more' => smBdMore($d), 'altered' => smBdAltered($d)];
}

function smBdImages(array $d): array
{
    $out = [];
    foreach ((array)($d['images'] ?? []) as $o) {
        if (!is_array($o)) continue;
        $thumb = smBdImg($o['image'] ?? $o['image_url'] ?? '');
        $page = smLink(smBdUnwrap((string)($o['link'] ?? '')));
        if ($thumb === '' || $page === '') continue;
        $src = smLink($o['original_image'] ?? '');
        // Full JSON: „title“ ist die Website, der eigentliche Titel steht in image_alt. Light JSON: „title“ ist der Titel.
        $full = isset($o['image_alt']);
        $site = $full ? smPlain((string)($o['title'] ?? '')) : smPlain((string)($o['source'] ?? ''));
        if (!$full && ($site === '' || mb_strlen($site) > 40)) $site = smHost((string)parse_url(smBdIsRedirect($page) ? $src : $page, PHP_URL_HOST));
        $out[] = [
            'title' => smPlain((string)($full ? $o['image_alt'] : ($o['title'] ?? $o['source'] ?? ''))),
            'page' => smBdIsRedirect($page) ? smBdGoUrl($page) : $page,
            'src' => $src, 'thumb' => $thumb,
            'host' => $site !== '' ? $site : smHost((string)parse_url($src, PHP_URL_HOST)),
            'w' => 0, 'h' => 0, 'tw' => 0, 'th' => 0, 'favicon' => '', 'via' => ['brightdata'],
        ];
    }
    return ['results' => $out, 'more' => false, 'altered' => smBdAltered($d)];
}

/**
 * Google-Weiterleitungen eines Ergebnisses über den eigenen Server leiten. Aufgelöst wird erst beim Klick (?a=go):
 * Das spart bei jeder Suche eine halbe bis eine Sekunde, und Google sieht trotzdem nicht, wer was anklickt.
 */
function smBdFixLinks(array $out): array
{
    $fix = function (&$x) use (&$fix) {
        if (is_array($x)) {
            foreach ($x as &$v) $fix($v);
            unset($v);
        } elseif (is_string($x) && smBdIsRedirect($x)) {
            $x = smBdGoUrl($x);
        }
    };
    $fix($out);
    return $out;
}

/* ---------- Extras: Infobox, KI-Übersicht, Spielstände, Kurse ---------- */

function smBdInfobox(array $k, string $q = ''): ?array
{
    if (empty($k['name']) || !is_string($k['name'])) return null;
    // Die Zerlegung erwischt manchmal den Namen eines Nachbarn („Dell“ bei „apple aktie“). Passt der Name nicht zur Suche,
    // die Beschreibung aber schon, gilt der Anfang der Beschreibung („Apple Inc. ist …“) als Name.
    $words = array_filter(preg_split('/[\s,.:]+/u', mb_strtolower($q)), function ($w) { return mb_strlen($w) >= 3; });
    $hits = function (string $s) use ($words): bool { foreach ($words as $w) if (mb_strpos(mb_strtolower($s), $w) !== false) return true; return false; };
    $desc0 = smPlain((string)($k['description'] ?? ''));
    if ($words && !$hits($k['name']) && preg_match('/^(.{2,50}?) (ist|war|sind|is|was|are) /u', $desc0, $m) && $hits($m[1])) {
        $k['name'] = $m[1];
        unset($k['summary'], $k['subtitle'], $k['images']);
    }
    $attrs = [];
    if (is_numeric($k['rating'] ?? null)) {
        $attrs[] = [t('Bewertung'), smNumF((float)$k['rating'], 1) . ' ★' . (is_numeric($k['reviews_cnt'] ?? null) ? ' (' . t('%s Rezensionen', smNum((int)$k['reviews_cnt'])) . ')' : '')];
    }
    foreach ((array)($k['facts'] ?? []) as $f) {
        if (!is_array($f) || !is_string($f['key'] ?? null)) continue;
        $vals = [];
        foreach ((array)($f['value'] ?? []) as $v) {
            // „Mehr“ ist bei Google nur ein Link zu einer weiteren Suche
            if (is_array($v) && is_string($v['text'] ?? null) && trim($v['text']) !== '' && !preg_match('/^(mehr|more|weitere|alle anzeigen|view all)$/iu', trim($v['text']))) $vals[] = smPlain($v['text']);
        }
        if (!$vals) continue;
        $hours = preg_match('/öffnungszeit|hours/iu', $f['key']);
        $attrs[] = [smPlain($f['key']), implode($hours ? ' · ' : ', ', $vals)];
        if (count($attrs) >= 9) break;
    }
    $profiles = [];
    foreach ((array)($k['widgets'] ?? []) as $w) {
        if (!is_array($w) || ($w['type'] ?? '') !== 'social_media') continue;
        foreach ((array)($w['items'] ?? []) as $it) {
            if (is_array($it) && ($u = smLink(smBdUnwrap((string)($it['link'] ?? '')))) !== '' && is_string($it['name'] ?? null)) $profiles[] = ['name' => smPlain($it['name']), 'url' => $u];
            if (count($profiles) >= 6) break 2;
        }
    }
    $img = '';
    foreach ((array)($k['images'] ?? []) as $im) if (is_array($im) && ($img = smBdImg($im['image'] ?? '')) !== '') break;
    $sub = smPlain((string)($k['summary'] ?? $k['subtitle'] ?? ''));
    return [
        'title' => smPlain($k['name']), 'subtitle' => $sub, 'desc' => smPlain((string)($k['description'] ?? '')),
        'url' => smLink(smBdUnwrap((string)($k['description_link'] ?? ''))), 'website' => smLink(smBdUnwrap((string)($k['website'] ?? ''))),
        'thumb' => $img, 'attrs' => $attrs, 'profiles' => $profiles, 'source' => smPlain((string)($k['description_source'] ?? '')),
    ];
}

/** Googles KI-Übersicht als Klartext (Absätze mit Zeilenumbruch, Aufzählungen mit •) und bis zu 4 Quellen */
function smBdAnswer(array $ai): array
{
    $lines = [];
    $links = [];
    $clean = function (string $s): string {
        $s = preg_replace('/\[(Beginn|Ende) der Markierung\]|\[(Start|End) of (the )?highlight(ed)?( text)?\]/iu', '', $s);
        $s = preg_replace('/\s+\+\d+$/u', '', trim(preg_replace('/\s+/u', ' ', (string)$s)));
        return trim((string)$s);
    };
    $add = function (array $t, string $prefix) use (&$lines, &$links, $clean) {
        $s = $clean(smPlain((string)($t['snippet'] ?? $t['title'] ?? '')));
        // Ohne Ballast und ohne die Rückfrage am Ende („Möchtest du mehr über … erfahren?“)
        if ($s !== '' && mb_strlen($s) >= 3 && !preg_match('/lizenziert|TeilenDownload|^Bild zum|^(Möchtest|Möchten Sie|Willst|Wollen Sie|Soll ich|Sollen wir|Would you like|Do you want)\b.*\?$/u', $s)) $lines[] = $prefix . $s;
        foreach ((array)($t['links'] ?? []) as $l) {
            if (is_array($l) && ($u = smLink(smBdUnwrap((string)($l['link'] ?? '')))) !== '') $links[$u] = ['url' => $u, 'title' => smPlain((string)($l['title'] ?? ''))];
        }
    };
    foreach ((array)($ai['texts'] ?? []) as $t) {
        if (!is_array($t)) continue;
        if (($t['type'] ?? '') === 'list' && is_array($t['list'] ?? null)) {
            if (is_string($t['title'] ?? null) && $t['title'] !== '' && $t['title'] !== ($t['list'][0]['snippet'] ?? '')) $lines[] = $clean(smPlain($t['title']));
            foreach ($t['list'] as $it) if (is_array($it)) $add($it, '• ');
        } else {
            $add($t, '');
        }
    }
    $text = mb_substr(implode("\n", array_unique(array_filter($lines))), 0, 1500);
    return [$text, array_slice(array_values($links), 0, 4)];
}

/** Spielstände aus dem, was Bright Data selbst zerlegt (ohne das Spiel von heute, Zeiten in der Zeitzone des Abrufs) */
function smBdSports(array $fs): ?array
{
    $ev = [];
    foreach ((array)$fs['events'] as $e) {
        if (!is_array($e) || count((array)($e['teams'] ?? [])) < 2) continue;
        $teams = [];
        foreach ((array)$e['teams'] as $tm) {
            if (is_array($tm) && is_string($tm['name'] ?? null)) $teams[] = ['name' => smPlain($tm['name']), 'score' => is_scalar($tm['score'] ?? null) ? smPlain((string)$tm['score']) : '', 'logo' => '', 'win' => false];
        }
        if (count($teams) < 2) continue;
        $ev[] = ['teams' => array_slice($teams, 0, 2), 'status' => smPlain((string)($e['status'] ?? '')), 'when' => smPlain((string)($e['date'] ?? '')),
                 'league' => smPlain((string)($e['results'] ?? $e['league'] ?? ''))];
        if (count($ev) >= 6) break;
    }
    return $ev ? ['title' => smPlain((string)($fs['title'] ?? '')), 'sub' => '', 'main' => null, 'events' => $ev] : null;
}

/** Anstoß lesbar: „heute 19:00“, „morgen 14:00“, „Sa., 5.12. 14:00“ (in der Zeitzone des Servers) */
function smBdMatchTime(int $ts): string
{
    if ($ts <= 0) return '';
    $day = (int)floor(($ts - strtotime('today')) / 86400);
    $hm = date('H:i', $ts);
    if ($day === 0) return t('heute %s', $hm);
    if ($day === 1) return t('morgen %s', $hm);
    if ($day === -1) return t('gestern %s', $hm);
    $wd = smLang() === 'de' ? ['So.', 'Mo.', 'Di.', 'Mi.', 'Do.', 'Fr.', 'Sa.'][(int)date('w', $ts)] : date('D', $ts);
    return $wd . ', ' . date(smLang() === 'de' ? 'j.n.' : 'n/j', $ts) . ($ts > time() ? ' ' . $hm : '');
}

/**
 * Die Sport-Karte aus Googles Seite: das große Spiel oben (Liga, „heute 19:00“, Wappen, Spielstand) und die Liste der Spiele.
 * Google benutzt dafür seit Jahren dieselben Klassennamen (imso_mh__ …, imspo_mt__ …). Ändert sich das, kommt hier null
 * zurück, und es gilt die einfachere Liste von Bright Data.
 */
function smBdSportsHtml(string $html): ?array
{
    if (strpos($html, 'imso_mh__') === false && strpos($html, 'imspo_mt__') === false) return null;
    if (!class_exists('DOMDocument')) return null;
    // Die Wappen stecken in Skripten: var s='data:…';var ii=['dimg_…'];
    $imgs = [];
    if (preg_match_all("~var s='(data:image/[a-z]+;base64,[^']+)';var ii=\[([^\]]*)\]~", $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            $data = smBdImg(str_replace(['\x3d', '\/'], ['=', '/'], $x[1]));
            if ($data === '' || strlen($data) > 20000) continue;
            foreach (explode(',', $x[2]) as $id) $imgs[trim($id, " '\"")] = $data;
        }
    }
    $doc = new DOMDocument();
    $old = libxml_use_internal_errors(true);
    $ok = @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($old);
    if (!$ok) return null;
    $xp = new DOMXPath($doc);
    $cls = function (string $c): string { return "contains(concat(' ', normalize-space(@class), ' '), ' $c ')"; };
    $txt = function (?DOMNode $n): string { return $n ? smPlain(trim((string)preg_replace('/\s+/u', ' ', $n->textContent))) : ''; };
    $one = function (string $q, DOMNode $ctx) use ($xp, $txt): string { return $txt($xp->query($q, $ctx)->item(0)); };
    $name = function (DOMNode $n) use ($xp, $txt): string {
        $s = $xp->query(".//span[@aria-hidden='true']", $n)->item(0);
        return mb_substr($txt($s ?: $n), 0, 40);
    };
    $logo = function (DOMNode $n) use ($xp, $imgs): string {
        $im = $xp->query('.//img[@id]', $n)->item(0);
        return $im instanceof DOMElement ? ($imgs[$im->getAttribute('id')] ?? '') : '';
    };
    $out = ['title' => '', 'sub' => '', 'main' => null, 'events' => []];
    // Kopf der Karte („Hansa Rostock“, „12. Platz 3. Liga“), wenn er sich finden lässt
    $head = $xp->query('//*[' . $cls('ZtSsId') . ']')->item(0);
    if ($head) $out['title'] = mb_substr($txt($head), 0, 60);
    $sub = $xp->query('//span[' . $cls('mKwiob') . ']')->item(0);
    if ($sub) $out['sub'] = mb_substr($txt($sub), 0, 60);
    // Das große Spiel oben
    $first = $xp->query('//*[' . $cls('imso_mh__first-tn-ed') . ']')->item(0);
    $second = $xp->query('//*[' . $cls('imso_mh__second-tn-ed') . ']')->item(0);
    if ($first && $second) {
        $box = $first;
        for ($i = 0; $i < 12 && $box->parentNode; $i++) {
            $box = $box->parentNode;
            if ($xp->query('.//*[' . $cls('imso_mh__lr-dt-ds') . ' or ' . $cls('imso-ln') . ']', $box)->length) break;
        }
        $nm = $xp->query('.//*[' . $cls('imso_mh__tm-nm') . ']', $box);
        $league = $one('.//*[' . $cls('imso-ln') . ']', $box);
        $when = $one('.//*[' . $cls('imso_mh__lr-dt-ds') . ']', $box);
        $status = $one('.//*[' . $cls('imso_mh__ft-mtch') . ' or ' . $cls('imso_mh__stts-l') . ']', $box);
        if ($status === '' || mb_strpos($status, $when) !== false && $when !== '') $status = '';
        $ls = $one('.//*[' . $cls('imso_mh__l-tm-sc') . ']', $box);
        $rs = $one('.//*[' . $cls('imso_mh__r-tm-sc') . ']', $box);
        $live = $xp->query('.//*[' . $cls('imso_mh__lv-m-stts-cont') . ' or ' . $cls('imso_mh__live') . ']', $box)->length > 0;
        $out['main'] = [
            'league' => $league, 'when' => $when, 'status' => $status, 'live' => $live,
            'teams' => [
                ['name' => $nm->length > 0 ? $name($nm->item(0)) : $name($first), 'score' => $ls, 'logo' => $logo($first), 'win' => false],
                ['name' => $nm->length > 1 ? $name($nm->item(1)) : $name($second), 'score' => $rs, 'logo' => $logo($second), 'win' => false],
            ],
        ];
        if ($out['main']['teams'][0]['name'] === '' || $out['main']['teams'][1]['name'] === '') $out['main'] = null;
    }
    // Weitere Spiele: jedes hat eine Startzeit (UTC), daraus die Uhrzeit in der eigenen Zeitzone
    $seen = [];
    foreach ($xp->query('//*[@data-start-time]') as $mt) {
        if (!$mt instanceof DOMElement) continue;
        $mid = $mt->getAttribute('data-mid') ?: $mt->getAttribute('data-start-time') . count($seen);
        if (isset($seen[$mid])) continue;
        $teams = [];
        foreach ($xp->query('.//td[' . $cls('imspo_mt__tt-w') . ']', $mt) as $td) {
            $row = $td->parentNode;
            $teams[] = ['name' => $name($td), 'score' => $one('.//*[' . $cls('imspo_mt__t-sc') . ']', $row), 'logo' => $logo($row),
                        'win' => $xp->query('.//*[' . $cls('imspo_mt__triangle') . ']', $row)->length > 0];
        }
        if (count($teams) < 2 || $teams[0]['name'] === '' || $teams[1]['name'] === '') continue;
        $seen[$mid] = true;
        $ts = smParseTime($mt->getAttribute('data-start-time')) ?: (int)strtotime($mt->getAttribute('data-start-time'));
        $out['events'][] = ['teams' => array_slice($teams, 0, 2), 'when' => smBdMatchTime((int)$ts), 'ts' => (int)$ts,
                            'status' => $one('.//*[' . $cls('imspo_mt__match-status') . ']', $mt), 'league' => ''];
        if (count($out['events']) >= 8) break;
    }
    // Kommende Spiele zuerst (nach Anstoß), dann die letzten Ergebnisse (neueste zuerst)
    $up = array_values(array_filter($out['events'], function ($e) { return $e['ts'] > time() - 3 * 3600 && $e['status'] === ''; }));
    $past = array_values(array_filter($out['events'], function ($e) { return !($e['ts'] > time() - 3 * 3600 && $e['status'] === ''); }));
    usort($up, function ($a, $b) { return $a['ts'] <=> $b['ts']; });
    usort($past, function ($a, $b) { return $b['ts'] <=> $a['ts']; });
    $out['events'] = array_merge(array_slice($up, 0, 3), array_slice($past, 0, 3));
    return $out['main'] || $out['events'] ? $out : null;
}

/** Kurs einer Aktie oder eines Index. Nur wenn er zur Suche passt (die Zerlegung erwischt manchmal einen Nachbarn). */
function smBdStock(array $fs, string $q): ?array
{
    $title = smPlain((string)($fs['title'] ?? ''));
    $sym = smPlain((string)($fs['symbol'] ?? ''));
    $price = smPlain((string)$fs['price']);
    if ($title === '' || $price === '') return null;
    $hay = mb_strtolower($title . ' ' . $sym);
    $hit = false;
    foreach (preg_split('/[\s,.:]+/u', mb_strtolower($q)) as $w) {
        if (mb_strlen($w) >= 3 && !in_array($w, ['aktie', 'aktien', 'kurs', 'stock', 'share', 'price', 'preis', 'index', 'aktuell', 'heute'], true) && mb_strpos($hay, $w) !== false) $hit = true;
    }
    if (!$hit) return null;
    $ch = (array)($fs['price_change'] ?? []);
    $pct = '';
    $abs = '';
    foreach (['abs', 'rel'] as $k) {
        $v = smPlain((string)($ch[$k] ?? ''));
        if ($v === '') continue;
        if (strpos($v, '%') !== false) $pct = $v; else $abs = $v;
    }
    $cur = smPlain((string)($fs['currency'] ?? ''));
    $rows = [];
    $skip = ['title', 'exchange', 'symbol', 'price', 'currency', 'price_change', 'rank', 'global_rank', 'link', 'date'];
    foreach ($fs as $k => $v) {
        if (!is_string($k) || $k === '' || in_array($k, $skip, true) || !is_scalar($v) || trim((string)$v) === '') continue;
        $nice = ['52_wo_hoch' => t('52-Wochen-Hoch'), '52_wo_tief' => t('52-Wochen-Tief'), '52-wk_high' => t('52-Wochen-Hoch'), '52-wk_low' => t('52-Wochen-Tief'),
                 'vort.|schl.' => t('Vortag'), 'prev_close' => t('Vortag'), 'kgv' => 'KGV', 'p/e_ratio' => 'KGV', 'marktkap.' => t('Marktkap.'), 'mkt_cap' => t('Marktkap.'),
                 'viertelj._div.' => t('Quartalsdividende'), 'open' => t('Eröffnung'), 'high' => t('Hoch'), 'low' => t('Tief'), 'div_yield' => t('Dividende')];
        $label = $nice[mb_strtolower($k)] ?? str_replace(['_', '|'], [' ', '/'], $k);
        $rows[] = [mb_convert_case(mb_substr($label, 0, 1), MB_CASE_UPPER) . mb_substr($label, 1), smPlain((string)$v)];
        if (count($rows) >= 8) break;
    }
    return ['title' => $title, 'symbol' => $sym, 'exchange' => smPlain((string)($fs['exchange'] ?? '')), 'price' => $price,
            'currency' => preg_match('/^[A-Z]{3}$/', $cur) ? $cur : '', 'abs' => $abs, 'pct' => $pct,
            'up' => !preg_match('/^[-−]/u', $abs !== '' ? $abs : $pct), 'period' => smPlain((string)($ch['period'] ?? '')), 'rows' => $rows];
}

/* ---------- Suchvorschläge ---------- */

/**
 * ?a=suggest&q=…: Vorschläge von Google fürs Suchfeld (JSON). Kostenlos, verbraucht keine Anfragen bei Bright Data oder Brave.
 * Der eigene Server fragt, Google sieht also nur den Server. Jeder Vorschlag wird einen Tag zwischengespeichert.
 */
function smServeSuggest(): void
{
    $q = trim(mb_substr(is_string($_GET['q'] ?? null) ? $_GET['q'] : '', 0, 100));
    $out = [];
    if (smSetting('suggest') === '1' && mb_strlen($q) >= 2 && $q[0] !== '!') {
        $hl = smLang() === 'de' ? 'de' : 'en';
        $ck = 'sugg|' . $hl . '|' . mb_strtolower($q);
        $hit = smCacheGet($ck);
        if ($hit !== null) {
            $out = (array)($hit['l'] ?? []);
        } else {
            $url = (getenv('SM_SUGGEST_BASE') ?: 'https://suggestqueries.google.com/complete/search')
                 . '?' . http_build_query(['client' => 'firefox', 'hl' => $hl, 'ie' => 'utf-8', 'oe' => 'utf-8', 'q' => $q]);
            $r = smHttpMulti(['x' => ['url' => $url, 'method' => 'GET', 'headers' => ['Accept: application/json']]], 3)['x'];
            $d = json_decode($r['body'], true);
            if ($r['status'] === 200 && is_array($d) && is_array($d[1] ?? null)) {
                foreach ($d[1] as $s) {
                    if (is_string($s) && ($s = trim(smPlain($s))) !== '' && mb_strlen($s) <= 120) $out[] = $s;
                    if (count($out) >= 8) break;
                }
                smCacheSet($ck, ['l' => $out], 86400);
            }
        }
    }
    smHeaders();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, max-age=600');
    echo json_encode(['list' => array_values($out)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Echte Adresse hinter einem eigenen Weiterleitungs-Link (?a=go&u=…) für den Lesemodus, sonst die Adresse selbst */
function smBdRealUrl(string $u): string
{
    if (strpos($u, 'a=go') === false) return $u;
    parse_str((string)parse_url($u, PHP_URL_QUERY), $qs);
    $g = is_string($qs['u'] ?? null) ? $qs['u'] : '';
    if (!smBdIsRedirect($g)) return $u;
    $to = smBdUnwrap($g);
    return $to !== $g ? $to : (smBdResolve([$g], 4)[$g] ?? $u);
}
