<?php defined('SM_VERSION') || exit;
/**
 * Werkzeuge: alle Helfer an einem Ort. Viele laufen als kleine App im Browser (QR-Studio, Scanner, Passwörter, Entwickler-Werkzeuge …),
 * die übrigen nutzen dieselbe Rechnung wie die Sofort-Antworten im Suchfeld. Nichts davon fragt einen Suchdienst, kostet also kein Guthaben.
 * $tool = gerade benutztes Werkzeug, $info = Angaben dazu, $card = Ergebnis (oder null), $toolErr = Fehlertext
 */
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
$state = $g('st', (string)($me['prefs']['state'] ?? ''));
// Ansicht der App zu einem Werkzeug (die Bausteine aus CodeForge heißen dort anders)
$view = ['qr' => 'create', 'scanner' => 'scan', 'nummern' => 'convert', 'qrdruck' => 'print'][$tool] ?? $tool;
$needLogin = $info && !empty($info[4]['login']) && !$me;
?>
<main class="page page--tools">
<?php if ($tool === ''): ?>
    <div class="tools__head">
        <h1 class="page__title"><?= h(t('Werkzeuge')) ?></h1>
        <label class="tools__find"><span class="visually-hidden"><?= h(t('Werkzeug finden')) ?></span>
            <input type="search" placeholder="<?= h(t('Werkzeug finden … (Taste /)')) ?>" autocomplete="off" spellcheck="false" data-tools-find aria-label="<?= h(t('Werkzeug finden')) ?>"></label>
    </div>
    <p class="muted small page__lead"><?= h(t('%d Helfer, alle ohne Suchdienst und ohne Guthaben. Mit ☆ heftest du Werkzeuge oben an. Vieles geht auch direkt im Suchfeld.', array_sum(array_map('count', smToolGroups())))) ?></p>
    <section class="tools__pinned" data-tools-pinned hidden>
        <h2 class="tools__group"><?= h(t('Favoriten')) ?></h2>
        <div class="toolgrid" data-tools-pinned-list></div>
    </section>
    <section class="tools__pinned" data-tools-recent hidden>
        <h2 class="tools__group"><?= h(t('Zuletzt benutzt')) ?></h2>
        <div class="toolgrid toolgrid--small" data-tools-recent-list></div>
    </section>
    <p class="muted tools__none" data-tools-none hidden><?= h(t('Kein Werkzeug gefunden. Vielleicht hilft die normale Suche?')) ?></p>
<?php foreach (smToolGroups() as $gname => $items): ?>
    <section class="tools__sec" data-tools-sec>
    <h2 class="tools__group"><?= h($gname) ?></h2>
    <div class="toolgrid">
<?php foreach ($items as $id => [$ico, $name, $desc, $opt]): ?>
        <a class="toolcard" id="<?= $id ?>" href="<?= h(smToolUrl($id)) ?>" data-tool="<?= $id ?>" data-kw="<?= h(mb_strtolower($name . ' ' . $desc . ' ' . ($opt['kw'] ?? ''))) ?>"><span class="toolcard__ico" aria-hidden="true"><?= $ico ?></span>
            <span class="toolcard__text"><strong><?= h($name) ?><?php if (!empty($opt['new'])): ?> <span class="toolcard__new"><?= h(t('neu')) ?></span><?php endif; ?><?php if (!empty($opt['login']) && !$me): ?> <span class="toolcard__lock" title="<?= h(t('nach dem Anmelden')) ?>">🔒</span><?php endif; ?></strong><small><?= h($desc) ?></small></span></a>
<?php endforeach; ?>
    </div>
    </section>
<?php endforeach; ?>
    <p class="muted small tools__foot"><a href="<?= h(smUrl(['a' => 'help'])) ?>#sofort"><?= h(t('Alle Sofort-Antworten in der Hilfe')) ?></a> · <?= h(t('Abkürzungen im Suchfeld: !qr, !scan, !pw, !kurz, !notiz, !json …')) ?></p>
