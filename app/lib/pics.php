<?php
/* Suchmaschine – Bilder über die Brave-Suche hinaus:
   1. Freie Bildquellen mit offizieller Schnittstelle (Wikimedia Commons, Openverse), wählbar im Reiter „Bilder“.
   2. Sammlungen: Bilderlisten im Konto (nur Adressen und Titel, keine Bilddateien). Sie kommen vom Lesezeichen „Bilder groß“
      („An meine App schicken“) oder aus „Bilder dieser Seite“ und lassen sich in der App randlos und als Diashow zeigen.
   Alles nur mit Konto: Der Server ruft dafür fremde Seiten und Bilder ab, das soll kein offener Dienst für jeden sein. */
defined('SM_VERSION') || exit;

const SM_PICSET_MAX = 400;      // Bilder pro Sammlung
const SM_PICSETS_MAX = 40;      // Sammlungen pro Konto, die ältesten fallen weg

/* ---------- Freie Bildquellen ---------- */

/** Bildquellen neben Brave: Kürzel in der Adresse (isrc=…) => [Name, Hinweis] */
function smImgSources(): array
{
    return [
        'commons'   => ['Wikimedia Commons', t('Freie Bilder und Fotos, die Sammlung hinter Wikipedia')],
        'openverse' => ['Openverse', t('Frei lizenzierte Bilder aus Flickr, Museen und Archiven')],
    ];
}

/**
 * Sucht bei einer freien Bildquelle. Liefert dieselbe Form wie die Brave-Bildersuche:
 * ['results' => [['title', 'page', 'src', 'thumb', 'host', 'w', 'h', 'tw', 'th', 'favicon']], 'more', 'altered', 'cached', 'source'].
 * Kostet nichts bei Brave. Antworten bleiben sechs Stunden im Zwischenspeicher. Wirft SmSearchError, wenn die Quelle nicht antwortet.
 */
