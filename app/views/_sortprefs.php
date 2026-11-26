<?php defined('SM_VERSION') || exit;
/** Sortierung und News-Zeitraum: in den eigenen Einstellungen ($sp = smPrefs) und als Standard für alle in der Verwaltung */
$sortOpts = ['date' => t('Neueste zuerst'), 'rel' => t('Relevanz')];
$newsFresh = ['pd' => t('Letzte 24 Stunden'), 'pw' => t('Letzte Woche'), 'pm' => t('Letzter Monat'), 'py' => t('Letztes Jahr'), 'all' => t('Beliebige Zeit')];
?>
            <div class="form__row">
                <fieldset class="field"><legend><?= h(t('Sortierung bei „Alle“')) ?></legend>
                    <div class="seg">
<?php foreach ($sortOpts as $k => $v): ?>
                        <label><input type="radio" name="sort_web" value="<?= $k ?>"<?= $k === $sp['sort_web'] ? ' checked' : '' ?>><span><?= h($v) ?></span></label>
<?php endforeach; ?>
                    </div>
                </fieldset>
                <fieldset class="field"><legend><?= h(t('Sortierung bei News')) ?></legend>
                    <div class="seg">
<?php foreach ($sortOpts as $k => $v): ?>
                        <label><input type="radio" name="sort_news" value="<?= $k ?>"<?= $k === $sp['sort_news'] ? ' checked' : '' ?>><span><?= h($v) ?></span></label>
<?php endforeach; ?>
                    </div>
                </fieldset>
            </div>
            <small class="muted"><?= h(t('„Neueste zuerst“ sortiert die Treffer jeder Seite nach Datum, Treffer ohne Datum stehen am Ende. Über „Sortierung“ in der Trefferliste lässt sich das jederzeit umschalten.')) ?></small>
            <div class="form__row">
                <label class="field"><span><?= h(t('News-Zeitraum')) ?></span>
                    <select name="news_fresh">
<?php foreach ($newsFresh as $k => $v): ?><option value="<?= $k ?>"<?= $k === $sp['news_fresh'] ? ' selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?>
                    </select></label>
            </div>
            <label class="switch"><input type="checkbox" name="news_expand" value="1"<?= $sp['news_expand'] ? ' checked' : '' ?>><span class="switch__ui"></span>
                <span><?= h(t('News-Zeitraum automatisch erweitern')) ?><br><small class="muted"><?= h(t('Am Ende der Liste geht es mit älteren News weiter: erst die letzte Woche, dann der letzte Monat, dann das letzte Jahr. Gibt es gar keine aktuellen News, wird gleich erweitert.')) ?></small></span></label>
