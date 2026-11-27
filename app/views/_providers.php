<?php defined('SM_VERSION') || exit;
/** Verwaltung → Suchdienste: Brave (Key, Stichtag, Limit, Zustand) und die Ausweich-Suchmaschine */
$aurl = function (string $t) { return smUrl(['a' => 'admin', 'tab' => $t]); };
$info = [
    'brave' => t('Plan „Search“ mit monatlichem Guthaben für etwa 1.000 Suchen. Web, Bilder, News und Videos, eigener Index.'),
];
// So kommt man an den Key (ausführlicher in der README)
$guide = [
    'brave' => [t('Bei api-dashboard.search.brave.com anmelden und den Plan „Search“ wählen. Eine Karte ist nötig (gegen Missbrauch), abgerechnet wird über das Guthaben, kein Abo.'), t('Automatisches Aufladen ausgeschaltet lassen.'), t('Unter „API Keys“ einen Key anlegen (beginnt mit BSA) und hier einfügen.')],
];
$order = smProvOrder(false);
$withKey = array_values(array_filter($order, function ($id) { return trim(smProvGet($id, 'key')) !== ''; }));
$without = array_values(array_diff($order, $withKey));
?>
    <form method="post" action="<?= h($aurl('providers')) ?>" class="form" data-busy>
        <?= smCsrfField($me) ?><input type="hidden" name="do" value="providers">
<?php if (!$withKey): ?>
        <div class="alert"><?= h(t('Noch kein Brave-Key eingetragen. Das geht auch so: Jede Suche landet dann direkt bei der Ausweich-Suchmaschine unten.')) ?></div>
<?php endif; ?>
<?php foreach ($withKey as $i => $id): $p = smProviders()[$id]; $st = smProvStat($id); $key = smProvGet($id, 'key'); $f = smProvForecast($id);
    $anc = smProvAnchor($id); $api = smProvApi($id);
    $paused = (int)$st['pause_until'] > time(); $on = smProvGet($id, 'on') !== '0'; ?>
        <section class="panel prov" id="p-<?= h($id) ?>">
            <div class="prov__head">
                <h2><?= h($p['name']) ?></h2>
                <span class="pill <?= !$on ? 'pill--off' : ($paused ? 'pill--warn' : ($f['limit'] > 0 && $f['left'] < (float)smProvGet($id, 'cost') ? 'pill--warn' : 'pill--ok')) ?>">
                    <?= h(!$on ? t('ausgeschaltet') : ($paused ? t('pausiert bis %s', date('H:i', (int)$st['pause_until'])) : ($f['limit'] > 0 && $f['left'] < (float)smProvGet($id, 'cost') ? t('Limit erreicht') : t('aktiv')))) ?></span>
                <a class="btn btn--ghost btn--small prov__dash" href="<?= h($p['site']) ?>" target="_blank" rel="noopener noreferrer"><?= h(t('Dashboard')) ?> ↗</a>
            </div>
            <p class="muted small"><?= h($info[$id]) ?></p>
            <div class="prov__meter">
                <progress class="meter" max="<?= max(1, $f['limit']) ?>" value="<?= $f['limit'] > 0 ? min($f['used'], $f['limit']) : 0 ?>"></progress>
                <span><?= h(smNumF($f['used'])) ?> / <?= $f['limit'] ?: '∞' ?> <?= h($p['unit']) ?> · <?= h(t('Reset am %s', smDate($f['cycle']['end']))) ?><?= $anc[1] === 'api' ? ' ' . h(t('(laut %s)', $p['badge'])) : '' ?></span>
            </div>
            <div class="form__row">
                <label class="field"><span><?= h(t('API-Key')) ?> <small class="muted">(<?= h(mb_substr($key, 0, 4) . '…' . mb_substr($key, -4)) ?>)</small></span>
                    <input type="password" name="key[<?= h($id) ?>]" autocomplete="off" spellcheck="false" placeholder="<?= h(t('neuer Key, leer = behalten')) ?>" data-reveal></label>
<?php if ($anc[1] === 'api'): ?>
                <div class="field"><span><?= h(t('Reset-Datum')) ?></span>
                    <p class="prov__auto"><strong><?= h(smDate($f['cycle']['end'])) ?></strong> <span class="pill pill--ok"><?= h(t('automatisch')) ?></span></p>
                    <small class="muted"><?= h(t('Meldet %s selbst, ein Stichtag ist nicht nötig. Zuletzt bestätigt: %s.', $p['name'], smDate((int)$api['at']) . ' ' . date('H:i', (int)$api['at']))) ?></small></div>
