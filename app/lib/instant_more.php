<?php
/* Suchmaschine – weitere Sofort-Antworten (seit 2.0): Lotto, Eurojackpot, Karte ziehen, Ja oder nein,
   Wochentag, Arbeitstage mit Feiertagen, Arbeitstage addieren, Brückentage-Planer, Jahreskalender.
   Alles ohne Suchdienst, Zufall kommt aus random_int (kryptografisch sicher). */
defined('SM_VERSION') || exit;

function smCardMore(string $l, ?array $me): ?array
{
    if (preg_match('/^(lotto|lottozahlen|6 ?aus ?49|lotto 6 ?aus ?49)$/u', $l)) {
        return ['type' => 'lotto', 'game' => 'lotto', 'main' => smDrawUnique(6, 1, 49), 'extra' => [random_int(0, 9)]];
    }
    if (preg_match('/^(eurojackpot|euro ?jackpot|eurojackpot zahlen)$/u', $l)) {
        return ['type' => 'lotto', 'game' => 'euro', 'main' => smDrawUnique(5, 1, 50), 'extra' => smDrawUnique(2, 1, 12)];
    }
    if (preg_match('/^(karte ziehen|spielkarte|zufällige karte|draw a card|random card|pick a card)$/u', $l)) {
        $suits = ['♠', '♥', '♦', '♣'];
        $ranks = ['2', '3', '4', '5', '6', '7', '8', '9', '10', smLang() === 'de' ? 'B' : 'J', smLang() === 'de' ? 'D' : 'Q', 'K', 'A'];
        $s = random_int(0, 3);
        return ['type' => 'card', 'suit' => $suits[$s], 'rank' => $ranks[random_int(0, 12)], 'red' => $s === 1 || $s === 2];
    }
    if (preg_match('/^(ja oder nein|ja nein|yes or no|yes no|soll ich)\??$/u', $l)) {
        return ['type' => 'yesno', 'yes' => random_int(0, 1) === 1];
    }
    $d = '(\d{1,2}\.\d{1,2}\.\d{2,4}|\d{4}-\d{1,2}-\d{1,2}|heute|today|morgen|tomorrow|gestern|yesterday)';
    if (preg_match('/^(?:wochentag|welcher tag ist|weekday|day of week)(?:\s+(?:am|von|of|on))?\s+' . $d . '$/u', $l, $m)) {
        $a = smParseDate($m[1]);
        if ($a === null) return null;
        $hol = smHolidayNames((int)date('Y', $a), '');
        return ['type' => 'datecalc', 'mode' => 'weekday', 'a' => $a, 'kw' => (int)date('W', $a), 'doy' => (int)date('z', $a) + 1, 'left' => (int)date('L', $a) + 365 - (int)date('z', $a) - 1,
                'holiday' => $hol[date('Y-m-d', $a)] ?? '', 'days' => smDayDiff(mktime(0, 0, 0), $a)];
    }
    $st = '(?:\s+(?:in\s+)?([a-zäöü][a-zäöü -]{1,40}))?';
    if (preg_match('/^(?:arbeitstage|werktage|workdays|working days|business days)\s+(?:zwischen|between|von|from)\s+' . $d . '\s+(?:und|and|bis|to|until)\s+' . $d . $st . '$/u', $l, $m)) {
        $a = smParseDate($m[1]);
        $b = smParseDate($m[2]);
        $state = smStateCode($m[3] ?? '');
        if ($a === null || $b === null || (($m[3] ?? '') !== '' && $state === '')) return null;
        if ($a > $b) [$a, $b] = [$b, $a];
        if ($b - $a > 3660 * 86400) return null;
        [$work, $hits] = smWorkdays($a, $b, $state);
        return ['type' => 'datecalc', 'mode' => 'work', 'a' => $a, 'b' => $b, 'state' => $state, 'work' => $work, 'days' => smDayDiff($a, $b) + 1, 'hol' => $hits];
    }
    if (preg_match('/^' . $d . '\s*([+-])\s*(\d{1,4})\s*(?:arbeitstage?n?|werktage?n?|workdays?|business days?)' . $st . '$/u', $l, $m)) {
        $a = smParseDate($m[1]);
        $state = smStateCode($m[4] ?? '');
        if ($a === null || (($m[4] ?? '') !== '' && $state === '')) return null;
        $n = (int)$m[3];
        $step = $m[2] === '-' ? -1 : 1;
        $t = $a;
        $set = [];
        for ($i = 0, $guard = 0; $i < $n && $guard < 20000; $guard++) {
            $t = strtotime(($step > 0 ? '+' : '-') . '1 day', $t);
            $y = (int)date('Y', $t);
            if (!isset($set[$y])) $set[$y] = smHolidayNames($y, $state);
            if ((int)date('N', $t) <= 5 && !isset($set[$y][date('Y-m-d', $t)])) $i++;
        }
        return ['type' => 'datecalc', 'mode' => 'addwork', 'a' => $a, 'b' => $t, 'sign' => $m[2], 'n' => $n, 'state' => $state, 'days' => smDayDiff(mktime(0, 0, 0), $t)];
    }
    if (preg_match('/^(?:brückentage|brueckentage|brückentag|bridge days)(?:\s+(\d{4}))?' . $st . '(?:\s+(\d{4}))?$/u', $l, $m)) {
        $y = (int)(($m[1] ?? '') !== '' ? $m[1] : (($m[3] ?? '') !== '' ? $m[3] : date('Y')));
        $state = smStateCode($m[2] ?? '');
        if ($y < 1995 || $y > 2100 || (($m[2] ?? '') !== '' && $state === '')) return null;
        if ($state === '' && $me) $state = (string)($me['prefs']['state'] ?? '');
        return ['type' => 'bridges', 'year' => $y, 'state' => $state, 'list' => smBridgeDays($y, $state)];
    }
    if (preg_match('/^(?:(?:jahreskalender|year calendar)(?:\s+(\d{4}))?|(?:kalender|calendar)\s+(\d{4}))' . $st . '$/u', $l, $m)) {
        if (($m[1] ?? '') === '' && ($m[2] ?? '') !== '') $m[1] = $m[2];
        $m[2] = $m[3] ?? '';
        $y = (int)(($m[1] ?? '') !== '' ? $m[1] : date('Y'));
        $state = smStateCode($m[2] ?? '');
        if ($y < 1970 || $y > 2100 || (($m[2] ?? '') !== '' && $state === '')) return null;
        if ($state === '' && $me) $state = (string)($me['prefs']['state'] ?? '');
        return ['type' => 'yearcal', 'year' => $y, 'state' => $state, 'hol' => smHolidayNames($y, $state)];
    }
    return null;
}

