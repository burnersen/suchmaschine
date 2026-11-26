<?php defined('SM_VERSION') || exit;
/** Hilfe: Suchoperatoren mit Beispielen, dazu dynamisch die Bangs, Linsen und Domain-Listen dieses Kontos */
$ex = function (string $q, array $more = []) { return '<a class="ex" href="' . h(smUrl(['q' => $q] + $more)) . '"><code>' . h($q) . '</code></a>'; };
$bangs = smBangs($me);
$lenses = smLenses($me);
$lists = smDomainLists($me);
$de = smLang() === 'de';
$ops = [
    ['"…"', t('Genau diese Wortfolge'), $de ? '"Ohne Fleiß kein Preis"' : '"the quick brown fox"'],
    ['-' . t('wort'), t('Wort ausschließen'), $de ? 'jaguar -auto' : 'jaguar -car'],
    ['+' . t('wort'), t('Wort muss vorkommen'), $de ? 'grafikkarte +freesync' : 'gpu +freesync'],
    ['site:', t('Nur Seiten dieser Domain (Unterdomains zählen mit)'), $de ? 'mietrecht site:bundesgerichtshof.de' : 'tax return site:gov.uk'],
    ['-site:', t('Diese Domain ausschließen (zuverlässiger: NOT site:)'), $de ? 'rezept lasagne -site:pinterest.com' : 'lasagna recipe -site:pinterest.com'],
    ['NOT', t('Begriff oder Domain ausschließen (großgeschrieben)'), $de ? 'brave browser NOT site:brave.com' : 'brave browser NOT site:brave.com'],
    ['OR', t('Eins von beiden genügt (großgeschrieben)'), $de ? 'urlaub kroatien OR slowenien' : 'holiday croatia OR slovenia'],
    ['AND', t('Beides muss passen (großgeschrieben)'), $de ? 'visum loc:gb AND lang:en' : 'visa loc:gb AND lang:en'],
    ['filetype:', t('Nur Dateien dieses Typs'), $de ? 'steuererklärung anleitung filetype:pdf' : 'tax guide filetype:pdf'],
    ['ext:', t('Nur Dateien mit dieser Endung (wie filetype:)'), $de ? 'bedienungsanleitung waschmaschine ext:pdf' : 'washing machine manual ext:pdf'],
    ['intitle:', t('Begriff muss im Titel stehen'), $de ? 'konferenz intitle:2026' : 'conference intitle:2026'],
    ['inbody:', t('Begriff muss im Text der Seite stehen'), $de ? 'grafikkarte inbody:"founders edition"' : 'graphics card inbody:"founders edition"'],
    ['inpage:', t('Begriff im Titel oder im Text'), $de ? 'oscars inpage:"bestes kostüm"' : 'oscars inpage:"best costume design"'],
    ['lang:', t('Sprache der Seite (zweistelliger Code)'), $de ? 'visum lang:es' : 'visa lang:es'],
    ['loc:', t('Land der Seite (zweistelliger Code)'), $de ? 'niagarafälle loc:ca' : 'niagara falls loc:ca'],
];
$combos = $de ? [
    ['steuererklärung "anlage n" filetype:pdf site:bundesfinanzministerium.de', t('Ein amtliches Formular als PDF')],
    ['python asyncio intitle:tutorial -site:medium.com', t('Anleitungen mit dem Wort im Titel, ohne eine bestimmte Seite')],
    ['"wärmepumpe" erfahrungen site:reddit.com OR site:gutefrage.net', t('Erfahrungen nur aus bestimmten Foren')],
    ['klimawandel studie filetype:pdf site:edu intitle:2025', t('Wissenschaftliche Arbeiten als PDF')],
    ['jobbörse -indeed -stepstone lang:de', t('Mehrere Begriffe ausschließen')],
] : [
    ['climate change filetype:pdf site:edu intitle:2025', t('Wissenschaftliche Arbeiten als PDF')],
    ['python asyncio intitle:tutorial -site:medium.com', t('Anleitungen mit dem Wort im Titel, ohne eine bestimmte Seite')],
    ['"heat pump" experience site:reddit.com OR site:news.ycombinator.com', t('Erfahrungen nur aus bestimmten Foren')],
    ['"form 1040" instructions filetype:pdf site:irs.gov', t('Ein amtliches Formular als PDF')],
    ['ai startup -google -microsoft -meta', t('Mehrere Begriffe ausschließen')],
];
?>
<main class="page page--help">
    <h1 class="page__title"><?= h(t('Hilfe')) ?></h1>
    <p class="page__lead"><?= h(t('Diese Suchmaschine ist mehr als eine Trefferliste: mehrere Suchdienste mit Lastverteilung, Sofort-Antworten ohne Guthaben, Werkzeuge, Filter wie bei den Großen und volle Tastatursteuerung. Hier steht alles, was geht. Klick auf ein Beispiel, um es auszuprobieren.')) ?></p>
    <nav class="toc">
        <a href="#ueberblick"><?= h(t('Überblick')) ?></a>
        <a href="#dienste"><?= h(t('Suchdienste')) ?></a>
        <a href="#sofort"><?= h(t('Sofort-Antworten')) ?></a>
        <a href="#werkzeuge"><?= h(t('Werkzeuge')) ?></a>
        <a href="#filter"><?= h(t('Filter und Sortierung')) ?></a>
        <a href="#bilder"><?= h(t('Bilder')) ?></a>
        <a href="#menue"><?= h(t('Menü ⋮')) ?></a>
        <a href="#operatoren"><?= h(t('Suchoperatoren')) ?></a>
        <a href="#kombinieren"><?= h(t('Kombinieren')) ?></a>
        <a href="#dateitypen"><?= h(t('Dateitypen')) ?></a>
        <a href="#bangs"><?= h(t('Bangs')) ?></a>
        <a href="#linsen"><?= h(t('Linsen')) ?></a>
        <a href="#domains"><?= h(t('Domain-Listen')) ?></a>
        <a href="#token"><?= h(t('Token und Browser')) ?></a>
        <a href="#tasten"><?= h(t('Tastenkürzel')) ?></a>
        <a href="#privat"><?= h(t('Datenschutz')) ?></a>
    </nav>

    <section class="panel" id="ueberblick">
        <h2><?= h(t('Überblick')) ?></h2>
        <div class="feat">