<?php else: ?>
                <label class="field"><span><?= h(t('Stichtag (Start des Abrechnungszeitraums)')) ?></span>
                    <input type="date" name="start[<?= h($id) ?>]" value="<?= h(smProvGet($id, 'start')) ?>">
                    <small class="muted"><?= h($id === 'brave' ? t('Wird nach der ersten Suche automatisch von Brave übernommen. Bis dahin: der Tag im Monat, an dem das Kontingent neu startet. Leer = Monatserster.') : t('Der Tag im Monat, an dem das Kontingent neu startet. Leer = Monatserster.')) ?></small></label>
<?php endif; ?>
            </div>
<?php if ($f['api_left'] !== null): ?>
            <p class="small prov__api"><?= h(t('Laut %s noch %s %s übrig (zuletzt abgeglichen: %s). Der Zähler hier richtet sich danach.', $p['name'], smNumF($f['api_left']), $p['unit'], smDate((int)$api['at']) . ' ' . date('H:i', (int)$api['at']))) ?></p>
<?php endif; ?>
            <div class="form__row">
<?php $setAt = (int)smProvGet($id, 'usedset'); ?>
                <label class="field field--short"><span><?= h(t('Requests laut Dashboard')) ?></span>
                    <input type="text" inputmode="numeric" name="used[<?= h($id) ?>]" value="" placeholder="<?= h(t('jetzt gezählt: %s', smNumF($f['used']))) ?>" autocomplete="off">
                    <small class="muted"><?= h(t('Im Brave-Dashboard unter „Usage by plan“ → „%s“ die Zahl bei „Requests“ eintragen. Der Zähler übernimmt genau diesen Stand und zählt von da an weiter. Leer lassen = nichts ändern.', 'Search')) ?>
<?php if ($setAt > 0 && $setAt >= strtotime($f['cycle']['from'])): ?>
                        <?= h(t('Zuletzt nach Dashboard gesetzt: %s.', smDate($setAt) . ' ' . date('H:i', $setAt))) ?>
<?php endif; ?></small></label>
            </div>
            <div class="form__row form__row--3">
                <label class="field"><span><?= h(t('Limit pro Zeitraum')) ?></span><input type="number" name="limit[<?= h($id) ?>]" min="0" value="<?= (int)smProvGet($id, 'limit') ?>">
                    <small class="muted"><?= h(t('Frei: %s. Vorschlag: %d (50 Puffer). 0 = ohne Grenze.', smNumF($p['free']) . ' ' . $p['unit'], $p['limit'])) ?></small></label>
                <label class="field"><span><?= h(t('Kosten pro Suche')) ?></span><input type="number" name="cost[<?= h($id) ?>]" min="0.01" step="0.01" value="<?= h(smProvGet($id, 'cost')) ?>">
                    <small class="muted"><?= h(t('Zum Vorab-Prüfen. Gebucht wird, was der Dienst meldet.')) ?></small></label>
                <label class="field"><span><?= h($id === 'brave' ? t('Zwischenspeicher (Minuten)') : t('Zwischenspeicher (Stunden)')) ?></span>
                    <input type="number" name="cache[<?= h($id) ?>]" min="0" max="<?= $id === 'brave' ? 60 : 168 ?>" value="<?= (int)smProvGet($id, 'cache') ?>">
                    <small class="muted"><?= h($id === 'brave' ? t('Brave erlaubt nur kurzes Speichern (höchstens 60).') : t('Gleiche Suche in dieser Zeit kostet nichts.')) ?></small></label>
            </div>
            <label class="switch"><input type="checkbox" name="on[<?= h($id) ?>]" value="1"<?= $on ? ' checked' : '' ?>><span class="switch__ui"></span><span><?= h(t('Eingeschaltet')) ?></span></label>
            <label class="check"><input type="checkbox" name="keydel[<?= h($id) ?>]" value="1"> <?= h(t('Key entfernen')) ?></label>
            <p class="prov__state small">
                <?= h(t('Letzter Erfolg: %s', (int)$st['ok_at'] ? smDate((int)$st['ok_at']) . ' ' . date('H:i', (int)$st['ok_at']) : '–')) ?>
                · <?= h(t('Ø Antwortzeit: %s', (int)$st['ms_n'] ? smNumF((float)$st['ms_avg']) . ' ms' : '–')) ?>
<?php if ((int)$st['err_at']): ?>
                <br><span class="prov__err"><?= h(t('Letzter Fehler (%s): %s', smDate((int)$st['err_at']) . ' ' . date('H:i', (int)$st['err_at']), $st['err'])) ?></span>
