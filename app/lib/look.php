<?php
/* Suchmaschine – Aussehen: fertige Designs (hell und dunkel), eigene Farben aus dem Farbmischer, Schrift, Größen, Abstände, Breite.
   Die Designs und der Farbmischer stammen aus „Kaufen“. Gespeichert wird pro Konto, der Administrator legt den Standard fest.
   Die Farben kommen als kleine CSS-Datei vom eigenen Server (die strengen Sicherheitsregeln verbieten eingebettete Styles).
   Die Schriften liegen in app/assets/fonts/ und werden nur von hier geladen, nie von Google oder anderen. */
defined('SM_VERSION') || exit;

/**
 * Fertige Designs: vier Grundfarben (Hintergrund, Karten, Schrift, Akzent), wahlweise Linkfarbe.
 * Alles andere (Linien, Nebentext, Flächen) wird daraus berechnet.
 */
function smPalettes(): array
{
    return [
        'light' => [
            'standard' => [t('Klar'), '#ffffff', '#ffffff', '#1f1f1f', '#4f46e5', '#1a0dab'],
            'papier'   => [t('Papier'), '#f3eee5', '#faf7f1', '#2b2620', '#8a5a2b', '#6b3f12'],
            'nebel'    => [t('Nebel'), '#e3e6ea', '#eef0f3', '#1d2228', '#2f5f8a', '#1f4f86'],
            'salbei'   => [t('Salbei'), '#e5ebe4', '#f1f4f0', '#1f2a22', '#3d7a52', '#2a5f3e'],
            'tuerkis'  => [t('Türkis'), '#f3f6f5', '#ffffff', '#13201d', '#0f766e', ''],
            'ozean'    => [t('Ozean'), '#f2f5fa', '#ffffff', '#131c2b', '#2563eb', ''],
            'sand'     => [t('Sand'), '#f8f4ee', '#fffdfa', '#2a211a', '#c2410c', '#9a3412'],
            'beere'    => [t('Beere'), '#f9f3f6', '#ffffff', '#261a22', '#9d2463', ''],
            'graphit'  => [t('Graphit'), '#f4f4f5', '#ffffff', '#18181b', '#27272a', '#1d4ed8'],
        ],
        'dark' => [
            'standard'    => [t('Dunkel'), '#1f1f1f', '#303134', '#e8eaed', '#8ab4f8', '#99c3ff'],
            'schiefer'    => [t('Schiefer'), '#2b3036', '#353b42', '#e6e9ec', '#8fb8de', '#9cc4ea'],
            'daemmerung'  => [t('Dämmerung'), '#262a33', '#30353f', '#e1e5ec', '#a5b4fc', '#b4c2ff'],
            'lagune'      => [t('Lagune'), '#0f1720', '#18232e', '#e7eef3', '#2dd4bf', ''],
            'mitternacht' => [t('Mitternacht'), '#0d1322', '#161f33', '#e6ebf5', '#7aa7ff', ''],
            'glut'        => [t('Glut'), '#17120e', '#221b15', '#f1e9e0', '#fb923c', ''],
            'aubergine'   => [t('Aubergine'), '#150f1a', '#201828', '#efe7f3', '#f08bd0', ''],
            'schwarz'     => [t('Schwarz'), '#000000', '#111113', '#ededee', '#e4e4e7', '#9cc4ff'],
        ],
    ];
}

/** Fest eingebaute Schriften (alle SIL Open Font License, liegen in app/assets/fonts/) */
function smFonts(): array
{
    return [
        'system'   => [t('Systemschrift'), ''],
        'atkinson' => ['Atkinson Hyperlegible', 'atkinson-hyperlegible'],
        'lexend'   => ['Lexend', 'lexend'],
        'source'   => ['Source Sans 3', 'source-sans-3'],
        'inter'    => ['Inter', 'inter'],
        'literata' => [t('Literata (mit Serifen)'), 'literata'],
    ];
}

