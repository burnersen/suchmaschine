<?php defined('SM_VERSION') || exit; $q = null; ?>
<main class="home">
    <h1 class="home__brand"><?= smLogoHtml(true) ?><span class="home__name"><?= smSiteNameHtml() ?></span></h1>
    <?php if ($tab !== 'web'): ?><p class="home__tab"><?= h(smTabs()[$tab]) ?></p><?php endif; ?>
    <?php $big = true; require __DIR__ . '/_searchbox.php'; ?>
    <p class="home__hint"><?= t('Tipp: <kbd>/</kbd> springt ins Suchfeld') ?> · <?= t('<kbd>!w</kbd> Berlin sucht direkt bei Wikipedia') ?></p>
    <p class="home__help"><a href="<?= h(smUrl(['a' => 'help'])) ?>"><?= h(t('Suchoperatoren, Bangs und Linsen')) ?></a></p>
</main>
