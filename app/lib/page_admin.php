<?php
/* Suchmaschine – Seite Verwaltung (nur Administratoren): Konten, Suchdienste, Verbrauch, Einstellungen, Sicherung, Update */
defined('SM_VERSION') || exit;

function smAdminTabs(): array
{
    return ['users' => t('Konten'), 'providers' => t('Suchdienste'), 'usage' => t('Verbrauch'), 'config' => t('Einstellungen'),
            'backup' => t('Sicherung'), 'update' => t('Update')];
}

function smPageAdmin(array $me, bool $post): void
{
    $tab = (string)($_GET['tab'] ?? 'users');
    // Aussehen für alle gibt es seit 2.1 nur noch an einer Stelle: Einstellungen → Aussehen → Standard für alle
    if ($tab === 'look' && !$post) smRedirect(smUrl(['a' => 'settings', 'tab' => 'look', 'for' => 'all']));
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
            case 'plans':
                // Verwaltung → Verbrauch: Requests laut Dashboard für alle Pläne
                $err = smSavePlans();
                break;
            case 'quota':
                $uid = (int)($_POST['id'] ?? 0);
                if (!smQ('SELECT 1 FROM users WHERE id = ?', [$uid])->fetchColumn()) { $err = t('Dieses Konto gibt es nicht.'); break; }
                $lim = [];
                foreach ((array)($_POST['lim'] ?? []) as $pid => $v) if (is_string($v) && trim($v) !== '') $lim[$pid] = (int)$v;
                smQuotaSave($uid, ['allow' => !empty($_POST['allow_all']) ? null : (array)($_POST['allow'] ?? []), 'lim' => $lim]);
                $ok = t('Kontingent gespeichert.');
                break;
            case 'fair':
                $ok = t('Kontingente gerecht aufgeteilt: %s', smQuotaFair(!empty($_POST['skip_admins'])));
                break;
            case 'quota_clear':
                smQ("UPDATE users SET quota = ''");
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
    // Guthaben und Reset-Datum bei den Diensten abgleichen, die das können (höchstens alle 6 Stunden)
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
    foreach (smSortPrefsFromPost() as $k => $v) smSetSetting($k, is_bool($v) ? ($v ? '1' : '0') : $v);
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
    smSetSetting('public', ($_POST['public'] ?? '') === '1' ? '1' : '0');
    smSetSetting('img_proxy', empty($_POST['img_proxy']) ? '0' : '1');
    smSetSetting('shortener', empty($_POST['shortener']) ? '0' : '1');
    $v4 = trim((string)($_POST['ipv4_host'] ?? ''));
    if ($v4 !== '' && smCleanHostname($v4) === '') return t('Die IPv4-Subdomain ist kein gültiger Name (z. B. v4.example.org, ohne https:// und ohne Pfad).');
    smSetSetting('ipv4_host', smCleanHostname($v4));
    $w = (string)($_POST['weather_src'] ?? '');
    if (isset(smWeatherSources()[$w])) smSetSetting('weather_src', $w);
    return '';
}

/**
 * Zahl aus einem Eingabefeld: 1.234,5 oder 1,234.5 oder 1234,5 oder 0,62. Das letzte Komma oder der letzte Punkt ist das
 * Dezimalzeichen, außer bei reinen Tausendergruppen (1.234). Liefert null, wenn es keine Zahl ist.
 */
function smParseAmount(string $v): ?float
{
    $raw = str_replace([' ', "\u{a0}", '$', '€'], '', trim($v));
    if (preg_match('/^\d{1,3}([.,]\d{3})+$/', $raw)) $raw = str_replace(['.', ','], '', $raw);
    elseif (strrpos($raw, ',') !== false && strrpos($raw, ',') > (int)strrpos($raw, '.')) $raw = str_replace(['.', ','], ['', '.'], $raw);
    else $raw = str_replace(',', '', $raw);
    return is_numeric($raw) ? (float)$raw : null;
}

/** Verwaltung → Suchdienste speichern: die vier Brave-Pläne, Ausweich-Suchmaschine, Warnschwelle */
function smSaveProviders(): string
{
    $err = smSavePlans();
    [$fb, $fberr] = smFallbackFromPost();
    if ($fberr !== '') { if ($err === '') $err = $fberr; }
    else smSetSetting('fallback_url', $fb !== '' ? $fb : smFallbackDefault());
    smSetSetting('warn_pct', (string)max(10, min(100, (int)($_POST['warn_pct'] ?? 80))));
    return $err;
}

/** Credits der Dienste gleichmäßig auf die Konten verteilen. Liefert eine kurze Zusammenfassung. */
function smQuotaFair(bool $skipAdmins): string
{
    $users = array_filter(smUsers(), function ($u) use ($skipAdmins) { return !$skipAdmins || (int)$u['is_admin'] !== 1; });
    $n = count($users);
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
    foreach ($targets as $uid) {
        $q = smQuotaOf($uid);
        $q['lim'] = $per + $q['lim'];
        foreach ($per as $id => $v) $q['lim'][$id] = $v;
        smQuotaSave($uid, $q);
    }
    return implode(', ', $parts) . ' ' . t('pro Konto');
}
