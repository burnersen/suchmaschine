<?php defined('SM_VERSION') || exit;
/**
 * Werkzeuge: alle kleinen Helfer an einem Ort. Die meisten nutzen dieselbe Rechnung wie die Sofort-Antworten im Suchfeld,
 * das Ergebnis erscheint direkt in der Karte. Nichts davon fragt einen Suchdienst, kostet also kein Guthaben.
 * $tool = gerade benutztes Werkzeug, $card = Ergebnis dazu (oder null), $toolErr = Fehlertext
 */
$c = ['len' => 20];
$links = $me ? smShortList($me['id']) : [];
$de = smLang() === 'de';
$g = function (string $k, string $def = ''): string { return isset($_GET[$k]) && is_string($_GET[$k]) ? $_GET[$k] : $def; };
$ex = function (string $q) { return '<a class="chip" href="' . h(smUrl(['q' => $q])) . '"><code>' . h($q) . '</code></a>'; };
// Ergebnis in der Karte des Werkzeugs
$result = function (string $id) use ($tool, $card, $toolErr, $me, $prefs) {
    if ($tool !== $id) return;
    if ($toolErr !== '') { echo '<div class="alert alert--error tool__err">' . h($toolErr) . '</div>'; return; }
    if (!$card) return;
    $instant = $card;
    $q = (string)($_GET['ia'] ?? '');
    $inTools = true;
    $toolId = $id;
    require __DIR__ . '/_instant.php';
};
$form = function (string $id, string $extra = '') { return '<form method="get" action="' . h(smBase()) . '" class="form tool__form"' . $extra . '><input type="hidden" name="a" value="tools"><input type="hidden" name="tool" value="' . $id . '">'; };
$sel = function (string $name, array $opts, string $cur) {
    $s = '<select name="' . h($name) . '">';
    foreach ($opts as $k => $v) $s .= '<option value="' . h((string)$k) . '"' . ((string)$k === $cur ? ' selected' : '') . '>' . h($v) . '</option>';
    return $s . '</select>';
};
?>
<main class="page page--tools">
<?php if ($tool === ''): ?>
    <h1 class="page__title"><?= h(t('Werkzeuge')) ?></h1>
    <p class="muted small page__lead"><?= h(t('Alles hier läuft ohne Suchdienst und kostet kein Guthaben. Fast alles geht auch direkt im Suchfeld, die Beispiele stehen bei jedem Werkzeug.')) ?></p>
<?php foreach (smToolGroups() as $gname => $items): ?>
    <h2 class="tools__group"><?= h($gname) ?></h2>
    <div class="toolgrid">
<?php foreach ($items as $id => [$ico, $name, $desc]): ?>
        <a class="toolcard" id="<?= $id ?>" href="<?= h(smToolUrl($id)) ?>"><span class="toolcard__ico" aria-hidden="true"><?= $ico ?></span>
            <span class="toolcard__text"><strong><?= h($name) ?></strong><small><?= h($desc) ?></small></span></a>
<?php endforeach; ?>
    </div>
<?php endforeach; ?>
    <p class="muted small tools__foot"><a href="<?= h(smUrl(['a' => 'help'])) ?>#sofort"><?= h(t('Alle Sofort-Antworten in der Hilfe')) ?></a></p>
<?php else: $id = $tool; [$gname, $ico, $name, $desc] = smToolInfo($tool); ?>
    <nav class="crumbs" aria-label="<?= h(t('Brotkrumen')) ?>"><a href="<?= h(smUrl(['a' => 'tools'])) ?>" data-tools-back>← <?= h(t('Alle Werkzeuge')) ?></a> <span aria-hidden="true">·</span> <span><?= h($gname) ?></span></nav>
    <section class="panel tool tool--single" id="<?= $id ?>">
        <h1 class="tool__title"><span aria-hidden="true"><?= $ico ?></span> <?= h($name) ?></h1>
        <p class="muted small tool__desc"><?= h($desc) ?></p>