/** Standardwerte und erlaubte Bereiche */
function smLookDefaults(): array
{
    return ['mode' => 'auto', 'pl' => 'standard', 'pd' => 'standard', 'cl' => null, 'cd' => null, 'font' => 'system',
            'fs' => 100, 'ft' => 100, 'fd' => 100, 'lh' => 158, 'gap' => 'normal', 'width' => 'normal'];
}

/** Prüft und vervollständigt ein Aussehen (aus Formular, Konto oder Sicherung) */
function smLookClean($a): array
{
    $a = is_array($a) ? $a : [];
    $d = smLookDefaults();
    $o = [];
    $o['mode'] = in_array($a['mode'] ?? '', ['auto', 'light', 'dark'], true) ? $a['mode'] : $d['mode'];
    $pal = smPalettes();
    $o['pl'] = isset($pal['light'][$a['pl'] ?? '']) || ($a['pl'] ?? '') === 'eigen' ? $a['pl'] : $d['pl'];
    $o['pd'] = isset($pal['dark'][$a['pd'] ?? '']) || ($a['pd'] ?? '') === 'eigen' ? $a['pd'] : $d['pd'];
    foreach (['cl', 'cd'] as $k) $o[$k] = smLookColors($a[$k] ?? null);
    if ($o['pl'] === 'eigen' && !$o['cl']) $o['pl'] = 'standard';
    if ($o['pd'] === 'eigen' && !$o['cd']) $o['pd'] = 'standard';
    $o['font'] = isset(smFonts()[$a['font'] ?? '']) ? $a['font'] : $d['font'];
    $num = function ($v, int $min, int $max, int $def): int { return is_numeric($v) ? max($min, min($max, (int)$v)) : $def; };
    $o['fs'] = $num($a['fs'] ?? null, 80, 150, $d['fs']);
    $o['ft'] = $num($a['ft'] ?? null, 70, 160, $d['ft']);
    $o['fd'] = $num($a['fd'] ?? null, 70, 160, $d['fd']);
    $o['lh'] = $num($a['lh'] ?? null, 130, 200, $d['lh']);
    $o['gap'] = in_array($a['gap'] ?? '', ['compact', 'normal', 'airy'], true) ? $a['gap'] : $d['gap'];
    $o['width'] = in_array($a['width'] ?? '', ['narrow', 'normal', 'wide', 'full'], true) ? $a['width'] : $d['width'];
    return $o;
}

/** Eigene Farben: bg, card, text, acc Pflicht, link und mut freiwillig. Liefert null, wenn etwas nicht passt. */
function smLookColors($c): ?array
{
    if (!is_array($c)) return null;
    $out = [];
    foreach (['bg', 'card', 'text', 'acc', 'link', 'mut'] as $k) {
        $v = strtolower(trim((string)($c[$k] ?? '')));
        if ($v === '' && in_array($k, ['link', 'mut'], true)) continue;
        if (!preg_match('/^#[0-9a-f]{6}$/', $v)) return null;
        $out[$k] = $v;
    }
    return $out;
}

/** Aussehen eines Kontos (oder der Standard der Installation) */
function smLook(?array $me): array
{
    $def = smLookClean(json_decode(smSetting('look'), true));
    // Ältere Installationen: die Akzentfarbe aus der Verwaltung färbt das Standard-Design
    if (smSetting('look') === '' && smSetting('accent') !== 'blue') {
        $acc = smAccentHex(smSetting('accent'));
        if ($acc) { $def['pl'] = 'eigen'; $def['cl'] = ['bg' => '#ffffff', 'card' => '#ffffff', 'text' => '#1f1f1f', 'acc' => $acc[0]];
                    $def['pd'] = 'eigen'; $def['cd'] = ['bg' => '#1f1f1f', 'card' => '#303134', 'text' => '#e8eaed', 'acc' => $acc[1]]; }
    }
    $own = $me['prefs']['look'] ?? null;
    return is_array($own) ? smLookClean($own + $def) : $def;
}

