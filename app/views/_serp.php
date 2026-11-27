<?php defined('SM_VERSION') || exit;
/** Die Trefferliste */
require __DIR__ . '/_vars.php';
?>
<main class="serp serp--<?= h($tab) ?>">
<?php if (!empty($instant)): require __DIR__ . '/_instant.php'; ?>
<?php elseif ($error !== ''): $fb = smFallbackTabUrl($q, $tab, $me); ?>
    <div class="alert alert--error serp__error">
        <strong><?= h(t('Die Suche hat nicht geklappt.')) ?></strong> <?= h($error) ?>
        <p><a class="btn btn--small" href="<?= h(smSelf()) ?>"><?= h(t('Noch einmal versuchen')) ?></a>
<?php if ($fb !== ''): ?> <a class="btn btn--small btn--primary" href="<?= h($fb) ?>"<?= $rel . $target ?>><?= h(t('Bei %s suchen', smFallbackName($me))) ?></a><?php endif; ?></p>
    </div>
<?php elseif ($res): ?>
<?php if (!empty($widened)): ?>
    <p class="serp__note"><?= h(t('Nichts aus dem Zeitraum „%s“, darum: %s.', smTierLabel($widened), smTierLabel($fresh))) ?></p>
<?php endif; ?>
<?php if (!empty($res['dl_hidden'])): ?>
    <p class="serp__note"><?= h(t('%d weitere Treffer von schon gezeigten Domains ausgeblendet (max. 3 pro Domain).', $res['dl_hidden'])) ?>
        <a href="<?= h(smUrl(['dl' => '0'] + $base)) ?>" data-load><?= h(t('Alle zeigen')) ?></a></p>
<?php endif; ?>
<?php if (!empty($res['img_hidden']) && ($flt['io'] || $flt['is'])): ?>
    <p class="serp__note"><?= h(t('%d Bilder passen nicht zum Filter.', $res['img_hidden'])) ?> <a href="<?= h(smUrl(['io' => null, 'is' => null] + $base)) ?>" data-load><?= h(t('Filter aufheben')) ?></a></p>
<?php endif; ?>
<?php if (!empty($res['hidden'])): ?>
    <p class="serp__note"><?= h($res['hidden'] === 1 ? t('1 Treffer ausgeblendet (deine Blockliste).') : t('%d Treffer ausgeblendet (deine Blockliste).', $res['hidden'])) ?>
        <a href="<?= h(smUrl(['a' => 'settings', 'tab' => 'domains'])) ?>"><?= h(t('Listen bearbeiten')) ?></a></p>
<?php endif; ?>
<?php if (!empty($didyoumean)): ?>
    <p class="serp__altered"><?= t('Meintest du: <a href="%s" data-load><strong>%s</strong></a>', h(smUrl(['q' => $didyoumean, 'exact' => null] + $base)), h($didyoumean)) ?></p>
<?php endif; ?>
<?php if (!empty($answer)): ?>
    <section class="answer" data-answer="<?= h($answer['url']) ?>"<?= $answer['auto'] ? ' data-answer-auto' : '' ?> data-error="<?= h(t('Brave konnte gerade keine Antwort liefern.')) ?>">
        <h2 class="answer__title"><?= h(t('KI-Antwort')) ?> <span class="answer__src">Brave</span></h2>
        <div class="answer__text" data-answer-text aria-live="polite"></div>
        <p class="answer__go"><button type="button" class="btn btn--small" data-answer-go><?= h(t('Antwort holen')) ?></button>
            <span class="spinner" hidden></span> <small class="muted"><?= h(t('Dauert ein paar Sekunden, kostet etwas Brave-Guthaben.')) ?></small></p>
        <p class="answer__note muted small" hidden><?= h(t('KI-Antworten können falsch sein. Wichtiges bitte in den Quellen prüfen.')) ?></p>
    </section>
<?php endif; ?>
<?php if (!empty($res['altered']) && !$exact): ?>
    <p class="serp__altered"><?= t('Ergebnisse für <strong>%s</strong>', h($res['altered'])) ?><br>
        <small><?= t('Stattdessen suchen nach <a href="%s">%s</a>', h(smUrl(['exact' => '1'] + $base)), h($q)) ?></small></p>
<?php endif; ?>

<?php if (!$res['results'] && empty($res['news']) && empty($res['videos'])): ?>
    <div class="serp__empty">
        <p><?= t('Zu <strong>%s</strong> wurde nichts gefunden.', h($q)) ?></p>
        <ul><li><?= h(t('Stimmt die Schreibweise?')) ?></li><li><?= h(t('Andere oder allgemeinere Begriffe probieren.')) ?></li>
