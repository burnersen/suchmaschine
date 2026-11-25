<?php defined('SM_VERSION') || exit;
/** Quell-Badges an einem Treffer: [Brave] [Exa] … (nur wenn mehrere Suchdienste im Spiel sind) */
if (!empty($showSrc) && !empty($r['via'])): ?><span class="srcb"><?php foreach ($r['via'] as $sid): if (isset(smProviders()[$sid])): ?><span class="srcb__b srcb__b--<?= h($sid) ?>"><?= h(smProviders()[$sid]['badge']) ?></span><?php endif; endforeach; ?></span><?php endif;