/** $k verschiedene Zahlen zwischen $lo und $hi, sortiert */
function smDrawUnique(int $k, int $lo, int $hi): array
{
    $pool = range($lo, $hi);
    $out = [];
    for ($i = 0; $i < $k; $i++) {
        $j = random_int(0, count($pool) - 1);
        $out[] = $pool[$j];
        array_splice($pool, $j, 1);
    }
    sort($out);
    return $out;
}

/** Feiertage eines Jahres als ['Y-m-d' => Name]: bundesweite und die des Bundeslands (ohne Land nur bundesweite) */
function smHolidayNames(int $y, string $state): array
{
    $out = [];
    foreach (smHolidays($y) as [$ts, $name, $where]) {
        if ($where === '' || ($state !== '' && in_array($state, explode(' ', $where), true))) {
            // Oster- und Pfingstsonntag fallen ohnehin auf einen Sonntag
            $out[date('Y-m-d', $ts)] = $name;
        }
    }
    return $out;
}

/** Arbeitstage (Mo–Fr ohne Feiertage) von $a bis $b einschließlich. Liefert [Zahl, Feiertage im Zeitraum an Werktagen] */
function smWorkdays(int $a, int $b, string $state): array
{
    $n = 0;
    $hits = [];
    $sets = [];
    for ($t = $a, $i = 0; $t <= $b && $i < 4000; $t = strtotime('+1 day', $t), $i++) {
        $y = (int)date('Y', $t);
        if (!isset($sets[$y])) $sets[$y] = smHolidayNames($y, $state);
        if ((int)date('N', $t) > 5) continue;
        $k = date('Y-m-d', $t);
        if (isset($sets[$y][$k])) { $hits[] = [$t, $sets[$y][$k]]; continue; }
        $n++;
    }
    return [$n, $hits];
}

/**
 * Brückentage-Planer: Wo bringen wenige Urlaubstage viele freie Tage am Stück?
 * Liefert Vorschläge [from, to, total, vacation, days (Urlaubstage), names (Feiertage)], beste zuerst, je Feiertag höchstens zwei.
 */
function smBridgeDays(int $y, string $state): array
{
    $hol = smHolidayNames($y - 1, $state) + smHolidayNames($y, $state) + smHolidayNames($y + 1, $state);
    $start = mktime(0, 0, 0, 12, 1, $y - 1);
    $days = [];   // Index => [ts, frei?, Feiertagsname]
    for ($t = $start, $i = 0; $i < 430; $t = strtotime('+1 day', $t), $i++) {
        $k = date('Y-m-d', $t);
        $days[$i] = [$t, (int)date('N', $t) >= 6 || isset($hol[$k]), $hol[$k] ?? ''];
    }
    $out = [];
    $seen = [];
    foreach ($days as $hi => [$ht, $free, $hname]) {
        if ($hname === '' || (int)date('N', $ht) >= 6 || (int)date('Y', $ht) !== $y) continue;
        $cands = [];
        for ($s = max(0, $hi - 9); $s <= $hi; $s++) {
            for ($e = $hi; $e <= min(count($days) - 1, $hi + 9); $e++) {
                $need = [];
                for ($i = $s; $i <= $e; $i++) if (!$days[$i][1]) $need[] = $days[$i][0];
                if (count($need) < 1 || count($need) > 5) continue;
                // Block bis zum nächsten Arbeitstag ausdehnen
                $a = $s;
                while ($a > 0 && $days[$a - 1][1]) $a--;
                $b = $e;
                while ($b < count($days) - 1 && $days[$b + 1][1]) $b++;
                $total = $b - $a + 1;
                $names = [];
                for ($i = $a; $i <= $b; $i++) if ($days[$i][2] !== '' && (int)date('N', $days[$i][0]) <= 5) $names[] = $days[$i][2];
                $cands[] = ['from' => $days[$a][0], 'to' => $days[$b][0], 'total' => $total, 'vacation' => count($need), 'days' => $need, 'names' => array_values(array_unique($names)), 'ratio' => $total / count($need)];
            }
        }
        usort($cands, function ($x, $z) { return [$z['ratio'], $z['total']] <=> [$x['ratio'], $x['total']]; });
        $taken = 0;
        foreach ($cands as $c) {
            $key = $c['from'] . '-' . $c['to'];
            if (isset($seen[$key]) || $c['total'] < 4) continue;
            $seen[$key] = true;
            $out[] = $c;
            if (++$taken >= 2) break;
        }
    }
    usort($out, function ($x, $z) { return [$z['ratio'], $z['total']] <=> [$x['ratio'], $x['total']]; });
    $out = array_slice($out, 0, 14);
    usort($out, function ($x, $z) { return $x['from'] <=> $z['from']; });
    return $out;
}
