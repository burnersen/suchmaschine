<?php defined('SM_VERSION') || exit;
/**
 * Endlos-Scrollen: Zugzone am Ende der Liste. Geladen wird erst, wenn man bewusst über das Ende hinaus weiterzieht
 * (oder auf den Knopf tippt), nicht schon beim Erreichen des letzten Treffers. So kostet das Lesen des letzten Eintrags nichts.
 * Ohne JavaScript ist es ein normaler Link.
 */
$moreUrl = '';
$moreLabel = '';
if (!empty($res['more']) && $page < 10) {
    $moreUrl = smUrl(['p' => $page + 1] + $base);
    $moreLabel = $tab === 'images' ? t('Weitere Bilder') : t('Weitere Treffer');
} elseif ($nt = smNextTier($tab, $flt, (string)$fresh, $res)) {
    $moreUrl = smUrl($nt[0] + $base);
    $moreLabel = $nt[1];
}
if ($moreUrl !== ''): ?>
    <div class="more" data-more="<?= h($moreUrl . (strpos($moreUrl, '?') === false ? '?' : '&') . 'part=1') ?>"<?= $tab === 'images' ? ' data-more-grid' : '' ?> data-error="<?= h(t('Weitere Treffer konnten nicht geladen werden.')) ?>">
        <a class="btn more__btn" href="<?= h($moreUrl) ?>"><?= h($moreLabel) ?></a>
        <div class="more__pull" aria-hidden="true">
            <span class="more__bar"><i></i></span>
            <span class="more__hint"><?= h(t('Unten angekommen? Kräftig weiterziehen für mehr')) ?></span>
            <span class="spinner"></span>
        </div>
    </div>
<?php elseif ($page > 1 || count($res['results']) > 5 || $flt['xf'] !== ''): ?>
    <p class="more__end"><?= h(t('Das waren alle Treffer.')) ?></p>
<?php endif;
