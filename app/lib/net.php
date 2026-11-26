<?php
/* Suchmaschine – Abrufe nach draußen für die Netz-Werkzeuge (Link-Röntgen, Header, Erreichbarkeit, Link-Check, Link-Vorschau, SSL).
   Nach dem Vorbild von LinkVault und CodeForge (gleicher Autor, gleiche Lizenz):
   nur öffentliche IP-Adressen (IPv4 und IPv6, auch versteckt in IPv6-Schreibweisen), die geprüfte Adresse wird festgenagelt,
   jede Weiterleitung wird einzeln neu geprüft, Größe und Zeit sind begrenzt. Nichts davon wird gespeichert. */
defined('SM_VERSION') || exit;

const SM_NET_UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36 Suchmaschine-Werkzeuge';
const SM_NET_PORTS = [80, 443, 8080, 8443];

/** Öffentliche IP-Adresse? IPv4 und IPv6, eingebettete IPv4 (::ffff:…, NAT64, 6to4) werden ausgepackt und mitgeprüft. */
function smNetIpPublic(string $ip): bool
{
    $bin = @inet_pton(trim($ip, '[]'));
    if ($bin === false) return false;
    if (strlen($bin) === 16) {
        $v4 = null;
        if (strncmp($bin, str_repeat("\0", 10) . "\xff\xff", 12) === 0 || strncmp($bin, str_repeat("\0", 12), 12) === 0
            || strncmp($bin, "\x00\x64\xff\x9b" . str_repeat("\0", 8), 12) === 0) {
            $v4 = substr($bin, 12, 4);
        } elseif (strncmp($bin, "\x00\x64\xff\x9b\x00\x01", 6) === 0) {
            return false;   // lokales NAT64
        } elseif (strncmp($bin, "\x20\x02", 2) === 0) {
            $v4 = substr($bin, 2, 4);
        }
        if ($v4 !== null) return smPublicIp((string)inet_ntop($v4));
        $b0 = ord($bin[0]);
        $b1 = ord($bin[1]);
        if (($b0 & 0xFE) === 0xFC) return false;                  // fc00::/7
        if ($b0 === 0xFE && ($b1 & 0xC0) === 0x80) return false;  // fe80::/10
        if ($b0 === 0xFE && ($b1 & 0xC0) === 0xC0) return false;  // fec0::/10
        if ($b0 === 0xFF) return false;                           // Multicast
        if (strncmp($bin, "\x20\x01\x0d\xb8", 4) === 0) return false;   // Dokumentation
        return (bool)filter_var((string)inet_ntop($bin), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    return smPublicIp((string)inet_ntop($bin));
}

/** Name → ['state' => ok|private|nx, 'ip' => erste Adresse, 'ips' => alle]. Alle Adressen müssen öffentlich sein. */
function smNetHost(string $host): array
{
    $h = strtolower(trim($host, '[]'));
    if (filter_var($h, FILTER_VALIDATE_IP)) return smNetIpPublic($h) ? ['state' => 'ok', 'ip' => $h, 'ips' => [$h]] : ['state' => 'private', 'ip' => '', 'ips' => []];
    if ($h === '' || $h === 'localhost' || strpos($h, '.') === false || preg_match('/\.(local|localhost|internal|lan|home|intranet|corp)$/', $h)) return ['state' => 'private', 'ip' => '', 'ips' => []];
    $h = smNetAscii($h);
    $all = @gethostbynamel($h) ?: [];
    if (function_exists('dns_get_record')) {
        foreach ((@dns_get_record($h, DNS_AAAA) ?: []) as $r) if (!empty($r['ipv6'])) $all[] = $r['ipv6'];
    }
    if (!$all) return ['state' => 'nx', 'ip' => '', 'ips' => []];
    foreach ($all as $ip) if (!smNetIpPublic($ip)) return ['state' => 'private', 'ip' => '', 'ips' => []];
    return ['state' => 'ok', 'ip' => $all[0], 'ips' => array_values(array_unique($all))];
}

/** Umlaut-Domain in Punycode (bücher.de → xn--bcher-kva.de), wenn intl da ist */
function smNetAscii(string $host): string
{
    if (preg_match('/[^\x20-\x7e]/', $host) && function_exists('idn_to_ascii')) {
        $a = @idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if (is_string($a) && $a !== '') return $a;
    }
    return $host;
}

/** Eingabe zu einer vollständigen Adresse: „example.org“ → „https://example.org/“. '' = unbrauchbar */
function smNetUrl(string $in): string
{
    $u = trim($in);
    if ($u === '' || strlen($u) > 2000 || preg_match('/[\s<>"]/', $u)) return '';
    if (!preg_match('~^[a-z][a-z0-9+.\-]*://~i', $u)) $u = 'https://' . ltrim($u, '/');
    $p = parse_url($u);
    if (!$p || empty($p['host']) || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || isset($p['user']) || isset($p['pass'])) return '';
    if (!isset($p['path'])) $u .= '/';
    return $u;
}

function smNetAbs(string $base, string $loc): string
{
    $loc = trim($loc);
    if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $loc)) return $loc;
    $b = parse_url($base);
    $scheme = strtolower($b['scheme'] ?? 'https');
    $origin = $scheme . '://' . ($b['host'] ?? '') . (isset($b['port']) ? ':' . $b['port'] : '');
    if (strncmp($loc, '//', 2) === 0) return $scheme . ':' . $loc;
    if ($loc !== '' && $loc[0] === '/') return $origin . $loc;
    if ($loc !== '' && $loc[0] === '?') return $origin . ($b['path'] ?? '/') . $loc;
    $path = $b['path'] ?? '/';
    $dir = substr($path, 0, (int)strrpos($path, '/') + 1) ?: '/';
    return $origin . $dir . $loc;
}

