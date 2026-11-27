<?php
/* Suchmaschine – Persönliche Sucheinstellungen: lesen, prüfen, sichern und wiederherstellen */
defined('SM_VERSION') || exit;

/** Sucheinstellungen: eigene des Kontos, sonst die der Installation */
function smPrefs(?array $me): array
{
    $p = $me['prefs'] ?? [];
    return [
        'country'     => isset($p['country'], smCountries()[$p['country']]) ? $p['country'] : smSetting('country'),
        'search_lang' => isset($p['search_lang'], smSearchLangs()[$p['search_lang']]) ? $p['search_lang'] : smSetting('search_lang'),
        'safesearch'  => in_array($p['safesearch'] ?? '', ['off', 'moderate', 'strict'], true) ? $p['safesearch'] : smSetting('safesearch'),
        'newtab'      => !empty($p['newtab']),
        'history'     => !isset($p['history']) || !empty($p['history']),
        'scroll'      => in_array($p['scroll'] ?? '', ['pages', 'endless'], true) ? $p['scroll'] : (smSetting('scroll') === 'endless' ? 'endless' : 'pages'),
        'sort_web'    => in_array($p['sort_web'] ?? '', ['date', 'rel'], true) ? $p['sort_web'] : (smSetting('sort_web') === 'rel' ? 'rel' : 'date'),
        'sort_news'   => in_array($p['sort_news'] ?? '', ['date', 'rel'], true) ? $p['sort_news'] : (smSetting('sort_news') === 'rel' ? 'rel' : 'date'),
        'sort_videos' => in_array($p['sort_videos'] ?? '', ['date', 'rel'], true) ? $p['sort_videos'] : (smSetting('sort_videos') === 'rel' ? 'rel' : 'date'),
        'news_fresh'  => smNewsFreshOk($p['news_fresh'] ?? '') ? $p['news_fresh'] : (smNewsFreshOk(smSetting('news_fresh')) ? smSetting('news_fresh') : 'pd'),
        'news_expand' => isset($p['news_expand']) ? !empty($p['news_expand']) : smSetting('news_expand') === '1',
        // Wettlauf: nach so vielen Sekunden zusätzlich den nächsten Dienst fragen ('' = Standard der Installation)
        'race'        => isset($p['race']) && is_numeric($p['race']) ? (string)max(0, min(20, (int)$p['race'])) : '',
    ];
}

/** Gültiger News-Zeitraum: pd, pw, pm, py oder all (beliebig) */
function smNewsFreshOk($v): bool
{
    return is_string($v) && in_array($v, ['pd', 'pw', 'pm', 'py', 'all'], true);
}

/** Nächste Zeitstufe bei „Neueste zuerst“: 24 Stunden → Woche → Monat → Jahr → älter ('' = keine mehr) */
function smNextFresh(string $f): string
{
    return ['ph' => 'pd', 'pd' => 'pw', 'pw' => 'pm', 'pm' => 'py', 'py' => 'all'][$f] ?? '';
}

/** Name einer Zeitstufe (all = alles, was älter ist als die Stufen davor) */
function smTierLabel(string $f): string
{
    return $f === 'all' ? t('Älter als ein Jahr') : (smFreshness()[$f] ?? smFreshness()['']);
}

/** Wie viele Tage eine Zeitstufe zurückreicht (für „ohne die schon gezeigten“) */
function smTierDays(string $f): int
{
    return ['ph' => 0, 'pd' => 1, 'pw' => 7, 'pm' => 31, 'py' => 365][$f] ?? 0;
}

/** Sortierung und News aus einem Formular (eigene Einstellungen oder Standard der Installation) */
function smSortPrefsFromPost(): array
{
    return [
        'sort_web'    => ($_POST['sort_web'] ?? '') === 'rel' ? 'rel' : 'date',
        'sort_news'   => ($_POST['sort_news'] ?? '') === 'rel' ? 'rel' : 'date',
        'sort_videos' => ($_POST['sort_videos'] ?? '') === 'rel' ? 'rel' : 'date',
        'news_fresh'  => smNewsFreshOk($_POST['news_fresh'] ?? '') ? (string)$_POST['news_fresh'] : 'pd',
        'news_expand' => !empty($_POST['news_expand']),
    ];
}

