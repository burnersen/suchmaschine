<?php
/* Suchmaschine – Seite Werkzeuge: Übersicht, einzelne Werkzeuge, Schnittstelle der Werkzeug-Apps */
defined('SM_VERSION') || exit;

/** Grundbausteine aus CodeForge, die QR-Studio, Scanner, Druck und Nummern-Detektiv brauchen */
const SM_CF_CORE = ['cf/qr', 'cf/render', 'cf/bars', 'cf/numbers', 'cf/gs1', 'cf/ticket', 'cf/parse', 'cf/store', 'cf/engine', 'cf/print'];

/**
 * Alle Werkzeuge nach Gruppen: id => [Symbol, Name, kurze Beschreibung, Optionen].
 * Optionen: 'app' => JavaScript-Bausteine (ohne .js), 'login' => nur angemeldet (ruft fremde Server ab),
 *           'wasm' => Scan-Engine (WebAssembly), 'cam' => Kamera, 'kw' => Suchwörter für die Übersicht, 'new' => neu in 2.0
 * Die id steht in der Adresse (?a=tools&tool=rechner). Werkzeuge ohne 'app' haben ein Formular in app/views/tools.php.
 */
function smToolGroups(): array
{
    $cf = SM_CF_CORE;
    return [
        t('QR-Codes und Scanner') => [
            'qr'        => ['🔳', t('QR-Studio'), t('WLAN, Kontakt, GiroCode, Termin und mehr, mit Farben, Logo und Lesetest'), ['app' => array_merge($cf, ['cf/create']), 'wasm' => true, 'new' => true, 'kw' => 'qr code generator barcode wlan wifi vcard girocode design logo']],
            'scanner'   => ['📷', t('Scanner'), t('QR- und Barcodes mit Kamera oder aus Bildern lesen, mit Link-Röntgen'), ['app' => array_merge($cf, ['cf/scan']), 'wasm' => true, 'cam' => true, 'new' => true, 'kw' => 'scan kamera webcam barcode ean isbn lesen quishing']],
            'qrdruck'   => ['🖨️', t('QR drucken'), t('WLAN-Schild, Tischkarte, Visitenkarte, Etiketten mit Seriennummern'), ['app' => array_merge($cf, []), 'new' => true, 'kw' => 'drucken etiketten aufsteller visitenkarte avery herma label print']],
            'nummern'   => ['🔢', t('Nummern-Detektiv'), t('IBAN, EAN, ISBN, IMEI prüfen, mit Prüfziffer und Zahlendreher-Suche'), ['app' => array_merge($cf, ['cf/convert']), 'wasm' => true, 'new' => true, 'kw' => 'iban ean isbn imei prüfziffer gtin upc pzn kreditkarte']],
        ],
        t('Sicherheit und Teilen') => [
            'passwort'  => ['🔑', t('Passwort-Generator'), t('Passwörter, Passphrasen, PINs und Tokens, mit Stärke-Check'), ['app' => ['tools/words', 'tools/pw'], 'kw' => 'passwort password passphrase pin sicher generator stärke entropie']],
            'kurzlinks' => ['🔗', t('Kurzlinks'), t('Lange Adressen kurz machen, mit Wunschname, Ablauf und Statistik'), ['app' => ['cf/qr', 'cf/render', 'tools/share'], 'kw' => 'kurzlink shortener url kürzen link qr dynamisch']],
            'schnipsel' => ['📝', t('Text-Schnipsel'), t('Verschlüsselte Notiz per Link, auf Wunsch nach dem Lesen weg'), ['app' => ['cf/qr', 'cf/render', 'tools/share'], 'new' => true, 'kw' => 'notiz einmal geheim verschlüsselt teilen burn after reading pastebin']],
        ],
        t('Rechnen und Umrechnen') => [
            'rechner'   => ['🧮', t('Rechner'), t('Rechnet beim Tippen, mit Verlauf und wissenschaftlichen Funktionen'), ['app' => ['tools/calc'], 'kw' => 'rechner taschenrechner rechnen sinus wurzel']],
            'prozent'   => ['💯', t('Prozent und MwSt'), t('Brutto/Netto, Rabatt, Prozent-Veränderung, Dreisatz'), ['app' => ['tools/calc'], 'new' => true, 'kw' => 'prozent mwst mehrwertsteuer brutto netto rabatt dreisatz']],
            'einheiten' => ['📏', t('Einheiten'), t('Länge, Gewicht, Temperatur, Verbrauch und mehr, live'), ['app' => ['tools/calc'], 'kw' => 'einheiten umrechnen meter meilen kilo pfund grad']],
            'waehrung'  => ['💶', t('Währungen'), t('Tageskurse der EZB, mehrere Währungen, Kursverlauf'), ['app' => ['tools/calc'], 'kw' => 'währung euro dollar kurs wechselkurs ezb']],
            'finanzen'  => ['📈', t('Zinsen, Sparplan, Kredit'), t('Zinseszins, monatlich sparen, Kreditrate und Tilgungsplan'), ['app' => ['tools/calc'], 'new' => true, 'kw' => 'zinsen zinseszins sparplan kredit rate tilgung darlehen']],
            'kosten'    => ['⛽', t('Sprit und Rechnung teilen'), t('Fahrtkosten, Kosten pro Person, Trinkgeld'), ['app' => ['tools/calc'], 'new' => true, 'kw' => 'sprit benzin fahrtkosten verbrauch rechnung teilen trinkgeld']],
        ],
        t('Zeit und Datum') => [
            'timer'     => ['⏱️', t('Timer und Stoppuhr'), t('Mehrere Timer, Pomodoro, Intervalle, Runden, Wecker'), ['app' => ['tools/time'], 'kw' => 'timer stoppuhr wecker pomodoro intervall countdown']],
            'countdown' => ['⏳', t('Countdown'), t('Bis zu deinem Termin, mit Titel, Vollbild und Link'), ['app' => ['tools/time'], 'kw' => 'countdown weihnachten silvester tage bis']],
            'datum'     => ['📅', t('Datumsrechner und Alter'), t('Tage, Arbeitstage, plus und minus, Wochentag'), ['kw' => 'datum tage zwischen arbeitstage alter wochentag']],
            'feiertage' => ['🎉', t('Feiertage und Brückentage'), t('Nach Bundesland, als Kalenderdatei, mit Brückentage-Planer'), ['kw' => 'feiertage brückentage urlaub bundesland ics kalender']],
            'kalender'  => ['🗓️', t('Kalenderwoche und Jahreskalender'), t('KW heute, KW suchen, Jahreskalender zum Drucken'), ['kw' => 'kalenderwoche kw jahreskalender drucken']],
            'weltzeit'  => ['🌍', t('Weltzeituhr'), t('Uhrzeit in anderen Städten, Termin-Planer über Zeitzonen'), ['app' => ['tools/time'], 'new' => true, 'kw' => 'weltzeit zeitzone uhrzeit new york tokio meeting']],
        ],
        t('Netz und Web') => [
            'ip'        => ['🌐', t('Meine IP und was Seiten sehen'), t('IP-Adresse, Browser, Sprache, Bildschirm, WebRTC'), ['app' => ['tools/net'], 'kw' => 'ip adresse browser fingerprint user agent webrtc']],
            'down'      => ['🚦', t('Ist die Seite down?'), t('Erreichbar oder nur bei dir gestört?'), ['app' => ['tools/net'], 'login' => true, 'new' => true, 'kw' => 'down erreichbar online offline störung status']],
            'header'    => ['📨', t('Header und Weiterleitungen'), t('HTTP-Kopfzeilen, Weiterleitungskette, Sicherheits-Check'), ['app' => ['tools/net'], 'login' => true, 'new' => true, 'kw' => 'http header weiterleitung redirect sicherheit hsts csp']],
            'ssl'       => ['🔒', t('SSL-Zertifikat'), t('Gültig bis wann, von wem, für welche Namen'), ['app' => ['tools/net'], 'login' => true, 'new' => true, 'kw' => 'ssl tls zertifikat https ablauf lets encrypt']],
            'dns'       => ['🧭', t('DNS-Abfrage'), t('A, AAAA, MX, TXT, NS, CAA, dazu SPF und DMARC'), ['app' => ['tools/net'], 'login' => true, 'new' => true, 'kw' => 'dns mx txt spf dmarc nameserver']],
            'whois'     => ['🪪', t('WHOIS'), t('Wem gehört eine Domain oder IP? Über RDAP'), ['app' => ['tools/net'], 'login' => true, 'new' => true, 'kw' => 'whois rdap domain registrar ablauf inhaber ip netz']],
            'linkcheck' => ['🩺', t('Link-Checker'), t('Viele Links auf einmal prüfen: tot, umgeleitet, ok'), ['app' => ['tools/net'], 'login' => true, 'new' => true, 'kw' => 'link check tote links 404 weiterleitung']],
            'vorschau'  => ['🖼️', t('Link-Vorschau'), t('So sieht dein Link in Messengern und sozialen Netzen aus'), ['app' => ['tools/net'], 'login' => true, 'new' => true, 'kw' => 'link vorschau open graph og image whatsapp twitter meta']],
            'lesezeichen' => ['🔖', t('Lesezeichen aufräumen'), t('Browser-Export hochladen, Doppelte und tote Links finden'), ['app' => ['tools/net'], 'new' => true, 'kw' => 'lesezeichen bookmarks favoriten doppelt tot aufräumen export']],
        ],
        t('Für Entwickler') => [
            'json'      => ['🧾', t('JSON'), t('Formatieren, prüfen, verkleinern, nach CSV'), ['app' => ['tools/dev'], 'new' => true, 'kw' => 'json formatieren validieren minify csv']],
            'kodieren'  => ['🔁', t('Kodieren und Dekodieren'), t('Base64, URL, HTML, Hex, Binär, Unicode'), ['app' => ['tools/dev'], 'new' => true, 'kw' => 'base64 url encode decode html entities hex']],
            'hash'      => ['#️⃣', t('Hash'), t('SHA-256, SHA-512, SHA-1, MD5, HMAC, auch von Dateien'), ['app' => ['tools/dev'], 'new' => true, 'kw' => 'hash sha256 md5 prüfsumme checksumme hmac datei']],
            'jwt'       => ['🎫', t('JWT-Decoder'), t('Token aufschlüsseln, Ablauf und Inhalt anzeigen'), ['app' => ['tools/dev'], 'new' => true, 'kw' => 'jwt token json web token decode']],
            'uuid'      => ['🆔', t('UUID und IDs'), t('UUID v4 und v7, ULID, Nano-ID, viele auf einmal'), ['app' => ['tools/dev'], 'new' => true, 'kw' => 'uuid guid ulid nanoid id generator']],
            'regex'     => ['🔍', t('Regex-Tester'), t('Muster testen, Treffer und Gruppen markiert, Ersetzen'), ['app' => ['tools/dev'], 'new' => true, 'kw' => 'regex regulärer ausdruck pattern test ersetzen']],
            'cron'      => ['⏰', t('Cron-Erklärer'), t('Cron-Ausdruck in Klartext, die nächsten Läufe'), ['app' => ['tools/dev'], 'new' => true, 'kw' => 'cron crontab zeitplan erklären']],
            'diff'      => ['🆚', t('Text vergleichen'), t('Unterschiede zwischen zwei Texten, zeilen- und wortweise'), ['app' => ['tools/dev'], 'new' => true, 'kw' => 'diff vergleichen unterschiede text']],
            'farben'    => ['🎨', t('Farben'), t('HEX, RGB, HSL umrechnen, Kontrast nach WCAG, Paletten'), ['app' => ['tools/dev'], 'new' => true, 'kw' => 'farbe hex rgb hsl kontrast wcag palette']],
            'zahlen'    => ['🔣', t('Zahlensysteme'), t('Binär, Oktal, Dezimal, Hex, beliebige Basis'), ['app' => ['tools/dev'], 'new' => true, 'kw' => 'binär hex oktal dezimal basis zahlensystem']],
            'subnetz'   => ['🕸️', t('Subnetz-Rechner'), t('IPv4 und IPv6, CIDR, Netz, Broadcast, Bereich'), ['app' => ['tools/dev'], 'new' => true, 'kw' => 'subnetz cidr netzmaske ipv4 ipv6 broadcast']],
            'chmod'     => ['🛡️', t('chmod-Rechner'), t('Dateirechte als Zahl und als rwx'), ['app' => ['tools/dev'], 'new' => true, 'kw' => 'chmod rechte rwx 755 644 linux']],
            'htpasswd'  => ['🧱', t('.htpasswd-Generator'), t('Zugangsschutz für Ordner auf dem Webspace'), ['app' => ['tools/dev'], 'login' => true, 'new' => true, 'kw' => 'htpasswd htaccess passwortschutz apache bcrypt']],
            'textwerk'  => ['✂️', t('Text-Werkzeuge'), t('Zählen, Groß/klein, Slug, sortieren, Duplikate, Lorem ipsum'), ['app' => ['tools/dev'], 'new' => true, 'kw' => 'text zeichen zählen wörter slug sortieren duplikate lorem ipsum']],
            'zeitstempel' => ['🕰️', t('Unix-Zeitstempel'), t('Zeitstempel und Datum hin und her, ISO 8601'), ['app' => ['tools/dev'], 'new' => true, 'kw' => 'unix timestamp zeitstempel epoch iso 8601']],
        ],
        t('Alltag und Zufall') => [
            'zufall'    => ['🎲', t('Würfel, Münze, Lotto'), t('Echter Zufall vom Server, Lottozahlen, Karte ziehen, Ja/Nein'), ['kw' => 'würfel münze zufall zufallszahl lotto eurojackpot karte']],
            'wetter'    => ['🌦️', t('Wetter'), t('Vorhersage, Stunden, 7 Tage und Unwetterwarnungen'), ['kw' => 'wetter vorhersage regen warnung dwd']],
            'roemisch'  => ['🏛️', t('Römische Zahlen'), t('Hin und her, von I bis MMMCMXCIX'), ['app' => ['tools/calc'], 'new' => true, 'kw' => 'römische zahlen roman numerals']],
            'morse'     => ['📡', t('Morsecode'), t('Text in Morse und zurück, mit Ton und Licht'), ['app' => ['tools/calc'], 'new' => true, 'kw' => 'morse morsecode sos']],
        ],
    ];
}

