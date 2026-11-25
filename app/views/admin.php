<?php defined('SM_VERSION') || exit;
$aurl = function (string $t) { return smUrl(['a' => 'admin', 'tab' => $t]); };
?>
<main class="page">
    <h1 class="page__title"><?= h(t('Verwaltung')) ?></h1>

<?php if ($protect['ok'] === false): ?>
    <div class="alert alert--error">
        <strong><?= h(t('Achtung: %s ist von außen abrufbar!', $protect['leak'])) ?></strong>
        <?= h(t('Der Webserver beachtet die .htaccess-Dateien nicht. Meist liefert nginx die Dateien direkt aus. Bei Plesk (z. B. Netcup): „Apache & nginx-Einstellungen“ öffnen und „Statische Dateien direkt durch nginx bereitstellen“ bzw. „Intelligente Verarbeitung statischer Dateien“ ausschalten. Danach hier neu prüfen.')) ?>
    </div>
<?php endif; ?>

    <nav class="subnav">
<?php foreach (smAdminTabs() as $k => $v): ?>
        <a href="<?= h($aurl($k)) ?>"<?= $k === $tab ? ' class="is-active" aria-current="page"' : '' ?>><?= h($v) ?></a>
<?php endforeach; ?>
    </nav>

<?php if ($tab === 'users'): ?>
    <section class="panel">
        <h2><?= h(t('Konten')) ?></h2>
        <ul class="users">