<?php else: $id = $tool; [$gname, $ico, $name, $desc, $opt] = $info; ?>
    <nav class="crumbs" aria-label="<?= h(t('Brotkrumen')) ?>"><a href="<?= h(smUrl(['a' => 'tools'])) ?>" data-tools-back>← <?= h(t('Alle Werkzeuge')) ?></a> <span aria-hidden="true">·</span> <span><?= h($gname) ?></span></nav>
    <section class="panel tool tool--single<?= !empty($opt['app']) ? ' tool--app' : '' ?>" id="<?= $id ?>" data-tool-page="<?= $id ?>">
        <div class="tool__top">
            <h1 class="tool__title"><span aria-hidden="true"><?= $ico ?></span> <?= h($name) ?></h1>
            <button type="button" class="iconbtn tool__pin" data-tool-pin="<?= $id ?>" title="<?= h(t('Als Favorit anheften')) ?>" aria-label="<?= h(t('Als Favorit anheften')) ?>" data-on="<?= h(t('Favorit entfernen')) ?>" data-off="<?= h(t('Als Favorit anheften')) ?>">☆</button>
        </div>
        <p class="muted small tool__desc"><?= h($desc) ?></p>
<?php if ($needLogin): ?>
        <div class="alert"><?= h(t('Dieses Werkzeug ruft fremde Server ab und ist darum nur nach dem Anmelden verfügbar.')) ?>
            <p><a class="btn btn--primary btn--small" href="<?= h(smUrl(['a' => 'login', 'next' => smSelf()])) ?>"><?= h(t('Anmelden')) ?></a></p></div>
<?php elseif (!empty($opt['app'])): ?>
        <div class="cf tool__app" data-cf-app="<?= h($view) ?>" data-tool="<?= $id ?>"><p class="muted small tool__loading"><span class="spinner"></span> <?= h(t('Wird geladen …')) ?></p></div>
        <noscript><p class="alert alert--error"><?= h(t('Dieses Werkzeug läuft im Browser und braucht JavaScript.')) ?></p></noscript>
<?php endif; ?>
<?php if (!$needLogin): ?>
        <div class="tool__fallback"<?= !empty($opt['app']) ? ' data-cf-fallback' : '' ?>>
<?php switch ($id):
case 'rechner': ?>
        <?= $form('rechner', ' data-calc') ?>
            <label class="field"><span><?= h(t('Rechnung')) ?></span><input type="text" name="x" value="<?= h($g('x')) ?>" inputmode="decimal" autocomplete="off" spellcheck="false" placeholder="(12,5 + 7) * 3" data-calc-in></label>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Ausrechnen')) ?></button></div>
        </form>
<?php $result('rechner'); ?>
        <p class="chips"><?= $ex('200 + 19%') ?> <?= $ex($de ? '15% von 80' : '15% of 80') ?> <?= $ex('sqrt 2') ?> <?= $ex('2^10') ?></p>
<?php break; case 'einheiten':
    $units = [];
    $gn = ['len' => t('Länge'), 'mass' => t('Gewicht'), 'temp' => t('Temperatur'), 'vol' => t('Volumen'), 'area' => t('Fläche'), 'speed' => t('Geschwindigkeit'), 'time' => t('Zeit'), 'data' => t('Datenmengen')];
    $seen = [];
    foreach (smUnits() as $k => [$grp, , $show]) { if (isset($seen[$grp . $show])) continue; $seen[$grp . $show] = 1; $units[$grp][$k] = $show; }
    $ufrom = $g('from', 'km'); $uto = $g('to', 'mi'); ?>
        <?= $form('einheiten') ?>
            <div class="form__row form__row--tool">
                <label class="field"><span><?= h(t('Wert')) ?></span><input type="text" name="n" value="<?= h($g('n', '1')) ?>" inputmode="decimal" required></label>
<?php foreach (['from' => [t('von'), $ufrom], 'to' => [t('in'), $uto]] as $fn => [$lab, $cur]): ?>
                <label class="field"><span><?= h($lab) ?></span><select name="<?= $fn ?>">
<?php foreach ($gn as $grp => $gl): if (empty($units[$grp])) continue; ?>
                    <optgroup label="<?= h($gl) ?>"><?php foreach ($units[$grp] as $k => $show): ?><option value="<?= h($k) ?>"<?= $k === $cur ? ' selected' : '' ?>><?= h($show) ?></option><?php endforeach; ?></optgroup>
<?php endforeach; ?>
                </select></label>
<?php endforeach; ?>
            </div>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Umrechnen')) ?></button></div>
        </form>
<?php $result('einheiten'); ?>
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
<?php break; case 'timer': ?>
        <?= $form('timer') ?>
            <div class="form__row form__row--tool">
                <label class="field"><span><?= h(t('Minuten')) ?></span><input type="number" name="min" value="<?= h($g('min', '5')) ?>" min="0" max="5999" step="any"></label>
            </div>
            <div class="form__actions">
                <button type="submit" class="btn btn--primary"><?= h(t('Timer stellen')) ?></button>
                <button type="submit" class="btn" name="watch" value="1"><?= h(t('Stoppuhr')) ?></button>
            </div>
        </form>
