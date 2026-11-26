<?php defined('SM_VERSION') || exit;
/**
 * Einstellungen → Bangs: suchen, anlegen, bearbeiten, löschen, Grundzustand wiederherstellen.
 * $for = 'all': Administrator bearbeitet die Bangs für alle (dazu: Liste aktualisieren, Erreichbarkeit).
 */
$all = $for === 'all';
$bq = mb_substr((string)($_GET['bq'] ?? ''), 0, 60);
$bf = (string)($_GET['bf'] ?? '');
$burl = function (array $more = []) use ($all, $bq, $bf) {
    return smUrl(['a' => 'settings', 'tab' => 'bangs', 'for' => $all ? 'all' : null, 'bq' => $bq !== '' ? $bq : null, 'bf' => $bf !== '' ? $bf : null] + $more);
};
$L = smBangLayers($me, $all ? 'all' : 'mine');
$mineOwn = $all ? $L['gown'] : $L['own'];
$mineOff = $all ? $L['goff'] : $L['off'];
$nEdited = count(array_filter($mineOwn, function ($b) { return !empty($b['e']); }));
$nOwn = count($mineOwn) - $nEdited;
$editT = smCleanBangTrigger($_GET['edit'] ?? '');
$editB = $editT !== '' ? smBangFind($editT, $me, $all ? 'all' : 'mine') : null;
$total = smBangSysCount();
?>
    <section class="panel">
        <h2><?= h(t('Bangs suchen')) ?></h2>
        <p class="muted small"><?= h(t('Ein Bang springt direkt zur Suche einer anderen Seite, ohne eine Suche bei deinen Suchdiensten zu verbrauchen: „!kürzel Suchbegriff“. Mitgeliefert sind %s Bangs aus der Liste von Kagi plus deutsche Extras.', number_format($total, 0, ',', '.'))) ?></p>
        <form method="get" action="<?= h(smBase()) ?>" class="bangfind" role="search" data-bangfind>
            <input type="hidden" name="a" value="settings"><input type="hidden" name="tab" value="bangs"><?php if ($all): ?><input type="hidden" name="for" value="all"><?php endif; ?>
            <input type="search" name="bq" value="<?= h($bq) ?>" placeholder="<?= h(t('Kürzel, Name oder Domain, z. B. wiki, !yt, amazon')) ?>" aria-label="<?= h(t('Bangs suchen')) ?>"
                   autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="60">
            <select name="bf" aria-label="<?= h(t('Anzeigen')) ?>">
<?php foreach (['' => t('Alle'), 'own' => $all ? t('Für alle angelegt oder geändert') : t('Eigene und geänderte'), 'off' => t('Gelöschte'), 'dead' => t('Nicht erreichbar')] as $k => $v): ?>
                <option value="<?= h($k) ?>"<?= $k === $bf ? ' selected' : '' ?>><?= h($v) ?></option>
<?php endforeach; ?>
            </select>
            <button type="submit" class="btn"><?= h(t('Suchen')) ?></button>
        </form>
        <div class="banglist" data-bang-results data-frag="<?= h(smUrl(['a' => 'settings', 'tab' => 'bangs', 'for' => $all ? 'all' : null, 'frag' => 1])) ?>">
<?php require __DIR__ . '/_banglist.php'; ?>
        </div>
    </section>

    <section class="panel" id="bang-form">
        <h2 data-bang-form-title data-new="<?= h(t('Neues Bang anlegen')) ?>" data-edit="<?= h(t('Bang bearbeiten')) ?>"><?= h($editB ? t('Bang bearbeiten') : t('Neues Bang anlegen')) ?></h2>
        <form method="post" action="<?= h($burl()) ?>" class="form" data-busy data-bang-form>
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="bang_save"><input type="hidden" name="orig" value="<?= h($editB['t'] ?? '') ?>">
            <div class="form__row form__row--bang">
                <label class="field"><span><?= h(t('Kürzel')) ?></span><input type="text" name="t" required maxlength="25" placeholder="duden" value="<?= h($editB['t'] ?? '') ?>" autocomplete="off" autocapitalize="off" spellcheck="false"></label>
                <label class="field"><span><?= h(t('Name')) ?></span><input type="text" name="n" maxlength="60" placeholder="Duden" value="<?= h($editB['n'] ?? '') ?>"></label>
            </div>
            <label class="field"><span><?= h(t('Adresse')) ?></span><input type="text" name="u" required maxlength="800" placeholder="https://www.duden.de/suchen/dudenonline/{q}" value="<?= h($editB['u'] ?? '') ?>" autocomplete="off" autocapitalize="off" spellcheck="false">
                <small class="muted"><?= h(t('{q} wird durch den Suchbegriff ersetzt. Tipp: Auf der Zielseite etwas suchen und die Adresse kopieren, dann den Suchbegriff durch {q} ersetzen.')) ?></small></label>
            <div class="form__actions">
                <button type="submit" class="btn btn--primary"><?= h(t('Bang speichern')) ?></button>
                <a class="btn" href="<?= h($burl()) ?>#bang-form" data-bang-new<?= $editB ? '' : ' hidden' ?>><?= h(t('Abbrechen')) ?></a>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2><?= h(t('Grundzustand')) ?></h2>
        <p class="muted small"><?= h($all
            ? t('Für alle: %d mitgelieferte gelöscht, %d geändert, %d neu angelegt.', count($mineOff), $nEdited, $nOwn)
            : t('Bei dir: %d mitgelieferte gelöscht, %d geändert, %d selbst angelegt.', count($mineOff), $nEdited, $nOwn)) ?></p>
        <form method="post" action="<?= h($burl()) ?>" data-busy data-confirm="<?= h(t('Alle gelöschten mitgelieferten Bangs zurückholen und geänderte auf das Original zurücksetzen? Selbst angelegte bleiben.')) ?>">
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="bang_restore">
            <button type="submit" class="btn"<?= count($mineOff) + $nEdited === 0 ? ' disabled' : '' ?>><?= h(t('Mitgelieferte wiederherstellen')) ?></button>
            <small class="muted"><?= h(t('Selbst angelegte Bangs bleiben dabei erhalten.')) ?></small>
        </form>
    </section>
