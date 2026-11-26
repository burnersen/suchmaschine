<?php defined('SM_VERSION') || exit;
/** Sofort-Antwort über der Seite. Kostet kein Guthaben. Darunter: trotzdem im Web suchen. */
$c = $instant;
$web = smUrl(['q' => $q, 'web' => '1']);
$nf = function (float $v): string { return smFmtNumber($v); };
?>
    <section class="ia ia--<?= h($c['type']) ?>" aria-live="polite">
<?php switch ($c['type']):
case 'calc': ?>
        <p class="ia__label"><?= h(t('Rechner')) ?></p>
        <p class="ia__expr"><?= h($c['expr']) ?> =</p>
        <p class="ia__big"><output><?= h($c['result']) ?></output>
<?php if ($c['value'] !== ''): ?> <button type="button" class="btn btn--small" data-copy-text="<?= h($c['result']) ?>" data-done="<?= h(t('Kopiert')) ?>"><?= h(t('Kopieren')) ?></button><?php endif; ?></p>
        <p class="ia__hint muted small"><?= h(t('Grundrechenarten, Klammern, Potenzen (^), Wurzel (sqrt), Prozent: „200 + 19%“, „15% von 80“. Komma und Punkt gehen beide.')) ?></p>
<?php break; case 'currency': ?>
        <p class="ia__label"><?= h(t('Währungsrechner')) ?></p>
<?php if (!empty($c['error'])): ?>
        <p><?= h($c['error']) ?></p>
<?php else: ?>
        <p class="ia__expr"><?= h($nf($c['amount'])) ?> <?= h($c['from']) ?> =</p>
        <p class="ia__big"><output><?= h(smFmtNumber($c['result'], 2)) ?></output> <?= h($c['to']) ?></p>
        <p class="muted small">1 <?= h($c['from']) ?> = <?= h(smFmtNumber($c['rate'], 6)) ?> <?= h($c['to']) ?> · 1 <?= h($c['to']) ?> = <?= h(smFmtNumber(1 / $c['rate'], 6)) ?> <?= h($c['from']) ?>
            · <?= h(t('Referenzkurs der EZB vom %s, abgerufen %s. Wird einmal am Tag nach der Veröffentlichung der EZB (gegen 16 Uhr) neu geholt.', $c['date'] !== '' ? smDate((int)strtotime($c['date'])) : '?', !empty($c['fetched']) ? date(smLang() === 'de' ? 'd.m.Y, H:i' : 'Y-m-d H:i', $c['fetched']) . (smLang() === 'de' ? ' Uhr' : '') : '?')) ?></p>
<?php if (empty($inTools)): /* unter Werkzeuge steht das Formular schon über der Karte */ ?>
        <form class="ia__form" method="get" action="<?= h(smBase()) ?>">
            <input type="text" inputmode="decimal" name="fx_a" value="<?= h($nf($c['amount'])) ?>" aria-label="<?= h(t('Betrag')) ?>">
            <select name="fx_f" aria-label="<?= h(t('Von')) ?>"><?php foreach ($c['list'] as $cc): ?><option<?= $cc === $c['from'] ? ' selected' : '' ?>><?= h($cc) ?></option><?php endforeach; ?></select>
            <span>→</span>
            <select name="fx_t" aria-label="<?= h(t('Nach')) ?>"><?php foreach ($c['list'] as $cc): ?><option<?= $cc === $c['to'] ? ' selected' : '' ?>><?= h($cc) ?></option><?php endforeach; ?></select>
            <button type="submit" class="btn btn--small"><?= h(t('Umrechnen')) ?></button>
        </form>
<?php endif; ?>
<?php endif; ?>
<?php break; case 'unit': ?>
        <p class="ia__label"><?= h(t('Einheiten umrechnen')) ?></p>
        <p class="ia__expr"><?= h($nf($c['value'])) ?> <?= h($c['from']) ?> =</p>
        <p class="ia__big"><output><?= h(smFmtNumber($c['result'], abs($c['result']) >= 100 ? 3 : 6)) ?></output> <?= h($c['to']) ?></p>
        <p class="ia__hint muted small"><?= h(t('Länge, Gewicht, Temperatur, Datenmengen, Volumen, Fläche, Geschwindigkeit und Zeit, z. B. „5 kg in lb“, „20 °C in F“, „1 GiB in MB“.')) ?></p>
