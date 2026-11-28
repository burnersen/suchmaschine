<?php
/* Suchmaschine – Seite Lesemodus (ohne Werbung lesen) */
defined('SM_VERSION') || exit;

/**
 * ?a=read&u=…: Lesemodus. Nur mit Konto. Nichts wird gespeichert, die Quelle steht immer dabei, Paywalls werden nicht umgangen.
 * Höchstens 60 Seiten pro Stunde und Konto, damit niemand die Suchmaschine zum Abgrasen fremder Seiten benutzt.
 */
function smPageRead(array $me, bool $post): void
{
    $u = trim((string)($_GET['u'] ?? $_POST['u'] ?? ''));
    $u = smLink($u);
    if ($u === '' || strlen($u) > 2000) smRedirect(smBase());
    // Vom Lesezeichen hochgeladene Seite (nur für das Konto, das sie geschickt hat, und nur zu dieser Adresse)
    $h = is_string($_GET['h'] ?? null) && preg_match('/^[a-f0-9]{24}$/', $_GET['h']) ? $_GET['h'] : '';
    $html = null;
    if ($h !== '') {
        $up = smCacheGet('readin|' . $h);
        if ($up && (int)($up['uid'] ?? 0) === (int)$me['id'] && ($up['u'] ?? '') === $u) $html = (string)$up['h'];
        else $h = '';
    }
    $view = ($_GET['v'] ?? '') === 'all' ? 'all' : '';
    $k = hash('sha256', 'read|' . $me['id'] . '|' . date('YmdH'));
    $row = smQ('SELECT fails FROM throttle WHERE k = ?', [$k])->fetch();
    $err = '';
    $r = null;
    if ($row && (int)$row['fails'] >= 60) {
        $err = t('Für diese Stunde sind genug Seiten im Lesemodus geöffnet worden. Bitte später wieder.');
    } else {
        smQ('INSERT INTO throttle (k, fails, last) VALUES (?, 1, ?) ON CONFLICT(k) DO UPDATE SET fails = fails + 1, last = excluded.last', [$k, time()]);
        $r = smReadPage($u, $html);
        if ($html !== null) $r['url'] = $u;
    }
    if ($r && $r['ok'] && $view === 'all' && !empty($r['alt'])) {
        $r['blocks'] = $r['alt'];
        $r['kind'] = 'article';
        $r['was_recipe'] = true;
    }
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    smRender('read', ['me' => $me, 'prefs' => smPrefs($me), 'u' => $u, 'r' => $r, 'error' => $err, 'view' => $view, 'h' => $h,
                      'title' => ($r['title'] ?? '') !== '' ? $r['title'] . ' – ' . t('Lesemodus') : t('Lesemodus')]);
}

/**
 * ?a=readin (POST vom Lesezeichen „Lesen & Drucken“): die Seite, die der Browser schon geladen hat. Erlaubt nur mit dem
 * persönlichen Schlüssel eines Kontos, höchstens 3 MB und 60 Seiten pro Stunde. Liegt 30 Minuten im Zwischenspeicher,
 * dann weiter zum Lesemodus (dort zählt wieder die normale Anmeldung).
 */
function smServeReadIn(): void
{
    $uid = smReadKeyUser(is_string($_POST['k'] ?? null) ? $_POST['k'] : '');
    $u = smLink(is_string($_POST['u'] ?? null) ? trim($_POST['u']) : '');
    $html = is_string($_POST['html'] ?? null) ? $_POST['html'] : '';
    if ($uid === 0 || !smQ('SELECT 1 FROM users WHERE id = ?', [$uid])->fetchColumn() || $u === '' || strlen($u) > 2000) {
        http_response_code(403);
        exit;
    }
    if ($html === '' || strlen($html) > 3200000) smRedirect(smUrl(['a' => 'read', 'u' => $u]));
    $k = hash('sha256', 'readin|' . $uid . '|' . date('YmdH'));
    $row = smQ('SELECT fails FROM throttle WHERE k = ?', [$k])->fetch();
    if ($row && (int)$row['fails'] >= 60) smRedirect(smUrl(['a' => 'read', 'u' => $u]));
    smQ('INSERT INTO throttle (k, fails, last) VALUES (?, 1, ?) ON CONFLICT(k) DO UPDATE SET fails = fails + 1, last = excluded.last', [$k, time()]);
    if (!mb_check_encoding($html, 'UTF-8')) $html = (string)mb_convert_encoding($html, 'UTF-8', 'UTF-8');
    $tok = bin2hex(random_bytes(12));
    smCacheSet('readin|' . $tok, ['uid' => $uid, 'u' => $u, 'h' => $html], 1800);
    smRedirect(smUrl(['a' => 'read', 'u' => $u, 'h' => $tok]));
}

/* ---------- „Bilder dieser Seite groß“ ---------- */

