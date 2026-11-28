<p align="center"><img src="app/assets/icon.svg" width="88" alt=""></p>

<h1 align="center">Suchmaschine ohne Werbung</h1>

<p align="center"><b>Deine eigene Suchmaschine. Ohne Werbung, ohne Tracking, auf deinem eigenen Webspace.</b><br>
Schnelle Ergebnisse von Brave Search, und wenn Brave nicht kann, sofort die Suchmaschine deiner Wahl.<br>
Bei normaler privater Nutzung komplett in den kostenlosen Monatskontingenten, mit eingebauter Bremse vor dem Limit.</p>

<p align="center"><a href="README.en.md">English version</a> · <a href="../../releases/latest">Neueste Version herunterladen</a> · <a href="../../releases">Was sich wann geändert hat</a></p>

<p align="center"><img src="docs/de/ergebnisse.png" alt="Ergebnisseite mit Treffern, Top-Meldungen und Kurzinfo" width="820"></p>

Einfach `suchmaschine-install.php` hochladen, im Browser aufrufen, Konto anlegen, optional den Brave-Key einfügen. Das ist die ganze Installation.

Auf dem Server laufen PHP und SQLite, im Browser schlichtes JavaScript und CSS. Keine Frameworks, keine Abhängigkeiten, kein Build-Schritt, keine MySQL-Datenbank. Läuft auf ganz normalem Shared Webhosting.

## Bilder

<table>
  <tr>
    <td align="center"><img src="docs/de/start.png" width="400" alt="Startseite"><br><sub>Startseite</sub></td>
    <td align="center"><img src="docs/de/dunkel.png" width="400" alt="Dunkles Design"><br><sub>Dunkles Design</sub></td>
  </tr>
  <tr>
    <td align="center"><img src="docs/de/bilder.png" width="400" alt="Bildersuche"><br><sub>Bildersuche</sub></td>
    <td align="center"><img src="docs/de/bildvorschau.png" width="400" alt="Bildvorschau"><br><sub>Bildvorschau</sub></td>
  </tr>
  <tr>
    <td align="center"><img src="docs/de/news.png" width="400" alt="News"><br><sub>News</sub></td>
    <td align="center"><img src="docs/de/videos.png" width="400" alt="Videos"><br><sub>Videos</sub></td>
  </tr>
  <tr>
    <td align="center"><img src="docs/de/fragen.png" width="400" alt="Ähnliche Fragen"><br><sub>Ähnliche Fragen</sub></td>
    <td align="center"><img src="docs/de/vorschlaege.png" width="400" alt="Vorschläge aus den letzten Suchen"><br><sub>Vorschläge aus den eigenen letzten Suchen</sub></td>
  </tr>
  <tr>
    <td align="center"><img src="docs/de/verwaltung.png" width="400" alt="Verwaltung: Einstellungen"><br><sub>Verwaltung: Key, Name, Logo, Farbe</sub></td>
    <td align="center"><img src="docs/de/konten.png" width="400" alt="Verwaltung: Konten"><br><sub>Verwaltung: Konten</sub></td>
  </tr>
</table>

<p align="center">
  <img src="docs/de/handy-start.png" width="200" alt="Handy: Startseite">
  <img src="docs/de/handy-ergebnisse.png" width="200" alt="Handy: Ergebnisse">
  <img src="docs/de/handy-bilder.png" width="200" alt="Handy: Bilder">
</p>

