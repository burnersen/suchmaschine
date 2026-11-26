<?php defined('SM_VERSION') || exit;
/** Eine Bildkachel. $i = Nummer auf dieser Seite (für die Großansicht ohne JavaScript) */ ?>
        <a class="grid__item" href="<?= h(smUrl(['view' => $i] + $base)) ?>#lb" data-i="<?= $i ?>" data-page="<?= h($im['page']) ?>" data-img="<?= h($im['thumb']) ?>" data-w="<?= (int)($im['tw'] ?: $im['w']) ?>" data-h="<?= (int)($im['th'] ?: $im['h']) ?>"
           data-title="<?= h($im['title']) ?>" data-host="<?= h($im['host']) ?>" data-src="<?= h($im['src']) ?>" data-full="<?= h(smImgProxyUrl($im['src'])) ?>" data-size="<?= $im['w'] && $im['h'] ? (int)$im['w'] . ' × ' . (int)$im['h'] . ' px' : '' ?>" data-res>
            <img src="<?= h($im['thumb']) ?>" alt="<?= h($im['title']) ?>" loading="<?= $i < 12 && $page === 1 ? 'eager' : 'lazy' ?>" referrerpolicy="no-referrer">
            <span class="grid__cap"><span class="grid__host"><?= h($im['host']) ?></span><span class="grid__title"><?= h($im['title']) ?></span></span>
<?php if ($im['w'] && $im['h']): ?>            <span class="grid__px"><?= (int)$im['w'] ?> × <?= (int)$im['h'] ?></span><?php endif; ?>
        </a>
