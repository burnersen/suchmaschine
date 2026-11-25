<?php
/* Suchmaschine – Ablauf: welche Seite, welche Aktion */
defined('SM_VERSION') || exit;

function smRun(): void
{
    $a = isset($_GET['a']) && is_string($_GET['a']) ? $_GET['a'] : '';
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

    /* ---------- Ohne Anmeldung und ohne Datenbank: Dateien, OpenSearch, Manifest, Sprache ---------- */
    if ($a === 'asset') smServeAsset();
    if ($a === 'opensearch') smServeOpenSearch();
    if ($a === 'manifest') smServeManifest();
    if ($a === 'logo') smServeLogo();
    if ($a === 'appicon') smServeAppIcon();
    if ($a === '' && isset($_GET['lang']) && in_array($_GET['lang'], ['de', 'en'], true)) {
        smLangCookie($_GET['lang']);
        smRedirect(smBase());
    }

    smMaintenance();

    /* ---------- Einrichtung ---------- */
    if (!smHasUsers()) {
        $err = '';
        if ($post && $a === 'setup' && smSameOrigin()) {
            // Der Brave-Key ist freiwillig: andere Suchdienste lassen sich auch später in der Verwaltung eintragen
            $key = trim((string)($_POST['key'] ?? ''));
            $err = $key === '' ? '' : smTestKey($key);
            if ($err === '') $err = smSetup((string)($_POST['name'] ?? ''), (string)($_POST['pass'] ?? ''), (string)($_POST['pass2'] ?? ''), $key);
            if ($err === '') {
                smFlash('ok', t('Fertig! Die Suchmaschine ist eingerichtet und du bist Administrator.'));
                smRedirect($key === '' ? smUrl(['a' => 'admin', 'tab' => 'providers']) : smBase());
            }
        }
        smRender('setup', ['title' => t('Einrichten'), 'error' => $err, 'bare' => true]);
        return;
    }

    // Kurzlinks: …/?s=abc123 führt ohne Anmeldung direkt zum Ziel
    if ($a === '' && isset($_GET['s']) && is_string($_GET['s'])) smServeShort($_GET['s']);

    $me = smUser();

    // Login per geheimem Token in der Adresse (?token=…, auch zusammen mit &q=… aus der Adressleiste).
    // Danach sofort weiter auf dieselbe Adresse ohne Token, damit er nicht in Verlauf oder Lesezeichen hängen bleibt.
    if (isset($_GET['token']) && is_string($_GET['token']) && !$post) {
        $clean = $_GET;
        unset($clean['token']);
        $to = smBase() . ($clean ? '?' . http_build_query($clean) : '');
        // Nur direkt aufgerufen (Adressleiste, Lesezeichen, Browser-Suche), nicht über einen Link einer fremden Seite.
        // Sonst könnte eine fremde Seite dich unbemerkt in ein anderes Konto einloggen (Login-CSRF).
        $site = (string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
        if ($site === 'cross-site' || $site === 'same-site') {
            smFlash('error', t('Aus Sicherheitsgründen funktioniert die Token-Adresse nur direkt aus der Adressleiste, als Lesezeichen oder als Browser-Suche, nicht über einen Link auf einer anderen Seite.'));
            smRedirect($to);
        }
        [$uid, $terr] = smTokenLogin(trim($_GET['token']));
        if ($uid === 0) {
            smFlash('error', $terr);
            smRedirect($to);
        }
        if ($me && $me['id'] !== $uid) {
            // Schon mit einem anderen Konto angemeldet: nicht stillschweigend wechseln
            smFlash('error', t('Du bist schon als %s angemeldet. Zum Wechseln bitte erst abmelden.', $me['name']));
            smRedirect($to);
        }
        if (!$me) {
            smStartSession($uid, SM_TOKEN_DAYS);
        } else {
            // Schon angemeldet: Sitzung auf die lange Laufzeit heben
            smQ('UPDATE sessions SET days = ? WHERE hash = ?', [SM_TOKEN_DAYS, $me['hash']]);
        }
        smRedirect($to);
    }

    if ($post && !smCsrfOk($me)) {
        smFlash('error', t('Das Formular war veraltet. Bitte noch einmal versuchen.'));
        smRedirect(smSelf());
    }

    /* ---------- Anmelden, Abmelden ---------- */
    if ($a === 'login') {
        if ($me) smRedirect(smBase());
        $err = '';
        if ($post) {
            $err = smLogin((string)($_POST['name'] ?? ''), (string)($_POST['pass'] ?? ''));
            if ($err === '') smRedirect(smSafeNext((string)($_POST['next'] ?? '')));
        }
        smRender('login', ['title' => t('Anmelden'), 'error' => $err, 'next' => (string)($_GET['next'] ?? $_POST['next'] ?? ''), 'bare' => true]);
        return;
    }
    if ($a === 'logout' && $post && $me) {
        smLogout($me);
        smRedirect(smBase());
    }

    $public = smSetting('public') === '1';
    if (!$me && !($public && in_array($a, ['', 'lucky', 'help', 'img', 'qr', 'tools'], true))) {
        if ($a === 'img' || $a === 'qr') {
            http_response_code(403);
            exit;
        }
        smRedirect(smUrl(['a' => 'login', 'next' => smSelf() !== smBase() ? smSelf() : null]));
    }

    switch ($a) {
        case 'img':
            smServeImgProxy();
            return;
        case 'qr':
            smServeQr();
            return;
        case 'help':
            smRender('help', ['me' => $me, 'prefs' => smPrefs($me), 'title' => t('Hilfe') . ' – ' . smSiteName()]);
            return;
        case 'tools':
            smPageTools($me, $post);
            return;
        case 'export':
            smServeExport($me);
            return;
        case 'domain':
            smPageDomain($me, $post);
            return;
        case 'settings':
            smPageSettings($me, $post);
            return;
        case 'admin':
            if (!$me['is_admin']) smRedirect(smBase());
            smPageAdmin($me, $post);
            return;
        case 'lucky':
            smPageLucky($me);
            return;
        default:
            smPageSearch($me);
            smAutoUpdate();
    }
}

/** Werkzeuge: Passwort-Generator, QR-Code, Kurzlinks (Kurzlinks nur mit Konto) */
function smPageTools(?array $me, bool $post): void
{
    if ($post && $me) {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'short_add') {
            if (smSetting('shortener') !== '1') {
                smFlash('error', t('Kurzlinks sind ausgeschaltet.'));
            } else {
                [$code, $err] = smShortCreate($me['id'], (string)($_POST['url'] ?? ''));
                if ($err !== '') smFlash('error', $err); else smFlash('ok', t('Kurzlink: %s', smShortUrl($code)));
                if ($code !== '') smRedirect(smUrl(['a' => 'tools', 'new' => $code]) . '#kurzlinks');
            }
        } elseif ($do === 'short_del') {
            smQ('DELETE FROM shortlinks WHERE code = ? AND user_id = ?', [(string)($_POST['code'] ?? ''), $me['id']]);
            smFlash('ok', t('Kurzlink gelöscht.'));
        }
        smRedirect(smUrl(['a' => 'tools']) . '#kurzlinks');
    }
    $new = isset($_GET['new']) && is_string($_GET['new']) && preg_match('/^[A-Za-z0-9]{4,12}$/', $_GET['new']) ? $_GET['new'] : '';
    smRender('tools', ['me' => $me, 'prefs' => smPrefs($me), 'title' => t('Werkzeuge') . ' – ' . smSiteName(), 'new' => $new,
        'qr' => isset($_GET['qr']) && is_string($_GET['qr']) ? mb_substr($_GET['qr'], 0, SM_QR_MAX) : '']);
}

