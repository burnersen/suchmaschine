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
            $key = trim((string)($_POST['key'] ?? ''));
            $err = $key === '' ? t('Bitte den Brave API-Key eintragen.') : smTestKey($key);
            if ($err === '') $err = smSetup((string)($_POST['name'] ?? ''), (string)($_POST['pass'] ?? ''), (string)($_POST['pass2'] ?? ''), $key);
            if ($err === '') {
                smFlash('ok', t('Fertig! Die Suchmaschine ist eingerichtet und du bist Administrator.'));
                smRedirect(smBase());
            }
        }
        smRender('setup', ['title' => t('Einrichten'), 'error' => $err, 'bare' => true]);
        return;
    }

    $me = smUser();

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
    if (!$me && !($public && in_array($a, ['', 'lucky', 'help', 'img'], true))) {
        if ($a === 'img') {
            http_response_code(403);
            exit;
        }
        smRedirect(smUrl(['a' => 'login', 'next' => smSelf() !== smBase() ? smSelf() : null]));
    }

    switch ($a) {
        case 'img':
            smServeImgProxy();
            return;
        case 'help':
            smRender('help', ['me' => $me, 'prefs' => smPrefs($me), 'title' => t('Hilfe') . ' – ' . smSiteName()]);
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
    header('Cache-Control: no-cache');
    $name = smSiteName();
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
       . '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">' . "\n"
       . '  <ShortName>' . h(mb_substr($name, 0, 16)) . "</ShortName>\n"
       . '  <Description>' . h(t('Eigene Suchmaschine ohne Werbung')) . "</Description>\n"
       . "  <InputEncoding>UTF-8</InputEncoding>\n"
       . '  <Image type="image/svg+xml">' . h(smOrigin() . '?a=asset&f=icon.svg') . "</Image>\n"
       . '  <Url type="text/html" method="get" template="' . h(smOrigin() . '?q={searchTerms}') . "\"/>\n"
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

function smPageSearch(?array $me): void
{
    $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 400));
    $tab = (string)($_GET['t'] ?? 'web');
    if (!isset(smTabs()[$tab])) $tab = 'web';
    $prefs = smPrefs($me);

    if ($q === '') {
        smRender('home', ['me' => $me, 'prefs' => $prefs, 'title' => smSiteName(), 'home' => true, 'tab' => $tab]);
        return;
    }

    // Bangs: vor jeder Anfrage an Brave, kosten also nichts
    if (($_GET['part'] ?? '') !== '1' && ($bang = smBangTarget($q, $me))) {
        if ($bang['internal']) smRedirect($bang['to']);
        smExternalRedirect($bang['to'], $q);
    }

    $ft = $tab === 'web' ? smCleanFileType($_GET['ft'] ?? '') : '';
    $lenses = smLenses($me);
    $lens = (string)($_GET['l'] ?? '');
    if (!isset($lenses[$lens])) $lens = '';

    $page = max(1, min(10, (int)($_GET['p'] ?? 1)));
    $fresh = (string)($_GET['f'] ?? '');
    if (!isset(smFreshness()[$fresh])) $fresh = '';
    $exact = ($_GET['exact'] ?? '') === '1';

    $res = null;
    $error = '';
    try {
        if (!$me && !smPublicQuota()) throw new SmSearchError(t('Für diese Stunde sind genug Suchen ohne Anmeldung gelaufen. Bitte später wieder, oder anmelden.'));
        $res = smSearch($tab, smApiQuery($q, $ft, $lens !== '' ? $lenses[$lens] : null),
            $prefs + ['page' => $page, 'freshness' => $fresh, 'exact' => $exact, 'endless' => $prefs['scroll'] === 'endless']);
        // Eigene Domain-Listen erst jetzt: der Cache bleibt für alle gleich
        $res = smApplyDomainLists($res, smDomainLists($me));
    } catch (SmSearchError $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Suchmaschine: ' . $e);
        $error = t('Da ist intern etwas schiefgelaufen. Die Einzelheiten stehen im Fehlerprotokoll des Servers.');
    }

    $part = ($_GET['part'] ?? '') === '1';
    if ($part) {
        // Endlos-Scrollen: nur die nächsten Treffer, ohne Seitengerüst
        if ($error !== '') http_response_code(502);
        smRender('part', ['me' => $me, 'prefs' => $prefs, 'q' => $q, 'tab' => $tab, 'page' => $page, 'fresh' => $fresh, 'exact' => $exact,
            'ft' => $ft, 'lens' => $lens, 'lenses' => $lenses, 'res' => $res, 'error' => $error], false);
        return;
    }
    smRender('results', [
        'me' => $me, 'prefs' => $prefs, 'title' => $q . ' – ' . smSiteName(),
        'q' => $q, 'tab' => $tab, 'page' => $page, 'fresh' => $fresh, 'exact' => $exact, 'res' => $res, 'error' => $error,
        'ft' => $ft, 'lens' => $lens, 'lenses' => $lenses,
    ]);
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
            $res = smApplyDomainLists(smSearch('web', $q, smPrefs($me)), smDomainLists($me));
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
                } elseif (isset(smTabBangs()[$t])) {
                    $err = t('Das Kürzel !%s ist für die Reiter reserviert.', $t);
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
        if ($err === '' && $do !== 'pass') smUserSavePrefs($me['id'], $p);
        smFlash($err === '' ? 'ok' : 'error', $err === '' ? $ok : $err);
        smRedirect(smUrl(['a' => 'settings', 'tab' => $tab !== 'search' ? $tab : null]));
    }
    $edit = null;
    if ($tab === 'lenses' && isset($_GET['edit'])) {
        foreach (smCleanLenses($me['prefs']['lenses'] ?? []) as $l) if ($l['id'] === $_GET['edit']) $edit = $l;
    }
    smRender('settings', ['me' => $me, 'prefs' => smPrefs($me), 'title' => t('Einstellungen'), 'tab' => $tab, 'edit' => $edit]);
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
    return ['users' => t('Konten'), 'config' => t('Einstellungen'), 'usage' => t('Verbrauch'), 'update' => t('Update')];
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
            case 'export':
                smServeConfigExport(!empty($_POST['with_key']));
                return;
            case 'import':
                [$in, $err] = smReadImport('file', 'suchmaschine-config');
                if ($in !== null) $ok = t('%d Einstellungen wiederhergestellt.', smConfigImport($in));
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
    if ($tab === 'users') $vars['users'] = smUsers();
    if ($tab === 'usage') $vars['history'] = smUsageHistory(12);
    smRender('admin', $vars);
}

