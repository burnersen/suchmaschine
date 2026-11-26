<?php defined('SM_VERSION') || exit;
require __DIR__ . '/_vars.php';
?>
<nav class="tabs" aria-label="<?= h(t('Art der Suche')) ?>">
    <div class="tabs__inner">
<?php foreach (smTabs() as $k => $label): ?>
        <a class="tabs__tab<?= $k === $tab ? ' is-active' : '' ?>" href="<?= h(smUrl(['q' => $q, 't' => $k !== 'web' ? $k : null, 'l' => $lens ?: null, 'src' => $flt['src'] !== '' && in_array($k, ['web', 'news'], true) ? $flt['src'] : null])) ?>"<?= $k === $tab ? ' aria-current="page"' : '' ?> data-load><?= h($label) ?></a>
<?php endforeach; ?>
<?php if (in_array($tab, ['web', 'news'], true)): ?>
        <details class="tools">
<?php $sdef = $prefs['sort_' . $tab]; ?>
            <summary class="tabs__tab<?= $flt['sort_q'] !== '' ? ' is-set' : '' ?>"><?= h($flt['sort'] ? t('Neueste zuerst') : t('Relevanz')) ?> <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
            <div class="tools__panel">
                <a href="<?= h(smUrl(['sort' => $sdef === 'rel' ? null : 'rel'] + $base)) ?>"<?= $flt['sort'] === '' ? ' class="is-active"' : '' ?> data-load><?= h(t('Relevanz')) ?></a>
                <a href="<?= h(smUrl(['sort' => $sdef === 'date' ? null : 'date'] + $base)) ?>"<?= $flt['sort'] === 'date' ? ' class="is-active"' : '' ?> data-load><?= h(t('Nach Datum (neueste zuerst)')) ?></a>
<?php if ($me): ?>
                <a class="tools__manage" href="<?= h(smUrl(['a' => 'settings', 'tab' => 'search'])) ?>#sort"><?= h(t('Standard ändern')) ?></a>
<?php endif; ?>
            </div>
        </details>
<?php endif; ?>
<?php if ($tab === 'images'): ?>
        <details class="tools">
            <summary class="tabs__tab<?= $flt['io'] ? ' is-set' : '' ?>"><?= h(smImageOrientations()[$flt['io']] === smImageOrientations()[''] ? t('Ausrichtung') : smImageOrientations()[$flt['io']]) ?> <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
            <div class="tools__panel">
<?php foreach (smImageOrientations() as $k => $label): ?>
                <a href="<?= h(smUrl(['io' => $k ?: null] + $base)) ?>"<?= $k === $flt['io'] ? ' class="is-active"' : '' ?> data-load><?= h($label) ?></a>
<?php endforeach; ?>
            </div>
        </details>
        <details class="tools">
            <summary class="tabs__tab<?= $flt['is'] ? ' is-set' : '' ?>"><?= h($flt['is'] ? smImageSizes()[$flt['is']] : t('Größe')) ?> <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
            <div class="tools__panel">
<?php foreach (smImageSizes() as $k => $label): ?>
                <a href="<?= h(smUrl(['is' => $k ?: null] + $base)) ?>"<?= $k === $flt['is'] ? ' class="is-active"' : '' ?> data-load><?= h($label) ?></a>
<?php endforeach; ?>
            </div>
        </details>
        <details class="tools">
            <summary class="tabs__tab<?= $flt['isort'] ? ' is-set' : '' ?>"><?= h($flt['isort'] ? t('Höchste Auflösung') : t('Sortierung')) ?> <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
            <div class="tools__panel">
                <a href="<?= h(smUrl(['isort' => null] + $base)) ?>"<?= $flt['isort'] === '' ? ' class="is-active"' : '' ?> data-load><?= h(t('Relevanz')) ?></a>
                <a href="<?= h(smUrl(['isort' => 'res'] + $base)) ?>"<?= $flt['isort'] === 'res' ? ' class="is-active"' : '' ?> data-load><?= h(t('Höchste Auflösung zuerst')) ?></a>
            </div>
        </details>
<?php endif; ?>
<?php if ($tab !== 'images'): ?>
        <details class="tools">
            <summary class="tabs__tab<?= $flt['f_q'] !== '' ? ' is-set' : '' ?>"><?= h($fresh ? smFreshness()[$fresh] : ($tab === 'news' ? smFreshness()[''] : t('Zeitraum'))) ?> <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
            <div class="tools__panel">
<?php foreach (smFreshness() as $k => $label):
    // News haben einen eigenen Standard-Zeitraum: „beliebig“ heißt dort ausdrücklich f=all, der Standard steht nicht in der Adresse
    $fv = $tab === 'news' ? ($k === '' ? 'all' : $k) : $k;
    $fv = $tab === 'news' && $fv === $prefs['news_fresh'] ? '' : $fv; ?>
                <a href="<?= h(smUrl(['f' => $fv ?: null, 'xf' => null] + $base)) ?>"<?= $k === $fresh ? ' class="is-active"' : '' ?> data-load><?= h($label) ?></a>
