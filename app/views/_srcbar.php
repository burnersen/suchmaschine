<?php defined('SM_VERSION') || exit;
/** Quellen-Leiste: dieselbe Suche mit einem Klick bei einem anderen Suchdienst, mit den restlichen Credits */
if (in_array($tab, ['web', 'news'], true)):
    $uid = $me ? $me['id'] : 0;
    $avail = [];
    foreach (smProvOrder() as $sid) {
        if (!in_array($tab, smProviders()[$sid]['kinds'], true) || smQuotaCheck($sid, $uid) === 'denied') continue;
        $avail[] = $sid;
    }
    if (count($avail) > 1):
        $cur = $flt['src'];
        $used = $res['source'] ?? '';
?>
    <nav class="srcbar" aria-label="<?= h(t('Suchdienst wählen')) ?>">
        <span class="srcbar__label"><?= h(t('Suchen bei:')) ?></span>
        <a class="chip<?= $cur === '' ? ' is-active' : '' ?>" href="<?= h(smUrl(['src' => null] + $base)) ?>" data-load title="<?= h(t('Automatisch: der beste verfügbare Dienst, mit Lastverteilung und Ausweichen bei Störungen')) ?>"><?= h(t('Automatisch')) ?><?= $cur === '' && $used !== '' && $used !== 'multi' ? ' · ' . h(smProviders()[$used]['badge']) : '' ?></a>
<?php foreach ($avail as $sid): $fc = smProvForecast($sid); $blk = smProvBlock($sid, $tab, $uid); ?>
        <a class="chip<?= $cur === $sid ? ' is-active' : '' ?><?= $blk !== '' ? ' is-off' : '' ?>" href="<?= h(smUrl(['src' => $sid] + $base)) ?>" data-load
           title="<?= h($blk !== '' ? smBlockText($sid, $blk) : t('Noch %s %s bis %s', smNumF($fc['left']), smProviders()[$sid]['unit'], smDate($fc['cycle']['end']))) ?>"><?= h(smProviders()[$sid]['badge']) ?> <small><?= $fc['limit'] > 0 ? h(smNumF($fc['left'])) : '∞' ?></small></a>
<?php endforeach; ?>
<?php if ($tab === 'web'): ?>
        <a class="chip<?= $cur === 'multi' ? ' is-active' : '' ?>" href="<?= h(smUrl(['src' => 'multi'] + $base)) ?>" data-load title="<?= h(t('Mehrere Dienste gleichzeitig fragen, doppelte Treffer zusammenlegen (kostet bei jedem Dienst)')) ?>"><?= h(t('Alle zusammen')) ?></a>
<?php endif; ?>
    </nav>
<?php endif; endif;
