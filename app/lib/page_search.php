<?php
/* Suchmaschine – Seiten Suche und „Auf gut Glück“, Reiter und Filter aus der Adresse */
defined('SM_VERSION') || exit;

function smTabs(): array
{
    return ['web' => t('Alle'), 'images' => t('Bilder'), 'news' => t('News'), 'videos' => t('Videos')];
}

/**
 * Filter und Sortierung aus der Adresse, geprüft. Was nicht in der Adresse steht, kommt aus den Einstellungen des Kontos
 * (Sortierung, News-Zeitraum). Die Felder mit _q sind das, was wirklich in der Adresse steht: nur das zählt als Abweichung
 * für „Alle Filter zurücksetzen“ und wandert in die Links.
 */
/**
 * Wohin es nach der letzten Seite einer Stufe weitergeht: [Adresse-Parameter, Beschriftung] oder null.
 * Zeitstufen bei „Neueste zuerst“ (Alle, News, Videos), Qualitätsstufen bei Bildern (nur Google).
 */
function smNextTier(string $tab, array $flt, string $fresh, ?array $res): ?array
{
    if ($tab === 'images') {
        $nx = (string)($res['iq_next'] ?? '');
        return $nx !== '' ? [['iq' => $nx, 'p' => null], t('Weitere Bilder')] : null;
    }
    if (empty($flt['casc']) || empty($flt['auto']) || $fresh === '' || ($nx = smNextFresh($fresh)) === '') return null;
    return [['f' => $nx, 'xf' => $fresh, 'p' => null], $tab === 'news' ? t('Ältere News: %s', smTierLabel($nx)) : t('Ältere Treffer: %s', smTierLabel($nx))];
}

/** Qualitätsstufen der Bildersuche bei Google, beste zuerst */
function smImageTiers(): array
{
    return ['q4', 'q2', 'ql', 'qa'];
}

function smSearchFilters(string $tab, ?array $prefs = null): array
{
    $g = function (string $k): string { return isset($_GET[$k]) && is_string($_GET[$k]) ? $_GET[$k] : ''; };
    $prefs = $prefs ?? smPrefs(null);
    // Eigener Zeitraum von/bis (Y-m-d). Ist er gesetzt, gilt kein anderer Zeitraum.
    $day = function (string $v): string {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]) && (int)$m[1] >= 1990 ? $v : '';
    };
    $fd = $day($g('fd'));
    $td = $day($g('td'));
    if ($fd !== '' && $td !== '' && $fd > $td) [$fd, $td] = [$td, $fd];
    $range = $fd !== '' || $td !== '';
    // Sortierung: Alle, News und Videos können nach Datum. Dann gilt „Neueste zuerst“ mit Zeitstufen
    // (Start-Zeitraum, am Ende von selbst der nächste: 24 Stunden → Woche → Monat → Jahr → älter).
    $dated = in_array($tab, ['web', 'news', 'videos'], true);
    $sq = $dated && in_array($g('sort'), ['date', 'rel'], true) ? $g('sort') : '';
    $sort = $dated ? ($sq !== '' ? $sq : $prefs['sort_' . $tab]) : 'rel';
    $casc = $dated && ($tab === 'news' || $sort === 'date');
    $fq = !$range && $g('f') !== '' && (isset(smFreshness()[$g('f')]) || ($casc && $g('f') === 'all')) ? $g('f') : '';
    $fresh = $fq === 'all' ? '' : $fq;
    $def = $casc && $prefs['news_fresh'] !== 'all' ? $prefs['news_fresh'] : '';
    $auto = false;
    if ($casc && $fq === '' && !$range) {
        $fresh = $def;
        $auto = $prefs['news_expand'] && $fresh !== '';
    }
    // xf: Fortsetzung in der nächsten Zeitstufe, die schon gezeigte (jüngere) Stufe weglassen
    $xf = $casc && $fq !== '' && in_array($g('xf'), ['ph', 'pd', 'pw', 'pm', 'py'], true) && smNextFresh($g('xf')) === $fq ? $g('xf') : '';
    if ($xf !== '' && $prefs['news_expand']) $auto = true;
    // Bilder: beste Qualität zuerst. Bei Google in Stufen (ab 4 MP, ab 2 MP, groß, alle), bei Brave nach Auflösung sortiert.
    $isort = $tab === 'images' && $g('isort') === 'rel' ? 'rel' : '';
    $isz = $tab === 'images' && isset(smImageSizes()[$g('is')]) ? $g('is') : '';
    $iq = $tab === 'images' && $isort === '' && $isz === '' ? (in_array($g('iq'), smImageTiers(), true) ? $g('iq') : smImageTiers()[0]) : '';
    $f = [
        'fresh'  => $fresh,
        'f_q'    => $fq,
        'xf'     => $xf,
        'auto'   => $auto,
        'sort'   => $sort === 'date' ? 'date' : '',
        'sort_q' => $sq,
        'casc'   => $casc,
        'def'    => $def,
        'ft'    => $tab === 'web' ? smCleanFileType($g('ft')) : '',
        'dl'    => $tab === 'web' && $g('dl') === '0' ? '0' : '',
        'src'   => isset(smProviders()[$g('src')]) || $g('src') === 'multi' ? $g('src') : '',
        'io'    => $tab === 'images' && isset(smImageOrientations()[$g('io')]) ? $g('io') : '',
        'is'    => $isz,
        'isort' => $isort,
        'iq'    => $iq,
        // Filter, die Google direkt in der Suche anwendet (Brave: soweit möglich danach)
        'fd'    => $fd,
        'td'    => $td,
        'vb'    => in_array($tab, ['web', 'news'], true) && $g('vb') === '1' ? '1' : '',
        'sl'    => isset(smSearchLangs()[$g('sl')]) ? $g('sl') : '',
        'cr'    => isset(smCountries()[$g('cr')]) ? $g('cr') : '',
        'ic'    => $tab === 'images' && $g('ic') !== '' && isset(smImageColors()[$g('ic')]) ? $g('ic') : '',
        'it'    => $tab === 'images' && $g('it') !== '' && isset(smImageTypes()[$g('it')]) ? $g('it') : '',
        'il'    => $tab === 'images' && $g('il') !== '' && isset(smImageLicenses()[$g('il')]) ? $g('il') : '',
        'vd'    => $tab === 'videos' && in_array($g('vd'), ['s', 'm', 'l'], true) ? $g('vd') : '',
    ];
    return $f;
}

