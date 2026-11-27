<?php
/* Suchmaschine – „Ohne Werbung lesen“: holt eine Seite über den eigenen Server und schneidet den eigentlichen Inhalt heraus.
   Rezepte kommen aus den strukturierten Rezeptdaten (schema.org/Recipe), die fast jede Rezeptseite für Suchmaschinen mitliefert:
   Zutaten, Zubereitung, Bild, ganz ohne Werbung. Sonst sucht der Server den Block mit dem meisten Fließtext.
   Rechtlich vorsichtig: nur für angemeldete Konten, nichts wird gespeichert oder geteilt, keine Paywall wird umgangen,
   die Quelle steht immer dabei. Gedacht zum privaten Lesen und Ablegen (Privatkopie), nicht zum Weiterverbreiten. */
defined('SM_VERSION') || exit;

const SM_READ_MAX = 4 * 1048576;

/**
 * Holt eine Adresse sicher: nur öffentliche IP-Adressen, feste DNS-Auflösung, jede Weiterleitung neu geprüft, Größenlimit.
 * Liefert ['status', 'type', 'body', 'url'] oder wirft.
 */
function smSafeGet(string $url, array $headers, int $max, int $timeout = 15): array
{
    for ($hop = 0; $hop < 5; $hop++) {
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
        $type = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => $timeout, CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Suchmaschine/' . SM_VERSION . '; Lesemodus)',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function ($c, $dlTotal, $dl) use ($max) { return ($dlTotal > $max || $dl > $max) ? 1 : 0; },
            CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$loc, &$type) {
                if (stripos($line, 'location:') === 0) $loc = trim(substr($line, 9));
                if (stripos($line, 'content-type:') === 0) $type = strtolower(trim(substr($line, 13)));
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
        return ['status' => $status, 'type' => $type, 'body' => (string)$body, 'url' => $url];
    }
    throw new RuntimeException('redirects');
}

/**
 * Liest eine Seite. Liefert ['ok', 'title', 'site', 'byline', 'date', 'url', 'blocks' => [['t' => h|p|li|oli|quote|img|pre, …]], 'kind' => recipe|article, 'why']
 * $html: schon geholtes HTML (z. B. vom Web Unlocker), sonst holt der Server die Seite selbst.
 */
