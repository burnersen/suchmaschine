<?php defined('SM_VERSION') || exit; ?>
<main class="page">
    <h1 class="page__title"><?= h(t('Einstellungen')) ?></h1>
    <section class="panel">
        <h2><?= h(t('Suche')) ?></h2>
        <form method="post" action="<?= h(smUrl(['a' => 'settings'])) ?>" class="form" data-busy>
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="prefs">
            <div class="form__row">
                <label class="field"><span><?= h(t('Region')) ?></span>
                    <select name="country"><?php foreach (smCountries() as $k => $v): ?><option value="<?= h($k) ?>"<?= $k === $prefs['country'] ? ' selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span><?= h(t('Sprache der Ergebnisse')) ?></span>
                    <select name="search_lang"><?php foreach (smSearchLangs() as $k => $v): ?><option value="<?= h($k) ?>"<?= $k === $prefs['search_lang'] ? ' selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
            </div>
            <fieldset class="field"><legend><?= h(t('SafeSearch')) ?></legend>
                <div class="seg">
<?php foreach (['strict' => t('Streng'), 'moderate' => t('Mittel'), 'off' => t('Aus')] as $k => $v): ?>
                    <label><input type="radio" name="safesearch" value="<?= $k ?>"<?= $k === $prefs['safesearch'] ? ' checked' : '' ?>><span><?= h($v) ?></span></label>
<?php endforeach; ?>
                </div>
            </fieldset>
            <label class="switch"><input type="checkbox" name="newtab" value="1"<?= $prefs['newtab'] ? ' checked' : '' ?>><span class="switch__ui"></span><span><?= h(t('Treffer in neuem Tab öffnen')) ?></span></label>
            <label class="switch"><input type="checkbox" name="history" value="1"<?= $prefs['history'] ? ' checked' : '' ?>><span class="switch__ui"></span>
                <span><?= h(t('Letzte Suchen als Vorschläge zeigen')) ?><br><small class="muted"><?= h(t('Der Verlauf bleibt nur in diesem Browser, der Server merkt sich nichts.')) ?></small></span></label>
            <div class="form__actions">
                <button type="submit" class="btn btn--primary"><?= h(t('Speichern')) ?></button>
                <button type="button" class="btn" data-clear-history data-done="<?= h(t('Verlauf gelöscht')) ?>"><?= h(t('Verlauf auf diesem Gerät löschen')) ?></button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2><?= h(t('Passwort ändern')) ?></h2>
        <form method="post" action="<?= h(smUrl(['a' => 'settings'])) ?>" class="form" data-busy>
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="pass">
            <input type="text" name="username" value="<?= h($me['name']) ?>" autocomplete="username" hidden>
            <label class="field"><span><?= h(t('Bisheriges Passwort')) ?></span><input type="password" name="old" autocomplete="current-password" required></label>
            <div class="form__row">
                <label class="field"><span><?= h(t('Neues Passwort')) ?></span><input type="password" name="pass" autocomplete="new-password" minlength="8" required></label>
                <label class="field"><span><?= h(t('Neues Passwort wiederholen')) ?></span><input type="password" name="pass2" autocomplete="new-password" minlength="8" required></label>
            </div>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Passwort ändern')) ?></button></div>
        </form>
    </section>

    <section class="panel">
        <h2><?= h(t('Als Standardsuche im Browser')) ?></h2>
        <p><?= h(t('Firefox: in der Adressleiste auf das Lupen-Symbol mit Plus klicken. Chrome und Edge: Einstellungen → Suchmaschinen → hinzufügen, mit dieser Adresse:')) ?></p>
        <p><code class="copy" data-copy title="<?= h(t('Klicken zum Kopieren')) ?>" data-done="<?= h(t('Kopiert')) ?>"><?= h(smOrigin()) ?>?q=%s</code></p>
    </section>
</main>
