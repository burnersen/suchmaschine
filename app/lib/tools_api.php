<?php
/* Suchmaschine – Schnittstelle der Werkzeug-Apps: POST …/?a=tools&api=<aktion> mit csrf und data (JSON).
   Antwort immer JSON: {ok: true, …} oder {ok: false, message}. Nur für angemeldete Konten (die Netz-Werkzeuge rufen fremde Server ab).
   Nichts davon fragt einen Suchdienst, nichts kostet Guthaben, nichts wird gespeichert (außer Kurzlinks und Schnipsel). */
defined('SM_VERSION') || exit;

function smToolsApi(?array $me, string $action): void
{
    smHeaders();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $send = function (array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); exit; };
    $fail = function (string $m) use ($send): void { $send(['ok' => false, 'message' => $m]); };
    if (!$me) $fail(t('Bitte zuerst anmelden.'));
    $d = json_decode((string)($_POST['data'] ?? '{}'), true);
    if (!is_array($d)) $d = [];
    $s = function (string $k, int $max = 2000) use ($d): string { return isset($d[$k]) && (is_string($d[$k]) || is_numeric($d[$k])) ? mb_substr(trim((string)$d[$k]), 0, $max) : ''; };
    @set_time_limit(60);
    try {
        switch ($action) {
            /* ---------- Netz ---------- */
            case 'resolve':
                $u = smNetUrl($s('url'));
                if ($u === '') $fail(t('Bitte eine vollständige Adresse eingeben, z. B. https://example.org/seite.'));
                $c = smNetChain($u, ['method' => 'HEAD', 'limit' => 65536, 'timeout' => 6, 'max' => 10, 'meta' => true]);
                $hops = array_map(function ($h) { return ['url' => $h['url'], 'status' => $h['status'], 'note' => $h['note'] ?? '', 'next' => $h['next'] ?? '']; }, $c['hops']);
                $send(['ok' => true, 'hops' => $hops, 'final' => $c['final']]);
            case 'headers':
                $u = smNetUrl($s('url'));
                if ($u === '') $fail(t('Bitte eine vollständige Adresse eingeben, z. B. https://example.org/seite.'));
                $c = smNetChain($u, ['method' => $s('method') === 'HEAD' ? 'HEAD' : 'GET', 'limit' => 524288, 'timeout' => 10, 'max' => 10, 'headers' => true]);
                $last = $c['last'] ?? [];
                $send(['ok' => true, 'hops' => smApiHops($c['hops']), 'final' => $c['final'], 'security' => smSecurityHeaders($last['headers'] ?? [], $c['final']),
                       'size' => (int)($last['size'] ?? 0)]);
            case 'down':
                $u = smNetUrl($s('url'));
                if ($u === '') $fail(t('Bitte eine vollständige Adresse eingeben, z. B. https://example.org/seite.'));
                $host = (string)parse_url($u, PHP_URL_HOST);
                $dns = smNetHost($host);
                $c = smNetChain($u, ['method' => 'GET', 'limit' => 65536, 'timeout' => 12, 'max' => 8]);
                $end = end($c['hops']);
                $st = (int)$end['status'];
                $verdict = $st >= 200 && $st < 400 ? 'up' : ($st >= 400 && $st < 500 && !in_array($st, [404, 410], true) ? 'blocked' : ($st >= 500 ? 'error' : ($st === 404 || $st === 410 ? 'missing' : 'down')));
                $send(['ok' => true, 'verdict' => $verdict, 'status' => $st, 'final' => $c['final'], 'ms' => array_sum(array_column($c['hops'], 'ms')), 'hops' => count($c['hops']),
                       'ip' => $end['ip'] ?? '', 'ips' => $dns['ips'], 'dns' => $dns['state'], 'http' => $end['http'] ?? '', 'error' => isset($end['note']) ? smNetErrText((string)$end['note']) : '', 'checked' => time()]);
            case 'check':
                $list = [];
                foreach ((array)($d['urls'] ?? []) as $u) {
                    if (!is_string($u)) continue;
                    $n = smNetUrl($u);
                    if ($n !== '') $list[] = $n;
                    if (count($list) >= 50) break;
                }
                if (!$list) $fail(t('Keine gültigen Adressen gefunden.'));
                $res = smNetCheckMany($list, 8);
                foreach ($res as &$r) $r['errText'] = $r['error'] !== '' ? smNetErrText($r['error']) : '';
                unset($r);
                $send(['ok' => true, 'results' => $res]);
            case 'preview':
                $u = smNetUrl($s('url'));
                if ($u === '') $fail(t('Bitte eine vollständige Adresse eingeben, z. B. https://example.org/seite.'));
                $send(['ok' => true] + smLinkPreview($u));
            case 'dns':
                $send(['ok' => true] + smDnsLookup($s('name', 300)));
            case 'whois':
                $send(['ok' => true] + smWhois($s('q', 300)));
            case 'ssl':
                $send(['ok' => true] + smSslCheck($s('host', 300), (int)$s('port', 6)));
            case 'htpasswd':
                $user = $s('user', 100);
                $pass = isset($d['pass']) && is_string($d['pass']) ? $d['pass'] : '';
                if ($user === '' || strpos($user, ':') !== false || preg_match('/\s/', $user)) $fail(t('Der Benutzername darf keine Leerzeichen und keinen Doppelpunkt enthalten.'));
                if ($pass === '' || strlen($pass) > 72) $fail(t('Bitte ein Passwort mit 1 bis 72 Zeichen eingeben.'));
                $line = $s('algo') === 'sha' ? $user . ':{SHA}' . base64_encode(sha1($pass, true)) : $user . ':' . password_hash($pass, PASSWORD_BCRYPT, ['cost' => 10]);
                $send(['ok' => true, 'line' => $line]);
            case 'fxhist':
                $send(['ok' => true] + smFxHistory(strtoupper($s('cur', 3)), (int)$s('days', 4)));

            /* ---------- Kurzlinks ---------- */
            case 'short_list':
                $send(['ok' => true, 'links' => smShortList($me['id']), 'on' => smSetting('shortener') === '1']);
            case 'short_create':
            case 'short_update':
                if (smSetting('shortener') !== '1') $fail(t('Kurzlinks sind ausgeschaltet.'));
                $o = [];
                foreach (['url', 'code', 'note', 'pass'] as $k) if (array_key_exists($k, $d)) $o[$k] = is_string($d[$k]) ? $d[$k] : '';
                foreach (['max_hits', 'active', 'strip', 'title'] as $k) if (array_key_exists($k, $d)) $o[$k] = (int)$d[$k];
                if (array_key_exists('expires', $d)) {
                    $e = is_string($d['expires']) ? trim($d['expires']) : '';
                    $o['expires'] = $e === '' ? 0 : (preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2})?$/', $e) ? (int)strtotime(str_replace('T', ' ', $e) . (strlen($e) === 10 ? ' 23:59:59' : '')) : 0);
                }
                if ($action === 'short_create') {
                    [$code, $err] = smShortCreate($me['id'], (string)($o['url'] ?? ''), $o + ['title' => 1]);
                    if ($err !== '') $fail($err);
                } else {
                    $code = $s('old', 40);
                    $err = smShortUpdate($me['id'], $code, $o);
                    if ($err !== '') $fail($err);
                    if (!empty($o['code'])) $code = (string)$o['code'];
                }
                $row = smQ('SELECT * FROM shortlinks WHERE code = ? AND user_id = ?', [$code, $me['id']])->fetch();
                $send(['ok' => true, 'link' => $row ? smShortPublic($row) : null]);
            case 'short_delete':
                smShortDelete($me['id'], $s('code', 40));
                $send(['ok' => true]);
            case 'short_stats':
                $send(['ok' => true, 'days' => smShortStats($me['id'], $s('code', 40), (int)$s('days', 4) ?: 30)]);
            case 'short_check':
                $r = smShortCheck($me['id']);
                $send(['ok' => true] + $r + ['links' => smShortList($me['id'])]);

            /* ---------- Text-Schnipsel ---------- */
            case 'note_create':
                if (smSetting('shortener') !== '1') $fail(t('Kurzlinks und Text-Schnipsel sind ausgeschaltet.'));
                [$id, $err] = smNoteCreate($me['id'], is_string($d['data'] ?? null) ? $d['data'] : '', (int)($d['hours'] ?? 24), (int)($d['views'] ?? 1), !empty($d['pass']), $s('label', 80), $s('auth', 64));
                if ($err !== '') $fail($err);
                $send(['ok' => true, 'id' => $id, 'url' => smOrigin() . '?n=' . $id]);
            case 'note_list':
                $send(['ok' => true, 'notes' => smNoteList($me['id']), 'base' => smOrigin() . '?n=']);
            case 'note_delete':
                smNoteDelete($me['id'], $s('id', 40));
                $send(['ok' => true]);
        }
    } catch (Throwable $e) {
        $fail(t('Das hat nicht geklappt: %s', $e->getMessage()));
    }
    http_response_code(404);
    $fail(t('Unbekannte Aktion.'));
}