function smImgSourceSearch(string $src, string $q, int $page): array
{
    $page = max(1, min(10, $page));
    $ck = 'isrc|' . $src . '|' . $page . '|' . mb_strtolower($q);
    if ($hit = smCacheGet($ck)) return ['cached' => true] + $hit;
    $name = smImgSources()[$src][0] ?? $src;
    $out = [];
    $more = false;
    if ($src === 'commons') {
        $d = smFetchJson(smProvBase('commons', 'https://commons.wikimedia.org/w/api.php') . '?' . http_build_query([
            'action' => 'query', 'format' => 'json', 'generator' => 'search', 'gsrsearch' => $q, 'gsrnamespace' => 6, 'gsrlimit' => 50,
            'gsroffset' => ($page - 1) * 50, 'prop' => 'imageinfo', 'iiprop' => 'url|size|mime|extmetadata',
            'iiextmetadatafilter' => 'LicenseShortName|Artist', 'iiurlwidth' => 480, 'uselang' => smLang(),
        ]), 60, 12);
        if ($d === null) throw new SmSearchError(t('%s antwortet gerade nicht. Bitte später noch einmal versuchen.', $name));
        $pages = array_values((array)($d['query']['pages'] ?? []));
        usort($pages, function ($a, $b) { return (int)($a['index'] ?? 0) <=> (int)($b['index'] ?? 0); });
        foreach ($pages as $p) {
            $ii = $p['imageinfo'][0] ?? null;
            if (!is_array($ii) || !preg_match('~^image/(jpeg|png|gif|webp)$~', (string)($ii['mime'] ?? ''))) continue;
            $full = smLink($ii['url'] ?? '');
            $thumb = smLink($ii['thumburl'] ?? '') ?: $full;
            if ($full === '') continue;
            $title = preg_replace('~\.\w{3,4}$~', '', preg_replace('~^(File|Datei):~i', '', (string)($p['title'] ?? '')));
            $by = trim(smPlain((string)($ii['extmetadata']['Artist']['value'] ?? '')));
            $lic = trim(smPlain((string)($ii['extmetadata']['LicenseShortName']['value'] ?? '')));
            $out[] = ['title' => smPlain(str_replace('_', ' ', $title)) . ($by !== '' ? ' · ' . mb_substr($by, 0, 60) . ($lic !== '' ? ' (' . $lic . ')' : '') : ($lic !== '' ? ' · ' . $lic : '')),
                      'page' => smLink($ii['descriptionurl'] ?? '') ?: $full, 'src' => $full, 'thumb' => $thumb, 'host' => 'commons.wikimedia.org',
                      'w' => (int)($ii['width'] ?? 0), 'h' => (int)($ii['height'] ?? 0), 'tw' => (int)($ii['thumbwidth'] ?? 0), 'th' => (int)($ii['thumbheight'] ?? 0), 'favicon' => ''];
        }
        $more = isset($d['continue']['gsroffset']);
    } elseif ($src === 'openverse') {
        $d = smFetchJson(smProvBase('openverse', 'https://api.openverse.org/v1/images/') . '?' . http_build_query(['q' => $q, 'page_size' => 20, 'page' => $page]), 60, 12);
        if ($d === null) throw new SmSearchError(t('%s antwortet gerade nicht. Bitte später noch einmal versuchen.', $name) . ' ' . t('Ohne Anmeldung dort sind nur wenige Suchen pro Stunde erlaubt.'));
        foreach ((array)($d['results'] ?? []) as $r) {
            $full = smLink($r['url'] ?? '');
            if ($full === '') continue;
            $by = trim(smPlain((string)($r['creator'] ?? '')));
            $lic = strtoupper(trim(smPlain((string)($r['license'] ?? ''))));
            $out[] = ['title' => smPlain((string)($r['title'] ?? '')) . ($by !== '' ? ' · ' . mb_substr($by, 0, 60) . ($lic !== '' ? ' (' . $lic . ')' : '') : ($lic !== '' ? ' · ' . $lic : '')),
                      'page' => smLink($r['foreign_landing_url'] ?? '') ?: $full, 'src' => $full, 'thumb' => smLink($r['thumbnail'] ?? '') ?: $full,
                      'host' => smHost((string)parse_url((string)($r['foreign_landing_url'] ?? $full), PHP_URL_HOST)),
                      'w' => (int)($r['width'] ?? 0), 'h' => (int)($r['height'] ?? 0), 'tw' => 0, 'th' => 0, 'favicon' => ''];
        }
        $more = (int)($d['page_count'] ?? 0) > $page;
    } else {
        throw new SmSearchError('unknown source');
    }
    // Vorschaubilder kommen wie die großen Bilder über den eigenen Server (die Sicherheitsregeln erlauben dem Browser keine fremden Bildserver)
    foreach ($out as $i => $im) $out[$i]['thumb'] = smImgProxyUrl($im['thumb']);
    $out = array_values(array_filter($out, function ($im) { return $im['thumb'] !== ''; }));
    $res = ['results' => $out, 'more' => $more, 'altered' => null, 'source' => $src];
    smCacheSet($ck, $res, 6 * 3600);
    return ['cached' => false] + $res;
}

/* ---------- Sammlungen ---------- */

/** Bilderliste prüfen und kürzen: [['u' => Bild, 't' => Titel, 'th' => Vorschau oder '', 'p' => Seite oder '']] */
function smPicsetClean($list): array
{
    $out = [];
    $seen = [];
    foreach (is_array($list) ? $list : [] as $it) {
        if (!is_array($it)) continue;
        $u = smLink(is_string($it['u'] ?? null) ? trim($it['u']) : '');
        if ($u === '' || strlen($u) > 2000 || isset($seen[$u])) continue;
        $seen[$u] = 1;
        $th = smLink(is_string($it['th'] ?? null) ? trim($it['th']) : '');
        $p = smLink(is_string($it['p'] ?? null) ? trim($it['p']) : '');
        $out[] = ['u' => $u, 't' => mb_substr(smPlain(is_string($it['t'] ?? null) ? $it['t'] : ''), 0, 200),
                  'th' => strlen($th) <= 2000 ? $th : '', 'p' => strlen($p) <= 2000 ? $p : ''];
        if (count($out) >= SM_PICSET_MAX) break;
    }
    return $out;
}