<?php break; case 'ip': ?>
        <p class="ia__label"><?= h(t('Deine IP-Adresse')) ?></p>
        <p class="ia__big"><output><?= h($c['ip']) ?></output> <button type="button" class="btn btn--small" data-copy-text="<?= h($c['ip']) ?>" data-done="<?= h(t('Kopiert')) ?>"><?= h(t('Kopieren')) ?></button></p>
        <p class="muted small"><?= h($c['v6'] ? 'IPv6' : 'IPv4') ?> · <?= h(t('So sieht dieser Server dich. Hinter einem VPN, Proxy oder Mobilfunk-NAT ist das deren Adresse.')) ?></p>
<?php $other = $c['v6'] ? 'IPv4' : 'IPv6'; $src = smIpOtherSources($c['v6']); ?>
        <div class="ipother" data-ip-other data-own="<?= h($src['own']) ?>" data-ext="<?= h($src['ext']) ?>" data-fail="<?= h(t('Keine %s gefunden. Vermutlich hat dein Anschluss gerade keine.', $other)) ?>" hidden>
            <p class="ia__label"><?= h(t('Deine %s-Adresse', $other)) ?></p>
            <p class="ia__big" data-ip-out hidden><output></output> <button type="button" class="btn btn--small" data-copy-text="" data-done="<?= h(t('Kopiert')) ?>"><?= h(t('Kopieren')) ?></button></p>
            <p class="muted small" data-ip-msg></p>
            <p data-ip-ask hidden><button type="button" class="btn btn--small" data-ip-go><?= h(t('%s auch anzeigen', $other)) ?></button>
                <small class="muted"><?= h(t('Fragt dafür einmal ipify.org. Der Dienst sieht dabei deine Adresse.')) ?></small></p>
        </div>
<?php break; case 'week': ?>
        <p class="ia__label"><?= h($c['current'] ? t('Aktuelle Kalenderwoche') : t('Kalenderwoche')) ?></p>
        <p class="ia__big"><?= h(t('KW %d', $c['week'])) ?> <small class="muted"><?= (int)$c['year'] ?></small></p>
        <p><?= h(smLongDate($c['from'])) ?> – <?= h(smLongDate($c['to'])) ?></p>
        <p class="muted small"><?= h(t('Das Jahr %d hat %d Kalenderwochen (ISO 8601, Woche beginnt am Montag).', $c['year'], $c['weeks'])) ?>
            <a href="<?= h(smUrl(['q' => 'kw ' . ($c['week'] > 1 ? $c['week'] - 1 : 52) . ' ' . ($c['week'] > 1 ? $c['year'] : $c['year'] - 1)])) ?>">‹ <?= h(t('vorige')) ?></a>
            · <a href="<?= h(smUrl(['q' => 'kw ' . ($c['week'] < $c['weeks'] ? $c['week'] + 1 : 1) . ' ' . ($c['week'] < $c['weeks'] ? $c['year'] : $c['year'] + 1)])) ?>"><?= h(t('nächste')) ?> ›</a></p>
<?php break; case 'time': ?>
        <p class="ia__label"><?= h(t('Datum und Uhrzeit')) ?></p>
        <p class="ia__big" data-clock><?= h(date('H:i:s', $c['ts'])) ?></p>
        <p><?= h(smLongDate($c['ts'])) ?></p>
        <p class="muted small"><?= h(t('KW %d · Tag %d des Jahres · Zeitzone %s', (int)date('W', $c['ts']), (int)date('z', $c['ts']) + 1, date_default_timezone_get())) ?> · <?= h(t('Unix-Zeit')) ?> <code><?= (int)$c['ts'] ?></code></p>
<?php break; case 'password': ?>
        <p class="ia__label"><?= h(t('Passwort-Generator')) ?></p>
<?php require __DIR__ . '/_pwgen.php'; ?>
<?php break; case 'qr': ?>
        <p class="ia__label"><?= h(t('QR-Code')) ?></p>
        <div class="ia__qr"><img src="<?= h(smUrl(['a' => 'qr', 'd' => $c['text']])) ?>" alt="<?= h(t('QR-Code für %s', $c['text'])) ?>" width="220" height="220"></div>
        <p class="ia__qrtext"><?= h(mb_substr($c['text'], 0, 200)) ?></p>
        <p><a class="btn btn--small" href="<?= h(smUrl(['a' => 'qr', 'd' => $c['text'], 'dl' => '1'])) ?>" download="qr-code.svg"><?= h(t('Als SVG herunterladen')) ?></a>
            <a class="btn btn--small btn--ghost" href="<?= h(smToolUrl('qr', ['qr' => $c['text']])) ?>"><?= h(t('Bearbeiten')) ?></a></p>
        <p class="muted small"><?= h(t('Wird auf diesem Server erzeugt, kein fremder Dienst sieht den Inhalt.')) ?></p>
