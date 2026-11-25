<?php defined('SM_VERSION') || exit;
/** Verwaltung → Suchdienste: Keys, Stichtag, Limits, Reihenfolge, Lastverteilung, Ausweichziel, Zustand */
$aurl = function (string $t) { return smUrl(['a' => 'admin', 'tab' => $t]); };
$info = [
    'brave'     => t('Plan „Search“ mit monatlichem Guthaben für etwa 1.000 Suchen. Kann als einziger Dienst Bilder, Videos und Infoboxen.'),
    'tavily'    => t('1.000 Credits pro Monat kostenlos, eine einfache Suche kostet 1 Credit. Liefert auf Wunsch eine Kurzantwort mit.'),
    'exa'       => t('10 $ Guthaben pro Monat. Gezählt wird in Cent anhand der echten Kosten, die Exa bei jeder Antwort mitschickt.'),
    'parallel'  => t('Bis zu 5.000 kostenlose Anfragen pro Monat (Guthaben mit hinterlegter Karte, Ausgabenlimit dort setzen).'),
    'firecrawl' => t('1.000 Credits im kostenlosen Plan. Firecrawl meldet die verbrauchten Credits bei jeder Suche zurück.'),
];
$order = smProvOrder(false);
$withKey = array_values(array_filter($order, function ($id) { return trim(smProvGet($id, 'key')) !== ''; }));
$without = array_values(array_diff($order, $withKey));
?>
    <form method="post" action="<?= h($aurl('providers')) ?>" class="form" data-busy>
        <?= smCsrfField($me) ?><input type="hidden" name="do" value="providers">
<?php if (!$withKey): ?>
        <div class="alert alert--error"><?= h(t('Noch kein Suchdienst eingerichtet. Trage unten mindestens einen API-Key ein.')) ?></div>
