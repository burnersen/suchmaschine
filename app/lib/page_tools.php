<?php
/* Suchmaschine – Seite Werkzeuge: Übersicht und einzelne Werkzeuge, Kurzlinks */
defined('SM_VERSION') || exit;

/**
 * Alle Werkzeuge nach Gruppen: id => [Symbol, Name, kurze Beschreibung].
 * Die id steht in der Adresse (?a=tools&tool=rechner) und ist zugleich der Abschnitt in app/views/tools.php.
 */
function smToolGroups(): array
{
    return [
        t('Rechnen und Umrechnen') => [
            'rechner'   => ['🧮', t('Rechner'), t('Grundrechenarten, Prozent, Wurzel, Potenz')],
            'einheiten' => ['📏', t('Einheiten'), t('Länge, Gewicht, Temperatur, Volumen und mehr')],
            'waehrung'  => ['💶', t('Währungen'), t('Tageskurse der Europäischen Zentralbank')],
        ],
        t('Zeit und Datum') => [
            'timer'     => ['⏱️', t('Timer und Stoppuhr'), t('Mit Ton, auch im Tab-Titel')],
            'countdown' => ['⏳', t('Countdown'), t('Tage bis Weihnachten oder zu deinem Datum')],
            'datum'     => ['📅', t('Datumsrechner und Alter'), t('Tage zwischen zwei Daten, plus und minus')],
            'feiertage' => ['🎉', t('Feiertage'), t('Gesetzliche Feiertage nach Bundesland')],
            'kalender'  => ['🗓️', t('Kalenderwoche und Uhrzeit'), t('Welche Woche ist gerade, wann ist KW 12?')],
        ],
        t('Zufall und Entscheidungen') => [
            'zufall'    => ['🎲', t('Würfel, Münze, Zufallszahl'), t('Echter Zufall vom Server')],
            'losen'     => ['🎟️', t('Losen und Gruppen bilden'), t('Gewinner ziehen, Teams einteilen, mischen')],
        ],
        t('Teilen und Sicherheit') => [
            'passwort'  => ['🔑', t('Passwort-Generator'), t('Sichere Passwörter und Passphrasen')],
            'qr'        => ['🔳', t('QR-Code'), t('Adresse oder Text als QR-Code')],
            'kurzlinks' => ['🔗', t('Kurzlinks'), t('Lange Adressen kurz machen')],
        ],
        t('Wetter und Netz') => [
            'wetter'    => ['🌦️', t('Wetter'), t('Vorhersage für deinen Ort')],
            'ip'        => ['🌐', t('Meine IP-Adresse'), t('So sieht dich das Internet')],
        ],
    ];
}

/** Ein Werkzeug: [Gruppe, Symbol, Name, Beschreibung] oder null */
function smToolInfo(string $id): ?array
{
    foreach (smToolGroups() as $g => $items) if (isset($items[$id])) return array_merge([$g], $items[$id]);
    return null;
}

/** Adresse eines Werkzeugs */
function smToolUrl(string $id, array $more = []): string
{
    return smUrl(['a' => 'tools', 'tool' => $id] + $more);
}

/** Werkzeuge: Übersicht oder ein einzelnes Werkzeug. Die meisten rechnen wie die Sofort-Antworten im Suchfeld. */
function smPageTools(?array $me, bool $post): void
{
    if ($post && $me) {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'short_add') {
            if (smSetting('shortener') !== '1') {
                smFlash('error', t('Kurzlinks sind ausgeschaltet.'));
            } else {
                [$code, $err] = smShortCreate($me['id'], (string)($_POST['url'] ?? ''));
                if ($err !== '') smFlash('error', $err); else smFlash('ok', t('Kurzlink: %s', smShortUrl($code)));
                if ($code !== '') smRedirect(smToolUrl('kurzlinks', ['new' => $code]));
            }
        } elseif ($do === 'short_del') {
            smQ('DELETE FROM shortlinks WHERE code = ? AND user_id = ?', [(string)($_POST['code'] ?? ''), $me['id']]);
            smFlash('ok', t('Kurzlink gelöscht.'));
        }
        smRedirect(smToolUrl('kurzlinks'));
    }
    $new = isset($_GET['new']) && is_string($_GET['new']) && preg_match('/^[A-Za-z0-9]{4,12}$/', $_GET['new']) ? $_GET['new'] : '';
    // Ein Werkzeug wurde benutzt: aus den Feldern die Anfrage bauen und mit derselben Rechnung wie im Suchfeld beantworten
    $tool = isset($_GET['tool']) && is_string($_GET['tool']) && smToolInfo($_GET['tool']) !== null ? $_GET['tool'] : '';
    // Ältere Adressen (?a=tools&qr=…) öffnen gleich das passende Werkzeug
    if ($tool === '' && isset($_GET['qr']) && is_string($_GET['qr'])) $tool = 'qr';
    if ($tool === '' && isset($_GET['new'])) $tool = 'kurzlinks';
    $card = null;
    $err = '';
    if ($tool === 'waehrung' && ($_GET['fx_refresh'] ?? '') === '1') smFxRates(true);
    if ($tool !== '') {
        $ia = isset($_GET['ia']) && is_string($_GET['ia']) ? mb_substr(trim($_GET['ia']), 0, 3000) : smToolQuery($tool);
        $_GET['ia'] = $ia;
        if ($ia !== '') {
            $card = smInstant($ia, $me);
            if ($card === null) $err = t('Das hat nicht geklappt. Bitte die Eingaben prüfen.');
            elseif (($card['type'] ?? '') === 'weather' && ($card['error'] ?? '') !== '' && $card['error'] !== 'noplace') $err = (string)$card['error'];
        }
    }
    $info = $tool !== '' ? smToolInfo($tool) : null;
    smRender('tools', ['me' => $me, 'prefs' => smPrefs($me), 'title' => ($info ? $info[2] . ' – ' : '') . t('Werkzeuge') . ' – ' . smSiteName(), 'new' => $new,
        'tool' => $tool, 'card' => $card, 'toolErr' => $err,
        'qr' => isset($_GET['qr']) && is_string($_GET['qr']) ? mb_substr($_GET['qr'], 0, SM_QR_MAX) : '']);
}

