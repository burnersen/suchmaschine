<?php defined('SM_VERSION') || exit;
$surl = function (string $t, array $more = []) { return smUrl(['a' => 'settings', 'tab' => $t !== 'search' ? $t : null] + $more); };
$lists = smDomainLists($me);
?>
<main class="page">
    <h1 class="page__title"><?= h(t('Einstellungen')) ?></h1>
    <p class="muted small page__lead"><?= h(t('Alles hier gilt nur für dein Konto. Andere Konten sehen deine Listen, Bangs und Linsen nicht.')) ?></p>

    <nav class="subnav">
<?php foreach (smSettingsTabs() as $k => $v): ?>
        <a href="<?= h($surl($k)) ?>"<?= $k === $tab ? ' class="is-active" aria-current="page"' : '' ?>><?= h($v) ?></a>
<?php endforeach; ?>
    </nav>

<?php if ($tab === 'search'): ?>
    <section class="panel">
        <h2><?= h(t('Suche')) ?></h2>
        <form method="post" action="<?= h($surl('search')) ?>" class="form" data-busy>
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
            <fieldset class="field"><legend><?= h(t('Weitere Treffer')) ?></legend>
                <div class="seg">
<?php foreach (['pages' => t('Seitenweise'), 'endless' => t('Endlos scrollen')] as $k => $v): ?>
                    <label><input type="radio" name="scroll" value="<?= $k ?>"<?= $k === $prefs['scroll'] ? ' checked' : '' ?>><span><?= h($v) ?></span></label>
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
        <h2><?= h(t('Als Standardsuche im Browser')) ?></h2>
        <p><?= h(t('Firefox: in der Adressleiste auf das Lupen-Symbol mit Plus klicken. Chrome und Edge: Einstellungen → Suchmaschinen → hinzufügen, mit dieser Adresse:')) ?></p>
        <p><code class="copy" data-copy title="<?= h(t('Klicken zum Kopieren')) ?>" data-done="<?= h(t('Kopiert')) ?>"><?= h(smOrigin()) ?>?q=%s</code></p>
        <p class="muted small"><?= h(t('Bangs funktionieren auch dort, z. B. „!w Berlin“ direkt in der Adressleiste.')) ?></p>
    </section>

<?php elseif ($tab === 'domains'): ?>
    <section class="panel">
        <h2><?= h(t('Eigene Domain-Listen')) ?></h2>
        <p class="muted small"><?= h(t('Eine Domain pro Zeile, z. B. example.org. Unterdomains zählen mit. Die Listen wirken erst auf deine eigene Trefferliste, die Anfrage an Brave bleibt gleich. Schneller geht es über das Menü ⋮ an jedem Treffer.')) ?></p>
        <form method="post" action="<?= h($surl('domains')) ?>" class="form" data-busy>
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="domains">
<?php foreach (['raise' => [t('Hochstufen (Favoriten)'), t('Diese Seiten rücken nach oben und bekommen einen Stern.')],
                'lower' => [t('Herabstufen'), t('Diese Seiten rutschen ans Ende der Trefferliste.')],
                'block' => [t('Blockieren'), t('Diese Seiten werden gar nicht mehr angezeigt.')]] as $k => $v): ?>
            <label class="field"><span><?= h($v[0]) ?> <small class="muted">(<?= count($lists[$k]) ?>)</small></span>
                <textarea name="<?= $k ?>" rows="<?= max(3, min(12, count($lists[$k]) + 1)) ?>" spellcheck="false" autocapitalize="off" placeholder="example.org"><?= h(implode("\n", $lists[$k])) ?></textarea>
                <small class="muted"><?= h($v[1]) ?></small></label>
<?php endforeach; ?>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Speichern')) ?></button></div>
        </form>
    </section>

<?php elseif ($tab === 'bangs'): $own = smCleanBangs($me['prefs']['bangs'] ?? []); $off = array_flip((array)($me['prefs']['bang_off'] ?? [])); ?>
    <section class="panel">
        <h2><?= h(t('Eigene Bangs')) ?></h2>
        <p class="muted small"><?= h(t('Ein Bang springt direkt zu einer anderen Seite, ohne eine Suche bei Brave zu verbrauchen. Einfach „!kürzel Suchbegriff“ eingeben. Ein eigenes Bang mit gleichem Kürzel ersetzt das mitgelieferte.')) ?></p>