<?php break; case 'short': ?>
        <p class="ia__label"><?= h(t('Kurzlink')) ?></p>
<?php if (!$me): ?>
        <p><?= h(t('Kurzlinks kann man nach dem Anmelden anlegen.')) ?></p>
<?php elseif (smSetting('shortener') !== '1'): ?>
        <p><?= h(t('Kurzlinks sind ausgeschaltet.')) ?></p>
<?php elseif ($c['url'] === ''): ?>
        <p><?= h(t('Bitte eine vollständige Adresse eingeben, z. B. https://example.org/seite.')) ?></p>
<?php else: ?>
        <p class="ia__expr"><?= h($c['url']) ?></p>
        <form method="post" action="<?= h(smUrl(['a' => 'tools'])) ?>" class="ia__form"><?= smCsrfField($me) ?><input type="hidden" name="do" value="short_add"><input type="hidden" name="url" value="<?= h($c['url']) ?>">
            <button type="submit" class="btn btn--primary"><?= h(t('Kurzlink erstellen')) ?></button></form>
<?php endif; ?>
<?php break; case 'coin': ?>
        <p class="ia__label"><?= h(t('Münzwurf')) ?></p>
        <p class="ia__big ia__coin" data-coin data-heads="<?= h(t('Kopf')) ?>" data-tails="<?= h(t('Zahl')) ?>"><?= h($c['heads'] ? t('Kopf') : t('Zahl')) ?></p>
        <p><button type="button" class="btn btn--small" data-reroll="coin"><?= h(t('Nochmal werfen')) ?></button></p>
<?php break; case 'dice': ?>
        <p class="ia__label"><?= h(t('Würfel')) ?> <small class="muted"><?= (int)$c['n'] ?> × W<?= (int)$c['faces'] ?></small></p>
        <p class="ia__dice" data-dice data-n="<?= (int)$c['n'] ?>" data-faces="<?= (int)$c['faces'] ?>"><?php foreach ($c['rolls'] as $r): ?><span class="die"><?= (int)$r ?></span><?php endforeach; ?></p>
<?php if ($c['n'] > 1): ?><p><?= h(t('Summe')) ?>: <strong data-dice-sum><?= (int)$c['sum'] ?></strong></p><?php endif; ?>
        <p><button type="button" class="btn btn--small" data-reroll="dice"><?= h(t('Nochmal würfeln')) ?></button> <span class="muted small"><?= h(t('Auch: „würfel 3“, „2w20“, „3d6“')) ?></span></p>
<?php break; case 'random': ?>
        <p class="ia__label"><?= h(t('Zufallszahl von %d bis %d', $c['min'], $c['max'])) ?></p>
        <p class="ia__big" data-random data-min="<?= (int)$c['min'] ?>" data-max="<?= (int)$c['max'] ?>"><?= (int)$c['value'] ?></p>
        <p><button type="button" class="btn btn--small" data-reroll="random"><?= h(t('Neue Zahl')) ?></button> <span class="muted small"><?= h(t('Auch: „zufallszahl 1-6“, „random 10 to 20“')) ?></span></p>
<?php break; case 'timer': ?>
        <p class="ia__label"><?= h($c['watch'] ? t('Stoppuhr') : t('Timer')) ?></p>
<?php $snd = []; foreach (smSounds() as $sk => $sn) $snd[$sk] = smUrl(['a' => 'asset', 'f' => 'sounds/' . $sk . '.mp3', 'v' => SM_VERSION]); ?>
        <div class="timer" data-timer data-sec="<?= (int)$c['sec'] ?>" data-done="<?= h(t('Zeit ist um!')) ?>" data-sounds="<?= h(json_encode($snd)) ?>">
            <p class="ia__big timer__show" data-timer-show><?= $c['watch'] ? '00:00' : h(sprintf('%02d:%02d', intdiv($c['sec'], 60), $c['sec'] % 60)) ?></p>
            <p><button type="button" class="btn btn--primary btn--small" data-timer-start data-label-pause="<?= h(t('Pause')) ?>" data-label-go="<?= h(t('Start')) ?>"><?= h(t('Start')) ?></button>
               <button type="button" class="btn btn--small" data-timer-reset><?= h(t('Zurücksetzen')) ?></button>
               <button type="button" class="btn btn--small btn--danger" data-timer-mute hidden><?= h(t('Ton aus')) ?></button></p>
