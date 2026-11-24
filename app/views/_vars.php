<?php defined('SM_VERSION') || exit;
/** Gemeinsame Werte für Trefferlisten */
$target = $prefs['newtab'] ? ' target="_blank"' : '';
$rel = ' rel="noopener noreferrer"';
$ft = $ft ?? '';
$lens = $lens ?? '';
$base = ['q' => $q, 't' => $tab !== 'web' ? $tab : null, 'f' => $fresh ?: null, 'exact' => $exact ? '1' : null, 'ft' => $ft ?: null, 'l' => $lens ?: null];
// Zurück hierher nach einer Schnellaktion an einem Treffer (beim Endlos-Scrollen an den Anfang der Liste)
$back = smUrl(['p' => $page > 1 && $prefs['scroll'] !== 'endless' ? $page : null] + $base);
$me = $me ?? null;
