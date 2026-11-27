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
<?php if (!empty($osToken)): ?>
<link rel="search" type="application/opensearchdescription+xml" title="<?= h($site) ?> (<?= h($me['name'] ?? '') ?>)" href="<?= h(smUrl(['a' => 'opensearch', 'k' => $osToken])) ?>">
<?php else: ?>
<link rel="search" type="application/opensearchdescription+xml" title="<?= h($site) ?>" href="<?= h(smUrl(['a' => 'opensearch'])) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= h(smAsset('app.css')) ?>">
<?php foreach ($styles ?? [] as $css): ?>
<link rel="stylesheet" href="<?= h(smAsset($css)) ?>">
<?php endforeach; ?>
<link rel="stylesheet" href="<?= h(smLookUrl(smLook($me ?? null))) ?>">
<script src="<?= h(smAsset('app.js')) ?>" defer></script>
<?php if (!empty($cfBoot)): ?>
<script type="application/json" id="cf-boot"><?= json_encode($cfBoot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
<?php foreach ($scripts ?? [] as $js): ?>
<script src="<?= h(smAsset($js)) ?>" defer></script>
<?php endforeach; ?>
</head>
<?php $tick = false; try { $tick = smHasUsersSafe() && smBangDue(); } catch (Throwable $e) {} ?>
<body class="<?= $home ? 'is-home' : ($bare ? 'is-bare' : 'is-page') ?>"<?= smPrefs($me ?? null)['newtab'] ? ' data-newtab' : '' ?><?= $tick ? ' data-tick="' . h(smUrl(['a' => 'bangtick'])) . '"' : '' ?>>
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
<?php if ($me || smSetting('public') === '1'): ?>
        <a class="iconbtn<?= ($_GET['a'] ?? '') === 'tools' ? ' is-active' : '' ?>" href="<?= h(smUrl(['a' => 'tools'])) ?>" title="<?= h(t('Werkzeuge')) ?>" aria-label="<?= h(t('Werkzeuge')) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle class="fill" cx="5" cy="5" r="1.9"/><circle class="fill" cx="12" cy="5" r="1.9"/><circle class="fill" cx="19" cy="5" r="1.9"/><circle class="fill" cx="5" cy="12" r="1.9"/><circle class="fill" cx="12" cy="12" r="1.9"/><circle class="fill" cx="19" cy="12" r="1.9"/><circle class="fill" cx="5" cy="19" r="1.9"/><circle class="fill" cx="12" cy="19" r="1.9"/><circle class="fill" cx="19" cy="19" r="1.9"/></svg>
        </a>
<?php endif; ?>
        <button type="button" class="iconbtn" data-theme-toggle data-path="<?= h(smBase()) ?>" aria-label="<?= h(t('Design umschalten')) ?>"
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
                <a href="<?= h(smUrl(['a' => 'tools'])) ?>"><?= h(t('Werkzeuge')) ?></a>
                <a href="<?= h(smUrl(['a' => 'recipes'])) ?>"><?= h(t('Rezepte')) ?></a>
                <a href="<?= h(smUrl(['a' => 'help'])) ?>"><?= h(t('Hilfe')) ?></a>
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
<?php if ($me && $me['is_admin'] && !$bare && empty($_COOKIE['sm_warn_off']) && ($warns = smProvWarnings())): ?>
<div class="warnbar" data-warnbar role="status">
    <span><strong><?= h(t('Guthaben wird knapp:')) ?></strong> <?= h(implode(' · ', $warns)) ?></span>
    <a href="<?= h(smUrl(['a' => 'admin', 'tab' => 'usage'])) ?>"><?= h(t('Verbrauch ansehen')) ?></a>
    <button type="button" class="iconbtn" data-warn-off aria-label="<?= h(t('Für heute ausblenden')) ?>" title="<?= h(t('Für heute ausblenden')) ?>"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
</div>
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
<?php $provs = smHasUsersSafe() && $me ? smProvOrder() : []; ?>
<?php if ($provs): ?>
    <span class="foot__sep">·</span>
    <span><?= h(t('Ergebnisse von %s', implode(', ', array_map(function ($i) { return smProviders()[$i]['name']; }, $provs)))) ?></span>
<?php endif; ?>
<?php if ($me && $me['is_admin'] && $provs): $f0 = smProvForecast($provs[0]); ?>
    <span class="foot__sep">·</span>
    <a href="<?= h(smUrl(['a' => 'admin', 'tab' => 'usage'])) ?>" title="<?= h(t('Verbrauch im laufenden Zeitraum')) ?>"><?= h(smProviders()[$provs[0]]['badge'] . ': ' . smNumF($f0['used']) . ' / ' . ($f0['limit'] ?: '∞')) ?><?= count($provs) > 1 ? ' …' : '' ?></a>
<?php endif; ?>
<?php if (!$bare): ?>
    <span class="foot__sep">·</span>
    <a href="<?= h(smUrl(['a' => 'help'])) ?>"><?= h(t('Hilfe')) ?></a>
<?php endif; ?>
    <span class="foot__sep">·</span>
    <a href="<?= h(smUrl(['lang' => smLang() === 'de' ? 'en' : 'de'])) ?>"><?= smLang() === 'de' ? 'English' : 'Deutsch' ?></a>
    <span class="foot__sep">·</span>
    <a href="https://github.com/<?= h(SM_REPO) ?>" rel="noopener noreferrer"><?= h(t('Selbst hosten')) ?></a>
<?php if (!$bare): ?>
    <span class="foot__sep">·</span>
    <button type="button" class="foot__keys" data-keys-open title="<?= h(t('Tastenkürzel (?)')) ?>"><kbd>?</kbd> <?= h(t('Tastenkürzel')) ?></button>
<?php endif; ?>
</footer>
<?php if (!$bare): ?>
<div class="overlay" data-keys hidden role="dialog" aria-modal="true" aria-labelledby="keys-title">
    <div class="overlay__box">
        <button type="button" class="iconbtn overlay__close" data-overlay-close aria-label="<?= h(t('Schließen')) ?>"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
        <h2 id="keys-title"><?= h(t('Tastenkürzel')) ?></h2>
        <dl class="keys">
            <div><dt><kbd>/</kbd></dt><dd><?= h(t('Suchfeld fokussieren und Text markieren')) ?></dd></div>
            <div><dt><kbd>↓</kbd> <kbd>J</kbd></dt><dd><?= h(t('nächster Treffer')) ?></dd></div>
            <div><dt><kbd>↑</kbd> <kbd>K</kbd></dt><dd><?= h(t('vorheriger Treffer')) ?></dd></div>
            <div><dt><kbd>Enter</kbd></dt><dd><?= h(t('markierten Treffer öffnen')) ?></dd></div>
            <div><dt><kbd>Strg</kbd>/<kbd>⌘</kbd> + <kbd>Enter</kbd></dt><dd><?= h(t('im neuen Tab öffnen')) ?></dd></div>
            <div><dt><kbd>←</kbd> <kbd>→</kbd></dt><dd><?= h(t('Seite zurück/weiter, in der Bildvorschau blättern')) ?></dd></div>
            <div><dt><kbd>F</kbd></dt><dd><?= h(t('Bildvorschau im Vollbild')) ?></dd></div>
            <div><dt><kbd>?</kbd></dt><dd><?= h(t('diese Übersicht')) ?></dd></div>
            <div><dt><kbd>Esc</kbd></dt><dd><?= h(t('Übersicht, Menüs, Bildvorschau und Vollbild schließen')) ?></dd></div>
        </dl>
        <p class="muted small"><?= h(t('Die Kürzel wirken nur, wenn gerade kein Eingabefeld aktiv ist.')) ?> <a href="<?= h(smUrl(['a' => 'help'])) ?>#tasten"><?= h(t('Mehr in der Hilfe')) ?></a></p>
    </div>
</div>
<div class="overlay" data-qr-dialog hidden role="dialog" aria-modal="true" aria-label="<?= h(t('QR-Code')) ?>">
    <div class="overlay__box overlay__box--qr">
        <button type="button" class="iconbtn overlay__close" data-overlay-close aria-label="<?= h(t('Schließen')) ?>"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
        <img alt="" width="260" height="260" data-qr-img>
        <p class="overlay__qrtext" data-qr-text></p>
    </div>
</div>
<?php endif; ?>
</body>
</html>