/** Adresse einer Archiv-Kopie bei web.archive.org (unveränderte Fassung der Seite, ohne die Leiste des Archivs) */
function smArchiveUrl(string $u): string
{
    return 'https://web.archive.org/web/2id_/' . $u;
}

/** Ist das eine Archiv-Adresse aus smArchiveUrl()? Dann die ursprüngliche Adresse, sonst ''. */
function smArchiveOrig(string $u): string
{
    return preg_match('~^https://web\.archive\.org/web/\d+(?:id_)?/(https?://.+)$~i', $u, $m) ? $m[1] : '';
}

/**
 * Sammelt die Bilder einer Seite in der größten Fassung, die sie verrät: <img> (srcset, nachgeladene per data-src), <picture>,
 * Links direkt auf Bilddateien und das Vorschaubild für soziale Netze. Kleine Symbole, Logos und Zählpixel fallen weg.
 * Liefert höchstens $max Einträge ['src', 'title', 'w', 'h'].
 */
function smPicsExtract(string $html, string $base, int $max = 150): array
{
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
    $out = [];
    // Link auf eine Bilddatei? Seiten über ein Bild (bei Wikis „Datei:Name.jpg“) sind keine.
    $isImgLink = function (string $href): bool {
        return (bool)preg_match('~\.(jpe?g|png|webp|gif)(\?|#|$)~i', $href) && !preg_match('~/[^/?#]*:[^/?#]*$~', (string)parse_url($href, PHP_URL_PATH));
    };
    $add = function (string $src, string $title, int $w = 0, int $h = 0) use (&$out, $base, $max): void {
        $src = trim($src);
        if ($src === '' || strpos($src, 'data:') === 0 || count($out) >= $max) return;
        $src = smResolveUrl($base, $src);
        if (!preg_match('~^https?://~i', $src) || strlen($src) > 2000) return;
        if (preg_match('~\.svg(\?|#|$)~i', $src)) return;
        // Wikipedia und Wikimedia Commons: aus der Vorschau (…/thumb/a/ab/Name.jpg/960px-Name.jpg) das Original machen
        if (preg_match('~^https://(?:upload|thumb)\.wikimedia\.org(/wikipedia/[^/?#]+)/thumb/([^?#]+\.(?:jpe?g|png|gif|webp))/[^/?#]+(?:[?#].*)?$~i', $src, $wm)) $src = 'https://upload.wikimedia.org' . $wm[1] . '/' . $wm[2];
        // Dieselbe Datei in mehreren Größen nur einmal (Größenangaben in der Adresse zählen nicht mit)
        $key = preg_replace('~([?&](w|h|width|height|size|resize|fit|quality|q)=[^&#]*)|(-\d{2,4}x\d{2,4}(?=\.\w{3,4}$))~i', '', $src);
        if (isset($out[$key])) return;
        $out[$key] = ['src' => $src, 'title' => mb_substr(smPlain($title), 0, 200), 'w' => $w, 'h' => $h];
    };
    $largest = function (string $srcset): string {
        $best = '';
        $bw = -1.0;
        foreach (explode(',', $srcset) as $part) {
            if (!preg_match('~^\s*(\S+)(?:\s+([\d.]+)([wx]))?\s*$~', $part, $m)) continue;
            $v = isset($m[2]) ? (float)$m[2] * (($m[3] ?? '') === 'x' ? 1000 : 1) : 0.0;
            if ($v > $bw) { $bw = $v; $best = $m[1]; }
        }
        return $best;
    };
    $pageTitle = '';
    if ($n = $xp->query('//title')->item(0)) $pageTitle = trim((string)$n->nodeValue);
    foreach ($xp->query('//meta[@property="og:image" or @property="og:image:secure_url" or @name="twitter:image"]/@content') as $a) $add((string)$a->nodeValue, $pageTitle);
    foreach ($xp->query('//img') as $im) {
        /** @var DOMElement $im */
        $w = (int)$im->getAttribute('width');
        $h = (int)$im->getAttribute('height');
        if (($w > 0 && $w < 120) || ($h > 0 && $h < 80)) continue;
        $src = '';
        // <picture>: die größte Quelle der umgebenden <source>-Angaben
        if ($im->parentNode instanceof DOMElement && strtolower($im->parentNode->nodeName) === 'picture') {
            foreach ($xp->query('./source', $im->parentNode) as $so) {
                /** @var DOMElement $so */
                if (preg_match('~image/(avif|jxl)~i', $so->getAttribute('type'))) continue;   // nicht jeder Browser zeigt das
                $src = $largest($so->getAttribute('srcset') ?: $so->getAttribute('data-srcset'));
                if ($src !== '') break;
            }
        }
        foreach (['srcset', 'data-srcset', 'data-lazy-srcset'] as $a) {
            if ($src === '') $src = $largest($im->getAttribute($a));
        }
        foreach (['data-src', 'data-lazy-src', 'data-original', 'data-full', 'data-zoom-src', 'src'] as $a) {
            if ($src !== '') break;
            $v = trim($im->getAttribute($a));
            if ($v !== '' && strpos($v, 'data:') !== 0) $src = $v;
        }
        if ($src === '') continue;
        if (preg_match('~(pixel|spacer|tracking|logo|icon|avatar|badge|emoji|gravatar|sprite|1x1)~i', $src . ' ' . $im->getAttribute('class'))) continue;
        if (preg_match('~[?&](w|width)=(\d+)~i', $src, $mw) && (int)$mw[2] < 160) continue;
        // Führt das Bild als Link zu einer größeren Bilddatei, zählt die
        $a = $im->parentNode;
        if ($a instanceof DOMElement && strtolower($a->nodeName) === 'a' && $isImgLink($a->getAttribute('href'))) $src = $a->getAttribute('href');
        $add($src, $im->getAttribute('alt') ?: $im->getAttribute('title'), $w >= 300 ? $w : 0, $w >= 300 ? $h : 0);
    }
    foreach ($xp->query('//a[@href]') as $a) {
        /** @var DOMElement $a */
        if ($isImgLink($a->getAttribute('href'))) $add($a->getAttribute('href'), $a->getAttribute('title') ?: trim((string)$a->textContent));
    }
    return [array_values($out), smPlain($pageTitle)];
}