<?php endforeach; ?>
            </div>
        </details>
<?php endif; ?>
<?php if ($tab === 'web'): $fts = smFileTypes(); ?>
        <details class="tools">
            <summary class="tabs__tab<?= $ft ? ' is-set' : '' ?>"><?= h($ft ? ($fts[$ft] ?? strtoupper($ft)) : t('Dateityp')) ?> <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
            <div class="tools__panel tools__panel--cols">
                <a href="<?= h(smUrl(['ft' => null] + $base)) ?>"<?= $ft === '' ? ' class="is-active"' : '' ?> data-load><?= h(t('Alle Dateitypen')) ?></a>
<?php foreach ($fts as $k => $label): ?>
                <a href="<?= h(smUrl(['ft' => $k] + $base)) ?>"<?= $k === $ft ? ' class="is-active"' : '' ?> data-load><?= h($label) ?></a>
<?php endforeach; ?>
                <form class="tools__form" method="get" action="<?= h(smBase()) ?>" data-busy>
<?php foreach (array_filter(['ft' => null] + $base, function ($v) { return $v !== null && $v !== ''; }) as $k => $v): ?>
                    <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>">
<?php endforeach; ?>
                    <label class="sr" for="ft-own"><?= h(t('Eigener Dateityp')) ?></label>
                    <input type="text" id="ft-own" name="ft" maxlength="10" pattern="\.?[A-Za-z0-9]{1,10}" placeholder="<?= h(t('Andere, z. B. md')) ?>" value="<?= h(isset($fts[$ft]) ? '' : $ft) ?>" autocomplete="off" autocapitalize="off" spellcheck="false">
                    <button type="submit" class="btn btn--small"><?= h(t('OK')) ?></button>
                </form>
            </div>
        </details>
<?php endif; ?>
<?php if ($lenses): ?>
        <details class="tools">
            <summary class="tabs__tab<?= $lens ? ' is-set' : '' ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="m20 20-4.5-4.5M8.5 11h5"/></svg><?= h($lens ? $lenses[$lens][0] : t('Linse')) ?> <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
            <div class="tools__panel">
                <a href="<?= h(smUrl(['l' => null] + $base)) ?>"<?= $lens === '' ? ' class="is-active"' : '' ?> data-load><?= h(t('Ohne Linse')) ?></a>
<?php foreach ($lenses as $k => $l): ?>
                <a href="<?= h(smUrl(['l' => $k] + $base)) ?>"<?= $k === $lens ? ' class="is-active"' : '' ?> title="<?= h(smLensQuery($l)) ?>" data-load><?= h($l[0]) ?></a>
<?php endforeach; ?>
<?php if ($me): ?>
                <a class="tools__manage" href="<?= h(smUrl(['a' => 'settings', 'tab' => 'lenses'])) ?>"><?= h(t('Linsen verwalten')) ?></a>
<?php endif; ?>
            </div>
        </details>
<?php endif; ?>
<?php if ($tab === 'web'): ?>
        <a class="tabs__tab tabs__toggle<?= $flt['dl'] === '' ? ' is-on' : ' is-set' ?>" href="<?= h(smUrl(['dl' => $flt['dl'] === '' ? '0' : null] + $base)) ?>" role="switch" aria-checked="<?= $flt['dl'] === '' ? 'true' : 'false' ?>" title="<?= h(t('Damit eine einzelne Seite nicht die ganze Trefferliste füllt')) ?>" data-load><span class="tabs__check" aria-hidden="true"></span><?= h(t('Max. 3 pro Domain')) ?></a>
<?php endif; ?>
<?php if ($ft || $lens || array_filter(array_intersect_key($flt, array_flip(['f_q', 'xf', 'sort_q', 'dl', 'src', 'io', 'is', 'isort'])), function ($v) { return $v !== ''; }) || $exact): ?>
        <a class="tabs__tab tabs__reset" href="<?= h(smUrl(['q' => $q, 't' => $tab !== 'web' ? $tab : null])) ?>" data-load><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg><?= h(t('Alle Filter zurücksetzen')) ?></a>
<?php endif; ?>
        <a class="tabs__tab tabs__help" href="<?= h(smUrl(['a' => 'help'])) ?>" title="<?= h(t('Hilfe zu Suchoperatoren, Bangs und Linsen')) ?>"><?= h(t('Hilfe')) ?></a>
    </div>
</nav>

<main class="serp serp--<?= h($tab) ?>">
<?php if (!empty($instant)): require __DIR__ . '/_instant.php'; ?>
<?php elseif ($error !== ''): $fb = smFallbackUrl($q); ?>
    <div class="alert alert--error serp__error">
        <strong><?= h(t('Die Suche hat nicht geklappt.')) ?></strong> <?= h($error) ?>
        <p><a class="btn btn--small" href="<?= h(smSelf()) ?>"><?= h(t('Noch einmal versuchen')) ?></a>
