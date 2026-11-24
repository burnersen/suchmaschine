<?php defined('SM_VERSION') || exit;
/** Endlos-Scrollen: Platzhalter am Ende der Liste. Mit JavaScript lädt er die nächsten Treffer von selbst, ohne ist er ein Link. */
if (!empty($res['more']) && $page < 10): ?>
    <div class="more" data-more="<?= h(smUrl(['p' => $page + 1, 'part' => '1'] + $base)) ?>" data-error="<?= h(t('Weitere Treffer konnten nicht geladen werden.')) ?>">
        <span class="spinner" aria-hidden="true"></span>
        <a class="btn" href="<?= h(smUrl(['p' => $page + 1] + $base)) ?>"><?= h(t('Mehr Ergebnisse')) ?></a>
    </div>
<?php elseif ($page > 1 || count($res['results']) > 5): ?>
    <p class="more__end"><?= h(t('Das waren alle Treffer.')) ?></p>
<?php endif;
