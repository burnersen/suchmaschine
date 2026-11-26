<?php
/* Suchmaschine – Ablauf: welche Seite, welche Aktion. Die Seiten selbst stehen in page_*.php */
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
    if ($a === 'look') smServeLook();
    if ($a === 'font') smServeFont();
    if ($a === 'myip') smServeMyIp();
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
    // Text-Schnipsel: …/?n=<id>#Schlüssel zeigt die Abholseite, der Inhalt kommt per POST an ?a=noteget (beides ohne Anmeldung)
    if ($a === '' && isset($_GET['n']) && is_string($_GET['n'])) smServeNote($_GET['n']);
    if ($a === 'noteget') smServeNoteGet();

    // Hintergrundarbeit für die Bangs (Liste aktualisieren, Erreichbarkeit prüfen), angestoßen per JavaScript, ohne Anmeldung
    if ($a === 'bangtick') smServeBangTick();

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
    if (!$me && !($public && in_array($a, ['', 'lucky', 'help', 'img', 'qr', 'tools', 'bangs'], true))) {
        if ($a === 'img' || $a === 'qr') {
            http_response_code(403);
            exit;
        }
        smRedirect(smUrl(['a' => 'login', 'next' => smSelf() !== smBase() ? smSelf() : null]));
    }

    // Aus dem Teilen-Menü des Handys: geteilte Adresse kürzen (oder geteilten Text suchen)
    if ($a === '' && (isset($_GET['share_url']) || isset($_GET['share_text']))) {
        $shared = trim((string)($_GET['share_url'] ?? '') !== '' ? (string)$_GET['share_url'] : (string)($_GET['share_text'] ?? ''));
        if (preg_match('~https?://\S+~', $shared, $sm)) smRedirect(smToolUrl('kurzlinks', ['url' => $sm[0]]));
        smRedirect(smUrl(['q' => mb_substr($shared, 0, 400)]));
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
        case 'bangs':
            smServeBangSuggest($me);
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
        case 'read':
            smPageRead($me, $post);
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