<?php if ($fresh): ?><li><a href="<?= h(smUrl(['f' => $flt['casc'] ? 'all' : null, 'xf' => null] + $base)) ?>"><?= h(t('Ohne Zeitraum suchen')) ?></a></li><?php endif; ?>
<?php if ($ft): ?><li><a href="<?= h(smUrl(['ft' => null] + $base)) ?>"><?= h(t('Ohne Dateityp suchen')) ?></a></li><?php endif; ?>
<?php if ($lens): ?><li><a href="<?= h(smUrl(['l' => null] + $base)) ?>"><?= h(t('Ohne Linse suchen')) ?></a></li><?php endif; ?>
<?php if ($flt['io'] || $flt['is']): ?><li><a href="<?= h(smUrl(['io' => null, 'is' => null] + $base)) ?>"><?= h(t('Ohne Bildfilter suchen')) ?></a></li><?php endif; ?>
<?php if ($flt['fd'] || $flt['td']): ?><li><a href="<?= h(smUrl(['fd' => null, 'td' => null] + $base)) ?>"><?= h(t('Ohne eigenen Zeitraum suchen')) ?></a></li><?php endif; ?>
<?php if ($flt['sl'] || $flt['cr'] || $flt['vb'] || $flt['vd']): ?><li><a href="<?= h(smUrl(['sl' => null, 'cr' => null, 'vb' => null, 'vd' => null] + $base)) ?>"><?= h(t('Ohne Sprache, Region, Wortgenau und Länge suchen')) ?></a></li><?php endif; ?>
<li><a href="<?= h(smFallbackTabUrl($q, $tab, $me)) ?>"<?= $rel . $target ?>><?= h(t('Bei %s suchen', smFallbackName($me))) ?></a></li></ul>
    </div>
<?php elseif ($tab === 'web'): ?>
<?php if ($page === 1) require __DIR__ . '/_infobox.php'; ?>
    <div class="serp__main">
<?php foreach ($res['order'] as $o):
        if ($o[0] === 'web'): $r = $res['results'][$o[1]]; require __DIR__ . '/_result.php';
        elseif ($o[0] === 'news' && $res['news'] && $page === 1): ?>
        <section class="block">
            <h2 class="block__title"><?= h(t('Top-Meldungen')) ?></h2>
            <div class="cards">
<?php foreach (array_slice($res['news'], 0, 6) as $n): ?>
                <a class="card card--news" href="<?= h($n['url']) ?>"<?= $rel . $target ?>>
                    <span class="card__src"><?php $fav = $n['favicon']; $host = $n['host']; require __DIR__ . '/_fav.php'; ?><?= h($n['source'] ?: $n['host']) ?></span>
                    <span class="card__title"><?= smHl($n['title']) ?></span>
<?php if ($n['thumb']): ?><img class="card__img" src="<?= h($n['thumb']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
                    <span class="card__meta"><?= h($n['age'] !== '' ? $n['age'] : smAgeText((int)($n['ts'] ?? 0))) ?></span>
                </a>
<?php endforeach; ?>
            </div>
            <a class="block__more" href="<?= h(smUrl(['q' => $q, 't' => 'news'])) ?>" data-load><?= h(t('Mehr News')) ?> →</a>
        </section>
<?php elseif ($o[0] === 'videos' && $res['videos'] && $page === 1): ?>
        <section class="block">
            <h2 class="block__title"><?= h(t('Videos')) ?></h2>
            <div class="cards">
<?php foreach (array_slice($res['videos'], 0, 4) as $v): ?>
                <a class="card card--video" href="<?= h($v['url']) ?>"<?= $rel . $target ?>>
                    <span class="card__thumb"><?php if ($v['thumb']): ?><img src="<?= h($v['thumb']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
                        <span class="play" aria-hidden="true"></span><?php if ($v['duration']): ?><span class="dur"><?= h($v['duration']) ?></span><?php endif; ?></span>
                    <span class="card__title"><?= smHl($v['title']) ?></span>
                    <span class="card__meta"><?= h(implode(' · ', array_filter([$v['host'], $v['creator'], $v['age']]))) ?></span>
                </a>
<?php endforeach; ?>
            </div>
            <a class="block__more" href="<?= h(smUrl(['q' => $q, 't' => 'videos'])) ?>" data-load><?= h(t('Mehr Videos')) ?> →</a>
        </section>
<?php elseif ($o[0] === 'faq' && $res['faq'] && $page === 1): ?>
        <section class="block">
            <h2 class="block__title"><?= h(t('Ähnliche Fragen')) ?></h2>
            <div class="faq">
<?php foreach ($res['faq'] as $f): ?>
                <details class="faq__item">
                    <summary><?= h($f['q']) ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
                    <div class="faq__a"><p><?= smHl($f['a']) ?></p>
