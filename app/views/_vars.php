<?php defined('SM_VERSION') || exit;
/** Gemeinsame Werte für Trefferlisten */
$target = $prefs['newtab'] ? ' target="_blank"' : '';
$rel = ' rel="noopener noreferrer"';
$ft = $ft ?? '';
$lens = $lens ?? '';
$flt = $flt ?? smSearchFilters($tab);
$base = ['q' => $q, 't' => $tab !== 'web' ? $tab : null, 'f' => $fresh ?: null, 'exact' => $exact ? '1' : null, 'ft' => $ft ?: null, 'l' => $lens ?: null,
         'src' => $flt['src'] ?: null, 'sort' => $flt['sort'] ?: null, 'dl' => $flt['dl'] ?: null,
         'io' => $flt['io'] ?: null, 'is' => $flt['is'] ?: null, 'isort' => $flt['isort'] ?: null];
// Zurück hierher nach einer Schnellaktion an einem Treffer (beim Endlos-Scrollen an den Anfang der Liste)
$back = smUrl(['p' => $page > 1 && $prefs['scroll'] !== 'endless' ? $page : null] + $base);
$me = $me ?? null;
$showSrc = isset($res['source']) && ($res['source'] === 'multi' || count(smProvOrder()) > 1);
