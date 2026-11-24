# Private Suche

Eine kleine, werbefreie Suchmaschine für den privaten Gebrauch. Läuft auf normalem Shared Webhosting (PHP 8+, cURL) und holt die Ergebnisse über die **Brave Search API**.

- Ergebnisse mit Titel, URL, Snippet und Favicon
- Login mit mehreren Nutzern: wer sich als Erster anlegt, wird Admin und kann weitere Nutzer anlegen
- Dark Mode (automatisch nach System oder per Knopf)
- Kurzer Datei-Cache (Standard 15 Minuten) und ein Monatslimit, damit das Freiguthaben nie überschritten wird
- Kein Tracking, keine externen Skripte oder Schriften, strenge Content Security Policy
- Lässt sich im Browser als Standardsuchmaschine eintragen (OpenSearch)

## Ordnerstruktur

```
suche/                       ← kommt auf den Webspace
├── index.php                ← einzige Seite, alles läuft hierüber
├── .htaccess                ← Sicherheit, noindex, sperrt private/
├── assets/
│   ├── style.css
│   ├── app.js
│   └── favicon.svg
└── private/                 ← NICHT öffentlich (siehe unten)
    ├── .htaccess            ← "Require all denied"
    ├── config.example.php   ← Vorlage für config.php
    ├── src/                 ← PHP-Logik (Brave, Login, Cache, …)
    ├── views/               ← HTML-Vorlagen
    ├── data/                ← wird automatisch angelegt (Nutzer, Zähler)
    ├── cache/               ← wird automatisch angelegt
    └── sessions/            ← wird automatisch angelegt
```

## 1. Brave API-Key holen

1. Auf <https://api-dashboard.search.brave.com> registrieren.
2. Unter **Plans** den **Search**-Plan wählen und eine Kreditkarte hinterlegen (Brave nutzt sie zur Missbrauchsvermeidung).
3. **Wichtig:** Im Dashboard prüfen, dass die **automatische Aufladung (Auto-Reload) aus** ist. Dann zahlst du nur, wenn du selbst Guthaben kaufst.
4. Unter **API Keys** einen Key erzeugen und kopieren.

Die $5 Freiguthaben pro Monat reichen für ca. 1.000 Suchen.

## 2. Hochladen (Netcup Webhosting)

1. Im Netcup **WCP / Plesk** unter *PHP-Einstellungen* PHP **8.1 oder neuer** wählen (cURL ist standardmäßig aktiv).
2. Den Ordner `suche/` per FTP/SFTP oder Plesk-Dateimanager nach `httpdocs/` hochladen, also `httpdocs/suche/`.
   Die Suche ist dann unter `https://deine-domain.de/suche/` erreichbar. Wenn sie direkt unter der Domain laufen soll, lädst du den **Inhalt** von `suche/` direkt nach `httpdocs/`.
3. **Empfohlen:** Den Ordner `private/` **außerhalb** von `httpdocs` ablegen, z. B. als `suche-private/` daneben:
   ```
   /httpdocs/suche/index.php …
   /suche-private/config.php …
   ```
   und dann in `index.php` die Zeile ändern zu:
   ```php
   define('PRIVATE_DIR', dirname(__DIR__, 2) . '/suche-private');
   ```
   (`dirname(__DIR__, 2)` geht von `httpdocs/suche` zwei Ebenen hoch.) Lässt du `private/` im Webroot, schützt ihn die `.htaccess`. Das klappt bei Netcup auch.
4. Unbedingt HTTPS nutzen (Let's Encrypt ist im Panel mit einem Klick aktivierbar).

## 3. API-Key eintragen

1. `private/config.example.php` kopieren und die Kopie `config.php` nennen (im selben Ordner).
2. In `config.php` den Key eintragen:
   ```php
   'brave_api_key' => 'BSA…dein-key…',
   ```
3. Optional anpassen: `cache_ttl` (Sekunden), `monthly_limit`, `country`, `search_lang`, `safesearch`, `results_per_page`, `site_name`.

## 4. Einrichten

1. `https://deine-domain.de/suche/` aufrufen.
2. Beim ersten Aufruf erscheint **„Einrichtung“**: Benutzernamen und Passwort wählen. Dieser Account ist **Admin**.
3. Oben rechts auf deinen Buchstaben, dann **Verwaltung**: dort weitere Nutzer anlegen, Passwörter zurücksetzen, Rollen ändern, löschen und den API-Verbrauch ansehen.

**Prüfen, ob private/ wirklich gesperrt ist:** `https://deine-domain.de/suche/private/config.php` aufrufen, dort muss **403 Forbidden** kommen.

## Als Standardsuche im Browser

- **Firefox:** Seite öffnen, in der Adressleiste rechts auf das Lupen-Plus klicken oder unter *Einstellungen → Suche* hinzufügen.
- **Chrome/Edge:** Seite einmal benutzen, dann unter *Einstellungen → Suchmaschinen verwalten* als Standard festlegen. Oder manuell mit der URL `https://deine-domain.de/suche/?q=%s`.

## Gut zu wissen

- **Cache:** Laut Brave-AGB darf man Ergebnisse nur kurzfristig zwischenspeichern. Daher Standard 15 Minuten. Das fängt Neuladen, Zurück-Button und Blättern ab.
- **Monatslimit:** Bei `monthly_limit` (Standard 900) stoppt die Suche bis zum Monatsersten. So kommst du nie über das Freiguthaben.
- **Sicherheit:** Passwörter werden mit `password_hash` gespeichert. Nach 5 falschen Logins ist die IP 15 Minuten gesperrt. Ändert jemand sein Passwort, werden andere Geräte ausgeloggt. Alle Formulare haben CSRF-Schutz.
- **Datenschutz:** Brave sieht die Suchanfragen (aber nicht, wer genau sucht, nur deinen Server). Favicons werden von Braves Bildproxy geladen, Links öffnen ohne Referrer.
- **Backup:** Wichtig ist nur `private/config.php` und `private/data/` (Nutzer und Zähler).

## Fehlerbehebung

| Meldung | Ursache |
|---|---|
| „config.php fehlt“ | `config.example.php` nicht nach `config.php` kopiert |
| „API-Key wird nicht akzeptiert“ / „abgelehnt“ | Key falsch kopiert oder kein Plan aktiv |
| „Monatslimit erreicht“ | `monthly_limit` in der Config erhöhen oder bis zum 1. warten |
| „Ordner … kann nicht angelegt werden“ | Schreibrechte für `private/` fehlen (im Dateimanager auf 755 setzen) |
| weiße Seite / 500 | PHP-Version unter 8.0, im Panel umstellen. Details im Fehlerlog des Hostings |
