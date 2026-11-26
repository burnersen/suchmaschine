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
        $fcUsed = false;
        // Auf Wunsch über Firecrawl (für Seiten, die nur mit JavaScript laufen oder Abrufe vom Server sperren). Kostet 1 Credit.
        if ($post && ($_POST['do'] ?? '') === 'firecrawl') {
            $why = smProvBlock('firecrawl', 'web', $me['id']);
            if ($why !== '') {
                $err = 'Firecrawl: ' . smBlockText('firecrawl', $why);
            } elseif (!smProvReserve('firecrawl', 'read', $me['id'], 1)) {
                $err = 'Firecrawl: ' . smBlockText('firecrawl', 'limit');
            } else {
                $res = smHttpMulti(['x' => ['url' => smProvBase('firecrawl', 'https://api.firecrawl.dev/') . 'v2/scrape', 'method' => 'POST',
                    'headers' => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . trim(smProvGet('firecrawl', 'key'))],
                    'body' => json_encode(['url' => $u, 'formats' => ['rawHtml'], 'onlyMainContent' => false, 'blockAds' => true, 'timeout' => 30000])]], 40)['x'];
                $d = json_decode($res['body'], true);
                if ($res['status'] === 200 && is_string($d['data']['rawHtml'] ?? null)) {
                    $html = $d['data']['rawHtml'];
                    $fcUsed = true;
                    $real = $d['data']['metadata']['creditsUsed'] ?? null;
                    if (is_numeric($real) && (float)$real !== 1.0) smUsageAdd('firecrawl', 'read', $me['id'], (float)$real - 1, 0);
                } else {
                    smUsageAdd('firecrawl', 'read', $me['id'], -1, -1);
                    $err = smProvErrorText('firecrawl', $res, is_array($d) ? $d : null)[0];
                }
            }
        }
        if ($err === '') {
            $r = smReadPage($u, $html);
            $r['firecrawl'] = $fcUsed;
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