/** Ein Werkzeug: [Gruppe, Symbol, Name, Beschreibung, Optionen] oder null */
function smToolInfo(string $id): ?array
{
    foreach (smToolGroups() as $g => $items) if (isset($items[$id])) return array_merge([$g], $items[$id]);
    return null;
}

/** Adresse eines Werkzeugs */
function smToolUrl(string $id, array $more = []): string
{
    return smUrl(['a' => 'tools', 'tool' => $id !== '' ? $id : null] + $more);
}

/** Werkzeuge: Übersicht, ein einzelnes Werkzeug oder die Schnittstelle der Apps (?api=…) */
function smPageTools(?array $me, bool $post): void
{
    if (isset($_GET['api']) && is_string($_GET['api'])) {
        if (!$post) { http_response_code(405); exit; }
        smToolsApi($me, $_GET['api']);
    }
    if ($post && $me) {
        // Ohne JavaScript: Kurzlink anlegen oder löschen
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'short_add') {
            if (smSetting('shortener') !== '1') {
                smFlash('error', t('Kurzlinks sind ausgeschaltet.'));
            } else {
                [$code, $err] = smShortCreate($me['id'], (string)($_POST['url'] ?? ''), ['strip' => !empty($_POST['strip']), 'code' => (string)($_POST['code'] ?? ''), 'title' => 1]);
                if ($err !== '') smFlash('error', $err); else smFlash('ok', t('Kurzlink: %s', smShortUrl($code)));
                if ($code !== '') smRedirect(smToolUrl('kurzlinks', ['new' => $code]));
            }
        } elseif ($do === 'short_del') {
            smShortDelete($me['id'], (string)($_POST['code'] ?? ''));
            smFlash('ok', t('Kurzlink gelöscht.'));
        }
        smRedirect(smToolUrl('kurzlinks'));
    }
    $tool = isset($_GET['tool']) && is_string($_GET['tool']) && smToolInfo($_GET['tool']) !== null ? $_GET['tool'] : '';
    // Ältere Adressen (?a=tools&qr=…, ?a=tools&new=…, ?a=tools&tool=losen) öffnen das passende Werkzeug
    if ($tool === '' && isset($_GET['qr']) && is_string($_GET['qr'])) $tool = 'qr';
    if ($tool === '' && isset($_GET['new'])) $tool = 'kurzlinks';
    if (isset($_GET['tool']) && $tool === '' && $_GET['tool'] === 'losen') smRedirect(smUrl(['a' => 'tools']));
    // Feiertage als Kalenderdatei
    if ($tool === 'feiertage' && ($_GET['ics'] ?? '') === '1') smServeHolidayIcs((int)($_GET['y'] ?? date('Y')), (string)($_GET['st'] ?? ''));
    $card = null;
    $err = '';
    if ($tool === 'waehrung' && ($_GET['fx_refresh'] ?? '') === '1') smFxRates(true);
    $info = $tool !== '' ? smToolInfo($tool) : null;
    if ($tool !== '' && empty($info[4]['app']) || in_array($tool, ['rechner', 'einheiten', 'waehrung', 'timer', 'countdown', 'ip'], true)) {
        // Werkzeuge mit Formular: aus den Feldern die Anfrage bauen und mit derselben Rechnung wie im Suchfeld beantworten
        if ($tool === 'wetter') $GLOBALS['sm_weather_more'] = true;
        $ia = isset($_GET['ia']) && is_string($_GET['ia']) ? mb_substr(trim($_GET['ia']), 0, 3000) : smToolQuery($tool);
        $_GET['ia'] = $ia;
        if ($ia !== '') {
            $card = smInstant($ia, $me);
            if ($card === null) $err = t('Das hat nicht geklappt. Bitte die Eingaben prüfen.');
            elseif (($card['type'] ?? '') === 'weather' && ($card['error'] ?? '') !== '' && $card['error'] !== 'noplace') $err = (string)$card['error'];
        }
    }
    // Apps: Bausteine, Scan-Engine (WebAssembly), Kamera
    $scripts = [];
    if ($info && !empty($info[4]['app'])) {
        $scripts[] = 'cf/ui.js';
        foreach ($info[4]['app'] as $m) $scripts[] = $m . '.js';
        $scripts[] = 'cf/boot.js';
        if (smLang() === 'en') array_unshift($scripts, 'cf/en.js', 'tools/en.js');
        $scripts = array_values(array_unique($scripts));
        if (!empty($info[4]['wasm'])) $GLOBALS['sm_csp_wasm'] = true;
    }
    if ($info && !empty($info[4]['cam'])) $GLOBALS['sm_camera'] = true;   // Kamera nur für den Scanner
    $boot = [
        'v' => SM_VERSION, 'lang' => smLang(), 'base' => smBase(), 'origin' => smOrigin(), 'tool' => $tool,
        'csrf' => $me['csrf'] ?? '', 'user' => $me ? ['name' => $me['name'], 'admin' => (bool)$me['is_admin']] : null,
        'login' => smUrl(['a' => 'login', 'next' => smSelf()]), 'search' => smBase() . '?q=',
        'settings' => ['resolve' => (bool)$me, 'shortener' => smSetting('shortener') === '1'],
    ];
    if ($tool === 'waehrung') $boot['data'] = smToolData($tool);
    if ($tool === 'ip') $boot['data'] = smToolIpData();
    smRender('tools', ['me' => $me, 'prefs' => smPrefs($me), 'title' => ($info ? $info[2] . ' – ' : '') . t('Werkzeuge') . ' – ' . smSiteName(),
        'new' => isset($_GET['new']) && is_string($_GET['new']) && preg_match('/^[A-Za-z0-9_-]{3,40}$/', $_GET['new']) ? $_GET['new'] : '',
        'tool' => $tool, 'info' => $info, 'card' => $card, 'toolErr' => $err, 'scripts' => $scripts, 'styles' => $info || $tool === '' ? ['tools.css'] : [], 'cfBoot' => $boot,
        'qr' => isset($_GET['qr']) && is_string($_GET['qr']) ? mb_substr($_GET['qr'], 0, SM_QR_MAX) : '']);
}

