<?php defined('SM_VERSION') || exit; $q = null; ?>
<main class="home">
    <h1 class="home__brand"><?= smLogoHtml(true) ?><span class="home__name"><?= smSiteNameHtml() ?></span></h1>
    <?php if ($tab !== 'web'): ?><p class="home__tab"><?= h(smTabs()[$tab]) ?></p><?php endif; ?>
    <?php $big = true; require __DIR__ . '/_searchbox.php'; ?>
    <p class="home__hint"><?= t('Tipp: <kbd>/</kbd> springt ins Suchfeld') ?> · <?= t('<kbd>!w</kbd> Berlin sucht direkt bei Wikipedia') ?> · <?= t('<kbd>100 usd in eur</kbd> rechnet sofort') ?></p>
<?php if (empty($me)): $fbMine = smFallbackFor(null); ?>
    <form class="home__fb" method="post" action="<?= h(smUrl(['a' => 'fb'])) ?>">
        <label for="home-fb"><?= h(t('Deine Suchen gehen zu')) ?></label>
        <select id="home-fb" name="fallback" data-autosubmit-select>
<?php foreach (smFallbackPresets() as $u => $n): ?>            <option value="<?= h($u) ?>"<?= $u === $fbMine ? ' selected' : '' ?>><?= h($n) ?></option>
<?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn--small" data-nojs><?= h(t('Merken')) ?></button>
        <small class="muted"><?= h(t('Ohne Anmeldung sucht diese Seite nicht selbst, sondern schickt dich direkt dorthin. Gemerkt wird das nur in deinem Browser.')) ?>
            <a href="<?= h(smUrl(['a' => 'help'])) ?>#bildergross"><?= h(t('Bilder dort groß ansehen')) ?></a></small>
    </form>
<?php endif; ?>
    <p class="home__help"><a href="<?= h(smUrl(['a' => 'help'])) ?>"><?= h(t('Hilfe: alles, was diese Suchmaschine kann')) ?></a> · <a href="<?= h(smUrl(['a' => 'tools'])) ?>"><?= h(t('Werkzeuge')) ?></a> · <a href="<?= h(smUrl(['a' => 'recipes'])) ?>"><?= h(t('Rezepte')) ?></a></p>
</main>