function smReadPage(string $url, ?string $html = null): array
{
    $out = ['ok' => false, 'alt' => null, 'title' => '', 'site' => smHost((string)parse_url($url, PHP_URL_HOST)), 'byline' => '', 'date' => '', 'url' => $url,
            'blocks' => [], 'kind' => 'article', 'why' => '', 'lang' => ''];
    if ($html === null) {
        try {
            $r = smSafeGet($url, ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.1', 'Accept-Language: ' . (smLang() === 'de' ? 'de-DE,de;q=0.9,en;q=0.6' : 'en;q=0.9,de;q=0.5')], SM_READ_MAX);
        } catch (Throwable $e) {
            $out['why'] = 'fetch';
            return $out;
        }
        if ($r['status'] === 401 || $r['status'] === 402 || $r['status'] === 403) { $out['why'] = 'blocked'; return $out; }
        if ($r['status'] !== 200) { $out['why'] = 'status'; return $out; }
        if ($r['type'] !== '' && strpos($r['type'], 'html') === false) { $out['why'] = 'type'; return $out; }
        $html = $r['body'];
        $out['url'] = $url = $r['url'];
    }
    // Zeichensatz: alles nach UTF-8
    if (!mb_check_encoding($html, 'UTF-8')) {
        $cs = preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', $html, $m) ? $m[1] : 'Windows-1252';
        $html = (string)@mb_convert_encoding($html, 'UTF-8', $cs);
    }
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $xp = new DOMXPath($doc);

    $meta = function (string $q) use ($xp): string {
        $n = $xp->query($q)->item(0);
        return $n ? trim((string)$n->nodeValue) : '';
    };
    $out['title'] = smPlain($meta('//meta[@property="og:title"]/@content') ?: $meta('//title'));
    $out['site'] = smPlain($meta('//meta[@property="og:site_name"]/@content')) ?: $out['site'];
    $out['lang'] = substr($meta('/html/@lang'), 0, 2);
    $out['date'] = $meta('//meta[@property="article:published_time"]/@content') ?: $meta('//time/@datetime');
    $out['byline'] = smPlain($meta('//meta[@name="author"]/@content'));
    $hero = $meta('//meta[@property="og:image"]/@content');

    // Paywall? Dann ehrlich sagen und nichts umgehen
    $paywalled = false;
    foreach (smJsonLd($xp) as $o) {
        if (isset($o['isAccessibleForFree']) && in_array(strtolower((string)json_encode($o['isAccessibleForFree'])), ['false', '"false"'], true)) $paywalled = true;
    }

    // 1. Rezept aus den strukturierten Daten
    foreach (smJsonLd($xp) as $o) {
        if (!smLdIs($o, 'Recipe')) continue;
        $b = [];
        $img = smLdImage($o['image'] ?? null) ?: $hero;
        if ($img !== '') $b[] = ['t' => 'img', 'src' => smResolveUrl($url, $img), 'alt' => ''];
        $desc = smPlain((string)($o['description'] ?? ''));
        if ($desc !== '') $b[] = ['t' => 'p', 'x' => $desc];
        $facts = [];
        foreach (['recipeYield' => 'Portionen', 'prepTime' => 'Vorbereitung', 'cookTime' => 'Kochen', 'totalTime' => 'Gesamt'] as $k => $label) {
            $v = $o[$k] ?? null;
            if (is_array($v)) $v = reset($v);
            if (!is_scalar($v) || trim((string)$v) === '') continue;
            $v = in_array($k, ['prepTime', 'cookTime', 'totalTime'], true) ? smIsoDuration((string)$v) : smPlain((string)$v);
            if ($v !== '') $facts[] = t($label) . ': ' . $v;
        }
        if ($facts) $b[] = ['t' => 'p', 'x' => implode(' · ', $facts), 'c' => 'facts'];
        $ing = array_values(array_filter(array_map(function ($x) { return is_scalar($x) ? smPlain((string)$x) : ''; }, (array)($o['recipeIngredient'] ?? []))));
        if ($ing) {
            $b[] = ['t' => 'h', 'x' => t('Zutaten')];
            foreach ($ing as $x) $b[] = ['t' => 'li', 'x' => $x];
        }
        $steps = smLdSteps($o['recipeInstructions'] ?? []);
        if ($steps) {
            $b[] = ['t' => 'h', 'x' => t('Zubereitung')];
            foreach ($steps as $s) {
                if ($s[0] === 'h') $b[] = ['t' => 'h3', 'x' => $s[1]];
                else $b[] = ['t' => 'oli', 'x' => $s[1]];
            }
        }
        if ($ing || $steps) {
            $out['title'] = smPlain((string)($o['name'] ?? '')) ?: $out['title'];
            $a = $o['author'] ?? null;
            if (is_array($a)) $a = $a['name'] ?? (is_array(reset($a)) ? (reset($a)['name'] ?? '') : '');
            if (is_string($a) && $a !== '') $out['byline'] = smPlain($a);
            $recipe = $b;
            break;
        }
    }

    $recipe = $recipe ?? null;
    // 2. Artikel: Ballast weg (bei Rezepten als „ganzer Artikel“ zum Umschalten und für weitere Bilder), dann den Block mit dem meisten Fließtext nehmen
    foreach (['script', 'style', 'noscript', 'iframe', 'object', 'embed', 'form', 'button', 'input', 'select', 'textarea', 'svg', 'canvas',
              'nav', 'header', 'footer', 'aside', 'template', 'dialog'] as $tag) {
        foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $n) if ($n->parentNode) $n->parentNode->removeChild($n);
    }
    $junk = '/(^|[\s_-])(ad|ads|advert\w*|anzeige|werbung|sponsor\w*|promo\w*|banner|cookie\w*|consent|gdpr|newsletter|subscribe|abo|paywall|share|sharing|social|'
          . 'comment\w*|kommentar\w*|related|recommend\w*|teaser|outbrain|taboola|popup|modal|overlay|sidebar|breadcrumb\w*|pagination|footer|header|nav\w*|menu|toolbar|'
          . 'author-box|tags?|rating|print|jump|toc|skip)([\s_-]|$)/i';
    foreach (iterator_to_array($xp->query('//*[@class or @id or @role or @aria-hidden or @hidden]')) as $n) {
        if (!$n->parentNode || in_array(strtolower($n->nodeName), ['html', 'body', 'article', 'main'], true)) continue;
        $sig = $n->getAttribute('class') . ' ' . $n->getAttribute('id');
        $role = strtolower($n->getAttribute('role'));
        if ($n->hasAttribute('hidden') || $n->getAttribute('aria-hidden') === 'true' || in_array($role, ['navigation', 'banner', 'contentinfo', 'complementary', 'dialog', 'search'], true)
            || (preg_match($junk, $sig) && !preg_match('/(article|content|entry|post|story|text|body)/i', $sig))) {
            $n->parentNode->removeChild($n);
        }
    }
    // Kandidaten: Eltern von Absätzen bekommen Punkte für Textmenge, weniger für Links
    $score = [];
    $nodes = [];
    foreach ($xp->query('//p | //pre | //blockquote | //li') as $p) {
        $txt = trim(preg_replace('/\s+/u', ' ', $p->textContent));
        $len = mb_strlen($txt);
        if ($len < 25) continue;
        $links = 0;
        foreach ($p->getElementsByTagName('a') as $a) $links += mb_strlen(trim($a->textContent));
        $pts = (1 + min(3, $len / 100) + substr_count($txt, ',') * .2) * (1 - min(.9, $links / max(1, $len)));
        foreach ([$p->parentNode, $p->parentNode ? $p->parentNode->parentNode : null] as $i => $anc) {
            if (!$anc instanceof DOMElement) continue;
            $id = spl_object_id($anc);
            $nodes[$id] = $anc;
            $score[$id] = ($score[$id] ?? 0) + $pts / ($i + 1);
        }
    }
    foreach ($xp->query('//article | //main | //*[@itemprop="articleBody"]') as $a) {
        $id = spl_object_id($a);
        $nodes[$id] = $a;
        $score[$id] = ($score[$id] ?? 0) * 1.3 + 2;
    }
    if (!$score) {
        if ($recipe) { $out['blocks'] = $recipe; $out['kind'] = 'recipe'; $out['ok'] = true; return $out; }
        $out['why'] = $paywalled ? 'paywall' : 'empty';
        return $out;
    }
    arsort($score);
    $best = $nodes[array_key_first($score)];
    // Liegt der Text auf mehrere Geschwister verteilt (häufig), den gemeinsamen Eltern nehmen
    if ($best->parentNode instanceof DOMElement && isset($score[spl_object_id($best->parentNode)]) && $score[spl_object_id($best->parentNode)] > $score[spl_object_id($best)] * .8) {
        $best = $best->parentNode;
    }
    $blocks = [];
    $seenImg = [];
    if ($hero !== '') { $blocks[] = ['t' => 'img', 'src' => smResolveUrl($url, $hero), 'alt' => '']; $seenImg[smResolveUrl($url, $hero)] = 1; }
    smReadWalk($best, $blocks, $url, $seenImg);
    // Titel nicht doppelt
    if ($blocks && in_array($blocks[0]['t'] ?? '', ['h', 'h3'], true) && mb_strtolower($blocks[0]['x']) === mb_strtolower($out['title'])) array_shift($blocks);
    if ($recipe) {
        $out['blocks'] = $recipe;
        $out['kind'] = 'recipe';
        $out['ok'] = true;
        $out['alt'] = array_slice($blocks, 0, 600);   // der ganze Artikel, mit seinen Bildern
        return $out;
    }
    $text = 0;
    foreach ($blocks as $b) if ($b['t'] !== 'img') $text += mb_strlen($b['x']);
    if ($text < 400) { $out['why'] = $paywalled ? 'paywall' : 'empty'; return $out; }
    $out['blocks'] = array_slice($blocks, 0, 600);
    $out['ok'] = true;
    if ($paywalled) $out['why'] = 'partial';
    return $out;
}

