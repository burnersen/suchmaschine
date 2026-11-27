<?php defined('SM_VERSION') || exit;
/** Aus dem Teilen-Menü des Geräts: Was soll mit der geteilten Adresse passieren? */
$host = smHost((string)parse_url($u, PHP_URL_HOST));
?>
<main class="page page--narrow share">
    <h1 class="page__title"><?= h(t('Geteilter Link')) ?></h1>
    <p class="share__url"><strong><?= h($host) ?></strong><br><small class="muted"><?= h(mb_strimwidth($u, 0, 140, '…')) ?></small></p>
    <div class="share__acts">
<?php if ($me): ?>
        <a class="btn btn--primary btn--wide" href="<?= h(smUrl(['a' => 'read', 'u' => $u])) ?>">📖 <?= h(t('Ohne Werbung lesen und drucken')) ?></a>
<?php else: ?>
        <a class="btn btn--primary btn--wide" href="<?= h(smUrl(['a' => 'login', 'next' => smUrl(['a' => 'read', 'u' => $u])])) ?>">📖 <?= h(t('Anmelden und ohne Werbung lesen')) ?></a>
<?php endif; ?>
        <a class="btn btn--wide" href="<?= h(smToolUrl('kurzlinks', ['url' => $u])) ?>">🔗 <?= h(t('Kurzlink machen')) ?></a>
        <a class="btn btn--wide" href="<?= h($u) ?>" rel="noopener noreferrer">↗ <?= h(t('Seite öffnen')) ?></a>
    </div>
</main>