/** Nur Adressen innerhalb der Suchmaschine als Ziel nach dem Anmelden */
function smSafeNext(string $next): string
{
    if ($next !== '' && strpos($next, smBase()) === 0 && strpos($next, '//') === false && !preg_match('/[\r\n]/', $next)) return $next;
    return smBase();
}

/* ---------- Dateien ---------- */

function smServeAsset(): void
{
    $types = ['app.css' => 'text/css; charset=utf-8', 'app.js' => 'text/javascript; charset=utf-8', 'icon.svg' => 'image/svg+xml',
              'icon-180.png' => 'image/png', 'icon-192.png' => 'image/png', 'icon-512.png' => 'image/png'];
    $f = isset($_GET['f']) && is_string($_GET['f']) ? $_GET['f'] : '';
    if (!isset($types[$f]) || !is_file(SM_APP . '/assets/' . $f)) {
        http_response_code(404);
        exit;
    }
    smHeaders();
    header('Content-Type: ' . $types[$f]);
    header('Cache-Control: ' . (isset($_GET['v']) ? 'public, max-age=31536000, immutable' : 'no-cache'));
    readfile(SM_APP . '/assets/' . $f);
    exit;
}

function smServeOpenSearch(): void
{
    smHeaders();
    header('Content-Type: application/opensearchdescription+xml; charset=utf-8');
    header('Cache-Control: ' . (isset($_GET['k']) ? 'private, no-store' : 'no-cache'));
    $name = smSiteName();
    // Persönliche Variante mit Login-Token: der Browser meldet sich bei jeder Suche aus der Adressleiste selbst an
    $k = isset($_GET['k']) && is_string($_GET['k']) && preg_match('/^[A-Za-z0-9._~-]{32,200}$/', $_GET['k']) ? $_GET['k'] : '';
    $tpl = smOrigin() . '?' . ($k !== '' ? 'token=' . rawurlencode($k) . '&' : '') . 'q={searchTerms}';
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
       . '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">' . "\n"
       . '  <ShortName>' . h(mb_substr($name, 0, 16)) . "</ShortName>\n"
       . '  <Description>' . h(t('Eigene Suchmaschine ohne Werbung')) . "</Description>\n"
       . "  <InputEncoding>UTF-8</InputEncoding>\n"
       . '  <Image type="image/svg+xml">' . h(smOrigin() . '?a=asset&f=icon.svg') . "</Image>\n"
       . '  <Url type="text/html" method="get" template="' . h($tpl) . "\"/>\n"
       . "</OpenSearchDescription>\n";
    exit;
}

