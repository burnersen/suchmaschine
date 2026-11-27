<?php
/* Suchmaschine – Zusatzdienste von Brave mit eigenem Plan und Key: Suchvorschläge (Autosuggest), Rechtschreibhilfe (Spellcheck)
   und KI-Antwort (Answers). Jeder hat ein eigenes Monatslimit und bremst sich selbst, bevor Kosten entstehen: Ist das Limit
   erreicht, fällt die Funktion einfach weg, die Suche läuft weiter. Nur für angemeldete Konten, jedes Konto kann sie abschalten. */
defined('SM_VERSION') || exit;

/**
 * Die Zusatzdienste. limit = Standardlimit pro Kalendermonat (Brave rechnet diese Pläne pro Monat ab),
 * money = gezählt wird in Dollar (Answers kostet je nach Textmenge), sonst in Anfragen.
 */
function smExtras(): array
{
    return [
        'suggest' => ['name' => t('Suchvorschläge'), 'plan' => 'Autosuggest', 'limit' => 9950, 'money' => false,
                      'desc' => t('Vorschläge beim Tippen. Das Monatsguthaben reicht für etwa 10.000 Anfragen, jeder Vorschlag bleibt eine Stunde im Zwischenspeicher.')],
        'spell'   => ['name' => t('Rechtschreibhilfe'), 'plan' => 'Spellcheck', 'limit' => 9950, 'money' => false,
                      'desc' => t('„Meintest du …?“ über den Treffern. Brave korrigiert Tippfehler meist schon selbst, darum fragt die Suchmaschine nur, wenn nicht: bei „Wortgenau“ und wenn kaum etwas gefunden wurde. Etwa 10.000 Anfragen im Monat.')],
        'answer'  => ['name' => t('KI-Antwort'), 'plan' => 'Answers', 'limit' => 4.5, 'money' => true,
                      'desc' => t('Eine kurze Antwort von Brave zur Suche, auf Knopfdruck oder automatisch. Brave rechnet nach Anfrage und Textmenge ab, eine Antwort kostet meist 5 bis 7 Cent. Das Monatsguthaben von 5 $ reicht also für etwa 70 bis 90 Antworten. Gezählt wird der echte Preis, den Brave mitschickt.')],
    ];
}

/** Einstellung eines Zusatzdienstes: key, on, limit, pause */
function smExGet(string $id, string $f): string
{
    return smSetting('x_' . $id . '_' . $f);
}

function smExSet(string $id, string $f, string $v): void
{
    smSetSetting('x_' . $id . '_' . $f, $v);
}

/** Alle Einstellungsnamen (für die Sicherung der Installation) */
function smExSettingKeys(): array
{
    $k = [];
    foreach (array_keys(smExtras()) as $id) foreach (['key', 'on', 'limit'] as $f) $k[] = 'x_' . $id . '_' . $f;
    return $k;
}

function smExActive(string $id): bool
{
    return isset(smExtras()[$id]) && trim(smExGet($id, 'key')) !== '' && smExGet($id, 'on') !== '0';
}

/** Monatslimit (Anfragen oder Dollar), 0 = ohne Grenze */
function smExLimit(string $id): float
{
    $v = smExGet($id, 'limit');
    return $v === '' ? (float)smExtras()[$id]['limit'] : max(0.0, (float)$v);
}

/** Verbrauch im laufenden Kalendermonat */
function smExUsed(string $id): float
{
    return smUsageSum('x_' . $id, date('Y-m-01'));
}

/**
 * Zähler auf den Stand laut Brave-Dashboard setzen (z. B. nach einem neuen Server mitten im Monat): Danach ist der Verbrauch
 * im laufenden Monat genau $used, alles Weitere zählt dazu. Die Korrektur steht als eigene Zeile (Konto -1) am Monatsersten.
 */
