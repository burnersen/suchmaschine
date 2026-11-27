<?php
/* Suchmaschine – Seite Lesemodus (ohne Werbung lesen) */
defined('SM_VERSION') || exit;

/**
 * ?a=read&u=…: Lesemodus. Nur mit Konto. Nichts wird gespeichert, die Quelle steht immer dabei, Paywalls werden nicht umgangen.
 * Höchstens 60 Seiten pro Stunde und Konto, damit niemand die Suchmaschine zum Abgrasen fremder Seiten benutzt.
 */
function smPageRead(array $me, bool $post): void
{
    $u = trim((string)($_GET['u'] ?? $_POST['u'] ?? ''));
    $u = smLink($u);
    if ($u === '' || strlen($u) > 2000) smRedirect(smBase());
    // Vom Lesezeichen hochgeladene Seite (nur für das Konto, das sie geschickt hat, und nur zu dieser Adresse)
    $h = is_string($_GET['h'] ?? null) && preg_match('/^[a-f0-9]{24}$/', $_GET['h']) ? $_GET['h'] : '';
    $html = null;
    if ($h !== '') {
        $up = smCacheGet('readin|' . $h);
        if ($up && (int)($up['uid'] ?? 0) === (int)$me['id'] && ($up['u'] ?? '') === $u) $html = (string)$up['h'];
        else $h = '';
    }
    $view = ($_GET['v'] ?? '') === 'all' ? 'all' : '';
    $k = hash('sha256', 'read|' . $me['id'] . '|' . date('YmdH'));
    $row = smQ('SELECT fails FROM throttle WHERE k = ?', [$k])->fetch();
    $err = '';
    $r = null;
    if ($row && (int)$row['fails'] >= 60) {
        $err = t('Für diese Stunde sind genug Seiten im Lesemodus geöffnet worden. Bitte später wieder.');
    } else {
        smQ('INSERT INTO throttle (k, fails, last) VALUES (?, 1, ?) ON CONFLICT(k) DO UPDATE SET fails = fails + 1, last = excluded.last', [$k, time()]);
        $r = smReadPage($u, $html);
        if ($html !== null) $r['url'] = $u;
    }
    if ($r && $r['ok'] && $view === 'all' && !empty($r['alt'])) {
        $r['blocks'] = $r['alt'];
        $r['kind'] = 'article';
        $r['was_recipe'] = true;
    }
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    smRender('read', ['me' => $me, 'prefs' => smPrefs($me), 'u' => $u, 'r' => $r, 'error' => $err, 'view' => $view, 'h' => $h,
                      'title' => ($r['title'] ?? '') !== '' ? $r['title'] . ' – ' . t('Lesemodus') : t('Lesemodus')]);
}

/**
 * ?a=readin (POST vom Lesezeichen „Lesen & Drucken“): die Seite, die der Browser schon geladen hat. Erlaubt nur mit dem
 * persönlichen Schlüssel eines Kontos, höchstens 3 MB und 60 Seiten pro Stunde. Liegt 30 Minuten im Zwischenspeicher,
 * dann weiter zum Lesemodus (dort zählt wieder die normale Anmeldung).
 */
function smServeReadIn(): void
{
    $uid = smReadKeyUser(is_string($_POST['k'] ?? null) ? $_POST['k'] : '');
    $u = smLink(is_string($_POST['u'] ?? null) ? trim($_POST['u']) : '');
    $html = is_string($_POST['html'] ?? null) ? $_POST['html'] : '';
    if ($uid === 0 || !smQ('SELECT 1 FROM users WHERE id = ?', [$uid])->fetchColumn() || $u === '' || strlen($u) > 2000) {
        http_response_code(403);
        exit;
    }
    if ($html === '' || strlen($html) > 3200000) smRedirect(smUrl(['a' => 'read', 'u' => $u]));
    $k = hash('sha256', 'readin|' . $uid . '|' . date('YmdH'));
    $row = smQ('SELECT fails FROM throttle WHERE k = ?', [$k])->fetch();
    if ($row && (int)$row['fails'] >= 60) smRedirect(smUrl(['a' => 'read', 'u' => $u]));
    smQ('INSERT INTO throttle (k, fails, last) VALUES (?, 1, ?) ON CONFLICT(k) DO UPDATE SET fails = fails + 1, last = excluded.last', [$k, time()]);
    if (!mb_check_encoding($html, 'UTF-8')) $html = (string)mb_convert_encoding($html, 'UTF-8', 'UTF-8');
    $tok = bin2hex(random_bytes(12));
    smCacheSet('readin|' . $tok, ['uid' => $uid, 'u' => $u, 'h' => $html], 1800);
    smRedirect(smUrl(['a' => 'read', 'u' => $u, 'h' => $tok]));
}
