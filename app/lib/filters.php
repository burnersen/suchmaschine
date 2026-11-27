<?php
/* Suchmaschine – Treffer filtern und sortieren: Dateitypen, Datum, Domains pro Seite, Bildformate. Läuft nach dem Zwischenspeicher, kostet also nichts. */
defined('SM_VERSION') || exit;

/* ---------- Dateitypen ---------- */

/** Die Auswahl im Menü. Andere Endungen kann man selbst eintippen. */
function smFileTypes(): array
{
    return ['pdf' => 'PDF', 'docx' => 'DOCX', 'xlsx' => 'XLSX', 'pptx' => 'PPTX', 'txt' => 'TXT', 'epub' => 'EPUB', 'csv' => 'CSV',
            'odt' => 'ODT', 'ods' => 'ODS', 'odp' => 'ODP', 'rtf' => 'RTF', 'doc' => 'DOC', 'xls' => 'XLS', 'ppt' => 'PPT'];
}

/** Dateityp aus der Adresse: nur Buchstaben und Ziffern, ohne Punkt. '' = alle. */
function smCleanFileType($ft): string
{
    $ft = strtolower(ltrim(trim(is_string($ft) ? $ft : ''), '.'));
    return preg_match('/^[a-z0-9]{1,10}$/', $ft) ? $ft : '';
}

/* ---------- Sortieren und Begrenzen (nach dem Zwischenspeicher, kostet also nichts) ---------- */

/** Ersetzt die Webtreffer und besetzt ihre Plätze in der gemischten Reihenfolge der Reihe nach neu */
function smSetWeb(array $res, array $results): array
{
    $res['results'] = array_values($results);
    if (isset($res['order'])) {
        $order = [];
        $n = 0;
        $total = count($res['results']);
        foreach ($res['order'] as $o) {
            if ($o[0] !== 'web') { $order[] = $o; continue; }
            if ($n < $total) $order[] = ['web', $n++];
        }
        while ($n < $total) $order[] = ['web', $n++];
        $res['order'] = $order;
    }
    return $res;
}

/** Streng chronologisch: neueste zuerst, Treffer ohne Datum ans Ende (sonst Reihenfolge wie geliefert) */
function smSortByDate(array $res): array
{
    $list = $res['results'] ?? [];
    $idx = array_keys($list);
    usort($idx, function ($a, $b) use ($list) {
        $ta = (int)($list[$a]['ts'] ?? 0);
        $tb = (int)($list[$b]['ts'] ?? 0);
        if (($ta > 0) !== ($tb > 0)) return $ta > 0 ? -1 : 1;
        return [$tb, $a] <=> [$ta, $b];
    });
    $res = smSetWeb($res, array_map(function ($i) use ($list) { return $list[$i]; }, $idx));
    $res['sorted'] = true;
    return $res;
}

/** Treffer weglassen, die jünger als $secs sind (beim Erweitern der News schon gezeigt). Ohne Datum bleiben sie. */
function smDropNewer(array $res, int $secs): array
{
    $limit = time() - $secs;
    $keep = array_filter($res['results'] ?? [], function ($r) use ($limit) { return (int)($r['ts'] ?? 0) <= 0 || (int)$r['ts'] < $limit; });
    return smSetWeb($res, $keep);
}

/** Höchstens $max Treffer pro Domain, damit eine Seite nicht alles flutet */
function smDomainLimit(array $res, int $max = 3): array
{
    $seen = [];
    $keep = [];
    $res['dl_hidden'] = 0;
    foreach ($res['results'] ?? [] as $r) {
        $h = strtolower(smHost((string)($r['host'] ?? '')));
        $seen[$h] = ($seen[$h] ?? 0) + 1;
        if ($h !== '' && $seen[$h] > $max) { $res['dl_hidden']++; continue; }
        $keep[] = $r;
    }
    return $res['dl_hidden'] ? smSetWeb($res, $keep) : $res;
}

/** Bildfilter: Ausrichtung, Größe, Sortierung nach Auflösung (Breite × Höhe) */
function smImageOrientations(): array
{
    return ['' => t('Alle Formate'), 'land' => t('Querformat'), 'port' => t('Hochformat'), 'square' => t('Quadratisch')];
}