<?php foreach ([
    ['🔎', t('Mehrere Suchdienste'), t('Brave, Tavily, Exa, parallel.ai und Firecrawl. Automatisch der beste verfügbare, gezielt per Bang oder alle zusammen.')],
    ['⚖️', t('Lastverteilung'), t('Die freien Kontingente reichen gleichmäßig über den Monat. Fällt ein Dienst aus, springt der nächste ein.')],
    ['⚡', t('Sofort-Antworten'), t('Rechner, Währungen, Einheiten, Wetter, Kalenderwoche, Würfel, Timer und mehr, ohne Guthaben zu verbrauchen.')],
    ['🧰', t('Werkzeuge'), t('Rechner, Timer, Feiertage, Passwörter, QR-Codes, Scanner, Kurzlinks und mehr.')],
    ['🎛️', t('Filter'), t('Zeitraum, Dateityp, Linsen, Sortierung nach Datum, max. 3 Treffer pro Domain, Bildfilter wie bei Google.')],
    ['⌨️', t('Tastatur'), t('Durch alle Treffer mit J/K oder Pfeilen, Enter öffnet, ? zeigt alle Kürzel.')],
    ['🔑', t('Login-Token'), t('Einmal ?token=… aufrufen und ein Jahr angemeldet bleiben, auch als Standardsuche im Browser.')],
    ['🛡️', t('Privat'), t('Keine Werbung, kein Tracking, kein Suchverlauf auf dem Server. Alles läuft auf deinem eigenen Webspace.')],
] as $f): ?>
            <div class="feat__item"><span class="feat__ico" aria-hidden="true"><?= $f[0] ?></span><strong><?= h($f[1]) ?></strong><span class="muted small"><?= h($f[2]) ?></span></div>
<?php endforeach; ?>
        </div>
    </section>

    <section class="panel" id="dienste">
        <h2><?= h(t('Suchdienste')) ?></h2>
        <p class="muted small"><?= h(t('Normalerweise wählt die Suchmaschine selbst: der Reihe nach den ersten Dienst, der noch Guthaben hat und funktioniert. Ist ein Dienst gestört, pausiert er 10 Minuten, damit niemand warten muss. Über jeder Trefferliste steht, wer geliefert hat, und mit einem Klick fragst du einen anderen.')) ?></p>
        <div class="table-wrap">
        <table class="table table--help">
            <thead><tr><th><?= h(t('Kürzel')) ?></th><th><?= h(t('Wirkung')) ?></th><th><?= h(t('Beispiel')) ?></th></tr></thead>
            <tbody>
