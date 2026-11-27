<?php defined('SM_VERSION') || exit;
/** Verwaltung → Verbrauch: die vier Brave-Pläne wie im Dashboard (Requests laut Dashboard eintragbar), pro Konto, Verlauf */
$plans = smPlans();
$withKey = array_values(array_filter(array_keys($plans), function ($id) { return smPlanKey($id) !== ''; }));
// Zählername im Verbrauchsprotokoll je Plan
$col = function (string $id) { return $id === 'brave' ? 'brave' : 'x_' . $id; };
?>
<?php if (!$withKey): ?>
    <section class="panel"><p class="muted"><?= h(t('Noch kein Brave-Plan eingerichtet.')) ?> <a href="<?= h(smUrl(['a' => 'admin', 'tab' => 'providers'])) ?>"><?= h(t('Jetzt einrichten')) ?></a></p></section>
<?php else: ?>
    <form method="post" action="<?= h(smUrl(['a' => 'admin', 'tab' => 'usage'])) ?>" class="form" data-busy>
        <?= smCsrfField($me) ?><input type="hidden" name="do" value="plans">
        <button type="submit" class="sr" tabindex="-1" aria-hidden="true"><?= h(t('Speichern')) ?></button>
        <div class="usage">
<?php foreach ($withKey as $pid): $planEdit = false; require __DIR__ . '/_plan.php'; endforeach; ?>
        </div>
        <p class="muted small"><?= h(t('Die Dollar rechnet die Suchmaschine aus den Requests aus, so wie Brave (jeder Plan hat 5 $ im Monat, bei Answers zählt der Durchschnittspreis der Antworten). Treffer aus dem Zwischenspeicher, Bangs, Sofort-Antworten und Suchen bei der Ausweich-Suchmaschine kosten nichts.')) ?></p>
        <div class="form__actions form__actions--sticky"><button type="submit" class="btn btn--primary"><?= h(t('Requests übernehmen')) ?></button></div>
    </form>

<?php if ($users): ?>
    <section class="panel">
        <h2><?= h(t('Pro Konto (laufender Monat)')) ?></h2>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= h(t('Konto')) ?></th><?php foreach ($withKey as $id): ?><th><?= h($plans[$id]['plan']) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
<?php foreach ($users as $u): $qq = smQuotaOf((int)$u['id']); ?>
                <tr><td><?= h($u['name']) ?></td>
<?php foreach ($withKey as $id): $used = smUsageSum($col($id), smPlanFrom($id), (int)$u['id']); ?>
                    <td><?= h(smNumF($used)) ?><?php if ($id === 'brave'): ?><?= isset($qq['lim']['brave']) ? ' <span class="muted">/ ' . (int)$qq['lim']['brave'] . '</span>' : '' ?><?= $qq['allow'] !== null && !in_array('brave', $qq['allow'], true) ? ' <span class="muted">' . h(t('(gesperrt)')) . '</span>' : '' ?><?php endif; ?></td>
<?php endforeach; ?></tr>
<?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
<?php endif; ?>
<?php endif; ?>

    <section class="panel">
        <h2><?= h(t('Verlauf nach Kalendermonat (Requests)')) ?></h2>
<?php if (!$history): ?>
        <p class="muted"><?= h(t('Noch keine Requests.')) ?></p>
<?php else: ?>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= h(t('Monat')) ?></th><?php foreach ($plans as $id => $pl): ?><th><?= h($pl['plan']) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
<?php foreach ($history as $m => $prov): ?>
                <tr><td><?= h($m) ?></td><?php foreach (array_keys($plans) as $id): ?><td><?= isset($prov[$col($id)]) ? h(smNumF($prov[$col($id)])) : '<span class="muted">–</span>' ?></td><?php endforeach; ?></tr>
<?php endforeach; ?>
            </tbody>
        </table>
        </div>
<?php endif; ?>
    </section>