<?php if ($all):
    $upd = json_decode(smSetting('bang_upd'), true) ?: [];
    [$chk, $chkAll, $dead] = smBangCheckStats(); ?>

    <section class="panel">
        <h2><?= h(t('Liste und Erreichbarkeit')) ?></h2>
        <p class="small"><?= h(t('Stand der Liste: %s, %d Bangs (%s).', !empty($upd['at']) ? date('d.m.Y H:i', (int)$upd['at']) : '–', (int)($upd['n'] ?? $total),
            ($upd['src'] ?? '') === 'online' ? t('frisch von Kagi geholt') : t('mitgelieferte Datei'))) ?>
<?php if (!empty($upd['add']) || !empty($upd['del'])): ?> <?= h(t('Zuletzt %d neu, %d weggefallen.', (int)$upd['add'], (int)$upd['del'])) ?><?php endif; ?></p>
<?php if (!empty($upd['err'])): ?>
        <p class="small error-text"><?= h(t('Letzter Versuch fehlgeschlagen: %s', (string)$upd['err'])) ?></p>
<?php endif; ?>
        <p class="small"><?= h(t('Erreichbarkeit: %d von %d Domains geprüft, %d gerade nicht erreichbar.', $chk, $chkAll, $dead)) ?>
<?php if ($dead): ?> <a href="<?= h(smUrl(['a' => 'settings', 'tab' => 'bangs', 'for' => 'all', 'bf' => 'dead'])) ?>"><?= h(t('Anzeigen')) ?></a><?php endif; ?></p>
        <form method="post" action="<?= h($burl()) ?>" class="form" data-busy>
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="bang_cfg">
            <label class="check"><input type="checkbox" name="bang_auto" value="1"<?= smSetting('bang_auto') === '1' ? ' checked' : '' ?>> <?= h(t('Liste einmal pro Woche von Kagi (GitHub) aktualisieren')) ?></label>
            <label class="check"><input type="checkbox" name="bang_check" value="1"<?= smSetting('bang_check') === '1' ? ' checked' : '' ?>> <?= h(t('Zielseiten nach und nach auf Erreichbarkeit prüfen')) ?></label>
            <small class="muted"><?= h(t('Beides läuft im Hintergrund, während jemand die Suchmaschine benutzt, und verbraucht keine Suchanfragen. Geprüft wird nur, ob die Startseite der Domain antwortet.')) ?></small>
            <label class="check"><input type="checkbox" name="bang_ref" value="1"<?= smSetting('bang_ref') === '1' ? ' checked' : '' ?>> <?= h(t('Beim Weiterleiten die Adresse dieser Suchmaschine als Herkunft mitschicken')) ?></label>
            <small class="muted"><?= h(t('Wie bei DuckDuckGo und Kagi: Die Zielseite sieht nur, von welcher Suchmaschine du kommst, nie den Suchbegriff. Manche Seiten (z. B. Reddit) sperren Aufrufe ganz ohne Herkunft als vermeintliche Bots. Aus: keinerlei Herkunft.')) ?></small>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Speichern')) ?></button></div>
        </form>
        <div class="form__actions">
            <form method="post" action="<?= h($burl()) ?>" data-busy><?= smCsrfField($me) ?><input type="hidden" name="do" value="bang_refresh"><button type="submit" class="btn"><?= h(t('Liste jetzt aktualisieren')) ?></button></form>
            <form method="post" action="<?= h($burl()) ?>" data-busy><?= smCsrfField($me) ?><input type="hidden" name="do" value="bang_checknow"><button type="submit" class="btn"><?= h(t('Jetzt 60 Domains prüfen')) ?></button></form>
        </div>
    </section>
<?php endif; ?>
