<?php defined('SM_VERSION') || exit; ?>
        <article class="video">
            <a class="video__link" href="<?= h($v['url']) ?>"<?= $rel . $target ?>>
                <span class="card__thumb video__thumb"><?php if ($v['thumb']): ?><img src="<?= h($v['thumb']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
                    <span class="play" aria-hidden="true"></span><?php if ($v['duration']): ?><span class="dur"><?= h($v['duration']) ?></span><?php endif; ?></span>
                <span class="video__body">
                    <span class="video__title"><?= smHl($v['title']) ?></span>
                    <span class="video__meta"><?php $fav = $v['favicon']; $host = $v['host']; require __DIR__ . '/_fav.php'; ?><?= h(implode(' · ', array_filter([$v['host'], $v['creator'], $v['views'] ? t('%s Aufrufe', smNum($v['views'])) : '', $v['age']]))) ?></span>
                    <span class="video__desc"><?= smHl($v['desc']) ?></span>
                </span>
            </a>
        </article>
