<?php defined('SM_VERSION') || exit;
/** Kurzinfo am Rand (nur Seite 1 bei „Alle“), so wie Brave sie liefert */ ?>
<?php if (!empty($res['infobox'])): $ib = $res['infobox']; ?>
    <aside class="kp" aria-label="<?= h(t('Kurzinfo')) ?>">
<?php if ($ib['thumb']): ?><img class="kp__img" src="<?= h($ib['thumb']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
        <h2 class="kp__title"><?= h($ib['title']) ?></h2>
<?php if ($ib['subtitle']): ?><p class="kp__sub"><?= h($ib['subtitle']) ?></p><?php endif; ?>
<?php if ($ib['desc']): ?><p class="kp__desc" data-clamp><?= h($ib['desc']) ?>
<?php if ($ib['url']): ?> <a href="<?= h($ib['url']) ?>"<?= $rel . $target ?>><?= h($ib['source'] ?: smHost((string)parse_url($ib['url'], PHP_URL_HOST))) ?></a><?php endif; ?>
<?php if (!empty($ib['license'])): ?> <small class="muted"><?= h($ib['license']) ?></small><?php endif; ?></p><?php endif; ?>
<?php if ($ib['attrs']): ?>
        <dl class="kp__attrs">
<?php foreach ($ib['attrs'] as $at): ?><div><dt><?= h($at[0]) ?>:</dt> <dd><?= h($at[1]) ?></dd></div><?php endforeach; ?>
        </dl>
<?php endif; ?>
<?php if ($ib['profiles'] || $ib['website']): ?>
        <div class="kp__links">
<?php if ($ib['website']): ?><a class="chip" href="<?= h($ib['website']) ?>"<?= $rel . $target ?>><?= h(t('Website')) ?></a><?php endif; ?>
<?php foreach ($ib['profiles'] as $pr): ?><a class="chip" href="<?= h($pr['url']) ?>"<?= $rel . $target ?>><?= h($pr['name']) ?></a><?php endforeach; ?>
        </div>
<?php endif; ?>
    </aside>
<?php endif; ?>