/** Daten für die Live-Werkzeuge im Browser (rechnen ohne Server) */
function smToolData(string $tool): array
{
    if ($tool === 'waehrung') {
        $fx = smFxRates();
        return ['rates' => $fx['rates'] + ['EUR' => 1.0], 'date' => $fx['date'] ?? '', 'fetched' => $fx['fetched'] ?? 0];
    }
    if ($tool === 'einheiten') {
        $u = [];
        foreach (smUnits() as $k => $row) $u[$k] = $row;
        return ['units' => $u];
    }
    return [];
}

/** Was der Server über den Besucher sieht (für „Was Seiten sehen“) */
function smToolIpData(): array
{
    $h = [];
    foreach (['HTTP_USER_AGENT' => 'User-Agent', 'HTTP_ACCEPT_LANGUAGE' => 'Accept-Language', 'HTTP_ACCEPT' => 'Accept', 'HTTP_ACCEPT_ENCODING' => 'Accept-Encoding',
              'HTTP_DNT' => 'DNT', 'HTTP_SEC_GPC' => 'Sec-GPC', 'HTTP_SEC_CH_UA' => 'Sec-CH-UA', 'HTTP_SEC_CH_UA_PLATFORM' => 'Sec-CH-UA-Platform', 'HTTP_SEC_CH_UA_MOBILE' => 'Sec-CH-UA-Mobile',
              'HTTP_VIA' => 'Via', 'HTTP_X_FORWARDED_FOR' => 'X-Forwarded-For'] as $k => $n) {
        if (isset($_SERVER[$k]) && is_string($_SERVER[$k]) && $_SERVER[$k] !== '') $h[] = [$n, mb_substr($_SERVER[$k], 0, 500)];
    }
    $ip = smClientIp();
    $host = $ip !== '' ? @gethostbyaddr($ip) : '';
    return ['ip' => $ip, 'v6' => strpos($ip, ':') !== false, 'host' => $host && $host !== $ip ? $host : '', 'headers' => $h, 'https' => smIsHttps(),
            'proto' => (string)($_SERVER['SERVER_PROTOCOL'] ?? ''), 'other' => smIpOtherSources(strpos($ip, ':') !== false)];
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
            if ($g('m') === 'weekday') return 'wochentag ' . $d1;
            if ($g('m') === 'work') return $date('d2') !== '' ? 'arbeitstage zwischen ' . $d1 . ' und ' . $date('d2') . ($g('st') !== '' && isset(smStates()[$g('st')]) ? ' ' . mb_strtolower(smStates()[$g('st')]) : '') : '';
            if ($g('m') === 'add') {
                $u = in_array($g('u'), ['tage', 'wochen', 'monate', 'jahre', 'arbeitstage'], true) ? $g('u') : 'tage';
                return $d1 . ' ' . ($g('sign') === '-' ? '-' : '+') . ' ' . (int)$g('k', '0') . ' ' . $u;
            }
            return $date('d2') !== '' ? 'tage zwischen ' . $d1 . ' und ' . $date('d2') : '';
        case 'feiertage':
            $st = smStates()[$g('st')] ?? '';
            if ($g('m') === 'bridge') return 'brückentage ' . max(1995, min(2100, (int)$g('y', date('Y')))) . ($st !== '' ? ' ' . mb_strtolower($st) : '');
            return 'feiertage ' . max(1995, min(2100, (int)$g('y', date('Y')))) . ($st !== '' ? ' ' . mb_strtolower($st) : '');
        case 'kalender':
            if ($g('now') === '1') return 'datum';
            if ($g('year') === '1') return 'jahreskalender ' . max(1970, min(2100, (int)$g('y', date('Y'))));
            return 'kw' . ((int)$g('w') > 0 ? ' ' . (int)$g('w') . ' ' . max(1970, min(2100, (int)$g('y', date('Y')))) : '');
        case 'zufall':
            if ($g('z') === 'coin') return 'münze';
            if ($g('z') === 'random') return 'zufallszahl ' . (int)$g('ra', '1') . '-' . (int)$g('rb', '100');
            if ($g('z') === 'lotto') return 'lotto';
            if ($g('z') === 'euro') return 'eurojackpot';
            if ($g('z') === 'card') return 'karte ziehen';
            if ($g('z') === 'yesno') return 'ja oder nein';
            return $g('z') === 'dice' ? 'würfel ' . max(1, min(20, (int)$g('dn', '1'))) . 'w' . max(2, min(1000, (int)$g('df', '6'))) : '';
        case 'wetter':   return $g('ort') !== '' ? 'wetter ' . mb_substr($g('ort'), 0, 60) : '';
        case 'ip':       return 'meine ip';
    }
    return '';
}

