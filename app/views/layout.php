<?php defined('SM_VERSION') || exit;
/** Grundgerüst jeder Seite. $content kommt aus der jeweiligen Vorlage. */
$me = $me ?? null;
$home = !empty($home);
$bare = !empty($bare);
$site = smSiteName();
$logo = smLogoUrl();
$flash = smFlash();
?><!DOCTYPE html>
<html lang="<?= h(smLang()) ?>" data-theme="<?= h(smTheme()) ?>" data-accent="<?= h(smAccent()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#1f1f1f" media="(prefers-color-scheme: dark)">
<title><?= h($title ?? $site) ?></title>
<link rel="icon" href="<?= h($logo !== '' ? $logo : smAsset('icon.svg')) ?>"<?= $logo === '' ? ' type="image/svg+xml"' : '' ?>>
<link rel="apple-touch-icon" href="<?= h(smAppIconUrl(180)) ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?= h(smAppIconUrl(192)) ?>">
<meta name="apple-mobile-web-app-title" content="<?= h(trim(smHasUsersSafe() ? smSetting('site_name') : '') !== '' ? smSetting('site_name') : t('Suchmaschine')) ?>">
<link rel="manifest" href="<?= h(smUrl(['a' => 'manifest', 'v' => smAppIconCustom() ? smSetting('appicon_v') : SM_VERSION])) ?>">
<link rel="search" type="application/opensearchdescription+xml" title="<?= h($site) ?>" href="<?= h(smUrl(['a' => 'opensearch'])) ?>">
<link rel="stylesheet" href="<?= h(smAsset('app.css')) ?>">
<script src="<?= h(smAsset('app.js')) ?>" defer></script>
</head>
<body class="<?= $home ? 'is-home' : ($bare ? 'is-bare' : 'is-page') ?>">
<div class="progress" aria-hidden="true"></div>
<?php if (!$bare): ?>
<header class="top<?= isset($q) ? ' top--results' : '' ?>">
<?php if (!$home): ?>
    <a class="brand" href="<?= h(smBase()) ?>" title="<?= h(t('Zur Startseite')) ?>"><?= smLogoHtml() ?><span class="brand__text"><?= smSiteNameHtml() ?></span></a>
<?php endif; ?>
<?php if (isset($q)): ?>
    <?php $big = false; require __DIR__ . '/_searchbox.php'; ?>
<?php endif; ?>
    <nav class="top__right">
<?php if ($home): ?>
        <a class="top__link" href="<?= h(smUrl(['t' => 'images'])) ?>" data-tab-home="images"><?= h(t('Bilder')) ?></a>
        <a class="top__link" href="<?= h(smUrl(['t' => 'news'])) ?>" data-tab-home="news"><?= h(t('News')) ?></a>
<?php endif; ?>
        <button type="button" class="iconbtn" data-theme-toggle aria-label="<?= h(t('Design umschalten')) ?>"
                data-label-auto="<?= h(t('Design: automatisch')) ?>" data-label-light="<?= h(t('Design: hell')) ?>" data-label-dark="<?= h(t('Design: dunkel')) ?>">
            <svg class="ico ico--auto" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path class="fill" d="M12 3.5a8.5 8.5 0 0 1 0 17z"/></svg>
            <svg class="ico ico--light" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            <svg class="ico ico--dark" viewBox="0 0 24 24"><path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/></svg>
        </button>
<?php if ($me): ?>
        <details class="menu">
            <summary class="avatar" aria-label="<?= h(t('Konto')) ?>"><?= h(mb_strtoupper(mb_substr($me['name'], 0, 1))) ?></summary>
            <div class="menu__panel">
                <div class="menu__who"><span class="avatar avatar--big"><?= h(mb_strtoupper(mb_substr($me['name'], 0, 1))) ?></span>
                    <span><strong><?= h($me['name']) ?></strong><?php if ($me['is_admin']): ?><br><small><?= h(t('Administrator')) ?></small><?php endif; ?></span></div>
                <a href="<?= h(smUrl(['a' => 'settings'])) ?>"><?= h(t('Einstellungen')) ?></a>
<?php if ($me['is_admin']): ?>
                <a href="<?= h(smUrl(['a' => 'admin'])) ?>"><?= h(t('Verwaltung')) ?></a>
<?php endif; ?>
                <form method="post" action="<?= h(smUrl(['a' => 'logout'])) ?>"><?= smCsrfField($me) ?><button type="submit"><?= h(t('Abmelden')) ?></button></form>
            </div>
        </details>
<?php else: ?>
        <a class="btn btn--primary btn--small" href="<?= h(smUrl(['a' => 'login', 'next' => smSelf()])) ?>"><?= h(t('Anmelden')) ?></a>
<?php endif; ?>
    </nav>
</header>
<?php endif; ?>
<?php if ($flash): ?>
<div class="flash">
<?php foreach ($flash as $f): ?>
    <div class="alert alert--<?= h($f[0]) ?>" role="status"><?= h($f[1]) ?></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?= $content ?>
<footer class="foot">
    <span><?= h(t('Ohne Werbung, ohne Tracking')) ?></span>
    <span class="foot__sep">·</span>
    <span><?= t('Ergebnisse von <a href="https://search.brave.com" rel="noopener noreferrer">Brave Search</a>') ?></span>
<?php if ($me && $me['is_admin']): ?>
    <span class="foot__sep">·</span>
    <a href="<?= h(smUrl(['a' => 'admin', 'tab' => 'usage'])) ?>" title="<?= h(t('Anfragen an Brave in diesem Monat')) ?>"><?= h(t('%d von %d Suchen', smUsageMonth(), (int)smSetting('monthly_limit'))) ?></a>
<?php endif; ?>
    <span class="foot__sep">·</span>
    <a href="<?= h(smUrl(['lang' => smLang() === 'de' ? 'en' : 'de'])) ?>"><?= smLang() === 'de' ? 'English' : 'Deutsch' ?></a>
    <span class="foot__sep">·</span>
    <a href="https://github.com/<?= h(SM_REPO) ?>" rel="noopener noreferrer"><?= h(t('Selbst hosten')) ?></a>
</footer>
</body>
</html>
