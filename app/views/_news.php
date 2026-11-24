<?php defined('SM_VERSION') || exit; ?>
        <article class="news">
            <a class="news__link" href="<?= h($n['url']) ?>"<?= $rel . $target ?>>
                <span class="news__body">
                    <span class="news__src"><?php $fav = $n['favicon']; $host = $n['host']; require __DIR__ . '/_fav.php'; ?><?= h($n['source'] ?: $n['host']) ?></span>
                    <span class="news__title"><?= smHl($n['title']) ?></span>
                    <span class="news__desc"><?= smHl($n['desc']) ?></span>
                    <span class="news__age"><?= $n['breaking'] ? '<b class="live">' . h(t('Eilmeldung')) . '</b> ' : '' ?><?= h($n['age']) ?></span>
                </span>
<?php if ($n['thumb']): ?><img class="news__img" src="<?= h($n['thumb']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
            </a>
        </article>
