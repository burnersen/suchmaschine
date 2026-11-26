<?php defined('SM_VERSION') || exit;
/** Kurzlink ohne direkte Weiterleitung: Vorschau („+“ am Ende), Passwort oder Aufruf-Grenze (Zwischenseite), abgelaufen, aus, unbekannt */
$l = $link ?? null;
$err = $err ?? '';
?>
<main class="auth">
    <div class="auth__card short-page">
        <a class="auth__logo" href="<?= h(smBase()) ?>"><?= smLogoHtml(true) ?><span class="auth__name"><?= smSiteNameHtml() ?></span></a>
<?php switch ($mode):
case 'preview': ?>
        <h1 class="auth__title"><?= h(t('Wohin führt dieser Kurzlink?')) ?></h1>
        <p class="short-page__to"><?= h($l['url']) ?></p>
<?php if ($l['title'] !== ''): ?><p class="muted"><?= h($l['title']) ?></p><?php endif; ?>
        <p class="muted small"><?= h(t('Die Vorschau zählt nicht als Aufruf. Prüfe die Adresse, bevor du weitergehst.')) ?></p>
<?php if ($l['state'] === 'ok'): ?>
        <p><a class="btn btn--primary" href="<?= h(smUrl(['s' => $l['code']])) ?>"><?= h(t('Zum Ziel')) ?></a></p>
<?php else: ?>
        <div class="alert"><?= h(t('Dieser Kurzlink ist gerade nicht aktiv.')) ?></div>
<?php endif; ?>
<?php break; case 'gate': ?>
        <h1 class="auth__title"><?= h($l['pass'] ? t('Geschützter Link') : t('Weiter zum Ziel')) ?></h1>
<?php if ($err !== ''): ?><div class="alert alert--error"><?= h($err) ?></div><?php endif; ?>
<?php if ($l['max'] > 0): ?><p class="muted small"><?= h($l['max'] === 1 ? t('Dieser Link funktioniert nur einmal.') : t('Dieser Link funktioniert noch %d Mal.', max(0, $l['max'] - $l['hits']))) ?></p><?php endif; ?>
        <form method="post" action="<?= h(smUrl(['s' => $l['code']])) ?>" class="form">
<?php if ($l['pass']): ?>
            <label class="field"><span><?= h(t('Passwort')) ?></span><input type="password" name="pass" required autofocus autocomplete="off"></label>
<?php endif; ?>
            <button type="submit" class="btn btn--primary btn--wide"><?= h(t('Weiter')) ?></button>
        </form>
<?php break; case 'expired': ?>
        <h1 class="auth__title"><?= h(t('Abgelaufen')) ?></h1>
        <p><?= h(t('Dieser Kurzlink ist abgelaufen.')) ?></p>
<?php break; case 'used': ?>
        <h1 class="auth__title"><?= h(t('Schon benutzt')) ?></h1>
        <p><?= h(t('Dieser Kurzlink wurde so oft aufgerufen, wie es erlaubt war.')) ?></p>
<?php break; case 'off': ?>
        <h1 class="auth__title"><?= h(t('Ausgeschaltet')) ?></h1>
        <p><?= h(t('Dieser Kurzlink ist gerade ausgeschaltet.')) ?></p>
<?php break; default: ?>
        <h1 class="auth__title"><?= h(t('Nicht gefunden')) ?></h1>
        <p><?= h(t('Diesen Kurzlink gibt es nicht (mehr).')) ?></p>
<?php endswitch; ?>
    </div>
</main>
