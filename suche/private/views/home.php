<?php defined('PRIVATE_DIR') || exit; $q = ''; $big = true; ?>
<section class="hero">
    <h1 class="hero__logo"><span class="brand__mark brand__mark--big" aria-hidden="true"></span><?= e(cfg('site_name')) ?></h1>
    <?php require __DIR__ . '/_searchform.php'; ?>
    <p class="hero__hint">Werbefrei, ohne Tracking. Tipp: <kbd>/</kbd> springt ins Suchfeld.</p>
</section>