<?php endif; ?>
            </p>
            <div class="form__actions">
                <button type="submit" class="btn btn--small" form="ptest-<?= h($id) ?>"><?= h(t('Key testen')) ?></button>
<?php if ($paused): ?><button type="submit" class="btn btn--small" form="punpause-<?= h($id) ?>"><?= h(t('Pause aufheben')) ?></button><?php endif; ?>
            </div>
        </section>
<?php endforeach; ?>

<?php if ($without): ?>
        <section class="panel">
            <h2><?= h(t('Brave einrichten')) ?></h2>
            <p class="muted small"><?= h(t('Einfach den API-Key eintragen und speichern. Er wird vorher mit einer Testsuche geprüft. Danach erscheinen hier alle Einstellungen. Ein Klick auf „Brave Search“ zeigt, wie du an den Key kommst.')) ?></p>
<?php foreach ($without as $id): $p = smProviders()[$id]; ?>
            <details class="prov__add">
                <summary><strong><?= h($p['name']) ?></strong> <small class="muted"><?= h(t('frei: %s pro Monat', smNumF($p['free']) . ' ' . $p['unit'])) ?></small></summary>
                <p class="muted small"><?= h($info[$id]) ?></p>
                <ol class="prov__steps">
<?php foreach ($guide[$id] ?? [] as $step): ?>
                    <li><?= h($step) ?></li>
<?php endforeach; ?>
                </ol>
                <p><a class="btn btn--small" href="<?= h($p['site']) ?>" target="_blank" rel="noopener noreferrer"><?= h(t('Zum Dashboard')) ?> ↗</a></p>
                <label class="field"><span><?= h(t('API-Key')) ?></span><input type="password" name="key[<?= h($id) ?>]" autocomplete="off" spellcheck="false" placeholder="<?= h($p['key_hint']) ?>" data-reveal></label>
            </details>
<?php endforeach; ?>
        </section>
<?php endif; ?>

<?php foreach (smExtras() as $xid => $x): $xk = smExGet($xid, 'key'); $xu = smExUsed($xid); $xl = smExLimit($xid);
    $xfmt = function (float $v) use ($x) { return $x['money'] ? '$' . smNumF($v, 2) : smNumF($v); }; ?>
        <section class="panel prov" id="p-<?= h($xid) ?>">
            <div class="prov__head">
                <h2><?= h($x['name']) ?> <small class="muted">(Brave <?= h($x['plan']) ?>)</small></h2>
<?php if ($xk !== ''): ?>
                <span class="pill <?= !smExActive($xid) ? 'pill--off' : ($xl > 0 && $xu >= $xl ? 'pill--warn' : ((int)smExGet($xid, 'pause') > time() ? 'pill--warn' : 'pill--ok')) ?>"><?= h(!smExActive($xid) ? t('ausgeschaltet') : ($xl > 0 && $xu >= $xl ? t('Limit erreicht') : ((int)smExGet($xid, 'pause') > time() ? t('pausiert bis %s', date('H:i', (int)smExGet($xid, 'pause'))) : t('aktiv')))) ?></span>
<?php endif; ?>
                <a class="btn btn--ghost btn--small prov__dash" href="https://api-dashboard.search.brave.com" target="_blank" rel="noopener noreferrer"><?= h(t('Dashboard')) ?> ↗</a>
            </div>
            <p class="muted small"><?= h($x['desc']) ?> <?= h(t('Nur für angemeldete Konten, jedes Konto kann es unter Einstellungen → Suche abschalten.')) ?></p>
<?php if ($xk !== ''): ?>
            <div class="prov__meter">
                <progress class="meter" max="<?= $xl > 0 ? $xl : 1 ?>" value="<?= $xl > 0 ? min($xu, $xl) : 0 ?>"></progress>
                <span><?= h($xfmt($xu)) ?> / <?= $xl > 0 ? h($xfmt($xl)) : '∞' ?><?= $x['money'] ? ' · ' . h(t('%s Requests', smNumF((float)smExRequests($xid)))) : ' ' . h(t('Anfragen')) ?> · <?= h(t('Reset am %s', smDate(mktime(0, 0, 0, (int)date('n') + 1, 1, (int)date('Y'))))) ?></span>
            </div>
<?php endif; ?>
<?php if ($xk !== ''): $xset = (int)smExGet($xid, 'usedset'); $xreq = smExRequests($xid); ?>
            <div class="form__row">
                <label class="field field--short"><span><?= h(t('Requests laut Dashboard')) ?></span>
                    <input type="text" inputmode="numeric" name="xreq[<?= h($xid) ?>]" value="" placeholder="<?= h(t('jetzt gezählt: %s', smNumF((float)$xreq))) ?>" autocomplete="off"></label>
