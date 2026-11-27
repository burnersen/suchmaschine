<?php defined('SM_VERSION') || exit;
/** Lesemodus: der Artikel ohne Werbung, dazu Drucken und PDF (mit Bildern nach Wahl) */
$target = $prefs['newtab'] ? ' target="_blank"' : '';
$rel = ' rel="noopener noreferrer"';
$imgOn = smImgProxyOn();
$fcOn = smProvActive('brightdata') && smBdUnlockerZone() !== '';
$host = smHost((string)parse_url($u, PHP_URL_HOST));
$data = null;
if ($r && $r['ok']) {
    $date = $r['date'] !== '' && ($ts = strtotime($r['date'])) ? smDate($ts) : '';
    $meta = implode(' · ', array_filter([$r['site'], $date, $r['byline']]));
    $blocks = [];
    foreach ($r['blocks'] as $b) {
        if ($b['t'] === 'img') {
            if (!$imgOn) continue;
            $b['src'] = smImgProxyUrl($b['src']);
            if ($b['src'] === '') continue;
        }
        $blocks[] = $b;
    }
    $data = ['title' => $r['title'], 'meta' => $meta, 'source' => t('Quelle: %s', $u),
             'note' => t('Abgerufen am %s · nur zum privaten Gebrauch · Rechte beim Urheber', smDate(time())), 'blocks' => $blocks];
}
?>
<main class="page reader">
    <div class="reader__bar">
        <a class="btn btn--small" href="<?= h($u) ?>"<?= $rel . $target ?>><?= h(t('Original öffnen')) ?> ↗</a>
<?php if ($data): ?>
        <button type="button" class="btn btn--small" data-print><?= h(t('Drucken')) ?></button>
        <button type="button" class="btn btn--small btn--primary" data-pdf-open hidden><?= h(t('Als PDF speichern')) ?></button>
<?php if (!empty($r['alt']) || !empty($r['was_recipe'])): ?>
        <span class="seg seg--small">
            <a href="<?= h(smUrl(['a' => 'read', 'u' => $u])) ?>"<?= $view === '' ? ' class="is-active"' : '' ?>><?= h(t('Nur das Rezept')) ?></a>
            <a href="<?= h(smUrl(['a' => 'read', 'u' => $u, 'v' => 'all'])) ?>"<?= $view === 'all' ? ' class="is-active"' : '' ?>><?= h(t('Ganzer Artikel')) ?></a>
        </span>
<?php endif; ?>
<?php endif; ?>
    </div>
    <p class="reader__legal small"><?= h(t('Lesemodus zum privaten Lesen. Inhalt und Rechte liegen bei %s. Bitte nicht weiterverbreiten. Nichts davon wird auf diesem Server gespeichert.', $host)) ?></p>

<?php if ($error !== ''): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php elseif (!$data): $why = $r['why'] ?? ''; ?>
    <div class="alert">
<?php if ($why === 'paywall'): ?>
        <strong><?= h(t('Dieser Artikel steht hinter einer Bezahlschranke.')) ?></strong> <?= h(t('Der Lesemodus umgeht keine Bezahlschranken. Bitte das Original öffnen.')) ?>
<?php elseif ($why === 'blocked'): ?>
        <strong><?= h(t('Die Seite lässt den Abruf durch den Server nicht zu.')) ?></strong>
<?php elseif ($why === 'fetch' || $why === 'status'): ?>
        <strong><?= h(t('Die Seite konnte nicht geladen werden.')) ?></strong>
<?php else: ?>
        <strong><?= h(t('Auf dieser Seite wurde kein Artikel gefunden.')) ?></strong> <?= h(t('Manche Seiten zeigen ihren Inhalt erst mit JavaScript an oder halten automatische Abrufe auf.')) ?>
<?php endif; ?>
<?php if ($why !== 'paywall' && $fcOn && empty($r['unlocked'])): ?>
        <form method="post" action="<?= h(smUrl(['a' => 'read', 'u' => $u] + ($view ? ['v' => $view] : []))) ?>" class="reader__fc">
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="unlock">
            <button type="submit" class="btn btn--small"><?= h(t('Über Bright Data versuchen')) ?></button>
            <small class="muted"><?= h(t('Kostet 1 Anfrage bei Bright Data. Der Web Unlocker lädt die Seite wie ein Browser.')) ?></small>
        </form>
<?php endif; ?>
    </div>
<?php else: ?>
<?php if (($r['why'] ?? '') === 'partial'): ?>
    <div class="alert"><?= h(t('Hinweis: Ein Teil dieses Artikels ist kostenpflichtig. Angezeigt wird nur, was frei zugänglich ist.')) ?></div>
