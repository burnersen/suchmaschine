<?php
/* Suchmaschine – Kurzlinks (…/?s=abc123) und Text-Schnipsel (…/?n=…#Schlüssel).
   Ideen aus ShrinkURL (gleicher Autor, gleiche Lizenz): Wunschname, Ablaufdatum, Aufruf-Grenze, Passwort, Zwischenseite,
   Vorschau mit „+“, Tracking-Parameter entfernen, Ziel ändern, an/aus, Statistik nur als Zähler pro Tag (keine IP, kein Cookie).
   Text-Schnipsel werden im Browser verschlüsselt (AES-256-GCM). Der Schlüssel steht hinter dem # im Link und erreicht den Server nie. */
defined('SM_VERSION') || exit;

const SM_SHORT_MAX = 500;            // Kurzlinks pro Konto
const SM_NOTE_MAX_BYTES = 120000;    // verschlüsselter Text-Schnipsel, etwa 80.000 Zeichen
const SM_NOTE_MAX_DAYS = 30;
const SM_NOTE_MAX_PER_USER = 200;

/** Ziel für einen Kurzlink prüfen: nur http(s), ohne Leerzeichen. Ohne Protokoll wird https:// ergänzt. '' = ungültig. */
function smCleanShortTarget($u): string
{
    $u = trim(is_string($u) ? $u : '');
    if ($u !== '' && !preg_match('~^[a-z][a-z0-9+.\-]*://~i', $u) && preg_match('~^[a-z0-9.\-]+\.[a-z]{2,}(/|$)~i', $u)) $u = 'https://' . $u;
    if (strlen($u) > 2000 || !preg_match('~^https?://[^\s/?#]+\.[^\s/?#]+~i', $u) || preg_match('/[\x00-\x20"<>\\\\]/', $u)) return '';
    $host = (string)parse_url($u, PHP_URL_HOST);
    if ($host === '') return '';
    // Kein Kurzlink auf einen Kurzlink dieser Suchmaschine (Schleife)
    $own = strtolower((string)parse_url(smOrigin(), PHP_URL_HOST));
    if ($own !== '' && strtolower($host) === $own && preg_match('/[?&](s|n)=/', $u)) return '';
    return $u;
}

/** Tracking-Parameter entfernen (utm_…, fbclid, gclid und Verwandte) */
function smStripTracking(string $u): string
{
    $q = (string)parse_url($u, PHP_URL_QUERY);
    if ($q === '') return $u;
    $keep = [];
    foreach (explode('&', $q) as $pair) {
        $k = strtolower(urldecode(explode('=', $pair, 2)[0]));
        if (preg_match('/^(utm_[a-z_]+|fbclid|gclid|gclsrc|dclid|gbraid|wbraid|msclkid|yclid|twclid|ttclid|li_fat_id|igshid|igsh|mc_cid|mc_eid|_hsenc|_hsmi|__hssc|__hstc|__hsfp|hsctatracking|mkt_tok|oly_anon_id|oly_enc_id|vero_id|_openstat|ref_src|ref_url|s_cid|wt_mc|wt\.mc_id|pk_campaign|pk_kwd|pk_source|pk_medium|mtm_[a-z]+|spm|scm|si|feature)$/', $k)) continue;
        $keep[] = $pair;
    }
    $base = preg_replace('/\?[^#]*/', '', $u, 1);
    $hash = '';
    if (($p = strpos($base, '#')) !== false) { $hash = substr($base, $p); $base = substr($base, 0, $p); }
    return $base . ($keep ? '?' . implode('&', $keep) : '') . $hash;
}

/** Wunschname prüfen: 3 bis 40 Zeichen, Buchstaben, Ziffern, - und _ */
function smShortCodeOk(string $c): bool
{
    return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,39}$/', $c);
}

function smShortUrl(string $code): string
{
    return smOrigin() . '?s=' . rawurlencode($code);
}

