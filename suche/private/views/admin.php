<?php defined('PRIVATE_DIR') || exit; ?>
<section class="card">
    <h1 class="card__title">Verwaltung</h1>

    <h2 class="card__sub">Nutzer</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>Rolle</th><th>Angelegt</th><th>Aktionen</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): $self = strtolower($u['name']) === strtolower($user['name']); ?>
                <tr>
                    <td><strong><?= e($u['name']) ?></strong><?= $self ? ' <span class="muted">(du)</span>' : '' ?></td>
                    <td><?= !empty($u['admin']) ? '<span class="badge">Admin</span>' : 'Nutzer' ?></td>
                    <td class="muted"><?= e($u['created'] ?? '') ?></td>
                    <td class="actions">
                        <details class="inline-form">
                            <summary class="btn btn--small">Passwort</summary>
                            <form method="post" action="<?= e(url(['a' => 'admin'])) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="do" value="password">
                                <input type="hidden" name="name" value="<?= e($u['name']) ?>">
                                <input type="password" name="password" placeholder="Neues Passwort" minlength="8" autocomplete="new-password" required>
                                <button type="submit" class="btn btn--small btn--primary">Setzen</button>
                            </form>
                        </details>
                        <form method="post" action="<?= e(url(['a' => 'admin'])) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="do" value="toggle_admin">
                            <input type="hidden" name="name" value="<?= e($u['name']) ?>">
                            <button type="submit" class="btn btn--small"><?= !empty($u['admin']) ? 'Admin entziehen' : 'Zum Admin machen' ?></button>
                        </form>
                        <?php if (!$self): ?>
                            <form method="post" action="<?= e(url(['a' => 'admin'])) ?>" data-confirm="„<?= e($u['name']) ?>“ wirklich löschen?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="do" value="delete">
                                <input type="hidden" name="name" value="<?= e($u['name']) ?>">
                                <button type="submit" class="btn btn--small btn--danger">Löschen</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2 class="card__sub">Neuen Nutzer anlegen</h2>
    <form method="post" action="<?= e(url(['a' => 'admin'])) ?>" class="form form--row" data-busy-form>
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="create">
        <label>Benutzername
            <input type="text" name="name" autocomplete="off" autocapitalize="off" pattern="[A-Za-z0-9._\-]{2,32}"
                   title="2 bis 32 Zeichen: Buchstaben, Zahlen, . _ -" required>
        </label>
        <label>Passwort
            <input type="password" name="password" placeholder="mind. 8 Zeichen" autocomplete="new-password" minlength="8" required>
        </label>
        <label class="check"><input type="checkbox" name="admin" value="1"> Admin</label>
        <button type="submit" class="btn btn--primary">Anlegen</button>
    </form>

    <h2 class="card__sub">API-Verbrauch</h2>
    <p class="muted small">Echte Anfragen an Brave (Cache-Treffer zählen nicht). Limit: <?= (int) cfg('monthly_limit') ?> pro Monat, Freiguthaben reicht für ca. 1.000.</p>
    <?php if (!$history): ?>
        <p class="muted">Noch keine Anfragen.</p>
    <?php else: ?>
        <ul class="usage">
            <?php foreach ($history as $month => $n): ?>
                <li>
                    <span class="usage__month"><?= e($month) ?></span>
                    <progress class="usage__bar" max="<?= max(1, (int) cfg('monthly_limit')) ?>" value="<?= (int) $n ?>"></progress>
                    <span class="usage__n"><?= (int) $n ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
