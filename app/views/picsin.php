<?php defined('SM_VERSION') || exit;
/* Bestätigung nach „An meine App schicken“ im Lesezeichen „Bilder groß“, wenn dieser Browser nicht angemeldet ist
   (am iPhone hat die App vom Startbildschirm eine eigene Anmeldung). Zeigt nichts aus dem Konto außer Name und Anzahl der neuen Sammlung. */ ?>
<main class="auth">
    <div class="auth__card">
        <span class="auth__logo"><?= smLogoHtml(true) ?><span class="auth__name"><?= smSiteNameHtml() ?></span></span>
<?php if ($set): ?>
        <h1 class="auth__title"><?= h(t('%d Bilder geschickt', (int)$set['n'])) ?></h1>
        <p><?= h(t('Sie liegen jetzt als Sammlung „%s“ in deinem Konto.', $set['title'])) ?></p>
        <p class="muted"><?= h(t('Öffne deine Suchmaschine als App und geh auf Werkzeuge → Bilder groß. Dort startest du die Sammlung randlos oder als Diashow. Im Suchfeld geht auch !bilder.')) ?></p>
        <p><a class="btn btn--primary btn--wide" href="<?= h(smUrl(['a' => 'pics', 'set' => $set['id']])) ?>"><?= h(t('Hier im Browser ansehen')) ?></a></p>
<?php else: ?>
        <h1 class="auth__title"><?= h(t('Das hat nicht geklappt')) ?></h1>
        <p class="muted"><?= h(t('Es kamen keine Bilder an, oder es waren zu viele Sammlungen in kurzer Zeit. Bitte später noch einmal versuchen.')) ?></p>
<?php endif; ?>
    </div>
</main>