<?php if (!$c['watch']): ?>
            <p class="timer__sound" data-timer-sound hidden><label><?= h(t('Ton')) ?>
                <select data-sound-pick><?php foreach (smSounds() as $sk => $sn): ?><option value="<?= h($sk) ?>"><?= h($sn) ?></option><?php endforeach; ?><option value=""><?= h(t('kein Ton')) ?></option></select></label>
                <button type="button" class="btn btn--small" data-sound-test><?= h(t('Ton testen')) ?></button></p>
<?php endif; ?>
            <noscript><p class="muted small"><?= h(t('Der Timer braucht JavaScript.')) ?></p></noscript>
        </div>
        <p class="muted small"><?= h(t('Auch: „timer 90 s“, „timer 1,5 h“, „stoppuhr“. Am Ende klingt der gewählte Ton, bis du „Ton aus“ drückst (höchstens eine Minute), solange der Tab offen ist. Am Handy den Lautlos-Schalter beachten.')) ?></p>
<?php break; case 'countdown': ?>
        <p class="ia__label"><?= h(t('Countdown bis %s', $c['label'])) ?></p>
        <p class="ia__big" data-countdown data-ts="<?= (int)$c['ts'] ?>" data-l-days="<?= h(t('Tage')) ?>" data-l-done="<?= h(t('Geschafft!')) ?>"><?= h(t('%d Tage', max(0, (int)ceil(($c['ts'] - time()) / 86400)))) ?></p>
        <p><?= h(smLongDate($c['ts'])) ?></p>
<?php break; case 'datecalc': ?>
<?php if ($c['mode'] === 'diff'): ?>
        <p class="ia__label"><?= h(t('Datumsrechner')) ?></p>
        <p class="ia__big"><?= h(t('%s Tage', smFmtNumber(abs($c['days'])))) ?></p>
        <p><?= h(smLongDate($c['a'])) ?> → <?= h(smLongDate($c['b'])) ?></p>
        <p class="muted small"><?= h(t('Das sind %d Wochen und %d Tage, davon %d Werktage (Montag bis Freitag, ohne Feiertage). Der Endtag zählt nicht mit.', $c['weeks'], $c['rest'], $c['work'])) ?></p>
<?php elseif ($c['mode'] === 'add'): ?>
        <p class="ia__label"><?= h(t('Datumsrechner')) ?></p>
        <p class="ia__big"><?= h(smLongDate($c['b'])) ?></p>
        <p class="muted small"><?= h(smDate($c['a'])) ?> <?= h($c['sign']) ?> <?= h($c['n'] . ' ' . ['day' => t('Tage'), 'week' => t('Wochen'), 'month' => t('Monate'), 'year' => t('Jahre')][$c['unit']]) ?>
            · <?= h($c['days'] === 0 ? t('das ist heute') : ($c['days'] > 0 ? t('in %d Tagen', $c['days']) : t('vor %d Tagen', -$c['days']))) ?> · <?= h(t('KW %d', (int)date('W', $c['b']))) ?></p>
<?php elseif ($c['mode'] === 'weekday'): ?>
        <p class="ia__label"><?= h(t('Wochentag')) ?></p>
        <p class="ia__big"><?= h(smLongDate($c['a'])) ?></p>
        <p class="muted small"><?= h(t('KW %d', $c['kw'])) ?> · <?= h(t('%d. Tag des Jahres, noch %d Tage bis Jahresende', $c['doy'], $c['left'])) ?>
            · <?= h($c['days'] === 0 ? t('das ist heute') : ($c['days'] > 0 ? t('in %d Tagen', $c['days']) : t('vor %d Tagen', -$c['days']))) ?><?php if ($c['holiday'] !== ''): ?> · <strong><?= h($c['holiday']) ?></strong><?php endif; ?></p>
<?php elseif ($c['mode'] === 'work'): ?>
        <p class="ia__label"><?= h(t('Arbeitstage')) ?> · <?= h($c['state'] !== '' ? smStates()[$c['state']] : t('nur bundesweite Feiertage')) ?></p>
        <p class="ia__big"><?= h(t('%s Arbeitstage', smFmtNumber($c['work']))) ?></p>
        <p><?= h(smLongDate($c['a'])) ?> → <?= h(smLongDate($c['b'])) ?></p>
        <p class="muted small"><?= h(t('Montag bis Freitag, ohne gesetzliche Feiertage, Anfangs- und Endtag zählen mit. Insgesamt %d Kalendertage.', $c['days'])) ?></p>
