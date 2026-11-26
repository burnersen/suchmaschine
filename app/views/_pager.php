<?php defined('SM_VERSION') || exit;
$hasNext = !empty($res['more']) && $page < 10;
$last = $hasNext ? $page + 1 : $page;
$first = max(1, $page - 4);
if ($page > 1 || $hasNext): ?>
    <nav class="pager" aria-label="<?= h(t('Seiten')) ?>">
<?php if ($page > 1): ?>
        <a class="pager__step" href="<?= h(smUrl(['p' => $page - 1 > 1 ? $page - 1 : null] + $base)) ?>" rel="prev" data-load>‹ <?= h(t('Zurück')) ?></a>
<?php endif; ?>
<?php for ($i = $first; $i <= $last; $i++): ?>
<?php if ($i === $page): ?>
        <span class="pager__n is-current" aria-current="page"><?= $i ?></span>
<?php else: ?>
        <a class="pager__n" href="<?= h(smUrl(['p' => $i > 1 ? $i : null] + $base)) ?>" data-load><?= $i ?></a>
<?php endif; ?>
<?php endfor; ?>
<?php if ($hasNext): ?>
        <a class="pager__step" href="<?= h(smUrl(['p' => $page + 1] + $base)) ?>" rel="next" data-load><?= h(t('Weiter')) ?> ›</a>
<?php endif; ?>
    </nav>
<?php endif;
// News am Ende des Zeitraums: weiter mit älteren
if (!$hasNext && $tab === 'news' && $flt['auto'] && $fresh !== '' && ($nx = smNextFresh($fresh)) !== ''): ?>
    <p class="more__end"><?= h(t('Das waren die News aus dem Zeitraum „%s“.', smFreshness()[$fresh])) ?>
        <a class="btn btn--small" href="<?= h(smUrl(['f' => $nx, 'xf' => $fresh, 'p' => null] + $base)) ?>" data-load><?= h(t('Ältere News: %s', smFreshness()[$nx])) ?> →</a></p>
<?php endif;
