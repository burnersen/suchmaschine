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
    if ($a === 'sw') smServeSw();
    if ($a === 'offline') smServeOffline();
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
            // Der Brave-Key ist freiwillig: ohne Key geht jede Suche zur Ausweich-Suchmaschine, der Key lässt sich später eintragen
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
    // Druck-Stylesheet der QR-Werkzeuge (Papierformat per @page, eingebettete Stile verbietet die CSP)
    if ($a === 'printcss') smServePrintCss();
    // Lesezeichen „Lesen & Drucken“: Seite aus dem Browser hochladen (kommt von der fremden Seite, darum ohne Formular-Prüfung,
    // erlaubt nur mit dem persönlichen Schlüssel des Kontos)
    if ($a === 'readin' && $post) smServeReadIn();
    // Lesezeichen „Bilder groß“, Knopf „An meine App schicken“: Bildadressen als Sammlung ins Konto (ebenfalls nur mit dem persönlichen Schlüssel)
    if ($a === 'picsin' && $post) smServePicsIn();

    $me = smUser();

    // Login per geheimem Token: …/?a=login#token=… (der Teil nach # erreicht nie den Server und landet so in keinem Protokoll,
    // ein kleines Skript schickt ihn per POST an ?a=tokenlogin) oder wie bisher …/?token=… (auch zusammen mit &q=… aus der Adressleiste).
    // Danach sofort weiter auf die Adresse ohne Token, damit er nicht in Verlauf oder Lesezeichen hängen bleibt.
    $tokPost = $a === 'tokenlogin' && $post && is_string($_POST['token'] ?? null);
    if ($tokPost || (isset($_GET['token']) && is_string($_GET['token']) && !$post)) {
        $clean = $_GET;
        unset($clean['token'], $clean['a']);
        $to = smBase() . ($clean ? '?' . http_build_query($clean) : '');
        // Nur direkt aufgerufen (Adressleiste, Lesezeichen, Browser-Suche), nicht über einen Link einer fremden Seite.
        // Sonst könnte eine fremde Seite dich unbemerkt in ein anderes Konto einloggen (Login-CSRF).
        $site = (string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
        if ($tokPost ? !smSameOrigin() : ($site === 'cross-site' || $site === 'same-site')) {
            smFlash('error', t('Aus Sicherheitsgründen funktioniert die Token-Adresse nur direkt aus der Adressleiste, als Lesezeichen oder als Browser-Suche, nicht über einen Link auf einer anderen Seite.'));
            smRedirect($to);
        }
        [$uid, $terr] = smTokenLogin(trim($tokPost ? $_POST['token'] : $_GET['token']));
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

    // Gäste: Startseite, Bangs, Sofort-Antworten, Werkzeuge und Hilfe. Suchen gehen bei ihnen immer zur Ausweich-Suchmaschine.
    // Mit „Nur mit Anmeldung“ (Verwaltung → Einstellungen) sehen Gäste nur die Anmeldung.
    $public = smSetting('public') === '1';
    if (!$me && !($public && in_array($a, ['', 'lucky', 'help', 'qr', 'tools', 'bangs', 'fb', 'recipes'], true))) {
        if ($a === 'qr') {
            http_response_code(403);
            exit;
        }
        smRedirect(smUrl(['a' => 'login', 'next' => smSelf() !== smBase() ? smSelf() : null]));
    }

    // Aus dem Teilen-Menü des Handys: geteilte Adresse kürzen (oder geteilten Text suchen)
    if ($a === '' && (isset($_GET['share_url']) || isset($_GET['share_text']))) {
        $shared = trim((string)($_GET['share_url'] ?? '') !== '' ? (string)$_GET['share_url'] : (string)($_GET['share_text'] ?? ''));
        // Geteilte Adresse: fragen, was damit passieren soll (lesen und drucken, Kurzlink, Seite öffnen)
        if (preg_match('~https?://\S+~', $shared, $sm) && ($su = smLink($sm[0])) !== '') {
            smRender('share', ['me' => $me, 'prefs' => smPrefs($me), 'u' => $su, 'title' => t('Geteilter Link') . ' – ' . smSiteName()]);
            return;
        }
        smRedirect(smUrl(['q' => mb_substr($shared, 0, 400)]));
    }

    switch ($a) {
        case 'img':
            smServeImgProxy();
            return;
        case 'suggest':
            smServeSuggest($me);
            return;
        case 'recipes':
            smRender('recipes', ['me' => $me, 'prefs' => smPrefs($me), 'title' => t('Rezepte') . ' – ' . smSiteName()]);
            return;
        case 'answer':
            smServeAnswer($me);
            return;
        case 'fb':
            // Gäste wählen ihre Suchmaschine (gemerkt nur im Browser), Konten in den Einstellungen
            if ($post && !$me) {
                $u = (string)($_POST['fallback'] ?? '');
                // Wer den Standard wählt, bekommt kein Cookie: so gilt jede spätere Änderung des Administrators sofort
                if ($u === smFallbackDefault()) $u = '';
                $path = (string)parse_url(smBase(), PHP_URL_PATH);
                setcookie('smfb', smFallbackValid($u, true) ? $u : '', ['expires' => smFallbackValid($u, true) ? time() + 31536000 : time() - 3600,
                    'path' => $path !== '' ? $path : '/', 'samesite' => 'Lax', 'secure' => smIsHttps(), 'httponly' => true]);
                smFlash('ok', t('Gespeichert. Deine Suchen gehen jetzt zu %s.', smFallbackName(null, smFallbackValid($u, true) ? $u : smFallbackDefault())));
            }
            smRedirect($me ? smUrl(['a' => 'settings', 'tab' => 'search']) : smBase());
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
        case 'pics':
            smPagePics($me, $post);
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
