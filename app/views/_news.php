<?php defined('SM_VERSION') || exit; $r = $n; $nage = $n['age'] !== '' ? $n['age'] : smAgeText((int)($n['ts'] ?? 0)); ?>
        <article class="news" data-res>
            <a class="news__link" href="<?= h($n['url']) ?>"<?= $rel . $target ?> data-res-link>
                <span class="news__body">
                    <span class="news__src"><?php $fav = $n['favicon']; $host = $n['host']; require __DIR__ . '/_fav.php'; ?><?= h($n['source'] ?: $n['host']) ?><?php require __DIR__ . '/_srcbadge.php'; ?></span>
                    <span class="news__title"><?= smHl($n['title']) ?></span>
                    <span class="news__desc"><?= smHl($n['desc']) ?></span>
                    <span class="news__age"><?= !empty($n['breaking']) ? '<b class="live">' . h(t('Eilmeldung')) . '</b> ' : '' ?><?= h($nage) ?></span>
                </span>
<?php if ($n['thumb']): ?><img class="news__img" src="<?= h($n['thumb']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
            </a>
        </article>
