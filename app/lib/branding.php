<?php
/* Suchmaschine – Eigenes Logo und App-Symbol für den Startbildschirm */
defined('SM_VERSION') || exit;

/** Hochgeladenes Logo prüfen und speichern. Liefert Fehlertext oder ''. */
function smSaveLogo(string $tmp, int $size): string
{
    if ($size <= 0 || $size > 1048576) return t('Das Logo darf höchstens 1 MB groß sein.');
    $bin = (string)file_get_contents($tmp);
    $type = '';
    $info = @getimagesizefromstring($bin);
    if (is_array($info) && in_array($info['mime'] ?? '', ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) {
        $type = $info['mime'];
    } elseif (preg_match('/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*(<!DOCTYPE[^>]*>\s*)?<svg[\s>]/is', $bin)) {
        // SVG: nichts Ausführbares zulassen
        if (preg_match('/<script|<foreignObject|\son[a-z]+\s*=|javascript:/i', $bin)) return t('Dieses SVG enthält Skripte und wird darum nicht angenommen.');
        $type = 'image/svg+xml';
    }
    if ($type === '') return t('Bitte ein Bild im Format PNG, JPG, WebP, GIF oder SVG hochladen.');
    if (@file_put_contents(SM_DATA_DIR . '/logo.bin', $bin, LOCK_EX) === false) return t('In den Ordner data/ kann nicht geschrieben werden. Bitte die Schreibrechte prüfen.');
    smSetSetting('logo_type', $type);
    smSetSetting('logo_v', (string)time());
    return '';
}

/* ---------- App-Symbol für den Startbildschirm (PNG, 512 × 512, daraus kleinere Größen) ---------- */

const SM_ICON_SIZES = [180, 192, 512];

function smAppIconFile(): string
{
    return SM_DATA_DIR . '/appicon.png';
}

/** Gibt es ein App-Symbol aus dem eigenen Logo? */
function smAppIconCustom(): bool
{
    return smLogoUrl() !== '' && is_file(smAppIconFile());
}

/** Adresse des App-Symbols. Ändert sich mit dem Logo, damit Geräte das neue holen. */
function smAppIconUrl(int $size): string
{
    return smUrl(['a' => 'appicon', 's' => $size, 'v' => smAppIconCustom() ? smSetting('appicon_v') : SM_VERSION]);
}

function smAppIconClear(): void
{
    foreach (array_merge([smAppIconFile()], (array)glob(SM_DATA_DIR . '/appicon-*.png')) as $f) {
        if (is_file($f)) @unlink($f);
    }
}

function smWriteAppIcon(string $png): bool
{
    smAppIconClear();
    if (@file_put_contents(smAppIconFile(), $png, LOCK_EX) === false) return false;
    smSetSetting('appicon_v', (string)time());
    return true;
}

/** PNG (512 × 512) speichern, das der Browser aus dem Logo gezeichnet hat. Liefert Fehlertext oder ''. */
function smSaveAppIconPng(string $data): string
{
    $bin = base64_decode((string)preg_replace('~^data:image/png;base64,~', '', trim($data)), true);
    $i = $bin === false || strlen($bin) > 4000000 ? false : @getimagesizefromstring($bin);
    if (!is_array($i) || ($i['mime'] ?? '') !== 'image/png' || $i[0] !== 512 || $i[1] !== 512) return t('Das App-Symbol ist ungültig.');
    return smWriteAppIcon($bin) ? '' : t('In den Ordner data/ kann nicht geschrieben werden. Bitte die Schreibrechte prüfen.');
}

/** App-Symbol mit PHP (GD) aus einem Pixelbild bauen: weißer Grund, Logo mittig. false = ging nicht. */
function smAppIconFromRaster(string $bin): bool
{
    if ($bin === '' || !function_exists('imagecreatefromstring')) return false;
    $src = @imagecreatefromstring($bin);
    if (!$src) return false;
    $w = imagesx($src);
    $h = imagesy($src);
    $dst = imagecreatetruecolor(512, 512);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagealphablending($dst, true);
    $scale = min(410 / max(1, $w), 410 / max(1, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    imagecopyresampled($dst, $src, (int)((512 - $nw) / 2), (int)((512 - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
    ob_start();
    imagepng($dst, null, 9);
    $png = (string)ob_get_clean();
    return $png !== '' && smWriteAppIcon($png);
}
