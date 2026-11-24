<?php
/* Suchmaschine – Anbindung an die Brave Search API (Web, Bilder, News, Videos) */
defined('SM_VERSION') || exit;

const SM_API = 'https://api.search.brave.com/res/v1/';

/** Fehler mit verständlicher Meldung für die Seite */
class SmSearchError extends RuntimeException
{
}

/** Länder, die Brave kennt (Code => Name) */
function smCountries(): array
{
    $c = [
        'ALL' => t('Alle Regionen'), 'AR' => t('Argentinien'), 'AU' => t('Australien'), 'AT' => t('Österreich'), 'BE' => t('Belgien'),
        'BR' => t('Brasilien'), 'CA' => t('Kanada'), 'CL' => t('Chile'), 'CN' => t('China'), 'DK' => t('Dänemark'), 'FI' => t('Finnland'),
        'FR' => t('Frankreich'), 'DE' => t('Deutschland'), 'HK' => t('Hongkong'), 'IN' => t('Indien'), 'ID' => t('Indonesien'),
        'IT' => t('Italien'), 'JP' => t('Japan'), 'KR' => t('Südkorea'), 'MY' => t('Malaysia'), 'MX' => t('Mexiko'),
        'NL' => t('Niederlande'), 'NZ' => t('Neuseeland'), 'NO' => t('Norwegen'), 'PH' => t('Philippinen'), 'PL' => t('Polen'),
        'PT' => t('Portugal'), 'RU' => t('Russland'), 'SA' => t('Saudi-Arabien'), 'ZA' => t('Südafrika'), 'ES' => t('Spanien'),
        'SE' => t('Schweden'), 'CH' => t('Schweiz'), 'TW' => t('Taiwan'), 'TR' => t('Türkei'), 'GB' => t('Vereinigtes Königreich'),
        'US' => t('Vereinigte Staaten'),
    ];
    $all = ['ALL' => $c['ALL']];
    unset($c['ALL']);
    asort($c, SORT_LOCALE_STRING);
    return $all + $c;
}

/** Sprachen der Ergebnisse (Code => Name) */
function smSearchLangs(): array
{
    $l = [
        'ar' => 'العربية', 'bg' => 'Български', 'ca' => 'Català', 'cs' => 'Čeština', 'da' => 'Dansk', 'de' => 'Deutsch',
        'en' => 'English', 'en-gb' => 'English (UK)', 'es' => 'Español', 'et' => 'Eesti', 'eu' => 'Euskara', 'fi' => 'Suomi',
        'fr' => 'Français', 'gl' => 'Galego', 'he' => 'עברית', 'hi' => 'हिन्दी', 'hr' => 'Hrvatski', 'hu' => 'Magyar',
        'is' => 'Íslenska', 'it' => 'Italiano', 'jp' => '日本語', 'ko' => '한국어', 'lt' => 'Lietuvių', 'lv' => 'Latviešu',
        'ms' => 'Bahasa Melayu', 'nb' => 'Norsk bokmål', 'nl' => 'Nederlands', 'pl' => 'Polski', 'pt-br' => 'Português (Brasil)',
        'pt-pt' => 'Português (Portugal)', 'ro' => 'Română', 'ru' => 'Русский', 'sk' => 'Slovenčina', 'sl' => 'Slovenščina',
        'sr' => 'Српски', 'sv' => 'Svenska', 'th' => 'ไทย', 'tr' => 'Türkçe', 'uk' => 'Українська', 'vi' => 'Tiếng Việt',
        'zh-hans' => '中文 (简体)', 'zh-hant' => '中文 (繁體)',
    ];
    return $l;
}

/** Zeitraum-Filter: Wert für Brave => Bezeichnung */
function smFreshness(): array
{
    return ['' => t('Beliebige Zeit'), 'pd' => t('Letzte 24 Stunden'), 'pw' => t('Letzte Woche'), 'pm' => t('Letzter Monat'), 'py' => t('Letztes Jahr')];
}

/**
 * Sucht bei Brave oder im Cache.
 * $kind: web | images | news | videos
 * $o: country, search_lang, safesearch, page, freshness, exact
 */
