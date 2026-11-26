<?php
/**
 * Suchmaschine – Installer (Version @@VERSION@@)
 *
 * Diese eine Datei enthält das ganze Programm. Hochladen, im Browser aufrufen, fertig:
 * Sie legt index.php und den Ordner app/ neben sich an und löscht sich danach selbst.
 * Bei einem Update werden nur die Programmdateien ersetzt, der Ordner data/ wird nie angefasst.
 *
 * Erzeugt von tools/build-release.php, nicht von Hand bearbeiten.
 * Lizenz: GNU Affero General Public License v3.0, siehe Datei LICENSE.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

define('SMI_VERSION', '@@VERSION@@');
define('SMI_MIN_PHP', '8.0.0');

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$smiAl = strtolower(trim(isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? (string)$_SERVER['HTTP_ACCEPT_LANGUAGE'] : ''));
$smiEn = $smiAl !== '' && strncmp($smiAl, 'de', 2) !== 0;

/** Deutsch oder Englisch, je nach Browser */
function smiT($de, $en)
{
    global $smiEn;
    return $smiEn ? $en : $de;
}

function smiH($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Die eingepackten Dateien: hinter dem Programmende steht pro Zeile „Pfad, Prüfsumme, Inhalt (Base64)“ */
function smiPayload()
{
    $fp = @fopen(__FILE__, 'rb');
    if (!$fp) return null;
    fseek($fp, __COMPILER_HALT_OFFSET__);
    $raw = stream_get_contents($fp);
    fclose($fp);
    $files = array();
    foreach (explode("\n", (string)$raw) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $p = explode("\t", $line);
        if (count($p) !== 3) return null;
        $path = $p[0];
        if (!preg_match('~^[A-Za-z0-9._\-]+(/[A-Za-z0-9._\-]+)*$~', $path) || strpos($path, '..') !== false) return null;
        $bin = base64_decode($p[2], true);
        if ($bin === false || !hash_equals($p[1], hash('sha256', $bin))) return null;
        $files[$path] = $bin;
    }
    return isset($files['index.php']) ? $files : null;
}

/** Version einer schon vorhandenen Suchmaschine, '' = index.php gehört zu etwas anderem, null = keine da */
function smiInstalledVersion($index)
{
    if (!is_file($index)) return null;
    $head = (string)@file_get_contents($index, false, null, 0, 8192);
    if (strpos($head, 'Suchmaschine') !== false && preg_match("~define\\('SM_VERSION',\\s*'([0-9][0-9A-Za-z.\\-]*)'\\)~", $head, $m)) return $m[1];
    return '';
}

/** Schreibt alles erst unter einem Zwischennamen und tauscht dann um. Liefert Fehlertext oder ''. */
function smiInstall($dir, array $files)
{
    $tmp = array();
    $fail = '';
    foreach ($files as $path => $bin) {
        $target = $dir . '/' . $path;
        $parent = dirname($target);
        if (!is_dir($parent) && !@mkdir($parent, 0755, true) && !is_dir($parent)) { $fail = $path; break; }
        $t = $target . '.tmp';
        if (@file_put_contents($t, $bin) !== strlen($bin) || hash('sha256', (string)@file_get_contents($t)) !== hash('sha256', $bin)) {
            @unlink($t);
            $fail = $path;
            break;
        }
        $tmp[$t] = $target;
    }
    if ($fail === '') {
        foreach ($tmp as $t => $target) {
            if (!@rename($t, $target)) { $fail = substr($target, strlen($dir) + 1); break; }
            @chmod($target, 0644);
            if (function_exists('opcache_invalidate')) @opcache_invalidate($target, true);
        }
    }
    if ($fail !== '') {
        foreach ($tmp as $t => $target) if (is_file($t)) @unlink($t);
        return $fail;
    }
    // Programmteile, die es in dieser Version nicht mehr gibt, wegräumen (nur in app/, sonst nichts)
    foreach (array('lib', 'views', 'assets', 'lang') as $sub) {
        foreach ((array)@glob($dir . '/app/' . $sub . '/*') as $old) {
            if (is_file($old) && !isset($files['app/' . $sub . '/' . basename($old)])) @unlink($old);
        }
    }
    return '';
}

/** ZIPs aus dem Release, die neben dem Installer liegen (werden nach der Installation nicht mehr gebraucht) */
function smiZips($dir)
{
    $out = array();
    foreach ((array)@glob($dir . '/suchmaschine-*.zip') as $z) {
        if (is_file($z) && preg_match('~^suchmaschine-[0-9][0-9A-Za-z.\-]*\.zip$~', basename($z))) $out[] = $z;
    }
    return $out;
}

$smiDir = __DIR__;
$smiSelf = __FILE__;
$smiIndex = $smiDir . '/index.php';
$smiSelfIsIndex = realpath($smiSelf) === realpath($smiIndex);
$smiFiles = smiPayload();
$smiOld = $smiSelfIsIndex ? null : smiInstalledVersion($smiIndex);
$smiHasData = is_dir($smiDir . '/data');

$smiProblems = array();
if (version_compare(PHP_VERSION, SMI_MIN_PHP, '<')) {
    $smiProblems[] = smiT('Die Suchmaschine braucht PHP 8.0 oder neuer, hier läuft PHP ', 'The search engine needs PHP 8.0 or newer, this server runs PHP ') . PHP_VERSION
        . smiT('. Im Hosting-Panel lässt sich die PHP-Version meist umstellen.', '. The PHP version can usually be changed in the hosting panel.');
}
if (!extension_loaded('pdo_sqlite')) {
    $smiProblems[] = smiT('Die PHP-Erweiterung „pdo_sqlite“ fehlt. Ohne sie kann die Suchmaschine nichts speichern.', 'The PHP extension "pdo_sqlite" is missing. The search engine cannot store anything without it.');
}
if (!extension_loaded('curl')) {
    $smiProblems[] = smiT('Die PHP-Erweiterung „curl“ fehlt. Ohne sie kann die Suchmaschine Brave nicht fragen.', 'The PHP extension "curl" is missing. The search engine cannot ask Brave without it.');
}
if (!is_writable($smiDir)) {
    $smiProblems[] = smiT('PHP darf in diesen Ordner nicht schreiben. Bitte die Schreibrechte anpassen.', 'PHP is not allowed to write to this folder. Please fix the permissions.');
}
if ($smiFiles === null) {
    $smiProblems[] = smiT('Diese Installer-Datei ist beschädigt (unvollständig hochgeladen?). Bitte noch einmal herunterladen und im Binärmodus hochladen.', 'This installer file is damaged (incomplete upload?). Please download it again and upload it in binary mode.');
}

$smiError = '';
$smiDone = false;
$smiSelfGone = true;
if (!$smiProblems && (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') === 'POST' && isset($_POST['smi_go'])) {
    if ($smiOld === '' && empty($_POST['smi_overwrite'])) {
        $smiError = smiT('Bitte erst bestätigen, dass die vorhandene index.php ersetzt werden darf.', 'Please confirm first that the existing index.php may be replaced.');
    } else {
        $fail = smiInstall($smiDir, $smiFiles);
        if ($fail !== '') {
            $smiError = smiT('Diese Datei ließ sich nicht schreiben: ', 'This file could not be written: ') . $fail
                      . smiT('. Es wurde nichts verändert.', '. Nothing has been changed.');
        } else {
            $smiDone = true;
            foreach (smiZips($smiDir) as $z) @unlink($z);
            if (!$smiSelfIsIndex) {
                @unlink($smiSelf);
                clearstatcache();
                $smiSelfGone = !is_file($smiSelf);
            }
            if ($smiSelfGone) {
                header('Location: index.php');
                exit;
            }
        }
    }
}

$smiTitle = $smiOld ? smiT('Suchmaschine aktualisieren', 'Update search engine') : smiT('Suchmaschine installieren', 'Install search engine');
?><!DOCTYPE html>
<html lang="<?php echo $smiEn ? 'en' : 'de'; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?php echo smiH($smiTitle); ?></title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.25rem;
           background: #f8f9fa; color: #1f1f1f; font: 16px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    @media (prefers-color-scheme: dark) { body { background: #171717; color: #e8eaed; } .card { background: #1f1f1f !important; border-color: #3c4043 !important; } code { background: #2a2b2e !important; border-color: #3c4043 !important; } .sub { color: #9aa0a6 !important; } }
    .card { width: 100%; max-width: 34rem; background: #fff; border: 1px solid #dadce0; border-radius: 1.5rem; padding: 2rem; }
    .head { display: flex; align-items: center; gap: .9rem; margin-bottom: 1.2rem; }
    .mark { width: 3rem; height: 3rem; border-radius: .9rem; background: linear-gradient(135deg, #3b82f6, #6366f1 55%, #14b8a6); display: grid; place-items: center; flex-shrink: 0; }
    h1 { margin: 0; font-size: 1.4rem; letter-spacing: -.02em; }
    .sub { margin: 0; color: #70757a; font-size: .9rem; }
    ul { margin: 0 0 1.25rem; padding-left: 1.2rem; }
    li { margin-bottom: 0.35rem; }
    code { background: #f1f3f4; border: 1px solid #dadce0; border-radius: 0.35rem; padding: 0.05rem 0.35rem; font-size: 0.85em; }
    .box { border-radius: 0.8rem; padding: 0.75rem 0.9rem; margin-bottom: 1rem; font-size: 0.92rem; }
    .bad { background: rgba(197, 34, 31, 0.1); border: 1px solid rgba(197, 34, 31, 0.4); }
    .warn { background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.45); }
    .good { background: rgba(24, 128, 56, 0.1); border: 1px solid rgba(24, 128, 56, 0.4); }
    label { display: flex; gap: 0.6rem; align-items: flex-start; margin-bottom: 1rem; font-size: 0.92rem; }
    button, .btn { display: inline-block; width: 100%; border: 0; border-radius: 999px; padding: 0.85rem 1rem; font: inherit; font-weight: 600;
                   color: #fff; background: #4f46e5; cursor: pointer; text-align: center; text-decoration: none; }
    button:hover, .btn:hover { filter: brightness(1.08); }
</style>
</head>
<body>
<div class="card">
    <div class="head">
        <span class="mark"><svg width="26" height="26" viewBox="0 0 64 64"><circle cx="28.5" cy="28.5" r="14" fill="none" stroke="#fff" stroke-width="7"/><path d="M39 39l11 11" stroke="#fff" stroke-width="7.5" stroke-linecap="round"/></svg></span>
        <div><h1><?php echo smiH($smiTitle); ?></h1><p class="sub"><?php echo smiH(smiT('Suchmaschine ohne Werbung', 'Search engine without ads')); ?> · Version <?php echo smiH(SMI_VERSION); ?></p></div>
    </div>
<?php if ($smiDone): ?>
    <div class="box good"><?php echo smiH(smiT('Die Suchmaschine ist eingerichtet.', 'The search engine is installed.')); ?></div>
    <div class="box bad"><?php echo smiH(smiT('Diese Installer-Datei ließ sich nicht löschen. Bitte lösche sie jetzt von Hand: ', 'This installer file could not be deleted. Please delete it by hand now: ')); ?><code><?php echo smiH(basename($smiSelf)); ?></code></div>
    <a class="btn" href="index.php"><?php echo smiH(smiT('Weiter zur Suchmaschine', 'Continue to the search engine')); ?></a>
<?php elseif ($smiProblems): ?>
<?php foreach ($smiProblems as $p): ?>
    <div class="box bad"><?php echo smiH($p); ?></div>
<?php endforeach; ?>
    <p class="sub"><?php echo smiH(smiT('Es wurde nichts verändert. Behebe das Problem und lade diese Seite neu.', 'Nothing has been changed. Fix the problem and reload this page.')); ?></p>
<?php else: ?>
<?php if ($smiError !== ''): ?>
    <div class="box bad"><?php echo smiH($smiError); ?></div>
<?php endif; ?>
    <ul>
<?php if ($smiOld): ?>
        <li><?php echo smiH(smiT('Gefunden: Suchmaschine ', 'Found: search engine ') . $smiOld . smiT('. Sie wird auf ', '. It will be updated to ') . SMI_VERSION . smiT(' aktualisiert.', '.')); ?></li>
<?php else: ?>
        <li><?php echo smiT('Legt <code>index.php</code> und den Ordner <code>app/</code> in diesem Verzeichnis an.', 'Creates <code>index.php</code> and the folder <code>app/</code> in this directory.'); ?></li>
<?php endif; ?>
<?php if ($smiHasData): ?>
        <li><?php echo smiT('Der Ordner <code>data/</code> mit deinen Konten und Einstellungen bleibt unangetastet.', 'The folder <code>data/</code> with your accounts and settings is left untouched.'); ?></li>
<?php endif; ?>
        <li><?php echo smiH(smiT('Danach löscht sich diese Installer-Datei selbst.', 'Afterwards this installer file deletes itself.')); ?></li>
<?php foreach (smiZips($smiDir) as $z): ?>
        <li><?php echo smiT('Auch <code>', 'Also <code>') . smiH(basename($z)) . smiT('</code> wird gelöscht, die Datei wird dann nicht mehr gebraucht.', '</code> is deleted, the file is no longer needed then.'); ?></li>
<?php endforeach; ?>
<?php if (!$smiOld && !$smiHasData): ?>
        <li><?php echo smiH(smiT('Gleich im Anschluss legst du dein Konto an und trägst den Brave API-Key ein. Wer das als Erster tut, wird Administrator, also nicht aufschieben.', 'Right afterwards you create your account and enter the Brave API key. Whoever does that first becomes the administrator, so do not put it off.')); ?></li>
<?php endif; ?>
    </ul>
    <form method="post">
        <input type="hidden" name="smi_go" value="1">
<?php if ($smiOld === ''): ?>
        <div class="box warn"><?php echo smiT('In diesem Verzeichnis liegt schon eine <code>index.php</code>, die nicht zur Suchmaschine gehört. Sie würde überschrieben.', 'This directory already contains an <code>index.php</code> that does not belong to the search engine. It would be overwritten.'); ?></div>
        <label><input type="checkbox" name="smi_overwrite" value="1"> <span><?php echo smiH(smiT('Ja, die vorhandene index.php darf ersetzt werden.', 'Yes, the existing index.php may be replaced.')); ?></span></label>
<?php endif; ?>
        <button type="submit"><?php echo smiH($smiOld ? smiT('Jetzt aktualisieren', 'Update now') : smiT('Jetzt installieren', 'Install now')); ?></button>
    </form>
<?php endif; ?>
</div>
</body>
</html>
<?php
__halt_compiler();
