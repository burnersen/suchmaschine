<?php defined('SM_VERSION') || exit;
/* Login per Token hinter dem # (…/?a=login#token=…): das Skript in app.js schickt ihn mit diesem Formular, ohne dass er im Server-Protokoll landet */ ?>
<form method="post" action="<?= h(smUrl(['a' => 'tokenlogin'])) ?>" data-token-login hidden><input type="hidden" name="token" value=""></form>
<main class="auth">
    <div class="auth__card">
        <a class="auth__logo" href="<?= h(smBase()) ?>"><?= smLogoHtml(true) ?><span class="auth__name"><?= smSiteNameHtml() ?></span></a>
        <h1 class="auth__title"><?= h(t('Anmelden')) ?></h1>
<?php if ($error !== ''): ?><div class="alert alert--error"><?= h($error) ?></div><?php endif; ?>
        <form method="post" action="<?= h(smUrl(['a' => 'login'])) ?>" class="form" data-busy>
            <input type="hidden" name="next" value="<?= h($next) ?>">
            <label class="field"><span><?= h(t('Name')) ?></span>
                <input type="text" name="name" autocomplete="username" autocapitalize="off" required autofocus value="<?= h((string)($_POST['name'] ?? '')) ?>"></label>
            <label class="field"><span><?= h(t('Passwort')) ?></span>
                <input type="password" name="pass" autocomplete="current-password" required></label>
            <button type="submit" class="btn btn--primary btn--wide"><?= h(t('Anmelden')) ?></button>
        </form>
<?php if (smSetting('public') === '1'): ?>
        <p class="auth__alt"><a href="<?= h(smBase()) ?>"><?= h(t('Ohne Anmeldung suchen')) ?></a></p>
<?php endif; ?>
    </div>
</main>
