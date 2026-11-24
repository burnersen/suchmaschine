<?php
/* Suchmaschine – Bilder in voller Auflösung über den eigenen Server.
   Braves Bild-Proxy liefert nur Vorschaubilder. Für die große Ansicht holt der Server das Original und reicht es weiter:
   Der Browser lädt so weiterhin nur von hier (die CSP bleibt streng), und der fremde Server sieht nur die Adresse des Servers.
   Es kostet keine Suche bei Brave. Schutz gegen Missbrauch: Jede Adresse ist signiert (kein offener Proxy), nur öffentliche
   IP-Adressen, feste DNS-Auflösung, jede Weiterleitung wird neu geprüft, nur echte Pixelbilder, Größenlimit, nichts wird gespeichert. */
defined('SM_VERSION') || exit;

const SM_IMG_MAX = 15 * 1048576;

function smImgProxyOn(): bool
{
    return smSetting('img_proxy') === '1' && function_exists('curl_init');
}

/** Geheimer Schlüssel für die Signaturen, entsteht beim ersten Gebrauch */
function smImgSecret(): string
{
    $s = smSetting('img_secret');
    if (strlen($s) < 32) {
        $s = bin2hex(random_bytes(32));
        smSetSetting('img_secret', $s);
    }
    return $s;
}

function smImgSig(string $url): string
{
    return substr(hash_hmac('sha256', 'img|' . $url, smImgSecret()), 0, 32);
}

/** Adresse des großen Bildes über den eigenen Server oder '' */
function smImgProxyUrl(string $src): string
{
    if ($src === '' || !smImgProxyOn() || strlen($src) > 2000 || !preg_match('~^https?://~i', $src)) return '';
    return smUrl(['a' => 'img', 'u' => $src, 's' => smImgSig($src)]);
}

/** Ist das eine öffentliche IPv4-Adresse? (keine privaten, reservierten, lokalen Netze) */
function smPublicIp(string $ip): bool
{
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    $n = ip2long($ip);
    foreach ([['100.64.0.0', 10], ['192.0.0.0', 24], ['192.0.2.0', 24], ['198.18.0.0', 15], ['198.51.100.0', 24], ['203.0.113.0', 24], ['224.0.0.0', 3]] as $net) {
        $mask = -1 << (32 - $net[1]);
        if (($n & $mask) === (ip2long($net[0]) & $mask)) return false;
    }
    return true;
}

/** Holt ein Bild. Liefert [MIME-Typ, Inhalt] oder wirft. */
function smImgFetch(string $url): array
{
    for ($hop = 0; $hop < 4; $hop++) {
        $p = parse_url($url);
        $scheme = strtolower((string)($p['scheme'] ?? ''));
        $host = strtolower((string)($p['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || isset($p['user']) || isset($p['pass'])) throw new RuntimeException('url');
        $port = (int)($p['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (!in_array($port, [80, 443, 8080, 8443], true)) throw new RuntimeException('port');
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = $host;
        } else {
            $ips = @gethostbynamel($host);
            if (!$ips) throw new RuntimeException('dns');
            $ip = $ips[0];
            foreach ($ips as $i) if (!smPublicIp($i)) throw new RuntimeException('ip');
        }
        if (!smPublicIp($ip)) throw new RuntimeException('ip');

        $loc = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RESOLVE        => [$host . ':' . $port . ':' . $ip],   // genau die geprüfte Adresse, kein zweites DNS
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Suchmaschine/' . SM_VERSION . ')',
            CURLOPT_HTTPHEADER     => ['Accept: image/avif,image/webp,image/png,image/jpeg,image/gif;q=0.9,*/*;q=0.1'],
            CURLOPT_NOPROGRESS     => false,
            CURLOPT_PROGRESSFUNCTION => function ($c, $dlTotal, $dl) { return ($dlTotal > SM_IMG_MAX || $dl > SM_IMG_MAX) ? 1 : 0; },
            CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$loc) {
                if (stripos($line, 'location:') === 0) $loc = trim(substr($line, 9));
                return strlen($line);
            },
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false) throw new RuntimeException('fetch');
        if ($status >= 300 && $status < 400 && $loc !== '') {
            $url = smResolveUrl($url, $loc);
            continue;
        }
        if ($status !== 200 || $body === '' || strlen($body) > SM_IMG_MAX) throw new RuntimeException('status ' . $status);
        $info = @getimagesizefromstring($body);
        $mime = is_array($info) ? (string)($info['mime'] ?? '') : '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp'], true)) {
            // AVIF erkennt PHP erst ab 8.2 sicher
            if (substr($body, 4, 8) === 'ftypavif') $mime = 'image/avif'; else throw new RuntimeException('type');
        }
        return [$mime, $body];
    }
    throw new RuntimeException('redirects');
}

/** Relative Weiterleitungsziele auflösen */
function smResolveUrl(string $base, string $rel): string
{
    if (preg_match('~^https?://~i', $rel)) return $rel;
    $p = parse_url($base);
    $root = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    if (strpos($rel, '//') === 0) return $p['scheme'] . ':' . $rel;
    if (($rel[0] ?? '') === '/') return $root . $rel;
    $dir = preg_replace('~/[^/]*$~', '/', (string)($p['path'] ?? '/'));
    return $root . $dir . $rel;
}

function smServeImgProxy(): void
{
    $u = isset($_GET['u']) && is_string($_GET['u']) ? $_GET['u'] : '';
    $s = isset($_GET['s']) && is_string($_GET['s']) ? $_GET['s'] : '';
    if (!smImgProxyOn() || $u === '' || !hash_equals(smImgSig($u), $s)) {
        http_response_code(403);
        exit;
    }
    try {
        [$mime, $body] = smImgFetch($u);
    } catch (Throwable $e) {
        http_response_code(502);
        exit;
    }
    smHeaders();
    header("Content-Security-Policy: default-src 'none'; sandbox");
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: private, max-age=3600');
    echo $body;
    exit;
}