/** Feiertage eines Jahres als Kalenderdatei (.ics) */
function smServeHolidayIcs(int $y, string $st): void
{
    $y = max(1995, min(2100, $y));
    $c = smInstant('feiertage ' . $y . (isset(smStates()[$st]) ? ' ' . mb_strtolower(smStates()[$st]) : ''), null);
    $esc = function (string $s): string { return addcslashes($s, ",;\\"); };
    $out = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Suchmaschine//Feiertage//DE\r\nCALSCALE:GREGORIAN\r\nX-WR-CALNAME:" . $esc(t('Feiertage %d', $y) . (isset(smStates()[$st]) ? ' ' . smStates()[$st] : '')) . "\r\n";
    foreach ((array)($c['list'] ?? []) as $hd) {
        $d = date('Ymd', $hd['ts']);
        $out .= "BEGIN:VEVENT\r\nUID:" . $d . '-' . substr(sha1($hd['name']), 0, 10) . "@suchmaschine\r\nDTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\nDTSTART;VALUE=DATE:" . $d
              . "\r\nDTEND;VALUE=DATE:" . date('Ymd', $hd['ts'] + 86400 + 3600) . "\r\nSUMMARY:" . $esc($hd['name']) . "\r\n"
              . ($hd['where'] !== '' && $st === '' ? 'DESCRIPTION:' . $esc(t('nur in: %s', $hd['where'])) . "\r\n" : '')
              . "TRANSP:TRANSPARENT\r\nEND:VEVENT\r\n";
    }
    $out .= "END:VCALENDAR\r\n";
    smHeaders();
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="feiertage-' . $y . ($st !== '' && isset(smStates()[$st]) ? '-' . strtolower($st) : '') . '.ics"');
    header('Cache-Control: no-store');
    echo $out;
    exit;
}