/** Hell- und Dunkelfarbe der alten Akzent-Auswahl */
function smAccentHex(string $a): ?array
{
    $m = ['green' => ['#15803d', '#81c995'], 'teal' => ['#0f766e', '#5eead4'], 'orange' => ['#c2410c', '#fdba74'],
          'red' => ['#be123c', '#fda4af'], 'purple' => ['#7c3aed', '#c4b5fd'], 'gray' => ['#374151', '#d1d5db']];
    return $m[$a] ?? null;
}

/* ---------- Farben rechnen (wie in Kaufen) ---------- */

function smHex2Rgb(string $x): array { return [hexdec(substr($x, 1, 2)), hexdec(substr($x, 3, 2)), hexdec(substr($x, 5, 2))]; }
function smRgb2Hex(array $a): string { return '#' . implode('', array_map(function ($v) { return str_pad(dechex((int)round(max(0, min(255, $v)))), 2, '0', STR_PAD_LEFT); }, $a)); }
function smMix(string $a, string $b, float $t): string
{
    $x = smHex2Rgb($a);
    $y = smHex2Rgb($b);
    return smRgb2Hex([$x[0] + ($y[0] - $x[0]) * $t, $x[1] + ($y[1] - $x[1]) * $t, $x[2] + ($y[2] - $x[2]) * $t]);
}
function smLum(string $x): float
{
    $c = array_map(function ($v) { $v /= 255; return $v <= .03928 ? $v / 12.92 : (($v + .055) / 1.055) ** 2.4; }, smHex2Rgb($x));
    return .2126 * $c[0] + .7152 * $c[1] + .0722 * $c[2];
}
function smContrast(string $a, string $b): float
{
    $x = smLum($a);
    $y = smLum($b);
    return (max($x, $y) + .05) / (min($x, $y) + .05);
}
/** Farbton drehen (für die zweite Akzentfarbe und besuchte Links) */
function smHueShift(string $hex, int $deg): string
{
    [$r, $g, $b] = array_map(function ($v) { return $v / 255; }, smHex2Rgb($hex));
    $mx = max($r, $g, $b); $mn = min($r, $g, $b); $d = $mx - $mn; $l = ($mx + $mn) / 2;
    $h = 0.0;
    if ($d > 0) $h = fmod((($mx === $r ? fmod(($g - $b) / $d, 6) : ($mx === $g ? ($b - $r) / $d + 2 : ($r - $g) / $d + 4)) * 60 + 360), 360);
    $s = $d > 0 ? $d / (1 - abs(2 * $l - 1)) : 0;
    $h = fmod($h + $deg + 360, 360);
    $a = $s * min($l, 1 - $l);
    $f = function ($n) use ($h, $l, $a) { $k = fmod($n + $h / 30, 12); return 255 * ($l - $a * max(-1, min($k - 3, 9 - $k, 1))); };
    return smRgb2Hex([$f(0), $f(8), $f(4)]);
}
/** Lesbare Linkfarbe aus dem Akzent: so lange Richtung Schrift mischen, bis der Kontrast reicht */
function smReadable(string $c, string $bg, string $toward, float $goal = 4.5): string
{
    for ($t = 0; $t <= 1.0001; $t += .05) {
        $x = smMix($c, $toward, $t);
        if (smContrast($x, $bg) >= $goal) return $x;
    }
    return $toward;
}