function smSearch(string $kind, string $q, array $o): array
{
    $page = max(1, min(10, (int)($o['page'] ?? 1)));
    $safe = (string)($o['safesearch'] ?? 'moderate');
    $params = [
        'q'           => $q,
        'country'     => (string)($o['country'] ?? 'ALL'),
        'search_lang' => (string)($o['search_lang'] ?? 'en'),
        'ui_lang'     => smLang() === 'de' ? 'de-DE' : 'en-US',
        'safesearch'  => $safe,
    ];
    switch ($kind) {
        case 'images':
            $path = 'images/search';
            $params['count'] = 100;
            $params['safesearch'] = $safe === 'off' ? 'off' : 'strict';   // Bilder kennen nur off/strict
            unset($params['ui_lang']);
            break;
        case 'news':
        case 'videos':
            $path = $kind . '/search';
            $params['count'] = 20;
            $params['offset'] = $page - 1;
            break;
        default:
            $kind = 'web';
            $path = 'web/search';
            $params['count'] = !empty($o['endless']) ? 20 : max(5, min(20, (int)smSetting('per_page')));
            $params['offset'] = $page - 1;
            if (!empty($o['exact'])) $params['spellcheck'] = '0';
    }
    if (!empty($o['freshness']) && $kind !== 'images' && isset(smFreshness()[$o['freshness']])) {
        $params['freshness'] = $o['freshness'];
    }
    if ($params['country'] === '') unset($params['country']);

    $cacheKey = $path . '|' . json_encode($params);
    if ($hit = smCacheGet($cacheKey)) {
        $hit['cached'] = true;
        return $hit;
    }

    $key = trim(smSetting('api_key'));
    if ($key === '') {
        throw new SmSearchError(t('Es ist noch kein Brave API-Key eingetragen. Ein Administrator kann ihn unter Verwaltung → Einstellungen hinterlegen.'));
    }
    if (!smUsageReserve($kind)) {
        throw new SmSearchError(t('Das Monatslimit von %d Suchen ist erreicht. Am Monatsersten geht es weiter, oder ein Administrator erhöht das Limit.', (int)smSetting('monthly_limit')));
    }

    $data = smBraveGet($key, $path, $params);
    switch ($kind) {
        case 'images': $out = smNormImages($data); break;
        case 'news':   $out = smNormNews($data); break;
        case 'videos': $out = smNormVideos($data); break;
        default:       $out = smNormWeb($data);
    }
    smCacheSet($cacheKey, $out);
    $out['cached'] = false;
    return $out;
}

/** Eine Anfrage an Brave. Wirft SmSearchError mit verständlicher Meldung. */
function smBraveGet(string $key, string $path, array $params, int $timeout = 12): array
{
    $base = getenv('SM_API_BASE') ?: SM_API;   // nur zum Testen
    $ch = curl_init($base . $path . '?' . http_build_query($params));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'X-Subscription-Token: ' . $key],
        CURLOPT_USERAGENT      => 'Suchmaschine/' . SM_VERSION . ' (+https://github.com/' . SM_REPO . ')',
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $errno) {
        error_log('Suchmaschine: Brave nicht erreichbar: ' . $error);
        throw new SmSearchError($errno === CURLE_OPERATION_TIMEDOUT
            ? t('Brave hat zu lange gebraucht. Bitte gleich noch einmal versuchen.')
            : t('Brave ist gerade nicht erreichbar. Kann der Server Verbindungen nach außen aufbauen?'));
    }
    $data = json_decode((string)$body, true);
    if ($status === 200 && is_array($data)) return $data;

    error_log('Suchmaschine: Brave HTTP ' . $status . ': ' . substr((string)$body, 0, 500));
    $detail = is_array($data) ? (string)($data['error']['detail'] ?? '') : '';
    $code = is_array($data) ? (string)($data['error']['code'] ?? '') : '';
    if ($status === 401 || $status === 403 || ($status === 422 && stripos($code . $detail, 'token') !== false)) {
        $msg = t('Brave nimmt den API-Key nicht an. Stimmt der Key, und ist im Brave-Dashboard ein Plan aktiv?');
    } elseif ($status === 402) {
        $msg = t('Das Brave-Guthaben ist aufgebraucht.');
    } elseif ($status === 422 || $status === 400) {
        $msg = t('Brave hat die Anfrage abgelehnt.');
    } elseif ($status === 429) {
        $msg = t('Zu viele Anfragen oder das Kontingent bei Brave ist erschöpft. Kurz warten oder im Brave-Dashboard nachsehen.');
    } elseif ($status >= 500) {
        $msg = t('Brave hat gerade technische Probleme. Bitte später noch einmal versuchen.');
    } else {
        $msg = t('Unerwartete Antwort von Brave (HTTP %d).', $status);
    }
    if ($detail !== '') $msg .= ' (Brave: ' . mb_substr($detail, 0, 200) . ')';
    throw new SmSearchError($msg);
}

/** Prüft einen API-Key mit einer echten Suche. Liefert Fehlertext oder ''. */
function smTestKey(string $key): string
{
    try {
        smBraveGet(trim($key), 'web/search', ['q' => 'test', 'count' => 1], 10);
        return '';
    } catch (SmSearchError $e) {
        return $e->getMessage();
    }
}

/* ---------- Antworten auf das Nötige eindampfen ---------- */