<?php switch ($id):
case 'rechner': ?>
        <?= $form('rechner', ' data-calc') ?>
            <label class="field"><span><?= h(t('Rechnung')) ?></span><input type="text" name="x" value="<?= h($g('x')) ?>" inputmode="decimal" autocomplete="off" spellcheck="false" placeholder="(12,5 + 7) * 3" data-calc-in></label>
            <div class="keypad" data-keypad hidden>
<?php foreach (['7', '8', '9', '/', '(', '4', '5', '6', '*', ')', '1', '2', '3', '-', '^', '0', $de ? ',' : '.', '%', '+', '⌫'] as $k): ?>
                <button type="button" class="keypad__k<?= in_array($k, ['/', '*', '-', '+', '^', '%', '(', ')'], true) ? ' keypad__k--op' : '' ?>" data-key="<?= h($k) ?>"><?= h(['/' => '÷', '*' => '×'][$k] ?? $k) ?></button>
<?php endforeach; ?>
                <button type="button" class="keypad__k keypad__k--clr" data-key="C">C</button>
                <button type="submit" class="keypad__k keypad__k--eq">=</button>
            </div>
            <div class="form__actions keypad__nojs"><button type="submit" class="btn btn--primary"><?= h(t('Ausrechnen')) ?></button></div>
        </form>
<?php $result('rechner'); ?>
        <p class="chips"><?= $ex($de ? '200 + 19%' : '200 + 19%') ?> <?= $ex($de ? '15% von 80' : '15% of 80') ?> <?= $ex('sqrt 2') ?> <?= $ex('2^10') ?></p>
<?php break; case 'einheiten':
    $units = [];
    $gn = ['len' => t('Länge'), 'mass' => t('Gewicht'), 'temp' => t('Temperatur'), 'vol' => t('Volumen'), 'area' => t('Fläche'), 'speed' => t('Geschwindigkeit'), 'time' => t('Zeit'), 'data' => t('Datenmengen')];
    $seen = [];
    foreach (smUnits() as $k => [$grp, , $show]) { if (isset($seen[$grp . $show])) continue; $seen[$grp . $show] = 1; $units[$grp][$k] = $show; }
    $ufrom = $g('from', 'km'); $uto = $g('to', 'mi'); ?>
        <?= $form('einheiten', ' data-units') ?>
            <div class="form__row form__row--tool">
                <label class="field"><span><?= h(t('Wert')) ?></span><input type="text" name="n" value="<?= h($g('n', '1')) ?>" inputmode="decimal" required></label>
<?php foreach (['from' => [t('von'), $ufrom], 'to' => [t('in'), $uto]] as $fn => [$lab, $cur]): ?>
                <label class="field"><span><?= h($lab) ?></span><select name="<?= $fn ?>">
<?php foreach ($gn as $grp => $gl): if (empty($units[$grp])) continue; ?>
                    <optgroup label="<?= h($gl) ?>" data-grp="<?= $grp ?>"><?php foreach ($units[$grp] as $k => $show): ?><option value="<?= h($k) ?>" data-grp="<?= $grp ?>"<?= $k === $cur ? ' selected' : '' ?>><?= h($show) ?></option><?php endforeach; ?></optgroup>
<?php endforeach; ?>
                </select></label>
<?php endforeach; ?>
            </div>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Umrechnen')) ?></button></div>
        </form>
<?php $result('einheiten'); ?>
        <p class="chips"><?= $ex($de ? '10 km in meilen' : '10 km in miles') ?> <?= $ex('20 °c in f') ?> <?= $ex($de ? '2 tassen in ml' : '2 cups in ml') ?></p>
<?php break; case 'waehrung':
    $fx = smFxRates();
    $codes = array_keys($fx['rates']);
    $codes[] = 'EUR';
    sort($codes); ?>
        <?= $form('waehrung') ?>
            <div class="form__row form__row--tool">
                <label class="field"><span><?= h(t('Betrag')) ?></span><input type="text" name="n" value="<?= h($g('n', '100')) ?>" inputmode="decimal" required></label>
<?php if (count($codes) > 1): ?>
                <label class="field"><span><?= h(t('von')) ?></span><?= $sel('from', array_combine($codes, $codes), strtoupper($g('from', 'EUR'))) ?></label>
                <label class="field"><span><?= h(t('in')) ?></span><?= $sel('to', array_combine($codes, $codes), strtoupper($g('to', 'USD'))) ?></label>
