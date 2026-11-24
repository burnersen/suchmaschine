<?php defined('SM_VERSION') || exit; ?>
<main class="auth">
    <div class="auth__card auth__card--wide">
        <div class="auth__logo"><?= smLogoHtml(true) ?><span><?= h(t('Willkommen')) ?></span></div>
        <p class="auth__lead"><?= h(t('Noch zwei Schritte, dann hast du deine eigene Suchmaschine ohne Werbung.')) ?></p>
<?php if ($error !== ''): ?><div class="alert alert--error"><?= h($error) ?></div><?php endif; ?>
        <form method="post" action="<?= h(smUrl(['a' => 'setup'])) ?>" class="form" data-busy>
            <h2 class="form__step"><span>1</span> <?= h(t('Dein Konto')) ?></h2>
            <p class="muted small"><?= h(t('Wer sich hier als Erster einträgt, wird Administrator und kann danach weitere Konten anlegen.')) ?></p>
            <label class="field"><span><?= h(t('Name')) ?></span>
                <input type="text" name="name" autocomplete="username" autocapitalize="off" required autofocus maxlength="30" value="<?= h((string)($_POST['name'] ?? '')) ?>"></label>
            <div class="form__row">
                <label class="field"><span><?= h(t('Passwort')) ?></span>
                    <input type="password" name="pass" autocomplete="new-password" minlength="8" required placeholder="<?= h(t('mindestens 8 Zeichen')) ?>"></label>
                <label class="field"><span><?= h(t('Passwort wiederholen')) ?></span>
                    <input type="password" name="pass2" autocomplete="new-password" minlength="8" required></label>
            </div>

            <h2 class="form__step"><span>2</span> <?= h(t('Brave API-Key')) ?></h2>
            <p class="muted small"><?= t('Kostenlos auf <a href="https://api-dashboard.search.brave.com" target="_blank" rel="noopener noreferrer">api-dashboard.search.brave.com</a>: anmelden, den Plan „Search“ wählen (enthält jeden Monat Guthaben für etwa 1.000 Suchen), unter „API Keys“ einen Key erzeugen und hier einfügen. Automatisches Aufladen dort ausgeschaltet lassen.') ?></p>
            <label class="field"><span><?= h(t('API-Key')) ?></span>
                <input type="password" name="key" autocomplete="off" spellcheck="false" required placeholder="BSA…" data-reveal></label>
            <p class="muted small"><?= h(t('Der Key wird gleich mit einer Testsuche geprüft und bleibt nur auf deinem Server.')) ?></p>
            <button type="submit" class="btn btn--primary btn--wide"><?= h(t('Einrichten')) ?></button>
        </form>
    </div>
</main>