/** Eigene Wartezeit für den Wettlauf aus dem Formular ('' = Standard der Installation) */
function smRacePrefFromPost(): string
{
    $v = trim((string)($_POST['race'] ?? ''));
    return $v !== '' && is_numeric($v) ? (string)max(0, min(20, (int)$v)) : '';
}

/* ---------- Persönliche Einstellungen sichern und wiederherstellen ---------- */

/** Alle persönlichen Einstellungen geprüft und bereinigt (für Import und zum Speichern) */
function smCleanPrefs(array $p): array
{
    $out = [];
    if (isset($p['country']) && isset(smCountries()[$p['country']])) $out['country'] = $p['country'];
    if (isset($p['search_lang']) && isset(smSearchLangs()[$p['search_lang']])) $out['search_lang'] = $p['search_lang'];
    if (in_array($p['safesearch'] ?? '', ['off', 'moderate', 'strict'], true)) $out['safesearch'] = $p['safesearch'];
    if (in_array($p['scroll'] ?? '', ['pages', 'endless'], true)) $out['scroll'] = $p['scroll'];
    foreach (['sort_web', 'sort_news', 'sort_videos'] as $k) if (in_array($p[$k] ?? '', ['date', 'rel'], true)) $out[$k] = $p[$k];
    if (smNewsFreshOk($p['news_fresh'] ?? '')) $out['news_fresh'] = $p['news_fresh'];
    if (isset($p['news_expand'])) $out['news_expand'] = !empty($p['news_expand']);
    if (isset($p['race']) && is_numeric($p['race'])) $out['race'] = (string)max(0, min(20, (int)$p['race']));
    foreach (['newtab', 'history'] as $k) if (isset($p[$k])) $out[$k] = !empty($p[$k]);
    if (isset($p['city']) && is_string($p['city'])) $out['city'] = trim(mb_substr(smPlain($p['city']), 0, 60));
    if (isset($p['weather_src']) && isset(smWeatherSources()[$p['weather_src']])) $out['weather_src'] = $p['weather_src'];
    foreach (['block', 'raise', 'lower'] as $k) $out[$k] = smCleanDomains($p[$k] ?? []);
    $out['bangs'] = smCleanBangs($p['bangs'] ?? []);
    $out['lenses'] = smCleanLenses($p['lenses'] ?? []);
    $out['bang_off'] = smCleanBangOff($p['bang_off'] ?? []);
    if (isset($p['look']) && is_array($p['look'])) $out['look'] = smLookClean($p['look']);
    $out['lens_off'] = array_values(array_intersect(array_keys(smBuiltinLenses()), is_array($p['lens_off'] ?? null) ? $p['lens_off'] : []));
    return $out;
}

/** Datei zum Herunterladen mit allen persönlichen Einstellungen */
function smServeExport(array $me): void
{
    $data = [
        'format'   => 'suchmaschine-prefs',
        'version'  => 1,
        'app'      => SM_VERSION,
        'exported' => date('c'),
        'account'  => $me['name'],
        'prefs'    => smCleanPrefs($me['prefs']),
    ];
    smHeaders();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Content-Disposition: attachment; filename="suchmaschine-' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $me['name']) . '-' . date('Y-m-d') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Hochgeladene Sicherung einlesen. Liefert [Einstellungen, Fehlertext]. */
function smReadImport(string $field, string $format): array
{
    $f = $_FILES[$field] ?? null;
    if (!is_array($f) || empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) return [null, t('Bitte eine Sicherungsdatei auswählen.')];
    if ((int)$f['size'] > 1048576) return [null, t('Die Datei ist zu groß.')];
    $j = json_decode((string)file_get_contents($f['tmp_name']), true);
    if (!is_array($j) || ($j['format'] ?? '') !== $format || !is_array($j['prefs'] ?? null)) {
        return [null, t('Das ist keine passende Sicherungsdatei der Suchmaschine.')];
    }
    return [$j['prefs'], ''];
}
