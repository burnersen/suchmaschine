<?php defined('SM_VERSION') || exit;
/**
 * Einstellungen → Aussehen: hell/dunkel, Designs, eigene Farben (Farbmischer), Schrift, Größen, Abstände, Breite.
 * Ohne JavaScript: Auswahl und die Farbwähler des Browsers. Mit JavaScript: Regler, Farbcode, Live-Vorschau, Kontrastwarnung.
 * $look = aktuelles Aussehen, $lookUrl = Formularziel, $isDefault = Standard der Installation bearbeiten
 */
$pals = smPalettes();
$palJs = [];
foreach ($pals as $side => $list) foreach ($list as $id => $p) $palJs[$side][$id] = ['bg' => $p[1], 'card' => $p[2], 'text' => $p[3], 'acc' => $p[4], 'link' => $p[5]];
$parts = ['bg' => t('Hintergrund'), 'card' => t('Flächen'), 'text' => t('Schrift'), 'acc' => t('Akzent'), 'link' => t('Titel und Links'), 'mut' => t('Nebentext')];
?>
    <form method="post" action="<?= h($lookUrl) ?>" class="form look" data-look data-pals="<?= h(json_encode($palJs)) ?>"
          data-warn="<?= h(t('Schwer zu lesen: %s')) ?>" data-names="<?= h(json_encode($parts)) ?>">
        <?= smCsrfField($me) ?><input type="hidden" name="do" value="look"><?php if (!empty($isDefault)): ?><input type="hidden" name="default_only" value="1"><?php endif; ?>

        <section class="panel">
            <h2><?= h(t('Vorschau')) ?></h2>
            <div class="lookprev" data-look-prev aria-hidden="true">
                <div class="lookprev__bar"><span class="lookprev__box"><?= h(t('Suchbegriff')) ?></span><span class="lookprev__chip is-on"><?= h(t('Alle')) ?></span><span class="lookprev__chip"><?= h(t('News')) ?></span></div>
                <div class="lookprev__res">
                    <span class="lookprev__site">beispiel.de › rezepte</span>
                    <span class="lookprev__title"><?= h(t('Kartoffelsuppe wie bei Oma: einfach und cremig')) ?></span>
                    <span class="lookprev__desc"><span class="lookprev__age"><?= h(t('vor 2 Stunden')) ?> · </span><?= h(t('Mit Lauch, Möhren und einem Schuss Sahne. Fertig in 45 Minuten, schmeckt am nächsten Tag noch besser.')) ?></span>
                </div>
                <div class="lookprev__res">
                    <span class="lookprev__site">nachrichten.example › politik</span>
                    <span class="lookprev__title"><?= h(t('Haushaltswoche im Bundestag: Was jetzt ansteht')) ?></span>
                    <span class="lookprev__desc"><?= h(t('Vier Tage Debatte, am Freitag die Abstimmung. Ein Überblick über die wichtigsten Fragen.')) ?></span>
                </div>
                <span class="btn btn--primary btn--small"><?= h(t('Knopf')) ?></span>
            </div>
        </section>

        <section class="panel">
            <h2><?= h(t('Hell oder dunkel')) ?></h2>
            <div class="seg">
<?php foreach (['auto' => t('Wie das Gerät'), 'light' => t('Hell'), 'dark' => t('Dunkel')] as $k => $v): ?>
                <label><input type="radio" name="mode" value="<?= $k ?>"<?= $k === $look['mode'] ? ' checked' : '' ?>><span><?= h($v) ?></span></label>
<?php endforeach; ?>
            </div>
            <small class="muted"><?= h(t('Bei „Wie das Gerät“ wechselt die Suchmaschine von selbst zwischen deinem hellen und deinem dunklen Design.')) ?></small>
        </section>

<?php foreach (['light' => ['pl', 'cl', t('Helles Design')], 'dark' => ['pd', 'cd', t('Dunkles Design')]] as $side => [$pk, $ck, $label]):
    $cur = $look[$pk];
    $own = $look[$ck] ?: smLookSide($look, $side); ?>
        <section class="panel" data-side="<?= $side ?>">
            <h2><?= h($label) ?></h2>
            <div class="swatches">
<?php foreach ($pals[$side] as $id => $p): ?>
                <label class="sw sw--<?= $side ?>-<?= h($id) ?>"><input type="radio" name="<?= $pk ?>" value="<?= h($id) ?>"<?= $id === $cur ? ' checked' : '' ?>>
                    <span class="sw__card"><i class="sw__bar"></i><i class="sw__line"></i><i class="sw__line sw__line--short"></i></span><span class="sw__name"><?= h($p[0]) ?></span></label>
<?php endforeach; ?>
                <label class="sw sw--<?= $side ?>-eigen" data-sw-own><input type="radio" name="<?= $pk ?>" value="eigen"<?= $cur === 'eigen' ? ' checked' : '' ?>>
                    <span class="sw__card"><i class="sw__bar"></i><i class="sw__line"></i><i class="sw__line sw__line--short"></i></span><span class="sw__name"><?= h(t('Eigenes')) ?></span></label>
            </div>
            <details class="mixer" data-mixer="<?= $side ?>"<?= $cur === 'eigen' ? ' open' : '' ?>>
                <summary><?= h(t('Eigene Farben mischen')) ?></summary>
                <p class="muted small"><?= h(t('Wählt automatisch „Eigenes“. Titel/Links und Nebentext rechnet die Suchmaschine sonst passend aus.')) ?></p>
                <div class="mixer__parts">
<?php foreach ($parts as $k => $name): $opt = in_array($k, ['link', 'mut'], true); $v = $own[$k] ?? ''; ?>
                    <label class="mixer__part">