/** Aus den vier (bis sechs) gewählten Farben alle Farben der Oberfläche */
function smLookVars(array $c): array
{
    $dark = smLum($c['bg']) < .2;
    $link = $c['link'] ?? smReadable($c['acc'], $c['card'], $c['text']);
    $mut = $c['mut'] ?? smMix($c['text'], $c['bg'], .3);
    return [
        '--bg' => $c['bg'], '--bg-2' => smMix($c['bg'], $c['text'], .03), '--surface' => $c['card'], '--surface-2' => smMix($c['card'], $c['text'], .06),
        '--line' => smMix($c['text'], $c['bg'], .82), '--line-2' => smMix($c['text'], $c['bg'], .91),
        '--text' => $c['text'], '--text-2' => $mut, '--text-3' => smReadable(smMix($c['text'], $c['bg'], .45), $c['bg'], $c['text'], 3.2),
        '--link' => $link, '--link-visited' => smReadable(smHueShift($link, 45), $c['card'], $c['text']),
        '--accent' => $c['acc'], '--accent-2' => smHueShift($c['acc'], 35),
        '--ok' => $dark ? '#81c995' : '#188038', '--error' => $dark ? '#f28b82' : '#c5221f',
        '--hover' => 'rgb(' . implode(' ', smHex2Rgb($c['text'])) . ' / .08)',
        '--shadow-box' => $dark ? '0 1px 6px rgb(0 0 0 / .6)' : '0 1px 6px rgb(32 33 36 / .28)',
        '--shadow' => $dark ? '0 1px 3px rgb(0 0 0 / .5), 0 4px 8px 3px rgb(0 0 0 / .3)' : '0 1px 3px rgb(60 64 67 / .3), 0 4px 8px 3px rgb(60 64 67 / .15)',
        'color-scheme' => $dark ? 'dark' : 'light',
    ];
}

/** Die Grundfarben einer Seite (hell oder dunkel) eines Aussehens */
function smLookSide(array $look, string $side): array
{
    $id = $look[$side === 'dark' ? 'pd' : 'pl'];
    if ($id === 'eigen' && ($c = $look[$side === 'dark' ? 'cd' : 'cl'])) return $c;
    $p = smPalettes()[$side][$id] ?? smPalettes()[$side]['standard'];
    $c = ['bg' => $p[1], 'card' => $p[2], 'text' => $p[3], 'acc' => $p[4]];
    if ($p[5] !== '') $c['link'] = $p[5];
    return $c;
}

/** Die ganze CSS-Datei zu einem Aussehen */
function smLookCss(array $look): string
{
    $block = function (array $vars): string {
        $s = '';
        foreach ($vars as $k => $v) $s .= $k . ':' . $v . ';';
        return $s;
    };
    $light = $block(smLookVars(smLookSide($look, 'light')));
    $dark = $block(smLookVars(smLookSide($look, 'dark')));
    $css = "/* Suchmaschine: Aussehen dieses Kontos */\n";
    $css .= ":root{{$light}}\n:root[data-theme=\"dark\"]{{$dark}}\n@media (prefers-color-scheme: dark){:root[data-theme=\"auto\"]{{$dark}}}\n";
    // Musterkarten der Designs (für Einstellungen → Aussehen), eigene Farben eingeschlossen
    foreach (smPalettes() as $side => $list) {
        $ids = array_keys($list);
        $ids[] = 'eigen';
        foreach ($ids as $id) {
            $c = $id === 'eigen' ? ($look[$side === 'dark' ? 'cd' : 'cl'] ?: smLookSide($look, $side)) : smLookSide(['pl' => $id, 'pd' => $id, 'cl' => null, 'cd' => null], $side);
            $css .= ".sw--{$side}-{$id}{--sw-bg:{$c['bg']};--sw-card:{$c['card']};--sw-text:{$c['text']};--sw-acc:{$c['acc']}}\n";
        }
    }
    // Schrift: nur die gewählte wird geladen (auf der Seite „Aussehen“ alle, für die Schriftproben)
    $f = smFonts()[$look['font']] ?? null;
    if (!empty($look['_all'])) {
        foreach (smFonts() as $id => $ff) {
            if ($ff[1] === '') continue;
            $css .= smFontFaces($id) . '.fontpick--' . $id . ' span{font-family:"SM ' . $ff[0] . '",system-ui,sans-serif}' . "\n";
        }
    } elseif ($f && $f[1] !== '') {
        $css .= smFontFaces($look['font']);
    }
    $stack = $f && $f[1] !== '' ? '"SM ' . $f[0] . '", ' . ($look['font'] === 'literata' ? 'Georgia, "Times New Roman", serif' : 'system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif') : '';
    if ($stack !== '') $css .= ":root{--font:{$stack}}\n";
    // Größen und Abstände
    $gap = ['compact' => .55, 'normal' => 1, 'airy' => 1.45][$look['gap']];
    $w = ['narrow' => 600, 'normal' => 652, 'wide' => 820, 'full' => 1100][$look['width']];
    $css .= ':root{--zoom:' . ($look['fs'] / 100) . ';--ft:' . ($look['ft'] / 100) . ';--fd:' . ($look['fd'] / 100) . ';--lh:' . ($look['lh'] / 100) . ';--gap:' . $gap . ';--main:' . $w . "px}\n";
    return $css;
}

