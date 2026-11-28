<?php defined('SM_VERSION') || exit; $rank = (int)($r['rank'] ?? 0); $age = $r['age'] !== '' ? $r['age'] : smAgeText((int)($r['ts'] ?? 0)); ?>
        <article class="res<?= $rank > 0 ? ' res--raised' : ($rank < 0 ? ' res--lowered' : '') ?>" data-res>
            <div class="res__top">
            <a class="res__head" href="<?= h($r['url']) ?>"<?= $rel . $target ?> tabindex="-1">
                <?php $fav = $r['favicon']; $host = $r['host']; require __DIR__ . '/_fav.php'; ?>
                <span class="res__site"><span class="res__name"><?= h($r['site'] ?: $r['host']) ?><?php if ($rank > 0): ?> <span class="res__badge" title="<?= h(t('Hochgestuft, steht in deiner Favoriten-Liste')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 4 2.4 5 5.6.6-4.2 3.8 1.2 5.6-5-2.9-5 2.9 1.2-5.6L4 9.6 9.6 9z"/></svg><span class="sr"><?= h(t('Hochgestuft')) ?></span></span><?php endif; ?></span>
                    <span class="res__url"><?= h(parse_url($r['url'], PHP_URL_HOST) ? parse_url($r['url'], PHP_URL_SCHEME) . '://' . parse_url($r['url'], PHP_URL_HOST) : $r['host']) ?><?= $r['path'] !== '' ? ' ' . h($r['path']) : '' ?></span></span>
            </a>
<?php if ($me && preg_match('~^https?://~i', $r['url'])): ?>
            <a class="iconbtn res__read" href="<?= h(smUrl(['a' => 'read', 'u' => $r['url']])) ?>" title="<?= h(t('Ohne Werbung lesen')) ?>" aria-label="<?= h(t('Ohne Werbung lesen')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 6.5C10.2 5.2 7.6 4.6 4 4.6v13c3.6 0 6.2.6 8 1.9 1.8-1.3 4.4-1.9 8-1.9v-13c-3.6 0-6.2.6-8 1.9zM12 6.5v13"/></svg></a>
<?php endif; ?>
<?php if ($r['host'] !== ''): require __DIR__ . '/_resmenu.php'; endif; ?>
            </div>
            <div class="res__row">
                <div class="res__text">
                    <h3 class="res__title"><a href="<?= h($r['url']) ?>"<?= $rel . $target ?> data-res-link><?= smHl($r['title']) ?></a></h3>
                    <p class="res__desc"><?php if ($age !== ''): ?><span class="res__age"><?= h($age) ?></span><?php endif; ?><?= smHl($r['desc']) ?></p>
<?php if ($r['links']): ?>
                    <ul class="res__links">
<?php foreach ($r['links'] as $l): ?><li><a href="<?= h($l['url']) ?>"<?= $rel . $target ?>><?= h($l['title']) ?></a></li><?php endforeach; ?>
                    </ul>
<?php endif; ?>
                </div>
<?php if ($r['thumb']): ?><img class="res__thumb" src="<?= h($r['thumb']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
            </div>
        </article>