/** Kurzlink zu einem Code (genau, sonst ohne Groß/klein) oder null */
function smShortFind(string $code): ?array
{
    if (!preg_match('/^[A-Za-z0-9_-]{2,40}$/', $code)) return null;
    $row = smQ('SELECT * FROM shortlinks WHERE code = ?', [$code])->fetch();
    if (!$row) $row = smQ('SELECT * FROM shortlinks WHERE code = ? COLLATE NOCASE LIMIT 1', [$code])->fetch();
    return $row ?: null;
}

/** Titel der Zielseite holen (für die Liste), schnell und sicher. '' wenn nicht möglich. */
function smShortTitle(string $url): string
{
    $r = smNetChain($url, ['method' => 'GET', 'limit' => 131072, 'timeout' => 4, 'max' => 4]);
    $body = (string)($r['last']['body'] ?? '');
    if ($body === '' || !preg_match('~<title[^>]*>(.*?)</title>~is', $body, $m)) return '';
    $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if (!mb_check_encoding($t, 'UTF-8')) $t = mb_convert_encoding($t, 'UTF-8', 'ISO-8859-1');
    return mb_substr($t, 0, 200);
}

/**
 * Kurzlink anlegen. $o: code (Wunschname), expires (Zeitstempel), max_hits, pass, note, strip (Tracking weg), title (Titel holen)
 * Ohne Wunschname und Extras kommt für dieselbe Adresse der vorhandene Kurzlink zurück. Liefert [Code, Fehlertext].
 */