<?php foreach ($users as $u): $self = (int)$u['id'] === $me['id']; ?>
            <li class="users__row">
                <span class="avatar"><?= h(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></span>
                <span class="users__name"><strong><?= h($u['name']) ?></strong><?= $self ? ' <span class="muted">(' . h(t('du')) . ')</span>' : '' ?><br>
                    <small class="muted"><?= (int)$u['is_admin'] ? h(t('Administrator')) : h(t('Benutzer')) ?> · <?= h(t('seit %s', date(smLang() === 'de' ? 'd.m.Y' : 'Y-m-d', (int)$u['created_at']))) ?></small></span>
                <span class="users__actions">
                    <details class="pop">
                        <summary class="btn btn--small"><?= h(t('Passwort')) ?></summary>
                        <form method="post" action="<?= h($aurl('users')) ?>" class="pop__panel">
                            <?= smCsrfField($me) ?><input type="hidden" name="do" value="pass"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <input type="password" name="pass" minlength="8" required autocomplete="new-password" placeholder="<?= h(t('Neues Passwort')) ?>">
                            <button type="submit" class="btn btn--primary btn--small"><?= h(t('Setzen')) ?></button>
                        </form>
                    </details>
<?php if (smProvOrder()): $quser = (int)$u['id']; require __DIR__ . '/_quota.php'; endif; ?>
                    <form method="post" action="<?= h($aurl('users')) ?>"><?= smCsrfField($me) ?><input type="hidden" name="do" value="admin"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <button type="submit" class="btn btn--small"><?= (int)$u['is_admin'] ? h(t('Admin entziehen')) : h(t('Zum Admin machen')) ?></button></form>
<?php if (!$self): ?>
                    <form method="post" action="<?= h($aurl('users')) ?>" data-confirm="<?= h(t('Das Konto „%s“ wirklich löschen?', $u['name'])) ?>"><?= smCsrfField($me) ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <button type="submit" class="btn btn--small btn--danger"><?= h(t('Löschen')) ?></button></form>
<?php endif; ?>
                </span>
            </li>
<?php endforeach; ?>
        </ul>
    </section>
<?php if (smProvOrder()): ?>
    <section class="panel">
        <h2><?= h(t('Kontingente')) ?></h2>
        <p class="muted small"><?= h(t('Pro Konto lässt sich festlegen, welche Suchdienste es nutzen darf und wie viele Credits es pro Abrechnungszeitraum bekommt (Knopf „Kontingent“ in der Liste oben). Ist ein Kontingent aufgebraucht, weicht die Suche auf andere erlaubte Dienste aus.')) ?></p>
<?php if (smSetting('public') === '1'): ?>
        <div class="users__row users__row--anon"><span class="avatar">?</span><span class="users__name"><strong><?= h(t('Besucher ohne Anmeldung')) ?></strong><br><small class="muted"><?= h(t('gemeinsames Kontingent')) ?></small></span>
            <span class="users__actions"><?php $quser = 0; require __DIR__ . '/_quota.php'; ?></span></div>
<?php endif; ?>
        <form method="post" action="<?= h($aurl('users')) ?>" class="form" data-confirm="<?= h(t('Kontingente aller Konten neu setzen?')) ?>">
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="fair">
            <label class="check"><input type="checkbox" name="skip_admins" value="1" checked> <?= h(t('Administratoren ausnehmen (ohne Grenze)')) ?></label>
<?php if (smSetting('public') === '1'): ?><label class="check"><input type="checkbox" name="with_anon" value="1"> <?= h(t('Besucher ohne Anmeldung mitzählen')) ?></label><?php endif; ?>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Gerecht aufteilen')) ?></button></div>
        </form>
        <form method="post" action="<?= h($aurl('users')) ?>" class="form__actions" data-confirm="<?= h(t('Alle Kontingente aufheben?')) ?>"><?= smCsrfField($me) ?><input type="hidden" name="do" value="quota_clear"><button type="submit" class="btn btn--ghost"><?= h(t('Alle Kontingente aufheben')) ?></button></form>
    </section>
<?php endif; ?>
    <section class="panel">
        <h2><?= h(t('Neues Konto')) ?></h2>
        <form method="post" action="<?= h($aurl('users')) ?>" class="form" data-busy>
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="create">
            <div class="form__row">
                <label class="field"><span><?= h(t('Name')) ?></span><input type="text" name="name" required maxlength="30" autocomplete="off" autocapitalize="off"></label>
                <label class="field"><span><?= h(t('Passwort')) ?></span><input type="password" name="pass" minlength="8" required autocomplete="new-password" placeholder="<?= h(t('mindestens 8 Zeichen')) ?>"></label>
            </div>
            <label class="switch"><input type="checkbox" name="admin" value="1"><span class="switch__ui"></span><span><?= h(t('Administrator (darf Konten und Einstellungen ändern)')) ?></span></label>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Konto anlegen')) ?></button></div>
        </form>
    </section>

<?php elseif ($tab === 'config'): ?>
    <form method="post" action="<?= h($aurl('config')) ?>" class="form" data-busy enctype="multipart/form-data">
        <?= smCsrfField($me) ?><input type="hidden" name="do" value="config">
        <section class="panel">
            <h2><?= h(t('Aussehen')) ?></h2>
            <label class="field"><span><?= h(t('Name der Suchmaschine')) ?></span><input type="text" name="site_name" maxlength="40" value="<?= h(smSetting('site_name')) ?>" placeholder="<?= h(t('Suchmaschine ohne Werbung')) ?>">
                <small class="muted"><?= h(t('Leer lassen für „Suchmaschine ohne Werbung“ (auf Englisch automatisch übersetzt).')) ?></small></label>
            <div class="field"><span><?= h(t('Logo')) ?></span>
                <div class="logo-edit"<?php if (smLogoUrl() !== '' && !is_file(smAppIconFile())): ?> data-appicon-missing data-logo-src="<?= h(smLogoUrl()) ?>" data-csrf="<?= h($me['csrf']) ?>" data-post="<?= h($aurl('config')) ?>"<?php endif; ?>>
                    <input type="hidden" name="logo_png" value="" data-logo-png>
                    <span class="logo-edit__preview"><?= smLogoHtml(true) ?></span>
                    <span class="logo-edit__controls">
                        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml">
                        <small class="muted"><?= h(t('PNG, JPG, WebP, GIF oder SVG, höchstens 1 MB. Am besten quadratisch.')) ?> <?= h(t('Daraus entsteht auch das Symbol für den Startbildschirm.')) ?></small>
<?php if (smLogoUrl() !== ''): ?>
                        <label class="check"><input type="checkbox" name="logo_remove" value="1"> <?= h(t('Eigenes Logo entfernen')) ?></label>
<?php endif; ?>
                    </span>
                </div>
            </div>
            <fieldset class="field"><legend><?= h(t('Farbe')) ?></legend>
                <div class="swatches">
<?php foreach (smAccents() as $k => $v): ?>
                    <label class="swatch swatch--<?= h($k) ?>" title="<?= h($v) ?>"><input type="radio" name="accent" value="<?= h($k) ?>"<?= $k === smAccent() ? ' checked' : '' ?>><span></span><small><?= h($v) ?></small></label>
<?php endforeach; ?>
                </div>
            </fieldset>
        </section>
        <section class="panel">
            <h2><?= h(t('Allgemein')) ?></h2>
            <div class="form__row">
                <label class="field"><span><?= h(t('Treffer pro Seite')) ?></span><input type="number" name="per_page" min="5" max="20" value="<?= (int)smSetting('per_page') ?>"></label>
            </div>
            <label class="switch"><input type="checkbox" name="img_proxy" value="1"<?= smSetting('img_proxy') === '1' ? ' checked' : '' ?>><span class="switch__ui"></span>
                <span><?= h(t('Bilder in voller Auflösung über diesen Server laden')) ?><br><small class="muted"><?= h(t('Für die große Bildvorschau und den Vollbild-Modus holt der Server das Originalbild und reicht es weiter. Kostet keine Suche bei Brave, nur etwas Datenverkehr. Die fremde Seite sieht dabei nur den Server, nicht den Besucher. Aus: nur Braves Vorschaubilder.')) ?></small></span></label>
            <label class="switch"><input type="checkbox" name="shortener" value="1"<?= smSetting('shortener') === '1' ? ' checked' : '' ?>><span class="switch__ui"></span>
                <span><?= h(t('Kurzlinks erlauben')) ?><br><small class="muted"><?= h(t('Angemeldete Konten können unter Werkzeuge eigene Kurzlinks (…/?s=abc123) anlegen.')) ?></small></span></label>
            <label class="field field--short"><span><?= h(t('Wetter: Standardquelle')) ?></span>
                <select name="weather_src"><?php foreach (smWeatherSources() as $k => $v): ?><option value="<?= h($k) ?>"<?= $k === smSetting('weather_src') ? ' selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
        </section>
        <section class="panel">
            <h2><?= h(t('Voreinstellungen für alle')) ?></h2>
            <p class="muted small"><?= h(t('Jedes Konto kann das unter Einstellungen für sich ändern.')) ?></p>
            <div class="form__row">
                <label class="field"><span><?= h(t('Region')) ?></span>
                    <select name="country"><?php foreach (smCountries() as $k => $v): ?><option value="<?= h($k) ?>"<?= $k === smSetting('country') ? ' selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span><?= h(t('Sprache der Ergebnisse')) ?></span>
                    <select name="search_lang"><?php foreach (smSearchLangs() as $k => $v): ?><option value="<?= h($k) ?>"<?= $k === smSetting('search_lang') ? ' selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
            </div>
            <fieldset class="field"><legend><?= h(t('SafeSearch')) ?></legend>
                <div class="seg">
<?php foreach (['strict' => t('Streng'), 'moderate' => t('Mittel'), 'off' => t('Aus')] as $k => $v): ?>
                    <label><input type="radio" name="safesearch" value="<?= $k ?>"<?= $k === smSetting('safesearch') ? ' checked' : '' ?>><span><?= h($v) ?></span></label>
<?php endforeach; ?>
                </div>
            </fieldset>
            <fieldset class="field"><legend><?= h(t('Weitere Treffer')) ?></legend>
                <div class="seg">
<?php foreach (['pages' => t('Seitenweise'), 'endless' => t('Endlos scrollen')] as $k => $v): ?>
                    <label><input type="radio" name="scroll" value="<?= $k ?>"<?= $k === (smSetting('scroll') === 'endless' ? 'endless' : 'pages') ? ' checked' : '' ?>><span><?= h($v) ?></span></label>
<?php endforeach; ?>
                </div>
                <small class="muted"><?= h(t('Beim Endlos-Scrollen ist jedes Nachladen eine Suche bei Brave (20 Treffer auf einmal).')) ?></small>
            </fieldset>
        </section>
        <section class="panel">
            <h2><?= h(t('Suchen ohne Anmeldung')) ?></h2>
            <label class="switch"><input type="checkbox" name="public" value="1"<?= smSetting('public') === '1' ? ' checked' : '' ?>><span class="switch__ui"></span>
                <span><?= h(t('Jeder mit der Adresse darf suchen')) ?><br><small class="muted"><?= h(t('Vorsicht: Jede Suche geht von deinem Guthaben ab. Das Monatslimit gilt trotzdem.')) ?></small></span></label>
            <label class="field field--short"><span><?= h(t('Dann höchstens Suchen pro Stunde und Besucher')) ?></span><input type="number" name="public_hour" min="1" max="1000" value="<?= (int)smSetting('public_hour') ?>"></label>
        </section>
        <div class="form__actions form__actions--sticky"><button type="submit" class="btn btn--primary"><?= h(t('Speichern')) ?></button></div>
    </form>
<?php elseif ($tab === 'providers'): require __DIR__ . '/_providers.php'; ?>

<?php elseif ($tab === 'usage'): require __DIR__ . '/_usage.php'; ?>

<?php elseif ($tab === 'backup'): ?>
    <section class="panel">
        <h2><?= h(t('Komplettsicherung')) ?></h2>
        <p class="muted small"><?= h(t('Alles in einer Datei: Einstellungen, alle API-Keys, Konten mit Passwort-Prüfsummen, Login-Tokens, persönliche Einstellungen, Kontingente, Kurzlinks, Verbrauch und Logo. Damit ziehst du die Suchmaschine auf einen anderen Server um oder holst nach einem Absturz alles zurück.')) ?></p>
        <form method="post" action="<?= h($aurl('backup')) ?>" class="form">
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="backup">
            <div class="form__row">
                <label class="field"><span><?= h(t('Passwort für die Sicherung (empfohlen)')) ?></span><input type="password" name="bpass" autocomplete="new-password" minlength="8" placeholder="<?= h(t('mindestens 8 Zeichen')) ?>"></label>
                <label class="field"><span><?= h(t('Passwort wiederholen')) ?></span><input type="password" name="bpass2" autocomplete="new-password"></label>
            </div>
            <p class="muted small"><?= h(t('Mit Passwort wird die Datei mit AES-256-GCM verschlüsselt (Schlüssel per PBKDF2 aus dem Passwort). Ohne Passwort stehen alle Keys und Tokens lesbar darin, dann die Datei bitte gut verwahren. Das Passwort lässt sich nicht wiederherstellen.')) ?></p>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Komplettsicherung herunterladen')) ?></button></div>
        </form>
    </section>
    <section class="panel">
        <h2><?= h(t('Komplettsicherung einspielen')) ?></h2>
        <form method="post" action="<?= h($aurl('backup')) ?>" class="form" data-busy enctype="multipart/form-data" data-confirm="<?= h(t('Alle Daten dieser Installation werden durch die Sicherung ersetzt, alle Geräte werden abgemeldet. Weiter?')) ?>">
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="restore">
            <label class="field"><span><?= h(t('Sicherungsdatei (.json)')) ?></span><input type="file" name="file" accept="application/json,.json" required></label>
            <label class="field"><span><?= h(t('Passwort (nur bei verschlüsselter Sicherung)')) ?></span><input type="password" name="bpass" autocomplete="off"></label>
            <div class="form__actions"><button type="submit" class="btn"><?= h(t('Einspielen')) ?></button></div>
        </form>
    </section>
    <section class="panel">
        <h2><?= h(t('Einstellungen der Installation sichern')) ?></h2>
        <p class="muted small"><?= h(t('Name, Farbe, Limits, Voreinstellungen und auf Wunsch den API-Key als Datei. Konten und ihre persönlichen Einstellungen gehören nicht dazu, die sichert jedes Konto selbst unter Einstellungen → Konto. Für eine komplette Sicherung den Ordner data/ herunterladen.')) ?></p>
        <form method="post" action="<?= h($aurl('backup')) ?>" class="form">
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="export">
            <label class="check"><input type="checkbox" name="with_key" value="1"> <?= h(t('API-Key mitsichern (Datei dann gut verwahren)')) ?></label>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Sicherung herunterladen')) ?></button></div>
        </form>
        <form method="post" action="<?= h($aurl('backup')) ?>" class="form" data-busy enctype="multipart/form-data" data-confirm="<?= h(t('Die Einstellungen der Installation werden durch die Sicherung ersetzt. Weiter?')) ?>">
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="import">
            <label class="field"><span><?= h(t('Sicherungsdatei (.json)')) ?></span><input type="file" name="file" accept="application/json,.json" required></label>
            <div class="form__actions"><button type="submit" class="btn"><?= h(t('Einspielen')) ?></button></div>
        </form>
    </section>

<?php elseif ($tab === 'update'): $info = smUpdInfo(); $prev = smUpdPrev(); $wr = smUpdWritable(); ?>
    <section class="panel">
        <h2><?= h(t('Version')) ?></h2>
        <p><?= h(t('Installiert: %s', SM_VERSION)) ?><?php if ($info): ?> · <?= h(t('Neueste: %s', $info['v'])) ?><?php endif; ?></p>
<?php if (smUpdNewer($info)): ?>
        <div class="alert alert--ok"><?= h(t('Version %s ist verfügbar.', $info['v'])) ?>
            <?php if ($info['page']): ?><a href="<?= h($info['page']) ?>" target="_blank" rel="noopener noreferrer"><?= h(t('Was ist neu?')) ?></a><?php endif; ?></div>
<?php endif; ?>
<?php if ($wr !== ''): ?><div class="alert alert--error"><?= h($wr) ?></div><?php endif; ?>
        <div class="form__actions">
            <form method="post" action="<?= h($aurl('update')) ?>" data-busy><?= smCsrfField($me) ?><input type="hidden" name="do" value="check"><button class="btn" type="submit"><?= h(t('Nach Updates suchen')) ?></button></form>
<?php if (smUpdNewer($info) && $wr === ''): ?>
            <form method="post" action="<?= h($aurl('update')) ?>" data-busy><?= smCsrfField($me) ?><input type="hidden" name="do" value="install"><button class="btn btn--primary" type="submit"><?= h(t('Jetzt aktualisieren')) ?></button></form>
<?php endif; ?>
<?php if ($prev !== ''): ?>
            <form method="post" action="<?= h($aurl('update')) ?>" data-busy data-confirm="<?= h(t('Zurück zu Version %s?', $prev)) ?>"><?= smCsrfField($me) ?><input type="hidden" name="do" value="rollback"><button class="btn btn--ghost" type="submit"><?= h(t('Zurück zu %s', $prev)) ?></button></form>
<?php endif; ?>
        </div>
        <form method="post" action="<?= h($aurl('update')) ?>" class="form" data-autosubmit><?= smCsrfField($me) ?><input type="hidden" name="do" value="auto">
            <label class="switch"><input type="checkbox" name="auto_update" value="1"<?= smSetting('auto_update') === '1' ? ' checked' : '' ?>><span class="switch__ui"></span>
                <span><?= h(t('Updates automatisch einspielen (höchstens einmal am Tag)')) ?></span></label>
            <noscript><button class="btn btn--small" type="submit"><?= h(t('Speichern')) ?></button></noscript>
        </form>
        <p class="muted small"><?= h(t('Updates kommen von GitHub und werden per Prüfsumme kontrolliert. Deine Daten in data/ bleiben unangetastet.')) ?></p>
    </section>
    <section class="panel">
        <h2><?= h(t('Schutz der Daten')) ?></h2>
        <p><?php if ($protect['ok'] === true): ?><?= h(t('Alles dicht: data/ und app/ sind von außen gesperrt.')) ?><?php elseif ($protect['ok'] === null): ?><?= h(t('Der Server kann sich selbst nicht erreichen, der Test war nicht möglich. Bitte von Hand prüfen.')) ?><?php else: ?><strong><?= h(t('Achtung: %s ist von außen abrufbar!', $protect['leak'])) ?></strong><?php endif; ?></p>
        <form method="post" action="<?= h($aurl('update')) ?>" data-busy><?= smCsrfField($me) ?><input type="hidden" name="do" value="protect"><button class="btn" type="submit"><?= h(t('Jetzt prüfen')) ?></button></form>
    </section>
<?php endif; ?>
</main>
