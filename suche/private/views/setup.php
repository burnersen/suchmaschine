<?php defined('PRIVATE_DIR') || exit; ?>
<section class="card card--narrow">
    <h1 class="card__title"><span class="brand__mark" aria-hidden="true"></span> Einrichtung</h1>
    <p class="muted">Noch gibt es keinen Account. Wer sich jetzt anlegt, wird <strong>Admin</strong> und kann danach weitere Nutzer hinzufügen.</p>
    <?php if ($error): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(base_path()) ?>" class="form" data-busy-form>
        <?= csrf_field() ?>
        <label>Benutzername
            <input type="text" name="name" value="<?= e($name) ?>" autocomplete="username" autocapitalize="off"
                   pattern="[A-Za-z0-9._\-]{2,32}" title="2 bis 32 Zeichen: Buchstaben, Zahlen, . _ -" required autofocus>
        </label>
        <label>Passwort <span class="muted">(mind. 8 Zeichen)</span>
            <input type="password" name="password" autocomplete="new-password" minlength="8" required>
        </label>
        <label>Passwort wiederholen
            <input type="password" name="password2" autocomplete="new-password" minlength="8" required>
        </label>
        <button type="submit" class="btn btn--primary">Admin-Account anlegen</button>
    </form>
</section>
