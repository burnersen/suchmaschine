<?php
/**
 * Suchmaschine – prüft die Texte der Werkzeug-Apps (JavaScript): Jeder T('…')-Text braucht eine englische Fassung
 * in app/assets/cf/en.js (aus CodeForge) oder app/assets/tools/en.js. Wird auch von tests/run.php benutzt.
 *
 *   php tools/js-strings.php          listet fehlende Texte
 *   php tools/js-strings.php --json   dasselbe als JSON
 */

/** Alle Schlüssel aus den englischen Wörterbüchern */
function smJsEnKeys(string $assets): array
{
    $keys = [];
    foreach (['cf/en.js', 'tools/en.js'] as $f) {
        if (!is_file($assets . '/' . $f)) continue;
        preg_match_all("~^\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*:~m", (string)file_get_contents($assets . '/' . $f), $m);
        foreach ($m[1] as $k) $keys[stripcslashes($k)] = true;
    }
    return $keys;
}

/** Fehlende Texte: T('…') in den Apps, dazu Texte, die über Variablen übersetzt werden (mit // T: markiert) */
function smJsMissing(string $assets): array
{
    $keys = smJsEnKeys($assets);
    $miss = [];
    foreach (array_merge(glob($assets . '/tools/*.js'), glob($assets . '/cf/*.js')) as $f) {
        if (preg_match('~/(en|words)\.js$~', $f)) continue;
        $src = (string)file_get_contents($f);
        preg_match_all("~\\bT\\(\\s*'((?:[^'\\\\]|\\\\.)*)'~", $src, $m);
        foreach ($m[1] as $s) {
            $s = stripcslashes($s);
            if (!isset($keys[$s]) && preg_match('/[a-zäöüß]/i', $s)) $miss[$s] = basename($f);
        }
    }
    return $miss;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $miss = smJsMissing(dirname(__DIR__) . '/app/assets');
    if (in_array('--json', $argv, true)) { echo json_encode(array_keys($miss), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n"; exit; }
    foreach ($miss as $s => $f) echo $f, "\t", $s, "\n";
    echo count($miss), " fehlen\n";
}