/** Anfrage im Format der Sofort-Antworten aus den Feldern eines Werkzeugs ('' = nichts zu tun) */
function smToolQuery(string $tool): string
{
    $g = function (string $k, string $def = ''): string { return isset($_GET[$k]) && is_string($_GET[$k]) ? trim(mb_substr($_GET[$k], 0, 3000)) : $def; };
    $num = function (string $k, string $def = '') use ($g): string { return preg_replace('/[^\d.,\-]/', '', $g($k, $def)); };
    // Datum aus <input type="date"> (2026-11-26) bleibt so, das versteht der Datumsrechner
    $date = function (string $k) use ($g): string { $v = $g($k); return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : ''; };
    switch ($tool) {
        case 'rechner':  return mb_substr($g('x'), 0, 300);
        case 'einheiten':
        case 'waehrung': return $num('n') !== '' && $g('from') !== '' && $g('to') !== '' ? $num('n') . ' ' . mb_strtolower($g('from')) . ' in ' . mb_strtolower($g('to')) : '';
        case 'timer':    return $g('watch') === '1' ? 'stoppuhr' : ($num('min') !== '' ? 'timer ' . $num('min') . ' min' : '');
        case 'countdown':
            if ($g('named') !== '' && preg_match('/^[a-z]+$/', $g('named'))) return 'countdown ' . $g('named');
            return $date('d') !== '' ? 'countdown ' . $date('d') : '';
        case 'datum':
            $d1 = $date('d1');
            if ($d1 === '') return '';
            if ($g('m') === 'age') return 'alter ' . $d1;
            if ($g('m') === 'add') {
                $u = in_array($g('u'), ['tage', 'wochen', 'monate', 'jahre'], true) ? $g('u') : 'tage';
                return $d1 . ' ' . ($g('sign') === '-' ? '-' : '+') . ' ' . (int)$g('k', '0') . ' ' . $u;
            }
            return $date('d2') !== '' ? 'tage zwischen ' . $d1 . ' und ' . $date('d2') : '';
        case 'feiertage':
            $st = smStates()[$g('st')] ?? '';
            return 'feiertage ' . max(1995, min(2100, (int)$g('y', date('Y')))) . ($st !== '' ? ' ' . mb_strtolower($st) : '');
        case 'kalender':
            if ($g('now') === '1') return 'datum';
            return 'kw' . ((int)$g('w') > 0 ? ' ' . (int)$g('w') . ' ' . max(1970, min(2100, (int)$g('y', date('Y')))) : '');
        case 'zufall':
            if ($g('z') === 'coin') return 'münze';
            if ($g('z') === 'random') return 'zufallszahl ' . (int)$g('ra', '1') . '-' . (int)$g('rb', '100');
            return $g('z') === 'dice' ? 'würfel ' . max(1, min(20, (int)$g('dn', '1'))) . 'w' . max(2, min(1000, (int)$g('df', '6'))) : '';
        case 'losen':
            $names = $g('names');
            if ($names === '') return '';
            $k = max(1, (int)$g('k', '1'));
            $head = ['teams' => 'teams ' . max(2, $k), 'order' => 'reihenfolge'][$g('lm')] ?? 'losen ' . $k;
            return $head . ': ' . str_replace(["\r\n", "\r"], "\n", $names);
        case 'wetter':   return $g('ort') !== '' ? 'wetter ' . mb_substr($g('ort'), 0, 60) : '';
        case 'ip':       return 'meine ip';
    }
    return '';
}