<?php else: ?>
                <label class="field"><span><?= h(t('von')) ?></span><input type="text" name="from" value="<?= h($g('from', 'EUR')) ?>" maxlength="3"></label>
                <label class="field"><span><?= h(t('in')) ?></span><input type="text" name="to" value="<?= h($g('to', 'USD')) ?>" maxlength="3"></label>
<?php endif; ?>
            </div>
            <div class="form__actions">
                <button type="submit" class="btn btn--primary"><?= h(t('Umrechnen')) ?></button>
                <button type="submit" class="btn" name="fx_refresh" value="1" title="<?= h(t('Holt sofort den neuesten Stand der EZB')) ?>"><?= h(t('Kurse jetzt abrufen')) ?></button>
            </div>
        </form>
<?php $result('waehrung'); ?>
        <p class="muted small"><?= h(t('Referenzkurse der Europäischen Zentralbank. Die EZB veröffentlicht sie einmal pro Werktag gegen 16 Uhr, die Suchmaschine holt sie danach automatisch. Mit „Kurse jetzt abrufen“ geht es sofort.')) ?></p>
<?php break; case 'timer': ?>
        <?= $form('timer') ?>
            <div class="form__row form__row--tool">
                <label class="field"><span><?= h(t('Minuten')) ?></span><input type="number" name="min" value="<?= h($g('min', '5')) ?>" min="0" max="5999" step="any"></label>
            </div>
            <div class="form__actions">
                <button type="submit" class="btn btn--primary"><?= h(t('Timer stellen')) ?></button>
                <button type="submit" class="btn" name="watch" value="1"><?= h(t('Stoppuhr')) ?></button>
<?php foreach ([1, 3, 5, 10, 15, 30] as $mm): ?>
                <a class="btn btn--small" href="<?= h(smToolUrl('timer', ['min' => $mm])) ?>"><?= $mm ?> min</a>
<?php endforeach; ?>
            </div>
        </form>
<?php $result('timer'); ?>
        <p class="muted small"><?= h(t('Sechs Töne zur Auswahl, mit „Ton testen“ vorher anhören. Auf dem Handy vibriert es zusätzlich, die Zeit steht auch im Tab-Titel.')) ?></p>
<?php break; case 'countdown': ?>
        <?= $form('countdown') ?>
            <div class="form__row form__row--tool">
                <label class="field"><span><?= h(t('Datum')) ?></span><input type="date" name="d" value="<?= h($g('d')) ?>"></label>
                <label class="field"><span><?= h(t('oder')) ?></span><?= $sel('named', ['' => '…', 'weihnachten' => t('Heiligabend'), 'silvester' => t('Silvester'), 'neujahr' => t('Neujahr'), 'ostern' => t('Ostern'), 'nikolaus' => t('Nikolaus'), 'valentinstag' => t('Valentinstag'), 'halloween' => 'Halloween'], $g('named')) ?></label>
            </div>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Countdown starten')) ?></button></div>
        </form>
<?php $result('countdown'); ?>
<?php break; case 'datum': $dm = $g('m', 'diff'); ?>
        <?= $form('datum', ' data-datemode') ?>
            <div class="seg">
<?php foreach (['diff' => t('Tage zwischen'), 'add' => t('Plus/minus'), 'age' => t('Alter')] as $k => $v): ?>
                <label><input type="radio" name="m" value="<?= $k ?>"<?= $k === $dm ? ' checked' : '' ?>><span><?= h($v) ?></span></label>