<?php if ($c['hol']): ?>
        <p class="muted small"><?= h(t('Feiertage an Werktagen:')) ?> <?= h(implode(', ', array_map(function ($x) { return $x[1] . ' (' . smDate($x[0]) . ')'; }, $c['hol']))) ?></p>
<?php endif; ?>
<?php elseif ($c['mode'] === 'addwork'): ?>
        <p class="ia__label"><?= h(t('Datumsrechner')) ?> · <?= h($c['state'] !== '' ? smStates()[$c['state']] : t('nur bundesweite Feiertage')) ?></p>
        <p class="ia__big"><?= h(smLongDate($c['b'])) ?></p>
        <p class="muted small"><?= h(smDate($c['a'])) ?> <?= h($c['sign']) ?> <?= h(t('%d Arbeitstage', $c['n'])) ?>
            · <?= h($c['days'] === 0 ? t('das ist heute') : ($c['days'] > 0 ? t('in %d Tagen', $c['days']) : t('vor %d Tagen', -$c['days']))) ?> · <?= h(t('KW %d', (int)date('W', $c['b']))) ?></p>
<?php else: ?>
        <p class="ia__label"><?= h(t('Alter')) ?></p>
        <p class="ia__big"><?= h(t('%d Jahre', $c['years'])) ?></p>
        <p><?= h(t('Geboren am %s', smLongDate($c['a']))) ?></p>
        <p class="muted small"><?= h($c['until'] === 0 ? t('Heute ist der Geburtstag! Herzlichen Glückwunsch zum %d.', $c['years']) : t('Nächster Geburtstag: %s, in %d Tagen (wird %d)', smLongDate($c['next']), $c['until'], $c['turns'])) ?>
            · <?= h(t('%s Tage auf der Welt', smFmtNumber($c['lived']))) ?></p>
<?php endif; ?>
<?php break; case 'lotto': ?>
        <p class="ia__label"><?= h($c['game'] === 'euro' ? t('Eurojackpot: 5 aus 50 und 2 aus 12') : t('Lotto 6 aus 49 mit Superzahl')) ?></p>
        <p class="ia__dice ia__balls"><?php foreach ($c['main'] as $n): ?><span class="ball"><?= (int)$n ?></span><?php endforeach; ?>
            <?php foreach ($c['extra'] as $n): ?><span class="ball ball--extra" title="<?= h($c['game'] === 'euro' ? t('Eurozahl') : t('Superzahl')) ?>"><?= (int)$n ?></span><?php endforeach; ?></p>
        <p><a class="btn btn--small" href="<?= h(!empty($inTools) ? smToolUrl('zufall', ['z' => $c['game'] === 'euro' ? 'euro' : 'lotto']) : smUrl(['q' => $q])) ?>"><?= h(t('Neue Zahlen')) ?></a>
            <span class="muted small"><?= h(t('Zufällig gezogen, ohne Gewähr auf Gewinn.')) ?></span></p>
<?php break; case 'card': ?>
        <p class="ia__label"><?= h(t('Gezogene Karte')) ?></p>
        <p class="ia__playcard<?= $c['red'] ? ' is-red' : '' ?>"><span><?= h($c['rank']) ?></span><span><?= h($c['suit']) ?></span></p>
        <p><a class="btn btn--small" href="<?= h(!empty($inTools) ? smToolUrl('zufall', ['z' => 'card']) : smUrl(['q' => $q])) ?>"><?= h(t('Neue Karte')) ?></a></p>
<?php break; case 'yesno': ?>
        <p class="ia__label"><?= h(t('Ja oder nein?')) ?></p>
        <p class="ia__big"><?= h($c['yes'] ? t('Ja!') : t('Nein!')) ?></p>
        <p><a class="btn btn--small" href="<?= h(!empty($inTools) ? smToolUrl('zufall', ['z' => 'yesno']) : smUrl(['q' => $q])) ?>"><?= h(t('Nochmal fragen')) ?></a></p>
<?php break; case 'bridges': ?>
        <p class="ia__label"><?= h(t('Brückentage %d', $c['year'])) ?> · <?= h($c['state'] !== '' ? smStates()[$c['state']] : t('nur bundesweite Feiertage')) ?></p>