/** Stationen für die Oberfläche: Kopfzeilen als Liste, Fehler als Text */
function smApiHops(array $hops): array
{
    return array_map(function ($h) {
        return ['url' => $h['url'], 'status' => $h['status'], 'ms' => $h['ms'] ?? 0, 'ip' => $h['ip'] ?? '', 'http' => $h['http'] ?? '', 'type' => $h['type'] ?? '',
                'headers' => $h['headers'] ?? [], 'error' => isset($h['note']) ? smNetErrText((string)$h['note']) : '', 'next' => $h['next'] ?? ''];
    }, $hops);
}

/** Sicherheits-Kopfzeilen bewerten: [[Name, gut|mittel|fehlt, Hinweis], …] */
function smSecurityHeaders(array $headers, string $url): array
{
    $hv = function ($n) use ($headers) { return smNetHeader($headers, $n); };
    $https = stripos($url, 'https://') === 0;
    $out = [];
    $hsts = $hv('strict-transport-security');
    $age = preg_match('/max-age\s*=\s*(\d+)/i', $hsts, $m) ? (int)$m[1] : 0;
    $out[] = ['Strict-Transport-Security', !$https ? 'bad' : ($hsts === '' ? 'bad' : ($age >= 15552000 ? 'ok' : 'mid')), $hsts !== '' ? $hsts : t('Fehlt: Browser könnten auch unverschlüsselt verbinden.')];
    $csp = $hv('content-security-policy');
    $out[] = ['Content-Security-Policy', $csp === '' ? 'bad' : (preg_match("/'unsafe-inline'|'unsafe-eval'|\\*/", $csp) ? 'mid' : 'ok'), $csp !== '' ? $csp : t('Fehlt: schützt gegen eingeschleuste Skripte (XSS).')];
    $xcto = $hv('x-content-type-options');
    $out[] = ['X-Content-Type-Options', strtolower($xcto) === 'nosniff' ? 'ok' : 'bad', $xcto !== '' ? $xcto : t('Fehlt: sollte „nosniff“ sein.')];
    $xfo = $hv('x-frame-options');
    $fa = stripos($csp, 'frame-ancestors') !== false;
    $out[] = ['X-Frame-Options', $xfo !== '' || $fa ? 'ok' : 'bad', $xfo !== '' ? $xfo : ($fa ? t('über CSP frame-ancestors geregelt') : t('Fehlt: Seite kann in fremde Rahmen eingebettet werden (Clickjacking).'))];
    $rp = $hv('referrer-policy');
    $out[] = ['Referrer-Policy', $rp === '' ? 'mid' : (preg_match('/no-referrer|same-origin|strict-origin/i', $rp) ? 'ok' : 'mid'), $rp !== '' ? $rp : t('Fehlt: der Browser nimmt dann meist strict-origin-when-cross-origin.')];
    $pp = $hv('permissions-policy');
    $out[] = ['Permissions-Policy', $pp !== '' ? 'ok' : 'mid', $pp !== '' ? $pp : t('Fehlt: regelt Kamera, Mikrofon, Standort und Co.')];
    $coop = $hv('cross-origin-opener-policy');
    $out[] = ['Cross-Origin-Opener-Policy', $coop !== '' ? 'ok' : 'mid', $coop !== '' ? $coop : t('Fehlt (optional).')];
    foreach (['server', 'x-powered-by', 'x-aspnet-version'] as $leak) {
        $v = $hv($leak);
        if ($v !== '' && preg_match('/\d/', $v)) $out[] = [ucwords($leak, '-'), 'mid', t('Verrät Software und Version: %s', $v)];
    }
    return $out;
}

