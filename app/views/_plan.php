<?php defined('SM_VERSION') || exit;
/**
 * Karte eines Brave-Plans, aufgebaut wie im Brave-Dashboard. $pid = Plan, $planEdit = true: mit API-Key, Limit, An/Aus
 * (Verwaltung → Suchdienste), false: nur Zahlen und „Requests laut Dashboard“ (Verwaltung → Verbrauch).
 */
$pl = smPlans()[$pid];
$pk = smPlanKey($pid);
$ps = smPlanStats($pid);
$pon = smPlanOn($pid);
$pp = smPlanPausedUntil($pid);
$money = function (float $v) { return '$' . number_format($v, 2, smLang() === 'de' ? ',' : '.', ''); };
?>
        <section class="panel plan" id="p-<?= h($pid) ?>">
            <div class="plan__head">
                <h2><?= h($pl['plan']) ?> <small class="muted"><?= h($pl['name']) ?></small></h2>
<?php if ($pk !== ''): ?>
                <span class="pill <?= !$pon ? 'pill--off' : ($ps['stop'] || $pp ? 'pill--warn' : 'pill--ok') ?>"><?= h(!$pon ? t('ausgeschaltet') : ($ps['stop'] ? t('Limit erreicht') : ($pp ? t('pausiert bis %s', date('H:i', $pp)) : t('aktiv')))) ?></span>
<?php else: ?>
                <span class="pill pill--off"><?= h(t('kein Key')) ?></span>
<?php endif; ?>
                <a class="btn btn--ghost btn--small plan__dash" href="https://api-dashboard.search.brave.com" target="_blank" rel="noopener noreferrer"><?= h(t('Dashboard')) ?> ↗</a>
            </div>
<?php if ($planEdit): ?>            <p class="muted small"><?= h($pl['desc']) ?></p>
<?php endif; ?>
<?php if ($pk !== ''): ?>
            <dl class="plan__stats">
                <div class="plan__row"><dt><?= h(t('Requests')) ?></dt><dd><span class="plan__num"><?= h(smNumF($ps['requests'])) ?></span></dd>
                    <progress class="meter<?= $ps['stop'] ? ' meter--warn' : '' ?>" max="<?= max(1, $ps['limit']) ?>" value="<?= $ps['limit'] > 0 ? min($ps['requests'], $ps['limit']) : 0 ?>"></progress>
                    <small class="muted"><?= h($ps['limit'] > 0 ? t('Bremse bei %s Requests, noch %s', smNumF($ps['limit']), smNumF($ps['left'])) : t('ohne Bremse')) ?></small></div>
                <div class="plan__row"><dt><?= h(t('Credits remaining')) ?></dt><dd><span class="plan__num"><?= h($money($ps['remaining'])) ?></span></dd></div>
                <div class="plan__row"><dt><?= h(t('Credits used this month')) ?></dt><dd><span class="plan__pct"><?= (int)$ps['pct'] ?> %</span> <span class="plan__num"><?= h($money($ps['used'])) ?> / <?= h($money(SM_PLAN_CREDIT)) ?></span></dd>
                    <progress class="meter<?= $ps['pct'] >= 90 ? ' meter--warn' : '' ?>" max="100" value="<?= (int)$ps['pct'] ?>"></progress></div>
            </dl>
            <p class="plan__outlook small"><span><?= h(t('Burn rate')) ?> <strong><?= h($money($ps['burn'])) ?><?= h(t('/Tag')) ?></strong></span>
                <span><?= h(t('Runway')) ?> <strong><?= $ps['runway'] === null ? '–' : h(t('~%d Tage', min(999, $ps['runway']))) ?></strong></span>
                <span><?= h(t('Reset am %s', smDate($ps['reset']))) ?></span></p>
            <div class="form__row">
                <label class="field field--short"><span><?= h(t('Requests laut Dashboard')) ?></span>
                    <input type="text" inputmode="numeric" name="preq[<?= h($pid) ?>]" value="" placeholder="<?= h(t('jetzt: %s', smNumF($ps['requests']))) ?>" autocomplete="off">
                    <small class="muted"><?= h(t('Zahl bei „%s“ → „Requests“ im Dashboard. Leer = nichts ändern.', $pl['plan'])) ?></small></label>
<?php if ($planEdit): ?>
                <label class="field field--short"><span><?= h(t('Limit (Requests pro Monat)')) ?></span>
                    <input type="number" name="plimit[<?= h($pid) ?>]" min="0" value="<?= (int)$ps['limit'] ?>">
                    <small class="muted"><?= h(t('Vorschlag: %s. Darüber hält die Suchmaschine an, bevor es Geld kostet. 0 = ohne Grenze.', smNumF((float)$pl['limit']))) ?></small></label>
<?php endif; ?>
            </div>
<?php endif; ?>
<?php if ($planEdit): ?>
            <div class="form__row">
                <label class="field"><span><?= h(t('API-Key für %s', $pl['plan'])) ?><?= $pk !== '' ? ' <small class="muted">(' . h(mb_substr($pk, 0, 4) . '…' . mb_substr($pk, -4)) . ')</small>' : '' ?></span>
                    <input type="password" name="pkey[<?= h($pid) ?>]" autocomplete="off" spellcheck="false" placeholder="<?= h($pk !== '' ? t('neuer Key, leer = behalten') : 'BSA…') ?>" data-reveal>
                    <small class="muted"><?= h(t('Im Brave-Dashboard den Plan „%s“ wählen und dort einen Key anlegen. Er wird vor dem Speichern geprüft.', $pl['plan'])) ?></small></label>
<?php if ($pid === 'brave'): ?>
                <label class="field field--short"><span><?= h(t('Zwischenspeicher (Minuten)')) ?></span>
                    <input type="number" name="cache[brave]" min="0" max="60" value="<?= (int)smProvGet('brave', 'cache') ?>">
                    <small class="muted"><?= h(t('Gleiche Suchen in dieser Zeit kosten nichts. Brave erlaubt höchstens 60.')) ?></small></label>
<?php endif; ?>
            </div>
<?php if ($pk !== ''): ?>
            <div class="plan__acts">
                <input type="hidden" name="pshown[<?= h($pid) ?>]" value="1">
                <label class="switch"><input type="checkbox" name="pon[<?= h($pid) ?>]" value="1"<?= $pon ? ' checked' : '' ?>><span class="switch__ui"></span><span><?= h(t('Eingeschaltet')) ?></span></label>
                <button type="submit" class="btn btn--small btn--danger" name="pdel" value="<?= h($pid) ?>" data-confirm-btn="<?= h(t('Den API-Key für %s wirklich entfernen?', $pl['plan'])) ?>"><?= h(t('Key entfernen')) ?></button>
            </div>
<?php endif; ?>
<?php endif; ?>
        </section>