<?php if ($opt): ?><input type="checkbox" name="<?= $ck ?>_own[<?= $k ?>]" value="1"<?= $v !== '' && ($look[$ck][$k] ?? '') !== '' ? ' checked' : '' ?> title="<?= h(t('Eigene Farbe statt automatisch')) ?>"><?php endif; ?>
                        <input type="color" name="<?= $ck ?>[<?= $k ?>]" value="<?= h($v !== '' ? $v : '#888888') ?>" data-part="<?= $k ?>"> <span><?= h($name) ?><?= $opt ? ' <small class="muted">' . h(t('(sonst automatisch)')) . '</small>' : '' ?></span></label>
<?php endforeach; ?>
                </div>
                <div class="mixer__js" hidden>
                    <div class="mixer__sliders">
                        <label class="sl sl--hue"><?= h(t('Farbton')) ?><input type="range" min="0" max="360" data-sl="0"></label>
                        <label class="sl sl--sat"><?= h(t('Sättigung')) ?><input type="range" min="0" max="100" data-sl="1"></label>
                        <label class="sl sl--lig"><?= h(t('Helligkeit')) ?><input type="range" min="0" max="100" data-sl="2"></label>
                    </div>
                    <div class="mixer__row">
                        <input type="text" class="mixer__hex" maxlength="7" spellcheck="false" autocomplete="off" aria-label="<?= h(t('Farbcode')) ?>">
                        <select class="mixer__from" aria-label="<?= h(t('Von einem fertigen Design ausgehen')) ?>">
                            <option value=""><?= h(t('Von einem fertigen Design ausgehen …')) ?></option>
<?php foreach ($pals as $s2 => $list): ?><optgroup label="<?= h($s2 === 'light' ? t('Hell') : t('Dunkel')) ?>"><?php foreach ($list as $id => $p): ?><option value="<?= h($s2 . ':' . $id) ?>"><?= h($p[0]) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn--small" data-match><?= h(t('Schriftfarben automatisch abstimmen')) ?></button>
                    </div>
                    <p class="mixer__warn" hidden></p>
                </div>
            </details>
        </section>
<?php endforeach; ?>

        <section class="panel">
            <h2><?= h(t('Schrift')) ?></h2>
            <div class="fonts">
<?php foreach (smFonts() as $id => $f): ?>
                <label class="fontpick fontpick--<?= h($id) ?>"><input type="radio" name="font" value="<?= h($id) ?>"<?= $id === $look['font'] ? ' checked' : '' ?>><span><?= h($f[0]) ?><small><?= h(t('Gut lesbar: 1 l I 0 O')) ?></small></span></label>
<?php endforeach; ?>
            </div>
            <small class="muted"><?= h(t('Alle Schriften liegen auf deinem eigenen Server. Es wird nichts bei Google oder anderen geladen.')) ?></small>
            <div class="form__row look__ranges">
<?php foreach (['fs' => [t('Alles zusammen'), 80, 150, '%'], 'ft' => [t('Titel der Treffer'), 70, 160, '%'], 'fd' => [t('Beschreibungen'), 70, 160, '%'], 'lh' => [t('Zeilenabstand'), 130, 200, '']] as $k => [$name, $min, $max, $unit]): ?>
                <label class="field"><span><?= h($name) ?> <output data-out="<?= $k ?>"><?= $k === 'lh' ? h(number_format($look[$k] / 100, 2, ',', '')) : (int)$look[$k] . ' %' ?></output></span>
                    <input type="range" name="<?= $k ?>" min="<?= $min ?>" max="<?= $max ?>" step="<?= $k === 'lh' ? 2 : 5 ?>" value="<?= (int)$look[$k] ?>"></label>
<?php endforeach; ?>
            </div>
        </section>

        <section class="panel">
            <h2><?= h(t('Platz')) ?></h2>
            <fieldset class="field"><legend><?= h(t('Abstand zwischen den Treffern')) ?></legend>
                <div class="seg">
<?php foreach (['compact' => t('Kompakt'), 'normal' => t('Normal'), 'airy' => t('Luftig')] as $k => $v): ?>
                    <label><input type="radio" name="gap" value="<?= $k ?>"<?= $k === $look['gap'] ? ' checked' : '' ?>><span><?= h($v) ?></span></label>
<?php endforeach; ?>
                </div>
            </fieldset>
            <fieldset class="field"><legend><?= h(t('Breite der Trefferliste (großer Bildschirm)')) ?></legend>
                <div class="seg">
<?php foreach (['narrow' => t('Schmal'), 'normal' => t('Normal'), 'wide' => t('Breit'), 'full' => t('Sehr breit')] as $k => $v): ?>
                    <label><input type="radio" name="width" value="<?= $k ?>"<?= $k === $look['width'] ? ' checked' : '' ?>><span><?= h($v) ?></span></label>
<?php endforeach; ?>
                </div>
            </fieldset>
        </section>

        <div class="form__actions form__actions--sticky">
<?php if (!empty($isDefault)): ?>
            <button type="submit" class="btn btn--primary" name="scope" value="default"><?= h(t('Als Standard für alle speichern')) ?></button>
<?php else: ?>
            <button type="submit" class="btn btn--primary"><?= h(t('Speichern')) ?></button>
            <button type="submit" class="btn" name="reset" value="1"><?= h(t('Auf den Standard zurücksetzen')) ?></button>
<?php if ($me['is_admin']): ?>
            <button type="submit" class="btn" name="scope" value="default"><?= h(t('Als Standard für alle speichern')) ?></button>
<?php endif; ?>
<?php endif; ?>
        </div>
    </form>