function smPageSearch(?array $me): void
{
    $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 400));
    // Kleine Formulare der Sofort-Antworten (funktionieren auch ohne JavaScript)
    if (isset($_GET['fx_a'], $_GET['fx_f'], $_GET['fx_t']) && is_string($_GET['fx_a']) && is_string($_GET['fx_f']) && is_string($_GET['fx_t'])) {
        smRedirect(smUrl(['q' => mb_substr(trim($_GET['fx_a']) . ' ' . $_GET['fx_f'] . ' in ' . $_GET['fx_t'], 0, 80)]));
    }
    if (($_GET['wx'] ?? '') === '1' && $q !== '') smRedirect(smUrl(['q' => (smLang() === 'de' ? 'wetter ' : 'weather ') . $q]));
    $tab = (string)($_GET['t'] ?? 'web');
    if (!isset(smTabs()[$tab])) $tab = 'web';
    $prefs = smPrefs($me);
    $part = ($_GET['part'] ?? '') === '1';

    if ($q === '') {
        smRender('home', ['me' => $me, 'prefs' => $prefs, 'title' => smSiteName(), 'home' => true, 'tab' => $tab]);
        return;
    }

    // Bangs: vor jeder Anfrage an einen Suchdienst, kosten also nichts
    if (!$part && ($bang = smBangTarget($q, $me))) {
        if ($bang['internal']) smRedirect($bang['to']);
        smExternalRedirect($bang['to'], $q, '', 0, smSetting('bang_ref') === '1');
    }

    $lenses = smLenses($me);
    $lens = (string)($_GET['l'] ?? '');
    if (!isset($lenses[$lens])) $lens = '';
    $page = max(1, min(10, (int)($_GET['p'] ?? 1)));
    $exact = ($_GET['exact'] ?? '') === '1';
    $flt = smSearchFilters($tab, $prefs);
    $ft = $flt['ft'];
    $fresh = $flt['fresh'];
    $view = $tab === 'images' && isset($_GET['view']) && is_string($_GET['view']) && ctype_digit($_GET['view']) ? min(199, (int)$_GET['view']) : -1;
    $vars = ['me' => $me, 'prefs' => $prefs, 'q' => $q, 'tab' => $tab, 'page' => $page, 'fresh' => $fresh, 'exact' => $exact, 'iview' => $view,
             'ft' => $ft, 'lens' => $lens, 'lenses' => $lenses, 'flt' => $flt, 'res' => null, 'error' => '', 'instant' => null];

    // Sofort-Antworten (Rechner, Währungen, Wetter …): ohne Suchdienst, kosten also kein Guthaben
    if (!$part && $tab === 'web' && $page === 1 && ($_GET['web'] ?? '') !== '1' && $lens === '' && $ft === '' && $flt['src'] === '') {
        $card = smInstant($q, $me);
        if ($card !== null) {
            $vars['instant'] = $card;
            smRender('results', $vars + ['title' => $q . ' – ' . smSiteName()]);
            return;
        }
    }

    $uid = $me ? (int)$me['id'] : 0;
    $optFor = function () use ($q, $ft, $lens, $lenses, $page, $fresh, $exact, $prefs, $flt): array {
        return [smApiQuery($q, $ft, $lens !== '' ? $lenses[$lens] : null), ['page' => $page, 'freshness' => $fresh, 'exact' => $exact,
            'endless' => $prefs['scroll'] === 'endless', 'src' => $flt['src'], 'older' => smTierDays($flt['xf']), 'from' => $flt['fd'], 'to' => $flt['td'],
            'verb' => $flt['vb'] === '1', 'lr' => $flt['sl'], 'io' => $flt['io'], 'is' => $flt['is'], 'ic' => $flt['ic'], 'it' => $flt['it'], 'il' => $flt['il'],
            'vdur' => $flt['vd'], 'iq' => $flt['iq']] + ($flt['cr'] !== '' ? ['country' => $flt['cr']] : []) + $prefs];
    };
    // Die eigentliche Suche. Läuft bei normalen Seitenaufrufen erst, nachdem Kopf, Suchfeld und Filter schon beim Browser sind.
    $run = function () use ($me, $uid, $q, $tab, $prefs, $part, $lens, $lenses, $exact, $ft, $flt, $page, $fresh): array {
        $res = null;
        $error = '';
        $vars = [];
        $redirect = '';
        try {
            if (!$me && !smPublicQuota()) throw new SmSearchError(t('Für diese Stunde sind genug Suchen ohne Anmeldung gelaufen. Bitte später wieder, oder anmelden.'));
            if (!$me && $flt['src'] === 'multi') throw new SmSearchError(t('„Alle zusammen“ gibt es nur mit Konto, weil es bei jedem Dienst eine Suche kostet.'));
            $apiQ = smApiQuery($q, $ft, $lens !== '' ? $lenses[$lens] : null);
            $opt = ['page' => $page, 'freshness' => $fresh, 'exact' => $exact, 'endless' => $prefs['scroll'] === 'endless', 'src' => $flt['src'],
                    'older' => smTierDays($flt['xf']), 'from' => $flt['fd'], 'to' => $flt['td'], 'verb' => $flt['vb'] === '1', 'lr' => $flt['sl'],
                    'io' => $flt['io'], 'is' => $flt['is'], 'ic' => $flt['ic'], 'it' => $flt['it'], 'il' => $flt['il'], 'vdur' => $flt['vd'], 'iq' => $flt['iq']]
                + ($flt['cr'] !== '' ? ['country' => $flt['cr']] : []) + $prefs;
            $res = smSearch($tab, $apiQ, $opt, $uid);
            if ($tab === 'images') {
                $res = smImageTierRun($res, $apiQ, $opt, $uid, $flt, $page, $part);
            } else {
                // Zeitstufen: Ist der Start-Zeitraum leer, gleich den nächsten nehmen (kostet nur in diesem Fall eine Suche mehr)
                $from = $fresh;
                while ($flt['casc'] && $flt['auto'] && $flt['xf'] === '' && $page === 1 && !$part && !$res['results'] && ($nx = smNextFresh($fresh)) !== '') {
                    $fresh = $nx;
                    $res = smSearch($tab, $apiQ, ['freshness' => $fresh === 'all' ? '' : $fresh, 'older' => $fresh === 'all' ? 365 : 0] + $opt, $uid);
                }
                if ($fresh !== $from) {
                    $flt['fresh'] = $fresh;
                    $flt['f_q'] = $fresh;
                    $vars['fresh'] = $fresh;
                    $vars['widened'] = $from;
                }
                // Fortsetzung in der nächsten Stufe: was schon in der jüngeren gezeigt wurde, weglassen
                if ($flt['xf'] !== '') $res = smDropNewer($res, max(3600, smTierDays($flt['xf']) * 86400));
                // Bleibt nach dem Aussortieren nichts übrig (oder ist die letzte Seite leer), gleich mit der nächsten Stufe weiter.
                // So gibt es keine leeren Abschnitte. Höchstens drei Schritte, damit eine leere Suche nicht alles abklappert.
                for ($hop = 0; $hop < 3 && $flt['casc'] && ($part || $flt['xf'] !== '') && $flt['auto'] && !$res['results'] && $fresh !== '' && ($nx = smNextFresh($fresh)) !== ''; $hop++) {
                    $flt['xf'] = $fresh;
                    $fresh = $nx;
                    $page = 1;
                    $days = smTierDays($flt['xf']);
                    $res = smSearch($tab, $apiQ, ['freshness' => $fresh === 'all' ? '' : $fresh, 'page' => 1, 'older' => $days] + $opt, $uid);
                    $res = smDropNewer($res, max(3600, $days * 86400));
                    $flt['fresh'] = $fresh;
                    $flt['f_q'] = $fresh;
                    $vars['fresh'] = $fresh;
                    $vars['page'] = 1;
                }
            }
            // Eigene Domain-Listen, Sortierung und Filter erst jetzt: der Zwischenspeicher bleibt für alle gleich
            $res = smApplyDomainLists($res, smDomainLists($me));
            if ($flt['sort'] === 'date') $res = smSortByDate($res);
            if ($tab === 'web' && $flt['dl'] !== '0') $res = smDomainLimit($res, 3);
            // Was Brave nicht selbst filtern kann: letzte Stunde, Videolänge
            if ($fresh === 'ph') $res = smKeepRecent($res, 3600);
            if ($tab === 'videos' && $flt['vd'] !== '') $res = smVideoDurFilter($res, $flt['vd']);
            if ($tab === 'images') {
                // Alle filtern, dann die gewünschte Seite herausschneiden: 50 pro Seite oder Nachladen, kostet keine weitere Suche
                $res = smImageFilter($res, $flt['io'], $flt['is'], $flt['isort'] === '');
                $all = $res['results'];
                $pg = (int)($vars['page'] ?? $page);
                $res['results'] = array_slice($all, ($pg - 1) * 50, 50);
                $res['more'] = count($all) > $pg * 50;
                $res['img_first'] = ($pg - 1) * 50;
                if (isset($res['iq'])) { $flt['iq'] = $res['iq']; }
            }
            // Karten (Spiele, Kurse, KI-Übersicht, Infobox): Kam die Liste mit Zeitfilter oder von Brave, holt der Browser sie gleich danach
            // über ?a=cards von Google nach (eine Anfrage mehr, aber die Liste muss nicht darauf warten)
            if ($tab === 'web' && $page === 1 && !$part && empty($res['cards']) && in_array($flt['src'], ['', 'brightdata', 'multi'], true)
                && smProvActive('brightdata') && smProvBlock('brightdata', 'web', $uid) === '') {
                $res['cards_url'] = smUrl(['a' => 'cards', 'q' => $q, 'l' => $lens ?: null, 'ft' => $ft ?: null, 'exact' => $exact ? '1' : null,
                                           'sl' => $flt['sl'] ?: null, 'cr' => $flt['cr'] ?: null]);
            } elseif ($tab === 'web' && $page === 1 && !$part && empty($res['infobox']) && !in_array($res['source'] ?? '', ['brave', 'multi'], true) && smSetting('wiki') === '1') {
                // Kurzinfo von Wikipedia, wenn der Suchdienst keine eigene liefert (sieht dann überall gleich aus)
                $res['infobox'] = smWikiCard($q);
            }
        } catch (SmSearchExhausted $e) {
            $error = $e->getMessage();
            // Alles leer oder gestört: automatisch zum eingestellten Ausweichziel, damit die Suche immer funktioniert
            $fb = smFallbackUrl($q);
            // Ausnahme: Administrator ohne eingerichteten Suchdienst. Der soll sehen, was fehlt, und gleich zur Einrichtung kommen.
            $setup = $me && $me['is_admin'] && !smProvOrder();
            if (!$part && !$setup && $tab === 'web' && $flt['src'] === '' && $fb !== '' && smSetting('fallback_auto') === '1') $redirect = $fb;
        } catch (SmSearchError $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Suchmaschine: ' . $e);
            $error = t('Da ist intern etwas schiefgelaufen. Die Einzelheiten stehen im Fehlerprotokoll des Servers.');
        }
        return $vars + ['res' => $res, 'error' => $error, 'flt' => $flt, 'fallback_go' => $redirect];
    };

    // ?warm=1: nur suchen (füllt den Zwischenspeicher), die Warteseite lädt danach sich selbst neu
    if (($_GET['warm'] ?? '') === '1') {
        $run();
        smHeaders();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $path = (string)parse_url(smBase(), PHP_URL_PATH);
        setcookie('smsw', '1', ['expires' => time() + 60, 'path' => $path !== '' ? $path : '/', 'samesite' => 'Lax', 'secure' => smIsHttps(), 'httponly' => true]);
        echo '{"ok":1}';
        exit;
    }
    if ($part) {
        // Endlos-Scrollen: nur die nächsten Treffer, ohne Seitengerüst
        $vars = $run() + $vars;
        if ($vars['error'] !== '') http_response_code(502);
        smRender('part', $vars, false);
        return;
    }
    // Seite sofort zeigen. Mit JavaScript: erst eine kleine Warteseite (Suchfeld, Filter, „Suche läuft …“), die die Suche anstößt
    // und sich danach neu lädt, dann aus dem Zwischenspeicher. Das klappt auch bei Webservern, die Antworten erst am Stück schicken.
    // Liegt die Suche schon im Zwischenspeicher, kommt die fertige Seite gleich.
    $bypass = isset($_COOKIE['smsw']);
    if ($bypass) {
        $path = (string)parse_url(smBase(), PHP_URL_PATH);
        setcookie('smsw', '', ['expires' => time() - 3600, 'path' => $path !== '' ? $path : '/', 'samesite' => 'Lax', 'secure' => smIsHttps(), 'httponly' => true]);
    }
    if (smSetting('stream') === '1' && !$bypass && ($_COOKIE['smjs'] ?? '') === '1' && $view < 0) {
        [$pq, $po] = $optFor();
        if (!smSearchPeek($tab, $pq, $po)) {
            $warm = smUrl(['warm' => '1'] + array_filter($_GET, function ($v, $k) { return is_string($v) && !in_array($k, ['warm', 'part'], true); }, ARRAY_FILTER_USE_BOTH));
            smRender('results', $vars + ['title' => $q . ' – ' . smSiteName(), 'stream' => true, 'warm' => $warm]);
            return;
        }
    }
    // Ohne JavaScript: Kopf, Suchfeld und Filter gehen gleich raus, die Treffer folgen (wenn der Webserver nicht puffert)
    if (smSetting('stream') === '1') {
        smRenderStream('results', $vars + ['title' => $q . ' – ' . smSiteName(), 'stream' => true], $run);
        return;
    }
    $vars = $run() + $vars;
    if ($vars['fallback_go'] !== '') {
        smExternalRedirect($vars['fallback_go'], $q, $vars['error'] . ' ' . t('Du wirst zu %s weitergeleitet …', smFallbackName()), 3);
    }
    smRender('results', $vars + ['title' => $q . ' – ' . smSiteName()]);
}