function smServeManifest(): void
{
    smHeaders();
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: no-cache');
    $name = smSiteName();
    $n = smHasUsersSafe() ? trim(smSetting('site_name')) : '';
    echo json_encode([
        'id' => './', 'name' => $name, 'short_name' => $n !== '' ? mb_substr($n, 0, 12) : t('Suchmaschine'),
        'start_url' => './', 'scope' => './', 'display' => 'standalone',
        'lang' => smLang(), 'background_color' => '#ffffff', 'theme_color' => '#ffffff',
        // PNG-Symbole: Startbildschirme von Android und iPhone nehmen kein SVG
        'icons' => [
            ['src' => smAppIconUrl(192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => smAppIconUrl(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => smAppIconUrl(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Eigenes Logo ausliefern (liegt in data/, darum über diese Datei) */
function smServeLogo(): void
{
    $type = smHasUsersSafe() ? smSetting('logo_type') : '';
    $file = SM_DATA_DIR . '/logo.bin';
    if ($type === '' || !is_file($file)) {
        http_response_code(404);
        exit;
    }
    smHeaders();
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
    header('Content-Type: ' . $type);
    header('Cache-Control: ' . (isset($_GET['v']) ? 'public, max-age=31536000, immutable' : 'no-cache'));
    readfile($file);
    exit;
}

function smHasUsersSafe(): bool
{
    try {
        return smHasUsers();
    } catch (Throwable $e) {
        return false;
    }
}

/* ---------- Suche ---------- */

/** Sucheinstellungen: eigene des Kontos, sonst die der Installation */
function smPrefs(?array $me): array
{
    $p = $me['prefs'] ?? [];
    return [
        'country'     => isset($p['country'], smCountries()[$p['country']]) ? $p['country'] : smSetting('country'),
        'search_lang' => isset($p['search_lang'], smSearchLangs()[$p['search_lang']]) ? $p['search_lang'] : smSetting('search_lang'),
        'safesearch'  => in_array($p['safesearch'] ?? '', ['off', 'moderate', 'strict'], true) ? $p['safesearch'] : smSetting('safesearch'),
        'newtab'      => !empty($p['newtab']),
        'history'     => !isset($p['history']) || !empty($p['history']),
        'scroll'      => in_array($p['scroll'] ?? '', ['pages', 'endless'], true) ? $p['scroll'] : (smSetting('scroll') === 'endless' ? 'endless' : 'pages'),
    ];
}

function smTabs(): array
{
    return ['web' => t('Alle'), 'images' => t('Bilder'), 'news' => t('News'), 'videos' => t('Videos')];
}

/** Filter und Sortierung aus der Adresse, geprüft. Abweichungen vom Standard zählen für „Alle Filter zurücksetzen“. */
function smSearchFilters(string $tab): array
{
    $g = function (string $k): string { return isset($_GET[$k]) && is_string($_GET[$k]) ? $_GET[$k] : ''; };
    $f = [
        'fresh' => isset(smFreshness()[$g('f')]) ? $g('f') : '',
        'ft'    => $tab === 'web' ? smCleanFileType($g('ft')) : '',
        'sort'  => in_array($tab, ['web', 'news'], true) && $g('sort') === 'date' ? 'date' : '',
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
    $flt = smSearchFilters($tab);
    $ft = $flt['ft'];
    $fresh = $flt['fresh'];
    $vars = ['me' => $me, 'prefs' => $prefs, 'q' => $q, 'tab' => $tab, 'page' => $page, 'fresh' => $fresh, 'exact' => $exact,
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
        $res = smSearch($tab, smApiQuery($q, $ft, $lens !== '' ? $lenses[$lens] : null),
            $prefs + ['page' => $page, 'freshness' => $fresh, 'exact' => $exact, 'endless' => $prefs['scroll'] === 'endless', 'src' => $flt['src']],
            $me ? $me['id'] : 0);
        // Eigene Domain-Listen, Sortierung und Filter erst jetzt: der Zwischenspeicher bleibt für alle gleich
        $res = smApplyDomainLists($res, smDomainLists($me));
        if (in_array($tab, ['web', 'news'], true) && ($flt['sort'] === 'date' || $fresh !== '')) $res = smSortByDate($res);
        if ($tab === 'web' && $flt['dl'] !== '0') $res = smDomainLimit($res, 3);
        if ($tab === 'images') $res = smImageFilter($res, $flt['io'], $flt['is'], $flt['isort'] === 'res');
        // Kurzinfo von Wikipedia, wenn der Suchdienst keine eigene liefert (sieht dann überall gleich aus)
        if ($tab === 'web' && $page === 1 && !$part && empty($res['infobox']) && ($res['source'] ?? '') !== 'brave' && smSetting('wiki') === '1') {
            $res['infobox'] = smWikiCard($q);
        }
    } catch (SmSearchExhausted $e) {
        $error = $e->getMessage();
        // Alles leer oder gestört: automatisch zum eingestellten Ausweichziel, damit die Suche immer funktioniert
        $fb = smFallbackUrl($q);
        if (!$part && $tab === 'web' && $flt['src'] === '' && $fb !== '' && smSetting('fallback_auto') === '1') {
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

/* ---------- Eigene Einstellungen: jedes Konto hat seinen eigenen Bereich ---------- */

function smSettingsTabs(): array
{
    return ['search' => t('Suche'), 'domains' => t('Domains'), 'bangs' => t('Bangs'), 'lenses' => t('Linsen'), 'account' => t('Konto')];
}

function smPageSettings(array $me, bool $post): void
{
    $tab = (string)($_GET['tab'] ?? 'search');
    if (!isset(smSettingsTabs()[$tab])) $tab = 'search';

    if ($post) {
        $do = (string)($_POST['do'] ?? '');
        $p = $me['prefs'];
        $err = '';
        $ok = t('Gespeichert.');
        switch ($do) {
            case 'prefs':
                $c = (string)($_POST['country'] ?? '');
                $l = (string)($_POST['search_lang'] ?? '');
                $s = (string)($_POST['safesearch'] ?? '');
                $p['country'] = isset(smCountries()[$c]) ? $c : smSetting('country');
                $p['search_lang'] = isset(smSearchLangs()[$l]) ? $l : smSetting('search_lang');
                $p['safesearch'] = in_array($s, ['off', 'moderate', 'strict'], true) ? $s : smSetting('safesearch');
                $p['newtab'] = !empty($_POST['newtab']);
                $p['history'] = !empty($_POST['history']);
                $p['scroll'] = ($_POST['scroll'] ?? '') === 'endless' ? 'endless' : 'pages';
                $p['city'] = trim(mb_substr(smPlain((string)($_POST['city'] ?? '')), 0, 60));
                $ws = (string)($_POST['weather_src'] ?? '');
                $p['weather_src'] = isset(smWeatherSources()[$ws]) ? $ws : '';
                break;
            case 'token_gen':
                $err = smTokenSet($me['id'], smTokenGenerate());
                $ok = t('Neuer Login-Token erzeugt. Alte Token-Adressen funktionieren ab jetzt nicht mehr.');
                break;
            case 'token_set':
                $err = smTokenSet($me['id'], trim((string)($_POST['token'] ?? '')));
                if (trim((string)($_POST['token'] ?? '')) === '') $err = t('Bitte einen Token eingeben.');
                $ok = t('Login-Token gespeichert.');
                break;
            case 'token_del':
                smTokenSet($me['id'], '');
                $ok = t('Login-Token gelöscht.');
                break;
            case 'logout_others':
                $ok = t('%d andere Geräte abgemeldet.', smLogoutOthers($me));
                break;
            case 'domains':
                foreach (['block', 'raise', 'lower'] as $k) $p[$k] = smCleanDomains((string)($_POST[$k] ?? ''));
                // Eine Domain steht nur in einer Liste: Blockieren geht vor Hochstufen geht vor Herabstufen
                $p['raise'] = array_values(array_diff($p['raise'], $p['block']));
                $p['lower'] = array_values(array_diff($p['lower'], $p['block'], $p['raise']));
                break;
            case 'bang_add':
                $t = smCleanBangTrigger($_POST['t'] ?? '');
                $u = smCleanBangUrl($_POST['u'] ?? '');
                if ($t === '') {
                    $err = t('Das Kürzel darf nur Buchstaben, Ziffern, Punkt, Minus und Unterstrich enthalten (höchstens 24 Zeichen).');
                } elseif (smBangReserved($t)) {
                    $err = t('Das Kürzel !%s ist für die Reiter oder Suchdienste reserviert.', $t);
                } elseif ($u === '') {
                    $err = t('Die Adresse muss mit https:// oder http:// beginnen. {q} steht für den Suchbegriff.');
                } else {
                    $list = array_values(array_filter(smCleanBangs($p['bangs'] ?? []), function ($b) use ($t) { return $b['t'] !== $t; }));
                    if (count($list) >= SM_MAX_BANGS) {
                        $err = t('Es sind schon zu viele eigene Bangs.');
                    } else {
                        $list[] = ['t' => $t, 'n' => (string)($_POST['n'] ?? ''), 'u' => $u];
                        $p['bangs'] = smCleanBangs($list);
                        $ok = t('Bang !%s gespeichert.', $t);
                    }
                }
                break;
            case 'bang_del':
                $t = smCleanBangTrigger($_POST['t'] ?? '');
                $p['bangs'] = array_values(array_filter(smCleanBangs($p['bangs'] ?? []), function ($b) use ($t) { return $b['t'] !== $t; }));
                $ok = t('Bang !%s gelöscht.', $t);
                break;
            case 'bang_builtin':
                $on = is_array($_POST['on'] ?? null) ? $_POST['on'] : [];
                $p['bang_off'] = array_values(array_diff(array_keys(smBuiltinBangs()), $on));
                break;
            case 'lens_save':
                $id = (string)($_POST['id'] ?? '');
                $lens = ['id' => $id, 'n' => (string)($_POST['n'] ?? ''), 's' => smCleanDomains((string)($_POST['s'] ?? ''), SM_LENS_SITES), 'x' => (string)($_POST['x'] ?? '')];
                $clean = smCleanLenses([$lens]);
                if (!$clean) {
                    $err = t('Eine Linse braucht einen Namen und mindestens eine Domain oder einen Operator.');
                    break;
                }
                $list = smCleanLenses($p['lenses'] ?? []);
                $found = false;
                foreach ($list as $i => $l) {
                    if ($l['id'] === $id) { $list[$i] = $clean[0]; $found = true; }
                }
                if (!$found) {
                    if (count($list) >= SM_MAX_LENSES) { $err = t('Es sind schon zu viele eigene Linsen.'); break; }
                    $list[] = $clean[0];
                }
                $p['lenses'] = $list;
                $ok = t('Linse „%s“ gespeichert.', $clean[0]['n']);
                break;
            case 'lens_del':
                $id = (string)($_POST['id'] ?? '');
                $p['lenses'] = array_values(array_filter(smCleanLenses($p['lenses'] ?? []), function ($l) use ($id) { return $l['id'] !== $id; }));
                $ok = t('Linse gelöscht.');
                break;
            case 'lens_builtin':
                $on = is_array($_POST['on'] ?? null) ? $_POST['on'] : [];
                $p['lens_off'] = array_values(array_diff(array_keys(smBuiltinLenses()), $on));
                break;
            case 'import':
                [$in, $err] = smReadImport('file', 'suchmaschine-prefs');
                if ($in !== null) {
                    $p = smCleanPrefs($in);
                    $ok = t('Einstellungen wiederhergestellt.');
                }
                break;
            case 'pass':
                $row = smQ('SELECT pass FROM users WHERE id = ?', [$me['id']])->fetch();
                $new = (string)($_POST['pass'] ?? '');
                if (!$row || !password_verify((string)($_POST['old'] ?? ''), $row['pass'])) {
                    $err = t('Das bisherige Passwort stimmt nicht.');
                } elseif ($new !== (string)($_POST['pass2'] ?? '')) {
                    $err = t('Die beiden Passwörter stimmen nicht überein.');
                } elseif (($e = smUserSetPass($me['id'], $new, $me['hash'])) !== '') {
                    $err = $e;
                } else {
                    $ok = t('Passwort geändert. Andere Geräte sind jetzt abgemeldet.');
                }
                break;
            default:
                $err = t('Unbekannte Aktion.');
        }
        if ($err === '' && !in_array($do, ['pass', 'token_gen', 'token_set', 'token_del', 'logout_others'], true)) smUserSavePrefs($me['id'], $p);
        smFlash($err === '' ? 'ok' : 'error', $err === '' ? $ok : $err);
        smRedirect(smUrl(['a' => 'settings', 'tab' => $tab !== 'search' ? $tab : null]));
    }
    $edit = null;
    if ($tab === 'lenses' && isset($_GET['edit'])) {
        foreach (smCleanLenses($me['prefs']['lenses'] ?? []) as $l) if ($l['id'] === $_GET['edit']) $edit = $l;
    }
    $token = in_array($tab, ['search', 'account'], true) ? smTokenGet($me['id']) : '';
    smRender('settings', ['me' => $me, 'prefs' => smPrefs($me), 'title' => t('Einstellungen'), 'tab' => $tab, 'edit' => $edit,
        'token' => $token, 'osToken' => $token]);
}

/** Schnellaktion an einem Treffer: Domain blockieren, hochstufen, herabstufen oder zurücksetzen */
function smPageDomain(array $me, bool $post): void
{
    $back = smSafeNext((string)($_POST['back'] ?? ''));
    if (!$post) smRedirect($back);
    $d = smCleanDomain((string)($_POST['d'] ?? ''));
    $act = (string)($_POST['act'] ?? '');
    if ($d === '' || !in_array($act, ['block', 'raise', 'lower', 'clear'], true)) {
        smFlash('error', t('Unbekannte Aktion.'));
        smRedirect($back);
    }
    $p = $me['prefs'];
    foreach (['block', 'raise', 'lower'] as $k) {
        $p[$k] = array_values(array_filter(smCleanDomains($p[$k] ?? []), function ($x) use ($d) { return $x !== $d; }));
    }
    if ($act !== 'clear') {
        if (count($p[$act]) >= SM_MAX_DOMAINS) {
            smFlash('error', t('Die Liste ist voll (höchstens %d Domains).', SM_MAX_DOMAINS));
            smRedirect($back);
        }
        $p[$act][] = $d;
    }
    smUserSavePrefs($me['id'], $p);
    $msg = ['block' => t('%s wird ab jetzt ausgeblendet.', $d), 'raise' => t('%s wird ab jetzt hochgestuft.', $d),
            'lower' => t('%s wird ab jetzt herabgestuft.', $d), 'clear' => t('%s ist wieder normal.', $d)];
    smFlash('ok', $msg[$act]);
    smRedirect($back);
}

/* ---------- Verwaltung (Administrator) ---------- */

function smAdminTabs(): array
{
    return ['users' => t('Konten'), 'providers' => t('Suchdienste'), 'usage' => t('Verbrauch'), 'config' => t('Einstellungen'),
            'backup' => t('Sicherung'), 'update' => t('Update')];
}

function smPageAdmin(array $me, bool $post): void
{
    $tab = (string)($_GET['tab'] ?? 'users');
    if (!isset(smAdminTabs()[$tab])) $tab = 'users';

    if ($post) {
        $do = (string)($_POST['do'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        $err = '';
        $ok = t('Gespeichert.');
        switch ($do) {
            case 'create':
                $err = smUserCreate((string)($_POST['name'] ?? ''), (string)($_POST['pass'] ?? ''), !empty($_POST['admin']));
                $ok = t('Konto angelegt.');
                break;
            case 'delete':
                $err = smUserDelete($id, $me);
                $ok = t('Konto gelöscht.');
                break;
            case 'pass':
                $err = smUserSetPass($id, (string)($_POST['pass'] ?? ''), $me['hash']);
                $ok = t('Passwort gesetzt.');
                break;
            case 'admin':
                $err = smUserToggleAdmin($id);
                break;
            case 'config':
                $err = smSaveConfig();
                break;
            case 'providers':
                $err = smSaveProviders();
                break;
            case 'ptest':
                $pid = (string)($_POST['p'] ?? '');
                if (!isset(smProviders()[$pid]) || trim(smProvGet($pid, 'key')) === '') { $err = t('Unbekannte Aktion.'); break; }
                $err = smProvTest($pid, smProvGet($pid, 'key'));
                if ($err === '') { smProvOk($pid, 0); $ok = t('%s: Der API-Key funktioniert.', smProviders()[$pid]['name']); }
                else smProvFail($pid, $err, 0);
                break;
            case 'punpause':
                $pid = (string)($_POST['p'] ?? '');
                if (isset(smProviders()[$pid])) smProvUnpause($pid);
                $ok = t('Pause aufgehoben.');
                break;
            case 'quota':
                $uid = (int)($_POST['id'] ?? 0);
                if ($uid !== 0 && !smQ('SELECT 1 FROM users WHERE id = ?', [$uid])->fetchColumn()) { $err = t('Dieses Konto gibt es nicht.'); break; }
                $lim = [];
                foreach ((array)($_POST['lim'] ?? []) as $pid => $v) if (is_string($v) && trim($v) !== '') $lim[$pid] = (int)$v;
                smQuotaSave($uid, ['allow' => !empty($_POST['allow_all']) ? null : (array)($_POST['allow'] ?? []), 'lim' => $lim]);
                $ok = t('Kontingent gespeichert.');
                break;
            case 'fair':
                $ok = t('Kontingente gerecht aufgeteilt: %s', smQuotaFair(!empty($_POST['skip_admins']), !empty($_POST['with_anon'])));
                break;
            case 'quota_clear':
                smQ("UPDATE users SET quota = ''");
                smSetSetting('anon_quota', '');
                $ok = t('Alle Kontingente aufgehoben.');
                break;
            case 'export':
                smServeConfigExport(!empty($_POST['with_key']));
                return;
            case 'import':
                [$in, $err] = smReadImport('file', 'suchmaschine-config');
                if ($in !== null) $ok = t('%d Einstellungen wiederhergestellt.', smConfigImport($in));
                break;
            case 'backup':
                $err = smServeBackup((string)($_POST['bpass'] ?? ''), (string)($_POST['bpass2'] ?? ''));
                break;
            case 'restore':
                [$data, $err] = smBackupRead('file', (string)($_POST['bpass'] ?? ''));
                if ($data !== null) $err = smBackupRestore($data);
                if ($err === '') {
                    smCookie('', time() - 3600);
                    smFlash('ok', t('Die Sicherung ist eingespielt. Bitte neu anmelden.'));
                    smRedirect(smUrl(['a' => 'login']));
                }
                break;
            case 'testkey':
                $err = smTestKey(smSetting('api_key'));
                $ok = t('Der API-Key funktioniert.');
                break;
            case 'protect':
                $r = smProtectionCheck(true);
                $err = $r['ok'] === false ? t('Achtung: %s ist von außen abrufbar!', $r['leak']) : '';
                $ok = $r['ok'] === null ? t('Der Server kann sich selbst nicht erreichen, der Test war nicht möglich. Bitte von Hand prüfen.') : t('Alles dicht: data/ und app/ sind von außen gesperrt.');
                break;
            case 'check':
                try {
                    $i = smUpdCheck();
                    $ok = smUpdNewer($i) ? t('Version %s ist verfügbar.', $i['v']) : t('Es ist schon die neueste Version installiert.');
                } catch (Throwable $e) {
                    $err = $e->getMessage();
                }
                break;
            case 'install':
                $err = smUpdInstall();
                $ok = t('Update eingespielt.');
                break;
            case 'rollback':
                $err = smUpdRollback();
                $ok = t('Die vorige Version ist wieder da.');
                break;
            case 'auto':
                smSetSetting('auto_update', empty($_POST['auto_update']) ? '0' : '1');
                break;
            case 'appicon':
                // Vom Browser aus dem Logo gezeichnet (für SVG-Logos, die PHP nicht in ein Bild umwandeln kann)
                $err = smLogoUrl() === '' ? '' : smSaveAppIconPng((string)($_POST['png'] ?? ''));
                $ok = t('App-Symbol aktualisiert.');
                break;
            default:
                $err = t('Unbekannte Aktion.');
        }
        smFlash($err === '' ? 'ok' : 'error', $err === '' ? $ok : $err);
        smRedirect(smUrl(['a' => 'admin', 'tab' => $tab]));
    }

    $vars = ['me' => $me, 'title' => t('Verwaltung'), 'tab' => $tab];
    $vars['protect'] = smProtectionCheck();
    // Logo ohne App-Symbol (z. B. aus Version 1.0.0): bei Pixelbildern gleich hier nachholen, SVG erledigt der Browser
    if ($tab === 'config' && smLogoUrl() !== '' && !is_file(smAppIconFile()) && smSetting('logo_type') !== 'image/svg+xml') {
        smAppIconFromRaster((string)@file_get_contents(SM_DATA_DIR . '/logo.bin'));
    }
    if ($tab === 'usage') $vars['history'] = smUsageHistory(12);
    if ($tab === 'users' || $tab === 'usage') $vars['users'] = smUsers();
    smRender('admin', $vars);
}

/** Einstellungen der Installation speichern. Liefert Fehlertext oder ''. */
function smSaveConfig(): string
{
    $name = trim(mb_substr((string)($_POST['site_name'] ?? ''), 0, 40));
    smSetSetting('site_name', $name);
    $acc = (string)($_POST['accent'] ?? '');
    if (isset(smAccents()[$acc])) smSetSetting('accent', $acc);
    smSetSetting('scroll', ($_POST['scroll'] ?? '') === 'endless' ? 'endless' : 'pages');
    if (!empty($_POST['logo_remove'])) {
        @unlink(SM_DATA_DIR . '/logo.bin');
        smSetSetting('logo_type', '');
        smAppIconClear();
    } elseif (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
        if (($e = smSaveLogo($_FILES['logo']['tmp_name'], (int)$_FILES['logo']['size'])) !== '') return $e;
        // App-Symbol: vom Browser gezeichnet, sonst mit PHP (nur Pixelbilder), sonst bleibt vorerst das Standardsymbol
        smAppIconClear();
        $png = (string)($_POST['logo_png'] ?? '');
        if ($png === '' || smSaveAppIconPng($png) !== '') {
            smAppIconFromRaster((string)@file_get_contents(SM_DATA_DIR . '/logo.bin'));
        }
    } elseif (!empty($_FILES['logo']['error']) && (int)$_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        return t('Das Logo konnte nicht hochgeladen werden (höchstens 1 MB).');
    }
    smSetSetting('per_page', (string)max(5, min(20, (int)($_POST['per_page'] ?? 10))));
    $c = (string)($_POST['country'] ?? '');
    if (isset(smCountries()[$c])) smSetSetting('country', $c);
    $l = (string)($_POST['search_lang'] ?? '');
    if (isset(smSearchLangs()[$l])) smSetSetting('search_lang', $l);
    $s = (string)($_POST['safesearch'] ?? '');
    if (in_array($s, ['off', 'moderate', 'strict'], true)) smSetSetting('safesearch', $s);
    smSetSetting('public', empty($_POST['public']) ? '0' : '1');
    smSetSetting('img_proxy', empty($_POST['img_proxy']) ? '0' : '1');
    smSetSetting('shortener', empty($_POST['shortener']) ? '0' : '1');
    smSetSetting('public_hour', (string)max(1, min(1000, (int)($_POST['public_hour'] ?? 20))));
    $w = (string)($_POST['weather_src'] ?? '');
    if (isset(smWeatherSources()[$w])) smSetSetting('weather_src', $w);
    return '';
}

/** Suchdienste speichern: Keys (werden vorher getestet), Stichtag, Limit, Kosten, Zwischenspeicher, Reihenfolge, Ausweichziel */
function smSaveProviders(): string
{
    $err = '';
    foreach (smProviders() as $id => $p) {
        if (!empty($_POST['keydel'][$id])) {
            smProvSet($id, 'key', '');
        } else {
            $key = trim((string)($_POST['key'][$id] ?? ''));
            if ($key !== '') {
                if (!preg_match('/^[\x21-\x7e]{8,300}$/', $key)) { $err = t('%s: Der API-Key sieht nicht richtig aus.', $p['name']); continue; }
                if (($e = smProvTest($id, $key)) !== '') { $err = t('%s: %s Der Key wurde nicht gespeichert.', $p['name'], $e); continue; }
                smProvSet($id, 'key', $key);
                smProvUnpause($id);
                smProvOk($id, 0);
            }
        }
        if (!isset($_POST['limit'][$id])) continue;   // Dienst war nicht im Formular (noch kein Key)
        $start = (string)($_POST['start'][$id] ?? '');
        smProvSet($id, 'start', preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && checkdate((int)substr($start, 5, 2), (int)substr($start, 8, 2), (int)substr($start, 0, 4)) ? $start : '');
        smProvSet($id, 'limit', (string)max(0, min(10000000, (int)$_POST['limit'][$id])));
        $cost = (float)str_replace(',', '.', (string)($_POST['cost'][$id] ?? $p['cost']));
        smProvSet($id, 'cost', (string)max(0.01, min(1000, $cost)));
        $cache = (int)($_POST['cache'][$id] ?? 0);
        smProvSet($id, 'cache', (string)($id === 'brave' ? max(0, min(60, $cache)) : max(0, min(168, $cache))));
        smProvSet($id, 'on', empty($_POST['on'][$id]) ? '0' : '1');
    }
    // Reihenfolge: kleinere Zahl = zuerst
    $prio = [];
    foreach (smProvOrder(false) as $i => $id) $prio[$id] = [(int)($_POST['prio'][$id] ?? ($i + 1)), $i];
    uasort($prio, function ($a, $b) { return $a <=> $b; });
    smSetSetting('prov_order', implode(',', array_keys($prio)));
    smSetSetting('balance', empty($_POST['balance']) ? '0' : '1');
    smSetSetting('answers', empty($_POST['answers']) ? '0' : '1');
    smSetSetting('wiki', empty($_POST['wiki']) ? '0' : '1');
    $multi = array_values(array_intersect(array_keys(smProviders()), (array)($_POST['multi'] ?? [])));
    smSetSetting('multi_set', implode(',', $multi));
    smSetSetting('fallback_auto', empty($_POST['fallback_auto']) ? '0' : '1');
    $fb = (string)($_POST['fallback_preset'] ?? '');
    if ($fb === 'own') $fb = (string)($_POST['fallback_own'] ?? '');
    if ($fb === '' || (smCleanBangUrl($fb) !== '' && strpos($fb, '{q}') !== false)) smSetSetting('fallback_url', $fb);
    elseif ($err === '') $err = t('Das Ausweichziel muss mit https:// beginnen und {q} für den Suchbegriff enthalten.');
    smSetSetting('warn_pct', (string)max(10, min(100, (int)($_POST['warn_pct'] ?? 80))));
    return $err;
}

/** Credits der Dienste gleichmäßig auf die Konten verteilen. Liefert eine kurze Zusammenfassung. */
function smQuotaFair(bool $skipAdmins, bool $withAnon): string
{
    $users = array_filter(smUsers(), function ($u) use ($skipAdmins) { return !$skipAdmins || (int)$u['is_admin'] !== 1; });
    $n = count($users) + ($withAnon ? 1 : 0);
    if ($n === 0) return '–';
    $parts = [];
    $per = [];
    foreach (smProvOrder() as $id) {
        $limit = (int)smProvGet($id, 'limit');
        if ($limit <= 0) continue;
        $per[$id] = intdiv($limit, $n);
        $parts[] = smProviders()[$id]['badge'] . ' ' . $per[$id];
    }
    $targets = array_map(function ($u) { return (int)$u['id']; }, $users);
    if ($withAnon) $targets[] = 0;
    foreach ($targets as $uid) {
        $q = smQuotaOf($uid);
        $q['lim'] = $per + $q['lim'];
        foreach ($per as $id => $v) $q['lim'][$id] = $v;
        smQuotaSave($uid, $q);
    }
    return implode(', ', $parts) . ' ' . t('pro Konto');
}

/** Hochgeladenes Logo prüfen und speichern. Liefert Fehlertext oder ''. */
function smSaveLogo(string $tmp, int $size): string
{
    if ($size <= 0 || $size > 1048576) return t('Das Logo darf höchstens 1 MB groß sein.');
    $bin = (string)file_get_contents($tmp);
    $type = '';
    $info = @getimagesizefromstring($bin);
    if (is_array($info) && in_array($info['mime'] ?? '', ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) {
        $type = $info['mime'];
    } elseif (preg_match('/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*(<!DOCTYPE[^>]*>\s*)?<svg[\s>]/is', $bin)) {
        // SVG: nichts Ausführbares zulassen
        if (preg_match('/<script|<foreignObject|\son[a-z]+\s*=|javascript:/i', $bin)) return t('Dieses SVG enthält Skripte und wird darum nicht angenommen.');
        $type = 'image/svg+xml';
    }
    if ($type === '') return t('Bitte ein Bild im Format PNG, JPG, WebP, GIF oder SVG hochladen.');
    if (@file_put_contents(SM_DATA_DIR . '/logo.bin', $bin, LOCK_EX) === false) return t('In den Ordner data/ kann nicht geschrieben werden. Bitte die Schreibrechte prüfen.');
    smSetSetting('logo_type', $type);
    smSetSetting('logo_v', (string)time());
    return '';
}

/* ---------- App-Symbol für den Startbildschirm (PNG, 512 × 512, daraus kleinere Größen) ---------- */

const SM_ICON_SIZES = [180, 192, 512];

function smAppIconFile(): string
{
    return SM_DATA_DIR . '/appicon.png';
}

/** Gibt es ein App-Symbol aus dem eigenen Logo? */
function smAppIconCustom(): bool
{
    return smLogoUrl() !== '' && is_file(smAppIconFile());
}

/** Adresse des App-Symbols. Ändert sich mit dem Logo, damit Geräte das neue holen. */
function smAppIconUrl(int $size): string
{
    return smUrl(['a' => 'appicon', 's' => $size, 'v' => smAppIconCustom() ? smSetting('appicon_v') : SM_VERSION]);
}

function smAppIconClear(): void
{
    foreach (array_merge([smAppIconFile()], (array)glob(SM_DATA_DIR . '/appicon-*.png')) as $f) {
        if (is_file($f)) @unlink($f);
    }
}

function smWriteAppIcon(string $png): bool
{
    smAppIconClear();
    if (@file_put_contents(smAppIconFile(), $png, LOCK_EX) === false) return false;
    smSetSetting('appicon_v', (string)time());
    return true;
}

/** PNG (512 × 512) speichern, das der Browser aus dem Logo gezeichnet hat. Liefert Fehlertext oder ''. */
function smSaveAppIconPng(string $data): string
{
    $bin = base64_decode((string)preg_replace('~^data:image/png;base64,~', '', trim($data)), true);
    $i = $bin === false || strlen($bin) > 4000000 ? false : @getimagesizefromstring($bin);
    if (!is_array($i) || ($i['mime'] ?? '') !== 'image/png' || $i[0] !== 512 || $i[1] !== 512) return t('Das App-Symbol ist ungültig.');
    return smWriteAppIcon($bin) ? '' : t('In den Ordner data/ kann nicht geschrieben werden. Bitte die Schreibrechte prüfen.');
}

/** App-Symbol mit PHP (GD) aus einem Pixelbild bauen: weißer Grund, Logo mittig. false = ging nicht. */
function smAppIconFromRaster(string $bin): bool
{
    if ($bin === '' || !function_exists('imagecreatefromstring')) return false;
    $src = @imagecreatefromstring($bin);
    if (!$src) return false;
    $w = imagesx($src);
    $h = imagesy($src);
    $dst = imagecreatetruecolor(512, 512);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagealphablending($dst, true);
    $scale = min(410 / max(1, $w), 410 / max(1, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    imagecopyresampled($dst, $src, (int)((512 - $nw) / 2), (int)((512 - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
    ob_start();
    imagepng($dst, null, 9);
    $png = (string)ob_get_clean();
    return $png !== '' && smWriteAppIcon($png);
}

/** App-Symbol ausliefern: eigenes (verkleinert, falls GD da ist) oder das eingebaute */
function smServeAppIcon(): void
{
    $s = (int)($_GET['s'] ?? 192);
    if (!in_array($s, SM_ICON_SIZES, true)) $s = 192;
    $file = SM_APP . '/assets/icon-' . $s . '.png';
    if (smHasUsersSafe() && smAppIconCustom()) {
        $file = smAppIconFile();
        if ($s !== 512 && function_exists('imagecreatefromstring')) {
            $small = SM_DATA_DIR . '/appicon-' . $s . '.png';
            if (!is_file($small) && ($src = @imagecreatefromstring((string)file_get_contents($file)))) {
                $dst = imagecreatetruecolor($s, $s);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $s, $s, imagesx($src), imagesy($src));
                @imagepng($dst, $small, 9);
            }
            if (is_file($small)) $file = $small;
        }
    }
    smHeaders();
    header('Content-Type: image/png');
    header('Cache-Control: ' . (isset($_GET['v']) ? 'public, max-age=31536000, immutable' : 'no-cache'));
    readfile($file);
    exit;
}