/** Sammlung anlegen. Liefert die Kennung oder '' (leere Liste). Die ältesten Sammlungen über der Grenze fallen weg. */
function smPicsetCreate(int $uid, string $title, string $from, array $list): string
{
    $list = smPicsetClean($list);
    if (!$list || $uid <= 0) return '';
    $id = bin2hex(random_bytes(8));
    $title = trim(mb_substr(smPlain($title), 0, 120));
    if ($title === '') $title = smHost((string)parse_url($from, PHP_URL_HOST)) ?: t('Sammlung');
    smQ('INSERT INTO picsets (id, user_id, title, src, created, n, data) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$id, $uid, $title, mb_substr(smLink($from), 0, 2000), time(), count($list), json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    $old = smQ('SELECT id FROM picsets WHERE user_id = ? ORDER BY created DESC, id LIMIT -1 OFFSET ' . SM_PICSETS_MAX, [$uid])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($old as $o) smQ('DELETE FROM picsets WHERE id = ? AND user_id = ?', [$o, $uid]);
    return $id;
}

/** Sammlungen eines Kontos, neueste zuerst (ohne die Bilderlisten) */
function smPicsets(int $uid): array
{
    return smQ('SELECT id, title, src, created, n FROM picsets WHERE user_id = ? ORDER BY created DESC, id', [$uid])->fetchAll();
}

/** Eine Sammlung mit Bildern ('list') oder null. Nur die eigene. */
function smPicsetGet(int $uid, string $id): ?array
{
    if (!preg_match('/^[a-f0-9]{16}$/', $id)) return null;
    $r = smQ('SELECT id, title, src, created, n, data FROM picsets WHERE id = ? AND user_id = ?', [$id, $uid])->fetch();
    if (!$r) return null;
    $r['list'] = smPicsetClean(json_decode((string)$r['data'], true));
    unset($r['data']);
    return $r;
}

/**
 * ?a=picsin (POST vom Lesezeichen „Bilder groß“, Knopf „An meine App schicken“): die Bildadressen, die das Lesezeichen im Browser
 * auf einer fremden Seite gefunden hat. Erlaubt nur mit dem persönlichen Schlüssel eines Kontos (wie beim Lesezeichen
 * „Lesen & Drucken“), höchstens 30 Sammlungen pro Stunde. Gespeichert werden nur Adressen und Titel.
 * Danach: angemeldet direkt zur Sammlung, sonst eine kurze Bestätigung (die App hat am iPhone eine eigene Anmeldung).
 */
function smServePicsIn(): void
{
    $uid = smReadKeyUser(is_string($_POST['k'] ?? null) ? $_POST['k'] : '');
    $raw = is_string($_POST['list'] ?? null) ? $_POST['list'] : '';
    if ($uid === 0 || !smQ('SELECT 1 FROM users WHERE id = ?', [$uid])->fetchColumn() || $raw === '' || strlen($raw) > 1500000) {
        http_response_code(403);
        exit;
    }
    $k = hash('sha256', 'picsin|' . $uid . '|' . date('YmdH'));
    $row = smQ('SELECT fails FROM throttle WHERE k = ?', [$k])->fetch();
    $id = '';
    if (!$row || (int)$row['fails'] < 30) {
        smQ('INSERT INTO throttle (k, fails, last) VALUES (?, 1, ?) ON CONFLICT(k) DO UPDATE SET fails = fails + 1, last = excluded.last', [$k, time()]);
        if (!mb_check_encoding($raw, 'UTF-8')) $raw = (string)mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
        $id = smPicsetCreate($uid, is_string($_POST['t'] ?? null) ? $_POST['t'] : '', is_string($_POST['u'] ?? null) ? trim($_POST['u']) : '', (array)json_decode($raw, true));
    }
    $me = smUser();
    if ($id !== '' && $me && (int)$me['id'] === $uid) smRedirect(smUrl(['a' => 'pics', 'set' => $id]));
    $set = $id !== '' ? smPicsetGet($uid, $id) : null;
    smRender('picsin', ['title' => t('Bilder groß') . ' – ' . smSiteName(), 'bare' => true, 'me' => null, 'set' => $set]);
    exit;
}