/** Nur Bilder über Braves Bild-Proxy (keine fremden Server, die Besucher sehen könnten) */
function smImg($src): string
{
    $src = is_string($src) ? $src : '';
    return strpos($src, 'https://imgs.search.brave.com/') === 0 ? $src : '';
}

function smLink($url): string
{
    $url = is_string($url) ? $url : '';
    return preg_match('~^https?://~i', $url) ? $url : '';
}

function smHostOf(array $r): string
{
    $h = (string)($r['meta_url']['hostname'] ?? '');
    if ($h === '') $h = (string)parse_url((string)($r['url'] ?? ''), PHP_URL_HOST);
    return smHost($h);
}

function smNormWeb(array $d): array
{
    $web = [];
    foreach ($d['web']['results'] ?? [] as $r) {
        $url = smLink($r['url'] ?? '');
        if ($url === '') continue;
        $links = [];
        foreach (array_merge((array)($r['cluster'] ?? []), (array)($r['deep_results']['buttons'] ?? [])) as $c) {
            if (!is_array($c) || ($u = smLink($c['url'] ?? '')) === '' || $u === $url) continue;
            $links[$u] = ['title' => smPlain((string)($c['title'] ?? $u)), 'url' => $u];
            if (count($links) >= 4) break;
        }
        $web[] = [
            'title'   => (string)($r['title'] ?? $url),
            'url'     => $url,
            'desc'    => (string)($r['description'] ?? ''),
            'host'    => smHostOf($r),
            'site'    => smPlain((string)($r['profile']['name'] ?? '')),
            'path'    => (string)($r['meta_url']['path'] ?? ''),
            'favicon' => smImg($r['meta_url']['favicon'] ?? ''),
            'age'     => (string)($r['age'] ?? ''),
            'thumb'   => smImg($r['thumbnail']['src'] ?? ''),
            'links'   => array_values($links),
        ];
    }

    $news = smNormNews(['results' => $d['news']['results'] ?? []])['results'];
    $videos = smNormVideos(['results' => $d['videos']['results'] ?? []])['results'];

    $faq = [];
    foreach ($d['faq']['results'] ?? [] as $f) {
        $u = smLink($f['url'] ?? '');
        if (empty($f['question']) || empty($f['answer'])) continue;
        $faq[] = ['q' => smPlain((string)$f['question']), 'a' => (string)$f['answer'], 'title' => smPlain((string)($f['title'] ?? '')), 'url' => $u, 'host' => smHostOf($f)];
        if (count($faq) >= 5) break;
    }

    $disc = [];
    foreach ($d['discussions']['results'] ?? [] as $r) {
        $u = smLink($r['url'] ?? '');
        if ($u === '') continue;
        $data = (array)($r['data'] ?? []);
        $disc[] = [
            'title' => smPlain((string)($data['title'] ?? $r['title'] ?? $u)), 'url' => $u, 'host' => smHostOf($r),
            'forum' => smPlain((string)($data['forum_name'] ?? '')), 'answers' => (int)($data['num_answers'] ?? 0),
            'desc'  => (string)($data['question'] ?? $r['description'] ?? ''), 'age' => (string)($r['age'] ?? ''),
            'favicon' => smImg($r['meta_url']['favicon'] ?? ''),
        ];
        if (count($disc) >= 4) break;
    }

    $info = null;
    $ib = $d['infobox']['results'][0] ?? null;
    if (is_array($ib) && !empty($ib['title'])) {
        $attrs = [];
        foreach ((array)($ib['attributes'] ?? []) as $a) {
            if (is_array($a) && count($a) >= 2 && is_scalar($a[0]) && is_scalar($a[1]) && $a[1] !== '') $attrs[] = [smPlain((string)$a[0]), smPlain((string)$a[1])];
            if (count($attrs) >= 8) break;
        }
        $profiles = [];
        foreach ((array)($ib['profiles'] ?? []) as $p) {
            if (is_array($p) && ($u = smLink($p['url'] ?? '')) !== '') $profiles[] = ['name' => smPlain((string)($p['long_name'] ?? $p['name'] ?? smHost((string)parse_url($u, PHP_URL_HOST)))), 'url' => $u];
            if (count($profiles) >= 6) break;
        }
        $src = '';
        foreach ((array)($ib['providers'] ?? []) as $p) {
            if (is_array($p) && !empty($p['name'])) { $src = smPlain((string)$p['name']); break; }
        }
        $info = [
            'title'    => smPlain((string)$ib['title']),
            'subtitle' => smPlain((string)($ib['description'] ?? $ib['label'] ?? '')),
            'desc'     => smPlain((string)($ib['long_desc'] ?? '')),
            'url'      => smLink($ib['url'] ?? ''),
            'website'  => smLink($ib['website_url'] ?? ''),
            'thumb'    => smImg($ib['thumbnail']['src'] ?? ''),
            'attrs'    => $attrs,
            'profiles' => $profiles,
            'source'   => $src,
        ];
    }

    // Reihenfolge wie bei Brave: Webtreffer, dazwischen News, Videos, Fragen, Diskussionen
    $order = [];
    $seen = [];
    foreach ($d['mixed']['main'] ?? [] as $m) {
        $type = (string)($m['type'] ?? '');
        if ($type === 'web' && isset($m['index'], $web[(int)$m['index']])) {
            $order[] = ['web', (int)$m['index']];
            $seen[(int)$m['index']] = true;
        } elseif (in_array($type, ['news', 'videos', 'faq', 'discussions'], true) && !isset($seen[$type])) {
            $order[] = [$type];
            $seen[$type] = true;
        }
    }
    foreach ($web as $i => $_) if (!isset($seen[$i])) $order[] = ['web', $i];
    foreach (['news', 'videos', 'faq', 'discussions'] as $t) {
        if (!isset($seen[$t])) $order[] = [$t];
    }

    return [
        'results'     => $web,
        'news'        => $news,
        'videos'      => $videos,
        'faq'         => $faq,
        'discussions' => $disc,
        'infobox'     => $info,
        'order'       => $order,
        'more'        => (bool)($d['query']['more_results_available'] ?? false),
        'altered'     => isset($d['query']['altered']) ? (string)$d['query']['altered'] : null,
    ];
}

