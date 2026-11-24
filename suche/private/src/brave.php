<?php
declare(strict_types=1);
defined('PRIVATE_DIR') || exit;

/** Fehler mit nutzerfreundlicher Meldung */
class SearchException extends RuntimeException
{
}

/**
 * Sucht bei Brave (oder im Cache).
 *
 * @return array{results: array, more: bool, altered: ?string, cached: bool}
 */
function search(string $q, int $page, bool $exact = false): array
{
    $count  = max(1, min(20, (int) cfg('results_per_page')));
    $params = [
        'q'           => $q,
        'count'       => $count,
        'offset'      => $page - 1,
        'country'     => (string) cfg('country'),
        'search_lang' => (string) cfg('search_lang'),
        'ui_lang'     => (string) cfg('ui_lang'),
        'safesearch'  => (string) cfg('safesearch'),
        'spellcheck'  => $exact ? '0' : '1',
    ];
    $params   = array_filter($params, fn($v) => $v !== '');
    $cacheKey = 'web|' . json_encode($params);

    if ($hit = cache_get($cacheKey)) {
        $hit['cached'] = true;
        return $hit;
    }

    $key = trim((string) cfg('brave_api_key'));
    if ($key === '' || $key === 'HIER_DEIN_BRAVE_API_KEY') {
        throw new SearchException('Es ist noch kein Brave API-Key eingetragen. Bitte in private/config.php ergänzen.');
    }
    if (!usage_reserve()) {
        throw new SearchException('Das Monatslimit von ' . (int) cfg('monthly_limit')
            . ' Suchen ist erreicht. Ab dem 1. geht\'s weiter (oder Limit in der config.php erhöhen).');
    }

    $data   = brave_request($key, $params);
    $result = brave_normalize($data);
    cache_set($cacheKey, $result);
    $result['cached'] = false;
    return $result;
}

function brave_request(string $key, array $params): array
{
    $ch = curl_init((string) cfg('api_url') . '?' . http_build_query($params));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '', // gzip annehmen
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'X-Subscription-Token: ' . $key,
        ],
        CURLOPT_USERAGENT      => 'PrivateSearch/1.0',
    ]);
    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errno  = curl_errno($ch);
    $error  = curl_error($ch);
    curl_close($ch);

    if ($body === false || $errno) {
        error_log('Brave cURL-Fehler: ' . $error);
        throw new SearchException($errno === CURLE_OPERATION_TIMEDOUT
            ? 'Brave hat zu lange gebraucht. Bitte gleich nochmal versuchen.'
            : 'Brave ist gerade nicht erreichbar. Bitte später nochmal versuchen.');
    }

    $data   = json_decode((string) $body, true);
    $detail = is_array($data) ? (string) ($data['error']['detail'] ?? '') : '';

    if ($status !== 200 || !is_array($data)) {
        error_log("Brave HTTP $status: " . substr((string) $body, 0, 500));
        $msg = match (true) {
            $status === 401, $status === 403 => 'Der API-Key wird nicht akzeptiert. Stimmt der Key in der config.php und ist im Brave-Dashboard ein Plan aktiv?',
            $status === 402                  => 'Das Brave-Guthaben ist aufgebraucht.',
            $status === 422                  => 'Brave hat die Anfrage abgelehnt (ungültige Parameter oder Key).',
            $status === 429                  => 'Zu viele Anfragen oder Kontingent erschöpft. Kurz warten oder im Brave-Dashboard nachsehen.',
            $status >= 500                   => 'Brave hat gerade technische Probleme. Bitte später nochmal versuchen.',
            default                          => 'Unerwartete Antwort von Brave (HTTP ' . $status . ').',
        };
        if ($detail !== '') {
            $msg .= ' (Brave: ' . $detail . ')';
        }
        throw new SearchException($msg);
    }
    return $data;
}

/** Nur die Felder behalten, die wir anzeigen */
function brave_normalize(array $data): array
{
    $results = [];
    foreach ($data['web']['results'] ?? [] as $r) {
        $url = (string) ($r['url'] ?? '');
        if (!preg_match('~^https?://~i', $url)) {
            continue;
        }
        $favicon = (string) ($r['meta_url']['favicon'] ?? '');
        $results[] = [
            'title'    => (string) ($r['title'] ?? $url),
            'url'      => $url,
            'desc'     => (string) ($r['description'] ?? ''),
            'host'     => (string) ($r['meta_url']['hostname'] ?? parse_url($url, PHP_URL_HOST)),
            'path'     => (string) ($r['meta_url']['path'] ?? ''),
            'favicon'  => str_starts_with($favicon, 'https://imgs.search.brave.com/') ? $favicon : '',
            'age'      => (string) ($r['age'] ?? ''),
        ];
    }
    return [
        'results' => $results,
        'more'    => (bool) ($data['query']['more_results_available'] ?? false),
        'altered' => isset($data['query']['altered']) ? (string) $data['query']['altered'] : null,
    ];
}