<?php if ($f['url']): ?><a href="<?= h($f['url']) ?>"<?= $rel . $target ?>><?= h($f['title'] ?: $f['host']) ?></a> <span class="muted"><?= h($f['host']) ?></span><?php endif; ?></div>
                </details>
<?php endforeach; ?>
            </div>
        </section>
<?php elseif ($o[0] === 'discussions' && $res['discussions'] && $page === 1): ?>
        <section class="block">
            <h2 class="block__title"><?= h(t('Diskussionen und Foren')) ?></h2>
            <ul class="disc">
<?php foreach ($res['discussions'] as $d): ?>
                <li><a href="<?= h($d['url']) ?>"<?= $rel . $target ?>>
                    <span class="disc__src"><?php $fav = $d['favicon']; $host = $d['host']; require __DIR__ . '/_fav.php'; ?><?= h($d['forum'] ?: $d['host']) ?><?php if ($d['answers']): ?> · <?= h(t('%d Antworten', $d['answers'])) ?><?php endif; ?><?php if ($d['age']): ?> · <?= h($d['age']) ?><?php endif; ?></span>
                    <span class="disc__title"><?= h($d['title']) ?></span></a></li>
<?php endforeach; ?>
            </ul>
        </section>
<?php endif; endforeach; ?>
<?php if ($page === 1 && !empty($res['related'])): ?>
        <section class="block related">
            <h2 class="block__title"><?= h(t('Ähnliche Suchen')) ?></h2>
            <p class="related__list"><?php foreach ($res['related'] as $rq): ?><a class="chip" href="<?= h(smUrl(['q' => $rq])) ?>" data-load><?= h($rq) ?></a> <?php endforeach; ?></p>
        </section>
<?php endif; ?>
<?php require __DIR__ . ($prefs['scroll'] === 'endless' ? '/_more.php' : '/_pager.php'); ?>
    </div>
<?php elseif ($tab === 'images'):
    // Großansicht ohne JavaScript: ?view=N öffnet die Vorschau schon auf dem Server (Blättern per Link, Bilder aus dem Zwischenspeicher)
    $imgs = $res['results'];
    $vi = isset($iview) && $iview >= 0 && isset($imgs[$iview]) ? (int)$iview : -1;
    $gridUrl = smUrl($base);
    $cur = $vi >= 0 ? $imgs[$vi] : null;
    $curFull = $cur ? smImgProxyUrl($cur['src']) : '';
?>
    <div class="grid" data-grid>
<?php foreach ($imgs as $i => $im) require __DIR__ . '/_img.php'; ?>
    </div>
<?php require __DIR__ . ($prefs['scroll'] === 'endless' ? '/_more.php' : '/_pager.php'); ?>
    <div class="lightbox" id="lb" data-lightbox<?= $vi >= 0 ? ' data-view="' . $vi . '"' : ' hidden' ?> role="dialog" aria-modal="true" aria-label="<?= h(t('Bildvorschau')) ?>" data-grid-url="<?= h($gridUrl) ?>">
        <div class="lightbox__box">
            <div class="lightbox__media">
                <img alt="" referrerpolicy="no-referrer" data-lb-img<?= $cur ? ' src="' . h($cur['thumb']) . '"' : '' ?>>
<?php if ($curFull !== ''): ?>                <img class="lightbox__hi" alt="" referrerpolicy="no-referrer" src="<?= h($curFull) ?>" data-lb-hi><?php endif; ?>
            </div>
            <p class="lightbox__cap"><span data-lb-cap><?= $cur ? h(implode(' · ', array_filter([$cur['host'], $cur['title']]))) : '' ?></span> <span class="lightbox__count" data-lb-count><?= $cur ? ($vi + 1) . ' / ' . count($imgs) : '' ?></span></p>
            <div class="lightbox__bar">
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
<?php elseif ($tab === 'news'): ?>
    <div class="serp__main">
<?php foreach ($res['results'] as $n) require __DIR__ . '/_news.php'; ?>
<?php require __DIR__ . ($prefs['scroll'] === 'endless' ? '/_more.php' : '/_pager.php'); ?>
    </div>
<?php elseif ($tab === 'videos'): ?>
    <div class="serp__main">
<?php foreach ($res['results'] as $v) require __DIR__ . '/_video.php'; ?>
<?php require __DIR__ . ($prefs['scroll'] === 'endless' ? '/_more.php' : '/_pager.php'); ?>
    </div>
<?php endif; ?>
<?php if ($res['cached']): ?><p class="serp__cached"><?= h(t('Aus dem Zwischenspeicher, kostet keine Suche.')) ?></p><?php endif; ?>
<?php endif; ?>
</main>