<?php foreach (smProviders() as $pid => $pp): ?>
                <tr><td><?php foreach ($pp['bangs'] as $bb): ?><code>!<?= h($bb) ?></code> <?php endforeach; ?></td><td><?= h(t('nur bei %s suchen', $pp['name'])) ?><?= smProvActive($pid) ? '' : ' <span class="tag">' . h(t('nicht eingerichtet')) . '</span>' ?></td><td><?= $ex('!' . $pp['bangs'][0] . ' ' . ($de ? 'wärmepumpe test' : 'heat pump review')) ?></td></tr>
<?php endforeach; ?>
                <tr><td><code>!multi</code> <code>!alle</code></td><td><?= h(t('mehrere Dienste gleichzeitig, doppelte Treffer zusammengelegt, jeder Treffer mit Quell-Badge')) ?></td><td><?= $ex('!multi ' . ($de ? 'linux laptop' : 'linux laptop')) ?></td></tr>
            </tbody>
        </table>
        </div>
        <ul class="bullets">
            <li><?= h(t('Lastverteilung: Verbraucht ein Dienst schneller, als sein Kontingent bis zum Reset reicht, gehen Suchen schon vorher an den nächsten.')) ?></li>
            <li><?= h(t('Gleiche Suchen kommen eine Weile aus dem Zwischenspeicher und kosten nichts (Brave bis 60 Minuten, die anderen standardmäßig 8 Stunden).')) ?></li>
            <li><?= h(t('Ist alles aufgebraucht oder gestört, leitet die Suche zum eingestellten Ausweichziel weiter (z. B. Startpage). So funktioniert sie immer.')) ?></li>
            <li><?= h(t('Bilder und Videos liefert nur Brave. News gehen auch über Tavily, Exa und Firecrawl.')) ?></li>
            <li><?= h(t('Liefert Tavily, gibt es oben eine kurze KI-Antwort. Liefert ein Dienst ohne Infobox, ergänzt die Suchmaschine eine Kurzinfo von Wikipedia.')) ?></li>
        </ul>
    </section>

    <section class="panel" id="sofort">
        <h2><?= h(t('Sofort-Antworten')) ?></h2>
        <p class="muted small"><?= h(t('Diese Eingaben beantwortet die Suchmaschine selbst, bevor ein Suchdienst gefragt wird. Sie kosten also kein Guthaben. Unter jeder Antwort steht „Trotzdem im Web suchen“.')) ?></p>
        <div class="table-wrap">
        <table class="table table--help">
            <thead><tr><th><?= h(t('Was')) ?></th><th><?= h(t('Beispiele')) ?></th></tr></thead>
            <tbody>
