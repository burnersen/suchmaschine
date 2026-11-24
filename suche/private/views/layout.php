<?php defined('PRIVATE_DIR') || exit;
$theme = current_theme();
$user  = $user ?? null;
$home  = $home ?? false;
$messages = flash();
?><!DOCTYPE html>
<html lang="de" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <meta name="color-scheme" content="light dark">
    <title><?= e($title ?? cfg('site_name')) ?></title>
    <link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
    <link rel="search" type="application/opensearchdescription+xml" title="<?= e(cfg('site_name')) ?>" href="<?= e(url(['a' => 'opensearch'])) ?>">
    <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
    <script src="<?= e(asset('app.js')) ?>" defer></script>
</head>
<body class="<?= $home ? 'is-home' : '' ?>">
<div class="progress" aria-hidden="true"></div>

<header class="topbar">
    <?php if (!$home): ?>
        <a class="brand" href="<?= e(base_path()) ?>" aria-label="Startseite">
            <span class="brand__mark" aria-hidden="true"></span><span class="brand__name"><?= e(cfg('site_name')) ?></span>
        </a>
        <?php if ($user && isset($q)): ?>
            <?php require __DIR__ . '/_searchform.php'; ?>
        <?php endif; ?>
    <?php endif; ?>
    <nav class="menu">
        <button type="button" class="icon-btn" data-theme-toggle title="Design umschalten" aria-label="Design umschalten">
            <svg class="ico ico--auto" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 3.5a8.5 8.5 0 0 1 0 17z" class="fill"/></svg>
            <svg class="ico ico--light" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            <svg class="ico ico--dark" viewBox="0 0 24 24"><path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/></svg>
        </button>
        <?php if ($user): ?>
            <details class="usermenu">
                <summary class="icon-btn" title="<?= e($user['name']) ?>" aria-label="Benutzermenü">
                    <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
                </summary>
                <div class="usermenu__panel">
                    <div class="usermenu__who"><?= e($user['name']) ?><?= !empty($user['admin']) ? ' <span class="badge">Admin</span>' : '' ?></div>
                    <a href="<?= e(url(['a' => 'account'])) ?>">Mein Konto</a>
                    <?php if (!empty($user['admin'])): ?>
                        <a href="<?= e(url(['a' => 'admin'])) ?>">Verwaltung</a>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url(['a' => 'logout'])) ?>">
                        <?= csrf_field() ?>
                        <button type="submit">Ausloggen</button>
                    </form>
                </div>
            </details>
        <?php endif; ?>
    </nav>
</header>

<main class="main">
    <?php foreach ($messages as $m): ?>
        <div class="alert alert--<?= e($m['type']) ?>" role="status"><?= e($m['msg']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<footer class="footer">
    <span>Ergebnisse via <a href="https://brave.com/search/api/" rel="noopener noreferrer">Brave Search</a></span>
    <?php if ($user): ?>
        <span>·</span>
        <span title="Echte API-Calls in diesem Monat (alle Nutzer)">API-Calls <?= e(date('m/Y')) ?>: <?= usage_this_month() ?> / <?= (int) cfg('monthly_limit') ?></span>
    <?php endif; ?>
</footer>
</body>
</html>