<?php endif; ?>
<?php foreach ($withKey as $i => $id): $p = smProviders()[$id]; $st = smProvStat($id); $key = smProvGet($id, 'key'); $f = smProvForecast($id);
    $paused = (int)$st['pause_until'] > time(); $on = smProvGet($id, 'on') !== '0'; ?>
        <section class="panel prov" id="p-<?= h($id) ?>">
            <div class="prov__head">
                <h2><?= h($p['name']) ?></h2>
                <span class="pill <?= !$on ? 'pill--off' : ($paused ? 'pill--warn' : ($f['limit'] > 0 && $f['left'] < (float)smProvGet($id, 'cost') ? 'pill--warn' : 'pill--ok')) ?>">
                    <?= h(!$on ? t('ausgeschaltet') : ($paused ? t('pausiert bis %s', date('H:i', (int)$st['pause_until'])) : ($f['limit'] > 0 && $f['left'] < (float)smProvGet($id, 'cost') ? t('Limit erreicht') : t('aktiv')))) ?></span>
                <a class="btn btn--ghost btn--small prov__dash" href="<?= h($p['site']) ?>" target="_blank" rel="noopener noreferrer"><?= h(t('Dashboard')) ?> ↗</a>
            </div>
            <p class="muted small"><?= h($info[$id]) ?> <?= h(t('Bangs: %s', implode(', ', array_map(function ($b) { return '!' . $b; }, $p['bangs'])))) ?></p>
            <div class="prov__meter">
                <progress class="meter" max="<?= max(1, $f['limit']) ?>" value="<?= $f['limit'] > 0 ? min($f['used'], $f['limit']) : 0 ?>"></progress>
                <span><?= h(smNumF($f['used'])) ?> / <?= $f['limit'] ?: '∞' ?> <?= h($p['unit']) ?> · <?= h(t('Reset am %s', smDate($f['cycle']['end']))) ?></span>
            </div>
            <div class="form__row">
                <label class="field"><span><?= h(t('API-Key')) ?> <small class="muted">(<?= h(mb_substr($key, 0, 4) . '…' . mb_substr($key, -4)) ?>)</small></span>
                    <input type="password" name="key[<?= h($id) ?>]" autocomplete="off" spellcheck="false" placeholder="<?= h(t('neuer Key, leer = behalten')) ?>" data-reveal></label>
                <label class="field"><span><?= h(t('Stichtag (Start des Abrechnungszeitraums)')) ?></span>
                    <input type="date" name="start[<?= h($id) ?>]" value="<?= h(smProvGet($id, 'start')) ?>">
                    <small class="muted"><?= h(t('Der Tag im Monat, an dem das Kontingent neu startet. Leer = Monatserster.')) ?></small></label>
            </div>
            <div class="form__row form__row--4">
                <label class="field"><span><?= h(t('Limit pro Zeitraum')) ?></span><input type="number" name="limit[<?= h($id) ?>]" min="0" value="<?= (int)smProvGet($id, 'limit') ?>">
                    <small class="muted"><?= h(t('Frei: %s. Vorschlag: %d (50 Puffer). 0 = ohne Grenze.', smNumF($p['free']) . ' ' . $p['unit'], $p['limit'])) ?></small></label>
                <label class="field"><span><?= h(t('Kosten pro Suche')) ?></span><input type="number" name="cost[<?= h($id) ?>]" min="0.01" step="0.01" value="<?= h(smProvGet($id, 'cost')) ?>">
                    <small class="muted"><?= h(t('Zum Vorab-Prüfen. Gebucht wird, was der Dienst meldet.')) ?></small></label>
                <label class="field"><span><?= h($id === 'brave' ? t('Zwischenspeicher (Minuten)') : t('Zwischenspeicher (Stunden)')) ?></span>
                    <input type="number" name="cache[<?= h($id) ?>]" min="0" max="<?= $id === 'brave' ? 60 : 168 ?>" value="<?= (int)smProvGet($id, 'cache') ?>">
                    <small class="muted"><?= h($id === 'brave' ? t('Brave erlaubt nur kurzes Speichern (höchstens 60).') : t('Gleiche Suche in dieser Zeit kostet nichts.')) ?></small></label>
                <label class="field"><span><?= h(t('Reihenfolge')) ?></span><input type="number" name="prio[<?= h($id) ?>]" min="1" max="99" value="<?= array_search($id, $order, true) + 1 ?>">
                    <small class="muted"><?= h(t('1 = wird zuerst gefragt.')) ?></small></label>
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
            <h2><?= h(t('Suchdienst hinzufügen')) ?></h2>
            <p class="muted small"><?= h(t('Einfach den API-Key eintragen und speichern. Er wird vorher mit einer Testsuche geprüft. Danach erscheinen hier alle Einstellungen für den Dienst. Wie man an die Keys kommt, steht in der README.')) ?></p>
<?php foreach ($without as $id): $p = smProviders()[$id]; ?>
            <details class="prov__add">
                <summary><strong><?= h($p['name']) ?></strong> <small class="muted"><?= h(t('frei: %s pro Monat', smNumF($p['free']) . ' ' . $p['unit'])) ?></small></summary>
                <p class="muted small"><?= h($info[$id]) ?> <a href="<?= h($p['site']) ?>" target="_blank" rel="noopener noreferrer"><?= h(t('Zum Dashboard')) ?> ↗</a></p>
                <label class="field"><span><?= h(t('API-Key')) ?></span><input type="password" name="key[<?= h($id) ?>]" autocomplete="off" spellcheck="false" placeholder="<?= h($p['key_hint']) ?>" data-reveal></label>
            </details>
<?php endforeach; ?>
        </section>
<?php endif; ?>

