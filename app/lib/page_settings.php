<?php
/* Suchmaschine – Seite Einstellungen: jedes Konto hat seinen eigenen Bereich */
defined('SM_VERSION') || exit;

function smSettingsTabs(): array
{
    return ['search' => t('Suche'), 'look' => t('Aussehen'), 'domains' => t('Domains'), 'bangs' => t('Bangs'), 'lenses' => t('Linsen'), 'browser' => t('Browser'), 'account' => t('Konto')];
}

function smPageSettings(array $me, bool $post): void
{
    $tab = (string)($_GET['tab'] ?? 'search');
    if (!isset(smSettingsTabs()[$tab])) $tab = 'search';
    // Aussehen und Bangs: Administratoren bearbeiten hier auch den Standard für alle (?for=all)
    if ($tab === 'look' && ($_GET['default'] ?? '') === '1' && $me['is_admin'] && !$post) smRedirect(smUrl(['a' => 'settings', 'tab' => 'look', 'for' => 'all']));
    $for = ($_GET['for'] ?? '') === 'all' && $me['is_admin'] && in_array($tab, ['look', 'bangs'], true) ? 'all' : '';
    $back = ['for' => $for !== '' ? $for : null];
    if ($tab === 'bangs') foreach (['bq', 'bf', 'bl', 'bp'] as $k) if (isset($_GET[$k]) && is_string($_GET[$k]) && $_GET[$k] !== '') $back[$k] = mb_substr($_GET[$k], 0, 60);

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
                $p = smSortPrefsFromPost() + $p;
                unset($p['race']);
                $p['x_suggest'] = !empty($_POST['x_suggest']) ? 'on' : 'off';
                $p['x_spell'] = !empty($_POST['x_spell']) ? 'on' : 'off';
                $p['x_answer'] = in_array($_POST['x_answer'] ?? '', ['off', 'button', 'auto'], true) ? $_POST['x_answer'] : 'button';
                [$fbu, $fberr] = smFallbackFromPost();
                if ($fberr !== '') $err = $fberr;
                elseif ($fbu === '') unset($p['fallback']);
                else $p['fallback'] = $fbu;
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
            case 'look':
                // Der Knopf oben (hell/dunkel) soll die neue Wahl nicht überdecken
                setcookie('sm_theme', '', ['expires' => time() - 3600, 'path' => smBase()]);
                if (smBase() !== '/') setcookie('sm_theme', '', ['expires' => time() - 3600, 'path' => '/']);   // Cookie aus 1.4 und früher
                $scope = (string)($_POST['scope'] ?? '');
                if (in_array($scope, ['default', 'force'], true) && $me['is_admin']) {
                    smSetSetting('look', json_encode(smLookFromPost()));
                    // Wer den Standard festlegt, sieht ihn auch selbst
                    unset($p['look']);
                    $ok = t('Aussehen als Standard für alle gespeichert. Konten mit eigenem Aussehen behalten ihres.');
                    if ($scope === 'force') {
                        $n = smLookResetAll();
                        $ok = t('Aussehen für alle gespeichert und bei %d Konten das eigene Aussehen zurückgesetzt.', $n);
                    }
                } elseif (!empty($_POST['reset'])) {
                    unset($p['look']);
                    $ok = t('Aussehen auf den Standard zurückgesetzt.');
                } else {
                    $p['look'] = smLookFromPost();
                }
                break;
            case 'domains':
                foreach (['block', 'raise', 'lower'] as $k) $p[$k] = smCleanDomains((string)($_POST[$k] ?? ''));
                // Eine Domain steht nur in einer Liste: Blockieren geht vor Hochstufen geht vor Herabstufen
                $p['raise'] = array_values(array_diff($p['raise'], $p['block']));
                $p['lower'] = array_values(array_diff($p['lower'], $p['block'], $p['raise']));
                break;
            case 'bang_save':
            case 'bang_del':
            case 'bang_bulkdel':
            case 'bang_dellang':
            case 'bang_revert':
            case 'bang_unhide':
            case 'bang_restore':
            case 'bang_undo':
                $scope = $for === 'all' ? 'all' : 'mine';
                $layer = $for === 'all' ? smBangGlobal() : ['own' => $p['bangs'] ?? [], 'off' => $p['bang_off'] ?? []];
                if ($do === 'bang_undo') {
                    $u = smBangUndoGet($me, $scope);
                    if (!$u) { $err = t('Da gibt es nichts mehr rückgängig zu machen.'); break; }
                    $l = smBangUndoApply($layer, $u['diff']);
                    $ok = t('Rückgängig gemacht.');
                    smBangUndoSet($me, $scope, null);
                } else {
                    if ($for === 'all') {
                        // Für alle: ändert die Schicht des Administrators über der großen Liste
                        $lower = function (string $t): bool { return smBangSys($t) !== null; };
                    } else {
                        $G = smBangLayers(null, 'all');
                        $lower = function (string $t) use ($G): bool { return isset($G['gown'][$t]) || (!isset($G['goff'][$t]) && smBangSys($t) !== null); };
                    }
                    $in = $_POST;
                    if ($do === 'bang_dellang') {
                        // Alle Bangs einer Sprachgruppe auf einmal löschen (z. B. alle anderssprachigen)
                        $lg = (string)($_POST['bl'] ?? 'xx');
                        $in['ts'] = smBangAllTriggers($me, $scope, isset(smBangLangs()[$lg]) && $lg !== '' ? $lg : 'xx');
                        $do2 = 'bang_bulkdel';
                    } else {
                        $do2 = $do;
                    }
                    [$l, $err, $ok] = smBangEdit($layer, $do2, $in, $lower);
                    if ($err === '') smBangUndoSet($me, $scope, smBangDiff($layer, $l), $ok);
                }
                if ($err !== '') break;
                if ($for === 'all') {
                    smBangSetGlobal($l);
                } else {
                    $p['bangs'] = smCleanBangs($l['own']);
                    $p['bang_off'] = smCleanBangOff($l['off']);
                }
                break;
            case 'bang_cfg':
            case 'bang_refresh':
            case 'bang_checknow':
                if ($for !== 'all') { $err = t('Unbekannte Aktion.'); break; }
                if ($do === 'bang_cfg') {
                    smSetSetting('bang_auto', !empty($_POST['bang_auto']) ? '1' : '0');
                    smSetSetting('bang_check', !empty($_POST['bang_check']) ? '1' : '0');
                    smSetSetting('bang_ref', !empty($_POST['bang_ref']) ? '1' : '0');
                    smSetSetting('bang_autodel', !empty($_POST['bang_autodel']) ? '1' : '0');
                    if (($n = smBangAutoSweep()) > 0) $ok = t('Gespeichert. %d Bangs nicht erreichbarer Seiten wurden für alle gelöscht.', $n);
                } elseif ($do === 'bang_refresh') {
                    @set_time_limit(120);
                    $err = smBangRefresh();
                    $u = json_decode(smSetting('bang_upd'), true);
                    $ok = t('Bang-Liste aktualisiert: %d Bangs, %d neu, %d weggefallen.', (int)($u['n'] ?? 0), (int)($u['add'] ?? 0), (int)($u['del'] ?? 0));
                } else {
                    @set_time_limit(120);
                    [$n, $bad] = smBangCheck(60);
                    $ok = t('%d Domains geprüft, davon %d gerade nicht erreichbar.', $n, $bad);
                    if (($m = smBangAutoSweep()) > 0) $ok .= ' ' . t('%d Bangs automatisch gelöscht.', $m);
                }
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
        smRedirect(smUrl(['a' => 'settings', 'tab' => $tab !== 'search' ? $tab : null] + $back));
    }
    // Bangs: nur die Trefferliste (für die Suche oben, ohne die Seite neu zu laden)
    if ($tab === 'bangs' && isset($_GET['frag'])) {
        smRender('_banglist', ['me' => $me, 'for' => $for], false);
        return;
    }
    $edit = null;
    if ($tab === 'lenses' && isset($_GET['edit'])) {
        foreach (smCleanLenses($me['prefs']['lenses'] ?? []) as $l) if ($l['id'] === $_GET['edit']) $edit = $l;
    }
    $token = $tab === 'browser' ? smTokenGet($me['id']) : '';
    smRender('settings', ['me' => $me, 'prefs' => smPrefs($me), 'title' => t('Einstellungen'), 'tab' => $tab, 'edit' => $edit,
        'token' => $token, 'osToken' => $token, 'for' => $for]);
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
