<?php defined('SM_VERSION') || exit;
/** Verwaltung → Verbrauch: pro Dienst Zähler, Zeitraum, Reset, Prognose; Verlauf; Verbrauch pro Konto */
$ids = smProvOrder();
$all = array_keys(smProviders());
?>
<?php if (!$ids): ?>
    <section class="panel"><p class="muted"><?= h(t('Noch kein Suchdienst eingerichtet.')) ?> <a href="<?= h(smUrl(['a' => 'admin', 'tab' => 'providers'])) ?>"><?= h(t('Jetzt einrichten')) ?></a></p></section>
<?php endif; ?>
    <div class="usage">
<?php foreach ($ids as $id): $p = smProviders()[$id]; $f = smProvForecast($id); $c = $f['cycle']; ?>
    <section class="panel usage__card">
        <h2><?= h($p['name']) ?></h2>
        <p class="big-num"><?= h(smNumF($f['used'])) ?> <span class="muted">/ <?= $f['limit'] ?: '∞' ?> <small><?= h($p['unit']) ?></small></span></p>
<?php if ($f['limit']): ?><progress class="meter<?= $f['pct'] >= 0.8 ? ' meter--warn' : '' ?>" max="<?= $f['limit'] ?>" value="<?= min($f['used'], $f['limit']) ?>"></progress><?php endif; ?>
        <dl class="facts">
            <div><dt><?= h(t('Zeitraum')) ?></dt><dd><?= h(smDate($c['start'])) ?> – <?= h(smDate($c['end'] - 86400)) ?></dd></div>
            <div><dt><?= h(t('Reset')) ?></dt><dd><?= h(smLongDate($c['end'])) ?> <span class="muted">(<?= h(t('noch %s Tage', smNumF(ceil($c['left'])))) ?><?= smProvAnchor($id)[1] === 'api' ? ', ' . h(t('laut %s', $p['badge'])) : '' ?>)</span></dd></div>
<?php if ($f['api_left'] !== null): ?>
            <div><dt><?= h(t('Laut %s übrig', $p['badge'])) ?></dt><dd><?= h(smNumF($f['api_left'])) ?></dd></div>
<?php endif; ?>
<?php if (($adj = smProvAdjust($id)) > 0): ?>
            <div><dt><?= h(smProvApiKnowsUsage($id) ? t('Davon per Abgleich mit %s', $p['badge']) : t('Davon vorab eingetragen')) ?></dt><dd><?= h(smNumF($adj)) ?></dd></div>
<?php endif; ?>
            <div><dt><?= h(t('Heute')) ?></dt><dd><?= h(smNumF($f['today'])) ?></dd></div>
            <div><dt><?= h(t('Ø pro Tag')) ?></dt><dd><?= h(smNumF($f['avg'], 1)) ?></dd></div>
            <div><dt><?= h(t('Hochrechnung bis Reset')) ?></dt><dd><?= h(smNumF($f['projected'])) ?></dd></div>
<?php if ($f['limit']): ?>
            <div><dt><?= h(t('Noch übrig')) ?></dt><dd><?= h(smNumF($f['left'])) ?> <span class="muted">(<?= h(t('%s pro Tag bis zum Reset', smNumF($f['per_day'], 1))) ?>)</span></dd></div>
<?php endif; ?>
        </dl>
<?php if (!$f['limit']): ?>
        <p class="verdict"><?= h(t('Ohne Limit. Den echten Stand zeigt das Dashboard des Dienstes.')) ?></p>
<?php elseif ($f['fits']): ?>
        <p class="verdict verdict--ok">✓ <?= h(t('Reicht bis zum Reset (beim jetzigen Tempo etwa %d %% des Limits).', (int)round($f['projected'] / max(1, $f['limit']) * 100))) ?></p>
<?php else: ?>
        <p class="verdict verdict--warn">⚠ <?= h($f['empty_at'] ? t('Reicht beim jetzigen Tempo nur bis etwa %s, %s Tage vor dem Reset.', smDate($f['empty_at']), smNumF(max(0, ceil(($c['end'] - $f['empty_at']) / 86400)))) : t('Limit erreicht.')) ?>
            <?= h(smSetting('balance') === '1' && count($ids) > 1 ? t('Die Lastverteilung schont den Dienst schon.') : '') ?></p>
<?php endif; ?>
    </section>
<?php endforeach; ?>
    </div>
    <p class="muted small"><?= h(t('Gezählt werden nur echte Anfragen an die Dienste. Treffer aus dem Zwischenspeicher, Bangs und Sofort-Antworten sind kostenlos. Exa wird in Cent nach den gemeldeten Kosten gezählt. Den genauen Stand zeigen die Dashboards der Dienste.')) ?></p>

<?php if ($ids && $users): ?>
    <section class="panel">
        <h2><?= h(t('Pro Konto (laufender Zeitraum)')) ?></h2>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= h(t('Konto')) ?></th><?php foreach ($ids as $id): ?><th><?= h(smProviders()[$id]['badge']) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
<?php foreach (array_merge($users, smSetting('public') === '1' ? [['id' => 0, 'name' => t('Besucher ohne Anmeldung')]] : []) as $u): $qq = smQuotaOf((int)$u['id']); ?>
                <tr><td><?= h($u['name']) ?></td>
<?php foreach ($ids as $id): $used = smUsageSum($id, smProvCycle($id)['from'], (int)$u['id']); ?>
                    <td><?= h(smNumF($used)) ?><?= isset($qq['lim'][$id]) ? ' <span class="muted">/ ' . (int)$qq['lim'][$id] . '</span>' : '' ?><?= $qq['allow'] !== null && !in_array($id, $qq['allow'], true) ? ' <span class="muted">' . h(t('(gesperrt)')) . '</span>' : '' ?></td>
<?php endforeach; ?></tr>
<?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
<?php endif; ?>

    <section class="panel">
        <h2><?= h(t('Verlauf nach Kalendermonat')) ?></h2>
<?php if (!$history): ?>
        <p class="muted"><?= h(t('Noch keine Suchen.')) ?></p>
<?php else: ?>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= h(t('Monat')) ?></th><?php foreach ($all as $id): ?><th><?= h(smProviders()[$id]['badge']) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
<?php foreach ($history as $m => $prov): ?>
                <tr><td><?= h($m) ?></td><?php foreach ($all as $id): ?><td><?= isset($prov[$id]) ? h(smNumF($prov[$id])) : '<span class="muted">–</span>' ?></td><?php endforeach; ?></tr>
<?php endforeach; ?>
            </tbody>
        </table>
        </div>
<?php endif; ?>
    </section>
