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
            · <?= h(t('Referenzkurs der EZB vom %s (über frankfurter.app), wird einmal am Tag aktualisiert.', $c['date'] !== '' ? smDate((int)strtotime($c['date'])) : '–')) ?></p>
        <form class="ia__form" method="get" action="<?= h(smBase()) ?>">
            <input type="text" inputmode="decimal" name="fx_a" value="<?= h($nf($c['amount'])) ?>" aria-label="<?= h(t('Betrag')) ?>">
            <select name="fx_f" aria-label="<?= h(t('Von')) ?>"><?php foreach ($c['list'] as $cc): ?><option<?= $cc === $c['from'] ? ' selected' : '' ?>><?= h($cc) ?></option><?php endforeach; ?></select>
            <span>→</span>
            <select name="fx_t" aria-label="<?= h(t('Nach')) ?>"><?php foreach ($c['list'] as $cc): ?><option<?= $cc === $c['to'] ? ' selected' : '' ?>><?= h($cc) ?></option><?php endforeach; ?></select>
            <button type="submit" class="btn btn--small"><?= h(t('Umrechnen')) ?></button>
        </form>
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
            <a class="btn btn--small btn--ghost" href="<?= h(smUrl(['a' => 'tools', 'qr' => $c['text']])) ?>#qr"><?= h(t('Bearbeiten')) ?></a></p>
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
        <div class="timer" data-timer data-sec="<?= (int)$c['sec'] ?>" data-done="<?= h(t('Zeit ist um!')) ?>">
            <p class="ia__big timer__show" data-timer-show><?= $c['watch'] ? '00:00' : h(sprintf('%02d:%02d', intdiv($c['sec'], 60), $c['sec'] % 60)) ?></p>
            <p><button type="button" class="btn btn--primary btn--small" data-timer-start data-label-pause="<?= h(t('Pause')) ?>" data-label-go="<?= h(t('Start')) ?>"><?= h(t('Start')) ?></button>
               <button type="button" class="btn btn--small" data-timer-reset><?= h(t('Zurücksetzen')) ?></button></p>
            <noscript><p class="muted small"><?= h(t('Der Timer braucht JavaScript.')) ?></p></noscript>
        </div>
        <p class="muted small"><?= h(t('Auch: „timer 90 s“, „timer 1,5 h“, „stoppuhr“. Am Ende piept es, solange der Tab offen ist.')) ?></p>
<?php break; case 'countdown': ?>
        <p class="ia__label"><?= h(t('Countdown bis %s', $c['label'])) ?></p>
        <p class="ia__big" data-countdown data-ts="<?= (int)$c['ts'] ?>" data-l-days="<?= h(t('Tage')) ?>" data-l-done="<?= h(t('Geschafft!')) ?>"><?= h(t('%d Tage', max(0, (int)ceil(($c['ts'] - time()) / 86400)))) ?></p>
        <p><?= h(smLongDate($c['ts'])) ?></p>
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
            <div class="wx__now"><span class="wx__ico" aria-hidden="true"><?= $ico ?></span><span class="wx__temp"><?= h(smFmtNumber(round($c['now']['temp']))) ?> °C</span>
                <span class="wx__txt"><?= h($txt) ?><br><small class="muted"><?= h(t('Wind %s km/h', smFmtNumber(round($c['now']['wind'])))) ?><?= $c['now']['hum'] !== null ? ' · ' . h(t('Luftfeuchte %d %%', $c['now']['hum'])) : '' ?></small></span></div>
            <ol class="wx__days">
<?php foreach ($c['days'] as $i => $d): [$di, $dt] = smWeatherLook($d['look']); ?>
                <li><strong><?= h($i === 0 ? t('Heute') : smShortDay($d['date'])) ?></strong><span class="wx__ico" title="<?= h($dt) ?>" aria-label="<?= h($dt) ?>"><?= $di ?></span>
                    <span><?= h(smFmtNumber(round($d['max']))) ?>° <small class="muted"><?= h(smFmtNumber(round($d['min']))) ?>°</small></span>
                    <small class="muted">💧 <?= (int)$d['prob'] ?> %<?= $d['rain'] > 0 ? ' · ' . h(smFmtNumber($d['rain'])) . ' mm' : '' ?></small></li>
<?php endforeach; ?>
            </ol>
        </div>
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
        <p class="ia__web"><a href="<?= h($web) ?>" data-load><?= h(t('Trotzdem im Web suchen')) ?> →</a></p>
    </section>