[Kosten](#was-kostet-das) · [Brave einrichten](#brave-einrichten-api-keys-holen) · [Funktionen](#funktionen) · [Gäste und Ersatz-Suchmaschine](#gäste-und-ersatz-suchmaschine) · [Hilfen von Brave](#hilfen-von-brave) · [Bilder groß](#bilder-groß-auch-bei-anderen-suchmaschinen) · [Werkzeuge](#werkzeuge) · [Aussehen](#aussehen-und-nachladen) · [Lesemodus, Rezepte und PDF](#lesemodus-und-pdf) · [Weitere Funktionen](#weitere-funktionen) · [Konten und Token-Login](#konten-und-token-login) · [Tastenkürzel](#tastenkürzel) · [Voraussetzungen](#voraussetzungen) · [Installation](#installation) · [Daten schützen](#daten-schützen) · [Aktualisieren](#aktualisieren) · [Sichern](#sichern) · [Aufbau](#aufbau) · [Selbst bauen](#selbst-bauen) · [Gut zu wissen](#gut-zu-wissen) · [Haftungsausschluss](#haftungsausschluss) · [Lizenz](#lizenz)

## Was kostet das?

Bei normaler privater Nutzung nichts. Brave gibt bei jedem Plan **5 $ Guthaben pro Monat** dazu. Gezählt wird überall in **Requests**, genau wie im Brave-Dashboard:

| Brave-Plan | Wofür | 5 $ reichen für | Bremse hier (Standard) |
|---|---|---|---|
| [Search](https://brave.com/search/api/) | die Suche selbst (Web, Bilder, News, Videos) | 1.000 Requests | 950 |
| Autosuggest | Suchvorschläge beim Tippen | 10.000 Requests | 9.950 |
| Spellcheck | „Meintest du …?“ | 10.000 Requests | 9.950 |
| Answers | KI-Antwort über den Treffern | etwa 80 Requests (Brave rechnet hier auch nach Textmenge) | 70 |

- Nur **Search** ist nötig, die anderen drei sind freiwillig. Ganz ohne Key geht es auch: Dann landet jede Suche direkt bei der Ersatz-Suchmaschine.
- **Bremse:** Pro Plan ein Limit in Requests, voreingestellt knapp unter dem freien Guthaben. Ist es erreicht, hält die Suchmaschine an, **bevor** Kosten entstehen.
- Unter *Verwaltung → Suchdienste* und *Verwaltung → Verbrauch* sieht jeder Plan aus wie im Brave-Dashboard: **Requests**, **Credits remaining**, **Credits used this month** (x $ / 5 $), **Burn rate** und **Runway**. Die Dollar rechnet die Suchmaschine aus den Requests aus (bei Answers mit dem echten Durchschnittspreis, den Brave bei jeder Antwort mitschickt).
- **Requests laut Dashboard:** Trag jederzeit die Zahl aus dem Brave-Dashboard ein, der Zähler übernimmt genau diesen Stand und zählt von da an weiter (z. B. nach einem neuen Server mitten im Monat).
- **Automatisches Aufladen ausgeschaltet lassen.** Doppelt sicher wird es mit einem „Monthly usage limit“ von 5 $ bei jedem Plan im Brave-Dashboard.
- Ein **Zwischenspeicher** von 60 Minuten (mehr erlauben Braves Bedingungen nicht) sorgt dafür, dass Neuladen, Zurück-Knopf und Blättern nichts kosten.
- **Sofort-Antworten** (Rechner, Währungen, Wetter …), **Bangs**, **Werkzeuge**, der **Lesemodus** und alle Suchen, die zur **Ersatz-Suchmaschine** gehen, kosten gar nichts.

Zum Vergleich: Wer 10 Mal am Tag sucht, kommt auf rund 300 Suchen im Monat.

## Brave einrichten (API-Keys holen)

Die Keys trägst du nach der Installation unter **Verwaltung → Suchdienste** ein. Jeder Key (außer bei Answers, da würde schon der Test Geld kosten) wird vorher mit einer Testanfrage geprüft. Keys gehören **nie** in den Code oder ins Git, sie liegen nur in deiner Datenbank im Ordner `data/`.

1. Auf [api-dashboard.search.brave.com](https://api-dashboard.search.brave.com) ein Konto anlegen.
2. Den Plan **„Search“** wählen. Brave möchte eine **Kreditkarte** sehen (gegen Missbrauch), abgerechnet wird über das Guthaben ohne Abo. **Automatische Aufladung ausgeschaltet lassen.**
3. Unter **„API Keys“** einen Key erzeugen (beginnt mit `BSA`) und in der Suchmaschine eintragen.
4. Optional genauso die Pläne **„Autosuggest“**, **„Spellcheck“** und **„Answers“** wählen. Jeder Plan hat **seinen eigenen Key**, den trägst du beim passenden Abschnitt ein.
5. Das Reset-Datum schickt Brave bei jeder Suche mit, ein Stichtag ist nicht nötig.

**Tipp:** Unter *Verwaltung → Suchdienste* legst du außerdem die **Ersatz-Suchmaschine** für Gäste und für Konten ohne eigene Wahl fest.

## Funktionen

- **Web, Bilder, News, Videos** in Reitern, wie man es kennt.
- **Gemischte Ergebnisse** wie bei den großen Suchmaschinen: Top-Meldungen, Videos, „Ähnliche Fragen“ und Diskussionen aus Foren erscheinen zwischen den Treffern, dazu eine **Kurzinfo** am Rand. Was Brave zu einer Suche liefert, wird angezeigt.
- **Ersatz-Suchmaschine nach Wahl**, sofort und ohne Zwischenstopp, wenn Brave nicht kann, und immer für Gäste.
- **Seitenweise oder endlos scrollen**, für jedes Konto einstellbar.
- **Zeitraum-Filter** (letzte Stunde, 24 Stunden, Woche, Monat, Jahr oder **von/bis**), **Sprache und Region** pro Suche, **Wortgenau**, **Rechtschreibkorrektur** mit „Stattdessen suchen nach …“ und „Meintest du …?“, **„Auf gut Glück“**.
- **Bildvorschau** im Großformat mit Pfeiltasten und Wischen zum Blättern, dazu ein **echter Vollbild-Modus** (Knopf oder Taste `F`) und eine **Diashow** (Knopf oder Taste `S`, Sekunden pro Bild wählbar, nach dem letzten Bild wieder von vorne) für Fernseher und große Bildschirme: randlos auf Schwarz, Bedienelemente blenden sich aus. Das Bild kommt dabei in **voller Auflösung über den eigenen Server**, ohne die strengen Sicherheitsregeln aufzuweichen und ohne Suche bei Brave. Für fremde Bildersuchen gibt es das Lesezeichen **„Bilder groß“**.
- **Dateityp-Filter** (PDF, DOCX, XLSX, PPTX, TXT, EPUB, CSV, ODT und mehr, oder jede andere Endung zum Eintippen). Bleibt beim Blättern und Endlos-Scrollen erhalten.
- **Hilfeseite** mit allen Suchoperatoren, die Brave kennt (`site:`, `filetype:`, `intitle:`, `"…"`, `OR`, `NOT`, `-` …), mit Beispielen zum Anklicken und der Übersicht über die eigenen Bangs, Linsen und Domain-Listen.
- **Vorschläge beim Tippen:** aus den eigenen letzten Suchen (bleiben nur im Browser) und, wenn eingerichtet, von Brave.
- **Hell und dunkel**, automatisch nach dem Gerät oder per Knopf.
- **Deutsch und Englisch**, je nach Browser, umschaltbar unten auf der Seite.
- **Für Handy und Rechner**, lässt sich auch als App auf den Startbildschirm legen.
- **Als Standardsuche im Browser** eintragbar (OpenSearch), z. B. in Firefox mit einem Klick.
- **Konten:** Wer sich als Erster einträgt, wird Administrator und kann weitere Konten anlegen, etwa für die Familie. Jedes Konto hat eigene Einstellungen (Region, Sprache, SafeSearch, neuer Tab, Seiten oder endlos, Ersatz-Suchmaschine, Hilfen von Brave, Domain-Listen, Bangs, Linsen).
- **Verwaltung** für den Administrator: Brave mit Keys, Stichtag, Limits und Status, Ersatz-Suchmaschine, Name der Suchmaschine, **eigenes Logo**, **Farbe**, Voreinstellungen für alle, Verbrauch mit Prognose, Kontingente pro Konto, Seite für Gäste offen oder nur mit Anmeldung, Bilder in voller Auflösung an oder aus, Sicherung der Einstellungen.
- **Updates per Knopfdruck:** Die Suchmaschine schaut beim neuesten Release auf GitHub nach, prüft die Datei per Prüfsumme, sichert die laufende Version und spielt die neue ein. Zurück zur vorigen Version geht mit einem Klick. Auf Wunsch einmal am Tag automatisch.
- **Selbsttest:** Die Verwaltung prüft, ob die internen Ordner von außen wirklich gesperrt sind, und warnt sonst.
- **Ohne Werbung, ohne Tracking, ohne fremde Skripte.** Strenge Sicherheitsregeln (Content Security Policy), Links öffnen ohne Referrer, Vorschaubilder kommen über Braves Bild-Proxy oder über den eigenen Server.

## Gäste und Ersatz-Suchmaschine

- **Brave sucht nur für angemeldete Konten.** Gäste können die Seite trotzdem benutzen: Startseite, Bangs, Sofort-Antworten, Werkzeuge und Hilfe. Ihre Suchen gehen **nie über deinen Brave-Key**, sondern sofort zur Ersatz-Suchmaschine. Ob Gäste überhaupt hereindürfen, stellst du unter *Verwaltung → Einstellungen → Gäste* ein: **„Alle (frei)“** oder **„Nur mit Anmeldung“**.
- **Ersatz-Suchmaschine:** Hat Brave keinen Key, ist das Limit erreicht, das Kontingent des Kontos aufgebraucht oder Brave gestört, geht die Suche **sofort und ohne Zwischenstopp** weiter. Zur Wahl stehen Startpage, DuckDuckGo, Brave Search (Website), Ecosia, Qwant, Mojeek, Google, Bing oder eine eigene Adresse mit `{q}`. Bilder, News und Videos landen dort gleich im passenden Bereich.
  - Den **Standard** legt der Administrator fest (*Verwaltung → Suchdienste*).
  - **Jedes Konto** wählt seine eigene unter *Einstellungen → Suche*.
  - **Gäste** wählen sie direkt auf der Startseite, gemerkt nur im eigenen Browser.

## Hilfen von Brave

Drei freiwillige Zusätze, jeder mit eigenem Brave-Plan, eigenem Key, eigenem Monatslimit und eigener Bremse. Nur für angemeldete Konten, jedes Konto kann sie unter *Einstellungen → Suche* abschalten.

- **Suchvorschläge** beim Tippen (Brave *Autosuggest*), unter den eigenen letzten Suchen. Jeder Vorschlag bleibt eine Stunde im Zwischenspeicher.
- **„Meintest du …?“** (Brave *Spellcheck*). Brave korrigiert Tippfehler in der Suche meist schon selbst („Ergebnisse für …“), darum fragt die Suchmaschine nur, wenn nicht: bei „Wortgenau“ und wenn kaum etwas gefunden wurde.
- **KI-Antwort** über den Treffern (Brave *Answers*): aus, auf Knopfdruck (Standard) oder automatisch. Die Trefferliste wartet nie darauf. Eine Antwort kostet bei Brave etwa 6 Cent, darum liegt die Bremse bei 70 Requests im Monat.

## Bilder groß, auch bei anderen Suchmaschinen

Bei Brave zeigt die Bildvorschau jedes Bild in voller Auflösung und im Vollbild. Für die Bildersuche anderer Suchmaschinen gibt es das Lesezeichen **„Bilder groß“** (Hilfe → Bilder groß):

- Auf der fremden Bilderseite antippen: Es sammelt die Bilder in der größten Fassung, die die Seite verrät, und zeigt sie **randlos auf Schwarz**. Wischen oder Pfeiltasten blättern, `F` schaltet Vollbild, `S` startet und stoppt die Diashow (Sekunden pro Bild wählbar), `Esc` oder nach unten wischen schließt.
- Am Rechner den Knopf in die Lesezeichenleiste ziehen. Auf iPad und iPhone irgendeine Seite als Lesezeichen sichern, das Lesezeichen bearbeiten und die Adresse durch den kopierten Code ersetzen.
- Am besten klappt es mit der **Bildersuche von Bing**, dort stehen die Originale direkt in der Seite. Bei anderen Suchmaschinen kommen je nach Seite die größten Vorschauen.
- Das Lesezeichen läuft nur in deinem Browser und schickt nichts an den Server oder sonst wohin.

## Wie bei ..., nur kostenlos

Bezahlte Suchmaschinen sind für ein paar Ideen bekannt, die es hier für alle gibt, die selbst hosten. Jedes Konto hat dabei **seinen eigenen Bereich**: Was einer einrichtet, sehen die anderen nicht.

- **Domains blockieren, hochstufen, herabstufen.** Unerwünschte Seiten verschwinden ganz, Lieblingsseiten rücken nach oben und bekommen einen kleinen Stern, andere rutschen nach hinten. Am schnellsten über das Menü ⋮ neben jedem Treffer, oder als Liste unter *Einstellungen → Domains*. Gefiltert wird erst nach dem Abruf, der Zwischenspeicher bleibt also für alle gleich.
- **Bangs** wie `!w Berlin`, `!gh`, `!yt`, `!osm`, `!so`, `!r`, `!dl`, `!leo` springen direkt zur Suche einer anderen Seite. Sie werden abgefangen, bevor Brave gefragt wird, und **kosten kein Guthaben**. `!i`, `!n` und `!v` wechseln zu Bildern, News und Videos. Mitgeliefert werden **über 10.800 Bangs** aus der offenen Liste von [Kagi](https://github.com/kagisearch/bangs) (MIT-Lizenz) plus deutsche Extras. Beim Tippen von `!` (oder über den `!`-Knopf) schlägt das Suchfeld passende Bangs vor und zeigt, wohin es geht. Unter *Einstellungen → Bangs* lässt sich jedes Bang suchen, bearbeiten, löschen und der Grundzustand wiederherstellen; Administratoren können das auch für alle. Die Liste aktualisiert sich wöchentlich selbst, und die Zielseiten werden nach und nach auf Erreichbarkeit geprüft, beides ohne Suchanfragen und abschaltbar. Funktioniert auch aus der Adressleiste des Browsers.
- **Linsen** schränken die Suche auf ein Thema ein, etwa Foren, Programmieren, Wikis oder Wissenschaft. Jeder kann **eigene Linsen** aus Domains und Operatoren bauen und die mitgelieferten ausblenden. Auswahl auf der Ergebnisseite unter „Linse“.
- **Sichern und wiederherstellen:** Unter *Einstellungen → Konto* lädt jedes Konto seine Einstellungen samt Listen, Bangs und Linsen als Datei herunter und spielt sie bei Bedarf wieder ein, auch auf einer anderen Installation. Der Administrator kann zusätzlich die Einstellungen der Installation sichern.

## Werkzeuge


<p align="center"><img src="docs/de/werkzeuge.png" alt="Werkzeuge mit Suche, Favoriten und Gruppen" width="820"></p>

<table>
  <tr>
    <td align="center"><img src="docs/de/qr-studio.png" width="400" alt="QR-Studio"><br><sub>QR-Studio mit Design und Lesetest</sub></td>
    <td align="center"><img src="docs/de/passwort.png" width="400" alt="Passwort-Generator"><br><sub>Passwort-Generator mit Stärke-Check</sub></td>
  </tr>
  <tr>
    <td align="center"><img src="docs/de/waehrung.png" width="400" alt="Währungen mit Kursverlauf"><br><sub>Währungen mit Kursverlauf der EZB</sub></td>
    <td align="center"><img src="docs/de/wetter.png" width="400" alt="Wetter mit DWD-Warnungen"><br><sub>Wetter mit Stunden, 7 Tagen und DWD-Warnungen</sub></td>
  </tr>
</table>

**Übersicht:** Werkzeug-Suche (Taste `/`), Favoriten mit ☆, „Zuletzt benutzt“, Abkürzungen im Suchfeld wie `!qr Hallo`, `!pw`, `!kurz https://…`, `!json`, `!dns example.org`.

**QR-Codes und Scanner** (Bausteine aus CodeForge, gleicher Autor)
- **QR-Studio:** Vorlagen für Link, Text, WLAN, Kontakt (vCard), Telefon, SMS, E-Mail, Termin, Ort, **GiroCode**, **2FA-Schlüssel**, Produkt (GS1) und **dynamische QR-Codes** (zeigen auf einen eigenen Kurzlink, das Ziel lässt sich später ändern). Farben, Verläufe, sechs Punktformen, eigene Ecken, **Logo in der Mitte**, Rahmen mit Text. Dazu Data Matrix, Aztec, PDF417 und alle Strichcodes. Export als PNG (bis 4096 px) und SVG.
- **Lesetest:** Jeder Code wird automatisch klein gedruckt, verwischt, schief und dunkel nachgestellt und wieder eingelesen, mit Empfehlung für die Druckgröße.
- **Scanner:** Webcam am PC, Kamera am Handy (mit Taschenlampe, Zoom, Kamerawechsel), Fotos und Screenshots (auch mit Strg+V). QR, Data Matrix, Aztec, PDF417, EAN, UPC, Code 128 und mehr. Serien- und Inventur-Modus mit CSV.
- **Gescanntes direkt suchen:** Kamera-Knopf im Suchfeld, Barcode, ISBN oder Text scannen, und die Suche startet sofort. Im Scanner auf Wunsch mit „Gleich suchen“.
- **Link-Röntgen gegen Quishing:** zeigt die echte Domain, warnt vor nachgemachten Adressen (`pаypal.com` mit kyrillischem a), fremden Markennamen, IP-Adressen und Tricks. Kurzlinks löst der Server auf, ohne die Seite zu öffnen.
- **QR drucken:** Gäste-WLAN-Schild, Tischkarte, Visitenkarte, Spendenschild, Etikettenbögen (Avery, Herma, Etikettendrucker) mit Seriennummern.
- **Nummern-Detektiv:** IBAN, EAN, ISBN, ISSN, PZN, IMEI prüfen, mit Prüfziffer und Hinweis, welche Ziffern vertauscht wurden.

**Sicherheit und Teilen**
- **Passwort-Generator:** zufällige Zeichen, Passphrasen (große deutsche und englische Wortliste), PIN ohne 1234 und Co., aussprechbar, eigenes Muster, Tokens (Hex, Base64url, Base32, UUID). Vorlagen für WLAN, Online-Konto, maximal sicher und zickige Seiten, 50 Stück auf einmal mit CSV, **Stärke-Check** für eigene Passwörter mit Knackzeit und Mustererkennung. Alles nur im Browser.
- **Kurzlinks 2.0** (Ideen aus [ShrinkURL](https://github.com/burnersen/ShrinkURL)): Wunschname, Ablaufdatum, Aufruf-Grenze („nur einmal“), Passwort, Zwischenseite gegen Messenger-Vorschauen, Vorschau mit `+` am Ende, Tracking-Parameter entfernen, Ziel nachträglich ändern, an/aus, **Statistik nur als Zähler pro Tag** (keine IP, kein Cookie), Titel des Ziels, Ziele auf tote Links prüfen, CSV, Lesezeichen-Knopf, Eintrag im Teilen-Menü am Handy.
- **Text-Schnipsel:** im Browser verschlüsselt (AES-256-GCM), mit Pflicht-Passwort, aus dem der Schlüssel entsteht. Dadurch ist der Link kurz (`…/?n=Ab3xK9pQ`) und der Server kennt weder Passwort noch Inhalt. Gültigkeit, „nach dem Lesen weg“, ein falsches Passwort verbraucht keinen Abruf.

**Rechnen:** Rechner, der beim Tippen rechnet (Verlauf, wissenschaftlich, Grad/Rad), Prozent und MwSt, Dreisatz, Einheiten live (auch Verbrauch, Druck, Energie, Leistung, Winkel, Kochen), Währungen mit mehreren Zielen und **Kursverlauf der EZB**, Zinseszins, Sparplan, Kredit mit Tilgungsplan, Spritkosten, Rechnung teilen, römische Zahlen, Morsecode mit Ton und Licht.

**Zeit und Datum:** mehrere Timer gleichzeitig, Pomodoro, Intervall-Training, Wecker, Stoppuhr mit Runden (Timer überleben das Neuladen), Countdown mit Titel, Vollbild und Link, **Arbeitstage mit Feiertagen des Bundeslands**, Wochentag, **Brückentage-Planer**, Feiertage als **Kalenderdatei (.ics)**, Jahreskalender zum Drucken, Weltzeituhr mit Termin-Planer.

**Netz und Web** (nach dem Anmelden, mit sicherem Abruf nach dem Vorbild von [LinkVault](https://github.com/burnersen/LinkVault)): Was Seiten über dich sehen (Kopfzeilen, Browser, WebRTC), Ist die Seite down?, Header und Weiterleitungen mit Sicherheits-Check, SSL-Zertifikat, DNS mit SPF und DMARC, WHOIS über RDAP, Link-Checker für bis zu 200 Links, **Link-Vorschau** (so sieht dein Link in Messengern aus), **Lesezeichen aufräumen** (Doppelte und tote Links aus dem Browser-Export entfernen).

**Für Entwickler:** JSON (formatieren, prüfen, CSV), Kodieren (Base64, URL, HTML, Hex, Binär, Unicode), Hash (SHA-256/512, SHA-1, MD5, CRC32, HMAC, auch von Dateien), JWT-Decoder mit Signaturprüfung, UUID v4/v7, ULID, Nano ID, Regex-Tester, Cron-Erklärer mit den nächsten Läufen, Text vergleichen, Farben mit WCAG-Kontrast, Zahlensysteme, Subnetz-Rechner (IPv4 und IPv6), chmod, `.htpasswd`, Text-Werkzeuge, Unix-Zeitstempel.

**Alltag:** Lotto 6 aus 49, Eurojackpot, Karte ziehen, Ja oder nein, **Wetter mit Stunden, 7 Tagen, UV und amtlichen DWD-Warnungen**.

**Raus:** „Losen und Gruppen bilden“.

**Datenschutz bleibt:** Passwörter, QR-Inhalte, Hashes, JWTs, Regex und Texte verlassen den Browser nicht. Abrufe nach draußen (Netz-Werkzeuge, Link-Röntgen) macht nur dein Server, nur zu öffentlichen Adressen, ohne etwas zu speichern. Die Scan-Engine (zxing-wasm, MIT) liegt auf deinem Server und wird nur geladen, wenn du scannst.

## Aussehen und Nachladen

### Aussehen

Unter **Einstellungen → Aussehen** stellt jedes Konto für sich ein, wie die Suchmaschine aussieht. Den Standard für alle legt der Administrator unter **Verwaltung → Aussehen** fest (gilt auch für Besucher ohne Anmeldung).

- **17 fertige Designs**, hell und dunkel. Neben klarem Weiß und Schwarz gibt es gedämpfte Töne für alle, denen Weiß zu grell und Schwarz zu dunkel ist: **Papier**, **Nebel**, **Salbei**, **Schiefer**, **Dämmerung**. Für hell und dunkel jeweils ein Lieblingsdesign, bei „Wie das Gerät“ wird automatisch gewechselt.
- **Farbmischer** (aus „Kaufen“): Hintergrund, Flächen, Schrift, Akzent, dazu freiwillig Titel/Links und Nebentext. Mit Reglern für Farbton, Sättigung und Helligkeit, Farbcode, Live-Vorschau, Warnung bei zu wenig Kontrast und einem Knopf, der die Schriftfarben automatisch abstimmt. Funktioniert auch ohne JavaScript mit den Farbwählern des Browsers.
- **Schriften fest eingebaut:** Atkinson Hyperlegible, Lexend, Source Sans 3, Inter und Literata (mit Serifen). Sie liegen auf deinem eigenen Server, es wird **nichts bei Google** geladen. Alle unter der SIL Open Font License (Lizenzdateien in `app/assets/fonts/`).
- **Größen und Platz:** alles zusammen, Titel der Treffer und Beschreibungen getrennt, Zeilenabstand, Abstand zwischen den Treffern (kompakt bis luftig) und Breite der Trefferliste für große Bildschirme.

### Nachladen und News

- **Endlos scrollen oder seitenweise** gilt für alle Reiter: Alle, Bilder, News und Videos.
- **Nachladen mit Widerstand:** Normales Runterscrollen lädt nichts nach. Erst wer ganz unten angekommen ist und dann bewusst weiterzieht (am Handy ein deutlicher Wisch, am Rechner mehrere Rasten am Mausrad), bekommt die nächsten Treffer. Ein Balken zeigt, wie weit es noch ist, loslassen setzt ihn zurück. Den letzten Eintrag in Ruhe lesen kostet also nichts. Der Knopf „Weitere Treffer“ lädt sofort.
- **Bilder:** 200 auf einmal mit einer einzigen Suche, Blättern und Nachladen kosten danach nichts mehr.
- **News:** Brave holt 50 Meldungen auf einmal, meist der ganze Tag in einer Anfrage. Am Ende geht es mit der letzten Woche weiter. Leere Abschnitte werden übersprungen.

## Lesemodus und PDF

Jede Seite lässt sich ohne Werbung lesen, drucken oder als PDF speichern (nur mit Konto). Drei Wege dorthin:

- **Link ins Suchfeld einfügen** (z. B. `https://www.chefkoch.de/rezepte/…` oder `chefkoch.de/rezepte/…`), Enter. Eine bloße Domain wie `spiegel.de` öffnet einfach die Seite, Suchen wie `node.js` bleiben Suchen.
- Im Menü **⋮** an jedem Treffer: **„Ohne Werbung lesen“**.
- Lesezeichen **„Lesen & Drucken“** (Hilfe → Lesen & Drucken oder im Bereich *Rezepte*): auf der offenen Seite antippen, und sie erscheint im Lesemodus. Das Lesezeichen ist persönlich und schickt die Seite mit, die dein Browser schon geladen hat. Darum klappt es auch bei Seiten, die Server aussperren (z. B. Chefkoch, REWE, kochbar).
- Als App auf Android oder am PC installiert, erscheint die Suchmaschine auch im **Teilen-Menü**: geteilte Links lassen sich lesen und drucken, kürzen oder öffnen.

### Rezepte

Unter **Rezepte** (Konto-Menü und Startseite) gibt es ein Feld für Rezept-Links, das Lesezeichen und eine Liste aller getesteten Rezeptseiten, getrennt nach „klappt mit Link“ und „klappt mit dem Lesezeichen“. Erkannt werden Rezepte im JSON-LD- und im Microdata-Format (schema.org), das liefern fast alle Rezeptseiten mit.

- Nur das Rezept: Bild, Zutaten, Zeiten, Zubereitung. **„Rezept drucken“** druckt genau das.
- **Portionen umrechnen** mit + und −: Mengen wie `250 ml`, `1/2 TL`, `1½ EL` oder `2-3 EL` rechnen sich um, auch im Ausdruck und im PDF.
- „Ganzer Artikel“ schaltet auf den Text der Seite mit allen Bildern.

### Lesen und PDF

- Der eigene Server holt die Seite (oder bekommt sie vom Lesezeichen) und schneidet den eigentlichen Inhalt heraus: Werbung, Menüs, Cookie-Banner, Kommentare und Skripte fliegen raus. Angezeigt wird alles in deiner Schrift und deinen Farben.
- **Rezepte** werden über ihre strukturierten Daten erkannt (schema.org), die fast jede Rezeptseite für Suchmaschinen mitliefert: Zutaten, Zubereitung, Zeiten, Bild. Ein Klick schaltet auf den ganzen Artikel mit allen Bildern um.
- **Als PDF speichern:** mit Live-Vorschau, Schrift (klar oder klassisch), Größe, Zeilenabstand, 1 oder 2 Spalten und Rändern. **Bilder nach Wahl:** Antippen lässt ein Bild weg, so kommt nur ins PDF, was du willst. Das PDF entsteht im Browser, ohne fremde Dienste. Es kann Umlaute, ß und €, aber keine Zeichen außerhalb Westeuropas. Dafür gibt es **Drucken** mit einem sauberen Drucklayout (der Browser kann dort auch „Als PDF sichern“).

**Bitte fair nutzen.** Der Lesemodus ist zum privaten Lesen und Ablegen gedacht (Privatkopie), nicht zum Weiterverbreiten. Darum:
- nur für angemeldete Konten, höchstens 60 Seiten pro Stunde und Konto,
- auf dem Server wird nichts gespeichert, es gibt keine Links, über die andere den Lesemodus aufrufen könnten,
- Bezahlschranken werden nicht umgangen, kostenpflichtige Artikel bleiben beim Anbieter,
- Quelle, Abrufdatum und „nur zum privaten Gebrauch, Rechte beim Urheber“ stehen in der Ansicht und auf jeder PDF-Seite,
- der Abruf gibt sich ehrlich als „Suchmaschine … Lesemodus“ zu erkennen.

Die Rechte an den Inhalten bleiben bei den jeweiligen Seiten. Wer ein PDF weitergibt oder veröffentlicht, ist selbst dafür verantwortlich.

## Neueste zuerst und Links

- **Neueste zuerst** ist Standard bei News und Videos, „Alle“ zeigt die besten Treffer zuerst (Relevanz). Jedes Konto stellt das unter *Einstellungen → Suche* für sich um, der Administrator legt unter *Verwaltung → Einstellungen* den Standard für alle fest. In der Trefferliste lässt es sich jederzeit über „Sortierung“ umschalten.
- **News aus den letzten 24 Stunden.** Am Ende der Liste geht es automatisch mit der letzten Woche weiter, dann mit dem letzten Monat und dem letzten Jahr. Gibt es gar keine aktuellen News, wird gleich erweitert. Zeitraum und Erweitern sind pro Konto einstellbar.
- **Neuer Tab überall:** „Links in neuem Tab öffnen“ gilt für jeden Link nach draußen, auch im Wetter, in der Kurzinfo und in nachgeladenen Treffern.
- **Bangs ohne Aufblitzen:** Die Zwischenseite ist leer und hat die Farbe der Seite. Nur wenn das Ziel hängt, erscheint nach 2 Sekunden ein Knopf „Weiter“.

## Weitere Funktionen

### Suchdienst, Störungen, Ersatz

- Gesucht wird bei **Brave Search**. Liefert Brave einen Fehler oder ist nicht erreichbar, pausiert es kurz, damit keine Suche darauf wartet, und die Suchen gehen solange sofort zur Ersatz-Suchmaschine. In der Verwaltung siehst du letzten Erfolg, letzten Fehler im Klartext und die durchschnittliche Antwortzeit.

### Verbrauch und Kontingente

- Unter **Verwaltung → Verbrauch** alle vier Brave-Pläne wie im Dashboard (Requests, Credits, Burn rate, Runway, Reset), mit Feld für die Requests laut Dashboard, dazu der Verlauf nach Monaten und der Verbrauch pro Konto.
- **Warnhinweis:** Ab 80 % der Bremse (einstellbar) sieht der Administrator oben einen Hinweis, ebenso wenn eine Bremse greift.
- **Kontingente pro Konto:** Unter *Verwaltung → Konten → Kontingent* legst du fest, ob ein Konto Brave nutzen darf und wie viele Suchen es pro Zeitraum bekommt. Ist es aufgebraucht, gehen die Suchen des Kontos zu seiner Ersatz-Suchmaschine. „Gerecht aufteilen“ verteilt das Limit gleichmäßig auf alle Konten.

### Sofort-Antworten (kosten kein Guthaben)

Direkt im Suchfeld, beantwortet bevor ein Suchdienst gefragt wird. Unter jeder Antwort steht „Trotzdem im Web suchen“.

| Eingabe | Ergebnis |
|---|---|
| `(12,5 + 7) * 3`, `200 + 19%`, `15% von 80`, `2^10`, `sqrt 2` | Taschenrechner (eigener Parser, kein `eval`), Komma und Punkt |
| `100 usd in eur`, `50 chf in eur`, `20 € in £` | Währungen, Kurse der EZB über frankfurter.app, einmal am Tag gespeichert |
| `10 km in meilen`, `20 °c in f`, `1 gib in mb`, `100 km/h in mph` | Einheiten: Länge, Gewicht, Temperatur, Daten, Volumen, Fläche, Tempo, Zeit |
| `wetter berlin`, `wetter` | Wetter mit 4-Tage-Vorhersage: Deutscher Wetterdienst über Bright Sky, Open-Meteo (DWD-Modell ICON) oder wttr.in, alle kostenlos |
| `meine ip` | eigene IP-Adresse |
| `kw`, `kw 12`, `kw 1 2027` | Kalenderwoche samt Datumsspanne |
| `datum`, `uhrzeit` | Datum, Uhrzeit, Tag des Jahres |
| `würfel`, `2w20`, `münze`, `zufallszahl 1-49` | Würfel, Münzwurf, Zufallszahl |
| `timer 5 min`, `stoppuhr`, `countdown weihnachten` | Timer mit Ton, Stoppuhr, Countdown |
| `passwort`, `passwort 32`, `passphrase` | Passwort-Generator, läuft nur im Browser |
| `qr https://…` | QR-Code als SVG, auf dem eigenen Server erzeugt |
| `kurz https://…` | eigener Kurzlink (`…/?s=abc123`) |
| `tage zwischen 1.1.2026 und 24.12.2026`, `heute + 6 wochen` | Datumsrechner mit Wochen und Werktagen |
| `alter 12.05.1940` | Alter, nächster Geburtstag, Tage auf der Welt |
| `feiertage 2027 bayern`, `feiertage nrw` | gesetzliche Feiertage nach Bundesland |
| `arbeitstage zwischen 1.12.2026 und 31.12.2026 bayern`, `heute + 10 arbeitstage` | Arbeitstage mit den Feiertagen des Bundeslands |
| `wochentag 24.12.2027` | Wochentag, Kalenderwoche, Tag des Jahres |
| `brückentage 2027 nrw` | Brückentage-Planer: wenig Urlaub, viele freie Tage |
| `jahreskalender 2027`, `kalender 2027` | Jahreskalender mit KW und Feiertagen, zum Drucken |
| `lotto`, `eurojackpot`, `karte ziehen`, `ja oder nein` | Lottozahlen, Spielkarte, Entscheidungshilfe |
| `!qr Hallo`, `!pw`, `!kurz https://…`, `!scan`, `!json`, `!dns example.org` | öffnet das passende Werkzeug |

### Werkzeuge im Suchfeld

Unter **Werkzeuge** (Kachel-Symbol oben rechts, Konto-Menü oder Startseite) stehen 47 Helfer an einem Ort, nach Gruppen sortiert, mit Suche, Favoriten und „Zuletzt benutzt“. Jedes Werkzeug hat eine eigene Seite. Nichts davon fragt einen Suchdienst, kostet also kein Guthaben. Was alles dabei ist, steht oben unter [Neu in 2.0](#neu-in-20-das-große-werkzeug-upgrade).

- **QR-Codes und Scanner:** QR-Studio, Scanner (auch direkt aus dem Suchfeld), QR drucken, Nummern-Detektiv
- **Sicherheit und Teilen:** Passwort-Generator, Kurzlinks, verschlüsselte Text-Schnipsel
- **Rechnen und Umrechnen:** Rechner, Prozent und MwSt, Einheiten, Währungen, Zinsen, Sprit und Rechnung teilen
- **Zeit und Datum:** Timer und Stoppuhr, Countdown, Datumsrechner, Feiertage und Brückentage, Kalender, Weltzeituhr
- **Netz und Web:** Meine IP, Ist die Seite down?, Header, SSL, DNS, WHOIS, Link-Checker, Link-Vorschau, Lesezeichen aufräumen
- **Für Entwickler:** JSON, Kodieren, Hash, JWT, UUID, Regex, Cron, Text vergleichen, Farben, Zahlensysteme, Subnetz, chmod, .htpasswd, Text-Werkzeuge, Zeitstempel
- **Alltag und Zufall:** Würfel, Münze, Lotto, Wetter, Römische Zahlen, Morsecode

Die einfachen Rechen- und Datumswerkzeuge funktionieren auch ohne JavaScript. Die Apps (QR-Studio, Scanner, Passwörter, Entwickler-Werkzeuge …) brauchen JavaScript, weil sie bewusst nur im Browser rechnen. Die Netz-Werkzeuge rufen fremde Server ab und sind darum nur nach dem Anmelden da.

**Töne:** Für den Timer gibt es sechs Töne (Glocke, Gong, Ding-Dong, sanfte Tonfolge, Vogelzwitschern, Wecker) mit „Ton testen“. Sie sind rein rechnerisch erzeugt, ohne fremde Aufnahmen, und liegen als MP3 in `app/assets/sounds/`. Frei verwendbar (CC0), auch in anderen Projekten.

### Filter, Sortierung, Ergebnis-Menü

- **Nach Datum sortieren (neueste zuerst):** streng chronologisch, Treffer ohne Datum ans Ende. Bei aktivem Zeitfilter automatisch.
- **Max. 3 Treffer pro Domain:** Schalter in der Filterleiste, standardmäßig an.
- **„Alle Filter zurücksetzen“** erscheint, sobald ein Filter vom Standard abweicht.
- **×** im Suchfeld zum Leeren, auf dem Handy extra groß.
- **Menü ⋮** an jedem Treffer: *Nur auf dieser Domain suchen* (`site:`), *Archiv* bei web.archive.org und archive.today, *Als QR-Code anzeigen*, *Adresse kopieren*, mit Konto dazu hoch-/herabstufen/blockieren.

### Bilder

- **Filter:** Ausrichtung (Quer, Hoch, Quadratisch) und Größe (Sehr groß ab 1 MP, Groß ab 0,3 MP, Mittel, Klein unter 0,15 MP), nach den Maßen, die Brave liefert.
- **Sortierung nach Auflösung** (Breite × Höhe, höchste zuerst).
- **Vollbild** zeigt jedes Bild immer ganz: zentriert auf Schwarz, höchstens so groß wie der Bildschirm, nie beschnitten.

### Komplettsicherung

Unter **Verwaltung → Sicherung**: eine Datei mit allem (Einstellungen, alle API-Keys, Konten mit Passwort-Prüfsummen, Login-Tokens, persönliche Einstellungen, Kontingente, Kurzlinks, Verbrauch, Logo). **Auf Wunsch mit Passwort verschlüsselt** (AES-256-GCM, Schlüssel per PBKDF2 aus dem Passwort). Einspielen geht an gleicher Stelle, danach melden sich alle neu an.

## Konten und Token-Login

- **Erstes Konto:** Wer die Suchmaschine zuerst aufruft, legt das Administrator-Konto an. Der Brave-Key ist dabei freiwillig, Keys lassen sich auch später unter *Verwaltung → Suchdienste* eintragen.
- **Weitere Konten** legt der Administrator unter *Verwaltung → Konten* an, auf Wunsch mit eigenem Kontingent.
- **Login-Token:** Unter *Einstellungen → Browser* erzeugt jedes Konto einen langen geheimen Token (48 Zeichen) oder legt einen eigenen fest (mindestens 32 Zeichen, mindestens drei Zeichenarten). Token, Login-Adresse und Such-Adresse lassen sich dort anzeigen, kopieren und neu erzeugen, natürlich nur angemeldet.
- **So funktioniert's:** `https://deine-domain.de/suche/?token=DEIN_TOKEN` (auch kombiniert: `?token=…&q=…`) meldet sofort an und setzt ein Sitzungs-Cookie für **365 Tage** (`HttpOnly`, `Secure` bei HTTPS, `SameSite=Lax`). Der Token verschwindet dabei sofort per Weiterleitung aus der Adresse. Die Sitzung liegt serverseitig (im Cookie steht nur ein Zufallswert, in der Datenbank nur dessen Prüfsumme), lässt sich also jederzeit beenden: Abmelden, Cookies löschen oder *Alle anderen Geräte abmelden*.
- **Als Standardsuche im Browser:** die Such-Adresse mit Token eintragen (`…/?token=…&q=%s`) oder in Firefox auf der Konto-Seite über das Lupen-Symbol hinzufügen (OpenSearch mit Token). Dann bleibt der Browser auch in privaten Fenstern angemeldet.
- **Sicherheit:** Falsche Tokens werden gebremst (nach 5 Fehlversuchen wachsende Wartezeit), verglichen wird in konstanter Zeit. Der Token liegt verschlüsselt in der Datenbank, der Schlüssel dazu in `data/config.php`. Ein Token ist wie ein Passwort: nur auf eigenen Geräten nutzen. Achtung, Webserver-Logs können Adressen mit Token mitschreiben.

## Tastenkürzel

Auf der Ergebnisseite, sobald kein Eingabefeld aktiv ist:

| Taste | Wirkung |
|---|---|
| `↓` / `J`, `↑` / `K` | durch die Treffer, mit sichtbarem Rahmen |
| `Enter` | markierten Treffer öffnen, mit `Strg`/`⌘` im neuen Tab |
| `←` `→` | vorige/nächste Ergebnisseite, in der Bildvorschau blättern |
| `/` | Suchfeld fokussieren und markieren |
| `F` | Bildvorschau im Vollbild |
| `S` | Diashow in der Bildvorschau starten und anhalten |
| `?` | Übersicht aller Kürzel (deutsche Tastatur: `Shift` + `ß`) |
| `Esc` | Übersicht, Menüs, Bildvorschau, Vollbild schließen |

## Voraussetzungen

- Webspace mit **PHP 8.0 oder neuer**, dazu die Erweiterungen `pdo_sqlite` und `curl`. Das hat praktisch jeder Hoster.
- Der Server muss Verbindungen nach außen aufbauen dürfen: zu `api.search.brave.com`, für Sofort-Antworten zu `api.frankfurter.dev`, `geocoding-api.open-meteo.com`, `api.brightsky.dev`, `api.open-meteo.com`, `wttr.in`, `*.wikipedia.org` und für Updates zu `github.com`.
- Für die verschlüsselte Sicherung und das verschlüsselte Speichern der Login-Tokens die Erweiterung `openssl` (fast immer vorhanden).
- **HTTPS.** Ohne geht das Passwort im Klartext durchs Netz. Bei den meisten Hostern ist ein kostenloses Let's-Encrypt-Zertifikat mit einem Klick eingerichtet.
- **Apache** (oder LiteSpeed) sperrt die internen Ordner von selbst über `.htaccess`. Unter **nginx** sind zwei Zeilen in der Konfiguration nötig, siehe [Daten schützen](#daten-schützen).
- Optional ein **Brave-Key** (kostenlos, siehe [Brave einrichten](#brave-einrichten-api-keys-holen)). Ohne Key geht jede Suche zur Ersatz-Suchmaschine.

## Installation

1. Optional einen **Brave-Key holen**, siehe [Brave einrichten](#brave-einrichten-api-keys-holen). Ohne Key geht jede Suche zur Ersatz-Suchmaschine.
2. Aus dem [neuesten Release](../../releases/latest) die Datei **`suchmaschine-install.php`** herunterladen und per FTP oder Dateimanager in ein Verzeichnis auf dem Webspace kopieren, z. B. `httpdocs/suche/`. Soll die Suchmaschine direkt unter der Domain laufen, kommt die Datei direkt nach `httpdocs/`.
3. Die Datei **sofort** im Browser aufrufen, z. B. `https://deine-domain.de/suche/suchmaschine-install.php`, und auf **„Jetzt installieren“** klicken. Sie packt das Programm aus, löscht sich selbst und leitet weiter. Meldet sie, dass sie sich nicht löschen konnte, die Datei bitte von Hand löschen.
4. Gleich danach **Konto anlegen** und optional den **Brave-Key einfügen**. Der Key wird mit einer Testsuche geprüft. Dieses Konto ist der Administrator. Nicht aufschieben: Wer zuerst kommt, wird Administrator. Die freiwilligen Hilfen von Brave (Vorschläge, Rechtschreibung, KI-Antwort) trägst du danach unter *Verwaltung → Suchdienste* ein.
5. **Test:** In der Verwaltung unter *Update → Schutz der Daten* auf „Jetzt prüfen“ klicken. Dort muss „Alles dicht“ stehen. Von Hand geht es so: `https://deine-domain.de/suche/app/lib/db.php` aufrufen, da muss **403** oder **404** kommen.

<p align="center"><img src="docs/de/einrichten.png" width="560" alt="Einrichten: Konto und API-Key"></p>

Wer lieber von Hand installiert: Das ZIP aus dem Release entpacken und den Inhalt des Ordners `suchmaschine/` hochladen.

### Netcup und andere Hoster mit Plesk

Bei vielen Hostern läuft vor Apache ein **nginx**, der Dateien direkt ausliefert und dabei die `.htaccess` übergeht. Dann öffnen: **„Apache & nginx-Einstellungen“** der Domain und **„Statische Dateien direkt durch nginx bereitstellen“** bzw. **„Intelligente Verarbeitung statischer Dateien“** ausschalten. Danach greift die Sperre der internen Ordner. Der Selbsttest in der Verwaltung zeigt, ob alles dicht ist.

Selbst wenn die Sperre nicht greift, bleibt das Wichtigste geschützt: Die Datenbank hat einen zufälligen Namen, der nur in einer `.php`-Datei steht, und `.php`-Dateien kann man von außen nicht herunterladen.

## Daten schützen

**1. Die internen Ordner sperren.** Unter Apache legt die Suchmaschine die nötigen `.htaccess`-Dateien selbst an. Unter **nginx** mit eigener Konfiguration gehören diese zwei Zeilen in den `server`-Block. Das `^~` ist wichtig, es gibt der Sperre Vorrang vor der Regel für `.php`-Dateien. Liegt die Suchmaschine in einem Unterordner, den Pfad anpassen, z. B. `/suche/data/`:

```nginx
location ^~ /data/ { deny all; return 404; }
location ^~ /app/  { deny all; return 404; }
```

Mit Sicherung vorher und Weg zurück:

```bash
# Sicherung der Konfiguration (Pfad je nach System, oft /etc/nginx/sites-available/)
sudo cp -a /etc/nginx/sites-available/default /etc/nginx/sites-available/default.bak
# Die zwei Zeilen oben in den server-Block eintragen, dann prüfen und neu laden:
sudo nano /etc/nginx/sites-available/default
sudo nginx -t && sudo systemctl reload nginx
# Falls etwas nicht stimmt, zurückholen:
sudo cp -a /etc/nginx/sites-available/default.bak /etc/nginx/sites-available/default && sudo systemctl reload nginx
```

**2. HTTPS einschalten** und am besten auf HTTPS umleiten.

**3. Den Ordner `data/` nie veröffentlichen oder ins Git legen.** Dort liegen Konten, Einstellungen, alle API-Keys und der Schlüssel für die Login-Tokens.

## Aktualisieren

**Am einfachsten in der Verwaltung** unter *Update*: „Nach Updates suchen“ und dann „Jetzt aktualisieren“. Dafür muss PHP die Programmdateien überschreiben dürfen. Die laufende Version wird vorher gesichert, „Zurück zu …“ holt sie wieder. Wer gar keine Verbindung zu GitHub möchte, trägt in `data/config.php` den Eintrag `'update_url' => ''` ein.

Von Hand: den neuen `suchmaschine-install.php` hochladen und aufrufen. Ersetzt werden nur die Programmdateien, der Ordner `data/` bleibt unangetastet.

## Sichern

Alles, was zählt, liegt im Ordner **`data/`**: Konten, Einstellungen, API-Keys, eigenes Logo. Diesen Ordner per FTP herunterladen, fertig. Zum Zurückholen den Ordner wieder hochladen.

Ohne FTP geht es im Browser:
- **Komplettsicherung** unter *Verwaltung → Sicherung*: alles in einer Datei, auf Wunsch mit Passwort verschlüsselt. Ideal für einen Umzug.
- **Nur die Einstellungen der Installation** (auf Wunsch mit API-Keys), ebenfalls unter *Verwaltung → Sicherung*.
- **Persönliche Einstellungen** jedes Kontos (Suche, Domain-Listen, Bangs, Linsen) unter *Einstellungen → Konto*.

Alles lässt sich an derselben Stelle wieder einspielen.

## Aufbau

```
index.php      Einstieg: Version, Pfade, lädt app/lib/ und startet den Ablauf
app/lib/       Funktionen, eine Datei pro Thema: Ablauf (router.php), Seiten (page_*.php), Datenbank, Konten,
               Brave und Ersatz-Suchmaschine (providers.php, brave.php), Hilfen von Brave (extras.php), Filter, Domain-Listen, Bangs und Linsen, Sofort-Antworten (instant.php),
               QR-Codes, Bild-Proxy, Lesemodus, Aussehen, Logo, Sicherung, Update,
               Werkzeuge (page_tools.php, tools_api.php), sicherer Abruf nach draußen (net.php), Kurzlinks und Schnipsel
app/lang/      englische Texte (en.php)
app/views/     HTML-Vorlagen
app/assets/    CSS, JavaScript, PDF-Baukasten (pdf.js), Symbole, Schriften, Töne (werden über index.php ausgeliefert)
  cf/          Bausteine der Werkzeug-Apps aus CodeForge: QR-Encoder, Zeichnen, Scanner, Druck, Nummern-Detektiv
  tools/       eigene Werkzeug-Apps (Passwörter, Kurzlinks, Netz, Entwickler, Rechnen, Zeit) und englische Texte (en.js)
  vendor/      Scan-Engine zxing-wasm (MIT), wird nur beim Scannen geladen
data/          entsteht beim ersten Aufruf: Datenbank, Einstellungen, Logo (geheim!)
tests/         automatische Tests
tools/         Release bauen, Texte der Apps prüfen (nur für Entwickler)
```

Die Dateien in `app/` tun nichts, wenn man sie direkt aufruft, alles läuft über `index.php`. Eine ausführliche Übersicht, welche Datei wofür zuständig ist, steht in [BUILD.md](BUILD.md#wo-was-im-quellcode-steht).

## Selbst bauen

Im Quellcode ist das Programm auf viele Dateien verteilt, Nutzer bekommen trotzdem nur **eine Datei**. Bauen kann sie jeder selbst, nur mit PHP:

```sh
php tools/build-release.php
```

Das prüft die Syntax, lässt alle Tests laufen, baut `dist/suchmaschine-install.php`, installiert sie zur Probe und schreibt Prüfsummen. Der Bau ist wiederholbar: Gleicher Quellcode ergibt dieselbe Datei, so lässt sich jedes Release mit dem Quellcode vergleichen. Alle Einzelheiten, auch zur Unterschrift der Updates: [BUILD.md](BUILD.md). Mitmachen: [CONTRIBUTING.md](CONTRIBUTING.md), Sicherheitslücken melden: [SECURITY.md](SECURITY.md).

## Gut zu wissen

- **Datenschutz:** Brave sieht die Suchbegriffe, aber nicht, wer sucht, denn die Anfrage kommt von deinem Server. Bei der Ersatz-Suchmaschine landet der Browser dagegen direkt, die sieht den Besuch ganz normal. Gezählt wird nur die Menge der Anfragen pro Konto, nie ihr Inhalt. Die Suchmaschine selbst speichert keine Suchbegriffe, nur kurz im Zwischenspeicher. Die letzten Suchen für die Vorschläge bleiben im Browser des jeweiligen Geräts. Vorschaubilder und Symbole lädt der Browser über Braves Bild-Proxy.
- **Bilder in voller Auflösung:** Für die große Vorschau holt der Server das Originalbild und reicht es weiter. Die fremde Seite sieht nur den Server, der Browser lädt weiter nur von der eigenen Adresse. Gegen Missbrauch ist jede Bildadresse signiert, interne Netze sind gesperrt, gespeichert wird nichts. Kostet keine Suche, nur etwas Datenverkehr, und lässt sich in der Verwaltung abschalten.
- **Suchoperatoren** gibt die Suchmaschine unverändert an den Suchdienst weiter. Brave versteht die meisten, die anderen Dienste vor allem Anführungszeichen und `site:`. Klammern kennt Brave nicht.
- **Mehrere Personen:** Konten für Familie oder Freunde sind schnell angelegt. Alle Suchen gehen vom selben Guthaben ab. Mit Kontingenten pro Konto teilst du es auf.
- **Suchen ohne Anmeldung** lässt sich einschalten, etwa fürs Heimnetz. Jede Suche kostet dann dein Guthaben. Die Grenze pro Stunde und Besucher bremst Missbrauch, das Monatslimit gilt trotzdem.
- **Endlos scrollen:** Jedes Nachladen ist eine echte Suche bei Brave. Darum holt die Suchmaschine dabei 20 Treffer auf einmal. Brave liefert pro Suchbegriff höchstens 10 Seiten.
- **Bedingungen der Anbieter:** Ergebnisse dürfen nicht weiterverkauft werden, Brave erlaubt nur kurzes Zwischenspeichern. Ein Hinweis auf die genutzten Dienste steht unten auf jeder Seite.
- **Wetter, Wechselkurse, Wikipedia:** Diese kostenlosen Dienste fragt nur der Server, mit Zwischenspeicher (Kurse 12 Stunden, Wetter 20 bis 30 Minuten, Wikipedia einen Tag).

## Sicherheit

Die Suchmaschine wurde für 1.2 aus Angreifersicht geprüft und gehärtet:

- **XSS:** Jede Ausgabe wird escaped, Texte von Brave nur mit `<strong>` als Ausnahme. Strenge Content Security Policy ohne Inline-Skripte, QR-Codes, Logos und Bilder aus dem Proxy kommen mit eigener Sandbox-CSP.
- **CSRF:** Jedes Formular braucht das Sitzungs-Token und muss von der eigenen Seite kommen. Der Token-Login funktioniert nur direkt (Adressleiste, Lesezeichen, Browser-Suche), nicht über Links fremder Seiten, und wechselt nie stillschweigend das Konto.
- **SQL-Injection:** ausschließlich vorbereitete Abfragen. Wiederherstellungen werden Feld für Feld geprüft.
- **SSRF:** Der Bild-Proxy lädt nur signierte Adressen, nur öffentliche IPs (DNS fest verdrahtet, jede Weiterleitung neu geprüft), nur echte Bilder. Wetter, Kurse und Wikipedia gehen nur an fest eingebaute HTTPS-Adressen, ohne Weiterleitungen.
- **Brute-Force und Timing:** Passwort- und Token-Login werden nach Fehlversuchen gebremst, Vergleiche laufen mit `hash_equals()` bzw. `password_verify()` in konstanter Zeit.
- **Rechner:** eigener Parser, kein `eval`, Grenzen für Länge, Tiefe und Exponenten.
- **Path Traversal:** feste Dateinamen, Updates nur mit Prüfsumme und erlaubten Pfaden.
- **Geheimnisse:** Login-Tokens liegen verschlüsselt in der Datenbank, der Schlüssel in `data/config.php`. Weiterleitungen mit Token werden nicht zwischengespeichert. Sitzungen liegen auf dem Server und lassen sich jederzeit beenden.
- **Missbrauch ohne Anmeldung:** Wetter zählt zur Stundengrenze, „Alle zusammen“ gibt es nur mit Konto.

## Haftungsausschluss

Die Suchmaschine ist kostenlose Hobby-Software und wird **so, wie sie ist, ohne jede Gewähr** bereitgestellt. Sie ist mit Sorgfalt gebaut und getestet, die Nutzung erfolgt aber **auf eigene Gefahr**. Wer sie betreibt, ist für den Server, seine Updates und Sicherungen selbst verantwortlich, ebenso dafür, die Bedingungen der genutzten Anbieter einzuhalten. Dieses Projekt steht in keiner Verbindung zu Brave Software, Bright Data oder Google.

## Lizenz

GNU Affero General Public License v3.0, siehe [LICENSE](LICENSE).

Eingebaut: Scan-Engine [zxing-wasm](https://github.com/Sec-ant/zxing-wasm) (MIT) mit zxing-cpp (Apache 2.0), Details in `app/assets/vendor/LIZENZEN.txt`. Wortlisten für Passphrasen: Deutsch aus [dys2p/wordlists-de](https://github.com/dys2p/wordlists-de) (CC0), Englisch aus den Wortlisten der [EFF](https://www.eff.org/deeplinks/2016/07/new-wordlists-random-passphrases) (CC BY 3.0 US). Symbole im Stil von Lucide (ISC). Bang-Liste aus [kagisearch/bangs](https://github.com/kagisearch/bangs) (MIT), Lizenz in `app/lists/LIESMICH.txt`.