/**
 * Bilder in Qualitätsstufen (nur Google): erst ab 4 MP, dann ab 2 MP, dann groß, dann alle. Jede Stufe ohne die Bilder der Stufen davor.
 * Ist eine Stufe (nach dem Aussortieren) leer, geht es gleich mit der nächsten weiter. Brave sortiert stattdessen nach Auflösung.
 */
function smImageTierRun(array $res, string $apiQ, array $opt, int $uid, array $flt, int $page, bool $part): array
{
    if ($flt['iq'] === '') return $res;
    $tiers = smImageTiers();
    $tier = array_search($flt['iq'], $tiers, true);
    $key = 'iseen|' . $uid . '|' . md5(json_encode([$apiQ, $opt['country'] ?? '', $opt['safesearch'] ?? '', $opt['io'], $opt['ic'], $opt['it'], $opt['il'], $opt['freshness'], $opt['lr']]));
    for ($hop = 0; $hop < 3; $hop++) {
        if (($res['source'] ?? '') !== 'brightdata') { $res['iq_next'] = ''; return $res; }   // Brave kennt keine Stufen
        $seen = smCacheGet($key) ?? [];
        $before = [];
        for ($t = 0; $t < $tier; $t++) foreach ((array)($seen[$t] ?? []) as $h) $before[$h] = true;
        $mine = [];
        $keep = [];
        foreach ($res['results'] as $im) {
            $h = substr(md5((string)($im['src'] !== '' ? $im['src'] : $im['thumb'])), 0, 12);
            $mine[] = $h;
            if (!isset($before[$h])) $keep[] = $im;
        }
        $seen[$tier] = $mine;
        smCacheSet($key, $seen, 7200);
        $res['results'] = $keep;
        $res['iq'] = $tiers[$tier];
        $res['iq_next'] = $tiers[$tier + 1] ?? '';
        if ($keep || $res['iq_next'] === '' || $page > 1) return $res;
        $tier++;
        $res = smSearch('images', $apiQ, ['iq' => $tiers[$tier], 'page' => 1] + $opt, $uid);
    }
    return $res;
}