<?php $result('timer'); ?>
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
            <div class="seg seg--wrap">
<?php foreach (['diff' => t('Tage zwischen'), 'work' => t('Arbeitstage'), 'add' => t('Plus/minus'), 'weekday' => t('Wochentag'), 'age' => t('Alter')] as $k => $v): ?>
                <label><input type="radio" name="m" value="<?= $k ?>"<?= $k === $dm ? ' checked' : '' ?>><span><?= h($v) ?></span></label>
<?php endforeach; ?>
            </div>
            <div class="form__row form__row--tool">
                <label class="field" data-dm="diff work add weekday age"><span data-dm-label data-l-age="<?= h(t('Geburtstag')) ?>" data-l-other="<?= h(t('Datum')) ?>"><?= h($dm === 'age' ? t('Geburtstag') : t('Datum')) ?></span><input type="date" name="d1" value="<?= h($g('d1', date('Y-m-d'))) ?>" required></label>
                <label class="field" data-dm="diff work"><span><?= h(t('bis')) ?></span><input type="date" name="d2" value="<?= h($g('d2')) ?>"></label>
                <label class="field" data-dm="add"><span><?= h(t('plus oder minus')) ?></span>
                    <span class="tool__inline"><?= $sel('sign', ['+' => '+', '-' => '−'], $g('sign', '+')) ?><input type="number" name="k" value="<?= h($g('k', '30')) ?>" min="0" max="99999"><?= $sel('u', ['tage' => t('Tage'), 'arbeitstage' => t('Arbeitstage'), 'wochen' => t('Wochen'), 'monate' => t('Monate'), 'jahre' => t('Jahre')], $g('u', 'tage')) ?></span></label>
                <label class="field" data-dm="work"><span><?= h(t('Feiertage von')) ?></span><?= $sel('st', ['' => t('nur bundesweite')] + smStates(), $state) ?></label>
            </div>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Ausrechnen')) ?></button></div>
        </form>
<?php $result('datum'); ?>
        <p class="chips"><?= $ex($de ? 'arbeitstage zwischen 1.12.2026 und 31.12.2026 bayern' : 'workdays between 2026-12-01 and 2026-12-31') ?> <?= $ex($de ? 'heute + 10 arbeitstage' : 'today + 10 workdays') ?> <?= $ex($de ? 'wochentag 24.12.2027' : 'weekday 2027-12-24') ?> <?= $ex($de ? 'alter 12.05.1940' : 'age 1940-05-12') ?></p>
<?php break; case 'feiertage': $fm = $g('m', 'list'); ?>
        <?= $form('feiertage') ?>
            <div class="seg">
<?php foreach (['list' => t('Feiertage'), 'bridge' => t('Brückentage')] as $k => $v): ?>
                <label><input type="radio" name="m" value="<?= $k ?>"<?= $k === $fm ? ' checked' : '' ?>><span><?= h($v) ?></span></label>
<?php endforeach; ?>
            </div>
            <div class="form__row form__row--tool">
                <label class="field"><span><?= h(t('Jahr')) ?></span><input type="number" name="y" value="<?= h($g('y', date('Y'))) ?>" min="1995" max="2100"></label>
                <label class="field"><span><?= h(t('Bundesland')) ?></span><?= $sel('st', ['' => t('alle Bundesländer')] + smStates(), $state) ?></label>
            </div>
            <div class="form__actions">
                <button type="submit" class="btn btn--primary"><?= h(t('Zeigen')) ?></button>
                <a class="btn" href="<?= h(smToolUrl('feiertage', ['ics' => '1', 'y' => $g('y', date('Y')), 'st' => $state])) ?>" download><?= h(t('Als Kalenderdatei (.ics)')) ?></a>
            </div>
        </form>
<?php $result('feiertage'); ?>
        <p class="chips"><?= $ex($de ? 'feiertage 2027 bayern' : 'holidays 2027 bavaria') ?> <?= $ex($de ? 'brückentage 2027 nrw' : 'bridge days 2027 nrw') ?></p>