<?php if (!$c['list']): ?>
        <p><?= h(t('Keine lohnenden Brückentage gefunden.')) ?></p>
<?php else: ?>
        <table class="table ia__table">
            <tr><th><?= h(t('Zeitraum')) ?></th><th><?= h(t('Urlaub')) ?></th><th><?= h(t('Frei')) ?></th></tr>
<?php foreach ($c['list'] as $b): ?>
            <tr<?= $b['to'] < mktime(0, 0, 0) ? ' class="is-past"' : '' ?>><td><strong><?= h(smDate($b['from'])) ?> – <?= h(smDate($b['to'])) ?></strong><br><small class="muted"><?= h(implode(', ', $b['names'])) ?> · <?= h(t('Urlaub nehmen: %s', implode(', ', array_map(function ($t) { return smShortDay($t) . ' ' . date(smLang() === 'de' ? 'j.n.' : 'n/j', $t); }, $b['days'])))) ?></small></td>
                <td><?= h(t('%d Tage', $b['vacation'])) ?></td><td><strong><?= h(t('%d Tage', $b['total'])) ?></strong></td></tr>
<?php endforeach; ?>
        </table>
<?php endif; ?>
        <p class="muted small"><?= h(t('Gerechnet mit Montag bis Freitag als Arbeitstage und den gesetzlichen Feiertagen. Schulferien sind nicht berücksichtigt.')) ?></p>
<?php break; case 'yearcal': ?>
        <p class="ia__label"><?= h(t('Jahreskalender %d', $c['year'])) ?> · <?= h($c['state'] !== '' ? smStates()[$c['state']] : t('bundesweite Feiertage')) ?>
            <button type="button" class="btn btn--small" data-print><?= h(t('Drucken')) ?></button></p>
        <div class="yearcal">
<?php $wd = smLang() === 'de' ? ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] : ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']; $today = date('Y-m-d');
for ($mo = 1; $mo <= 12; $mo++): $first = mktime(0, 0, 0, $mo, 1, $c['year']); $dim = (int)date('t', $first); $off = (int)date('N', $first) - 1; ?>
            <table class="yearcal__m"><caption><?= h(smMonthName($mo)) ?></caption>
                <tr><th class="yearcal__kw"><?= h(t('KW')) ?></th><?php foreach ($wd as $w): ?><th><?= h($w) ?></th><?php endforeach; ?></tr>
<?php for ($cell = 0; $cell < $off + $dim; $cell += 7): ?>
                <tr><td class="yearcal__kw"><?= (int)date('W', mktime(0, 0, 0, $mo, max(1, $cell - $off + 1), $c['year'])) ?></td>
<?php for ($k = 0; $k < 7; $k++): $dd = $cell + $k - $off + 1; if ($dd < 1 || $dd > $dim): ?><td></td><?php else: $key = sprintf('%04d-%02d-%02d', $c['year'], $mo, $dd); $hn = $c['hol'][$key] ?? ''; ?>
                    <td class="<?= $k >= 5 ? 'is-we ' : '' ?><?= $hn !== '' ? 'is-hol ' : '' ?><?= $key === $today ? 'is-today' : '' ?>"<?= $hn !== '' ? ' title="' . h($hn) . '"' : '' ?>><?= $dd ?></td>
<?php endif; endfor; ?>
                </tr>
<?php endfor; ?>
            </table>
<?php endfor; ?>
        </div>
<?php break; case 'holidays': ?>
        <p class="ia__label"><?= h(t('Feiertage %d', $c['year'])) ?> · <?= h($c['state'] !== '' ? smStates()[$c['state']] : t('alle Bundesländer')) ?></p>
<?php if ($c['next']): ?>        <p><?= h(t('Nächster Feiertag: %s, %s', $c['next']['name'], smLongDate($c['next']['ts']))) ?> <span class="muted">(<?= h(t('in %d Tagen', max(0, smDayDiff(mktime(0, 0, 0), $c['next']['ts'])))) ?>)</span></p><?php endif; ?>
        <table class="table ia__table">
<?php foreach ($c['list'] as $hd): ?>
            <tr<?= $hd['ts'] < mktime(0, 0, 0) ? ' class="is-past"' : '' ?>><td><?= h(smLongDate($hd['ts'])) ?></td><td><strong><?= h($hd['name']) ?></strong>
