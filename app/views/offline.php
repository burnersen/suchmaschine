<?php defined('SM_VERSION') || exit;
/* „Keine Verbindung“: Diese Seite legt der Service Worker (app/assets/sw.js) auf dem Gerät ab und zeigt sie, wenn das Netz fehlt.
   Sie enthält nichts Persönliches. Kommt das Netz zurück, lädt app.js die ursprünglich gewünschte Adresse neu. */ ?>
<main class="auth" data-offline>
    <div class="auth__card">
        <span class="auth__logo"><?= smLogoHtml(true) ?><span class="auth__name"><?= smSiteNameHtml() ?></span></span>
        <h1 class="auth__title"><?= h(t('Keine Verbindung')) ?></h1>
        <p class="muted"><?= h(t('Gerade ist kein Internet da. Sobald die Verbindung zurück ist, geht es von selbst weiter.')) ?></p>
        <p><a class="btn btn--primary btn--wide" href="" data-offline-retry><?= h(t('Noch einmal versuchen')) ?></a></p>
    </div>
</main>