<?php if ($withKey): ?>
        <section class="panel">
            <h2><?= h(t('Zusammenspiel')) ?></h2>
            <label class="switch"><input type="checkbox" name="balance" value="1"<?= smSetting('balance') === '1' ? ' checked' : '' ?>><span class="switch__ui"></span>
                <span><?= h(t('Lastverteilung')) ?><br><small class="muted"><?= h(t('Verbraucht ein Dienst schneller, als sein Kontingent bis zum Reset reicht, gehen Suchen schon vorher an den nächsten Dienst. So reichen alle Kontingente gleichmäßig über den Monat.')) ?></small></span></label>
            <label class="switch"><input type="checkbox" name="answers" value="1"<?= smSetting('answers') === '1' ? ' checked' : '' ?>><span class="switch__ui"></span>
                <span><?= h(t('Kurzantwort von Tavily zeigen')) ?><br><small class="muted"><?= h(t('Wenn Tavily sucht, kommt eine kurze KI-Antwort mit, ohne Aufpreis.')) ?></small></span></label>
            <label class="switch"><input type="checkbox" name="wiki" value="1"<?= smSetting('wiki') === '1' ? ' checked' : '' ?>><span class="switch__ui"></span>
                <span><?= h(t('Wikipedia-Kurzinfo ergänzen')) ?><br><small class="muted"><?= h(t('Liefert der Suchdienst keine eigene Infobox (alle außer Brave), holt der Server eine kostenlose Kurzinfo von Wikipedia. Wikipedia sieht dann den Suchbegriff, aber nur den Server, nicht den Besucher.')) ?></small></span></label>
            <fieldset class="field"><legend><?= h(t('Für „Alle zusammen“ (!multi) fragen')) ?></legend>
                <div class="checks">
<?php $ms = explode(',', smSetting('multi_set')); foreach ($withKey as $id): ?>
                    <label class="check"><input type="checkbox" name="multi[]" value="<?= h($id) ?>"<?= in_array($id, $ms, true) ? ' checked' : '' ?>> <?= h(smProviders()[$id]['name']) ?></label>
<?php endforeach; ?>
                </div>
                <small class="muted"><?= h(t('Jeder Dienst kostet dabei seine eigene Suche. Doppelte Treffer werden zusammengelegt.')) ?></small>
            </fieldset>
        </section>
<?php endif; ?>
        <section class="panel">
            <h2><?= h(t('Wenn nichts mehr geht')) ?></h2>
            <p class="muted small"><?= h(t('Sind alle Dienste aufgebraucht, gestört oder nicht eingerichtet, leitet die Suche automatisch an dieses Ziel weiter. So funktioniert sie immer.')) ?></p>
<?php $fbu = smSetting('fallback_url'); $isPreset = isset(smFallbackPresets()[$fbu]); ?>
            <div class="form__row">
                <label class="field"><span><?= h(t('Ausweichziel')) ?></span>
                    <select name="fallback_preset">
<?php foreach (smFallbackPresets() as $u => $n): ?><option value="<?= h($u) ?>"<?= $u === $fbu ? ' selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?>
                        <option value="own"<?= !$isPreset && $fbu !== '' ? ' selected' : '' ?>><?= h(t('Eigene Adresse …')) ?></option>
                        <option value=""<?= $fbu === '' ? ' selected' : '' ?>><?= h(t('keins')) ?></option>
                    </select></label>
                <label class="field"><span><?= h(t('Eigene Adresse mit {q}')) ?></span><input type="text" name="fallback_own" value="<?= h($isPreset ? '' : $fbu) ?>" placeholder="https://example.org/search?q={q}" autocomplete="off" spellcheck="false"></label>
            </div>
            <label class="switch"><input type="checkbox" name="fallback_auto" value="1"<?= smSetting('fallback_auto') === '1' ? ' checked' : '' ?>><span class="switch__ui"></span>
                <span><?= h(t('Automatisch weiterleiten (sonst nur ein Knopf auf der Fehlerseite)')) ?></span></label>
            <label class="field field--short"><span><?= h(t('Warnhinweis für Administratoren ab (%)')) ?></span><input type="number" name="warn_pct" min="10" max="100" value="<?= (int)smSetting('warn_pct') ?>"></label>
        </section>
        <div class="form__actions form__actions--sticky"><button type="submit" class="btn btn--primary"><?= h(t('Speichern')) ?></button></div>
    </form>
<?php foreach ($withKey as $id): ?>
    <form method="post" action="<?= h($aurl('providers')) ?>" id="ptest-<?= h($id) ?>" hidden data-busy><?= smCsrfField($me) ?><input type="hidden" name="do" value="ptest"><input type="hidden" name="p" value="<?= h($id) ?>"></form>
    <form method="post" action="<?= h($aurl('providers')) ?>" id="punpause-<?= h($id) ?>" hidden><?= smCsrfField($me) ?><input type="hidden" name="do" value="punpause"><input type="hidden" name="p" value="<?= h($id) ?>"></form>
<?php endforeach; ?>
