<?php defined('SM_VERSION') || exit;
/** „Bilder groß“: Übersicht mit Sammlungen, die Bilder einer Seite oder eine Sammlung. Immer mit der Großansicht der Bildersuche. */
$target = $prefs['newtab'] ? ' target="_blank"' : '';
$rel = ' rel="noopener noreferrer"';
$host = $u !== '' ? smHost((string)parse_url($u, PHP_URL_HOST)) : '';
$base = $set ? ['a' => 'pics', 'set' => $set['id']] : ['a' => 'pics', 'u' => $u];
$page = 1;
$vi = isset($iview) && $iview >= 0 && isset($imgs[$iview]) ? (int)$iview : -1;
$gridUrl = smUrl($base);
$cur = $vi >= 0 ? $imgs[$vi] : null;
$curFull = $cur ? smImgProxyUrl($cur['src']) : '';
$overview = !$set && $u === '';
?>
<main class="page pics">
<?php if ($overview): ?>
    <h1 class="pics__title"><?= h(t('Bilder groß')) ?></h1>
    <p class="muted"><?= h(t('Bilder randlos im Vollbild und als Diashow, auch von anderen Seiten und Suchmaschinen.')) ?></p>
<?php if ($error !== ''): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php else: ?>
    <form class="form pics__form" method="get" action="<?= h(smBase()) ?>" data-busy>
        <input type="hidden" name="a" value="pics">
        <label class="field"><span><?= h(t('Adresse einer Seite mit Bildern')) ?></span>
            <input type="url" name="u" inputmode="url" autocapitalize="off" autocomplete="off" spellcheck="false" placeholder="https://…" required></label>
        <button type="submit" class="btn btn--primary"><?= h(t('Bilder holen')) ?></button>
    </form>
    <p class="muted small"><?= h(t('Klappt bei Galerien, Blogs, Wikipedia und den meisten normalen Seiten. Bildersuchen von Google, Bing oder DuckDuckGo geben ihre Treffer nur an Browser heraus: Dort das Lesezeichen „Bilder groß“ antippen und dann auf „An meine App“ tippen. Die Bilder erscheinen hier als Sammlung.')) ?>
        <a href="<?= h(smUrl(['a' => 'help'])) ?>#bildergross"><?= h(t('So richtest du es ein')) ?></a></p>

    <h2 class="pics__h2"><?= h(t('Sammlungen')) ?></h2>
<?php if (!$sets): ?>
    <p class="muted"><?= h(t('Noch keine. Eine Sammlung entsteht, wenn du mit dem Lesezeichen Bilder schickst oder bei „Bilder dieser Seite“ auf „Als Sammlung merken“ tippst.')) ?></p>
<?php else: ?>
    <ul class="pics__sets">
<?php foreach ($sets as $s): ?>
        <li>
            <a class="pics__set" href="<?= h(smUrl(['a' => 'pics', 'set' => $s['id']])) ?>" data-load>
                <strong><?= h($s['title']) ?></strong>
                <small class="muted"><?= h(t('%d Bilder', (int)$s['n'])) ?> · <?= h(smDate((int)$s['created'])) ?><?= $s['src'] !== '' ? ' · ' . h(smHost((string)parse_url($s['src'], PHP_URL_HOST))) : '' ?></small>
            </a>
            <a class="btn btn--small btn--primary" href="<?= h(smUrl(['a' => 'pics', 'set' => $s['id'], 'show' => '1'])) ?>"><?= h(t('Diashow')) ?></a>
            <form method="post" action="<?= h(smUrl(['a' => 'pics'])) ?>" data-confirm="<?= h(t('Diese Sammlung löschen?')) ?>"><?= smCsrfField($me) ?><input type="hidden" name="set" value="<?= h($s['id']) ?>">
                <button type="submit" name="do" value="del" class="btn btn--small" aria-label="<?= h(t('Sammlung löschen')) ?>"><?= h(t('Löschen')) ?></button></form>
        </li>
<?php endforeach; ?>
    </ul>
    <p class="muted small"><?= h(t('Gespeichert werden nur die Adressen der Bilder, nicht die Bilder selbst. Verschwindet ein Bild im Netz, fehlt es auch hier. Höchstens %d Sammlungen, die ältesten fallen weg.', SM_PICSETS_MAX)) ?></p>
<?php endif; ?>
<?php endif; ?>
<?php else: ?>
    <div class="reader__bar">
        <a class="btn btn--small" href="<?= h(smUrl(['a' => 'pics'])) ?>" data-load>← <?= h(t('Sammlungen')) ?></a>