/** Inhalt eines Knotens in einfache Blöcke zerlegen: Überschriften, Absätze, Listen, Zitate, Bilder, Code */
function smReadWalk(DOMNode $n, array &$blocks, string $base, array &$seenImg, int $depth = 0): void
{
    if ($depth > 40) return;
    foreach ($n->childNodes as $c) {
        if (!$c instanceof DOMElement) continue;
        $tag = strtolower($c->nodeName);
        $txt = fn() => trim(preg_replace('/\s+/u', ' ', smPlain($c->textContent)));
        switch ($tag) {
            case 'h1': case 'h2':
                if (($x = $txt()) !== '') $blocks[] = ['t' => 'h', 'x' => mb_substr($x, 0, 300)];
                break;
            case 'h3': case 'h4': case 'h5': case 'h6':
                if (($x = $txt()) !== '') $blocks[] = ['t' => 'h3', 'x' => mb_substr($x, 0, 300)];
                break;
            case 'p':
                // Bilder in Absätzen nicht verlieren
                foreach ($c->getElementsByTagName('img') as $im) smReadImg($im, $blocks, $base, $seenImg);
                if (($x = $txt()) !== '' && mb_strlen($x) > 1) $blocks[] = ['t' => 'p', 'x' => $x];
                break;
            case 'ul': case 'ol':
                foreach ($c->childNodes as $li) {
                    if (!$li instanceof DOMElement || strtolower($li->nodeName) !== 'li') continue;
                    $x = trim(preg_replace('/\s+/u', ' ', smPlain($li->textContent)));
                    if ($x !== '') $blocks[] = ['t' => $tag === 'ol' ? 'oli' : 'li', 'x' => mb_substr($x, 0, 2000)];
                }
                break;
            case 'blockquote':
                if (($x = $txt()) !== '') $blocks[] = ['t' => 'quote', 'x' => $x];
                break;
            case 'pre':
                $x = rtrim(smPlain($c->textContent));
                if ($x !== '') $blocks[] = ['t' => 'pre', 'x' => mb_substr($x, 0, 8000)];
                break;
            case 'img':
                smReadImg($c, $blocks, $base, $seenImg);
                break;
            case 'figure': case 'picture':
                foreach ($c->getElementsByTagName('img') as $im) { smReadImg($im, $blocks, $base, $seenImg); break; }
                $cap = $c->getElementsByTagName('figcaption')->item(0);
                if ($cap && ($x = trim(preg_replace('/\s+/u', ' ', smPlain($cap->textContent)))) !== '') $blocks[] = ['t' => 'cap', 'x' => mb_substr($x, 0, 400)];
                break;
            case 'table':
                foreach ($c->getElementsByTagName('tr') as $tr) {
                    $cells = [];
                    foreach ($tr->childNodes as $td) if ($td instanceof DOMElement) $cells[] = trim(preg_replace('/\s+/u', ' ', smPlain($td->textContent)));
                    $cells = array_filter($cells, 'strlen');
                    if ($cells) $blocks[] = ['t' => 'li', 'x' => implode(' · ', $cells)];
                }
                break;
            default:
                smReadWalk($c, $blocks, $base, $seenImg, $depth + 1);
        }
    }
}