<?php if ($own): ?>
        <ul class="list">
<?php foreach ($own as $b): ?>
            <li class="list__row">
                <code>!<?= h($b['t']) ?></code>
                <span class="list__main"><strong><?= h($b['n']) ?></strong><br><small class="muted list__url"><?= h($b['u']) ?></small></span>
                <form method="post" action="<?= h($surl('bangs')) ?>" data-confirm="<?= h(t('Bang !%s löschen?', $b['t'])) ?>"><?= smCsrfField($me) ?><input type="hidden" name="do" value="bang_del"><input type="hidden" name="t" value="<?= h($b['t']) ?>">
                    <button type="submit" class="btn btn--small btn--danger"><?= h(t('Löschen')) ?></button></form>
            </li>
<?php endforeach; ?>
        </ul>
<?php else: ?>
        <p class="muted"><?= h(t('Noch keine eigenen Bangs.')) ?></p>
<?php endif; ?>
        <form method="post" action="<?= h($surl('bangs')) ?>" class="form" data-busy>
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="bang_add">
            <div class="form__row form__row--bang">
                <label class="field"><span><?= h(t('Kürzel')) ?></span><input type="text" name="t" required maxlength="25" placeholder="dict" autocomplete="off" autocapitalize="off" spellcheck="false"></label>
                <label class="field"><span><?= h(t('Name')) ?></span><input type="text" name="n" maxlength="40" placeholder="Duden"></label>
            </div>
            <label class="field"><span><?= h(t('Adresse')) ?></span><input type="text" name="u" required maxlength="500" placeholder="https://www.duden.de/suchen/dudenonline/{q}" autocomplete="off" autocapitalize="off" spellcheck="false">
                <small class="muted"><?= h(t('{q} wird durch den Suchbegriff ersetzt. Tipp: Auf der Zielseite etwas suchen und die Adresse kopieren, dann den Suchbegriff durch {q} ersetzen.')) ?></small></label>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Bang speichern')) ?></button></div>
        </form>
    </section>
    <section class="panel">
        <h2><?= h(t('Mitgelieferte Bangs')) ?></h2>
        <p class="muted small"><?= h(t('Was du hier ausschaltest, gilt nur für dich. Immer dabei: !i, !n und !v wechseln zu Bildern, News und Videos.')) ?></p>
        <form method="post" action="<?= h($surl('bangs')) ?>" class="form" data-busy>
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="bang_builtin">
            <div class="checks">
<?php foreach (smBuiltinBangs() as $k => $b): ?>
                <label class="check" title="<?= h($b[1]) ?>"><input type="checkbox" name="on[]" value="<?= h($k) ?>"<?= isset($off[$k]) ? '' : ' checked' ?>> <code>!<?= h($k) ?></code> <?= h($b[0]) ?></label>
<?php endforeach; ?>
            </div>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Speichern')) ?></button></div>
        </form>
    </section>

<?php elseif ($tab === 'lenses'): $own = smCleanLenses($me['prefs']['lenses'] ?? []); $off = array_flip((array)($me['prefs']['lens_off'] ?? [])); ?>
    <section class="panel">
        <h2><?= h(t('Eigene Linsen')) ?></h2>
        <p class="muted small"><?= h(t('Eine Linse beschränkt die Suche auf ausgewählte Seiten oder hängt Operatoren an. Auswählen kannst du sie auf der Ergebnisseite über „Linse“.')) ?></p>
<?php if ($own): ?>
        <ul class="list">
<?php foreach ($own as $l): ?>
            <li class="list__row">
                <span class="list__main"><strong><?= h($l['n']) ?></strong><br><small class="muted list__url"><?= h(smLensQuery([$l['n'], $l['s'], $l['x']])) ?></small></span>
                <a class="btn btn--small" href="<?= h($surl('lenses', ['edit' => $l['id']])) ?>#lens-form"><?= h(t('Bearbeiten')) ?></a>
                <form method="post" action="<?= h($surl('lenses')) ?>" data-confirm="<?= h(t('Linse „%s“ löschen?', $l['n'])) ?>"><?= smCsrfField($me) ?><input type="hidden" name="do" value="lens_del"><input type="hidden" name="id" value="<?= h($l['id']) ?>">
                    <button type="submit" class="btn btn--small btn--danger"><?= h(t('Löschen')) ?></button></form>
            </li>