/** Prüft eine Adresse, bevor sie abgerufen wird. Liefert [Fehlercode oder '', Teile, IP] */
function smNetPrepare(string $url): array
{
    $p = parse_url($url);
    if (!$p || empty($p['host']) || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || isset($p['user']) || isset($p['pass'])) return ['scheme', $p ?: [], ''];
    $port = (int)($p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80));
    if (!in_array($port, SM_NET_PORTS, true)) return ['port', $p, ''];
    $r = smNetHost((string)$p['host']);
    if ($r['state'] === 'private') return ['blocked', $p, ''];
    if ($r['state'] === 'nx') return ['nx', $p, ''];
    if (smNetIsSelf($p)) return ['self', $p, ''];
    return ['', $p, $r['ip']];
}

/** Zeigt die Adresse auf diese Suchmaschine selbst? (kein Abruf im Kreis) */
function smNetIsSelf(array $p): bool
{
    $own = strtolower((string)parse_url(smOrigin(), PHP_URL_HOST));
    return $own !== '' && strtolower((string)$p['host']) === $own && strpos((string)($p['path'] ?? '/'), rtrim(smBase(), '/')) === 0;
}

/**
 * Ein einzelner Abruf ohne Weiterleitungen. $o: method (GET|HEAD), limit (Bytes), timeout, accept.
 * Liefert status, headers [[Name, Wert] …], body, type, ip, ms, http, error ('' = gut), size
 */
