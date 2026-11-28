<?php defined('SM_VERSION') || exit;
/** „Bilder dieser Seite“: alle Bilder einer fremden Seite im Raster, mit der Großansicht der Bildersuche (Vollbild, Diashow, Wischen) */
$target = $prefs['newtab'] ? ' target="_blank"' : '';
$rel = ' rel="noopener noreferrer"';
$host = smHost((string)parse_url($u, PHP_URL_HOST));
$base = ['a' => 'pics', 'u' => $u];
$page = 1;
$vi = isset($iview) && $iview >= 0 && isset($imgs[$iview]) ? (int)$iview : -1;
$gridUrl = smUrl($base);
$cur = $vi >= 0 ? $imgs[$vi] : null;
$curFull = $cur ? $cur['thumb'] : '';
?>
<main class="page pics">
    <div class="reader__bar">
        <a class="btn btn--small" href="<?= h($u) ?>"<?= $rel . $target ?>><?= h(t('Original öffnen')) ?> ↗</a>
        <a class="btn btn--small" href="<?= h(smUrl(['a' => 'read', 'u' => $u])) ?>"><?= h(t('Ohne Werbung lesen')) ?></a>
<?php if ($imgs): ?>
        <button type="button" class="btn btn--small btn--primary" data-pics-start hidden><?= h(t('Groß ansehen')) ?></button>
<?php endif; ?>
    </div>
    <h1 class="pics__title"><?= h(t('Bilder dieser Seite')) ?> <small class="muted"><?= h($host) ?><?= $imgs ? ' · ' . count($imgs) : '' ?></small></h1>
<?php if ($ptitle !== ''): ?>    <p class="muted small pics__sub"><?= h($ptitle) ?></p><?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php elseif (!$imgs): ?>
    <div class="alert"><strong><?= h(t('Auf dieser Seite wurden keine größeren Bilder gefunden.')) ?></strong>
        <?= h(t('Manche Seiten laden ihre Bilder erst mit JavaScript nach. Dort hilft das Lesezeichen „Bilder groß“, weil es direkt in deinem Browser läuft.')) ?>
        <a href="<?= h(smUrl(['a' => 'help'])) ?>#bildergross"><?= h(t('So richtest du es ein')) ?></a></div>
<?php else: ?>
    <p class="reader__legal small"><?= h(t('Zum privaten Ansehen. Inhalt und Rechte liegen bei %s. Nichts davon wird auf diesem Server gespeichert.', $host)) ?></p>
    <div class="grid" data-grid data-grid-measure>
<?php foreach ($imgs as $i => $im) require __DIR__ . '/_img.php'; ?>
    </div>
<?php require __DIR__ . '/_lightbox.php'; ?>
<?php endif; ?>
</main>