<?php if ($c['state'] === '' && $hd['where'] !== ''): ?><br><small class="muted"><?= h(t('nur in: %s', $hd['where'])) ?></small><?php endif; ?>
<?php if ($hd['note'] !== ''): ?><br><small class="muted"><?= h($hd['note']) ?></small><?php endif; ?></td></tr>
<?php endforeach; ?>
        </table>
        <p class="muted small"><?= h(t('Gesetzliche Feiertage, ohne Gewähr. Regionale Besonderheiten (z. B. Augsburger Friedensfest) fehlen.')) ?></p>
<?php break; case 'weather': ?>
        <p class="ia__label"><?= h(t('Wetter')) ?><?php if (!empty($c['geo'])): ?> · <?= h(implode(', ', array_filter([$c['geo']['name'], $c['geo']['region'] !== $c['geo']['name'] ? $c['geo']['region'] : '', $c['geo']['country']]))) ?><?php endif; ?></p>
<?php if ($c['error'] === 'noplace'): ?>
        <form method="get" action="<?= h(smBase()) ?>" class="ia__form"><input type="hidden" name="wx" value="1">
            <input type="text" name="q" placeholder="<?= h(t('Ort, z. B. Berlin')) ?>" required maxlength="60" aria-label="<?= h(t('Ort')) ?>">
            <button type="submit" class="btn btn--small btn--primary"><?= h(t('Wetter zeigen')) ?></button>
        </form>
        <p class="muted small"><?= h(t('Tipp: Unter Einstellungen → Suche kannst du deinen Ort hinterlegen, dann reicht „wetter“.')) ?></p>
<?php elseif ($c['error'] !== ''): ?>
        <p><?= h($c['error']) ?></p>
<?php else: [$ico, $txt] = smWeatherLook($c['now']['look']); ?>
        <div class="wx">
            <div class="wx__now"><span class="wx__ico" aria-hidden="true"><?= $ico ?></span><span class="wx__temp"><?= h(smFmtNumber(round($c['now']['temp']))) ?>&nbsp;°C</span>
                <span class="wx__txt"><?= h($txt) ?><br><small class="muted"><?= h(t('Wind %s km/h', smFmtNumber(round($c['now']['wind'])))) ?><?= $c['now']['hum'] !== null ? ' · ' . h(t('Luftfeuchte %d %%', $c['now']['hum'])) : '' ?></small></span></div>
            <ol class="wx__days">
<?php foreach ($c['days'] as $i => $d): [$di, $dt] = smWeatherLook($d['look']); ?>
                <li><strong><?= h($i === 0 ? t('Heute') : smShortDay($d['date'])) ?></strong><span class="wx__ico" title="<?= h($dt) ?>" aria-label="<?= h($dt) ?>"><?= $di ?></span>
                    <span><?= h(smFmtNumber(round($d['max']))) ?>° <small class="muted"><?= h(smFmtNumber(round($d['min']))) ?>°</small></span>
                    <small class="muted">💧 <?= (int)$d['prob'] ?> %<?= $d['rain'] > 0 ? ' · ' . h(smFmtNumber($d['rain'])) . ' mm' : '' ?></small></li>
<?php endforeach; ?>
            </ol>
        </div>
<?php if (!empty($c['more'])): $mo = $c['more']; ?>
<?php foreach ($mo['alerts'] as $al): ?>
        <div class="wx__alert wx__alert--<?= h($al['level']) ?>"><strong>⚠️ <?= h($al['head'] !== '' ? $al['head'] : $al['event']) ?></strong>
            <small><?= h($al['from'] ? date(smLang() === 'de' ? 'd.m. H:i' : 'm/d H:i', $al['from']) : '') ?><?= $al['to'] ? ' – ' . h(date(smLang() === 'de' ? 'd.m. H:i' : 'm/d H:i', $al['to'])) : '' ?></small>
            <?php if ($al['text'] !== ''): ?><p><?= h($al['text']) ?></p><?php endif; ?><?php if ($al['todo'] !== ''): ?><p class="muted small"><?= h($al['todo']) ?></p><?php endif; ?></div>
<?php endforeach; ?>
<?php if (!$mo['alerts'] && ($c['geo']['cc'] ?? '') === 'DE'): ?>
        <p class="small wx__ok">✓ <?= h(t('Keine Unwetterwarnungen des DWD für diesen Ort.')) ?></p>
