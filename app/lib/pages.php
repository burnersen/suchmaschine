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
    if (!$me && !($public && in_array($a, ['', 'lucky'], true))) {
        smRedirect(smUrl(['a' => 'login', 'next' => smSelf() !== smBase() ? smSelf() : null]));
    }

    switch ($a) {
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
    $types = ['app.css' => 'text/css; charset=utf-8', 'app.js' => 'text/javascript; charset=utf-8', 'icon.svg' => 'image/svg+xml'];
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
    $name = smHasUsersSafe() ? smSetting('site_name') : 'Suche';
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
    $name = smHasUsersSafe() ? smSetting('site_name') : 'Suche';
    echo json_encode([
        'name' => $name, 'short_name' => mb_substr($name, 0, 12), 'start_url' => './', 'scope' => './', 'display' => 'standalone',
        'lang' => smLang(), 'background_color' => '#ffffff', 'theme_color' => '#ffffff',
        'icons' => [['src' => '?a=asset&f=icon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any']],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
        smRender('home', ['me' => $me, 'prefs' => $prefs, 'title' => smSetting('site_name'), 'home' => true, 'tab' => $tab]);
        return;
    }

    $page = max(1, min(10, (int)($_GET['p'] ?? 1)));
    $fresh = (string)($_GET['f'] ?? '');
    if (!isset(smFreshness()[$fresh])) $fresh = '';
    $exact = ($_GET['exact'] ?? '') === '1';

    $res = null;
    $error = '';
    try {
        if (!$me && !smPublicQuota()) throw new SmSearchError(t('Für diese Stunde sind genug Suchen ohne Anmeldung gelaufen. Bitte später wieder, oder anmelden.'));
        $res = smSearch($tab, $q, $prefs + ['page' => $page, 'freshness' => $fresh, 'exact' => $exact]);
    } catch (SmSearchError $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Suchmaschine: ' . $e);
        $error = t('Da ist intern etwas schiefgelaufen. Die Einzelheiten stehen im Fehlerprotokoll des Servers.');
    }

    smRender('results', [
        'me' => $me, 'prefs' => $prefs, 'title' => $q . ' – ' . smSetting('site_name'),
        'q' => $q, 'tab' => $tab, 'page' => $page, 'fresh' => $fresh, 'exact' => $exact, 'res' => $res, 'error' => $error,
    ]);
}

/** „Auf gut Glück“: direkt zum ersten Treffer */
function smPageLucky(?array $me): void
{
    $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 400));
    if ($q === '') smRedirect(smBase());
    try {
        if ($me || smPublicQuota()) {
            $res = smSearch('web', $q, smPrefs($me));
            if (!empty($res['results'][0]['url'])) smRedirect($res['results'][0]['url']);
        }
    } catch (Throwable $e) {
    }
    smRedirect(smUrl(['q' => $q]));
}

/* ---------- Eigene Einstellungen ---------- */

function smPageSettings(array $me, bool $post): void
{
    if ($post) {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'prefs') {
            $p = $me['prefs'];
            $c = (string)($_POST['country'] ?? '');
            $l = (string)($_POST['search_lang'] ?? '');
            $s = (string)($_POST['safesearch'] ?? '');
            $p['country'] = isset(smCountries()[$c]) ? $c : smSetting('country');
            $p['search_lang'] = isset(smSearchLangs()[$l]) ? $l : smSetting('search_lang');
            $p['safesearch'] = in_array($s, ['off', 'moderate', 'strict'], true) ? $s : smSetting('safesearch');
            $p['newtab'] = !empty($_POST['newtab']);
            $p['history'] = !empty($_POST['history']);
            smUserSavePrefs($me['id'], $p);
            smFlash('ok', t('Gespeichert.'));
        } elseif ($do === 'pass') {
            $row = smQ('SELECT pass FROM users WHERE id = ?', [$me['id']])->fetch();
            $new = (string)($_POST['pass'] ?? '');
            if (!$row || !password_verify((string)($_POST['old'] ?? ''), $row['pass'])) {
                smFlash('error', t('Das bisherige Passwort stimmt nicht.'));
            } elseif ($new !== (string)($_POST['pass2'] ?? '')) {
                smFlash('error', t('Die beiden Passwörter stimmen nicht überein.'));
            } elseif (($e = smUserSetPass($me['id'], $new, $me['hash'])) !== '') {
                smFlash('error', $e);
            } else {
                smFlash('ok', t('Passwort geändert. Andere Geräte sind jetzt abgemeldet.'));
            }
        }
        smRedirect(smUrl(['a' => 'settings']));
    }
    smRender('settings', ['me' => $me, 'prefs' => smPrefs($me), 'title' => t('Einstellungen')]);
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
            default:
                $err = t('Unbekannte Aktion.');
        }
        smFlash($err === '' ? 'ok' : 'error', $err === '' ? $ok : $err);
        smRedirect(smUrl(['a' => 'admin', 'tab' => $tab]));
    }

    $vars = ['me' => $me, 'title' => t('Verwaltung'), 'tab' => $tab];
    $vars['protect'] = smProtectionCheck();
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
    smSetSetting('site_name', $name !== '' ? $name : 'Suche');
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
    smSetSetting('public_hour', (string)max(1, min(1000, (int)($_POST['public_hour'] ?? 20))));
    return '';
}