<?php if ($imgs): ?>
        <button type="button" class="btn btn--small btn--primary" data-pics-show<?= ($_GET['show'] ?? '') === '1' ? ' data-auto' : '' ?> hidden>▶ <?= h(t('Diashow starten')) ?></button>
        <button type="button" class="btn btn--small" data-pics-start hidden><?= h(t('Groß ansehen')) ?></button>
<?php endif; ?>
<?php if ($set): ?>
        <a class="btn btn--small<?= $edit ? ' is-active' : '' ?>" href="<?= h(smUrl(['a' => 'pics', 'set' => $set['id'], 'edit' => $edit ? null : '1'])) ?>"><?= h($edit ? t('Fertig') : t('Aussortieren')) ?></a>
<?php else: ?>
        <a class="btn btn--small" href="<?= h($u) ?>"<?= $rel . $target ?>><?= h(t('Original öffnen')) ?> ↗</a>
        <a class="btn btn--small" href="<?= h(smUrl(['a' => 'read', 'u' => $u])) ?>"><?= h(t('Ohne Werbung lesen')) ?></a>
<?php if ($imgs): ?>
        <form method="post" action="<?= h(smUrl(['a' => 'pics'])) ?>"><?= smCsrfField($me) ?><input type="hidden" name="u" value="<?= h($u) ?>">
            <button type="submit" name="do" value="save" class="btn btn--small"><?= h(t('Als Sammlung merken')) ?></button></form>
<?php endif; ?>
<?php endif; ?>
    </div>
<?php if ($set): ?>
    <h1 class="pics__title"><?= h($set['title']) ?> <small class="muted"><?= h(t('%d Bilder', count($imgs))) ?></small></h1>
<?php if ($edit): ?>
    <form class="form pics__form" method="post" action="<?= h(smUrl(['a' => 'pics'])) ?>"><?= smCsrfField($me) ?><input type="hidden" name="set" value="<?= h($set['id']) ?>">
        <label class="field"><span><?= h(t('Name der Sammlung')) ?></span><input type="text" name="title" maxlength="120" value="<?= h($set['title']) ?>"></label>
        <button type="submit" name="do" value="rename" class="btn"><?= h(t('Umbenennen')) ?></button>
    </form>
    <p class="muted small"><?= h(t('Zum Aussortieren auf das × an einem Bild tippen. Es verschwindet nur aus dieser Sammlung.')) ?></p>
<?php endif; ?>
<?php else: ?>
    <h1 class="pics__title"><?= h(t('Bilder dieser Seite')) ?> <small class="muted"><?= h($host) ?><?= $imgs ? ' · ' . count($imgs) : '' ?></small></h1>
<?php if ($ptitle !== ''): ?>    <p class="muted small pics__sub"><?= h($ptitle) ?></p><?php endif; ?>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php elseif (!$imgs): ?>
    <div class="alert"><strong><?= h(t('Auf dieser Seite wurden keine größeren Bilder gefunden.')) ?></strong>
        <?= h(t('Manche Seiten laden ihre Bilder erst mit JavaScript nach. Dort hilft das Lesezeichen „Bilder groß“, weil es direkt in deinem Browser läuft.')) ?>
        <a href="<?= h(smUrl(['a' => 'help'])) ?>#bildergross"><?= h(t('So richtest du es ein')) ?></a></div>
<?php else: ?>
<?php if (!$set): ?>    <p class="reader__legal small"><?= h(t('Zum privaten Ansehen. Inhalt und Rechte liegen bei %s. Nichts davon wird auf diesem Server gespeichert.', $host)) ?></p><?php endif; ?>
<?php if ($set && $edit): ?>
    <div class="grid pics__edit">
<?php foreach ($imgs as $i => $im): ?>
        <form class="grid__item" method="post" action="<?= h(smUrl(['a' => 'pics'])) ?>"><?= smCsrfField($me) ?><input type="hidden" name="set" value="<?= h($set['id']) ?>"><input type="hidden" name="i" value="<?= $i ?>">
            <img src="<?= h($im['thumb']) ?>" alt="<?= h($im['title']) ?>" loading="lazy" referrerpolicy="no-referrer">
            <button type="submit" name="do" value="drop" class="pics__drop" aria-label="<?= h(t('Bild aus der Sammlung nehmen')) ?>" title="<?= h(t('Bild aus der Sammlung nehmen')) ?>">×</button>
        </form>
<?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="grid" data-grid data-grid-measure>
<?php foreach ($imgs as $i => $im) require __DIR__ . '/_img.php'; ?>
    </div>
<?php require __DIR__ . '/_lightbox.php'; ?>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
</main>
