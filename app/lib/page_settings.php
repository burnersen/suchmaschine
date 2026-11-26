<?php
/* Suchmaschine – Seite Einstellungen: jedes Konto hat seinen eigenen Bereich */
defined('SM_VERSION') || exit;

function smSettingsTabs(): array
{
    return ['search' => t('Suche'), 'look' => t('Aussehen'), 'domains' => t('Domains'), 'bangs' => t('Bangs'), 'lenses' => t('Linsen'), 'account' => t('Konto')];
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
                $p = smSortPrefsFromPost() + $p;
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
                if (($_POST['scope'] ?? '') === 'default' && $me['is_admin']) {
                    smSetSetting('look', json_encode(smLookFromPost()));
                    $ok = t('Aussehen als Standard für alle gespeichert.');
                    if (!empty($_POST['default_only'])) break;
                    unset($p['look']);   // Wer den Standard festlegt, sieht ihn auch selbst
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
