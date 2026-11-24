<?php defined('PRIVATE_DIR') || exit; ?>
<section class="card card--narrow">
    <h1 class="card__title"><span class="brand__mark" aria-hidden="true"></span> <?= e(cfg('site_name')) ?></h1>
    <p class="muted">Bitte einloggen.</p>
    <?php if ($error): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(current_url()) ?>" class="form" data-busy-form>
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="login">
        <label>Benutzername
            <input type="text" name="name" value="<?= e($name) ?>" autocomplete="username" autocapitalize="off" required autofocus>
        </label>
        <label>Passwort
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit" class="btn btn--primary">Einloggen</button>
    </form>
</section>
