<?php defined('SM_VERSION') || exit;
/**
 * Auswahl der Ausweich-Suchmaschine (Einstellungen des Kontos und Verwaltung).
 * $fbCur: gespeicherte Adresse mit {q} ('' = Standard), $fbDefault: Eintrag „Standard der Installation“ anbieten
 */
$fbIsPreset = isset(smFallbackPresets()[$fbCur]);
?>
            <p class="muted small"><?= h($fbDefault
                ? t('Hat Brave gerade keine Suchen mehr übrig, ist gestört oder nicht eingerichtet, geht deine Suche sofort und ohne Zwischenstopp zu dieser Suchmaschine. Bilder, News und Videos landen dort im passenden Bereich.')
                : t('Gäste und Konten ohne eigene Wahl suchen hier, wenn Brave nicht kann. Gäste suchen nie bei Brave, sondern immer hier (oder bei der Suchmaschine, die sie sich auf der Startseite aussuchen). Jedes Konto kann unter Einstellungen → Suche eine eigene wählen.')) ?></p>
            <div class="form__row">
                <label class="field"><span><?= h(t('Suchmaschine')) ?></span>
                    <select name="fallback_preset">
<?php if ($fbDefault): ?>                        <option value=""<?= $fbCur === '' ? ' selected' : '' ?>><?= h(t('Standard der Installation (%s)', smFallbackName(null, smFallbackDefault()))) ?></option>
<?php endif; ?>
<?php foreach (smFallbackPresets() as $u => $n): ?>                        <option value="<?= h($u) ?>"<?= $u === $fbCur ? ' selected' : '' ?>><?= h($n) ?></option>
<?php endforeach; ?>
                        <option value="own"<?= !$fbIsPreset && $fbCur !== '' ? ' selected' : '' ?>><?= h(t('Eigene Adresse …')) ?></option>
                    </select></label>
                <label class="field"><span><?= h(t('Eigene Adresse mit {q}')) ?></span><input type="text" name="fallback_own" value="<?= h($fbIsPreset ? '' : $fbCur) ?>" placeholder="https://example.org/search?q={q}" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="800">
                    <small class="muted"><?= h(t('Nur bei „Eigene Adresse“. {q} wird durch den Suchbegriff ersetzt. Einen Startpage-Einstellungslink (…/mypage.pl?prfe=…) kannst du direkt einfügen, er wird zur Suche mit genau diesen Einstellungen.')) ?></small></label>
            </div>
            <p class="muted small"><?= h(t('Bilder dort trotzdem groß und im Vollbild ansehen:')) ?> <a href="<?= h(smUrl(['a' => 'help'])) ?>#bildergross"><?= h(t('Lesezeichen „Bilder groß“')) ?></a></p>
