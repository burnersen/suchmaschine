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
    $view = ($_GET['v'] ?? '') === 'all' ? 'all' : '';
    $k = hash('sha256', 'read|' . $me['id'] . '|' . date('YmdH'));
    $row = smQ('SELECT fails FROM throttle WHERE k = ?', [$k])->fetch();
    $err = '';
    $r = null;
    if ($row && (int)$row['fails'] >= 60) {
        $err = t('Für diese Stunde sind genug Seiten im Lesemodus geöffnet worden. Bitte später wieder.');
    } else {
        smQ('INSERT INTO throttle (k, fails, last) VALUES (?, 1, ?) ON CONFLICT(k) DO UPDATE SET fails = fails + 1, last = excluded.last', [$k, time()]);
        $html = null;
        $unlocked = false;
        // Auf Wunsch über den Web Unlocker von Bright Data (für Seiten, die nur mit JavaScript laufen oder Abrufe vom Server sperren).
        // Kostet 1 Anfrage aus demselben Kontingent wie die Google-Suche.
        if ($post && ($_POST['do'] ?? '') === 'unlock') {
            $why = smProvBlock('brightdata', 'web', $me['id']);
            if ($why !== '') {
                $err = 'Bright Data: ' . smBlockText('brightdata', $why);
            } elseif (!smProvReserve('brightdata', 'read', $me['id'], 1)) {
                $err = 'Bright Data: ' . smBlockText('brightdata', 'limit');
            } else {
                [$html, $uerr] = smBdUnlock($u);
                if ($html !== null) {
                    $unlocked = true;
                } else {
                    smUsageAdd('brightdata', 'read', $me['id'], -1, -1);
                    $err = $uerr;
                }
            }
        }
        if ($err === '') {
            $r = smReadPage($u, $html);
            $r['unlocked'] = $unlocked;
        }
    }
    if ($r && $r['ok'] && $view === 'all' && !empty($r['alt'])) {
        $r['blocks'] = $r['alt'];
        $r['kind'] = 'article';
        $r['was_recipe'] = true;
    }
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    smRender('read', ['me' => $me, 'prefs' => smPrefs($me), 'u' => $u, 'r' => $r, 'error' => $err, 'view' => $view,
                      'title' => ($r['title'] ?? '') !== '' ? $r['title'] . ' – ' . t('Lesemodus') : t('Lesemodus')]);
}