function smNetOnce(string $url, array $o = []): array
{
    $out = ['url' => $url, 'status' => 0, 'headers' => [], 'body' => '', 'type' => '', 'ip' => '', 'ms' => 0, 'http' => '', 'error' => '', 'size' => 0];
    [$err, $p, $ip] = smNetPrepare($url);
    if ($err !== '') { $out['error'] = $err; return $out; }
    if (!function_exists('curl_init')) { $out['error'] = 'nocurl'; return $out; }
    $out['ip'] = $ip;
    $limit = (int)($o['limit'] ?? 262144);
    $head = strtoupper((string)($o['method'] ?? 'GET')) === 'HEAD';
    $port = (int)($p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80));
    $headers = [];
    $body = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => $head,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => min(5, (int)($o['timeout'] ?? 8)),
        CURLOPT_TIMEOUT => (int)($o['timeout'] ?? 8),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => SM_NET_UA,
        CURLOPT_ENCODING => '',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Accept: ' . ($o['accept'] ?? 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.5'), 'Accept-Language: ' . (smLang() === 'de' ? 'de-DE,de;q=0.9,en;q=0.6' : 'en;q=0.9,de;q=0.5')],
        CURLOPT_RESOLVE => [smNetAscii(trim((string)$p['host'], '[]')) . ':' . $port . ':' . (strpos($ip, ':') !== false ? '[' . $ip . ']' : $ip)],
        CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$headers) {
            $t = trim($line);
            if (preg_match('~^HTTP/\S+\s+\d+~', $t)) $headers = [];   // neue Antwort (z. B. nach 100 Continue)
            elseif ($t !== '' && strpos($t, ':') !== false) { [$k, $v] = explode(':', $t, 2); $headers[] = [trim($k), trim($v)]; }
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION => function ($c, $data) use (&$body, $limit) {
            $body .= $data;
            return strlen($body) > $limit ? 0 : strlen($data);
        },
    ]);
    $t0 = microtime(true);
    curl_exec($ch);
    $out['ms'] = (int)round((microtime(true) - $t0) * 1000);
    $out['status'] = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $out['type'] = strtolower((string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
    $ver = (int)curl_getinfo($ch, CURLINFO_HTTP_VERSION);
    $out['http'] = [1 => '1.0', 2 => '1.1', 3 => '2', 4 => '2', 30 => '3'][$ver] ?? '';
    $errno = curl_errno($ch);
    if ($out['status'] === 0) $out['error'] = $errno === 6 ? 'nx' : ($errno === 28 ? 'timeout' : ($errno === 60 || $errno === 51 || $errno === 35 ? 'tls' : 'connect'));
    if (PHP_VERSION_ID < 80000) curl_close($ch);
    $out['headers'] = $headers;
    $out['body'] = $body;
    $out['size'] = strlen($body);
    return $out;
}

/** Wert eines Kopfes (der letzte gewinnt) oder '' */
function smNetHeader(array $headers, string $name): string
{
    $v = '';
    foreach ($headers as [$k, $val]) if (strcasecmp($k, $name) === 0) $v = $val;
    return $v;
}

/**
 * Folgt einer Adresse Schritt für Schritt (HTTP-Weiterleitung, Refresh-Kopf, <meta http-equiv="refresh">).
 * $o wie smNetOnce, dazu 'max' (Stationen) und 'meta' (auch Meta-Refresh folgen). Liefert ['hops' => […], 'final' => Adresse, 'last' => letzter Abruf]
 */
function smNetChain(string $url, array $o = []): array
{
    $hops = [];
    $seen = [];
    $last = null;
    $max = (int)($o['max'] ?? 10);
    for ($i = 0; $i < $max; $i++) {
        if (isset($seen[$url])) { $hops[] = ['url' => $url, 'status' => 0, 'note' => 'loop']; break; }
        $seen[$url] = true;
        $r = smNetOnce($url, $o);
        // Manche Server mögen HEAD nicht
        if (($o['method'] ?? 'GET') === 'HEAD' && ($r['status'] === 0 && $r['error'] === 'connect' || in_array($r['status'], [400, 403, 404, 405, 500, 501], true))) $r = smNetOnce($url, ['method' => 'GET'] + $o);
        $last = $r;
        $next = '';
        $loc = smNetHeader($r['headers'], 'location');
        $refresh = smNetHeader($r['headers'], 'refresh');
        if ($r['status'] >= 300 && $r['status'] < 400 && $loc !== '') $next = smNetAbs($url, $loc);
        elseif ($refresh !== '' && preg_match('/^\s*\d+\s*;\s*url\s*=\s*(.+)$/i', $refresh, $m)) $next = smNetAbs($url, trim($m[1], " '\""));
        elseif (!empty($o['meta']) && $r['status'] === 200 && preg_match('~<meta[^>]+http-equiv\s*=\s*["\']?refresh["\']?[^>]*content\s*=\s*["\']?\s*\d+\s*;\s*url\s*=\s*([^"\'>\s]+)~i', $r['body'], $m)) {
            $next = smNetAbs($url, html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }
        $hop = ['url' => $url, 'status' => $r['status'], 'type' => $r['type'], 'ms' => $r['ms'], 'ip' => $r['ip'], 'http' => $r['http']];
        if (!empty($o['headers'])) $hop['headers'] = $r['headers'];
        if ($r['error'] !== '') $hop['note'] = $r['error'];
        if ($next !== '') $hop['next'] = $next;
        $hops[] = $hop;
        if ($next === '' || $r['error'] !== '') break;
        $url = $next;
    }
    $end = end($hops);
    return ['hops' => $hops, 'final' => $end['next'] ?? $end['url'], 'last' => $last];
}

/**
 * Viele Adressen auf einmal prüfen (Link-Checker): je Runde parallel, Weiterleitungen in der nächsten Runde.
 * Liefert je Adresse ['url', 'status', 'final', 'ms', 'error', 'hops']
 */
function smNetCheckMany(array $urls, int $timeout = 8): array
{
    $res = [];
    $todo = [];
    foreach (array_values($urls) as $i => $u) {
        $res[$i] = ['url' => $u, 'status' => 0, 'final' => $u, 'ms' => 0, 'error' => '', 'hops' => 0];
        $todo[$i] = $u;
    }
    if (!function_exists('curl_multi_init')) {
        foreach ($todo as $i => $u) {
            $c = smNetChain($u, ['method' => 'HEAD', 'timeout' => $timeout, 'max' => 6, 'limit' => 4096]);
            $end = end($c['hops']);
            $res[$i] = ['url' => $u, 'status' => (int)$end['status'], 'final' => $end['url'], 'ms' => array_sum(array_column($c['hops'], 'ms')), 'error' => (string)($end['note'] ?? ''), 'hops' => count($c['hops']) - 1];
        }
        return $res;
    }
    for ($round = 0; $round < 6 && $todo; $round++) {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($todo as $i => $u) {
            [$err, $p, $ip] = smNetPrepare($u);
            if ($err !== '') { $res[$i]['error'] = $err; $res[$i]['final'] = $u; continue; }
            $port = (int)($p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80));
            $ch = curl_init($u);
            $loc = '';
            curl_setopt_array($ch, [
                CURLOPT_NOBODY => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => $timeout, CURLOPT_USERAGENT => SM_NET_UA,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_RESOLVE => [smNetAscii(trim((string)$p['host'], '[]')) . ':' . $port . ':' . (strpos($ip, ':') !== false ? '[' . $ip . ']' : $ip)],
                CURLOPT_HEADER => true,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$i] = $ch;
        }
        $running = 0;
        do {
            curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh, 1.0);
        } while ($running > 0);
        $next = [];
        foreach ($handles as $i => $ch) {
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $res[$i]['ms'] += (int)round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
            $head = (string)curl_multi_getcontent($ch);
            $errno = curl_errno($ch);
            curl_multi_remove_handle($mh, $ch);
            // HEAD abgelehnt: in der nächsten Runde mit GET probieren wäre aufwendig, 405 zählt darum als erreichbar
            $res[$i]['status'] = $status;
            $res[$i]['final'] = $todo[$i];
            if ($status === 0) { $res[$i]['error'] = $errno === 6 ? 'nx' : ($errno === 28 ? 'timeout' : ($errno === 60 || $errno === 51 || $errno === 35 ? 'tls' : 'connect')); continue; }
            if ($status >= 300 && $status < 400 && preg_match('/^location:\s*(.+)$/mi', $head, $m)) {
                $to = smNetAbs($todo[$i], trim($m[1]));
                if ($to !== $todo[$i] && $res[$i]['hops'] < 6) { $res[$i]['hops']++; $next[$i] = $to; }
            }
        }
        curl_multi_close($mh);
        $todo = $next;
    }
    return array_values($res);
}

/** Verständlicher Text zu einem Fehlercode aus smNetOnce */
function smNetErrText(string $e): string
{
    $m = [
        'scheme' => t('Nur Adressen mit http:// oder https:// gehen.'),
        'port' => t('Dieser Port wird nicht abgerufen (erlaubt sind 80, 443, 8080 und 8443).'),
        'blocked' => t('Das Ziel liegt in einem privaten Netz und wird nicht abgerufen.'),
        'self' => t('Das ist diese Suchmaschine selbst.'),
        'nx' => t('Diese Domain gibt es nicht (keine DNS-Antwort).'),
        'timeout' => t('Keine Antwort innerhalb der Wartezeit.'),
        'tls' => t('Die verschlüsselte Verbindung ist fehlgeschlagen (Zertifikat ungültig oder abgelaufen?).'),
        'connect' => t('Keine Verbindung möglich.'),
        'loop' => t('Die Weiterleitungen drehen sich im Kreis.'),
        'nocurl' => t('Auf diesem Server fehlt die PHP-Erweiterung curl.'),
    ];
    return $m[$e] ?? $e;
}