function smNormImages(array $d): array
{
    $out = [];
    foreach ($d['results'] ?? [] as $r) {
        $thumb = smImg($r['thumbnail']['src'] ?? '');
        $page = smLink($r['url'] ?? '');
        if ($thumb === '' || $page === '') continue;
        $out[] = [
            'title'  => smPlain((string)($r['title'] ?? '')),
            'page'   => $page,
            'src'    => smLink($r['properties']['url'] ?? ''),
            'thumb'  => $thumb,
            'host'   => smHost((string)($r['source'] ?? smHostOf($r))),
            'w'      => (int)($r['properties']['width'] ?? $r['thumbnail']['width'] ?? 0),
            'h'      => (int)($r['properties']['height'] ?? $r['thumbnail']['height'] ?? 0),
            'tw'     => (int)($r['thumbnail']['width'] ?? 0),
            'th'     => (int)($r['thumbnail']['height'] ?? 0),
            'favicon' => smImg($r['meta_url']['favicon'] ?? ''),
        ];
    }
    return ['results' => $out, 'more' => false, 'altered' => isset($d['query']['altered']) ? (string)$d['query']['altered'] : null];
}

function smNormNews(array $d): array
{
    $out = [];
    foreach ($d['results'] ?? [] as $r) {
        $u = smLink($r['url'] ?? '');
        if ($u === '') continue;
        $out[] = [
            'title'   => (string)($r['title'] ?? $u),
            'url'     => $u,
            'desc'    => (string)($r['description'] ?? ''),
            'host'    => smHostOf($r),
            'source'  => smPlain((string)($r['profile']['name'] ?? $r['source']['name'] ?? '')),
            'favicon' => smImg($r['meta_url']['favicon'] ?? ''),
            'age'     => (string)($r['age'] ?? ''),
            'thumb'   => smImg($r['thumbnail']['src'] ?? ''),
            'breaking' => !empty($r['breaking']),
        ];
    }
    return ['results' => $out, 'more' => (bool)($d['query']['more_results_available'] ?? count($out) >= 20),
            'altered' => isset($d['query']['altered']) ? (string)$d['query']['altered'] : null];
}

function smNormVideos(array $d): array
{
    $out = [];
    foreach ($d['results'] ?? [] as $r) {
        $u = smLink($r['url'] ?? '');
        if ($u === '') continue;
        $v = (array)($r['video'] ?? []);
        $out[] = [
            'title'    => (string)($r['title'] ?? $u),
            'url'      => $u,
            'desc'     => (string)($r['description'] ?? ''),
            'host'     => smHostOf($r),
            'favicon'  => smImg($r['meta_url']['favicon'] ?? ''),
            'age'      => (string)($r['age'] ?? ''),
            'thumb'    => smImg($r['thumbnail']['src'] ?? $v['thumbnail']['src'] ?? ''),
            'duration' => smPlain((string)($v['duration'] ?? '')),
            'creator'  => smPlain((string)($v['creator'] ?? $v['publisher'] ?? '')),
            'views'    => (int)($v['views'] ?? 0),
        ];
    }
    return ['results' => $out, 'more' => (bool)($d['query']['more_results_available'] ?? count($out) >= 20),
            'altered' => isset($d['query']['altered']) ? (string)$d['query']['altered'] : null];
}