/** Ein Bild aufnehmen: größte Variante aus srcset, Lazy-Loading-Attribute beachten, winzige Bilder (Zähler, Icons) weglassen */
function smReadImg(DOMElement $im, array &$blocks, string $base, array &$seenImg): void
{
    $src = '';
    foreach (['srcset', 'data-srcset'] as $a) {
        $best = 0;
        foreach (explode(',', $im->getAttribute($a)) as $part) {
            if (!preg_match('~^\s*(\S+)\s+(\d+)w~', $part, $m)) continue;
            if ((int)$m[2] > $best && (int)$m[2] <= 2000) { $best = (int)$m[2]; $src = $m[1]; }
        }
        if ($src !== '') break;
    }
    foreach (['data-src', 'data-lazy-src', 'data-original', 'src'] as $a) {
        if ($src !== '') break;
        $v = trim($im->getAttribute($a));
        if ($v !== '' && strpos($v, 'data:') !== 0) $src = $v;
    }
    if ($src === '') return;
    $w = (int)$im->getAttribute('width');
    $h = (int)$im->getAttribute('height');
    if (($w > 0 && $w < 120) || ($h > 0 && $h < 80)) return;
    if (preg_match('~(pixel|spacer|tracking|logo|icon|avatar|badge|emoji|gravatar|1x1-small)~i', $src . ' ' . $im->getAttribute('class'))) return;
    if (preg_match('~[?&](w|width)=(\d+)~i', $src, $mw) && (int)$mw[2] < 240) return;
    $src = smResolveUrl($base, $src);
    if (!preg_match('~^https?://~i', $src) || isset($seenImg[$src]) || count($seenImg) >= 30) return;
    $seenImg[$src] = 1;
    $blocks[] = ['t' => 'img', 'src' => $src, 'alt' => mb_substr(smPlain($im->getAttribute('alt')), 0, 200)];
}