<?php endforeach; ?>
            </div>
            <div class="form__row form__row--tool">
                <label class="field" data-dm="diff add age"><span data-dm-label data-l-age="<?= h(t('Geburtstag')) ?>" data-l-other="<?= h(t('Datum')) ?>"><?= h($dm === 'age' ? t('Geburtstag') : t('Datum')) ?></span><input type="date" name="d1" value="<?= h($g('d1', date('Y-m-d'))) ?>" required></label>
                <label class="field" data-dm="diff"><span><?= h(t('bis')) ?></span><input type="date" name="d2" value="<?= h($g('d2')) ?>"></label>
                <label class="field" data-dm="add"><span><?= h(t('plus oder minus')) ?></span>
                    <span class="tool__inline"><?= $sel('sign', ['+' => '+', '-' => '−'], $g('sign', '+')) ?><input type="number" name="k" value="<?= h($g('k', '30')) ?>" min="0" max="99999"><?= $sel('u', ['tage' => t('Tage'), 'wochen' => t('Wochen'), 'monate' => t('Monate'), 'jahre' => t('Jahre')], $g('u', 'tage')) ?></span></label>
            </div>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Ausrechnen')) ?></button></div>
        </form>
<?php $result('datum'); ?>
        <p class="chips"><?= $ex('tage zwischen 1.1.2026 und 24.12.2026') ?> <?= $ex('heute + 6 wochen') ?> <?= $ex('alter 12.05.1940') ?></p>
<?php break; case 'feiertage': ?>
        <?= $form('feiertage') ?>
            <div class="form__row form__row--tool">
                <label class="field"><span><?= h(t('Jahr')) ?></span><input type="number" name="y" value="<?= h($g('y', date('Y'))) ?>" min="1995" max="2100"></label>
                <label class="field"><span><?= h(t('Bundesland')) ?></span><?= $sel('st', ['' => t('alle Bundesländer')] + smStates(), $g('st', (string)($me['prefs']['state'] ?? ''))) ?></label>
            </div>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Feiertage zeigen')) ?></button></div>
        </form>
<?php $result('feiertage'); ?>
        <p class="chips"><?= $ex('feiertage 2027 bayern') ?> <?= $ex('feiertage nrw') ?></p>
<?php break; case 'kalender': ?>
        <?= $form('kalender') ?>
            <div class="form__row form__row--tool">
                <label class="field"><span><?= h(t('Kalenderwoche (leer = aktuelle)')) ?></span><input type="number" name="w" value="<?= h($g('w')) ?>" min="1" max="53"></label>
                <label class="field"><span><?= h(t('Jahr')) ?></span><input type="number" name="y" value="<?= h($g('y', date('Y'))) ?>" min="1970" max="2100"></label>
            </div>
            <div class="form__actions">
                <button type="submit" class="btn btn--primary"><?= h(t('Kalenderwoche zeigen')) ?></button>
                <a class="btn" href="<?= h(smToolUrl('kalender', ['now' => '1'])) ?>"><?= h(t('Datum und Uhrzeit')) ?></a>
            </div>
        </form>
<?php $result('kalender'); ?>
<?php break; case 'zufall': ?>
        <?= $form('zufall') ?>
            <div class="form__row form__row--tool">
                <label class="field"><span><?= h(t('Würfel')) ?></span><span class="tool__inline"><input type="number" name="dn" value="<?= h($g('dn', '1')) ?>" min="1" max="20"> × W <input type="number" name="df" value="<?= h($g('df', '6')) ?>" min="2" max="1000"></span></label>
                <label class="field"><span><?= h(t('Zufallszahl von … bis')) ?></span><span class="tool__inline"><input type="number" name="ra" value="<?= h($g('ra', '1')) ?>"> – <input type="number" name="rb" value="<?= h($g('rb', '100')) ?>"></span></label>
            </div>
            <div class="form__actions">
                <button type="submit" class="btn btn--primary" name="z" value="dice"><?= h(t('Würfeln')) ?></button>
                <button type="submit" class="btn" name="z" value="random"><?= h(t('Zufallszahl')) ?></button>
                <button type="submit" class="btn" name="z" value="coin"><?= h(t('Münze werfen')) ?></button>
            </div>
        </form>
