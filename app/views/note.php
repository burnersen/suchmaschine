<?php defined('SM_VERSION') || exit;
/** Abholseite für einen Text-Schnipsel. Entschlüsselt wird nur im Browser: neue Schnipsel (v2) mit dem Passwort, alte mit dem Schlüssel hinter dem # im Link. */
?>
<main class="auth">
    <div class="auth__card note-page" data-note="<?= h($noteId) ?>" data-salt="<?= h($info['salt'] ?? '') ?>" data-pass="<?= !empty($info['pass']) ? '1' : '0' ?>" data-v="<?= (int)($info['v'] ?? 1) ?>"
         data-l-wrong="<?= h(t('Der Schlüssel im Link passt nicht. Wurde der Link vollständig kopiert?')) ?>" data-l-nokey="<?= h(t('Im Link fehlt der Schlüssel (der Teil nach dem #). Bitte den vollständigen Link verwenden.')) ?>"
         data-l-gone="<?= h(t('Der Schnipsel wurde jetzt gelöscht. Kopiere den Text, wenn du ihn brauchst.')) ?>" data-l-left="<?= h(t('Noch %d Abrufe möglich.')) ?>"
         data-l-copy="<?= h(t('Kopieren')) ?>" data-l-done="<?= h(t('Kopiert')) ?>" data-l-err="<?= h(t('Das hat nicht geklappt. Bitte noch einmal versuchen.')) ?>" data-l-https="<?= h(t('Entschlüsseln geht nur über HTTPS.')) ?>">
        <a class="auth__logo" href="<?= h(smBase()) ?>"><?= smLogoHtml(true) ?><span class="auth__name"><?= smSiteNameHtml() ?></span></a>
        <h1 class="auth__title"><?= h(t('Text-Schnipsel')) ?></h1>
<?php if (!$info): ?>
        <div class="alert"><?= h(t('Diesen Text-Schnipsel gibt es nicht mehr. Er wurde schon abgeholt oder ist abgelaufen.')) ?></div>
<?php else: ?>
        <p class="muted small"><?= h(t('Verschlüsselt. Der Server kennt den Inhalt nicht, entschlüsselt wird nur hier in deinem Browser.')) ?>
            <?= h($info['left'] === 1 ? t('Nach dem Anzeigen wird er gelöscht.') : ($info['left'] > 1 ? t('Noch %d Abrufe möglich.', $info['left']) : '')) ?>
            <?= h(t('Gültig bis %s.', smLongDate($info['expires'], false) . ' ' . date('H:i', $info['expires']))) ?></p>
        <form class="form" data-note-form>
<?php if ($info['pass']): ?>
            <label class="field"><span><?= h(t('Passwort')) ?></span><input type="password" name="pass" required autocomplete="off" autofocus></label>
<?php endif; ?>
            <button type="submit" class="btn btn--primary btn--wide"><?= h(t('Anzeigen')) ?></button>
        </form>
        <div class="alert alert--error" data-note-err hidden></div>
        <div data-note-out hidden>
            <pre class="note-page__text" data-note-text></pre>
            <p><button type="button" class="btn btn--primary btn--small" data-note-copy><?= h(t('Kopieren')) ?></button> <span class="muted small" data-note-info></span></p>
        </div>
        <noscript><div class="alert alert--error"><?= h(t('Zum Entschlüsseln braucht diese Seite JavaScript.')) ?></div></noscript>
<?php endif; ?>
    </div>
</main>