function smImageSizes(): array
{
    // Grenzen nach echten Brave-Daten: Brave meldet meist die Maße, in denen das Bild auf der Seite steckt (Median um 0,25 MP)
    return ['' => t('Alle Größen'), 'xl' => t('Sehr groß (ab 1 MP, etwa HD)'), 'large' => t('Groß (ab 0,3 MP, etwa 640 × 480)'),
            'medium' => t('Mittel'), 'small' => t('Klein (unter 0,15 MP)')];
}

function smImageFilter(array $res, string $or, string $size, bool $byRes): array
{
    $before = count($res['results'] ?? []);
    $list = array_values(array_filter($res['results'] ?? [], function ($im) use ($or, $size) {
        $w = (int)$im['w'];
        $h = (int)$im['h'];
        // Ohne Maße lässt sich nicht filtern
        if (($or !== '' || $size !== '') && ($w <= 0 || $h <= 0)) return false;
        if ($or === 'land' && !($w > $h * 1.1)) return false;
        if ($or === 'port' && !($h > $w * 1.1)) return false;
        if ($or === 'square' && ($w > $h * 1.1 || $h > $w * 1.1)) return false;
        $px = $w * $h;
        if ($size === 'xl' && $px < 1000000) return false;
        if ($size === 'large' && $px < 300000) return false;
        if ($size === 'medium' && ($px < 150000 || $px >= 300000)) return false;
        if ($size === 'small' && $px >= 150000) return false;
        return true;
    }));
    if ($byRes) {
        $idx = array_keys($list);
        usort($idx, function ($a, $b) use ($list) {
            return [(int)$list[$b]['w'] * (int)$list[$b]['h'], $a] <=> [(int)$list[$a]['w'] * (int)$list[$a]['h'], $b];
        });
        $list = array_map(function ($i) use ($list) { return $list[$i]; }, $idx);
    }
    $res['results'] = $list;
    $res['img_hidden'] = $before - count($list);
    return $res;
}

/** Nur Treffer aus den letzten $secs Sekunden (ohne Datum bleiben sie). Für „letzte Stunde“, das Brave nicht kann. */
function smKeepRecent(array $res, int $secs): array
{
    $limit = time() - $secs - 120;
    $keep = array_filter($res['results'] ?? [], function ($r) use ($limit) { return (int)($r['ts'] ?? 0) <= 0 || (int)$r['ts'] >= $limit; });
    return count($keep) === count($res['results'] ?? []) ? $res : smSetWeb($res, $keep);
}

/** Videolänge in Sekunden aus „8:56“ oder „1:02:03“ */
function smDurSecs(array $v): int
{
    if (!empty($v['dur_s'])) return (int)$v['dur_s'];
    $parts = explode(':', (string)($v['duration'] ?? ''));
    if (count($parts) < 2 || count($parts) > 3 || array_filter($parts, function ($x) { return !ctype_digit(trim($x)); })) return 0;
    $s = 0;
    foreach ($parts as $x) $s = $s * 60 + (int)$x;
    return $s;
}

/** Videolänge: Wert => [Bezeichnung, von Sekunden, bis Sekunden] */
function smVideoDurations(): array
{
    return ['' => [t('Beliebige Länge'), 0, 0], 's' => [t('Kurz (unter 4 Minuten)'), 0, 240], 'm' => [t('Mittel (4 bis 20 Minuten)'), 240, 1200], 'l' => [t('Lang (über 20 Minuten)'), 1200, 0]];
}

/** Videos nach Länge (Brave kann das nicht selbst, darum nach dem Abruf) */
function smVideoDurFilter(array $res, string $d): array
{
    $def = smVideoDurations()[$d] ?? null;
    if (!$def) return $res;
    $res['results'] = array_values(array_filter($res['results'] ?? [], function ($v) use ($def) {
        $s = smDurSecs($v);
        if ($s <= 0) return false;
        return $s >= $def[1] && ($def[2] === 0 || $s < $def[2]);
    }));
    return $res;
}

/** „vor 3 Tagen“ aus einem Zeitstempel, für Dienste, die nur ein Datum liefern */
function smAgeText(int $ts): string
{
    if ($ts <= 0) return '';
    $d = time() - $ts;
    if ($d < 120) return t('vor 1 Minute');
    if ($d < 3600) return t('vor %d Minuten', (int)floor($d / 60));
    if ($d < 7200) return t('vor 1 Stunde');
    if ($d < 86400) return t('vor %d Stunden', (int)floor($d / 3600));
    if ($d < 86400 * 2) return t('gestern');
    if ($d < 86400 * 30) return t('vor %d Tagen', (int)floor($d / 86400));
    return smDate($ts);
}