<?php endforeach; ?>
        </ul>
<?php else: ?>
        <p class="muted"><?= h(t('Noch keine eigenen Linsen.')) ?></p>
<?php endif; ?>
    </section>
    <section class="panel" id="lens-form">
        <h2><?= h($edit ? t('Linse bearbeiten') : t('Neue Linse')) ?></h2>
        <form method="post" action="<?= h($surl('lenses')) ?>" class="form" data-busy>
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="lens_save"><input type="hidden" name="id" value="<?= h($edit['id'] ?? '') ?>">
            <label class="field"><span><?= h(t('Name')) ?></span><input type="text" name="n" required maxlength="40" value="<?= h($edit['n'] ?? '') ?>" placeholder="<?= h(t('z. B. Rezepte')) ?>"></label>
            <label class="field"><span><?= h(t('Domains (eine pro Zeile, höchstens %d)', SM_LENS_SITES)) ?></span>
                <textarea name="s" rows="4" spellcheck="false" autocapitalize="off" placeholder="chefkoch.de&#10;lecker.de"><?= h(implode("\n", $edit['s'] ?? [])) ?></textarea></label>
            <label class="field"><span><?= h(t('Zusätzliche Operatoren (optional)')) ?></span><input type="text" name="x" maxlength="150" value="<?= h($edit['x'] ?? '') ?>" placeholder="NOT site:pinterest.com lang:de" autocomplete="off" autocapitalize="off" spellcheck="false">
                <small class="muted"><?= t('Alles, was auch im Suchfeld geht, siehe <a href="%s">Hilfe</a>.', h(smUrl(['a' => 'help']))) ?></small></label>
            <div class="form__actions">
                <button type="submit" class="btn btn--primary"><?= h(t('Linse speichern')) ?></button>
<?php if ($edit): ?><a class="btn btn--ghost" href="<?= h($surl('lenses')) ?>"><?= h(t('Abbrechen')) ?></a><?php endif; ?>
            </div>
        </form>
    </section>
    <section class="panel">
        <h2><?= h(t('Mitgelieferte Linsen')) ?></h2>
        <form method="post" action="<?= h($surl('lenses')) ?>" class="form" data-busy>
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="lens_builtin">
            <div class="checks checks--wide">
<?php foreach (smBuiltinLenses() as $k => $l): ?>
                <label class="check" title="<?= h(smLensQuery($l)) ?>"><input type="checkbox" name="on[]" value="<?= h($k) ?>"<?= isset($off[$k]) ? '' : ' checked' ?>> <?= h($l[0]) ?> <small class="muted"><?= h(implode(', ', $l[1])) ?></small></label>
<?php endforeach; ?>
            </div>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Speichern')) ?></button></div>
        </form>
    </section>

<?php else: ?>
    <section class="panel">
        <h2><?= h(t('Einstellungen sichern')) ?></h2>
        <p class="muted small"><?= h(t('Lädt alle deine Einstellungen als Datei herunter: Suche, Domain-Listen, Bangs und Linsen. Damit holst du alles nach einem Umzug oder Absturz wieder zurück, auch auf einer anderen Installation.')) ?></p>
        <div class="form__actions"><a class="btn btn--primary" href="<?= h(smUrl(['a' => 'export'])) ?>" download><?= h(t('Sicherung herunterladen')) ?></a></div>
    </section>
    <section class="panel">
        <h2><?= h(t('Sicherung einspielen')) ?></h2>
        <form method="post" action="<?= h($surl('account')) ?>" class="form" data-busy enctype="multipart/form-data" data-confirm="<?= h(t('Deine jetzigen Einstellungen werden durch die Sicherung ersetzt. Weiter?')) ?>">
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="import">
            <label class="field"><span><?= h(t('Sicherungsdatei (.json)')) ?></span><input type="file" name="file" accept="application/json,.json" required></label>
            <div class="form__actions"><button type="submit" class="btn"><?= h(t('Einspielen')) ?></button></div>
        </form>
    </section>
    <section class="panel">
        <h2><?= h(t('Passwort ändern')) ?></h2>
        <form method="post" action="<?= h($surl('account')) ?>" class="form" data-busy>
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
<?php endif; ?>
</main>