/** Einstellungen der Installation speichern. Liefert Fehlertext oder ''. */
function smSaveConfig(): string
{
    $key = trim((string)($_POST['api_key'] ?? ''));
    if ($key !== '') {
        if (($e = smTestKey($key)) !== '') return $e;
        smSetSetting('api_key', $key);
    }
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
    smSetSetting('monthly_limit', (string)max(0, min(1000000, (int)($_POST['monthly_limit'] ?? 950))));
    smSetSetting('cache_min', (string)max(0, min(60, (int)($_POST['cache_min'] ?? 15))));
    smSetSetting('per_page', (string)max(5, min(20, (int)($_POST['per_page'] ?? 10))));
    $c = (string)($_POST['country'] ?? '');
    if (isset(smCountries()[$c])) smSetSetting('country', $c);
    $l = (string)($_POST['search_lang'] ?? '');
    if (isset(smSearchLangs()[$l])) smSetSetting('search_lang', $l);
    $s = (string)($_POST['safesearch'] ?? '');
    if (in_array($s, ['off', 'moderate', 'strict'], true)) smSetSetting('safesearch', $s);
    smSetSetting('public', empty($_POST['public']) ? '0' : '1');
    smSetSetting('img_proxy', empty($_POST['img_proxy']) ? '0' : '1');
    smSetSetting('public_hour', (string)max(1, min(1000, (int)($_POST['public_hour'] ?? 20))));
    return '';
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
