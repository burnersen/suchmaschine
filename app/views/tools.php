<?php defined('SM_VERSION') || exit;
/** Werkzeuge: Passwort-Generator, QR-Code-Generator, Kurzlinks. Alles ohne Suchdienst, kostet also kein Guthaben. */
$c = ['len' => 20];
$links = $me ? smShortList($me['id']) : [];
$ex = function (string $q) { return '<a class="chip" href="' . h(smUrl(['q' => $q])) . '"><code>' . h($q) . '</code></a>'; };
?>
<main class="page page--tools">
    <h1 class="page__title"><?= h(t('Werkzeuge')) ?></h1>
    <p class="muted small page__lead"><?= h(t('Alles hier läuft ohne Suchdienst und kostet kein Guthaben. Viele Werkzeuge gehen auch direkt im Suchfeld, siehe Beispiele.')) ?></p>

    <details class="panel tool" id="passwort" open>
        <summary><h2><?= h(t('Passwort-Generator')) ?></h2></summary>
<?php require __DIR__ . '/_pwgen.php'; ?>
        <p class="chips"><?= $ex(smLang() === 'de' ? 'passwort 32' : 'password 32') ?> <?= $ex('passphrase') ?></p>
    </details>

    <details class="panel tool" id="qr" open>
        <summary><h2><?= h(t('QR-Code-Generator')) ?></h2></summary>
        <form method="get" action="<?= h(smBase()) ?>" class="form" data-qr-form>
            <input type="hidden" name="a" value="tools">
            <label class="field"><span><?= h(t('Adresse oder Text')) ?></span>
                <textarea name="qr" rows="2" maxlength="<?= SM_QR_MAX ?>" required placeholder="https://example.org" spellcheck="false" data-qr-input><?= h($qr) ?></textarea></label>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('QR-Code erzeugen')) ?></button></div>
        </form>
<?php if ($qr !== ''): ?>
        <div class="ia__qr" data-qr-preview><img src="<?= h(smUrl(['a' => 'qr', 'd' => $qr])) ?>" alt="<?= h(t('QR-Code für %s', $qr)) ?>" width="240" height="240"></div>
        <p><a class="btn btn--small" href="<?= h(smUrl(['a' => 'qr', 'd' => $qr, 'dl' => '1'])) ?>" download="qr-code.svg"><?= h(t('Als SVG herunterladen')) ?></a></p>
<?php endif; ?>
        <p class="muted small"><?= h(t('Der Code entsteht auf diesem Server als SVG, ohne fremden Dienst. Im Menü ⋮ neben jedem Suchtreffer gibt es ihn auch für die Adresse des Treffers.')) ?></p>
        <p class="chips"><?= $ex('qr https://example.org') ?></p>
    </details>

    <details class="panel tool" id="kurzlinks" open>
        <summary><h2><?= h(t('Kurzlinks')) ?></h2></summary>
<?php if (!$me): ?>
        <p class="muted"><?= h(t('Kurzlinks kann man nach dem Anmelden anlegen.')) ?></p>
<?php elseif (smSetting('shortener') !== '1'): ?>
        <p class="muted"><?= h(t('Kurzlinks sind ausgeschaltet.')) ?></p>
<?php else: ?>
        <form method="post" action="<?= h(smUrl(['a' => 'tools'])) ?>" class="form" data-busy>
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="short_add">
            <label class="field"><span><?= h(t('Lange Adresse')) ?></span><input type="url" name="url" required maxlength="2000" placeholder="https://example.org/ein/sehr/langer/pfad" autocomplete="off" spellcheck="false"></label>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Kurzlink erstellen')) ?></button></div>
        </form>
<?php if ($links): ?>
        <ul class="list shortlist">
<?php foreach ($links as $l): $su = smShortUrl($l['code']); ?>
            <li class="list__row<?= $l['code'] === $new ? ' is-new' : '' ?>">
                <span class="list__main"><code class="copy" data-copy title="<?= h(t('Klicken zum Kopieren')) ?>" data-done="<?= h(t('Kopiert')) ?>"><?= h($su) ?></code><br>
                    <small class="muted list__url"><?= h($l['url']) ?> · <?= h(t('%d Aufrufe', (int)$l['hits'])) ?> · <?= h(smDate((int)$l['created'])) ?></small></span>
                <button type="button" class="btn btn--small" data-qr="<?= h($su) ?>" data-qr-src="<?= h(smUrl(['a' => 'qr', 'd' => $su])) ?>"><?= h(t('QR')) ?></button>
                <form method="post" action="<?= h(smUrl(['a' => 'tools'])) ?>" data-confirm="<?= h(t('Kurzlink löschen?')) ?>"><?= smCsrfField($me) ?><input type="hidden" name="do" value="short_del"><input type="hidden" name="code" value="<?= h($l['code']) ?>">
                    <button type="submit" class="btn btn--small btn--danger"><?= h(t('Löschen')) ?></button></form>
            </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
        <p class="muted small"><?= h(t('Kurzlinks funktionieren für jeden, auch ohne Anmeldung, und leiten direkt weiter. Nur du siehst deine Liste. Im Suchfeld geht auch „kurz https://…“.')) ?></p>
<?php endif; ?>
    </details>

    <section class="panel">
        <h2><?= h(t('Direkt im Suchfeld')) ?></h2>
        <p class="chips"><?php foreach (smLang() === 'de'
            ? ['(12,5 + 7) * 3', '200 + 19%', '15% von 80', '100 usd in eur', '50 chf in eur', '10 km in meilen', '20 °c in f', '1 gib in mb', 'meine ip', 'kw', 'datum', 'wetter berlin', 'würfel 2w6', 'münze', 'zufallszahl 1-49', 'timer 5 min', 'countdown weihnachten']
            : ['(12.5 + 7) * 3', '200 + 19%', '15% of 80', '100 usd in eur', '50 chf in eur', '10 km in miles', '20 °c in f', '1 gib in mb', 'my ip', 'week', 'date', 'weather london', 'dice 2d6', 'coin', 'random 1-49', 'timer 5 min', 'countdown christmas'] as $e) echo $ex($e) . ' '; ?></p>
        <p><a href="<?= h(smUrl(['a' => 'help'])) ?>#sofort"><?= h(t('Alle Sofort-Antworten in der Hilfe')) ?></a></p>
    </section>
</main>
