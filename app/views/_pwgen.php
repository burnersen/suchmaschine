<?php defined('SM_VERSION') || exit;
/** Passwort-Generator: läuft komplett im Browser (crypto.getRandomValues), der Server sieht das Passwort nie */
$pwLen = (int)($c['len'] ?? 20);
$pwPhrase = !empty($c['phrase']);
?>
        <div class="pwgen" data-pwgen>
            <div class="pwgen__out">
                <input type="text" class="pwgen__pw" data-pw-out readonly aria-label="<?= h(t('Erzeugtes Passwort')) ?>" spellcheck="false" autocomplete="off" value="">
                <button type="button" class="btn btn--primary btn--small" data-pw-copy data-done="<?= h(t('Kopiert')) ?>"><?= h(t('Kopieren')) ?></button>
                <button type="button" class="btn btn--small" data-pw-new title="<?= h(t('Neu erzeugen')) ?>">↻</button>
            </div>
            <div class="pwgen__meter"><span data-pw-bar></span></div>
            <p class="muted small" data-pw-info data-l-bits="<?= h(t('Bit Entropie')) ?>" data-l-weak="<?= h(t('schwach')) ?>" data-l-ok="<?= h(t('gut')) ?>" data-l-strong="<?= h(t('sehr stark')) ?>"></p>
            <fieldset class="seg pwgen__mode">
                <label><input type="radio" name="pwmode" value="pw"<?= $pwPhrase ? '' : ' checked' ?> data-pw-mode><span><?= h(t('Zufällige Zeichen')) ?></span></label>
                <label><input type="radio" name="pwmode" value="phrase"<?= $pwPhrase ? ' checked' : '' ?> data-pw-mode><span><?= h(t('Leicht merkbar (Wörter)')) ?></span></label>
            </fieldset>
            <div class="pwgen__opts" data-pw-opts-pw>
                <label class="pwgen__len"><?= h(t('Länge')) ?> <input type="range" min="6" max="64" value="<?= max(6, min(64, $pwLen)) ?>" data-pw-len> <output data-pw-len-out><?= $pwLen ?></output></label>
                <label class="check"><input type="checkbox" checked data-pw-set="lower"> a–z</label>
                <label class="check"><input type="checkbox" checked data-pw-set="upper"> A–Z</label>
                <label class="check"><input type="checkbox" checked data-pw-set="digits"> 0–9</label>
                <label class="check"><input type="checkbox" checked data-pw-set="symbols"> !#$%&amp;*…</label>
                <label class="check"><input type="checkbox" data-pw-noambig> <?= h(t('Ohne Verwechsler (l, 1, I, O, 0)')) ?></label>
            </div>
            <div class="pwgen__opts" data-pw-opts-phrase hidden>
                <label class="pwgen__len"><?= h(t('Wörter')) ?> <input type="range" min="3" max="10" value="5" data-pw-words> <output data-pw-words-out>5</output></label>
                <label class="check"><input type="checkbox" checked data-pw-cap> <?= h(t('Großbuchstaben am Wortanfang')) ?></label>
                <label class="check"><input type="checkbox" checked data-pw-num> <?= h(t('Mit Zahl')) ?></label>
                <label><?= h(t('Trenner')) ?> <select data-pw-sep><option value="-">-</option><option value=".">.</option><option value="_">_</option><option value=" "><?= h(t('Leerzeichen')) ?></option><option value=""><?= h(t('keiner')) ?></option></select></label>
            </div>
            <noscript><p class="alert alert--error"><?= h(t('Der Passwort-Generator braucht JavaScript. Er läuft nur in deinem Browser, der Server sieht die Passwörter nie.')) ?></p></noscript>
            <p class="muted small"><?= h(t('Wird nur in deinem Browser erzeugt (Web Crypto), nichts davon geht an den Server.')) ?></p>
        </div>