<?php foreach ([
    [t('Taschenrechner: + − × ÷ ^, Klammern, Wurzel, Prozent, Komma oder Punkt'), $de ? ['(12,5 + 7) * 3', '200 + 19%', '15% von 80', '2^10', 'sqrt 2', '= 1.234,5 / 3'] : ['(12.5 + 7) * 3', '200 + 19%', '15% of 80', '2^10', 'sqrt 2', '= 1,234.5 / 3']],
    [t('Währungen (Kurse der EZB, einmal am Tag aktualisiert)'), ['100 usd in eur', '50 chf in eur', '20 € in £', '1000 jpy to usd', '100 usd']],
    [t('Einheiten: Länge, Gewicht, Temperatur, Daten, Volumen, Fläche, Tempo, Zeit'), $de ? ['10 km in meilen', '5 kg in lb', '20 °c in f', '1 gib in mb', '3 l in gal', '100 km/h in mph', '2 h in min'] : ['10 km in miles', '5 kg in lb', '20 °c in f', '1 gib in mb', '3 l in gal', '100 km/h in mph', '2 h in min']],
    [t('Wetter mit Vorhersage (DWD über Bright Sky, Open-Meteo oder wttr.in)'), $de ? ['wetter berlin', 'wetter in hamburg', 'wetter'] : ['weather london', 'weather in paris', 'weather']],
    [t('Eigene IP-Adresse'), $de ? ['meine ip', 'ip'] : ['my ip', 'ip']],
    [t('Kalenderwoche mit Datumsspanne'), $de ? ['kw', 'kw 12', 'kw 1 2027'] : ['week', 'week 12', 'week 1 2027']],
    [t('Datum und Uhrzeit'), $de ? ['datum', 'uhrzeit', 'heute'] : ['date', 'time', 'today']],
    [t('Countdown'), $de ? ['countdown weihnachten', 'countdown silvester', 'countdown 1.5.', 'tage bis ostern'] : ['countdown christmas', 'countdown 2027-01-01', 'days until easter']],
    [t('Timer und Stoppuhr (mit Ton am Ende)'), $de ? ['timer 5 min', 'timer 90 s', 'stoppuhr'] : ['timer 5 min', 'timer 90 s', 'stopwatch']],
    [t('Würfel, Münze, Zufallszahl'), $de ? ['würfel', 'würfel 3', '2w20', 'münze', 'zufallszahl 1-49'] : ['dice', '2d20', 'coin', 'random 1-49']],
    [t('Datumsrechner und Alter'), $de ? ['tage zwischen 1.1.2026 und 24.12.2026', 'heute + 6 wochen', 'alter 12.05.1940'] : ['days between 2026-01-01 and 2026-12-24', 'today + 30 days', 'age 1940-05-12']],
    [t('Feiertage'), $de ? ['feiertage', 'feiertage 2027 bayern', 'feiertage nrw'] : ['holidays', 'holidays 2027 bavaria']],
    [t('Passwort-Generator (läuft nur im Browser)'), $de ? ['passwort', 'passwort 32', 'passphrase'] : ['password', 'password 32', 'passphrase']],
    [t('QR-Code für eine Adresse oder einen Text'), ['qr https://example.org', $de ? 'qr WLAN-Passwort ist geheim' : 'qr hello world']],
    [t('Kurzlink anlegen (mit Konto)'), $de ? ['kurz https://example.org/ein/langer/pfad'] : ['short https://example.org/a/long/path']],
] as $row): ?>
                <tr><td><?= h($row[0]) ?></td><td class="chips"><?php foreach ($row[1] as $e) echo $ex($e) . ' '; ?></td></tr>
