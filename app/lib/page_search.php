<?php
/* Suchmaschine – Seiten Suche und „Auf gut Glück“, Reiter und Filter aus der Adresse */
defined('SM_VERSION') || exit;

function smTabs(): array
{
    return ['web' => t('Alle'), 'images' => t('Bilder'), 'news' => t('News'), 'videos' => t('Videos')];
}

/**
 * Wohin es nach der letzten Seite einer Zeitstufe weitergeht (bei „Neueste zuerst“ in Alle, News, Videos):
 * [Adresse-Parameter, Beschriftung] oder null.
 */
function smNextTier(string $tab, array $flt, string $fresh, ?array $res): ?array
{
    if ($tab === 'images' || empty($flt['casc']) || empty($flt['auto']) || $fresh === '' || ($nx = smNextFresh($fresh)) === '') return null;
    return [['f' => $nx, 'xf' => $fresh, 'p' => null], $tab === 'news' ? t('Ältere News: %s', smTierLabel($nx)) : t('Ältere Treffer: %s', smTierLabel($nx))];
}

/**
 * Filter und Sortierung aus der Adresse, geprüft. Was nicht in der Adresse steht, kommt aus den Einstellungen des Kontos
 * (Sortierung, News-Zeitraum). Die Felder mit _q sind das, was wirklich in der Adresse steht: nur das zählt als Abweichung
 * für „Alle Filter zurücksetzen“ und wandert in die Links.
 */
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
    // Bilder: beste Qualität zuerst (nach Auflösung sortiert) oder Relevanz
    $isort = $tab === 'images' && $g('isort') === 'rel' ? 'rel' : '';
    $isz = $tab === 'images' && isset(smImageSizes()[$g('is')]) ? $g('is') : '';
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
        'io'    => $tab === 'images' && isset(smImageOrientations()[$g('io')]) ? $g('io') : '',
        'is'    => $isz,
        'isort' => $isort,
        // Eigener Zeitraum, Wortgenau, Sprache und Region gehen mit zu Brave, Videolänge wird danach gefiltert
        'fd'    => $fd,
        'td'    => $td,
        'vb'    => in_array($tab, ['web', 'news'], true) && $g('vb') === '1' ? '1' : '',
        'sl'    => isset(smSearchLangs()[$g('sl')]) ? $g('sl') : '',
        'cr'    => isset(smCountries()[$g('cr')]) ? $g('cr') : '',
        'vd'    => $tab === 'videos' && in_array($g('vd'), ['s', 'm', 'l'], true) ? $g('vd') : '',
    ];
    return $f;
}

/**
 * Ist die Eingabe eine Adresse? Liefert [Adresse, hat einen Pfad] oder null.
 * Mit https:// immer; ohne nur mit Pfad (chefkoch.de/rezepte/…), damit Suchen wie „node.js“ Suchen bleiben.
 */
