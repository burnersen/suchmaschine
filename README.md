<p align="center"><img src="app/assets/icon.svg" width="88" alt=""></p>

<h1 align="center">Suchmaschine ohne Werbung</h1>

<p align="center"><b>Deine eigene Suchmaschine. Ohne Werbung, ohne Tracking, auf deinem eigenen Webspace.</b><br>
Ergebnisse von Brave Search, Tavily, Exa, parallel.ai und Firecrawl, mit Lastverteilung und Ausweichen bei Störungen,<br>
bei normaler privater Nutzung komplett in den kostenlosen Monatskontingenten.</p>

<p align="center"><a href="README.en.md">English version</a> · <a href="../../releases/latest">Neueste Version herunterladen</a></p>

<p align="center"><img src="docs/de/ergebnisse.png" alt="Ergebnisseite mit Treffern, Top-Meldungen und Kurzinfo" width="820"></p>

Einfach `suchmaschine-install.php` hochladen, im Browser aufrufen, Konto anlegen, einen oder mehrere API-Keys einfügen. Das ist die ganze Installation.

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

[Kosten](#was-kostet-das) · [Suchdienste einrichten](#suchdienste-einrichten-api-keys-holen) · [Funktionen](#funktionen) · [Neu in 1.5](#neu-in-15) · [Neu in 1.4](#neu-in-14) · [Lesemodus und PDF](#lesemodus-und-pdf) · [Neu in 1.3](#neu-in-13) · [Neu in 1.2](#neu-in-12) · [Konten und Token-Login](#konten-und-token-login) · [Tastenkürzel](#tastenkürzel) · [Voraussetzungen](#voraussetzungen) · [Installation](#installation) · [Daten schützen](#daten-schützen) · [Aktualisieren](#aktualisieren) · [Sichern](#sichern) · [Aufbau](#aufbau) · [Selbst bauen](#selbst-bauen) · [Gut zu wissen](#gut-zu-wissen) · [Haftungsausschluss](#haftungsausschluss) · [Lizenz](#lizenz)

## Was kostet das?

Bei normaler privater Nutzung nichts. Die Suchmaschine kann fünf Such-APIs nutzen, jede mit eigenem kostenlosen Monatskontingent. Schon eine reicht, mehrere zusammen ergeben einen dicken Puffer:

| Dienst | Kostenlos pro Monat | Gezählt wird in | Standardlimit | Kann |
|---|---|---|---|---|
| [Brave Search](https://brave.com/search/api/) | 5 $ Guthaben, etwa **1.000 Suchen** | Suchen | 950 | Web, Bilder, News, Videos, Infobox |
| [Tavily](https://tavily.com) | **1.000 Credits** (einfache Suche = 1 Credit) | Credits | 950 | Web, News, Kurzantwort |
| [Exa](https://exa.ai) | **10 $ Guthaben** | Cent (echte Kosten aus jeder Antwort) | 950 | Web, News |
| [parallel.ai](https://parallel.ai) | bis zu **5.000 Anfragen** (mit hinterlegter Karte) | Anfragen | 4.950 | Web |
| [Firecrawl](https://firecrawl.dev) | **1.000 Credits** im Free-Plan | Credits (gemeldet pro Suche) | 950 | Web, News |

- Pro Dienst stellst du in der Verwaltung ein **eigenes Limit** ein. Voreingestellt ist immer das Freikontingent **minus 50 als Puffer**. Ist ein Limit erreicht, nimmt die Suchmaschine automatisch den nächsten Dienst.
- **Reset-Datum:** Brave und Firecrawl melden selbst, wann ihr Kontingent neu startet, das übernimmt die Suchmaschine automatisch. Firecrawl und Tavily melden außerdem ihr Restguthaben, der Zähler gleicht sich daran an. Für Exa und parallel.ai trägst du einen **Stichtag** ein (der Tag im Monat, an dem das Kontingent neu startet, ohne Stichtag gilt der Monatserste).
- **Schon verbraucht:** Trägst du einen Key mitten im Monat ein, gibst du in der Verwaltung an, was laut Dashboard schon weg ist. Gilt bis zum nächsten Reset.
- Schalte bei allen Anbietern **automatisches Aufladen aus** und setz dort, wo es geht, ein Ausgabenlimit von 0. Dann kann nichts abgebucht werden.
- Ein **Zwischenspeicher** sorgt dafür, dass Neuladen, Zurück-Knopf und Blättern nichts kosten: bei Brave bis 60 Minuten (mehr erlauben Braves Bedingungen nicht), bei den anderen standardmäßig 8 Stunden.
- **Sofort-Antworten** (Rechner, Währungen, Wetter …), **Bangs** und **Werkzeuge** kosten gar nichts, weil sie keinen Suchdienst fragen.

Zum Vergleich: Wer 10 Mal am Tag sucht, kommt auf rund 300 Suchen im Monat.

## Suchdienste einrichten (API-Keys holen)

Die Keys trägst du nach der Installation unter **Verwaltung → Suchdienste** ein. Jeder Key wird vorher mit einer Testsuche geprüft. Dienste ohne Key tauchen nirgends auf. Keys gehören **nie** in den Code oder ins Git, sie liegen nur in deiner Datenbank im Ordner `data/`.

**Brave Search**
1. Auf [api-dashboard.search.brave.com](https://api-dashboard.search.brave.com) ein Konto anlegen.
2. Den Plan **„Search“** wählen. Brave möchte eine **Kreditkarte** sehen (gegen Missbrauch), abgerechnet wird über Guthaben ohne Abo. **Automatische Aufladung ausgeschaltet lassen.**
3. Unter **„API Keys“** einen Key erzeugen (beginnt mit `BSA`).
4. Das Reset-Datum schickt Brave bei jeder Suche mit, ein Stichtag ist nicht nötig. Den bisherigen Verbrauch im laufenden Monat (Dashboard unter „Usage“) kannst du unter „Schon verbraucht“ eintragen.

**Tavily**
1. Auf [app.tavily.com](https://app.tavily.com) anmelden (Google, GitHub oder E-Mail).
2. Der kostenlose „Researcher“-Plan enthält 1.000 Credits pro Monat, ohne Karte.
3. Im Dashboard den Key kopieren (beginnt mit `tvly-`). „Pay as you go“ ausgeschaltet lassen.

**Exa**
1. Auf [dashboard.exa.ai](https://dashboard.exa.ai) anmelden.
2. Unter **„API Keys“** einen Key erzeugen. Das monatliche Gratisguthaben (10 $) gilt für alle Endpunkte.
3. Unter „Billing“ keine automatische Aufladung einrichten. Die Suchmaschine zählt Exa **in Cent**, nach den echten Kosten, die Exa bei jeder Antwort mitschickt.

**parallel.ai**
1. Auf [platform.parallel.ai](https://platform.parallel.ai) anmelden.
2. Das monatliche Freiguthaben gibt es mit hinterlegter Karte. Unter „Billing“ **Auto-Reload aus** und ein **Spend Limit** setzen.
3. Unter **„API Keys“** einen Key erzeugen.

**Firecrawl**
1. Auf [firecrawl.dev](https://www.firecrawl.dev) anmelden, der Free-Plan enthält 1.000 Credits.
2. Im Dashboard unter **„API Keys“** den Key kopieren (beginnt mit `fc-`).
3. Reset-Datum und Restguthaben holt sich die Suchmaschine direkt bei Firecrawl, ein Stichtag ist nicht nötig.

**Tipp:** In der Verwaltung legst du außerdem die **Reihenfolge** fest (wer zuerst gefragt wird), schaltest die **Lastverteilung** an oder aus, wählst die Dienste für **„Alle zusammen“** und das **Ausweichziel** für den Notfall.

## Funktionen

- **Web, Bilder, News, Videos** in Reitern, wie man es kennt.
- **Gemischte Ergebnisse** wie bei den großen Suchmaschinen: Top-Meldungen, Videos, „Ähnliche Fragen“ und Diskussionen aus Foren erscheinen zwischen den Treffern, dazu eine **Kurzinfo** am Rand (z. B. aus Wikipedia). Was Brave zu einer Suche liefert, wird angezeigt.
- **Seitenweise oder endlos scrollen**, für jedes Konto einstellbar.
- **Zeitraum-Filter** (24 Stunden, Woche, Monat, Jahr), **Rechtschreibkorrektur** mit „Stattdessen suchen nach …“, **„Auf gut Glück“**.
- **Bildvorschau** im Großformat mit Pfeiltasten und Wischen zum Blättern, dazu ein **echter Vollbild-Modus** (Knopf oder Taste `F`) für Fernseher und große Bildschirme: randlos auf Schwarz, Bedienelemente blenden sich aus. Das Bild kommt dabei in **voller Auflösung über den eigenen Server**, ohne die strengen Sicherheitsregeln aufzuweichen und ohne Suche bei Brave.
- **Dateityp-Filter** (PDF, DOCX, XLSX, PPTX, TXT, EPUB, CSV, ODT und mehr, oder jede andere Endung zum Eintippen). Bleibt beim Blättern und Endlos-Scrollen erhalten.
- **Hilfeseite** mit allen Suchoperatoren, die Brave kennt (`site:`, `filetype:`, `intitle:`, `"…"`, `OR`, `NOT`, `-` …), mit Beispielen zum Anklicken und der Übersicht über die eigenen Bangs, Linsen und Domain-Listen.
- **Vorschläge aus den eigenen letzten Suchen.** Die bleiben nur im Browser, der Server merkt sich nichts.
- **Hell und dunkel**, automatisch nach dem Gerät oder per Knopf.
- **Deutsch und Englisch**, je nach Browser, umschaltbar unten auf der Seite.
- **Für Handy und Rechner**, lässt sich auch als App auf den Startbildschirm legen.
- **Als Standardsuche im Browser** eintragbar (OpenSearch), z. B. in Firefox mit einem Klick.
- **Konten:** Wer sich als Erster einträgt, wird Administrator und kann weitere Konten anlegen, etwa für die Familie. Jedes Konto hat eigene Einstellungen (Region, Sprache, SafeSearch, neuer Tab, Seiten oder endlos, Domain-Listen, Bangs, Linsen).
- **Verwaltung** für den Administrator: Suchdienste mit Keys, Stichtag, Limits und Status, Name der Suchmaschine, **eigenes Logo**, **Farbe**, Voreinstellungen für alle, Verbrauch mit Prognose, Kontingente pro Konto, auf Wunsch Suchen ohne Anmeldung (mit Grenze pro Stunde), Bilder in voller Auflösung an oder aus, Sicherung der Einstellungen.
- **Updates per Knopfdruck:** Die Suchmaschine schaut beim neuesten Release auf GitHub nach, prüft die Datei per Prüfsumme, sichert die laufende Version und spielt die neue ein. Zurück zur vorigen Version geht mit einem Klick. Auf Wunsch einmal am Tag automatisch.
- **Selbsttest:** Die Verwaltung prüft, ob die internen Ordner von außen wirklich gesperrt sind, und warnt sonst.
- **Ohne Werbung, ohne Tracking, ohne fremde Skripte.** Strenge Sicherheitsregeln (Content Security Policy), Links öffnen ohne Referrer, Vorschaubilder kommen über Braves Bild-Proxy.

## Wie bei ..., nur kostenlos

Bezahlte Suchmaschinen sind für ein paar Ideen bekannt, die es hier für alle gibt, die selbst hosten. Jedes Konto hat dabei **seinen eigenen Bereich**: Was einer einrichtet, sehen die anderen nicht.

- **Domains blockieren, hochstufen, herabstufen.** Unerwünschte Seiten verschwinden ganz, Lieblingsseiten rücken nach oben und bekommen einen kleinen Stern, andere rutschen nach hinten. Am schnellsten über das Menü ⋮ neben jedem Treffer, oder als Liste unter *Einstellungen → Domains*. Gefiltert wird erst nach dem Abruf, der Zwischenspeicher bleibt also für alle gleich.
- **Bangs** wie `!w Berlin`, `!gh`, `!yt`, `!osm`, `!so`, `!r`, `!dl`, `!leo` springen direkt zur Suche einer anderen Seite. Sie werden abgefangen, bevor Brave gefragt wird, und **kosten kein Guthaben**. `!i`, `!n` und `!v` wechseln zu Bildern, News und Videos. Mitgeliefert werden die wichtigsten, jeder kann sie für sich abschalten und **eigene anlegen** (Adresse mit `{q}` für den Suchbegriff). Funktioniert auch aus der Adressleiste des Browsers.
- **Linsen** schränken die Suche auf ein Thema ein, etwa Foren, Programmieren, Wikis oder Wissenschaft. Jeder kann **eigene Linsen** aus Domains und Operatoren bauen und die mitgelieferten ausblenden. Auswahl auf der Ergebnisseite unter „Linse“.
- **Sichern und wiederherstellen:** Unter *Einstellungen → Konto* lädt jedes Konto seine Einstellungen samt Listen, Bangs und Linsen als Datei herunter und spielt sie bei Bedarf wieder ein, auch auf einer anderen Installation. Der Administrator kann zusätzlich die Einstellungen der Installation sichern.

## Neu in 1.5

- **Werkzeuge neu sortiert:** eine Übersicht mit Kacheln nach Gruppen (Rechnen, Zeit und Datum, Zufall, Teilen und Sicherheit, Wetter und Netz), jedes Werkzeug öffnet sich auf einer **eigenen Seite** statt in einer langen Liste. Unten gibt es passende weitere Werkzeuge. Erreichbar überall über das **Kachel-Symbol oben rechts**, auch für Besucher, wenn Suchen ohne Anmeldung erlaubt ist.
- **Einstellungen aufgeräumt:** Login-Token und „Als Standardsuche im Browser“ stehen jetzt zusammen unter **Einstellungen → Browser**. Das Aussehen für alle bearbeitet der Administrator unter **Verwaltung → Aussehen**.
- **API-Keys holen, Schritt für Schritt** direkt in der Verwaltung bei jedem Suchdienst, ohne in die README schauen zu müssen.
- Kleinigkeiten: Der Name oben auf der Ergebnisseite wird nicht mehr abgeschnitten, die Währungskarte ist kompakter, Reiter auf dem Handy zeigen, dass man wischen kann. Ist noch kein Suchdienst eingerichtet, kommt der Administrator direkt zur Einrichtung, statt weitergeleitet zu werden.
- **Für Entwickler:** Code nach Themen aufgeteilt, englische Texte in `app/lang/en.php`, automatische Tests, Prüfung bei jedem Push, wiederholbarer Bau, Updates auf Wunsch mit Unterschrift. Für Nutzer bleibt es **eine einzige Installationsdatei**. Wie das zusammengebaut wird: [BUILD.md](BUILD.md).

## Neu in 1.4

### Aussehen nach Wunsch

Unter **Einstellungen → Aussehen** stellt jedes Konto für sich ein, wie die Suchmaschine aussieht. Den Standard für alle legt der Administrator unter **Verwaltung → Aussehen** fest (gilt auch für Besucher ohne Anmeldung).

- **17 fertige Designs**, hell und dunkel. Neben klarem Weiß und Schwarz gibt es gedämpfte Töne für alle, denen Weiß zu grell und Schwarz zu dunkel ist: **Papier**, **Nebel**, **Salbei**, **Schiefer**, **Dämmerung**. Für hell und dunkel jeweils ein Lieblingsdesign, bei „Wie das Gerät“ wird automatisch gewechselt.
- **Farbmischer** (aus „Kaufen“): Hintergrund, Flächen, Schrift, Akzent, dazu freiwillig Titel/Links und Nebentext. Mit Reglern für Farbton, Sättigung und Helligkeit, Farbcode, Live-Vorschau, Warnung bei zu wenig Kontrast und einem Knopf, der die Schriftfarben automatisch abstimmt. Funktioniert auch ohne JavaScript mit den Farbwählern des Browsers.
- **Schriften fest eingebaut:** Atkinson Hyperlegible, Lexend, Source Sans 3, Inter und Literata (mit Serifen). Sie liegen auf deinem eigenen Server, es wird **nichts bei Google** geladen. Alle unter der SIL Open Font License (Lizenzdateien in `app/assets/fonts/`).
- **Größen und Platz:** alles zusammen, Titel der Treffer und Beschreibungen getrennt, Zeilenabstand, Abstand zwischen den Treffern (kompakt bis luftig) und Breite der Trefferliste für große Bildschirme. Auf dem Handy ist die Quellen-Leiste jetzt eine wischbare Zeile.

### Nachladen und News

- **Endlos scrollen oder seitenweise** gilt jetzt für alle Reiter: Alle, Bilder, News und Videos.
- **Nachladen mit Widerstand:** Normales Runterscrollen lädt nichts nach. Erst wer ganz unten angekommen ist und dann bewusst weiterzieht (am Handy ein deutlicher Wisch, am Rechner mehrere Rasten am Mausrad), bekommt die nächsten Treffer. Ein Balken zeigt, wie weit es noch ist, loslassen setzt ihn zurück. Den letzten Eintrag in Ruhe lesen kostet also nichts. Der Knopf „Weitere Treffer“ lädt sofort.
- **Bilder:** 200 auf einmal mit einer einzigen Suche, Blättern und Nachladen kosten danach nichts mehr.
- **News:** Brave holt 50 Meldungen auf einmal, meist der ganze Tag in einer Anfrage. Am Ende geht es mit der letzten Woche weiter. Ältere News hat Brave kaum im Index, die kommen von **Exa mit genauem Zeitraum** (sonst Firecrawl oder Tavily). Leere Abschnitte gibt es nicht mehr.

## Lesemodus und PDF

Im Menü **⋮** an jedem Treffer steht **„Ohne Werbung lesen“** (nur mit Konto).

- Der eigene Server holt die Seite und schneidet den eigentlichen Inhalt heraus: Werbung, Menüs, Cookie-Banner, Kommentare und Skripte fliegen raus. Angezeigt wird alles in deiner Schrift und deinen Farben.
- **Rezepte** werden über ihre strukturierten Daten erkannt (schema.org), die fast jede Rezeptseite für Suchmaschinen mitliefert: Zutaten, Zubereitung, Zeiten, Bild. Ein Klick schaltet auf den ganzen Artikel mit allen Bildern um.
- Klappt das Herausschneiden nicht (Seiten, die nur mit JavaScript laufen oder Abrufe sperren), gibt es den Knopf **„Mit Firecrawl versuchen“**. Das kostet 1 Firecrawl-Credit.
- **Als PDF speichern:** mit Live-Vorschau, Schrift (klar oder klassisch), Größe, Zeilenabstand, 1 oder 2 Spalten und Rändern. **Bilder nach Wahl:** Antippen lässt ein Bild weg, so kommt nur ins PDF, was du willst. Das PDF entsteht im Browser, ohne fremde Dienste. Es kann Umlaute, ß und €, aber keine Zeichen außerhalb Westeuropas. Dafür gibt es **Drucken** mit einem sauberen Drucklayout (der Browser kann dort auch „Als PDF sichern“).

**Bitte fair nutzen.** Der Lesemodus ist zum privaten Lesen und Ablegen gedacht (Privatkopie), nicht zum Weiterverbreiten. Darum:
- nur für angemeldete Konten, höchstens 60 Seiten pro Stunde und Konto,
- auf dem Server wird nichts gespeichert, es gibt keine Links, über die andere den Lesemodus aufrufen könnten,
- Bezahlschranken werden nicht umgangen, kostenpflichtige Artikel bleiben beim Anbieter,
- Quelle, Abrufdatum und „nur zum privaten Gebrauch, Rechte beim Urheber“ stehen in der Ansicht und auf jeder PDF-Seite,
- der Abruf gibt sich ehrlich als „Suchmaschine … Lesemodus“ zu erkennen.

Die Rechte an den Inhalten bleiben bei den jeweiligen Seiten. Wer ein PDF weitergibt oder veröffentlicht, ist selbst dafür verantwortlich.

## Neu in 1.3

- **Neueste zuerst** ist Standard bei „Alle“ und bei News. Jedes Konto stellt das unter *Einstellungen → Suche* für sich um, der Administrator legt unter *Verwaltung → Einstellungen* den Standard für alle fest. In der Trefferliste lässt es sich jederzeit über „Sortierung“ umschalten.
- **News aus den letzten 24 Stunden.** Am Ende der Liste geht es automatisch mit der letzten Woche weiter, dann mit dem letzten Monat und dem letzten Jahr. Gibt es gar keine aktuellen News, wird gleich erweitert. Zeitraum und Erweitern sind pro Konto einstellbar.
- **Reset-Datum vom Dienst:** Brave (bei jeder Suche) und Firecrawl (eigene Abfrage, höchstens alle 6 Stunden, kostenlos) melden selbst, wann ihr Kontingent neu startet. Firecrawl und Tavily melden auch ihr Restguthaben. Dafür gibt es ein neues Feld **„Schon verbraucht“** für Dienste, die das nicht können.
- **Neuer Tab überall:** „Links in neuem Tab öffnen“ gilt für jeden Link nach draußen, auch im Wetter, in der Kurzinfo und in nachgeladenen Treffern.
- **Bangs ohne Aufblitzen:** Die Zwischenseite ist leer und hat die Farbe der Seite. Nur wenn das Ziel hängt, erscheint nach 2 Sekunden ein Knopf „Weiter“.

## Neu in 1.2

### Mehrere Suchdienste, Lastverteilung, Ausweichen

- **Automatisch:** Die Suchmaschine fragt den ersten Dienst in deiner Reihenfolge, der noch Guthaben hat und funktioniert. Über jeder Trefferliste steht, wer geliefert hat und warum ein anderer übersprungen wurde.
- **Lastverteilung:** Verbraucht ein Dienst schneller, als sein Kontingent bis zum Reset reicht, gehen Suchen schon vorher an den nächsten. So läuft Brave nicht am 20. leer, sondern alle Kontingente reichen gleichmäßig.
- **Störungen:** Liefert ein Dienst einen Fehler oder ist nicht erreichbar, springt sofort der nächste ein. Der gestörte Dienst pausiert 10 Minuten (bei falschem Key eine Stunde), damit keine Suche auf ihn warten muss. In der Verwaltung siehst du letzten Erfolg, letzten Fehler im Klartext und die durchschnittliche Antwortzeit.
- **Wenn gar nichts mehr geht** (alles aufgebraucht oder gestört), leitet die Suche automatisch an ein Ausweichziel weiter, z. B. Startpage, DuckDuckGo, Mojeek oder eine eigene Adresse mit `{q}`. Die Suche funktioniert also immer.
- **Gezielt ansteuern:** `!brave`, `!tv` (Tavily), `!exa`, `!pa` (parallel.ai), `!fc` (Firecrawl). Oder in der **Quellen-Leiste** über den Treffern einen Dienst anklicken, mit den restlichen Credits daneben.
- **Alle zusammen:** `!multi` (oder `!alle`) fragt mehrere Dienste gleichzeitig, legt doppelte Adressen zusammen und kennzeichnet jeden Treffer mit Badges wie `[Brave]` `[Exa]` `[Tavily]`.
- **Kurzantwort:** Wenn Tavily sucht, kommt eine kurze KI-Antwort mit (ohne Aufpreis, abschaltbar).
- **Wikipedia-Kurzinfo:** Liefert ein Dienst keine Infobox (alle außer Brave), ergänzt die Suchmaschine eine kostenlose Kurzinfo von Wikipedia. So sieht die Seite überall gleich aus.

### Verbrauch, Prognose, Kontingente

- Unter **Verwaltung → Verbrauch** pro Dienst: verbraucht / Limit, Zeitraum, **Reset-Datum mit verbleibenden Tagen**, Verbrauch heute, **Tagesdurchschnitt**, **Hochrechnung bis zum Reset** und ein klares Urteil, ob es reicht (sonst: „reicht nur bis etwa …“). Dazu der Verlauf nach Monaten und der Verbrauch pro Konto.
- **Warnhinweis:** Ab 80 % (einstellbar) oder wenn die Prognose nicht bis zum Reset reicht, sieht der Administrator oben einen Hinweis.
- **Kontingente pro Konto:** Unter *Verwaltung → Konten → Kontingent* legst du fest, welche Dienste ein Konto nutzen darf und wie viele Credits es pro Zeitraum bekommt. „Gerecht aufteilen“ verteilt die Limits gleichmäßig auf alle Konten.

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
| `losen: Anna, Bernd, Carla`, `teams 3: …`, `reihenfolge: …` | auslosen, Gruppen bilden, Reihenfolge mischen |

### Werkzeuge

Unter **Werkzeuge** (Kachel-Symbol oben rechts, Konto-Menü oder Startseite) stehen alle kleinen Helfer an einem Ort: eine Übersicht nach Gruppen, jedes Werkzeug mit eigener Seite und eigenen Eingabefeldern. Niemand muss wissen, was man ins Suchfeld tippt. Nichts davon fragt einen Suchdienst, kostet also kein Guthaben.

- **Rechnen und Umrechnen:** Rechner mit Tastenfeld, Einheiten (Länge, Gewicht, Temperatur, Volumen, Fläche, Geschwindigkeit, Zeit, Datenmengen), Währungen nach EZB-Kurs
- **Zeit und Datum:** Timer mit Ton und Stoppuhr, Countdown, **Datumsrechner** (Tage zwischen zwei Daten samt Werktagen, Datum plus/minus Tage, Wochen, Monate, Jahre), **Alter und nächster Geburtstag**, **Feiertage nach Bundesland**, Kalenderwoche, Datum und Uhrzeit
- **Zufall und Entscheidungen:** Würfel, Münze, Zufallszahl, **Losen und Gruppen bilden** (Namen eingeben, einen auslosen, in Gruppen aufteilen oder die Reihenfolge mischen)
- **Teilen und Sicherheit:** **Passwort-Generator** (Länge, Groß/Klein, Zahlen, Sonderzeichen, ohne Verwechsler, merkbare Wort-Passwörter, Kopier-Knopf, Stärke-Anzeige), **QR-Code-Generator** mit SVG-Download, deine **Kurzlinks** mit Aufrufzahlen (funktionieren für jeden ohne Anmeldung)
- **Wetter und Netz:** Wetter für deinen Ort und die eigene IP-Adresse

Alles funktioniert auch ohne JavaScript. Mit JavaScript laufen Timer und Stoppuhr live, und der Rechner hat ein Tastenfeld zum Antippen.

**Töne:** Für den Timer gibt es sechs Töne (Glocke, Gong, Ding-Dong, sanfte Tonfolge, Vogelzwitschern, Wecker) mit „Ton testen“. Sie sind rein rechnerisch erzeugt, ohne fremde Aufnahmen, und liegen als MP3 in `app/assets/sounds/`. Frei verwendbar (CC0), auch in anderen Projekten.

### Filter, Sortierung, Ergebnis-Menü

- **Nach Datum sortieren (neueste zuerst):** streng chronologisch, Treffer ohne Datum ans Ende. Bei aktivem Zeitfilter automatisch.
- **Max. 3 Treffer pro Domain:** Schalter in der Filterleiste, standardmäßig an.
- **„Alle Filter zurücksetzen“** erscheint, sobald ein Filter vom Standard abweicht.
- **×** im Suchfeld zum Leeren, auf dem Handy extra groß.
- **Menü ⋮** an jedem Treffer: *Nur auf dieser Domain suchen* (`site:`), *Archiv* bei web.archive.org und archive.today, *Als QR-Code anzeigen*, *Adresse kopieren*, mit Konto dazu hoch-/herabstufen/blockieren.

### Bilder

- **Filter wie bei Google:** Ausrichtung (Quer, Hoch, Quadratisch) und Größe (Sehr groß ab 1 MP, Groß ab 0,3 MP, Mittel, Klein unter 0,15 MP), nach den Maßen, die Brave liefert.
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
| `?` | Übersicht aller Kürzel (deutsche Tastatur: `Shift` + `ß`) |
| `Esc` | Übersicht, Menüs, Bildvorschau, Vollbild schließen |

## Voraussetzungen

- Webspace mit **PHP 8.0 oder neuer**, dazu die Erweiterungen `pdo_sqlite` und `curl`. Das hat praktisch jeder Hoster.
- Der Server muss Verbindungen nach außen aufbauen dürfen: zu den Such-APIs (`api.search.brave.com`, `api.tavily.com`, `api.exa.ai`, `api.parallel.ai`, `api.firecrawl.dev`), für Sofort-Antworten zu `api.frankfurter.dev`, `geocoding-api.open-meteo.com`, `api.brightsky.dev`, `api.open-meteo.com`, `wttr.in`, `*.wikipedia.org` und für Updates zu `github.com`.
- Für die verschlüsselte Sicherung und das verschlüsselte Speichern der Login-Tokens die Erweiterung `openssl` (fast immer vorhanden).
- **HTTPS.** Ohne geht das Passwort im Klartext durchs Netz. Bei den meisten Hostern ist ein kostenloses Let's-Encrypt-Zertifikat mit einem Klick eingerichtet.
- **Apache** (oder LiteSpeed) sperrt die internen Ordner von selbst über `.htaccess`. Unter **nginx** sind zwei Zeilen in der Konfiguration nötig, siehe [Daten schützen](#daten-schützen).
- Mindestens ein **API-Key** eines Suchdienstes (kostenlos, siehe [Suchdienste einrichten](#suchdienste-einrichten-api-keys-holen)).

## Installation

1. **API-Keys holen**, siehe [Suchdienste einrichten](#suchdienste-einrichten-api-keys-holen). Einer reicht für den Anfang.
2. Aus dem [neuesten Release](../../releases/latest) die Datei **`suchmaschine-install.php`** herunterladen und per FTP oder Dateimanager in ein Verzeichnis auf dem Webspace kopieren, z. B. `httpdocs/suche/`. Soll die Suchmaschine direkt unter der Domain laufen, kommt die Datei direkt nach `httpdocs/`.
3. Die Datei **sofort** im Browser aufrufen, z. B. `https://deine-domain.de/suche/suchmaschine-install.php`, und auf **„Jetzt installieren“** klicken. Sie packt das Programm aus, löscht sich selbst und leitet weiter. Meldet sie, dass sie sich nicht löschen konnte, die Datei bitte von Hand löschen.
4. Gleich danach **Konto anlegen** und optional den **Brave-Key einfügen**. Der Key wird mit einer Testsuche geprüft. Dieses Konto ist der Administrator. Nicht aufschieben: Wer zuerst kommt, wird Administrator. Weitere Dienste (Tavily, Exa, parallel.ai, Firecrawl) trägst du danach unter *Verwaltung → Suchdienste* ein.
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
               Suchdienste (providers.php, brave.php), Filter, Domain-Listen, Bangs und Linsen, Sofort-Antworten (instant.php),
               QR-Codes, Bild-Proxy, Lesemodus, Aussehen, Logo, Sicherung, Update
app/lang/      englische Texte (en.php)
app/views/     HTML-Vorlagen
app/assets/    CSS, JavaScript, PDF-Baukasten (pdf.js), Symbole, Schriften, Töne (werden über index.php ausgeliefert)
data/          entsteht beim ersten Aufruf: Datenbank, Einstellungen, Logo (geheim!)
tests/         automatische Tests
tools/         Release bauen (nur für Entwickler)
```

Die Dateien in `app/` tun nichts, wenn man sie direkt aufruft, alles läuft über `index.php`. Eine ausführliche Übersicht, welche Datei wofür zuständig ist, steht in [BUILD.md](BUILD.md#wo-was-im-quellcode-steht).

## Selbst bauen

Im Quellcode ist das Programm auf viele Dateien verteilt, Nutzer bekommen trotzdem nur **eine Datei**. Bauen kann sie jeder selbst, nur mit PHP:

```sh
php tools/build-release.php
```

Das prüft die Syntax, lässt alle Tests laufen, baut `dist/suchmaschine-install.php`, installiert sie zur Probe und schreibt Prüfsummen. Der Bau ist wiederholbar: Gleicher Quellcode ergibt dieselbe Datei, so lässt sich jedes Release mit dem Quellcode vergleichen. Alle Einzelheiten, auch zur Unterschrift der Updates: [BUILD.md](BUILD.md). Mitmachen: [CONTRIBUTING.md](CONTRIBUTING.md), Sicherheitslücken melden: [SECURITY.md](SECURITY.md).

## Gut zu wissen

- **Datenschutz:** Die Suchdienste sehen die Suchbegriffe, aber nicht, wer sucht, denn die Anfrage kommt von deinem Server. Gezählt wird nur die Menge der Anfragen pro Konto, nie ihr Inhalt. Die Suchmaschine selbst speichert keine Suchbegriffe, nur kurz im Zwischenspeicher. Die letzten Suchen für die Vorschläge bleiben im Browser des jeweiligen Geräts. Vorschaubilder und Symbole lädt der Browser über Braves Bild-Proxy.
- **Bilder in voller Auflösung:** Für die große Vorschau holt der Server das Originalbild und reicht es weiter. Die fremde Seite sieht nur den Server, der Browser lädt weiter nur von der eigenen Adresse. Gegen Missbrauch ist jede Bildadresse signiert, interne Netze sind gesperrt, gespeichert wird nichts. Kostet keine Suche, nur etwas Datenverkehr, und lässt sich in der Verwaltung abschalten.
- **Suchoperatoren** gibt die Suchmaschine unverändert an den Suchdienst weiter. Brave versteht die meisten, die anderen Dienste vor allem Anführungszeichen und `site:`. Klammern kennt Brave nicht.
- **Mehrere Personen:** Konten für Familie oder Freunde sind schnell angelegt. Alle Suchen gehen vom selben Guthaben ab. Mit Kontingenten pro Konto teilst du es auf.
- **Suchen ohne Anmeldung** lässt sich einschalten, etwa fürs Heimnetz. Jede Suche kostet dann dein Guthaben. Die Grenze pro Stunde und Besucher bremst Missbrauch, das Monatslimit gilt trotzdem.
- **Endlos scrollen:** Jedes Nachladen ist eine echte Suche bei Brave. Darum holt die Suchmaschine dabei 20 Treffer auf einmal. Brave liefert pro Suchbegriff höchstens 10 Seiten.
- **Bedingungen der Anbieter:** Ergebnisse dürfen nicht weiterverkauft werden, Brave erlaubt nur kurzes Zwischenspeichern. Ein Hinweis auf die genutzten Dienste steht unten auf jeder Seite.
- **Wetter, Wechselkurse, Wikipedia:** Diese kostenlosen Dienste fragt nur der Server, mit Zwischenspeicher (Kurse 12 Stunden, Wetter 20 bis 30 Minuten, Wikipedia einen Tag).

## Sicherheit

Die Suchmaschine wurde für 1.2 aus Angreifersicht geprüft und gehärtet:

- **XSS:** Jede Ausgabe wird escaped, Texte der Suchdienste nur mit `<strong>` als Ausnahme. Strenge Content Security Policy ohne Inline-Skripte, QR-Codes, Logos und Bilder aus dem Proxy kommen mit eigener Sandbox-CSP.
- **CSRF:** Jedes Formular braucht das Sitzungs-Token und muss von der eigenen Seite kommen. Der Token-Login funktioniert nur direkt (Adressleiste, Lesezeichen, Browser-Suche), nicht über Links fremder Seiten, und wechselt nie stillschweigend das Konto.
- **SQL-Injection:** ausschließlich vorbereitete Abfragen. Wiederherstellungen werden Feld für Feld geprüft.
- **SSRF:** Der Bild-Proxy lädt nur signierte Adressen, nur öffentliche IPs (DNS fest verdrahtet, jede Weiterleitung neu geprüft), nur echte Bilder. Wetter, Kurse und Wikipedia gehen nur an fest eingebaute HTTPS-Adressen, ohne Weiterleitungen.
- **Brute-Force und Timing:** Passwort- und Token-Login werden nach Fehlversuchen gebremst, Vergleiche laufen mit `hash_equals()` bzw. `password_verify()` in konstanter Zeit.
- **Rechner:** eigener Parser, kein `eval`, Grenzen für Länge, Tiefe und Exponenten.
- **Path Traversal:** feste Dateinamen, Updates nur mit Prüfsumme und erlaubten Pfaden.
- **Geheimnisse:** Login-Tokens liegen verschlüsselt in der Datenbank, der Schlüssel in `data/config.php`. Weiterleitungen mit Token werden nicht zwischengespeichert. Sitzungen liegen auf dem Server und lassen sich jederzeit beenden.
- **Missbrauch ohne Anmeldung:** Wetter zählt zur Stundengrenze, „Alle zusammen“ gibt es nur mit Konto.

## Haftungsausschluss

Die Suchmaschine ist kostenlose Hobby-Software und wird **so, wie sie ist, ohne jede Gewähr** bereitgestellt. Sie ist mit Sorgfalt gebaut und getestet, die Nutzung erfolgt aber **auf eigene Gefahr**. Wer sie betreibt, ist für den Server, seine Updates und Sicherungen selbst verantwortlich, ebenso dafür, die Bedingungen der genutzten Anbieter einzuhalten. Dieses Projekt steht in keiner Verbindung zu Brave Software, Tavily, Exa, Parallel Web Systems oder Firecrawl.

## Lizenz

GNU Affero General Public License v3.0, siehe [LICENSE](LICENSE).