function smExSetUsed(string $id, ?float $used, ?int $requests = null): void
{
    $from = date('Y-m-01');
    $p = 'x_' . $id;
    // Bisherige Korrektur merken, damit ein Feld das andere nicht überschreibt
    $old = smQ("SELECT COALESCE(SUM(credits), 0) AS c, COALESCE(SUM(req), 0) AS n FROM usage_log WHERE provider = ? AND user_id = -1 AND day >= ?", [$p, $from])->fetch();
    smQ("DELETE FROM usage_log WHERE provider = ? AND user_id = -1 AND day >= ?", [$p, $from]);
    $c = $used !== null ? $used - smExUsed($id) : (float)$old['c'];
    $n = $requests !== null ? $requests - smExRequests($id) : (int)$old['n'];
    if (abs($c) >= 0.0005 || $n !== 0) smQ("INSERT INTO usage_log (day, provider, kind, user_id, credits, req) VALUES (?, ?, 'adjust', -1, ?, ?)", [$from, $p, round($c, 4), $n]);
    smExSet($id, 'usedset', (string)time());
}

/** Anzahl Anfragen im laufenden Monat (wie „Requests“ im Brave-Dashboard) */
function smExRequests(string $id): int
{
    return (int)smQ("SELECT COALESCE(SUM(req), 0) FROM usage_log WHERE provider = ? AND day >= ?", ['x_' . $id, date('Y-m-01')])->fetchColumn();
}

/** Darf der Dienst jetzt für dieses Konto fragen? $est = was die Anfrage voraussichtlich kostet */
function smExCan(string $id, ?array $me, float $est = 1.0): bool
{
    if (!$me || !smExActive($id) || (int)smExGet($id, 'pause') > time()) return false;
    $pref = smPrefs($me)['x_' . $id] ?? 'on';
    if ($pref === 'off') return false;
    $lim = smExLimit($id);
    return $lim <= 0 || smExUsed($id) + $est <= $lim;
}

/** Buchen (negativ = gutschreiben) */
function smExBook(string $id, int $uid, float $amount, int $req = 1): void
{
    smUsageAdd('x_' . $id, $id, $uid, $amount, $req);
}

/** Bei Key-, Guthaben- oder Serverfehlern eine Weile nicht fragen. Die Suche selbst läuft ja weiter. */
function smExFailed(string $id, array $r): void
{
    error_log('Suchmaschine: Brave ' . $id . ' HTTP ' . $r['status'] . ' ' . substr((string)$r['body'], 0, 200));
    if (in_array($r['status'], [401, 402, 403, 429], true) || $r['status'] >= 500 || $r['status'] === 0) smExSet($id, 'pause', (string)(time() + 300));
}

/** Anfrage an einen Zusatzdienst */
function smExRequest(string $id, string $q, string $key, ?array $prefs = null): array
{
    $prefs = $prefs ?? smPrefs(null);
    $c = (string)($prefs['country'] ?? 'ALL');
    $base = smProvBase('extras', 'https://api.search.brave.com/res/v1/');
    $loc = ['country' => $c !== '' ? $c : 'ALL', 'lang' => (string)($prefs['search_lang'] ?? 'en')];
    if ($id === 'answer') {
        return ['url' => $base . 'chat/completions', 'method' => 'POST',
                'headers' => ['Content-Type: application/json', 'Accept: application/json', 'X-Subscription-Token: ' . $key],
                'body' => json_encode(['model' => 'brave', 'stream' => false, 'country' => strtolower($loc['country'] !== 'ALL' ? $loc['country'] : 'us'),
                    'language' => $loc['lang'], 'messages' => [['role' => 'user', 'content' => $q]]], JSON_UNESCAPED_UNICODE)];
    }
    $path = $id === 'suggest' ? 'suggest/search' : 'spellcheck/search';
    return ['url' => $base . $path . '?' . http_build_query(['q' => $q, 'count' => 8] + $loc), 'method' => 'GET',
            'headers' => ['Accept: application/json', 'X-Subscription-Token: ' . $key]];
}

/** Preis einer Antwort laut Brave (Dollar), sonst grob geschätzt */
function smExCost(string $id, array $r): float
{
    if (!smExtras()[$id]['money']) return 1.0;
    $c = $r['headers']['x-request-total-cost'] ?? '';
    return is_numeric($c) ? max(0.0, (float)$c) : 0.07;
}

