<?php defined('SM_VERSION') || exit;
/** Gemeinsame Werte für Trefferlisten */
$target = $prefs['newtab'] ? ' target="_blank"' : '';
$rel = ' rel="noopener noreferrer"';
$base = ['q' => $q, 't' => $tab !== 'web' ? $tab : null, 'f' => $fresh ?: null, 'exact' => $exact ? '1' : null];