function smShortCreate(int $uid, string $url, array $o = []): array
{
    $url = smCleanShortTarget($url);
    if ($url === '') return ['', t('Bitte eine vollständige Adresse eingeben, z. B. https://example.org/seite.')];
    if (!empty($o['strip'])) $url = smStripTracking($url);
    $custom = trim((string)($o['code'] ?? ''));
    $plain = $custom === '' && empty($o['expires']) && empty($o['max_hits']) && (string)($o['pass'] ?? '') === '';
    if ($plain) {
        $old = smQ("SELECT code FROM shortlinks WHERE user_id = ? AND url = ? AND pass = '' AND expires = 0 AND max_hits = 0 AND active = 1", [$uid, $url])->fetchColumn();
        if ($old) return [(string)$old, ''];
    }
    if ((int)smQ('SELECT COUNT(*) FROM shortlinks WHERE user_id = ?', [$uid])->fetchColumn() >= SM_SHORT_MAX) {
        return ['', t('Du hast schon %d Kurzlinks. Bitte erst alte löschen.', SM_SHORT_MAX)];
    }
    $exp = max(0, (int)($o['expires'] ?? 0));
    if ($exp > 0 && $exp < time()) return ['', t('Das Ablaufdatum liegt in der Vergangenheit.')];
    $max = max(0, min(1000000, (int)($o['max_hits'] ?? 0)));
    $pass = (string)($o['pass'] ?? '');
    if ($pass !== '' && mb_strlen($pass) < 4) return ['', t('Das Passwort für den Link braucht mindestens 4 Zeichen.')];
    $hash = $pass !== '' ? password_hash($pass, PASSWORD_DEFAULT) : '';
    $title = !empty($o['title']) ? smShortTitle($url) : '';
    $note = mb_substr(trim((string)($o['note'] ?? '')), 0, 200);
    if ($custom !== '') {
        if (!smShortCodeOk($custom)) return ['', t('Der Wunschname darf 3 bis 40 Zeichen haben: Buchstaben, Ziffern, - und _.')];
        if (smQ('SELECT 1 FROM shortlinks WHERE code = ? COLLATE NOCASE', [$custom])->fetchColumn()) return ['', t('Den Namen „%s“ gibt es schon. Bitte einen anderen wählen.', $custom)];
        smQ('INSERT INTO shortlinks (code, url, user_id, created, title, expires, max_hits, pass, note, updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$custom, $url, $uid, time(), $title, $exp, $max, $hash, $note, time()]);
        return [$custom, ''];
    }
    $abc = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    for ($try = 0; $try < 12; $try++) {
        $code = '';
        for ($i = 0; $i < ($try < 8 ? 6 : 8); $i++) $code .= $abc[random_int(0, strlen($abc) - 1)];
        if (smQ('SELECT 1 FROM shortlinks WHERE code = ? COLLATE NOCASE', [$code])->fetchColumn()) continue;
        smQ('INSERT INTO shortlinks (code, url, user_id, created, title, expires, max_hits, pass, note, updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$code, $url, $uid, time(), $title, $exp, $max, $hash, $note, time()]);
        return [$code, ''];
    }
    return ['', t('Es konnte kein freier Kurzlink gefunden werden. Bitte nochmal versuchen.')];
}

/** Kurzlink ändern. $o: url, expires, max_hits, pass ('' = lassen, '-' = entfernen), active, note, code (umbenennen). Liefert Fehlertext oder ''. */
function smShortUpdate(int $uid, string $code, array $o): string
{
    $row = smQ('SELECT * FROM shortlinks WHERE code = ? AND user_id = ?', [$code, $uid])->fetch();
    if (!$row) return t('Diesen Kurzlink gibt es nicht (mehr).');
    $set = [];
    $args = [];
    if (array_key_exists('url', $o)) {
        $url = smCleanShortTarget((string)$o['url']);
        if ($url === '') return t('Bitte eine vollständige Adresse eingeben, z. B. https://example.org/seite.');
        if (!empty($o['strip'])) $url = smStripTracking($url);
        if ($url !== $row['url']) { $set[] = 'url = ?'; $args[] = $url; $set[] = "title = ''"; $set[] = 'dead = 0'; $set[] = 'checked = 0'; }
    }
    if (array_key_exists('expires', $o)) { $set[] = 'expires = ?'; $args[] = max(0, (int)$o['expires']); }
    if (array_key_exists('max_hits', $o)) { $set[] = 'max_hits = ?'; $args[] = max(0, min(1000000, (int)$o['max_hits'])); }
    if (array_key_exists('active', $o)) { $set[] = 'active = ?'; $args[] = !empty($o['active']) ? 1 : 0; }
    if (array_key_exists('note', $o)) { $set[] = 'note = ?'; $args[] = mb_substr(trim((string)$o['note']), 0, 200); }
    $pass = (string)($o['pass'] ?? '');
    if ($pass === '-') { $set[] = "pass = ''"; }
    elseif ($pass !== '') {
        if (mb_strlen($pass) < 4) return t('Das Passwort für den Link braucht mindestens 4 Zeichen.');
        $set[] = 'pass = ?'; $args[] = password_hash($pass, PASSWORD_DEFAULT);
    }
    $new = trim((string)($o['code'] ?? ''));
    if ($new !== '' && $new !== $code) {
        if (!smShortCodeOk($new)) return t('Der Wunschname darf 3 bis 40 Zeichen haben: Buchstaben, Ziffern, - und _.');
        if (smQ('SELECT 1 FROM shortlinks WHERE code = ? COLLATE NOCASE AND code <> ?', [$new, $code])->fetchColumn()) return t('Den Namen „%s“ gibt es schon. Bitte einen anderen wählen.', $new);
        $set[] = 'code = ?'; $args[] = $new;
    }
    if (!$set) return '';
    $set[] = 'updated = ?';
    $args[] = time();
    $args[] = $code;
    $args[] = $uid;
    smQ('UPDATE shortlinks SET ' . implode(', ', $set) . ' WHERE code = ? AND user_id = ?', $args);
    if ($new !== '' && $new !== $code) smQ('UPDATE short_days SET code = ? WHERE code = ?', [$new, $code]);
    return '';
}

function smShortDelete(int $uid, string $code): void
{
    if (smQ('DELETE FROM shortlinks WHERE code = ? AND user_id = ?', [$code, $uid])->rowCount() > 0) smQ('DELETE FROM short_days WHERE code = ?', [$code]);
}

/** Liste eines Kontos, neueste zuerst. Ohne Passwort-Prüfsumme, dafür mit Zustand. */
function smShortList(int $uid): array
{
    $out = [];
    foreach (smQ('SELECT * FROM shortlinks WHERE user_id = ? ORDER BY created DESC LIMIT ' . SM_SHORT_MAX, [$uid])->fetchAll() as $r) {
        $out[] = smShortPublic($r);
    }
    return $out;
}

/** Ein Kurzlink, wie die Oberfläche ihn sieht */
function smShortPublic(array $r): array
{
    $state = 'ok';
    if ((int)$r['active'] !== 1) $state = 'off';
    elseif ((int)$r['expires'] > 0 && (int)$r['expires'] < time()) $state = 'expired';
    elseif ((int)$r['max_hits'] > 0 && (int)$r['hits'] >= (int)$r['max_hits']) $state = 'used';
    return [
        'code' => $r['code'], 'url' => $r['url'], 'short' => smShortUrl($r['code']), 'title' => (string)$r['title'], 'note' => (string)$r['note'],
        'created' => (int)$r['created'], 'hits' => (int)$r['hits'], 'last' => (int)$r['last_hit'], 'expires' => (int)$r['expires'], 'max' => (int)$r['max_hits'],
        'pass' => (string)$r['pass'] !== '', 'active' => (int)$r['active'] === 1, 'state' => $state, 'dead' => (int)$r['dead'], 'checked' => (int)$r['checked'],
    ];
}

/** Aufrufe pro Tag der letzten $days Tage: ['2026-11-26' => 3, …] (lückenlos) */
function smShortStats(int $uid, string $code, int $days): array
{
    if (!smQ('SELECT 1 FROM shortlinks WHERE code = ? AND user_id = ?', [$code, $uid])->fetchColumn()) return [];
    $days = max(7, min(366, $days));
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $have = [];
    foreach (smQ('SELECT day, n FROM short_days WHERE code = ? AND day >= ?', [$code, $from])->fetchAll() as $r) $have[$r['day']] = (int)$r['n'];
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) { $d = date('Y-m-d', strtotime('-' . $i . ' days')); $out[$d] = $have[$d] ?? 0; }
    return $out;
}

/** Ziele prüfen (höchstens 40 auf einmal, die am längsten nicht geprüften zuerst). Liefert die Zahl der toten Ziele. */
function smShortCheck(int $uid): array
{
    $rows = smQ('SELECT code, url FROM shortlinks WHERE user_id = ? ORDER BY checked ASC LIMIT 40', [$uid])->fetchAll();
    if (!$rows) return ['checked' => 0, 'dead' => 0];
    $res = smNetCheckMany(array_column($rows, 'url'), 7);
    $dead = 0;
    foreach ($rows as $i => $r) {
        $st = (int)($res[$i]['status'] ?? 0);
        $err = (string)($res[$i]['error'] ?? '');
        // tot = gibt es nicht (404/410), Domain weg, keine Verbindung. 401/403/405/429/5xx zählen nicht (Seiten sperren oft Abrufe von Servern).
        $isDead = in_array($st, [404, 410], true) || in_array($err, ['nx', 'connect'], true) ? 1 : 0;
        $dead += $isDead;
        smQ('UPDATE shortlinks SET checked = ?, dead = ? WHERE code = ? AND user_id = ?', [time(), $isDead ? max(1, $st) : 0, $r['code'], $uid]);
    }
    return ['checked' => count($rows), 'dead' => $dead];
}

/** Aufruf zählen: Gesamtzahl, Tageszähler, letzter Aufruf */
function smShortCount(string $code): void
{
    smQ('UPDATE shortlinks SET hits = hits + 1, last_hit = ? WHERE code = ?', [time(), $code]);
    smQ('INSERT INTO short_days (code, day, n) VALUES (?, ?, 1) ON CONFLICT(code, day) DO UPDATE SET n = n + 1', [$code, date('Y-m-d')]);
}

/**
 * Kurzlink aufrufen (ohne Anmeldung). „…?s=abc+“ zeigt nur das Ziel (Vorschau), zählt nichts.
 * Mit Passwort oder Aufruf-Grenze kommt erst eine Zwischenseite, damit die Linkvorschau eines Messengers keinen Aufruf verbraucht.
 */
function smServeShort(string $raw): void
{
    $preview = (bool)preg_match('/[+ ]$/', $raw);
    $code = rtrim($raw, '+ ');
    $row = smShortFind($code);
    $view = function (string $mode, array $more = []) use ($row): void {
        if (in_array($mode, ['gone', 'off', 'expired', 'used'], true)) http_response_code(410);
        if ($mode === 'missing') http_response_code(404);
        smRender('short', ['title' => t('Kurzlink'), 'bare' => true, 'mode' => $mode, 'link' => $row ? smShortPublic($row) : null] + $more);
        exit;
    };
    if (!$row || smCleanShortTarget($row['url']) === '') $view('missing');
    if ($preview) $view('preview');
    $pub = smShortPublic($row);
    if ($pub['state'] !== 'ok') $view($pub['state']);
    $gate = $row['pass'] !== '' || (int)$row['max_hits'] > 0;
    if ($gate) {
        $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        if (!$post || !smSameOrigin()) $view('gate');
        if ($row['pass'] !== '') {
            $tk = 'short|' . $row['code'];
            if (($w = smThrottleWait($tk)) > 0) $view('gate', ['err' => t('Zu viele Versuche. Bitte in %d Sekunden noch einmal.', $w)]);
            if (!password_verify((string)($_POST['pass'] ?? ''), $row['pass'])) {
                smThrottleFail($tk);
                $view('gate', ['err' => t('Das Passwort stimmt nicht.')]);
            }
            smThrottleClear($tk);
        }
        // Aufruf-Grenze: erst zählen, dann weiter (gleichzeitige Aufrufe dürfen die Grenze nicht überschreiten)
        if ((int)$row['max_hits'] > 0) {
            $ok = smQ('UPDATE shortlinks SET hits = hits + 1, last_hit = ? WHERE code = ? AND hits < max_hits', [time(), $row['code']])->rowCount() > 0;
            if (!$ok) $view('used');
            smQ('INSERT INTO short_days (code, day, n) VALUES (?, ?, 1) ON CONFLICT(code, day) DO UPDATE SET n = n + 1', [$row['code'], date('Y-m-d')]);
        } else {
            smShortCount($row['code']);
        }
        // Nach einem Formular blockt die CSP eine Weiterleitung nach draußen, darum die Zwischenseite der Suchmaschine
        smExternalRedirect($row['url'], (string)$row['title']);
    }
    smShortCount($row['code']);
    smHeaders();
    header('Cache-Control: no-store');
    header('Location: ' . $row['url'], true, 302);
    exit;
}

/* ---------- Text-Schnipsel ---------- */

/** Abgelaufene Schnipsel löschen (läuft nebenbei beim Anlegen und Auflisten) */
function smNoteCleanup(): void
{
    smQ('DELETE FROM notes WHERE expires < ?', [time()]);
}

/**
 * Schnipsel ablegen. $data = verschlüsselter Inhalt als JSON-Text {v, iv, ct, salt?} (Base64). Der Server prüft nur Form und Größe.
 * Liefert [id, Fehlertext]
 */
function smNoteCreate(int $uid, string $data, int $hours, int $views, bool $hasPass, string $label): array
{
    smNoteCleanup();
    if (strlen($data) > SM_NOTE_MAX_BYTES) return ['', t('Der Text ist zu lang.')];
    $d = json_decode($data, true);
    if (!is_array($d) || !isset($d['iv'], $d['ct']) || !is_string($d['iv']) || !is_string($d['ct']) || !preg_match('~^[A-Za-z0-9+/=_-]+$~', $d['ct'] . $d['iv'] . (string)($d['salt'] ?? ''))) {
        return ['', t('Der verschlüsselte Inhalt ist ungültig.')];
    }
    if ((int)smQ('SELECT COUNT(*) FROM notes WHERE user_id = ?', [$uid])->fetchColumn() >= SM_NOTE_MAX_PER_USER) return ['', t('Du hast schon %d Schnipsel. Bitte erst alte löschen.', SM_NOTE_MAX_PER_USER)];
    $hours = max(1, min(SM_NOTE_MAX_DAYS * 24, $hours));
    $views = max(0, min(100, $views));
    $id = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
    smQ('INSERT INTO notes (id, user_id, data, created, expires, views_left, has_pass, label) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$id, $uid, $data, time(), time() + $hours * 3600, $views, $hasPass ? 1 : 0, mb_substr(trim($label), 0, 80)]);
    return [$id, ''];
}

/** Schnipsel abholen (öffentlich). Verbraucht einen Abruf, beim letzten wird er sofort gelöscht. null = weg. */
function smNoteFetch(string $id): ?array
{
    if (!preg_match('/^[A-Za-z0-9_-]{16}$/', $id)) return null;
    smNoteCleanup();
    $row = smQ('SELECT * FROM notes WHERE id = ?', [$id])->fetch();
    if (!$row) return null;
    $left = (int)$row['views_left'];
    if ($left === 1) smQ('DELETE FROM notes WHERE id = ?', [$id]);
    elseif ($left > 1) smQ('UPDATE notes SET views_left = views_left - 1 WHERE id = ?', [$id]);
    return ['data' => $row['data'], 'left' => $left === 0 ? -1 : $left - 1, 'expires' => (int)$row['expires'], 'pass' => (int)$row['has_pass'] === 1];
}

/** Gibt es den Schnipsel noch? (ohne einen Abruf zu verbrauchen) */
function smNoteExists(string $id): ?array
{
    if (!preg_match('/^[A-Za-z0-9_-]{16}$/', $id)) return null;
    $row = smQ('SELECT expires, views_left, has_pass FROM notes WHERE id = ? AND expires >= ?', [$id, time()])->fetch();
    return $row ? ['expires' => (int)$row['expires'], 'left' => (int)$row['views_left'], 'pass' => (int)$row['has_pass'] === 1] : null;
}

function smNoteList(int $uid): array
{
    smNoteCleanup();
    $out = [];
    foreach (smQ('SELECT id, created, expires, views_left, has_pass, label FROM notes WHERE user_id = ? ORDER BY created DESC', [$uid])->fetchAll() as $r) {
        $out[] = ['id' => $r['id'], 'created' => (int)$r['created'], 'expires' => (int)$r['expires'], 'left' => (int)$r['views_left'], 'pass' => (int)$r['has_pass'] === 1, 'label' => $r['label']];
    }
    return $out;
}

function smNoteDelete(int $uid, string $id): void
{
    smQ('DELETE FROM notes WHERE id = ? AND user_id = ?', [$id, $uid]);
}

/** Abholseite …/?n=<id> (ohne Anmeldung). Den Inhalt holt erst ein Klick (POST), damit Linkvorschauen ihn nicht verbrauchen. */
function smServeNote(string $id): void
{
    $info = smNoteExists($id);
    if (!$info) http_response_code(404);
    smRender('note', ['title' => t('Text-Schnipsel'), 'bare' => true, 'noteId' => $id, 'info' => $info]);
    exit;
}

/** POST …/?a=noteget: verschlüsselten Inhalt ausliefern (JSON) */
function smServeNoteGet(): void
{
    smHeaders();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !smSameOrigin()) {
        http_response_code(403);
        exit(json_encode(['ok' => false]));
    }
    $r = smNoteFetch((string)($_POST['id'] ?? ''));
    if (!$r) exit(json_encode(['ok' => false, 'message' => t('Diesen Text-Schnipsel gibt es nicht mehr. Er wurde schon abgeholt oder ist abgelaufen.')]));
    exit(json_encode(['ok' => true] + $r, JSON_UNESCAPED_SLASHES));
}
