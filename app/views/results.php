<?php defined('SM_VERSION') || exit;
require __DIR__ . '/_vars.php';
?>
<nav class="tabs" aria-label="<?= h(t('Art der Suche')) ?>">
    <div class="tabs__inner">
<?php foreach (smTabs() as $k => $label): ?>
        <a class="tabs__tab<?= $k === $tab ? ' is-active' : '' ?>" href="<?= h(smUrl(['q' => $q, 't' => $k !== 'web' ? $k : null])) ?>"<?= $k === $tab ? ' aria-current="page"' : '' ?> data-load><?= h($label) ?></a>
<?php endforeach; ?>
<?php if ($tab !== 'images'): ?>
        <details class="tools">
            <summary class="tabs__tab<?= $fresh ? ' is-set' : '' ?>"><?= h($fresh ? smFreshness()[$fresh] : t('Zeitraum')) ?> <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
            <div class="tools__panel">
<?php foreach (smFreshness() as $k => $label): ?>
                <a href="<?= h(smUrl(['f' => $k ?: null] + $base)) ?>"<?= $k === $fresh ? ' class="is-active"' : '' ?> data-load><?= h($label) ?></a>
<?php endforeach; ?>
            </div>
        </details>
<?php endif; ?>
    </div>
</nav>

<main class="serp serp--<?= h($tab) ?>">
<?php if ($error !== ''): ?>
    <div class="alert alert--error serp__error">
        <strong><?= h(t('Die Suche hat nicht geklappt.')) ?></strong> <?= h($error) ?>
        <p><a class="btn btn--small" href="<?= h(smSelf()) ?>"><?= h(t('Noch einmal versuchen')) ?></a></p>
    </div>
<?php elseif ($res): ?>
<?php if (!empty($res['altered']) && !$exact): ?>
    <p class="serp__altered"><?= t('Ergebnisse für <strong>%s</strong>', h($res['altered'])) ?><br>
        <small><?= t('Stattdessen suchen nach <a href="%s">%s</a>', h(smUrl(['exact' => '1'] + $base)), h($q)) ?></small></p>
<?php endif; ?>

<?php if (!$res['results'] && empty($res['news']) && empty($res['videos'])): ?>
    <div class="serp__empty">
        <p><?= t('Zu <strong>%s</strong> wurde nichts gefunden.', h($q)) ?></p>
        <ul><li><?= h(t('Stimmt die Schreibweise?')) ?></li><li><?= h(t('Andere oder allgemeinere Begriffe probieren.')) ?></li>
<?php if ($fresh): ?><li><a href="<?= h(smUrl(['f' => null] + $base)) ?>"><?= h(t('Ohne Zeitraum suchen')) ?></a></li><?php endif; ?></ul>
    </div>
<?php elseif ($tab === 'web'): ?>
<?php if ($page === 1 && !empty($res['infobox'])): $ib = $res['infobox']; ?>
    <aside class="kp" aria-label="<?= h(t('Kurzinfo')) ?>">
<?php if ($ib['thumb']): ?><img class="kp__img" src="<?= h($ib['thumb']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
        <h2 class="kp__title"><?= h($ib['title']) ?></h2>
<?php if ($ib['subtitle']): ?><p class="kp__sub"><?= h($ib['subtitle']) ?></p><?php endif; ?>
<?php if ($ib['desc']): ?><p class="kp__desc" data-clamp><?= h($ib['desc']) ?>
<?php if ($ib['url']): ?> <a href="<?= h($ib['url']) ?>"<?= $rel . $target ?>><?= h($ib['source'] ?: smHost((string)parse_url($ib['url'], PHP_URL_HOST))) ?></a><?php endif; ?></p><?php endif; ?>
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
                    <span class="card__meta"><?= h($n['age']) ?></span>
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
<?php require __DIR__ . ($prefs['scroll'] === 'endless' ? '/_more.php' : '/_pager.php'); ?>
    </div>
<?php elseif ($tab === 'images'): ?>
    <div class="grid" data-grid>
<?php foreach ($res['results'] as $i => $im): ?>
        <a class="grid__item" href="<?= h($im['page']) ?>"<?= $rel . $target ?> data-img="<?= h($im['thumb']) ?>" data-w="<?= (int)($im['tw'] ?: $im['w']) ?>" data-h="<?= (int)($im['th'] ?: $im['h']) ?>"
           data-title="<?= h($im['title']) ?>" data-host="<?= h($im['host']) ?>" data-src="<?= h($im['src']) ?>" data-size="<?= $im['w'] && $im['h'] ? (int)$im['w'] . ' × ' . (int)$im['h'] : '' ?>">
            <img src="<?= h($im['thumb']) ?>" alt="<?= h($im['title']) ?>" loading="<?= $i < 12 ? 'eager' : 'lazy' ?>" referrerpolicy="no-referrer">
            <span class="grid__cap"><span class="grid__host"><?= h($im['host']) ?></span><span class="grid__title"><?= h($im['title']) ?></span></span>
        </a>
<?php endforeach; ?>
    </div>
    <div class="lightbox" data-lightbox hidden role="dialog" aria-modal="true" aria-label="<?= h(t('Bildvorschau')) ?>">
        <div class="lightbox__box">
            <button type="button" class="iconbtn lightbox__close" data-lb-close aria-label="<?= h(t('Schließen')) ?>"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
            <div class="lightbox__media"><img alt="" referrerpolicy="no-referrer" data-lb-img></div>
            <div class="lightbox__info">
                <span class="lightbox__host" data-lb-host></span>
                <h2 class="lightbox__title" data-lb-title></h2>
                <span class="muted" data-lb-size></span>
                <div class="lightbox__actions">
                    <a class="btn btn--primary" data-lb-page<?= $rel . $target ?>><?= h(t('Seite besuchen')) ?></a>
                    <a class="btn" data-lb-src<?= $rel ?> target="_blank"><?= h(t('Bild in Originalgröße')) ?></a>
                </div>
            </div>
            <button type="button" class="iconbtn lightbox__nav lightbox__nav--prev" data-lb-prev aria-label="<?= h(t('Vorheriges Bild')) ?>"><svg viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></svg></button>
            <button type="button" class="iconbtn lightbox__nav lightbox__nav--next" data-lb-next aria-label="<?= h(t('Nächstes Bild')) ?>"><svg viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></button>
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