<?php if ($x['money']): ?>
                <label class="field field--short"><span><?= h(t('Credits used laut Dashboard ($)')) ?></span>
                    <input type="text" inputmode="decimal" name="xused[<?= h($xid) ?>]" value="" placeholder="<?= h(t('jetzt gezählt: %s', $xfmt($xu))) ?>" autocomplete="off"></label>
<?php endif; ?>
            </div>
            <p class="muted small"><?= h($x['money']
                ? t('Im Brave-Dashboard unter „Usage by plan“ → „%s“ die Zahl bei „Requests“ und den Betrag bei „Credits used this month“ eintragen (z. B. 0,18). Die Bremse richtet sich nach dem Betrag. Leer lassen = nichts ändern.', $x['plan'])
                : t('Im Brave-Dashboard unter „Usage by plan“ → „%s“ die Zahl bei „Requests“ eintragen. Der Zähler übernimmt genau diesen Stand und zählt von da an weiter. Leer lassen = nichts ändern.', $x['plan'])) ?>
<?php if ($xset >= strtotime(date('Y-m-01'))): ?> <?= h(t('Zuletzt nach Dashboard gesetzt: %s.', smDate($xset) . ' ' . date('H:i', $xset))) ?><?php endif; ?></p>
<?php endif; ?>
            <div class="form__row">
                <label class="field"><span><?= h(t('API-Key für %s', $x['plan'])) ?><?= $xk !== '' ? ' <small class="muted">(' . h(mb_substr($xk, 0, 4) . '…' . mb_substr($xk, -4)) . ')</small>' : '' ?></span>
                    <input type="password" name="xkey[<?= h($xid) ?>]" autocomplete="off" spellcheck="false" placeholder="<?= h($xk !== '' ? t('neuer Key, leer = behalten') : 'BSA…') ?>" data-reveal>
                    <small class="muted"><?= h(t('Im Brave-Dashboard den Plan „%s“ wählen und dort einen eigenen Key anlegen.', $x['plan'])) ?></small></label>
                <label class="field field--short"><span><?= h($x['money'] ? t('Limit pro Monat (Dollar)') : t('Limit pro Monat')) ?></span>
                    <input type="number" name="xlimit[<?= h($xid) ?>]" min="0" step="<?= $x['money'] ? '0.01' : '1' ?>" value="<?= h($x['money'] ? number_format($xl, 2, '.', '') : (string)(int)$xl) ?>">
                    <small class="muted"><?= h($x['money'] ? t('Vorschlag: 4,50 (das Guthaben ist 5 $). 0 = ohne Grenze.') : t('Vorschlag: %s (50 Puffer). 0 = ohne Grenze.', smNumF((float)$x['limit']))) ?></small></label>
            </div>
<?php if ($xk !== ''): ?>
            <input type="hidden" name="xshown[<?= h($xid) ?>]" value="1">
            <label class="switch"><input type="checkbox" name="xon[<?= h($xid) ?>]" value="1"<?= smExGet($xid, 'on') !== '0' ? ' checked' : '' ?>><span class="switch__ui"></span><span><?= h(t('Eingeschaltet')) ?></span></label>
            <label class="check"><input type="checkbox" name="xdel[<?= h($xid) ?>]" value="1"> <?= h(t('Key entfernen')) ?></label>
<?php endif; ?>
        </section>
<?php endforeach; ?>
        <section class="panel">
            <h2><?= h(t('Ausweich-Suchmaschine')) ?></h2>
<?php $fbCur = smSetting('fallback_url'); $fbDefault = false; require __DIR__ . '/_fallback.php'; ?>
            <label class="field field--short"><span><?= h(t('Warnhinweis für Administratoren ab (%)')) ?></span><input type="number" name="warn_pct" min="10" max="100" value="<?= (int)smSetting('warn_pct') ?>"></label>
        </section>
        <div class="form__actions form__actions--sticky"><button type="submit" class="btn btn--primary"><?= h(t('Speichern')) ?></button></div>
    </form>
<?php foreach ($withKey as $id): ?>
    <form method="post" action="<?= h($aurl('providers')) ?>" id="ptest-<?= h($id) ?>" hidden data-busy><?= smCsrfField($me) ?><input type="hidden" name="do" value="ptest"><input type="hidden" name="p" value="<?= h($id) ?>"></form>
    <form method="post" action="<?= h($aurl('providers')) ?>" id="punpause-<?= h($id) ?>" hidden><?= smCsrfField($me) ?><input type="hidden" name="do" value="punpause"><input type="hidden" name="p" value="<?= h($id) ?>"></form>
<?php endforeach; ?>