function smQueryUrl(string $q): ?array
{
    $q = trim($q);
    if ($q === '' || preg_match('/\s/u', $q)) return null;
    $scheme = (bool)preg_match('~^https?://~i', $q);
    if (!$scheme && preg_match('~^www\.~i', $q) && strpos($q, '/') === false) return ['https://' . $q, false];
    $u = $scheme ? $q : 'https://' . $q;
    $p = parse_url($u);
    $host = strtolower((string)($p['host'] ?? ''));
    if ($host === '' || !preg_match('/^([a-z0-9äöüß-]+\.)+[a-z]{2,}$/u', $host) || isset($p['user']) || isset($p['pass'])) return null;
    $path = (string)($p['path'] ?? '');
    $hasPath = trim($path, '/') !== '' || isset($p['query']);
    if (!$scheme && !$hasPath) return null;
    return [smLink($u), $hasPath];
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

    // Bangs: vor jeder Anfrage an Brave, kosten also nichts
    if (!$part && ($bang = smBangTarget($q, $me))) {
        if ($bang['internal']) smRedirect($bang['to']);
        smExternalRedirect($bang['to'], $q, '', 0, smSetting('bang_ref') === '1');
    }

    // Adresse eingefügt (z. B. ein Rezept von Chefkoch): mit Konto gleich in den Lesemodus (Drucken, PDF, nur das Rezept),
    // ohne Konto oder bei einer bloßen Domain direkt zur Seite
    if (!$part && ($_GET['web'] ?? '') !== '1' && ($qu = smQueryUrl($q)) !== null && $qu[0] !== '') {
        if ($me && $qu[1]) smRedirect(smUrl(['a' => 'read', 'u' => $qu[0]]));
        smExternalRedirect($qu[0], $q);
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

    // Sofort-Antworten (Rechner, Währungen, Wetter …): ohne Suchdienst, gibt es auch für Gäste
    if (!$part && $tab === 'web' && $page === 1 && ($_GET['web'] ?? '') !== '1' && $lens === '' && $ft === '') {
        $card = smInstant($q, $me);
        if ($card !== null) {
            $vars['instant'] = $card;
            smRender('results', $vars + ['title' => $q . ' – ' . smSiteName()]);
            return;
        }
    }

    $apiQ = smApiQuery($q, $ft, $lens !== '' ? $lenses[$lens] : null);
    // Gäste suchen nie bei Brave: gleich zur gewählten Suchmaschine (mit Linse und Dateityp in der Suche)
    if (!$me) {
        if ($part) { http_response_code(403); exit; }
        smExternalRedirect(smFallbackTabUrl($apiQ, $tab, null), $q);
    }

    $uid = (int)$me['id'];
    $res = null;
    $error = '';
    try {
        $opt = ['page' => $page, 'freshness' => $fresh, 'exact' => $exact, 'endless' => $prefs['scroll'] === 'endless',
                'from' => $flt['fd'], 'to' => $flt['td'], 'verb' => $flt['vb'] === '1', 'lr' => $flt['sl']]
            + ($flt['cr'] !== '' ? ['country' => $flt['cr']] : []) + $prefs;
        $res = smSearch($tab, $apiQ, $opt, $uid);
        // Rechtschreibhilfe: Brave korrigiert Tippfehler meist selbst („Ergebnisse für …“). Gefragt wird nur, wenn nicht:
        // bei „Wortgenau“ oder „Stattdessen suchen nach“ und wenn kaum etwas gefunden wurde. Das schont das Kontingent.
        if (!$part && $page === 1 && empty($res['altered']) && ($exact || $flt['vb'] === '1' || count($res['results']) < 3)) {
            $vars['didyoumean'] = smSpellcheck($q, $me);
        }
        // KI-Antwort von Brave: auf Knopfdruck oder automatisch (der Browser holt sie, die Treffer warten nicht)
        if (!$part && $page === 1 && $tab === 'web' && smExActive('answer') && $prefs['x_answer'] !== 'off') {
            $vars['answer'] = ['url' => smUrl(['a' => 'answer', 'q' => $q]), 'auto' => $prefs['x_answer'] === 'auto'];
        }
        if ($tab !== 'images') {
            // Zeitstufen: Ist der Start-Zeitraum leer, gleich den nächsten nehmen (kostet nur in diesem Fall eine Suche mehr)
            $from = $fresh;
            while ($flt['casc'] && $flt['auto'] && $flt['xf'] === '' && $page === 1 && !$part && !$res['results'] && ($nx = smNextFresh($fresh)) !== '') {
                $fresh = $nx;
                $res = smSearch($tab, $apiQ, ['freshness' => $fresh === 'all' ? '' : $fresh] + $opt, $uid);
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
                $res = smSearch($tab, $apiQ, ['freshness' => $fresh === 'all' ? '' : $fresh, 'page' => 1] + $opt, $uid);
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
        }
    } catch (SmSearchExhausted $e) {
        // Brave kann gerade nicht (kein Key, Limit, Kontingent, Störung): sofort zur eigenen Suchmaschine, ohne Zwischenstopp
        if (!$part) smExternalRedirect(smFallbackTabUrl($apiQ, $tab, $me), $q);
        $error = $e->getMessage();
    } catch (SmSearchError $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Suchmaschine: ' . $e);
        $error = t('Da ist intern etwas schiefgelaufen. Die Einzelheiten stehen im Fehlerprotokoll des Servers.');
    }
    $vars = ['res' => $res, 'error' => $error, 'flt' => $flt] + $vars;
    if ($part) {
        // Endlos-Scrollen: nur die nächsten Treffer, ohne Seitengerüst
        if ($error !== '') http_response_code(502);
        smRender('part', $vars, false);
        return;
    }
    smRender('results', $vars + ['title' => $q . ' – ' . smSiteName()]);
}

/** „Auf gut Glück“: direkt zum ersten Treffer (Gäste und ohne Brave: zur eigenen Suchmaschine) */
function smPageLucky(?array $me): void
{
    $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 400));
    if ($q === '') smRedirect(smBase());
    if ($bang = smBangTarget($q, $me)) {
        if ($bang['internal']) smRedirect($bang['to']);
        smExternalRedirect($bang['to'], $q, '', 0, smSetting('bang_ref') === '1');
    }
    $u = '';
    if ($me) {
        try {
            $res = smApplyDomainLists(smSearch('web', $q, smPrefs($me), (int)$me['id']), smDomainLists($me));
            $u = (string)($res['results'][0]['url'] ?? '');
        } catch (Throwable $e) {
        }
    }
    smExternalRedirect($u !== '' ? $u : smFallbackTabUrl($q, 'web', $me), $q);
}
