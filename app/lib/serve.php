<?php
/* Suchmaschine – Dateien ausliefern: CSS, JavaScript, Symbole, OpenSearch, Manifest, Logo, App-Symbol */
defined('SM_VERSION') || exit;

function smServeAsset(): void
{
    $types = ['app.css' => 'text/css; charset=utf-8', 'app.js' => 'text/javascript; charset=utf-8', 'pdf.js' => 'text/javascript; charset=utf-8', 'icon.svg' => 'image/svg+xml',
              'icon-180.png' => 'image/png', 'icon-192.png' => 'image/png', 'icon-512.png' => 'image/png'];
    $f = isset($_GET['f']) && is_string($_GET['f']) ? $_GET['f'] : '';
    // Töne für Timer und Co. (selbst erzeugt, siehe app/assets/sounds/LIESMICH.txt)
    if (preg_match('~^sounds/[a-z]{2,20}\.mp3$~', $f)) $types[$f] = 'audio/mpeg';
    // Werkzeuge: Bausteine aus CodeForge (cf/), eigene Apps (tools/), Scan-Engine (vendor/)
    if (preg_match('~^(cf|tools)/[a-z0-9]{2,20}\.js$~', $f)) $types[$f] = 'text/javascript; charset=utf-8';
    if ($f === 'tools.css') $types[$f] = 'text/css; charset=utf-8';
    if ($f === 'vendor/zxing.js') $types[$f] = 'text/javascript; charset=utf-8';
    if ($f === 'vendor/zxing.wasm') $types[$f] = 'application/wasm';
    if (!isset($types[$f]) || !is_file(SM_APP . '/assets/' . $f)) {
        http_response_code(404);
        exit;
    }
    smHeaders();
    // Der Web Worker der Scan-Engine lädt zxing.js und die WebAssembly selbst, dafür braucht er eigene Regeln
    if ($f === 'cf/worker.js') header("Content-Security-Policy: default-src 'none'; script-src 'self' 'wasm-unsafe-eval'; connect-src 'self'");
    header('Content-Type: ' . $types[$f]);
    header('Cache-Control: ' . (isset($_GET['v']) ? 'public, max-age=31536000, immutable' : 'no-cache'));
    readfile(SM_APP . '/assets/' . $f);
    exit;
}

/**
 * ?a=printcss&s=A6&m=0&one=1: Stylesheet für den Druckrahmen der QR-Werkzeuge. s = Papierformat (A3 bis A6, auto oder „62mm 29mm“),
 * m = Rand in mm, one = ein einzelner Code mittig statt ganzer Seiten.
 */