<?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>

    <section class="panel" id="werkzeuge">
        <h2><?= h(t('Werkzeuge')) ?></h2>
        <p class="muted small"><?= h(t('Unter Werkzeuge (Kachel-Symbol oben rechts) gibt es alle Helfer nach Gruppen, jeden auf einer eigenen Seite: Rechner, Einheiten, Währungen, Timer, Countdown, Datumsrechner, Feiertage, Kalenderwoche, Würfel und Losen, Passwort-Generator, QR-Codes, deine Kurzlinks, Wetter und die eigene IP-Adresse.')) ?>
            <a href="<?= h(smUrl(['a' => 'tools'])) ?>"><?= h(t('Zu den Werkzeugen')) ?></a></p>
    </section>

    <section class="panel" id="filter">
        <h2><?= h(t('Filter und Sortierung')) ?></h2>
        <dl class="keys keys--wide">
            <div><dt><?= h(t('Zeitraum')) ?></dt><dd><?= h(t('Letzte 24 Stunden, Woche, Monat oder Jahr. Dann wird automatisch streng nach Datum sortiert, neueste zuerst.')) ?></dd></div>
            <div><dt><?= h(t('Sortierung')) ?></dt><dd><?= h(t('„Nach Datum (neueste zuerst)“ sortiert die Treffer nach ihrem Datum, Treffer ohne Datum kommen ans Ende.')) ?></dd></div>
            <div><dt><?= h(t('Max. 3 pro Domain')) ?></dt><dd><?= h(t('Standardmäßig an: Eine einzelne Seite kann die Liste nicht fluten. Der Schalter in der Filterleiste zeigt alles.')) ?></dd></div>
            <div><dt><?= h(t('Dateityp')) ?></dt><dd><?= h(t('Nur PDFs, Tabellen, Präsentationen … oder jede andere Endung.')) ?></dd></div>
            <div><dt><?= h(t('Linse')) ?></dt><dd><?= h(t('Suche auf ein Thema oder ausgewählte Seiten eingrenzen (siehe unten).')) ?></dd></div>
            <div><dt><?= h(t('Zurücksetzen')) ?></dt><dd><?= h(t('Sobald ein Filter vom Standard abweicht, erscheint „Alle Filter zurücksetzen“.')) ?></dd></div>
            <div><dt>×</dt><dd><?= h(t('Das Kreuz im Suchfeld leert es mit einem Tipp, auch auf dem Handy.')) ?></dd></div>
        </dl>
    </section>

    <section class="panel" id="bilder">
        <h2><?= h(t('Bilder')) ?></h2>
        <dl class="keys keys--wide">
            <div><dt><?= h(t('Ausrichtung')) ?></dt><dd><?= h(t('Alle, Querformat, Hochformat oder Quadratisch.')) ?></dd></div>
            <div><dt><?= h(t('Größe')) ?></dt><dd><?= h(t('Sehr groß (ab 1 Megapixel), Groß (ab 0,3 Megapixel), Mittel, Klein (unter 0,15 Megapixel), nach den Maßen, die Brave liefert. Brave kennt meist die Größe, in der ein Bild auf der Seite steckt, darum sind die Stufen bewusst niedrig.')) ?></dd></div>
            <div><dt><?= h(t('Sortierung')) ?></dt><dd><?= h(t('„Höchste Auflösung zuerst“ sortiert nach Breite × Höhe.')) ?></dd></div>
            <div><dt><?= h(t('Vorschau')) ?></dt><dd><?= h(t('Klick auf ein Bild öffnet die große Vorschau, F oder Doppelklick das Vollbild. Dort ist das Bild immer ganz zu sehen, zentriert auf Schwarz. Blättern mit ← →, Wischen oder den Pfeilen.')) ?></dd></div>
        </dl>
        <p class="chips"><?= $ex($de ? 'sonnenuntergang' : 'sunset', ['t' => 'images', 'io' => 'land', 'isort' => 'res']) ?></p>
    </section>

    <section class="panel" id="menue">
        <h2><?= h(t('Menü ⋮ an jedem Treffer')) ?></h2>
        <ul class="bullets">
            <li><?= h(t('Nur auf dieser Domain suchen: setzt site:domain.tld vor deine Suche.')) ?></li>
            <li><?= h(t('Archiv: die gespeicherte Fassung bei web.archive.org oder archive.today, falls die Seite weg oder hinter einer Paywall ist.')) ?></li>
            <li><?= h(t('Als QR-Code anzeigen: Adresse aufs Handy holen, ohne zu tippen.')) ?></li>
            <li><?= h(t('Adresse kopieren.')) ?></li>
            <li><?= h(t('Mit Konto: Domain hochstufen, herabstufen oder blockieren.')) ?></li>
        </ul>
    </section>

    <section class="panel" id="operatoren">
        <h2><?= h(t('Suchoperatoren')) ?></h2>
        <p class="muted small"><?= h(t('Operatoren kommen einfach mit ins Suchfeld und werden an den Suchdienst weitergegeben. Am vollständigsten versteht sie Brave, die anderen Dienste beachten vor allem Anführungszeichen und site:. Klick auf ein Beispiel, um es auszuprobieren.')) ?></p>
        <div class="table-wrap">
        <table class="table table--help">
            <thead><tr><th><?= h(t('Operator')) ?></th><th><?= h(t('Wirkung')) ?></th><th><?= h(t('Beispiel')) ?></th></tr></thead>
            <tbody>
<?php foreach ($ops as $o): ?>
                <tr><td><code><?= h($o[0]) ?></code></td><td><?= h($o[1]) ?></td><td><?= $ex($o[2]) ?></td></tr>
<?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>

    <section class="panel" id="kombinieren">
        <h2><?= h(t('Kombinieren')) ?></h2>
        <p class="muted small"><?= h(t('Operatoren lassen sich frei mischen. AND, OR und NOT immer großschreiben. Klammern kennt Brave nicht.')) ?></p>
        <ul class="examples">
<?php foreach ($combos as $c): ?>
            <li><?= $ex($c[0]) ?><span class="muted"><?= h($c[1]) ?></span></li>