<?php break; case 'kalender': ?>
        <?= $form('kalender') ?>
            <div class="form__row form__row--tool">
                <label class="field"><span><?= h(t('Kalenderwoche (leer = aktuelle)')) ?></span><input type="number" name="w" value="<?= h($g('w')) ?>" min="1" max="53"></label>
                <label class="field"><span><?= h(t('Jahr')) ?></span><input type="number" name="y" value="<?= h($g('y', date('Y'))) ?>" min="1970" max="2100"></label>
            </div>
            <div class="form__actions">
                <button type="submit" class="btn btn--primary"><?= h(t('Kalenderwoche zeigen')) ?></button>
                <button type="submit" class="btn" name="year" value="1"><?= h(t('Jahreskalender')) ?></button>
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
                <button type="submit" class="btn" name="z" value="lotto"><?= h(t('Lotto 6 aus 49')) ?></button>
                <button type="submit" class="btn" name="z" value="euro"><?= h(t('Eurojackpot')) ?></button>
                <button type="submit" class="btn" name="z" value="card"><?= h(t('Karte ziehen')) ?></button>
                <button type="submit" class="btn" name="z" value="yesno"><?= h(t('Ja oder nein?')) ?></button>
            </div>
        </form>
<?php $result('zufall'); ?>
        <p class="muted small"><?= h(t('Echter Zufall vom Server (kryptografisch sicher).')) ?></p>
<?php break; case 'wetter': ?>
        <?= $form('wetter') ?>
            <div class="form__row form__row--tool">
                <label class="field"><span><?= h(t('Ort')) ?></span><input type="text" name="ort" value="<?= h($g('ort', (string)($me['prefs']['city'] ?? ''))) ?>" maxlength="60" placeholder="<?= h(t('z. B. Berlin')) ?>"></label>
            </div>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Wetter zeigen')) ?></button></div>
        </form>
<?php $result('wetter'); ?>
<?php break; case 'kurzlinks': $links = $me ? smShortList($me['id']) : []; ?>
<?php if (!$me): ?>
        <p class="muted"><?= h(t('Kurzlinks kann man nach dem Anmelden anlegen.')) ?></p>
<?php elseif (smSetting('shortener') !== '1'): ?>
        <p class="muted"><?= h(t('Kurzlinks sind ausgeschaltet.')) ?></p>
<?php else: ?>
        <form method="post" action="<?= h(smUrl(['a' => 'tools'])) ?>" class="form" data-busy>
            <?= smCsrfField($me) ?><input type="hidden" name="do" value="short_add">
            <label class="field"><span><?= h(t('Lange Adresse')) ?></span><input type="url" name="url" required maxlength="2000" placeholder="https://example.org/ein/sehr/langer/pfad" autocomplete="off" spellcheck="false"></label>
            <label class="field field--short"><span><?= h(t('Wunschname (optional)')) ?></span><input type="text" name="code" maxlength="40" pattern="[A-Za-z0-9][A-Za-z0-9_\-]{2,39}" autocomplete="off" spellcheck="false"></label>
            <label class="check"><input type="checkbox" name="strip" value="1" checked> <?= h(t('Tracking-Parameter entfernen (utm_…, fbclid …)')) ?></label>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('Kurzlink erstellen')) ?></button></div>
        </form>
<?php if ($links): ?>
        <ul class="list shortlist">
<?php foreach ($links as $l): ?>
            <li class="list__row<?= $l['code'] === $new ? ' is-new' : '' ?>">
                <span class="list__main"><code class="copy" data-copy title="<?= h(t('Klicken zum Kopieren')) ?>" data-done="<?= h(t('Kopiert')) ?>"><?= h($l['short']) ?></code><br>
                    <small class="muted list__url"><?= h($l['title'] !== '' ? $l['title'] . ' · ' : '') ?><?= h($l['url']) ?> · <?= h(t('%d Aufrufe', $l['hits'])) ?> · <?= h(smDate($l['created'])) ?></small></span>
                <form method="post" action="<?= h(smUrl(['a' => 'tools'])) ?>" data-confirm="<?= h(t('Kurzlink löschen?')) ?>"><?= smCsrfField($me) ?><input type="hidden" name="do" value="short_del"><input type="hidden" name="code" value="<?= h($l['code']) ?>">
                    <button type="submit" class="btn btn--small btn--danger"><?= h(t('Löschen')) ?></button></form>
            </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
<?php endif; ?>
<?php break; case 'ip': ?>
        <?= $form('ip') ?>
            <div class="form__actions"><button type="submit" class="btn btn--primary"><?= h(t('IP-Adresse zeigen')) ?></button></div>
        </form>
<?php $result('ip'); ?>
<?php break; case 'qr': if ($qr !== ''): ?>
        <div class="ia__qr"><img src="<?= h(smUrl(['a' => 'qr', 'd' => $qr])) ?>" alt="<?= h(t('QR-Code für %s', $qr)) ?>" width="240" height="240"></div>
<?php endif; break; endswitch; ?>
        </div>
<?php endif; ?>
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