/** Link-Vorschau: Titel, Beschreibung, Open-Graph und Twitter, Bild und Icon als data:-Adresse (sonst blockiert die CSP fremde Bilder) */
function smLinkPreview(string $url): array
{
    $c = smNetChain($url, ['method' => 'GET', 'limit' => 786432, 'timeout' => 10, 'max' => 8]);
    $r = $c['last'] ?? [];
    $end = end($c['hops']);
    if (($r['status'] ?? 0) === 0) throw new RuntimeException(smNetErrText((string)($end['note'] ?? 'connect')));
    $html = (string)($r['body'] ?? '');
    if (!preg_match('~charset\s*=\s*["\']?utf-?8~i', (string)$r['type'] . substr($html, 0, 3000)) && !mb_check_encoding($html, 'UTF-8')) $html = mb_convert_encoding($html, 'UTF-8', 'Windows-1252');
    $head = preg_match('~<head\b.*?</head>~is', $html, $hm) ? $hm[0] : substr($html, 0, 200000);
    $meta = [];
    if (preg_match_all('~<meta\b[^>]*>~i', $head, $mm)) {
        foreach ($mm[0] as $tag) {
            $a = smHtmlAttrs($tag);
            $k = strtolower($a['property'] ?? $a['name'] ?? $a['itemprop'] ?? '');
            if ($k !== '' && isset($a['content']) && !isset($meta[$k])) $meta[$k] = mb_substr(trim(html_entity_decode($a['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, 1000);
        }
    }
    $title = preg_match('~<title[^>]*>(.*?)</title>~is', $head, $tm) ? trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($tm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) : '';
    $links = [];
    if (preg_match_all('~<link\b[^>]*>~i', $head, $lm)) foreach ($lm[0] as $tag) { $a = smHtmlAttrs($tag); if (isset($a['rel'], $a['href'])) $links[] = $a; }
    $final = $c['final'];
    $icon = '';
    $canon = '';
    foreach ($links as $a) {
        $rel = strtolower($a['rel']);
        if ($canon === '' && $rel === 'canonical') $canon = smNetAbs($final, $a['href']);
        if ($icon === '' && preg_match('/(^|\s)(icon|apple-touch-icon)(\s|$)/', $rel)) $icon = smNetAbs($final, $a['href']);
    }
    if ($icon === '') $icon = smNetAbs($final, '/favicon.ico');
    $img = $meta['og:image'] ?? $meta['og:image:url'] ?? $meta['twitter:image'] ?? $meta['twitter:image:src'] ?? '';
    $out = [
        'final' => $final, 'status' => (int)$r['status'], 'title' => mb_substr($title, 0, 300),
        'desc' => $meta['description'] ?? '', 'site' => $meta['og:site_name'] ?? '', 'type' => $meta['og:type'] ?? '',
        'ogTitle' => $meta['og:title'] ?? '', 'ogDesc' => $meta['og:description'] ?? '', 'ogImage' => $img !== '' ? smNetAbs($final, $img) : '',
        'twCard' => $meta['twitter:card'] ?? '', 'twTitle' => $meta['twitter:title'] ?? '', 'twDesc' => $meta['twitter:description'] ?? '',
        'theme' => $meta['theme-color'] ?? '', 'canonical' => $canon, 'lang' => preg_match('~<html[^>]*\blang\s*=\s*["\']?([a-zA-Z-]{2,10})~i', $html, $lg) ? $lg[1] : '',
        'robots' => $meta['robots'] ?? '', 'meta' => $meta, 'image' => '', 'imageInfo' => '', 'icon' => '',
    ];
    if ($out['ogImage'] !== '') {
        [$out['image'], $out['imageInfo']] = smFetchImageData($out['ogImage'], 2500000);
    }
    [$out['icon']] = smFetchImageData($icon, 200000);
    return $out;
}

/** Attribute eines HTML-Tags als Liste (Namen klein) */
function smHtmlAttrs(string $tag): array
{
    $a = [];
    if (preg_match_all('~([a-zA-Z:_-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))~', $tag, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) $a[strtolower($x[1])] = $x[3] !== '' ? $x[3] : ($x[4] !== '' ? $x[4] : ($x[5] ?? ''));
    }
    return $a;
}

/** Bild sicher holen und als data:-Adresse liefern: [data-URI oder '', Info] */
function smFetchImageData(string $url, int $max): array
{
    if (!preg_match('~^https?://~i', $url)) return ['', ''];
    $c = smNetChain($url, ['method' => 'GET', 'limit' => $max + 1, 'timeout' => 8, 'max' => 5, 'accept' => 'image/avif,image/webp,image/png,image/svg+xml,image/*;q=0.8']);
    $r = $c['last'] ?? [];
    $body = (string)($r['body'] ?? '');
    if (($r['status'] ?? 0) !== 200 || $body === '' || strlen($body) > $max) return ['', strlen($body) > $max ? t('Bild ist zu groß') : ''];
    $type = smSniffImage($body);
    if ($type === '') return ['', ''];
    $info = '';
    if ($type !== 'image/svg+xml' && function_exists('getimagesizefromstring')) {
        $sz = @getimagesizefromstring($body);
        if ($sz) $info = $sz[0] . ' × ' . $sz[1] . ' px';
    }
    $info .= ($info !== '' ? ' · ' : '') . smFmtNumber(round(strlen($body) / 1024)) . ' KB';
    return ['data:' . $type . ';base64,' . base64_encode($body), $info];
}

function smSniffImage(string $b): string
{
    if (strncmp($b, "\x89PNG", 4) === 0) return 'image/png';
    if (strncmp($b, "\xFF\xD8\xFF", 3) === 0) return 'image/jpeg';
    if (strncmp($b, 'GIF8', 4) === 0) return 'image/gif';
    if (strncmp($b, 'RIFF', 4) === 0 && substr($b, 8, 4) === 'WEBP') return 'image/webp';
    if (substr($b, 4, 8) === 'ftypavif') return 'image/avif';
    if (strncmp($b, "\0\0\1\0", 4) === 0) return 'image/x-icon';
    if (preg_match('~^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*<svg\b~is', substr($b, 0, 2000))) return 'image/svg+xml';
    return '';
}

/* ---------- DNS ---------- */

function smDnsLookup(string $name): array
{
    $name = strtolower(trim($name));
    $name = preg_replace('~^[a-z]+://~', '', $name);
    $name = trim(explode('/', $name)[0], '. ');
    if (filter_var($name, FILTER_VALIDATE_IP)) {
        $ptr = @gethostbyaddr($name);
        return ['name' => $name, 'ip' => true, 'records' => [['type' => 'PTR', 'value' => $ptr && $ptr !== $name ? $ptr : '–', 'ttl' => 0]], 'mail' => null];
    }
    $ascii = smNetAscii($name);
    if (!preg_match('/^(?=.{1,253}$)([a-z0-9_]([a-z0-9_-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/', $ascii)) throw new RuntimeException(t('Bitte eine Domain wie example.org eingeben.'));
    if (!function_exists('dns_get_record')) throw new RuntimeException(t('Auf diesem Server fehlt die DNS-Abfrage von PHP.'));
    $types = ['A' => DNS_A, 'AAAA' => DNS_AAAA, 'CNAME' => DNS_CNAME, 'MX' => DNS_MX, 'NS' => DNS_NS, 'TXT' => DNS_TXT, 'SOA' => DNS_SOA];
    if (defined('DNS_CAA')) $types['CAA'] = DNS_CAA;
    $rec = [];
    foreach ($types as $tn => $tc) {
        foreach ((@dns_get_record($ascii, $tc) ?: []) as $r) {
            $v = '';
            switch ($tn) {
                case 'A': $v = $r['ip'] ?? ''; break;
                case 'AAAA': $v = $r['ipv6'] ?? ''; break;
                case 'CNAME': case 'NS': $v = $r['target'] ?? ''; break;
                case 'MX': $v = ($r['pri'] ?? 0) . ' ' . ($r['target'] ?? ''); break;
                case 'TXT': $v = isset($r['entries']) ? implode('', $r['entries']) : ($r['txt'] ?? ''); break;
                case 'SOA': $v = ($r['mname'] ?? '') . ' ' . ($r['rname'] ?? '') . ' ' . ($r['serial'] ?? ''); break;
                case 'CAA': $v = ($r['flags'] ?? 0) . ' ' . ($r['tag'] ?? '') . ' "' . ($r['value'] ?? '') . '"'; break;
            }
            if ($v !== '' && ($r['type'] ?? $tn) === $tn) $rec[] = ['type' => $tn, 'value' => $v, 'ttl' => (int)($r['ttl'] ?? 0)];
        }
    }
    // E-Mail-Sicherheit: SPF, DMARC, MX
    $spf = '';
    foreach ($rec as $r) if ($r['type'] === 'TXT' && stripos($r['value'], 'v=spf1') === 0) $spf = $r['value'];
    $dmarc = '';
    foreach ((@dns_get_record('_dmarc.' . $ascii, DNS_TXT) ?: []) as $r) { $t = isset($r['entries']) ? implode('', $r['entries']) : ($r['txt'] ?? ''); if (stripos($t, 'v=DMARC1') === 0) $dmarc = $t; }
    $mx = count(array_filter($rec, function ($r) { return $r['type'] === 'MX'; }));
    return ['name' => $name, 'ascii' => $ascii, 'ip' => false, 'records' => $rec,
            'mail' => ['mx' => $mx, 'spf' => $spf, 'dmarc' => $dmarc, 'policy' => preg_match('/;\s*p\s*=\s*(\w+)/i', $dmarc, $pm) ? strtolower($pm[1]) : '']];
}

/* ---------- WHOIS über RDAP (mit klassischem WHOIS als Ersatz) ---------- */

function smWhois(string $q): array
{
    $q = strtolower(trim($q));
    $q = preg_replace('~^[a-z]+://~', '', $q);
    $q = trim(explode('/', $q)[0], '. ');
    if (preg_match('/^www\./', $q) && substr_count($q, '.') >= 2) $q = substr($q, 4);
    if (filter_var($q, FILTER_VALIDATE_IP)) {
        $j = smRdapGet('https://rdap.arin.net/registry/ip/' . rawurlencode($q));
        if (!$j) throw new RuntimeException(t('Keine Daten zu dieser IP-Adresse gefunden.'));
        return ['kind' => 'ip', 'query' => $q] + smRdapSummary($j);
    }
    if (preg_match('/^(as)?(\d{1,10})$/i', $q, $m)) {
        $j = smRdapGet('https://rdap.arin.net/registry/autnum/' . $m[2]);
        if (!$j) throw new RuntimeException(t('Keine Daten zu dieser AS-Nummer gefunden.'));
        return ['kind' => 'as', 'query' => 'AS' . $m[2]] + smRdapSummary($j);
    }
    $ascii = smNetAscii($q);
    if (!preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/', $ascii)) throw new RuntimeException(t('Bitte eine Domain, IP-Adresse oder AS-Nummer eingeben.'));
    $tld = substr($ascii, (int)strrpos($ascii, '.') + 1);
    $boot = smFetchJson('https://data.iana.org/rdap/dns.json', 7 * 86400, 8);
    $base = '';
    foreach ((array)($boot['services'] ?? []) as $svc) {
        if (in_array($tld, (array)($svc[0] ?? []), true)) { $base = (string)($svc[1][0] ?? ''); break; }
    }
    if ($base !== '') {
        $j = smRdapGet(rtrim($base, '/') . '/domain/' . rawurlencode($ascii));
        if ($j && isset($j['ldhName'])) return ['kind' => 'domain', 'query' => $q, 'source' => 'RDAP'] + smRdapSummary($j);
        if ($j && (int)($j['errorCode'] ?? 0) === 404) return ['kind' => 'domain', 'query' => $q, 'free' => true, 'source' => 'RDAP'];
    }
    // Ersatz: klassisches WHOIS über Port 43 (z. B. für .de)
    $raw = smWhoisRaw($ascii, $tld);
    if ($raw === '') throw new RuntimeException(t('Für diese Endung gibt es keine öffentliche Abfrage.'));
    return ['kind' => 'domain', 'query' => $q, 'source' => 'WHOIS', 'raw' => mb_substr($raw, 0, 20000), 'free' => (bool)preg_match('/^(status:\s*free|no match|not found|no entries found|no data found)/mi', $raw)];
}

function smRdapGet(string $url): ?array
{
    $ck = 'rdap|' . $url;
    if ($hit = smCacheGet($ck)) return $hit;
    $c = smNetChain($url, ['method' => 'GET', 'limit' => 1048576, 'timeout' => 10, 'max' => 5, 'accept' => 'application/rdap+json, application/json']);
    $j = json_decode((string)($c['last']['body'] ?? ''), true);
    if (!is_array($j)) return null;
    smCacheSet($ck, $j, 3600);
    return $j;
}

function smRdapSummary(array $j): array
{
    $ev = [];
    foreach ((array)($j['events'] ?? []) as $e) if (isset($e['eventAction'], $e['eventDate'])) $ev[$e['eventAction']] = substr((string)$e['eventDate'], 0, 10);
    $ent = [];
    $walk = function ($list) use (&$walk, &$ent) {
        foreach ((array)$list as $e) {
            $fn = '';
            foreach ((array)($e['vcardArray'][1] ?? []) as $v) if (($v[0] ?? '') === 'fn') $fn = (string)($v[3] ?? '');
            foreach ((array)($e['roles'] ?? []) as $role) if ($fn !== '' && !isset($ent[$role])) $ent[$role] = $fn;
            if (!empty($e['entities'])) $walk($e['entities']);
        }
    };
    $walk($j['entities'] ?? []);
    $ns = [];
    foreach ((array)($j['nameservers'] ?? []) as $n) if (!empty($n['ldhName'])) $ns[] = strtolower($n['ldhName']);
    $cidr = [];
    foreach ((array)($j['cidr0_cidrs'] ?? []) as $c) $cidr[] = ($c['v4prefix'] ?? $c['v6prefix'] ?? '') . '/' . ($c['length'] ?? '');
    return [
        'name' => (string)($j['unicodeName'] ?? $j['ldhName'] ?? $j['name'] ?? ''), 'handle' => (string)($j['handle'] ?? ''),
        'status' => array_values(array_map('strval', (array)($j['status'] ?? []))), 'events' => $ev, 'entities' => $ent, 'nameservers' => $ns,
        'dnssec' => isset($j['secureDNS']['delegationSigned']) ? (bool)$j['secureDNS']['delegationSigned'] : null,
        'range' => isset($j['startAddress']) ? $j['startAddress'] . ' – ' . ($j['endAddress'] ?? '') : '', 'cidr' => $cidr,
        'country' => (string)($j['country'] ?? ''), 'type' => (string)($j['type'] ?? ''), 'port43' => (string)($j['port43'] ?? ''),
        'asn' => isset($j['startAutnum']) ? 'AS' . $j['startAutnum'] . (isset($j['endAutnum']) && $j['endAutnum'] != $j['startAutnum'] ? ' – AS' . $j['endAutnum'] : '') : '',
    ];
}

/** Klassisches WHOIS: erst bei IANA nach dem zuständigen Server fragen, dann dort */
function smWhoisRaw(string $domain, string $tld): string
{
    $ask = function (string $server, string $query): string {
        $r = smNetHost($server);
        if ($r['state'] !== 'ok') return '';
        $fp = @stream_socket_client('tcp://' . (strpos($r['ip'], ':') !== false ? '[' . $r['ip'] . ']' : $r['ip']) . ':43', $en, $es, 6);
        if (!$fp) return '';
        stream_set_timeout($fp, 6);
        fwrite($fp, $query . "\r\n");
        $out = '';
        while (!feof($fp) && strlen($out) < 200000) { $chunk = fread($fp, 8192); if ($chunk === false || $chunk === '') break; $out .= $chunk; }
        fclose($fp);
        return mb_check_encoding($out, 'UTF-8') ? $out : mb_convert_encoding($out, 'UTF-8', 'ISO-8859-1');
    };
    $ck = 'whoisrefer|' . $tld;
    $server = (string)(smCacheGet($ck)['s'] ?? '');
    if ($server === '') {
        $iana = $ask('whois.iana.org', $tld);
        if (preg_match('/^refer:\s*(\S+)/mi', $iana, $m)) $server = strtolower($m[1]);
        if ($server === '') return '';
        smCacheSet($ck, ['s' => $server], 30 * 86400);
    }
    return trim($ask($server, $server === 'whois.denic.de' ? '-T dn,ace ' . $domain : $domain));
}

/* ---------- SSL-Zertifikat ---------- */

function smSslCheck(string $host, int $port): array
{
    $host = strtolower(trim($host));
    $host = preg_replace('~^[a-z]+://~', '', $host);
    $host = explode('/', $host)[0];
    if (preg_match('/^(.+):(\d+)$/', $host, $m) && strpos($m[1], ':') === false) { $host = $m[1]; $port = (int)$m[2]; }
    $port = $port > 0 ? $port : 443;
    if (!in_array($port, [443, 8443, 465, 993, 995, 636, 853, 5061, 6697], true)) throw new RuntimeException(t('Erlaubt sind die Ports 443, 8443, 465, 993, 995, 636, 853, 5061 und 6697.'));
    $r = smNetHost($host);
    if ($r['state'] === 'nx') throw new RuntimeException(smNetErrText('nx'));
    if ($r['state'] !== 'ok') throw new RuntimeException(smNetErrText('blocked'));
    $ascii = smNetAscii(trim($host, '[]'));
    $addr = 'ssl://' . (strpos($r['ip'], ':') !== false ? '[' . $r['ip'] . ']' : $r['ip']) . ':' . $port;
    $open = function (bool $verify) use ($addr, $ascii) {
        $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'capture_peer_cert_chain' => true, 'verify_peer' => $verify, 'verify_peer_name' => $verify,
            'allow_self_signed' => !$verify, 'peer_name' => $ascii, 'SNI_enabled' => true, 'SNI_server_name' => $ascii]]);
        $t0 = microtime(true);
        $fp = @stream_socket_client($addr, $en, $es, 8, STREAM_CLIENT_CONNECT, $ctx);
        return [$fp, $ctx, (int)round((microtime(true) - $t0) * 1000), $es];
    };
    [$fp, $ctx, $ms, $err] = $open(true);
    $trusted = (bool)$fp;
    if (!$fp) [$fp, $ctx, $ms, $err2] = $open(false);
    if (!$fp) throw new RuntimeException(t('Keine verschlüsselte Verbindung möglich: %s', $err ?: '?'));
    $meta = stream_get_meta_data($fp)['crypto'] ?? [];
    $params = stream_context_get_params($fp)['options']['ssl'] ?? [];
    fclose($fp);
    $cert = $params['peer_certificate'] ?? null;
    if (!$cert) throw new RuntimeException(t('Kein Zertifikat erhalten.'));
    $x = openssl_x509_parse($cert) ?: [];
    $san = [];
    foreach (explode(',', (string)($x['extensions']['subjectAltName'] ?? '')) as $e) { $e = trim($e); if (stripos($e, 'DNS:') === 0) $san[] = substr($e, 4); elseif (stripos($e, 'IP Address:') === 0) $san[] = substr($e, 11); }
    $match = false;
    foreach ($san as $n) {
        $n = strtolower($n);
        if ($n === $ascii || (strpos($n, '*.') === 0 && substr_count($ascii, '.') === substr_count($n, '.') && substr($ascii, strpos($ascii, '.')) === substr($n, 1))) $match = true;
    }
    $bits = 0;
    $ktype = '';
    if (($pk = @openssl_pkey_get_public($cert)) && ($det = openssl_pkey_get_details($pk))) {
        $bits = (int)($det['bits'] ?? 0);
        $ktype = [OPENSSL_KEYTYPE_RSA => 'RSA', OPENSSL_KEYTYPE_EC => 'EC'][$det['type']] ?? '';
        if ($ktype === 'EC' && !empty($det['ec']['curve_name'])) $ktype .= ' (' . $det['ec']['curve_name'] . ')';
    }
    $chain = [];
    foreach ((array)($params['peer_certificate_chain'] ?? []) as $c) {
        $p = openssl_x509_parse($c) ?: [];
        $chain[] = ['subject' => smCertName($p['subject'] ?? []), 'issuer' => smCertName($p['issuer'] ?? []), 'to' => (int)($p['validTo_time_t'] ?? 0)];
    }
    $to = (int)($x['validTo_time_t'] ?? 0);
    return [
        'host' => $host, 'port' => $port, 'ip' => $r['ip'], 'ms' => $ms, 'trusted' => $trusted, 'match' => $match,
        'subject' => smCertName($x['subject'] ?? []), 'issuer' => smCertName($x['issuer'] ?? []), 'issuerOrg' => (string)($x['issuer']['O'] ?? ''),
        'from' => (int)($x['validFrom_time_t'] ?? 0), 'to' => $to, 'daysLeft' => (int)floor(($to - time()) / 86400),
        'san' => array_slice($san, 0, 200), 'sanCount' => count($san), 'serial' => (string)($x['serialNumberHex'] ?? ''), 'sig' => (string)($x['signatureTypeSN'] ?? ''),
        'key' => trim($ktype . ' ' . ($bits ? $bits . ' Bit' : '')), 'protocol' => (string)($meta['protocol'] ?? ''), 'cipher' => (string)($meta['cipher_name'] ?? ''),
        'cipherBits' => (int)($meta['cipher_bits'] ?? 0), 'chain' => $chain,
        'sha256' => function_exists('openssl_x509_fingerprint') ? strtoupper(implode(':', str_split((string)openssl_x509_fingerprint($cert, 'sha256'), 2))) : '',
    ];
}

function smCertName(array $n): string
{
    $cn = $n['CN'] ?? '';
    if (is_array($cn)) $cn = implode(', ', $cn);
    $o = $n['O'] ?? '';
    if (is_array($o)) $o = implode(', ', $o);
    return trim($cn . ($o !== '' && $o !== $cn ? ' (' . $o . ')' : ''));
}

/* ---------- Kursverlauf der EZB ---------- */

/** $range: 1 = 30 Tage, 2 = 90 Tage, 3 = 1 Jahr, 4 = 5 Jahre. Liefert ['cur', 'points' => [[Datum, Kurs], …]] */
function smFxHistory(string $cur, int $range): array
{
    if (!preg_match('/^[A-Z]{3}$/', $cur) || $cur === 'EUR') throw new RuntimeException(t('Bitte eine Währung außer Euro wählen.'));
    $days = [1 => 31, 2 => 92, 3 => 366, 4 => 1830][$range] ?? 366;
    $ck = 'fxh|' . $cur . '|' . $days . '|' . date('Y-m-d');
    if ($hit = smCacheGet($ck)) return $hit;
    $from = date('Y-m-d', strtotime('-' . $days . ' days'));
    $r = smNetOnce('https://data-api.ecb.europa.eu/service/data/EXR/D.' . $cur . '.EUR.SP00.A?startPeriod=' . $from . '&format=csvdata', ['limit' => 2000000, 'timeout' => 12, 'accept' => 'text/csv']);
    if ($r['status'] !== 200) throw new RuntimeException(t('Die EZB hat gerade keinen Kursverlauf geliefert.'));
    $lines = preg_split('/\r?\n/', trim($r['body']));
    $head = str_getcsv((string)array_shift($lines), ',', '"', '\\');
    $it = array_search('TIME_PERIOD', $head, true);
    $iv = array_search('OBS_VALUE', $head, true);
    if ($it === false || $iv === false) throw new RuntimeException(t('Die EZB hat gerade keinen Kursverlauf geliefert.'));
    $pts = [];
    foreach ($lines as $l) {
        $c = str_getcsv($l, ',', '"', '\\');
        if (isset($c[$it], $c[$iv]) && is_numeric($c[$iv])) $pts[] = [$c[$it], (float)$c[$iv]];
    }
    $out = ['cur' => $cur, 'points' => $pts];
    smCacheSet($ck, $out, 6 * 3600);
    return $out;
}
