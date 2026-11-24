<?php defined('PRIVATE_DIR') || exit; ?>
<section class="card card--narrow">
    <h1 class="card__title">Mein Konto</h1>
    <p class="muted">Eingeloggt als <strong><?= e($user['name']) ?></strong><?= !empty($user['admin']) ? ' (Admin)' : '' ?>.</p>
    <h2 class="card__sub">Passwort ändern</h2>
    <form method="post" action="<?= e(url(['a' => 'account'])) ?>" class="form" data-busy-form>
        <?= csrf_field() ?>
        <input type="text" name="username" value="<?= e($user['name']) ?>" autocomplete="username" hidden>
        <label>Aktuelles Passwort
            <input type="password" name="old" autocomplete="current-password" required>
        </label>
        <label>Neues Passwort <span class="muted">(mind. 8 Zeichen)</span>
            <input type="password" name="password" autocomplete="new-password" minlength="8" required>
        </label>
        <label>Neues Passwort wiederholen
            <input type="password" name="password2" autocomplete="new-password" minlength="8" required>
        </label>
        <button type="submit" class="btn btn--primary">Speichern</button>
    </form>
    <p class="muted small">Tipp: Im Browser kannst du diese Suche als Standardsuchmaschine einstellen (Adressleiste, Rechtsklick bzw. Einstellungen → Suchmaschinen).</p>
</section>
