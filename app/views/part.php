<?php defined('SM_VERSION') || exit;
/** Nachgeladene Treffer fürs Endlos-Scrollen (ohne Seitengerüst) */
require __DIR__ . '/_vars.php';
if ($error !== ''): ?>
<p class="alert alert--error"><?= h($error) ?></p>
<?php elseif ($res):
    if ($tab === 'news' && $flt['xf'] !== '' && $page === 1): ?>
<h2 class="news__sep"><?= h(t('Ältere News: %s', smFreshness()[$fresh])) ?></h2>
<?php endif;
    if ($tab === 'web') foreach ($res['results'] as $r) require __DIR__ . '/_result.php';
    elseif ($tab === 'news') foreach ($res['results'] as $n) require __DIR__ . '/_news.php';
    elseif ($tab === 'videos') foreach ($res['results'] as $v) require __DIR__ . '/_video.php';
    require __DIR__ . '/_more.php';
endif;