/** Alle JSON-LD-Objekte der Seite, @graph aufgelöst */
function smJsonLd(DOMXPath $xp): array
{
    $all = [];
    foreach ($xp->query('//script[@type="application/ld+json"]') as $s) {
        $d = json_decode(trim($s->textContent), true);
        if (!is_array($d)) continue;
        $stack = array_is_list($d) ? $d : [$d];
        while ($stack) {
            $o = array_shift($stack);
            if (!is_array($o)) continue;
            if (isset($o['@graph']) && is_array($o['@graph'])) foreach ($o['@graph'] as $g) $stack[] = $g;
            if (isset($o['mainEntity']) && is_array($o['mainEntity'])) $stack[] = $o['mainEntity'];
            $all[] = $o;
        }
    }
    return $all;
}

function smLdIs(array $o, string $type): bool
{
    $t = $o['@type'] ?? '';
    return is_array($t) ? in_array($type, $t, true) : $t === $type;
}

function smLdImage($i): string
{
    if (is_string($i)) return $i;
    if (is_array($i)) {
        if (isset($i['url']) && is_string($i['url'])) return $i['url'];
        foreach ($i as $x) if (($u = smLdImage($x)) !== '') return $u;
    }
    return '';
}

/** Zubereitungsschritte: Text, HowToStep, HowToSection (mit Überschrift) */
function smLdSteps($ins): array
{
    $out = [];
    if (is_string($ins)) {
        foreach (preg_split('/\n+|(?<=\.)\s+(?=\d+\.\s)/u', smPlain($ins)) as $s) if (trim($s) !== '') $out[] = ['s', trim($s)];
        return $out;
    }
    foreach ((array)$ins as $s) {
        if (is_string($s)) { if (trim($s) !== '') $out[] = ['s', smPlain($s)]; continue; }
        if (!is_array($s)) continue;
        if (smLdIs($s, 'HowToSection')) {
            if (!empty($s['name'])) $out[] = ['h', smPlain((string)$s['name'])];
            $out = array_merge($out, smLdSteps($s['itemListElement'] ?? []));
            continue;
        }
        $x = smPlain((string)($s['text'] ?? $s['name'] ?? ''));
        if ($x !== '') $out[] = ['s', $x];
    }
    return $out;
}

/** PT1H30M → „1 Std. 30 Min.“ */
function smIsoDuration(string $d): string
{
    if (!preg_match('/^P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?)?$/i', trim($d), $m)) return smPlain($d);
    $min = ((int)($m[1] ?? 0)) * 1440 + ((int)($m[2] ?? 0)) * 60 + (int)($m[3] ?? 0);
    if ($min <= 0) return '';
    $h = intdiv($min, 60);
    $mm = $min % 60;
    return trim(($h ? t('%d Std.', $h) . ' ' : '') . ($mm ? t('%d Min.', $mm) : ''));
}