<?php endforeach; ?>
        </ul>
    </section>

    <section class="panel" id="dateitypen">
        <h2><?= h(t('Dateitypen')) ?></h2>
        <p class="muted small"><?= h(t('Auf der Ergebnisseite unter „Dateityp“ auswählen, dort lässt sich auch jede andere Endung eintippen. Der Filter bleibt beim Blättern und bei neuen Suchen erhalten, bis du „Alle Dateitypen“ wählst.')) ?></p>
        <p class="chips"><?php foreach (smFileTypes() as $k => $v): ?><a class="chip" href="<?= h(smUrl(['q' => $de ? 'anleitung' : 'manual', 'ft' => $k])) ?>"><?= h($v) ?></a> <?php endforeach; ?></p>
    </section>

    <section class="panel" id="bangs">
        <h2><?= h(t('Bangs')) ?></h2>
        <p class="muted small"><?= h(t('Mit einem Bang springst du direkt zur Suche einer anderen Seite. Das Kürzel darf vorne oder hinten stehen: „!w Berlin“ oder „Berlin !w“. Bangs verbrauchen kein Guthaben.')) ?>
<?php if ($me): ?> <a href="<?= h(smUrl(['a' => 'settings', 'tab' => 'bangs'])) ?>"><?= h(t('Eigene Bangs anlegen')) ?></a><?php endif; ?></p>
        <div class="table-wrap">
        <table class="table table--help">
            <thead><tr><th><?= h(t('Kürzel')) ?></th><th><?= h(t('Ziel')) ?></th><th></th></tr></thead>
            <tbody>
                <tr><td><code>!i</code> <code>!n</code> <code>!v</code></td><td><?= h(t('Bilder, News, Videos (Reiter wechseln)')) ?></td><td></td></tr>
<?php foreach ($bangs as $k => $b): ?>
                <tr><td><code>!<?= h($k) ?></code></td><td><?= h($b[0]) ?> <small class="muted help__host"><?= h((string)parse_url($b[1], PHP_URL_HOST)) ?></small></td><td><?= $b[2] ? '<span class="tag">' . h(t('eigenes')) . '</span>' : '' ?></td></tr>
<?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>

    <section class="panel" id="linsen">
        <h2><?= h(t('Linsen')) ?></h2>
        <p class="muted small"><?= h(t('Eine Linse schränkt die Suche auf ein Thema ein, etwa nur Foren oder nur Seiten zum Programmieren. Auswählen auf der Ergebnisseite unter „Linse“. Sie bleibt bei neuen Suchen aktiv, bis du „Ohne Linse“ wählst.')) ?>
<?php if ($me): ?> <a href="<?= h(smUrl(['a' => 'settings', 'tab' => 'lenses'])) ?>"><?= h(t('Eigene Linsen anlegen')) ?></a><?php endif; ?></p>
<?php if ($lenses): ?>
        <ul class="examples">
<?php foreach ($lenses as $k => $l): ?>
            <li><span><a href="<?= h(smUrl(['q' => $de ? 'erfahrungen' : 'review', 'l' => $k])) ?>"><strong><?= h($l[0]) ?></strong></a><?= $l[3] ? ' <span class="tag">' . h(t('eigene')) . '</span>' : '' ?></span><span class="muted"><code><?= h(smLensQuery($l)) ?></code></span></li>
<?php endforeach; ?>
        </ul>
<?php else: ?>
        <p class="muted"><?= h(t('Keine Linsen eingeschaltet.')) ?></p>
<?php endif; ?>
    </section>

    <section class="panel" id="domains">
        <h2><?= h(t('Domain-Listen')) ?></h2>
<?php if (!$me): ?>
        <p class="muted"><?= h(t('Eigene Listen zum Blockieren und Hochstufen von Seiten gibt es nach dem Anmelden.')) ?></p>
<?php else: ?>
        <p class="muted small"><?= h(t('Deine persönlichen Listen. Sie wirken nur auf deine Trefferliste. Schnell ändern geht über das Menü ⋮ neben jedem Treffer.')) ?>
            <a href="<?= h(smUrl(['a' => 'settings', 'tab' => 'domains'])) ?>"><?= h(t('Listen bearbeiten')) ?></a></p>
<?php foreach (['raise' => t('Hochstufen (Favoriten)'), 'lower' => t('Herabstufen'), 'block' => t('Blockieren')] as $k => $label): ?>
        <div class="domlist"><strong><?= h($label) ?></strong>
