<?php defined('SM_VERSION') || exit; $q = null; ?>
<main class="home">
    <h1 class="home__logo"><span class="logo__mark logo__mark--big" aria-hidden="true"></span><span><?= h(smSetting('site_name')) ?></span></h1>
    <?php if ($tab !== 'web'): ?><p class="home__tab"><?= h(smTabs()[$tab]) ?></p><?php endif; ?>
    <?php $big = true; require __DIR__ . '/_searchbox.php'; ?>
    <p class="home__hint"><?= t('Tipp: <kbd>/</kbd> springt ins Suchfeld') ?></p>
</main>
