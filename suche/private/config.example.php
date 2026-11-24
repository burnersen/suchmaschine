<?php
/**
 * Konfiguration der privaten Suche.
 *
 * Diese Datei als "config.php" in denselben Ordner kopieren und anpassen.
 * config.php wird nie über den Browser ausgeliefert (Ordner ist gesperrt).
 */
defined('PRIVATE_DIR') || exit;

return [
    // Dein Brave Search API Key (Dashboard → API Keys)
    'brave_api_key' => 'HIER_DEIN_BRAVE_API_KEY',

    // Wie lange eine Suche gecacht wird, in Sekunden.
    // Bravens AGB erlauben nur kurzfristiges ("transient") Zwischenspeichern,
    // 15 Minuten fangen Neuladen und Zurück-Klicks ab.
    'cache_ttl' => 900,

    // Harte Grenze für echte API-Calls pro Monat (alle Nutzer zusammen).
    // Schützt dein Guthaben: $5 Freiguthaben ≈ 1.000 Suchen.
    'monthly_limit' => 900,

    // Suchregion und Sprache
    'country'     => 'DE',     // Land der Ergebnisse (ALL = weltweit)
    'search_lang' => 'de',     // bevorzugte Sprache der Ergebnisse
    'ui_lang'     => 'de-DE',

    // off | moderate | strict
    'safesearch' => 'moderate',

    // Ergebnisse pro Seite (1 bis 20) und max. Seiten (Brave erlaubt 10)
    'results_per_page' => 10,
    'max_pages'        => 5,

    // Ergebnisse in neuem Tab öffnen?
    'open_in_new_tab' => false,

    // Wie lange man eingeloggt bleibt (Tage)
    'session_days' => 30,

    // Name der Suche (Titel, Logo)
    'site_name' => 'Suche',
];