<?php if ($lists[$k]): ?>
            <p class="chips"><?php foreach ($lists[$k] as $d): ?><span class="chip chip--<?= $k ?>"><?= h($d) ?></span> <?php endforeach; ?></p>
<?php else: ?>
            <p class="muted small"><?= h(t('leer')) ?></p>
<?php endif; ?>
        </div>
<?php endforeach; ?>
<?php endif; ?>
    </section>

    <section class="panel" id="token">
        <h2><?= h(t('Login-Token und Browser')) ?></h2>
        <ul class="bullets">
            <li><?= h(t('Unter Einstellungen → Browser erzeugst du einen langen geheimen Login-Token (oder legst einen eigenen fest, mindestens 32 Zeichen).')) ?></li>
            <li><?= h(t('Wer die Suchmaschine mit ?token=… aufruft, ist sofort angemeldet und bleibt es 365 Tage. Der Token verschwindet dabei sofort aus der Adresse.')) ?></li>
            <li><?= h(t('Als Standardsuche: die Such-Adresse mit Token in den Browser eintragen, oder in Firefox auf der Seite Einstellungen → Browser die Suchmaschine über das Lupen-Symbol hinzufügen.')) ?></li>
            <li><?= h(t('Bangs und Sofort-Antworten funktionieren auch direkt aus der Adressleiste.')) ?></li>
            <li><?= h(t('Token weitergegeben? Neu erzeugen und „Alle anderen Geräte abmelden“.')) ?></li>
        </ul>
    </section>

    <section class="panel" id="tasten">
        <h2><?= h(t('Tastenkürzel')) ?></h2>
        <dl class="keys">
            <div><dt><kbd>/</kbd></dt><dd><?= h(t('Suchfeld fokussieren und Text markieren')) ?></dd></div>
            <div><dt><kbd>↓</kbd> <kbd>J</kbd></dt><dd><?= h(t('nächster Treffer (mit sichtbarem Rahmen)')) ?></dd></div>
            <div><dt><kbd>↑</kbd> <kbd>K</kbd></dt><dd><?= h(t('vorheriger Treffer')) ?></dd></div>
            <div><dt><kbd>Enter</kbd></dt><dd><?= h(t('markierten Treffer öffnen, mit Strg/⌘ im neuen Tab')) ?></dd></div>
            <div><dt><kbd>←</kbd> <kbd>→</kbd></dt><dd><?= h(t('vorige/nächste Ergebnisseite, in der Bildvorschau blättern (auch Wischen)')) ?></dd></div>
            <div><dt><kbd>F</kbd></dt><dd><?= h(t('Bildvorschau im Vollbild, nochmal F beendet es')) ?></dd></div>
            <div><dt><kbd>?</kbd></dt><dd><?= h(t('Übersicht aller Tastenkürzel (auf deutschen Tastaturen Shift + ß)')) ?></dd></div>
            <div><dt><kbd>Esc</kbd></dt><dd><?= h(t('Übersicht, Menüs, Bildvorschau und Vollbild schließen')) ?></dd></div>
            <div><dt><kbd>↓</kbd> <kbd>↑</kbd> <?= h(t('im Suchfeld')) ?></dt><dd><?= h(t('durch die Vorschläge aus den letzten Suchen blättern')) ?></dd></div>
        </dl>
        <p class="muted small"><?= h(t('Die Kürzel wirken nur, wenn gerade kein Eingabefeld aktiv ist.')) ?></p>
    </section>

    <section class="panel" id="privat">
        <h2><?= h(t('Datenschutz')) ?></h2>
        <ul class="bullets">
            <li><?= h(t('Keine Werbung, kein Tracking, keine Cookies außer Anmeldung, Sprache und Design.')) ?></li>
            <li><?= h(t('Der Server speichert keine Suchbegriffe. Der Verlauf für die Vorschläge bleibt nur in deinem Browser.')) ?></li>
            <li><?= h(t('Die Suchdienste sehen nur den Server, nicht dich. Gezählt wird nur die Menge der Anfragen, nicht ihr Inhalt.')) ?></li>
            <li><?= h(t('Passwörter aus dem Generator entstehen nur im Browser. QR-Codes entstehen auf diesem Server.')) ?></li>
        </ul>
    </section>
</main>
