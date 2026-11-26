# Selbst bauen: vom Quellcode zur einen Installationsdatei

*English summary at the end.*

Nutzer bekommen genau **eine Datei**: `suchmaschine-install.php`. Hochladen, im Browser aufrufen, fertig. Im Quellcode ist das Programm dagegen auf viele kleine Dateien verteilt, damit man sich zurechtfindet. Diese Anleitung zeigt, wie aus dem Quellcode die eine Datei wird, und zwar so, dass jeder es auf dem eigenen Rechner nachmachen und mit dem offiziellen Release vergleichen kann.

## Kurzfassung

```sh
git clone https://github.com/burnersen/suchmaschine.git
cd suchmaschine
php tools/build-release.php
```

Danach liegt in `dist/` die fertige `suchmaschine-install.php`. Mehr braucht es nicht: kein Composer, kein npm, kein Docker, keine Internetverbindung.

## Was man braucht

- **PHP 8.0 oder neuer** auf der Kommandozeile (`php -v`)
- Erweiterungen: `pdo_sqlite`, `curl`, `mbstring`, `openssl` (fast immer schon dabei). Für die Tests mit Unterschrift zusätzlich `sodium`, für das ZIP `zip`, fürs App-Symbol `gd`. Fehlt etwas davon, sagt das Skript Bescheid.
- Sonst nichts. Kein Build-Werkzeug, keine Pakete aus dem Netz.

## Was beim Bauen passiert

`php tools/build-release.php` macht der Reihe nach:

| Schritt | Was | Wo im Code |
|---|---|---|
| 1 | Version aus `index.php` lesen (`define('SM_VERSION', …)`) | `tools/build-release.php` |
| 2 | Dateiliste: `index.php`, `LICENSE` und alles unter `app/`, nach Namen sortiert | ebenda |
| 3 | Jede PHP-Datei mit `php -l` auf Syntaxfehler prüfen | ebenda |
| 4 | Tests: `tests/run.php` (Rechner, Datum, Sicherheit, Übersetzungen …) und `tests/smoke.php` (Suchmaschine wirklich starten, einrichten, jede Seite aufrufen) | `tests/` |
| 5 | Installer zusammensetzen: Vorlage `tools/installer-template.php`, dahinter eine Zeile pro Datei | ebenda |
| 6 | Fertigen Installer zurücklesen, jede Prüfsumme kontrollieren und ihn mit `tests/smoke.php` echt installieren | ebenda |
| 7 | Unterschreiben, falls ein geheimer Schlüssel da ist (siehe unten) | ebenda |
| 8 | ZIP zum Entpacken von Hand und `SHA256SUMS` schreiben | ebenda |

Bricht ein Schritt ab, steht dabei, warum. Mit `--skip-tests` geht es schneller (nur zum Ausprobieren, nie für ein Release).

## So ist die Installationsdatei aufgebaut

```
<?php
… Installer: prüft PHP, zeigt eine Seite mit „Jetzt installieren“, schreibt die Dateien …
__halt_compiler();
index.php<TAB>sha256-Prüfsumme<TAB>Inhalt als Base64
LICENSE<TAB>…<TAB>…
app/lib/auth.php<TAB>…<TAB>…
…
```

- Oben steht ganz normales PHP (`tools/installer-template.php`), lesbar mit jedem Editor.
- `__halt_compiler();` beendet das PHP-Programm. Alles danach liest PHP nicht als Code, der Installer liest es als Daten.
- Pro Datei eine Zeile: Pfad, SHA-256 des Inhalts, Inhalt in Base64. Der Installer prüft jede Prüfsumme, bevor er schreibt, und schreibt nur nach `index.php`, `LICENSE` und `app/`. Der Ordner `data/` (Konten, Keys, Datenbank) wird nie angefasst.
- Dasselbe Format nimmt die Update-Funktion in der Verwaltung (`smPayloadParse()` in `app/lib/update.php`).

Man kann die Datei also jederzeit auseinandernehmen und nachsehen, was drin ist:

```sh
php -r '$r = file_get_contents("dist/suchmaschine-install.php");
foreach (explode("\n", substr($r, strpos($r, "__halt_compiler();") + 18)) as $l)
    if (trim($l) !== "") echo explode("\t", $l)[0], "\n";'
```

## Mit dem offiziellen Release vergleichen

Der Bau ist **wiederholbar**: gleicher Quellcode ergibt Byte für Byte dieselbe `suchmaschine-install.php` (keine Zeitstempel, feste Reihenfolge). So prüfst du, ob ein Release wirklich aus dem veröffentlichten Quellcode stammt:

```sh
git checkout 1.5.0                      # den Tag des Releases
php tools/build-release.php
sha256sum dist/suchmaschine-install.php # mit SHA256SUMS aus dem Release vergleichen
```

Nur das ZIP kann abweichen, weil ZIP-Dateien das Änderungsdatum der Dateien enthalten.

## Release auf GitHub

`.github/workflows/release.yml` führt **denselben Befehl** aus (`php tools/build-release.php --require-signature`) und hängt die Dateien an das Release. Auslösen: einen Tag wie `1.5.0` pushen oder unter *Actions → Release → Run workflow*. Text fürs Release: `docs/release-notes/<Version>.md`.

`.github/workflows/check.yml` baut und testet bei jedem Push und Pull Request, mit PHP 8.0 und 8.3.

## Updates unterschreiben (empfohlen)

Die Prüfsummen schützen vor kaputten Downloads, aber nicht davor, dass jemand ein Release austauscht. Dafür gibt es eine Unterschrift (Ed25519):

1. Einmalig ein Schlüsselpaar erzeugen: `php tools/sign-key.php`
2. Den **geheimen** Schlüssel auf GitHub speichern: *Settings → Secrets and variables → Actions → New repository secret*, Name `SM_SIGN_KEY`. Niemals committen oder weitergeben.
3. Den **öffentlichen** Schlüssel in `app/lib/update.php` bei `const SM_UPDATE_PUBKEY = '';` eintragen und committen.
4. Ab dem nächsten Release legt der Workflow `suchmaschine-install.php.sig` daneben. Installationen mit dieser Version spielen danach nur noch Updates mit gültiger Unterschrift ein.

Reihenfolge beachten: erst das Secret, dann den öffentlichen Schlüssel. Fehlt das Secret, bricht der Release-Workflow absichtlich ab, statt ein Release zu bauen, das keiner einspielen kann. Lokal bauen geht auch ohne Schlüssel, die Datei lässt sich ganz normal installieren.

## Wo was im Quellcode steht

```
index.php            Einstieg: Version, Pfade, lädt app/lib/, startet den Ablauf
app/lib/             Funktionen, eine Datei pro Thema
  router.php           welche Seite bei welcher Adresse (?a=…)
  page_*.php           die Seiten: Suche, Werkzeuge, Lesemodus, Einstellungen, Verwaltung
  serve.php            CSS, JavaScript, Symbole, Manifest, OpenSearch ausliefern
  util.php db.php      Helfer, Datenbank (SQLite), Einstellungen, Zwischenspeicher
  auth.php             Konten, Anmeldung, Sitzungen, Login-Token, Schutz gegen Durchprobieren
  providers.php brave.php   Suchdienste, Lastverteilung, Ausweichen, Verbrauch
  filters.php domains.php bangs.php   Treffer filtern und sortieren, eigene Domain-Listen, Bangs und Linsen
  instant.php          Sofort-Antworten: Rechner, Einheiten, Währungen, Wetter, Datum, Zufall, Kurzlinks
  prefs.php look.php branding.php   persönliche Einstellungen, Aussehen, Logo und App-Symbol
  qr.php imgproxy.php reader.php    QR-Codes, Bild-Proxy, Lesemodus
  backup.php update.php             Sicherung, Updates
  lang.php             Sprache wählen, t('…') übersetzt
app/lang/en.php      englische Texte (links Deutsch wie im Code, rechts Englisch)
app/views/           HTML-Vorlagen, Teile mit _ am Anfang werden eingebunden
app/assets/          CSS, JavaScript, Schriften, Töne, Symbole
tests/               run.php (Funktionen), smoke.php (alles einmal durchklicken)
tools/               build-release.php, installer-template.php, sign-key.php
```

Eine neue PHP-Datei in `app/lib/` muss in `index.php` in die Liste der geladenen Dateien. Alles unter `app/` landet automatisch im Installer.

---

## English summary

Users get **one file**, `suchmaschine-install.php`. To build it yourself: `git clone …`, then `php tools/build-release.php` (PHP 8.0+, no other tools). The script checks syntax, runs `tests/run.php` and `tests/smoke.php`, packs `index.php`, `LICENSE` and everything under `app/` behind `__halt_compiler();` as one line per file (path, SHA-256, base64), reads it back, installs it for real in a temporary folder, optionally signs it (`SM_SIGN_KEY`, Ed25519, keys from `php tools/sign-key.php`) and writes a ZIP plus `SHA256SUMS`. The build is reproducible: the same source gives the same installer byte for byte, so anyone can compare their build with an official release. The GitHub release workflow runs exactly the same command.
