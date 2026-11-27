<?php defined('SM_VERSION') || exit;
/** Verwaltung → Suchdienste: die vier Brave-Pläne (gleich aufgebaut wie im Brave-Dashboard) und die Ausweich-Suchmaschine */
$aurl = function (string $t) { return smUrl(['a' => 'admin', 'tab' => $t]); };
?>
    <form method="post" action="<?= h($aurl('providers')) ?>" class="form" data-busy>
        <?= smCsrfField($me) ?><input type="hidden" name="do" value="providers">
        <button type="submit" class="sr" tabindex="-1" aria-hidden="true"><?= h(t('Speichern')) ?></button>
<?php if (smPlanKey('brave') === ''): ?>
        <div class="alert"><?= h(t('Noch kein Key für Brave Search. Das geht auch so: Jede Suche landet dann direkt bei der Ausweich-Suchmaschine unten. Einen Key bekommst du bei api-dashboard.search.brave.com (Plan „Search“, automatisches Aufladen ausgeschaltet lassen).')) ?></div>
<?php endif; ?>
<?php foreach (array_keys(smPlans()) as $pid): $planEdit = true; require __DIR__ . '/_plan.php'; endforeach; ?>
        <section class="panel">
            <h2><?= h(t('Ausweich-Suchmaschine')) ?></h2>
<?php $fbCur = smSetting('fallback_url'); $fbDefault = false; require __DIR__ . '/_fallback.php'; ?>
            <label class="field field--short"><span><?= h(t('Warnhinweis für Administratoren ab (%)')) ?></span><input type="number" name="warn_pct" min="10" max="100" value="<?= (int)smSetting('warn_pct') ?>"></label>
        </section>
        <div class="form__actions form__actions--sticky"><button type="submit" class="btn btn--primary"><?= h(t('Speichern')) ?></button></div>
    </form>

