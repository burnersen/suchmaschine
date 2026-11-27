<?php defined('SM_VERSION') || exit;
/** Rezepte: Link einfügen, Lesezeichen, getestete Rezeptseiten, Tipps zu Portionen, Drucken und PDF */
$sites = smRecipeSites();
?>
<main class="page recipes">
    <h1 class="page__title">🍳 <?= h(t('Rezepte')) ?></h1>
    <p class="page__lead"><?= h(t('Rezept-Link einfügen und nur das Rezept bekommen: ohne Werbung, ohne Lebensgeschichte, mit Zutaten und Zubereitung. Portionen umrechnen, drucken oder als PDF speichern.')) ?></p>

    <section class="panel">
<?php if ($me): ?>
        <form method="get" action="<?= h(smBase()) ?>" class="recipes__form" data-busy>
            <input type="hidden" name="a" value="read">
            <label class="sr" for="rec-u"><?= h(t('Rezept-Link')) ?></label>
            <input type="url" id="rec-u" name="u" required placeholder="https://www.chefkoch.de/rezepte/…" autocomplete="off" autocapitalize="off" spellcheck="false" inputmode="url">
            <button type="submit" class="btn btn--primary"><?= h(t('Rezept holen')) ?></button>
        </form>
        <p class="muted small"><?= h(t('Geht auch direkt im Suchfeld oben: Link einfügen, Enter.')) ?></p>
<?php else: ?>
        <p><?= h(t('Rezepte holen geht mit Konto.')) ?> <a class="btn btn--primary btn--small" href="<?= h(smUrl(['a' => 'login', 'next' => smUrl(['a' => 'recipes'])])) ?>"><?= h(t('Anmelden')) ?></a></p>
<?php endif; ?>
    </section>

    <section class="panel">
        <h2><?= h(t('Direkt von der Rezeptseite: Lesezeichen „Lesen & Drucken“')) ?></h2>
        <p class="muted small"><?= h(t('Du bist gerade auf einer Rezeptseite (z. B. Chefkoch in Safari)? Lesezeichen antippen, und das Rezept öffnet sich hier. Es schickt die Seite mit, die dein Browser schon geladen hat, darum klappt es auch bei Seiten, die Server aussperren.')) ?></p>
<?php if ($me): ?>
        <p><a class="btn btn--primary" href="<?= h(smReadBookmarklet($me)) ?>" data-bookmarklet title="<?= h(t('In die Lesezeichenleiste ziehen')) ?>">📖 <?= h(t('Lesen & Drucken')) ?></a>
            <button type="button" class="btn" data-copy-text="<?= h(smReadBookmarklet($me)) ?>" data-done="<?= h(t('Kopiert')) ?>"><?= h(t('Lesezeichen-Code kopieren')) ?></button></p>
<?php endif; ?>
        <p class="small"><a href="<?= h(smUrl(['a' => 'help'])) ?>#bildergross"><?= h(t('Wie richte ich ein Lesezeichen ein? (Rechner, iPad, iPhone, Android)')) ?></a></p>
    </section>

    <section class="panel">
        <h2><?= h(t('Getestete Rezeptseiten')) ?></h2>
        <p class="muted small"><?= h(t('Stand %s. Fast alle Rezeptseiten liefern ihre Rezepte in einem festen Format für Suchmaschinen mit, darum klappen meist auch Seiten, die hier nicht stehen.', smDate($sites['tested']))) ?></p>
        <h3><?= h(t('Klappt mit Link und mit Lesezeichen')) ?></h3>
        <p class="recipes__sites"><?php foreach ($sites['direct'] as $d): ?><a class="chip" href="https://<?= h($d) ?>" rel="noopener noreferrer" target="_blank"><?= h($d) ?></a> <?php endforeach; ?></p>
        <h3><?= h(t('Klappt nur mit dem Lesezeichen')) ?></h3>
        <p class="muted small"><?= h(t('Diese Seiten sperren Abrufe von Servern. Mit dem Lesezeichen geht es trotzdem, weil dein Browser die Seite mitschickt.')) ?></p>
        <p class="recipes__sites"><?php foreach ($sites['bookmark'] as $d): ?><a class="chip" href="https://<?= h($d) ?>" rel="noopener noreferrer" target="_blank"><?= h($d) ?></a> <?php endforeach; ?></p>
    </section>

    <section class="panel">
        <h2><?= h(t('Gut zu wissen')) ?></h2>
        <ul class="bullets">
            <li><?= h(t('Portionen: Mit + und − rechnen sich alle Mengen um, auch im Ausdruck und im PDF.')) ?></li>
            <li><?= h(t('„Rezept drucken“ druckt nur das Rezept. „Als PDF speichern“ lässt dich Schrift, Größe und Bilder wählen.')) ?></li>
            <li><?= h(t('„Ganzer Artikel“ zeigt den Text der Seite mit allen Bildern, falls du doch mehr lesen willst.')) ?></li>
            <li><?= h(t('Nichts davon kostet Brave-Guthaben, dein Server holt die Seite selbst.')) ?></li>
        </ul>
    </section>
</main>
