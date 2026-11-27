<?php defined('SM_VERSION') || exit;
require __DIR__ . '/_vars.php';
?>
<nav class="tabs" aria-label="<?= h(t('Art der Suche')) ?>">
    <div class="tabs__inner">
<?php foreach (smTabs() as $k => $label): ?>
        <a class="tabs__tab<?= $k === $tab ? ' is-active' : '' ?>" href="<?= h(smUrl(['q' => $q, 't' => $k !== 'web' ? $k : null, 'l' => $lens ?: null, 'src' => $flt['src'] !== '' && in_array($k, ['web', 'news'], true) ? $flt['src'] : null])) ?>"<?= $k === $tab ? ' aria-current="page"' : '' ?> data-load><?= h($label) ?></a>
<?php endforeach; ?>
<?php if (in_array($tab, ['web', 'news', 'videos'], true)): ?>
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
        <?= $smDrop('ic', smImageColors(), t('Farbe')) ?>
        <?= $smDrop('it', smImageTypes(), t('Typ')) ?>
        <?= $smDrop('il', smImageLicenses(), t('Lizenz')) ?>
        <details class="tools">
            <summary class="tabs__tab<?= $flt['isort'] ? ' is-set' : '' ?>"><?= h($flt['isort'] ? t('Relevanz') : t('Beste Qualität zuerst')) ?> <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
            <div class="tools__panel">
                <a href="<?= h(smUrl(['isort' => null, 'iq' => null] + $base)) ?>"<?= $flt['isort'] === '' ? ' class="is-active"' : '' ?> data-load><?= h(t('Beste Qualität zuerst')) ?></a>
                <a href="<?= h(smUrl(['isort' => 'rel', 'iq' => null] + $base)) ?>"<?= $flt['isort'] === 'rel' ? ' class="is-active"' : '' ?> data-load><?= h(t('Relevanz')) ?></a>
            </div>
        </details>
<?php endif; ?>
<?php if ($tab === 'videos'): ?>
        <?= $smDrop('vd', array_map(function ($d) { return $d[0]; }, smVideoDurations()), t('Länge')) ?>
<?php endif; ?>
<?php $range = $flt['fd'] !== '' || $flt['td'] !== '';
      $rangeText = $range ? ($flt['fd'] !== '' ? smDate((int)strtotime($flt['fd'])) : '…') . ' – ' . ($flt['td'] !== '' ? smDate((int)strtotime($flt['td'])) : '…') : ''; ?>
        <details class="tools">
            <summary class="tabs__tab<?= $flt['f_q'] !== '' || $range ? ' is-set' : '' ?>"><?= h($range ? $rangeText : ($fresh ? smTierLabel($fresh) : ($flt['casc'] ? smFreshness()[''] : t('Zeitraum')))) ?> <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
            <div class="tools__panel">
<?php foreach (smFreshness() as $k => $label):
    // Bei „Neueste zuerst“ gibt es einen Start-Zeitraum: „beliebig“ heißt dort ausdrücklich f=all, der Start steht nicht in der Adresse
    $fv = $flt['casc'] ? ($k === '' ? 'all' : $k) : $k;
    $fv = $flt['casc'] && $fv === ($flt['def'] !== '' ? $flt['def'] : 'all') ? '' : $fv; ?>
                <a href="<?= h(smUrl(['f' => $fv ?: null, 'xf' => null, 'fd' => null, 'td' => null] + $base)) ?>"<?= $k === $fresh && !$range ? ' class="is-active"' : '' ?> data-load><?= h($label) ?></a>
<?php endforeach; ?>
                <form class="tools__form tools__form--range" method="get" action="<?= h(smBase()) ?>" data-busy>
                    <?= $smHidden(['f', 'xf', 'fd', 'td', 'p']) ?>
                    <label><span><?= h(t('Von')) ?></span><input type="date" name="fd" value="<?= h($flt['fd']) ?>" max="<?= date('Y-m-d') ?>"></label>
                    <label><span><?= h(t('Bis')) ?></span><input type="date" name="td" value="<?= h($flt['td']) ?>" max="<?= date('Y-m-d') ?>"></label>
                    <button type="submit" class="btn btn--small"><?= h(t('OK')) ?></button>
                </form>
            </div>
        </details>