<?php $result('zufall'); ?>
<?php break; case 'losen': $lm = $g('lm', 'pick'); ?>
        <?= $form('losen') ?>
            <label class="field"><span><?= h(t('Namen, einer pro Zeile oder mit Komma getrennt')) ?></span>
                <textarea name="names" rows="4" maxlength="3000" placeholder="<?= h(t('Anna, Bernd, Carla, Dieter')) ?>"><?= h($g('names')) ?></textarea></label>
            <div class="form__row form__row--tool">
                <label class="field"><span><?= h(t('Was soll passieren?')) ?></span><?= $sel('lm', ['pick' => t('Einen auslosen'), 'teams' => t('In Gruppen aufteilen'), 'order' => t('Reihenfolge mischen')], $lm) ?></label>
                <label class="field"><span><?= h(t('Anzahl (Gewinner oder Gruppen)')) ?></span><input type="number" name="k" value="<?= h($g('k', $lm === 'teams' ? '2' : '1')) ?>" min="1" max="50"></label>
            </div>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Los')) ?></button></div>
        </form>
<?php $result('losen'); ?>
        <p class="muted small"><?= h(t('Echter Zufall vom Server. Die Namen werden nirgends gespeichert.')) ?></p>
<?php break; case 'wetter': ?>
        <?= $form('wetter') ?>
            <div class="form__row form__row--tool">
                <label class="field"><span><?= h(t('Ort')) ?></span><input type="text" name="ort" value="<?= h($g('ort', (string)($me['prefs']['city'] ?? ''))) ?>" maxlength="60" placeholder="<?= h(t('z. B. Berlin')) ?>"></label>
            </div>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Wetter zeigen')) ?></button></div>
        </form>
<?php $result('wetter'); ?>
<?php break; case 'passwort': ?>
<?php require __DIR__ . '/_pwgen.php'; ?>
        <p class="chips"><?= $ex($de ? 'passwort 32' : 'password 32') ?> <?= $ex('passphrase') ?></p>
<?php break; case 'qr': ?>
        <form method="get" action="<?= h(smBase()) ?>" class="form" data-qr-form>
            <input type="hidden" name="a" value="tools"><input type="hidden" name="tool" value="qr">
            <label class="field"><span><?= h(t('Adresse oder Text')) ?></span>
                <textarea name="qr" rows="2" maxlength="<?= SM_QR_MAX ?>" required placeholder="https://example.org" spellcheck="false" data-qr-input><?= h($qr) ?></textarea></label>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('QR-Code erzeugen')) ?></button></div>
        </form>
<?php if ($qr !== ''): ?>
        <div class="ia__qr" data-qr-preview><img src="<?= h(smUrl(['a' => 'qr', 'd' => $qr])) ?>" alt="<?= h(t('QR-Code für %s', $qr)) ?>" width="240" height="240"></div>
        <p><a class="btn btn--small" href="<?= h(smUrl(['a' => 'qr', 'd' => $qr, 'dl' => '1'])) ?>" download="qr-code.svg"><?= h(t('Als SVG herunterladen')) ?></a></p>
<?php endif; ?>
        <p class="muted small"><?= h(t('Der Code entsteht auf diesem Server als SVG, ohne fremden Dienst. Im Menü ⋮ neben jedem Suchtreffer gibt es ihn auch für die Adresse des Treffers.')) ?></p>
<?php break; case 'kurzlinks': ?>
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
<?php break; case 'ip': ?>
        <?= $form('ip') ?>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('IP-Adresse zeigen')) ?></button></div>
        </form>
<?php $result('ip'); ?>
<?php break; endswitch; ?>
    </section>
<?php $sib = array_diff_key(smToolGroups()[$gname], [$id => 1]); ?>
    <nav class="toolmore" aria-label="<?= h(t('Weitere Werkzeuge')) ?>">
<?php if ($sib): ?>
        <span class="toolmore__label"><?= h(t('Mehr dazu:')) ?></span>
<?php foreach ($sib as $sid => [$sico, $sname]): ?>
        <a class="chip" href="<?= h(smToolUrl($sid)) ?>"><span aria-hidden="true"><?= $sico ?></span> <?= h($sname) ?></a>
<?php endforeach; ?>
<?php endif; ?>
        <a class="chip chip--all" href="<?= h(smUrl(['a' => 'tools'])) ?>"><?= h(t('Alle Werkzeuge')) ?></a>
    </nav>
<?php endif; ?>
</main>