<?php endif; ?>
    <article class="reader__doc" data-reader>
        <h1 class="reader__title"><?= h($data['title']) ?></h1>
        <p class="reader__meta"><?= h($data['meta']) ?></p>
<?php $open = '';
foreach ($data['blocks'] as $i => $b):
    $list = $b['t'] === 'li' ? 'ul' : ($b['t'] === 'oli' ? 'ol' : '');
    if ($open !== $list) { if ($open) echo "        </$open>\n"; if ($list) echo "        <$list>\n"; $open = $list; }
    switch ($b['t']):
        case 'h': ?>        <h2><?= h($b['x']) ?></h2>
<?php break; case 'h3': ?>        <h3><?= h($b['x']) ?></h3>
<?php break; case 'li': case 'oli': ?>            <li><?= h($b['x']) ?></li>
<?php break; case 'quote': ?>        <blockquote><?= h($b['x']) ?></blockquote>
<?php break; case 'pre': ?>        <pre><?= h($b['x']) ?></pre>
<?php break; case 'cap': ?>        <p class="reader__cap"><?= h($b['x']) ?></p>
<?php break; case 'img': ?>        <figure class="reader__fig"><img src="<?= h($b['src']) ?>" alt="<?= h($b['alt']) ?>" loading="<?= $i < 3 ? 'eager' : 'lazy' ?>" data-pdf-img="<?= $i ?>"></figure>
<?php break; default: ?>        <p<?= ($b['c'] ?? '') === 'facts' ? ' class="reader__facts"' : '' ?>><?= h($b['x']) ?></p>
<?php endswitch; endforeach; if ($open) echo "        </$open>\n"; ?>
        <p class="reader__src"><?= h($data['source']) ?><br><?= h($data['note']) ?></p>
    </article>
    <script type="application/json" id="reader-data"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

    <div class="overlay pdfpanel" data-pdfpanel hidden role="dialog" aria-modal="true" aria-label="<?= h(t('Als PDF speichern')) ?>">
        <div class="pdfpanel__box">
            <div class="pdfpanel__prev"><canvas data-pdf-canvas></canvas><p class="pdfpanel__pages"><button type="button" class="iconbtn" data-pdf-prev aria-label="<?= h(t('Vorige Seite')) ?>">‹</button> <span data-pdf-page></span> <button type="button" class="iconbtn" data-pdf-next aria-label="<?= h(t('Nächste Seite')) ?>">›</button></p></div>
            <div class="pdfpanel__opts form">
                <h2><?= h(t('Als PDF speichern')) ?></h2>
                <fieldset class="field"><legend><?= h(t('Schrift')) ?></legend><div class="seg">
                    <label><input type="radio" name="pf" value="helvetica" checked><span><?= h(t('Klar')) ?></span></label>
                    <label><input type="radio" name="pf" value="times"><span><?= h(t('Klassisch')) ?></span></label></div></fieldset>
                <label class="field"><span><?= h(t('Schriftgröße')) ?></span><input type="range" name="ps" min="70" max="160" step="5" value="100"></label>
                <label class="field"><span><?= h(t('Zeilenabstand')) ?></span><input type="range" name="pl" min="100" max="200" step="5" value="135"></label>
                <fieldset class="field"><legend><?= h(t('Spalten')) ?></legend><div class="seg">
                    <label><input type="radio" name="pc" value="1" checked><span>1</span></label>
                    <label><input type="radio" name="pc" value="2"><span>2</span></label></div></fieldset>
                <fieldset class="field"><legend><?= h(t('Ränder')) ?></legend><div class="seg">
                    <label><input type="radio" name="pm" value="narrow"><span><?= h(t('Schmal')) ?></span></label>
                    <label><input type="radio" name="pm" value="normal" checked><span><?= h(t('Normal')) ?></span></label>
                    <label><input type="radio" name="pm" value="wide"><span><?= h(t('Breit')) ?></span></label></div></fieldset>
                <fieldset class="field" data-pdf-imgs><legend><?= h(t('Bilder (antippen zum Weglassen)')) ?></legend><div class="pdfpanel__imgs"></div></fieldset>
                <p class="muted small"><?= h(t('Quelle und „nur zum privaten Gebrauch“ stehen auf jeder Seite. Umlaute gehen, sehr seltene Zeichen fallen weg, dafür lieber „Drucken“ nehmen.')) ?></p>
                <div class="form__actions">
                    <button type="button" class="btn btn--primary" data-pdf-save><?= h(t('PDF herunterladen')) ?></button>
                    <button type="button" class="btn" data-pdf-close><?= h(t('Schließen')) ?></button>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= h(smAsset('pdf.js')) ?>" defer></script>
<?php endif; ?>
</main>
