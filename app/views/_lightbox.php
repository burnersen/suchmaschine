<?php defined('SM_VERSION') || exit;
/** Bildvorschau im Großformat (Bildersuche und „Bilder dieser Seite“). Braucht: $imgs, $vi (geöffnetes Bild oder -1), $cur, $curFull,
    $gridUrl (Adresse des Rasters), $base (Parameter für ?view=N), $rel, $target */ ?>
    <div class="lightbox" id="lb" data-lightbox<?= $vi >= 0 ? ' data-view="' . $vi . '"' : ' hidden' ?> role="dialog" aria-modal="true" aria-label="<?= h(t('Bildvorschau')) ?>" data-grid-url="<?= h($gridUrl) ?>">
        <div class="lightbox__box">
            <div class="lightbox__media">
                <img alt="" referrerpolicy="no-referrer" data-lb-img<?= $cur ? ' src="' . h($cur['thumb']) . '"' : '' ?>>
<?php if ($curFull !== ''): ?>                <img class="lightbox__hi" alt="" referrerpolicy="no-referrer" src="<?= h($curFull) ?>" data-lb-hi><?php endif; ?>
            </div>
            <p class="lightbox__cap"><span data-lb-cap><?= $cur ? h(implode(' · ', array_filter([$cur['host'], $cur['title']]))) : '' ?></span> <span class="lightbox__count" data-lb-count><?= $cur ? ($vi + 1) . ' / ' . count($imgs) : '' ?></span></p>
            <div class="lightbox__bar">
                <label class="lightbox__secs" data-lb-secs-wrap hidden title="<?= h(t('Sekunden pro Bild')) ?>"><span class="sr"><?= h(t('Sekunden pro Bild')) ?></span>
                    <select data-lb-secs><?php foreach ([2, 3, 5, 8, 10, 15, 30, 60] as $sec): ?><option value="<?= $sec ?>"><?= $sec ?> s</option><?php endforeach; ?></select></label>
                <button type="button" class="iconbtn lightbox__play" data-lb-play hidden aria-pressed="false" aria-label="<?= h(t('Diashow starten (Taste S)')) ?>" title="<?= h(t('Diashow starten (Taste S)')) ?>"
                        data-label-on="<?= h(t('Diashow starten (Taste S)')) ?>" data-label-off="<?= h(t('Diashow anhalten (Taste S)')) ?>"><svg viewBox="0 0 24 24" class="ico-play"><path class="fill" d="M8 5.5v13l11-6.5z"/></svg><svg viewBox="0 0 24 24" class="ico-pause"><path class="fill" d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z"/></svg></button>
                <button type="button" class="iconbtn lightbox__full" data-lb-full aria-label="<?= h(t('Vollbild (Taste F)')) ?>" title="<?= h(t('Vollbild (Taste F)')) ?>"
                        data-label-on="<?= h(t('Vollbild (Taste F)')) ?>" data-label-off="<?= h(t('Vollbild beenden (F oder Esc)')) ?>"><svg viewBox="0 0 24 24" class="ico-enter"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg><svg viewBox="0 0 24 24" class="ico-exit"><path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5"/></svg></button>
                <a class="iconbtn lightbox__close" href="<?= h($gridUrl) ?>" data-lb-close aria-label="<?= h(t('Schließen')) ?>" title="<?= h(t('Schließen')) ?>"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></a>
            </div>
            <div class="lightbox__info">
                <span class="lightbox__host" data-lb-host><?= $cur ? h($cur['host']) : '' ?></span>
                <h2 class="lightbox__title" data-lb-title><?= $cur ? h($cur['title']) : '' ?></h2>
                <span class="muted" data-lb-size><?= $cur && $cur['w'] && $cur['h'] ? (int)$cur['w'] . ' × ' . (int)$cur['h'] . ' px' : '' ?></span>
                <div class="lightbox__actions">
                    <a class="btn btn--primary" data-lb-page<?= $cur ? ' href="' . h($cur['page']) . '"' : '' ?><?= $rel . $target ?>><?= h(t('Seite besuchen')) ?></a>
                    <a class="btn" data-lb-src<?= $rel ?> target="_blank"<?= $cur && $cur['src'] !== '' ? ' href="' . h($cur['src']) . '"' : ($cur ? ' hidden' : '') ?>><?= h(t('Bild in Originalgröße')) ?></a>
                </div>
            </div>
            <a class="iconbtn lightbox__nav lightbox__nav--prev" data-lb-prev aria-label="<?= h(t('Vorheriges Bild')) ?>"<?= $vi > 0 ? ' href="' . h(smUrl(['view' => $vi - 1] + $base)) . '#lb"' : ($vi === 0 ? ' hidden' : '') ?>><svg viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></svg></a>
            <a class="iconbtn lightbox__nav lightbox__nav--next" data-lb-next aria-label="<?= h(t('Nächstes Bild')) ?>"<?= $vi >= 0 && $vi < count($imgs) - 1 ? ' href="' . h(smUrl(['view' => $vi + 1] + $base)) . '#lb"' : ($vi >= 0 ? ' hidden' : '') ?>><svg viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></a>
        </div>
    </div>