/** @font-face für eine Schrift (lateinisch und erweitert, normal und fett) */
function smFontFaces(string $id): string
{
    $f = smFonts()[$id] ?? null;
    if (!$f || $f[1] === '') return '';
    $ranges = [
        'latin' => 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD',
        'latin-ext' => 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF',
    ];
    $css = '';
    foreach ([400, 700] as $wt) foreach ($ranges as $sub => $r) {
        $css .= '@font-face{font-family:"SM ' . $f[0] . '";font-style:normal;font-weight:' . ($wt === 400 ? '300 500' : '600 800') . ';font-display:swap;'
              . 'src:url(' . smUrl(['a' => 'font', 'f' => $f[1] . '-' . $sub . '-' . $wt . '-normal.woff2', 'v' => SM_VERSION]) . ') format("woff2");unicode-range:' . $r . "}\n";
    }
    return $css;
}

/** Adresse der CSS-Datei eines Aussehens (die Prüfsumme sorgt dafür, dass Browser nach Änderungen neu laden) */
function smLookUrl(array $look, bool $allFonts = false): string
{
    if ($allFonts) $look['_all'] = 1;
    $j = json_encode($look);
    return smUrl(['a' => 'look', 'l' => rtrim(strtr(base64_encode($j), '+/', '-_'), '='), 'v' => substr(hash('sha256', $j . SM_VERSION), 0, 10)]);
}

/** ?a=look: CSS aus dem Aussehen in der Adresse (enthält nur Farben und Zahlen, nichts Geheimes, darum ohne Anmeldung) */
function smServeLook(): void
{
    $raw = isset($_GET['l']) && is_string($_GET['l']) && strlen($_GET['l']) < 2000 ? base64_decode(strtr($_GET['l'], '-_', '+/')) : '';
    $in = json_decode((string)$raw, true);
    $look = smLookClean($in);
    if (!empty($in['_all'])) $look['_all'] = 1;
    smHeaders();
    header('Content-Type: text/css; charset=utf-8');
    header('Cache-Control: public, max-age=31536000, immutable');
    echo smLookCss($look);
    exit;
}

/** ?a=font: eine der eingebauten Schriften */
function smServeFont(): void
{
    $f = isset($_GET['f']) && is_string($_GET['f']) ? $_GET['f'] : '';
    if (!preg_match('/^[a-z0-9-]+-latin(-ext)?-(400|700)-normal\.woff2$/', $f) || !is_file(SM_APP . '/assets/fonts/' . $f)) {
        http_response_code(404);
        exit;
    }
    smHeaders();
    header('Content-Type: font/woff2');
    header('Cache-Control: public, max-age=31536000, immutable');
    readfile(SM_APP . '/assets/fonts/' . $f);
    exit;
}

/** Aussehen aus dem Formular */
function smLookFromPost(): array
{
    $a = [];
    foreach (['mode', 'pl', 'pd', 'font', 'fs', 'ft', 'fd', 'lh', 'gap', 'width'] as $k) if (isset($_POST[$k]) && is_string($_POST[$k])) $a[$k] = $_POST[$k];
    foreach (['cl', 'cd'] as $k) {
        if (isset($_POST[$k]) && is_array($_POST[$k])) {
            $c = array_map(function ($v) { return is_string($v) ? $v : ''; }, $_POST[$k]);
            // Freiwillige Farben nur, wenn „eigene Farbe“ angehakt ist (sonst automatisch)
            foreach (['link', 'mut'] as $opt) if (empty($_POST[$k . '_own'][$opt])) unset($c[$opt]);
            $a[$k] = $c;
        }
    }
    return smLookClean($a);
}
