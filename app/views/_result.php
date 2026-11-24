<?php defined('SM_VERSION') || exit; ?>
        <article class="res">
            <a class="res__head" href="<?= h($r['url']) ?>"<?= $rel . $target ?>>
                <?php $fav = $r['favicon']; $host = $r['host']; require __DIR__ . '/_fav.php'; ?>
                <span class="res__site"><span class="res__name"><?= h($r['site'] ?: $r['host']) ?></span>
                    <span class="res__url"><?= h(parse_url($r['url'], PHP_URL_SCHEME) . '://' . parse_url($r['url'], PHP_URL_HOST)) ?><?= $r['path'] !== '' ? ' ' . h($r['path']) : '' ?></span></span>
            </a>
            <div class="res__row">
                <div class="res__text">
                    <h3 class="res__title"><a href="<?= h($r['url']) ?>"<?= $rel . $target ?>><?= smHl($r['title']) ?></a></h3>
                    <p class="res__desc"><?php if ($r['age']): ?><span class="res__age"><?= h($r['age']) ?></span><?php endif; ?><?= smHl($r['desc']) ?></p>
<?php if ($r['links']): ?>
                    <ul class="res__links">
<?php foreach ($r['links'] as $l): ?><li><a href="<?= h($l['url']) ?>"<?= $rel . $target ?>><?= h($l['title']) ?></a></li><?php endforeach; ?>
                    </ul>
<?php endif; ?>
                </div>
<?php if ($r['thumb']): ?><img class="res__thumb" src="<?= h($r['thumb']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
            </div>
        </article>