<?php $langs = smSearchLangs(); $ctry = smCountries();
      $lrText = implode(' · ', array_filter([$flt['sl'] !== '' ? $langs[$flt['sl']] : '', $flt['cr'] !== '' ? $ctry[$flt['cr']] : ''])); ?>
        <details class="tools">
            <summary class="tabs__tab<?= $lrText !== '' ? ' is-set' : '' ?>"><?= h($lrText !== '' ? $lrText : t('Sprache & Region')) ?> <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
            <div class="tools__panel">
                <form class="tools__form tools__form--lr" method="get" action="<?= h(smBase()) ?>" data-busy>
                    <?= $smHidden(['sl', 'cr', 'p']) ?>
                    <label><span><?= h(t('Sprache der Seiten')) ?></span><select name="sl">
                        <option value=""><?= h(t('Alle Sprachen')) ?></option>
<?php foreach ($langs as $k => $l): ?>                        <option value="<?= h($k) ?>"<?= $k === $flt['sl'] ? ' selected' : '' ?>><?= h($l) ?></option>
<?php endforeach; ?>
                    </select></label>
                    <label><span><?= h(t('Ergebnisse wie aus')) ?></span><select name="cr">
                        <option value=""><?= h(t('Eigene Einstellung (%s)', $ctry[$prefs['country']] ?? $prefs['country'])) ?></option>
<?php foreach ($ctry as $k => $l): ?>                        <option value="<?= h($k) ?>"<?= $k === $flt['cr'] ? ' selected' : '' ?>><?= h($l) ?></option>
<?php endforeach; ?>
                    </select></label>
                    <button type="submit" class="btn btn--small"><?= h(t('Übernehmen')) ?></button>
                </form>
            </div>
        </details>
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
<?php if (in_array($tab, ['web', 'news'], true)): ?>
        <a class="tabs__tab tabs__toggle<?= $flt['vb'] === '1' ? ' is-on is-set' : '' ?>" href="<?= h(smUrl(['vb' => $flt['vb'] === '1' ? null : '1'] + $base)) ?>" role="switch" aria-checked="<?= $flt['vb'] === '1' ? 'true' : 'false' ?>" title="<?= h(t('Genau diese Wörter, ohne Korrektur und ohne ähnliche Begriffe')) ?>" data-load><span class="tabs__check" aria-hidden="true"></span><?= h(t('Wortgenau')) ?></a>
<?php endif; ?>
<?php if ($tab === 'web'): ?>
        <a class="tabs__tab tabs__toggle<?= $flt['dl'] === '' ? ' is-on' : ' is-set' ?>" href="<?= h(smUrl(['dl' => $flt['dl'] === '' ? '0' : null] + $base)) ?>" role="switch" aria-checked="<?= $flt['dl'] === '' ? 'true' : 'false' ?>" title="<?= h(t('Damit eine einzelne Seite nicht die ganze Trefferliste füllt')) ?>" data-load><span class="tabs__check" aria-hidden="true"></span><?= h(t('Max. 3 pro Domain')) ?></a>
<?php endif; ?>
<?php if ($ft || $lens || array_filter(array_intersect_key($flt, array_flip(['f_q', 'xf', 'sort_q', 'dl', 'src', 'io', 'is', 'isort', 'fd', 'td', 'vb', 'sl', 'cr', 'ic', 'it', 'il', 'vd'])), function ($v) { return $v !== ''; }) || $exact): ?>
        <a class="tabs__tab tabs__reset" href="<?= h(smUrl(['q' => $q, 't' => $tab !== 'web' ? $tab : null])) ?>" data-load><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg><?= h(t('Alle Filter zurücksetzen')) ?></a>
<?php endif; ?>
        <a class="tabs__tab tabs__help" href="<?= h(smUrl(['a' => 'help'])) ?>" title="<?= h(t('Hilfe zu Suchoperatoren, Bangs und Linsen')) ?>"><?= h(t('Hilfe')) ?></a>
    </div>
</nav>

<?php if (!empty($stream)): ?>
<div class="serp-wait<?= !empty($warm) ? ' serp-wait--warm' : '' ?>" role="status"<?= !empty($warm) ? ' data-warm="' . h($warm) . '"' : '' ?>><span class="spinner"></span> <?= h(t('Suche läuft …')) ?></div>
<!--SM-STREAM-->
<?php else: require __DIR__ . '/_serp.php'; endif; ?>