/**
 * ?a=cards: die Karten über den Treffern (Spiele, Kurs, KI-Übersicht, Infobox) als HTML-Stück.
 * Eine Google-Suche ohne Zeitfilter, über den Zwischenspeicher (Spielstände und Kurse höchstens 5 Minuten).
 */
function smServeCards(?array $me): void
{
    $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 400));
    $prefs = smPrefs($me);
    $lenses = smLenses($me);
    $lens = (string)($_GET['l'] ?? '');
    if (!isset($lenses[$lens])) $lens = '';
    $ft = smCleanFileType($_GET['ft'] ?? '');
    $sl = isset($_GET['sl']) && is_string($_GET['sl']) && isset(smSearchLangs()[$_GET['sl']]) ? $_GET['sl'] : '';
    $cr = isset($_GET['cr']) && is_string($_GET['cr']) && isset(smCountries()[$_GET['cr']]) ? $_GET['cr'] : '';
    $res = null;
    $retry = false;
    if ($q !== '') {
        try {
            $opt = ['page' => 1, 'freshness' => '', 'exact' => ($_GET['exact'] ?? '') === '1', 'src' => 'brightdata', 'lr' => $sl]
                 + ($cr !== '' ? ['country' => $cr] : []) + $prefs;
            $res = smSearch('web', smApiQuery($q, $ft, $lens !== '' ? $lenses[$lens] : null), $opt, $me ? (int)$me['id'] : 0);
            if (empty($res['infobox']) && smSetting('wiki') === '1') $res['infobox'] = smWikiCard($q);
        } catch (SmSearchError $e) {
            $res = null;
            $retry = true;   // Aussetzer bei Google: der Browser versucht es gleich noch einmal
        } catch (Throwable $e) {
            $res = null;
        }
    }
    smHeaders();
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, max-age=300');
    header('X-Robots-Tag: noindex');
    if (!$res) { http_response_code($retry ? 503 : 204); exit; }
    $target = $prefs['newtab'] ? ' target="_blank"' : '';
    $rel = ' rel="noopener noreferrer"';
    require SM_APP . '/views/_cards.php';
    exit;
}

/** „Auf gut Glück“: direkt zum ersten Treffer */
function smPageLucky(?array $me): void
{
    $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 400));
    if ($q === '') smRedirect(smBase());
    if ($bang = smBangTarget($q, $me)) {
        if ($bang['internal']) smRedirect($bang['to']);
        smExternalRedirect($bang['to'], $q, '', 0, smSetting('bang_ref') === '1');
    }
    $u = '';
    try {
        if ($me || smPublicQuota()) {
            $res = smApplyDomainLists(smSearch('web', $q, smPrefs($me), $me ? $me['id'] : 0), smDomainLists($me));
            $u = (string)($res['results'][0]['url'] ?? '');
        }
    } catch (Throwable $e) {
    }
    if ($u !== '') smExternalRedirect($u, $q);
    smRedirect(smUrl(['q' => $q]));
}
