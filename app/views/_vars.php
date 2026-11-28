<?php defined('SM_VERSION') || exit;
/** Gemeinsame Werte für Trefferlisten */
$target = $prefs['newtab'] ? ' target="_blank"' : '';
$rel = ' rel="noopener noreferrer"';
$ft = $ft ?? '';
$lens = $lens ?? '';
$flt = $flt ?? smSearchFilters($tab, $prefs);
$base = ['q' => $q, 't' => $tab !== 'web' ? $tab : null, 'f' => $flt['f_q'] ?: null, 'xf' => $flt['xf'] ?: null, 'exact' => $exact ? '1' : null, 'ft' => $ft ?: null, 'l' => $lens ?: null,
         'sort' => $flt['sort_q'] ?: null, 'dl' => $flt['dl'] ?: null,
         'io' => $flt['io'] ?: null, 'is' => $flt['is'] ?: null, 'isort' => $flt['isort'] ?: null, 'isrc' => ($flt['isrc'] ?? '') ?: null,
         'fd' => $flt['fd'] ?: null, 'td' => $flt['td'] ?: null, 'vb' => $flt['vb'] ?: null, 'sl' => $flt['sl'] ?: null, 'cr' => $flt['cr'] ?: null,
         'vd' => $flt['vd'] ?: null];
// Ein Auswahlmenü in der Filterleiste: Adressparameter, Werte => Bezeichnung, Beschriftung ohne Auswahl
$smDrop = function (string $param, array $opts, string $label) use ($flt, $base) {
    $cur = (string)($flt[$param] ?? '');
    $sum = $cur !== '' && isset($opts[$cur]) ? $opts[$cur] : $label;
    $o = '<details class="tools"><summary class="tabs__tab' . ($cur !== '' ? ' is-set' : '') . '">' . h($sum)
       . ' <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary><div class="tools__panel' . (count($opts) > 8 ? ' tools__panel--cols' : '') . '">';
    foreach ($opts as $k => $l) {
        $o .= '<a href="' . h(smUrl([$param => $k !== '' ? $k : null] + $base)) . '"' . ((string)$k === $cur ? ' class="is-active"' : '') . ' data-load>' . h($l) . '</a>';
    }
    return $o . '</div></details>';
};
// Formular in einem Menü: alle Parameter der Suche als versteckte Felder, außer den genannten
$smHidden = function (array $except) use ($base) {
    $o = '';
    foreach ($base as $k => $v) if ($v !== null && $v !== '' && !in_array($k, $except, true)) $o .= '<input type="hidden" name="' . h($k) . '" value="' . h((string)$v) . '">';
    return $o;
};
// Zurück hierher nach einer Schnellaktion an einem Treffer (beim Endlos-Scrollen an den Anfang der Liste)
$back = smUrl(['p' => $page > 1 && $prefs['scroll'] !== 'endless' ? $page : null] + $base);
$me = $me ?? null;