function smServePrintCss(): void
{
    $s = is_string($_GET['s'] ?? null) ? trim($_GET['s']) : 'auto';
    if (!preg_match('/^(A[3-6]|auto|\d{1,3}(\.\d{1,2})?mm \d{1,3}(\.\d{1,2})?mm)$/', $s)) $s = 'auto';
    // A6 kennt CSS nicht als Namen (nur A3 bis A5): Browser verwerfen die Angabe sonst und nehmen das Standardpapier
    if ($s === 'A6') $s = '105mm 148mm';
    $m = is_string($_GET['m'] ?? null) ? (int)$_GET['m'] : 0;
    $m = max(0, min(30, $m));
    $one = !empty($_GET['one']);
    smHeaders();
    header('Content-Type: text/css; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    echo '@page { size: ' . $s . '; margin: ' . $m . "mm; }\n"
        . "html, body { margin: 0; padding: 0; background: #fff; }\n"
        . "* { -webkit-print-color-adjust: exact; print-color-adjust: exact; color-adjust: exact; box-sizing: border-box; }\n"
        . ($one
            ? "body { display: flex; align-items: center; justify-content: center; height: 100vh; }\nsvg { display: block; width: 70%; height: auto; max-height: 90vh; }\n"
            : "svg { display: block; width: 100vw; height: 100vh; overflow: hidden; break-after: page; page-break-after: always; break-inside: avoid; }\n"
              . "svg:last-child { break-after: auto; page-break-after: auto; }\n");
    exit;
}

function smServeOpenSearch(): void
{
    smHeaders();
    header('Content-Type: application/opensearchdescription+xml; charset=utf-8');
    header('Cache-Control: ' . (isset($_GET['k']) ? 'private, no-store' : 'no-cache'));
    $name = smSiteName();
    // Persönliche Variante mit Login-Token: der Browser meldet sich bei jeder Suche aus der Adressleiste selbst an
    $k = isset($_GET['k']) && is_string($_GET['k']) && preg_match('/^[A-Za-z0-9._~-]{32,200}$/', $_GET['k']) ? $_GET['k'] : '';
    $tpl = smOrigin() . '?' . ($k !== '' ? 'token=' . rawurlencode($k) . '&' : '') . 'q={searchTerms}';
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
       . '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">' . "\n"
       . '  <ShortName>' . h(mb_substr($name, 0, 16)) . "</ShortName>\n"
       . '  <Description>' . h(t('Eigene Suchmaschine ohne Werbung')) . "</Description>\n"
       . "  <InputEncoding>UTF-8</InputEncoding>\n"
       . '  <Image type="image/svg+xml">' . h(smOrigin() . '?a=asset&f=icon.svg') . "</Image>\n"
       . '  <Url type="text/html" method="get" template="' . h($tpl) . "\"/>\n"
       . "</OpenSearchDescription>\n";
    exit;
}

function smServeManifest(): void
{
    smHeaders();
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: no-cache');
    $name = smSiteName();
    $n = smHasUsersSafe() ? trim(smSetting('site_name')) : '';
    // Startbildschirm und Leiste der App in den Farben des gewählten Designs (dunkel nur, wenn es fest auf dunkel steht)
    $bg = smThemeColor() ?? '#ffffff';
    $sc = function (string $label, array $params): array {
        return ['name' => $label, 'url' => './' . ($params ? '?' . http_build_query($params) : ''),
                'icons' => [['src' => smAppIconUrl(192), 'sizes' => '192x192', 'type' => 'image/png']]];
    };
    echo json_encode([
        'id' => './', 'name' => $name, 'short_name' => $n !== '' ? mb_substr($n, 0, 12) : t('Suchmaschine'),
        'start_url' => './', 'scope' => './', 'display' => 'standalone',
        // Langer Druck auf das App-Symbol: direkt zu Bildern, News, Werkzeugen und zum Scanner
        'shortcuts' => [$sc(t('Bilder suchen'), ['t' => 'images']), $sc(t('News suchen'), ['t' => 'news']),
                        $sc(t('Werkzeuge'), ['a' => 'tools']), $sc(t('QR-Code scannen'), ['a' => 'tools', 'tool' => 'scanner'])],
        // Am Handy im Teilen-Menü: geteilte Adressen landen bei den Kurzlinks
        'share_target' => ['action' => './', 'method' => 'GET', 'params' => ['title' => 'share_title', 'text' => 'share_text', 'url' => 'share_url']],
        'lang' => smLang(), 'background_color' => $bg, 'theme_color' => $bg,
        // PNG-Symbole: Startbildschirme von Android und iPhone nehmen kein SVG
        'icons' => [
            ['src' => smAppIconUrl(192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => smAppIconUrl(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => smAppIconUrl(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Farbe für die Statusleiste des Handys (theme-color): Hintergrund des fest gewählten Designs, bei „automatisch“ null
 * (dann gibt es je eine Angabe für hell und dunkel). Eigene Farben aus „Aussehen“ setzt app.js nach dem Laden genau.
 */
function smThemeColor(): ?string
{
    $t = smTheme();
    return $t === 'dark' ? '#1f1f1f' : ($t === 'light' ? '#ffffff' : null);
}

/**
 * ?a=sw: Service Worker der App (app/assets/sw.js). Zeigt ohne Netz die Seite „Keine Verbindung“ und hält Aussehen,
 * Skripte und Symbole auf dem Gerät. Die Adresse liegt im Ordner der Suchmaschine, damit er nur für sie gilt.
 */
function smServeSw(): void
{
    $js = (string)file_get_contents(SM_APP . '/assets/sw.js');
    $first = [smAsset('app.css'), smAsset('app.js'), smUrl(['a' => 'asset', 'f' => 'icon.svg']), smLookUrl(smLook(null))];
    $js = strtr($js, [
        '__SM_VERSION__' => SM_VERSION,
        '__SM_OFFLINE__' => smUrl(['a' => 'offline']),
        '__SM_FIRST__'   => json_encode($first, JSON_UNESCAPED_SLASHES),
    ]);
    smHeaders();
    // Der Service Worker ruft Seiten und Dateien der Suchmaschine selbst ab, dafür braucht er connect-src
    header("Content-Security-Policy: default-src 'none'; connect-src 'self'");
    header('Content-Type: text/javascript; charset=utf-8');
    header('Cache-Control: no-cache');
    header('Service-Worker-Allowed: ' . smBase());
    echo $js;
    exit;
}

/** ?a=offline: Seite „Keine Verbindung“, die der Service Worker auf dem Gerät ablegt (für alle gleich, nichts Persönliches) */
function smServeOffline(): void
{
    smRender('offline', ['title' => t('Keine Verbindung') . ' – ' . smSiteName(), 'bare' => true, 'me' => null, 'offline' => true]);
    exit;
}

/** Eigenes Logo ausliefern (liegt in data/, darum über diese Datei) */
function smServeLogo(): void
{
    $type = smHasUsersSafe() ? smSetting('logo_type') : '';
    $file = SM_DATA_DIR . '/logo.bin';
    if ($type === '' || !is_file($file)) {
        http_response_code(404);
        exit;
    }
    smHeaders();
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
    header('Content-Type: ' . $type);
    header('Cache-Control: ' . (isset($_GET['v']) ? 'public, max-age=31536000, immutable' : 'no-cache'));
    readfile($file);
    exit;
}

/** App-Symbol ausliefern: eigenes (verkleinert, falls GD da ist) oder das eingebaute */
function smServeAppIcon(): void
{
    $s = (int)($_GET['s'] ?? 192);
    if (!in_array($s, SM_ICON_SIZES, true)) $s = 192;
    $file = SM_APP . '/assets/icon-' . $s . '.png';
    if (smHasUsersSafe() && smAppIconCustom()) {
        $file = smAppIconFile();
        if ($s !== 512 && function_exists('imagecreatefromstring')) {
            $small = SM_DATA_DIR . '/appicon-' . $s . '.png';
            if (!is_file($small) && ($src = @imagecreatefromstring((string)file_get_contents($file)))) {
                $dst = imagecreatetruecolor($s, $s);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $s, $s, imagesx($src), imagesy($src));
                @imagepng($dst, $small, 9);
            }
            if (is_file($small)) $file = $small;
        }
    }
    smHeaders();
    header('Content-Type: image/png');
    header('Cache-Control: ' . (isset($_GET['v']) ? 'public, max-age=31536000, immutable' : 'no-cache'));
    readfile($file);
    exit;
}
