<?php
/* Suchmaschine – Sprache: Deutsch oder Englisch, je nach Browser oder Auswahl.
   Die Texte stehen im Code auf Deutsch, hier steht die englische Fassung. */
defined('SM_VERSION') || exit;

function smLang(): string
{
    static $lang = null;
    if ($lang !== null) return $lang;
    $c = $_COOKIE['sm_lang'] ?? '';
    if ($c === 'de' || $c === 'en') return $lang = $c;
    $al = strtolower(trim((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')));
    return $lang = ($al === '' || strncmp($al, 'de', 2) === 0) ? 'de' : 'en';
}

function smLangCookie(string $l): void
{
    setcookie('sm_lang', $l, ['expires' => time() + 365 * 86400, 'path' => smBase(), 'secure' => smIsHttps(), 'httponly' => true, 'samesite' => 'Lax']);
}

/** Übersetzt einen deutschen Text (bei Englisch) und setzt Werte für %s/%d ein */
function t(string $de, ...$args): string
{
    static $en = null;
    $s = $de;
    if (smLang() === 'en') {
        if ($en === null) $en = smEnglish();
        $s = $en[$de] ?? $de;
    }
    return $args ? vsprintf($s, $args) : $s;
}

function smEnglish(): array
{
    return [
        // Konten und Anmeldung
        'Der Name muss 2 bis 30 Zeichen lang sein.' => 'The name must be 2 to 30 characters long.',
        'Der Name enthält unzulässige Zeichen.' => 'The name contains characters that are not allowed.',
        'Das Passwort muss mindestens 8 Zeichen lang sein.' => 'The password must be at least 8 characters long.',
        'Das Passwort ist zu lang.' => 'The password is too long.',
        'Die beiden Passwörter stimmen nicht überein.' => 'The two passwords do not match.',
        'Die Suchmaschine ist schon eingerichtet.' => 'The search engine is already set up.',
        'Bitte Name und Passwort eingeben.' => 'Please enter name and password.',
        'Zu viele Fehlversuche. Bitte in %s noch einmal versuchen.' => 'Too many failed attempts. Please try again in %s.',
        '%d Minuten' => '%d minutes',
        '%d Sekunden' => '%d seconds',
        'Name oder Passwort stimmt nicht.' => 'Name or password is wrong.',
        'Es gibt schon zu viele Konten.' => 'There are too many accounts already.',
        'Diesen Namen gibt es schon.' => 'This name already exists.',
        'Du kannst dich nicht selbst löschen.' => 'You cannot delete yourself.',
        'Dieses Konto gibt es nicht.' => 'This account does not exist.',
        'Es muss immer mindestens einen Administrator geben.' => 'There must always be at least one administrator.',

        // Länder
        'Alle Regionen' => 'All regions', 'Argentinien' => 'Argentina', 'Australien' => 'Australia', 'Österreich' => 'Austria',
        'Belgien' => 'Belgium', 'Brasilien' => 'Brazil', 'Kanada' => 'Canada', 'Chile' => 'Chile', 'China' => 'China',
        'Dänemark' => 'Denmark', 'Finnland' => 'Finland', 'Frankreich' => 'France', 'Deutschland' => 'Germany',
        'Hongkong' => 'Hong Kong', 'Indien' => 'India', 'Indonesien' => 'Indonesia', 'Italien' => 'Italy', 'Japan' => 'Japan',
        'Südkorea' => 'South Korea', 'Malaysia' => 'Malaysia', 'Mexiko' => 'Mexico', 'Niederlande' => 'Netherlands',
        'Neuseeland' => 'New Zealand', 'Norwegen' => 'Norway', 'Philippinen' => 'Philippines', 'Polen' => 'Poland',
        'Portugal' => 'Portugal', 'Russland' => 'Russia', 'Saudi-Arabien' => 'Saudi Arabia', 'Südafrika' => 'South Africa',
        'Spanien' => 'Spain', 'Schweden' => 'Sweden', 'Schweiz' => 'Switzerland', 'Taiwan' => 'Taiwan', 'Türkei' => 'Turkey',
        'Vereinigtes Königreich' => 'United Kingdom', 'Vereinigte Staaten' => 'United States',

        // Zeitraum
        'Beliebige Zeit' => 'Any time', 'Letzte 24 Stunden' => 'Past 24 hours', 'Letzte Woche' => 'Past week',
        'Letzter Monat' => 'Past month', 'Letztes Jahr' => 'Past year',

        // Brave
        'Es ist noch kein Brave API-Key eingetragen. Ein Administrator kann ihn unter Verwaltung → Einstellungen hinterlegen.' => 'No Brave API key has been entered yet. An administrator can add it under Admin → Settings.',
        'Das Monatslimit von %d Suchen ist erreicht. Am Monatsersten geht es weiter, oder ein Administrator erhöht das Limit.' => 'The monthly limit of %d searches has been reached. Searching resumes on the first of the month, or an administrator can raise the limit.',
        'Brave hat zu lange gebraucht. Bitte gleich noch einmal versuchen.' => 'Brave took too long. Please try again in a moment.',
        'Brave ist gerade nicht erreichbar. Kann der Server Verbindungen nach außen aufbauen?' => 'Brave cannot be reached right now. Is the server allowed to make outgoing connections?',
        'Brave nimmt den API-Key nicht an. Stimmt der Key, und ist im Brave-Dashboard ein Plan aktiv?' => 'Brave does not accept the API key. Is the key correct, and is a plan active in the Brave dashboard?',
        'Das Brave-Guthaben ist aufgebraucht.' => 'The Brave credit is used up.',
        'Brave hat die Anfrage abgelehnt.' => 'Brave rejected the request.',
        'Zu viele Anfragen oder das Kontingent bei Brave ist erschöpft. Kurz warten oder im Brave-Dashboard nachsehen.' => 'Too many requests, or the Brave quota is used up. Wait a moment or check the Brave dashboard.',
        'Brave hat gerade technische Probleme. Bitte später noch einmal versuchen.' => 'Brave is having technical problems. Please try again later.',
        'Unerwartete Antwort von Brave (HTTP %d).' => 'Unexpected answer from Brave (HTTP %d).',

        // Daten
        'Der Ordner data/ konnte nicht angelegt werden. PHP braucht Schreibrechte im Verzeichnis der Suchmaschine.' => 'The folder data/ could not be created. PHP needs write access to the search engine\'s directory.',
        'In den Ordner data/ kann nicht geschrieben werden. Bitte die Schreibrechte prüfen.' => 'Cannot write to the folder data/. Please check the permissions.',
        'Die PHP-Erweiterung pdo_sqlite fehlt. Ohne sie kann die Suchmaschine nichts speichern.' => 'The PHP extension pdo_sqlite is missing. The search engine cannot store anything without it.',
        'Die Datenbank konnte nicht geöffnet werden: ' => 'The database could not be opened: ',

        // Seiten
        'Bitte den Brave API-Key eintragen.' => 'Please enter the Brave API key.',
        'Fertig! Die Suchmaschine ist eingerichtet und du bist Administrator.' => 'Done! The search engine is set up and you are the administrator.',
        'Einrichten' => 'Set up',
        'Das Formular war veraltet. Bitte noch einmal versuchen.' => 'The form was out of date. Please try again.',
        'Anmelden' => 'Sign in',
        'Eigene Suchmaschine ohne Werbung' => 'Your own search engine without ads',
        'Alle' => 'All', 'Bilder' => 'Images', 'News' => 'News', 'Videos' => 'Videos',
        'Für diese Stunde sind genug Suchen ohne Anmeldung gelaufen. Bitte später wieder, oder anmelden.' => 'Enough searches without an account for this hour. Please come back later, or sign in.',
        'Da ist intern etwas schiefgelaufen. Die Einzelheiten stehen im Fehlerprotokoll des Servers.' => 'Something went wrong internally. The details are in the server\'s error log.',
        'Gespeichert.' => 'Saved.',
        'Das bisherige Passwort stimmt nicht.' => 'The current password is wrong.',
        'Passwort geändert. Andere Geräte sind jetzt abgemeldet.' => 'Password changed. Other devices are signed out now.',
        'Einstellungen' => 'Settings',
        'Konten' => 'Accounts', 'Verbrauch' => 'Usage', 'Update' => 'Update',
        'Konto angelegt.' => 'Account created.',
        'Konto gelöscht.' => 'Account deleted.',
        'Passwort gesetzt.' => 'Password set.',
        'Der API-Key funktioniert.' => 'The API key works.',
        'Achtung: %s ist von außen abrufbar!' => 'Warning: %s can be accessed from outside!',
        'Der Server kann sich selbst nicht erreichen, der Test war nicht möglich. Bitte von Hand prüfen.' => 'The server cannot reach itself, so the test was not possible. Please check by hand.',
        'Alles dicht: data/ und app/ sind von außen gesperrt.' => 'All tight: data/ and app/ are blocked from outside.',
        'Version %s ist verfügbar.' => 'Version %s is available.',
        'Es ist schon die neueste Version installiert.' => 'The latest version is already installed.',
        'Update eingespielt.' => 'Update installed.',
        'Die vorige Version ist wieder da.' => 'The previous version is back.',
        'Unbekannte Aktion.' => 'Unknown action.',
        'Verwaltung' => 'Admin',

        // Update
        'GitHub ist gerade nicht erreichbar (HTTP %d).' => 'GitHub cannot be reached right now (HTTP %d).',
        'Die Update-Suche ist in data/config.php abgeschaltet.' => 'Update checks are switched off in data/config.php.',
        'Die Antwort von GitHub war unverständlich.' => 'The answer from GitHub could not be understood.',
        'Im neuesten Release fehlt die Datei suchmaschine-install.php.' => 'The latest release does not contain suchmaschine-install.php.',
        'Diese Datei ließ sich nicht schreiben: %s. Es wurde nichts verändert.' => 'This file could not be written: %s. Nothing has been changed.',
        'PHP darf %s nicht überschreiben.' => 'PHP is not allowed to overwrite %s.',
        'Die heruntergeladene Datei ist beschädigt. Es wurde nichts verändert.' => 'The downloaded file is damaged. Nothing has been changed.',
        'Die laufende Version konnte nicht gesichert werden. Es wurde nichts verändert.' => 'The running version could not be backed up. Nothing has been changed.',
        'Es gibt keine gesicherte Version zum Zurückholen.' => 'There is no saved version to go back to.',

        // Zahlen
        ' Mio.' => 'M', ' Tsd.' => 'K',

        // Suchfeld, Seiten
        'Seiten' => 'Pages', 'Zurück' => 'Previous', 'Weiter' => 'Next',
        'Aus dem Verlauf entfernen' => 'Remove',
        'Suchen oder Adresse eingeben' => 'Search the web',
        'Suchbegriff' => 'Search term',
        'Eingabe löschen' => 'Clear',
        'Suchen' => 'Search',
        'Auf gut Glück' => 'Feeling lucky',

        // Verwaltung
        'Der Webserver beachtet die .htaccess-Dateien nicht. Meist liefert nginx die Dateien direkt aus. Bei Plesk (z. B. Netcup): „Apache & nginx-Einstellungen“ öffnen und „Statische Dateien direkt durch nginx bereitstellen“ bzw. „Intelligente Verarbeitung statischer Dateien“ ausschalten. Danach hier neu prüfen.' => 'The web server ignores the .htaccess files. Usually nginx serves the files directly. On Plesk: open "Apache & nginx Settings" and turn off "Serve static files directly by nginx" or "Smart static files processing". Then check again here.',
        'du' => 'you',
        'Administrator' => 'Administrator',
        'Benutzer' => 'User',
        'seit %s' => 'since %s',
        'Passwort' => 'Password',
        'Neues Passwort' => 'New password',
        'Setzen' => 'Set',
        'Admin entziehen' => 'Remove admin',
        'Zum Admin machen' => 'Make admin',
        'Das Konto „%s“ wirklich löschen?' => 'Really delete the account "%s"?',
        'Löschen' => 'Delete',
        'Neues Konto' => 'New account',
        'Name' => 'Name',
        'mindestens 8 Zeichen' => 'at least 8 characters',
        'Administrator (darf Konten und Einstellungen ändern)' => 'Administrator (may change accounts and settings)',
        'Konto anlegen' => 'Create account',
        'Brave API-Key' => 'Brave API key',
        'Hinterlegt: %s' => 'Stored: %s',
        'Noch kein Key hinterlegt.' => 'No key stored yet.',
        'Neuer Key (leer lassen = behalten)' => 'New key (leave empty to keep)',
        'Key testen' => 'Test key',
        'Brave-Dashboard öffnen' => 'Open Brave dashboard',
        'Allgemein' => 'General',
        'Name der Suchmaschine' => 'Name of the search engine',
        'Treffer pro Seite' => 'Results per page',
        'Höchstens Suchen pro Monat' => 'Maximum searches per month',
        'Das Freiguthaben reicht für etwa 1.000. 0 = ohne Grenze.' => 'The free credit covers about 1,000. 0 = no limit.',
        'Zwischenspeicher (Minuten)' => 'Cache (minutes)',
        'Gleiche Suche in dieser Zeit kostet nichts. Brave erlaubt nur kurzes Speichern.' => 'The same search within this time costs nothing. Brave only allows short-term storage.',
        'Voreinstellungen für alle' => 'Defaults for everyone',
        'Jedes Konto kann das unter Einstellungen für sich ändern.' => 'Every account can change this for itself under Settings.',
        'Region' => 'Region',
        'Sprache der Ergebnisse' => 'Language of results',
        'SafeSearch' => 'SafeSearch',
        'Streng' => 'Strict', 'Mittel' => 'Moderate', 'Aus' => 'Off',
        'Suchen ohne Anmeldung' => 'Searching without an account',
        'Jeder mit der Adresse darf suchen' => 'Anyone with the address may search',
        'Vorsicht: Jede Suche geht von deinem Guthaben ab. Das Monatslimit gilt trotzdem.' => 'Careful: every search uses your credit. The monthly limit still applies.',
        'Dann höchstens Suchen pro Stunde und Besucher' => 'Then at most searches per hour and visitor',
        'Speichern' => 'Save',
        'Dieser Monat' => 'This month',
        'Gezählt werden nur echte Anfragen an Brave. Treffer aus dem Zwischenspeicher sind kostenlos. Den genauen Stand deines Guthabens zeigt das Brave-Dashboard.' => 'Only real requests to Brave are counted. Results from the cache are free. The Brave dashboard shows your exact credit.',
        'Verlauf' => 'History',
        'Noch keine Suchen.' => 'No searches yet.',
        'Monat' => 'Month',
        'Gesamt' => 'Total',
        'Version' => 'Version',
        'Installiert: %s' => 'Installed: %s',
        'Neueste: %s' => 'Latest: %s',
        'Was ist neu?' => 'What\'s new?',
        'Nach Updates suchen' => 'Check for updates',
        'Jetzt aktualisieren' => 'Update now',
        'Zurück zu Version %s?' => 'Go back to version %s?',
        'Zurück zu %s' => 'Back to %s',
        'Updates automatisch einspielen (höchstens einmal am Tag)' => 'Install updates automatically (at most once a day)',
        'Updates kommen von GitHub und werden per Prüfsumme kontrolliert. Deine Daten in data/ bleiben unangetastet.' => 'Updates come from GitHub and are verified by checksum. Your data in data/ is left untouched.',
        'Schutz der Daten' => 'Data protection',
        'Jetzt prüfen' => 'Check now',

        // Startseite, Grundgerüst
        'Tipp: <kbd>/</kbd> springt ins Suchfeld' => 'Tip: <kbd>/</kbd> jumps to the search box',
        'Zur Startseite' => 'Home',
        'Design umschalten' => 'Switch theme',
        'Design: automatisch' => 'Theme: automatic',
        'Design: hell' => 'Theme: light',
        'Design: dunkel' => 'Theme: dark',
        'Konto' => 'Account',
        'Abmelden' => 'Sign out',
        'Ohne Werbung, ohne Tracking' => 'No ads, no tracking',
        'Ergebnisse von <a href="https://search.brave.com" rel="noopener noreferrer">Brave Search</a>' => 'Results by <a href="https://search.brave.com" rel="noopener noreferrer">Brave Search</a>',
        'Anfragen an Brave in diesem Monat' => 'Requests to Brave this month',
        '%d von %d Suchen' => '%d of %d searches',
        'Selbst hosten' => 'Host your own',
        'Ohne Anmeldung suchen' => 'Search without an account',

        // Ergebnisse
        'Art der Suche' => 'Search type',
        'Zeitraum' => 'Time',
        'Die Suche hat nicht geklappt.' => 'The search did not work.',
        'Noch einmal versuchen' => 'Try again',
        'Ergebnisse für <strong>%s</strong>' => 'Showing results for <strong>%s</strong>',
        'Stattdessen suchen nach <a href="%s">%s</a>' => 'Search instead for <a href="%s">%s</a>',
        'Zu <strong>%s</strong> wurde nichts gefunden.' => 'Your search for <strong>%s</strong> did not match anything.',
        'Stimmt die Schreibweise?' => 'Make sure all words are spelled correctly.',
        'Andere oder allgemeinere Begriffe probieren.' => 'Try different or more general keywords.',
        'Ohne Zeitraum suchen' => 'Search without time limit',
        'Kurzinfo' => 'Overview',
        'Website' => 'Website',
        'Top-Meldungen' => 'Top stories',
        'Mehr News' => 'More news',
        'Mehr Videos' => 'More videos',
        'Ähnliche Fragen' => 'People also ask',
        'Diskussionen und Foren' => 'Discussions and forums',
        '%d Antworten' => '%d answers',
        'Bildvorschau' => 'Image preview',
        'Schließen' => 'Close',
        'Seite besuchen' => 'Visit page',
        'Bild in Originalgröße' => 'Full-size image',
        'Vorheriges Bild' => 'Previous image',
        'Nächstes Bild' => 'Next image',
        'Eilmeldung' => 'Breaking',
        '%s Aufrufe' => '%s views',
        'Aus dem Zwischenspeicher, kostet keine Suche.' => 'From the cache, did not cost a search.',

        // Einstellungen
        'Suche' => 'Search',
        'Treffer in neuem Tab öffnen' => 'Open results in a new tab',
        'Letzte Suchen als Vorschläge zeigen' => 'Suggest recent searches',
        'Der Verlauf bleibt nur in diesem Browser, der Server merkt sich nichts.' => 'The history stays in this browser only, the server keeps nothing.',
        'Verlauf gelöscht' => 'History deleted',
        'Verlauf auf diesem Gerät löschen' => 'Delete history on this device',
        'Passwort ändern' => 'Change password',
        'Bisheriges Passwort' => 'Current password',
        'Neues Passwort wiederholen' => 'Repeat new password',
        'Als Standardsuche im Browser' => 'As default search in your browser',
        'Firefox: in der Adressleiste auf das Lupen-Symbol mit Plus klicken. Chrome und Edge: Einstellungen → Suchmaschinen → hinzufügen, mit dieser Adresse:' => 'Firefox: click the magnifier icon with a plus in the address bar. Chrome and Edge: Settings → Search engines → add, with this address:',
        'Klicken zum Kopieren' => 'Click to copy',
        'Kopiert' => 'Copied',

        // Einrichten
        'Willkommen' => 'Welcome',
        'Noch zwei Schritte, dann hast du deine eigene Suchmaschine ohne Werbung.' => 'Two more steps and you have your own search engine without ads.',
        'Dein Konto' => 'Your account',
        'Wer sich hier als Erster einträgt, wird Administrator und kann danach weitere Konten anlegen.' => 'Whoever signs up here first becomes the administrator and can then create more accounts.',
        'Passwort wiederholen' => 'Repeat password',
        'Kostenlos auf <a href="https://api-dashboard.search.brave.com" target="_blank" rel="noopener noreferrer">api-dashboard.search.brave.com</a>: anmelden, den Plan „Search“ wählen (enthält jeden Monat Guthaben für etwa 1.000 Suchen), unter „API Keys“ einen Key erzeugen und hier einfügen. Automatisches Aufladen dort ausgeschaltet lassen.' => 'Free at <a href="https://api-dashboard.search.brave.com" target="_blank" rel="noopener noreferrer">api-dashboard.search.brave.com</a>: sign up, choose the "Search" plan (includes monthly credit for about 1,000 searches), create a key under "API Keys" and paste it here. Leave automatic top-up switched off there.',
        'API-Key' => 'API key',
        'Der Key wird gleich mit einer Testsuche geprüft und bleibt nur auf deinem Server.' => 'The key is checked with a test search right away and stays on your server only.',
    ];
}