<?php if ($fb !== ''): ?> <a class="btn btn--small btn--primary" href="<?= h($fb) ?>"<?= $rel . $target ?>><?= h(t('Bei %s suchen', smFallbackName())) ?></a><?php endif; ?>
<?php if ($flt['src'] !== ''): ?> <a class="btn btn--small" href="<?= h(smUrl(['src' => null] + $base)) ?>" data-load><?= h(t('Automatisch einen anderen Dienst nehmen')) ?></a><?php endif; ?>
<?php if ($me && $me['is_admin']): ?> <a class="btn btn--small" href="<?= h(smUrl(['a' => 'admin', 'tab' => smProvOrder() ? 'usage' : 'providers'])) ?>"><?= h(smProvOrder() ? t('Verbrauch ansehen') : t('Suchdienst einrichten')) ?></a><?php endif; ?></p>
    </div>
<?php require __DIR__ . '/_srcbar.php'; ?>
<?php elseif ($res): ?>
<?php require __DIR__ . '/_srcbar.php'; ?>
<?php if (!empty($res['notice']) && empty($res['cached'])): ?>
    <p class="serp__notice"><strong><?= h($res['source'] === 'multi' ? t('Ergebnisse von %s', implode(' + ', array_map(function ($i) { return smProviders()[$i]['name']; }, $res['sources']))) : t('Ergebnisse von %s', smProviders()[$res['source']]['name'])) ?>.</strong>
        <?= h(implode(' · ', $res['notice'])) ?></p>
<?php endif; ?>
<?php if (!empty($widened)): ?>
    <p class="serp__note"><?= h(t('Keine News aus dem Zeitraum „%s“, darum: %s.', smFreshness()[$widened], smFreshness()[$fresh])) ?></p>
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
<?php if (!empty($res['altered']) && !$exact): ?>
    <p class="serp__altered"><?= t('Ergebnisse für <strong>%s</strong>', h($res['altered'])) ?><br>
        <small><?= t('Stattdessen suchen nach <a href="%s">%s</a>', h(smUrl(['exact' => '1'] + $base)), h($q)) ?></small></p>
<?php endif; ?>

<?php if (!$res['results'] && empty($res['news']) && empty($res['videos'])): ?>
    <div class="serp__empty">
        <p><?= t('Zu <strong>%s</strong> wurde nichts gefunden.', h($q)) ?></p>
        <ul><li><?= h(t('Stimmt die Schreibweise?')) ?></li><li><?= h(t('Andere oder allgemeinere Begriffe probieren.')) ?></li>
<?php if ($fresh): ?><li><a href="<?= h(smUrl(['f' => $tab === 'news' ? 'all' : null, 'xf' => null] + $base)) ?>"><?= h(t('Ohne Zeitraum suchen')) ?></a></li><?php endif; ?>
<?php if ($ft): ?><li><a href="<?= h(smUrl(['ft' => null] + $base)) ?>"><?= h(t('Ohne Dateityp suchen')) ?></a></li><?php endif; ?>
<?php if ($lens): ?><li><a href="<?= h(smUrl(['l' => null] + $base)) ?>"><?= h(t('Ohne Linse suchen')) ?></a></li><?php endif; ?>
<?php if ($flt['io'] || $flt['is']): ?><li><a href="<?= h(smUrl(['io' => null, 'is' => null] + $base)) ?>"><?= h(t('Ohne Bildfilter suchen')) ?></a></li><?php endif; ?>
<?php if ($flt['src'] !== ''): ?><li><a href="<?= h(smUrl(['src' => null] + $base)) ?>"><?= h(t('Mit einem anderen Suchdienst suchen')) ?></a></li><?php endif; ?></ul>
    </div>
<?php elseif ($tab === 'web'): ?>
<?php if ($page === 1 && !empty($res['answer'])): ?>
    <section class="answer" aria-label="<?= h(t('Kurzantwort')) ?>">
        <h2 class="answer__title"><?= h(t('Kurzantwort')) ?> <span class="answer__src"><?= h(t('KI-generiert von Tavily, bitte nachprüfen')) ?></span></h2>
        <p class="answer__text" data-clamp><?= h($res['answer']) ?></p>
    </section>
<?php endif; ?>
<?php if ($page === 1 && !empty($res['infobox'])): $ib = $res['infobox']; ?>
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
<?php if (!empty($res['source']) && $res['source'] !== 'multi' && !$res['cached']): ?><p class="serp__cached"><?= h(t('Geliefert von %s.', smProviders()[$res['source']]['name'])) ?></p><?php endif; ?>
<?php endif; ?>
</main>
