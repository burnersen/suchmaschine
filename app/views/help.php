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
    <nav class="toc">
        <a href="#operatoren"><?= h(t('Suchoperatoren')) ?></a>
        <a href="#kombinieren"><?= h(t('Kombinieren')) ?></a>
        <a href="#dateitypen"><?= h(t('Dateitypen')) ?></a>
        <a href="#bangs"><?= h(t('Bangs')) ?></a>
        <a href="#linsen"><?= h(t('Linsen')) ?></a>
        <a href="#domains"><?= h(t('Domain-Listen')) ?></a>
        <a href="#tasten"><?= h(t('Tastenkürzel')) ?></a>
    </nav>

    <section class="panel" id="operatoren">
        <h2><?= h(t('Suchoperatoren')) ?></h2>
        <p class="muted small"><?= h(t('Operatoren kommen einfach mit ins Suchfeld. Sie werden direkt an Brave weitergegeben. Brave nennt sie noch experimentell, nicht jede Kombination liefert Treffer. Klick auf ein Beispiel, um es auszuprobieren.')) ?></p>
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
        <p class="muted small"><?= h(t('Mit einem Bang springst du direkt zur Suche einer anderen Seite. Das Kürzel darf vorne oder hinten stehen: „!w Berlin“ oder „Berlin !w“. Bangs verbrauchen keine Suche bei Brave.')) ?>
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

    <section class="panel" id="tasten">
        <h2><?= h(t('Tastenkürzel')) ?></h2>
        <dl class="keys">
            <div><dt><kbd>/</kbd></dt><dd><?= h(t('ins Suchfeld springen')) ?></dd></div>
            <div><dt><kbd>↓</kbd> <kbd>↑</kbd></dt><dd><?= h(t('durch die Vorschläge aus den letzten Suchen blättern')) ?></dd></div>
            <div><dt><kbd>←</kbd> <kbd>→</kbd></dt><dd><?= h(t('in der Bildvorschau blättern (auch Wischen)')) ?></dd></div>
            <div><dt><kbd>F</kbd></dt><dd><?= h(t('Bildvorschau im Vollbild, nochmal F beendet es')) ?></dd></div>
            <div><dt><kbd>Esc</kbd></dt><dd><?= h(t('Vollbild beenden, dann die Vorschau schließen')) ?></dd></div>
        </dl>
    </section>
</main>