<?php endif; ?>
<?php if ($mo['uv'] !== null || $mo['rise'] !== ''): ?>
        <p class="small"><?php if ($mo['uv'] !== null): $uv = (float)$mo['uv']; ?><?= h(t('UV-Index heute: %s (%s)', smFmtNumber(round($uv, 1)), $uv < 3 ? t('niedrig') : ($uv < 6 ? t('mäßig') : ($uv < 8 ? t('hoch') : ($uv < 11 ? t('sehr hoch') : t('extrem')))))) ?><?php endif; ?>
            <?php if ($mo['rise'] !== ''): ?><?= $mo['uv'] !== null ? ' · ' : '' ?>🌅 <?= h($mo['rise']) ?> · 🌇 <?= h($mo['set']) ?><?php endif; ?></p>
<?php endif; ?>
<?php if ($mo['hours']): ?>
        <p class="ia__label"><?= h(t('Die nächsten 24 Stunden')) ?></p>
        <ol class="wx__hours">
<?php foreach ($mo['hours'] as $hh): [$hi, $ht] = smWeatherLook($hh['look']); ?>
            <li title="<?= h($ht) ?>"><small><?= h($hh['h']) ?></small><span class="wx__ico"><?= $hi ?></span><strong><?= h(smFmtNumber(round($hh['temp']))) ?>°</strong><small class="muted">💧<?= (int)$hh['prob'] ?></small></li>
<?php endforeach; ?>
        </ol>
<?php endif; ?>
<?php if (count($mo['week']) > 4): ?>
        <p class="ia__label"><?= h(t('7 Tage')) ?></p>
        <table class="table ia__table wx__week">
<?php foreach ($mo['week'] as $i => $wd): [$wi, $wt] = smWeatherLook($wd['look']); ?>
            <tr><td><strong><?= h($i === 0 ? t('Heute') : smShortDay($wd['date']) . ' ' . date(smLang() === 'de' ? 'j.n.' : 'n/j', $wd['date'])) ?></strong></td><td><span class="wx__ico" title="<?= h($wt) ?>"><?= $wi ?></span> <?= h($wt) ?></td>
                <td><?= h(smFmtNumber(round($wd['max']))) ?>° / <span class="muted"><?= h(smFmtNumber(round($wd['min']))) ?>°</span></td><td class="muted">💧 <?= (int)$wd['prob'] ?> %<?= $wd['rain'] > 0 ? ' · ' . h(smFmtNumber($wd['rain'])) . ' mm' : '' ?></td><td class="muted">UV <?= h(smFmtNumber(round($wd['uv']))) ?></td></tr>
<?php endforeach; ?>
        </table>
<?php endif; ?>
        <p class="muted small"><?= h(t('Stunden, 7 Tage und UV: Open-Meteo.com (CC BY 4.0). Warnungen: Deutscher Wetterdienst über Bright Sky.')) ?></p>
<?php endif; ?>
        <p class="muted small"><?= !empty($c['wanted']) && $c['wanted'] !== $c['src'] ? h(t('%s hat für diesen Ort keine Daten, darum:', smWeatherSources()[$c['wanted']])) . ' ' : '' ?><?= h(t('Quelle: %s', $c['credit'])) ?><?= $c['station'] !== '' ? ' · ' . h(t('Station %s', $c['station'])) : '' ?></p>
<?php endif; ?>
        <p class="ia__switch small"><?= h(t('Andere Quelle:')) ?>
<?php foreach (smWeatherSources() as $k => $v): ?>
            <a class="chip<?= $k === $c['src'] ? ' is-active' : '' ?>" href="<?= h(smUrl(['q' => $q, 'ws' => $k])) ?>"><?= h($v) ?></a>
<?php endforeach; ?>
<?php if (!empty($c['geo'])): ?>
            <a class="chip" href="https://www.windy.com/<?= h($c['geo']['lat']) ?>/<?= h($c['geo']['lon']) ?>" rel="noopener noreferrer" target="_blank">Windy ↗</a>
<?php if ($c['geo']['cc'] === 'DE'): ?><a class="chip" href="https://www.dwd.de/DE/wetter/warnungen_gemeinden/warnWetter_node.html" rel="noopener noreferrer" target="_blank"><?= h(t('DWD-Unwetterwarnungen')) ?> ↗</a><?php endif; ?>
<?php endif; ?></p>
<?php break; endswitch; ?>
<?php if (empty($inTools)): ?>
        <p class="ia__web"><a href="<?= h($web) ?>" data-load><?= h(t('Trotzdem im Web suchen')) ?> →</a></p>
<?php endif; ?>
    </section>