/**
 * ?a=pics&u=…: alle Bilder einer Seite im Raster, mit der Großansicht der Bildersuche (Vollbild, Diashow, Wischen).
 * Nur mit Konto, die Bilder kommen über den eigenen Server (Bild-Proxy), gespeichert wird nichts. Zählt zum Stundenlimit des Lesemodus.
 */
function smPagePics(array $me): void
{
    $u = smLink(trim((string)($_GET['u'] ?? '')));
    if ($u === '' || strlen($u) > 2000) smRedirect(smBase());
    $vars = ['me' => $me, 'prefs' => smPrefs($me), 'u' => $u, 'imgs' => [], 'error' => '', 'ptitle' => '',
             'iview' => isset($_GET['view']) && is_string($_GET['view']) && ctype_digit($_GET['view']) ? (int)$_GET['view'] : -1];
    if (!smImgProxyOn()) {
        $vars['error'] = t('Dafür muss „Bilder in voller Auflösung über diesen Server laden“ eingeschaltet sein (Verwaltung → Einstellungen).');
    } else {
        // Die Liste liegt kurz im Zwischenspeicher: Blättern in der Großansicht ohne JavaScript holt die Seite nicht jedes Mal neu
        $ck = 'pics|' . $me['id'] . '|' . $u;
        $hit = smCacheGet($ck);
        if (!$hit) {
            $k = hash('sha256', 'read|' . $me['id'] . '|' . date('YmdH'));
            $row = smQ('SELECT fails FROM throttle WHERE k = ?', [$k])->fetch();
            if ($row && (int)$row['fails'] >= 60) {
                $vars['error'] = t('Für diese Stunde sind genug Seiten im Lesemodus geöffnet worden. Bitte später wieder.');
            } else {
                smQ('INSERT INTO throttle (k, fails, last) VALUES (?, 1, ?) ON CONFLICT(k) DO UPDATE SET fails = fails + 1, last = excluded.last', [$k, time()]);
                try {
                    $r = smSafeGet($u, ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.1'], SM_READ_MAX);
                    if ($r['status'] !== 200) {
                        $vars['error'] = in_array($r['status'], [401, 402, 403], true) ? t('Die Seite lässt den Abruf durch den Server nicht zu.') : t('Die Seite konnte nicht geladen werden.');
                    } elseif (strpos($r['type'], 'image/') === 0) {
                        $hit = ['imgs' => [['src' => $r['url'], 'title' => '', 'w' => 0, 'h' => 0]], 't' => ''];
                    } else {
                        [$list, $pt] = smPicsExtract($r['body'], $r['url']);
                        $hit = ['imgs' => $list, 't' => $pt];
                    }
                } catch (Throwable $e) {
                    $vars['error'] = t('Die Seite konnte nicht geladen werden.');
                }
                if ($hit) smCacheSet($ck, $hit, 900);
            }
        }
        if ($hit) {
            $host = smHost((string)parse_url($u, PHP_URL_HOST));
            foreach ($hit['imgs'] as $im) {
                $px = smImgProxyUrl($im['src']);
                if ($px === '') continue;
                $vars['imgs'][] = ['title' => $im['title'], 'page' => $u, 'src' => $im['src'], 'thumb' => $px, 'host' => $host,
                                   'w' => (int)$im['w'], 'h' => (int)$im['h'], 'tw' => 0, 'th' => 0, 'favicon' => ''];
            }
            $vars['ptitle'] = (string)$hit['t'];
        }
    }
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    smRender('pics', $vars + ['title' => t('Bilder dieser Seite') . ' – ' . smSiteName()]);
}
