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
function smSearchFilters(string $tab, ?array $prefs = null): array
{
    $g = function (string $k): string { return isset($_GET[$k]) && is_string($_GET[$k]) ? $_GET[$k] : ''; };
    $prefs = $prefs ?? smPrefs(null);
    $fq = $g('f') !== '' && (isset(smFreshness()[$g('f')]) || ($tab === 'news' && $g('f') === 'all')) ? $g('f') : '';
    $fresh = $fq === 'all' ? '' : $fq;
    $auto = false;
    if ($tab === 'news' && $fq === '') {
        $fresh = $prefs['news_fresh'] === 'all' ? '' : $prefs['news_fresh'];
        $auto = $prefs['news_expand'] && $fresh !== '';
    }
    // xf: Fortsetzung beim Erweitern, Treffer aus diesem (schon gezeigten) Zeitraum weglassen
    $xf = $tab === 'news' && $fq !== '' && $fq !== 'all' && in_array($g('xf'), ['pd', 'pw', 'pm'], true) ? $g('xf') : '';
    if ($xf !== '' && $prefs['news_expand']) $auto = true;
    $sq = in_array($tab, ['web', 'news'], true) && in_array($g('sort'), ['date', 'rel'], true) ? $g('sort') : '';
    $sort = in_array($tab, ['web', 'news'], true) ? ($sq !== '' ? $sq : $prefs['sort_' . $tab]) : 'rel';
    $f = [
        'fresh'  => $fresh,
        'f_q'    => $fq,
        'xf'     => $xf,
        'auto'   => $auto,
        'sort'   => $sort === 'date' ? 'date' : '',
        'sort_q' => $sq,
        'ft'    => $tab === 'web' ? smCleanFileType($g('ft')) : '',
        'dl'    => $tab === 'web' && $g('dl') === '0' ? '0' : '',
        'src'   => isset(smProviders()[$g('src')]) || $g('src') === 'multi' ? $g('src') : '',
        'io'    => $tab === 'images' && isset(smImageOrientations()[$g('io')]) ? $g('io') : '',
        'is'    => $tab === 'images' && isset(smImageSizes()[$g('is')]) ? $g('is') : '',
        'isort' => $tab === 'images' && $g('isort') === 'res' ? 'res' : '',
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
        smExternalRedirect($bang['to'], $q);
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

    $res = null;
    $error = '';
    try {
        if (!$me && !smPublicQuota()) throw new SmSearchError(t('Für diese Stunde sind genug Suchen ohne Anmeldung gelaufen. Bitte später wieder, oder anmelden.'));
        if (!$me && $flt['src'] === 'multi') throw new SmSearchError(t('„Alle zusammen“ gibt es nur mit Konto, weil es bei jedem Dienst eine Suche kostet.'));
        $apiQ = smApiQuery($q, $ft, $lens !== '' ? $lenses[$lens] : null);
        $xdays = ['pd' => 1, 'pw' => 7, 'pm' => 31][$flt['xf']] ?? 0;
        $opt = $prefs + ['page' => $page, 'freshness' => $fresh, 'exact' => $exact, 'endless' => $prefs['scroll'] === 'endless', 'src' => $flt['src'],
                         'older' => $xdays >= 7 ? $xdays : 0];
        $res = smSearch($tab, $apiQ, $opt, $me ? $me['id'] : 0);
        // News: gar nichts im eingestellten Zeitraum? Dann gleich den nächstgrößeren nehmen (kostet nur in diesem Fall eine Suche mehr)
        $from = $fresh;
        while ($tab === 'news' && $flt['auto'] && $flt['xf'] === '' && $page === 1 && !$part && !$res['results'] && ($nx = smNextFresh($fresh)) !== '') {
            $fresh = $nx;
            $res = smSearch($tab, $apiQ, ['freshness' => $fresh] + $opt, $me ? $me['id'] : 0);
        }
        if ($fresh !== $from) {
            $flt['fresh'] = $fresh;
            $flt['f_q'] = $fresh;
            $vars['fresh'] = $fresh;
            $vars['widened'] = $from;
        }
        // Fortsetzung beim Erweitern: was schon im kleineren Zeitraum gezeigt wurde, weglassen
        if ($flt['xf'] !== '') $res = smDropNewer($res, ['pd' => 86400, 'pw' => 7 * 86400, 'pm' => 31 * 86400][$flt['xf']]);
        // Nachladen bei News: Bleibt nach dem Aussortieren nichts übrig (oder ist die letzte Seite leer), gleich mit dem nächsten Zeitraum weiter.
        // So gibt es keine leeren Abschnitte. Höchstens zwei Schritte, damit eine leere Suche nicht alle Dienste abklappert.
        for ($hop = 0; $hop < 2 && $tab === 'news' && ($part || $flt['xf'] !== '') && $flt['auto'] && !$res['results'] && $fresh !== '' && ($nx = smNextFresh($fresh)) !== ''; $hop++) {
            $flt['xf'] = $fresh;
            $fresh = $nx;
            $page = 1;
            $xdays = ['pd' => 1, 'pw' => 7, 'pm' => 31][$flt['xf']];
            $res = smSearch($tab, $apiQ, ['freshness' => $fresh, 'page' => 1, 'older' => $xdays >= 7 ? $xdays : 0] + $opt, $me ? $me['id'] : 0);
            $res = smDropNewer($res, $xdays * 86400);
            $flt['fresh'] = $fresh;
            $flt['f_q'] = $fresh;
            $vars['fresh'] = $fresh;
            $vars['page'] = 1;
        }
        // Eigene Domain-Listen, Sortierung und Filter erst jetzt: der Zwischenspeicher bleibt für alle gleich
        $res = smApplyDomainLists($res, smDomainLists($me));
        if ($flt['sort'] === 'date') $res = smSortByDate($res);
        if ($tab === 'web' && $flt['dl'] !== '0') $res = smDomainLimit($res, 3);
        if ($tab === 'images') {
            // Alle (bis 200) filtern, dann die gewünschte Seite herausschneiden: 50 pro Seite oder Nachladen, kostet keine weitere Suche
            $res = smImageFilter($res, $flt['io'], $flt['is'], $flt['isort'] === 'res');
            $all = $res['results'];
            $res['results'] = array_slice($all, ($page - 1) * 50, 50);
            $res['more'] = count($all) > $page * 50;
            $res['img_first'] = ($page - 1) * 50;
        }
        // Kurzinfo von Wikipedia, wenn der Suchdienst keine eigene liefert (sieht dann überall gleich aus)
        if ($tab === 'web' && $page === 1 && !$part && empty($res['infobox']) && ($res['source'] ?? '') !== 'brave' && smSetting('wiki') === '1') {
            $res['infobox'] = smWikiCard($q);
        }
    } catch (SmSearchExhausted $e) {
        $error = $e->getMessage();
        // Alles leer oder gestört: automatisch zum eingestellten Ausweichziel, damit die Suche immer funktioniert
        $fb = smFallbackUrl($q);
        // Ausnahme: Administrator ohne eingerichteten Suchdienst. Der soll sehen, was fehlt, und gleich zur Einrichtung kommen.
        $setup = $me && $me['is_admin'] && !smProvOrder();
        if (!$part && !$setup && $tab === 'web' && $flt['src'] === '' && $fb !== '' && smSetting('fallback_auto') === '1') {
            smExternalRedirect($fb, $q, $error . ' ' . t('Du wirst zu %s weitergeleitet …', smFallbackName()), 3);
        }
    } catch (SmSearchError $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Suchmaschine: ' . $e);
        $error = t('Da ist intern etwas schiefgelaufen. Die Einzelheiten stehen im Fehlerprotokoll des Servers.');
    }

    $vars['res'] = $res;
    $vars['error'] = $error;
    $vars['flt'] = $flt;
    if ($part) {
        // Endlos-Scrollen: nur die nächsten Treffer, ohne Seitengerüst
        if ($error !== '') http_response_code(502);
        smRender('part', $vars, false);
        return;
    }
    smRender('results', $vars + ['title' => $q . ' – ' . smSiteName()]);
}

/** „Auf gut Glück“: direkt zum ersten Treffer */
function smPageLucky(?array $me): void
{
    $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 400));
    if ($q === '') smRedirect(smBase());
    if ($bang = smBangTarget($q, $me)) {
        if ($bang['internal']) smRedirect($bang['to']);
        smExternalRedirect($bang['to'], $q);
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