/** Key prüfen. Liefert Fehlertext oder ''. Answers wird nicht getestet: jede Antwort kostet etwa 6 Cent. */
function smExTest(string $id, string $key): string
{
    if ($id === 'answer') return '';
    $r = smHttpMulti(['x' => smExRequest($id, $id === 'answer' ? 'Antworte nur mit OK.' : 'wetter', trim($key))], $id === 'answer' ? 30 : 10)['x'];
    $d = json_decode($r['body'], true);
    if ($r['status'] === 200 && is_array($d)) {
        smExBook($id, 0, smExCost($id, $r));
        return '';
    }
    $msg = smProvErrorText('brave', $r, is_array($d) ? $d : null)[0];
    return str_replace('Brave Search', 'Brave ' . smExtras()[$id]['plan'], $msg);
}

/* ---------- Suchvorschläge ---------- */

/**
 * ?a=suggest&q=…: Vorschläge fürs Suchfeld (JSON), nur für angemeldete Konten. Der eigene Server fragt Brave,
 * jeder Vorschlag bleibt eine Stunde im Zwischenspeicher. Ist das Limit erreicht, kommt einfach eine leere Liste.
 */
function smServeSuggest(?array $me): void
{
    $q = trim(mb_substr(is_string($_GET['q'] ?? null) ? $_GET['q'] : '', 0, 100));
    $out = [];
    if ($me && mb_strlen($q) >= 2 && $q[0] !== '!') {
        $prefs = smPrefs($me);
        $ck = 'sugg|' . $prefs['country'] . '|' . $prefs['search_lang'] . '|' . mb_strtolower($q);
        $hit = smCacheGet($ck);
        if ($hit !== null) {
            $out = (array)($hit['l'] ?? []);
        } elseif (smExCan('suggest', $me)) {
            smExBook('suggest', (int)$me['id'], 1);
            $r = smHttpMulti(['x' => smExRequest('suggest', $q, trim(smExGet('suggest', 'key')), $prefs)], 3)['x'];
            $d = json_decode($r['body'], true);
            if ($r['status'] === 200 && is_array($d) && is_array($d['results'] ?? null)) {
                foreach ($d['results'] as $s) {
                    $s = is_array($s) ? trim(smPlain((string)($s['query'] ?? ''))) : '';
                    if ($s !== '' && mb_strlen($s) <= 120) $out[] = $s;
                    if (count($out) >= 8) break;
                }
                smCacheSet($ck, ['l' => $out], 3600);
            } else {
                smExBook('suggest', (int)$me['id'], -1, -1);
                smExFailed('suggest', $r);
            }
        }
    }
    smHeaders();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, max-age=600');
    echo json_encode(['list' => array_values($out)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---------- Rechtschreibhilfe ---------- */

/** „Meintest du …?“: korrigierte Schreibweise von Brave oder '' (bleibt einen Tag im Zwischenspeicher) */
function smSpellcheck(string $q, ?array $me): string
{
    if (!$me || mb_strlen($q) < 3 || mb_strlen($q) > 100 || strpbrk($q, ':"') !== false) return '';
    $prefs = smPrefs($me);
    $ck = 'spell|' . $prefs['country'] . '|' . $prefs['search_lang'] . '|' . mb_strtolower($q);
    $hit = smCacheGet($ck);
    if ($hit !== null) {
        $s = (string)($hit['s'] ?? '');
    } else {
        if (!smExCan('spell', $me)) return '';
        smExBook('spell', (int)$me['id'], 1);
        $r = smHttpMulti(['x' => smExRequest('spell', $q, trim(smExGet('spell', 'key')), $prefs)], 3)['x'];
        $d = json_decode((string)$r['body'], true);
        if ($r['status'] !== 200 || !is_array($d)) {
            smExBook('spell', (int)$me['id'], -1, -1);
            smExFailed('spell', $r);
            return '';
        }
        $s = trim(smPlain((string)($d['results'][0]['query'] ?? '')));
        smCacheSet($ck, ['s' => $s], 86400);
    }
    return $s !== '' && mb_strtolower($s) !== mb_strtolower($q) ? $s : '';
}

/* ---------- KI-Antwort ---------- */

/** Was eine Antwort voraussichtlich kostet: Durchschnitt der letzten, mindestens 7 Cent */
function smAnswerEstimate(): float
{
    $r = smQ("SELECT SUM(credits) AS c, SUM(req) AS n FROM usage_log WHERE provider = 'x_answer' AND user_id <> -1 AND day >= ?", [date('Y-m-01')])->fetch();
    $avg = $r && (int)$r['n'] > 0 ? (float)$r['c'] / (int)$r['n'] : 0.0;
    return max(0.07, $avg * 1.2);
}

/**
 * ?a=answer&q=…: KI-Antwort von Brave als HTML-Stück (für den Knopf über den Treffern oder automatisch).
 * Antworten bleiben eine Stunde im Zwischenspeicher. Ist das Limit (in Dollar) erreicht, gibt es nur einen Hinweis.
 */
function smServeAnswer(?array $me): void
{
    $q = trim(mb_substr(is_string($_GET['q'] ?? null) ? $_GET['q'] : '', 0, 400));
    $html = '';
    $status = 200;
    if (!$me || $q === '' || !smExActive('answer')) {
        $status = 204;
    } else {
        $prefs = smPrefs($me);
        $ck = 'answer|' . $prefs['country'] . '|' . $prefs['search_lang'] . '|' . mb_strtolower($q);
        $hit = smCacheGet($ck);
        $text = $hit !== null ? (string)($hit['t'] ?? '') : null;
        if ($text === null) {
            $est = smAnswerEstimate();
            if (!smExCan('answer', $me, $est)) {
                $html = '<p class="muted small">' . h(t('Das Monatslimit für KI-Antworten ist erreicht. Ab dem 1. geht es weiter.')) . '</p>';
            } else {
                smExBook('answer', (int)$me['id'], $est);
                $r = smHttpMulti(['x' => smExRequest('answer', $q, trim(smExGet('answer', 'key')), $prefs)], 45)['x'];
                $d = json_decode($r['body'], true);
                $text = $r['status'] === 200 && is_array($d) ? trim((string)($d['choices'][0]['message']['content'] ?? '')) : null;
                // Vorab gebuchte Schätzung durch den echten Preis ersetzen (bei Fehlern: gutschreiben)
                smExBook('answer', (int)$me['id'], ($text !== null ? smExCost('answer', $r) : 0.0) - $est, $text !== null ? 0 : -1);
                if ($text === null) {
                    smExFailed('answer', $r);
                    $html = '<p class="muted small">' . h(t('Brave konnte gerade keine Antwort liefern.')) . '</p>';
                } else {
                    smCacheSet($ck, ['t' => $text], 3600);
                }
            }
        }
        if ($text !== null && $text !== '') $html = smMiniMarkdown($text);
    }
    smHeaders();
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex');
    http_response_code($status);
    echo $html;
    exit;
}

/** Das bisschen Markdown der Antworten (Absätze, Listen, **fett**, Überschriften) sicher als HTML */
function smMiniMarkdown(string $md): string
{
    $out = '';
    $list = '';
    $inline = function (string $s): string {
        $s = h($s);
        $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s);
        return preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/u', '<em>$1</em>', $s);
    };
    $close = function () use (&$out, &$list) { if ($list !== '') { $out .= '</' . $list . '>'; $list = ''; } };
    foreach (preg_split('/\R/u', $md) as $line) {
        $t = trim($line);
        if ($t === '') { $close(); continue; }
        if (preg_match('/^[*\-•]\s+(.*)$/u', $t, $m) || preg_match('/^\d+[.)]\s+(.*)$/u', $t, $m)) {
            $type = preg_match('/^\d/', $t) ? 'ol' : 'ul';
            if ($list !== $type) { $close(); $out .= '<' . $type . '>'; $list = $type; }
            $out .= '<li>' . $inline($m[1]) . '</li>';
            continue;
        }
        $close();
        if (preg_match('/^#{1,6}\s+(.*)$/u', $t, $m)) $out .= '<p><strong>' . $inline($m[1]) . '</strong></p>';
        else $out .= '<p>' . $inline($t) . '</p>';
    }
    $close();
    return $out;
}
