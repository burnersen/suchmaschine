<?php
/* Suchmaschine – die vier Brave-Pläne einheitlich: Search, Autosuggest, Spellcheck, Answers.
   Gezählt wird überall in Requests (wie im Brave-Dashboard). Die Dollar rechnet die Suchmaschine daraus aus, genau wie Brave:
   jeder Plan hat 5 $ Guthaben im Monat, Beträge werden auf volle Cent abgerundet angezeigt. */
defined('SM_VERSION') || exit;

/** Monatsguthaben pro Plan in Dollar */
const SM_PLAN_CREDIT = 5.0;

/**
 * Die Pläne, in der Reihenfolge des Dashboards. price = Dollar pro Request (bei Answers ein Richtwert,
 * dort zählt der echte Durchschnitt, sobald es Antworten gab), limit = Standard-Bremse in Requests.
 */
function smPlans(): array
{
    return [
        'brave'   => ['plan' => 'Search', 'name' => t('Suche'), 'price' => 0.005, 'limit' => 950,
                      'desc' => t('Die Suche selbst: Web, Bilder, News und Videos aus Braves eigenem Index. 5 $ reichen für 1.000 Requests.')],
        'suggest' => ['plan' => 'Autosuggest', 'name' => t('Suchvorschläge'), 'price' => 0.0005, 'limit' => 9950,
                      'desc' => t('Vorschläge beim Tippen, nur für angemeldete Konten. 5 $ reichen für 10.000 Requests.')],
        'spell'   => ['plan' => 'Spellcheck', 'name' => t('Rechtschreibhilfe'), 'price' => 0.0005, 'limit' => 9950,
                      'desc' => t('„Meintest du …?“, nur wenn Brave nicht schon selbst korrigiert hat. 5 $ reichen für 10.000 Requests.')],
        'answer'  => ['plan' => 'Answers', 'name' => t('KI-Antwort'), 'price' => 0.06, 'limit' => 70,
                      'desc' => t('Kurze Antwort über den Treffern, auf Knopfdruck oder automatisch. Brave rechnet hier auch nach Textmenge ab, eine Antwort kostet etwa 6 Cent. 5 $ reichen für etwa 80 Requests.')],
    ];
}

function smPlanKey(string $id): string
{
    return trim($id === 'brave' ? smProvGet('brave', 'key') : smExGet($id, 'key'));
}

function smPlanOn(string $id): bool
{
    return ($id === 'brave' ? smProvGet('brave', 'on') : smExGet($id, 'on')) !== '0';
}

/** Monatsanfang (Y-m-d), ab dem gezählt wird. Search folgt dem Reset-Datum, das Brave meldet (in der Regel der Monatserste). */
function smPlanFrom(string $id): string
{
    return $id === 'brave' ? smProvCycle('brave')['from'] : date('Y-m-01');
}

/** Nächster Reset (Unix-Zeit) */
function smPlanReset(string $id): int
{
    return $id === 'brave' ? smProvCycle('brave')['end'] : mktime(0, 0, 0, (int)date('n') + 1, 1, (int)date('Y'));
}

/** Requests im laufenden Monat */
function smPlanRequests(string $id): float
{
    return $id === 'brave' ? smUsageSum('brave', smPlanFrom('brave')) : smExUsed($id);
}

/** Bremse in Requests (0 = ohne Grenze) */
function smPlanLimit(string $id): int
{
    return $id === 'brave' ? (int)smProvGet('brave', 'limit') : (int)smExLimit($id);
}

/** Dollar pro Request. Bei Answers der echte Durchschnitt dieses Monats (Brave meldet den Preis jeder Antwort mit). */
function smPlanPrice(string $id): float
{
    if ($id === 'answer') {
        $a = json_decode(smExGet('answer', 'usd'), true);
        if (is_array($a) && ($a['m'] ?? '') === date('Y-m') && (int)($a['n'] ?? 0) > 0) return max(0.001, (float)$a['c'] / (int)$a['n']);
    }
    return smPlans()[$id]['price'];
}

/** Echten Preis einer Antwort merken (für den Durchschnitt) */
function smPlanAddCost(string $id, float $usd): void
{
    $a = json_decode(smExGet($id, 'usd'), true);
    if (!is_array($a) || ($a['m'] ?? '') !== date('Y-m')) $a = ['m' => date('Y-m'), 'c' => 0.0, 'n' => 0];
    $a['c'] = round((float)$a['c'] + $usd, 5);
    $a['n'] = (int)$a['n'] + 1;
    smExSet($id, 'usd', json_encode($a));
}

/** Auf volle Cent abrunden, wie das Brave-Dashboard */
function smCents(float $v): float
{
    return floor($v * 100 + 1e-6) / 100;
}

/**
 * Alles für die Karte wie im Brave-Dashboard: Requests, Credits used/remaining, Prozent, Burn rate, Runway, Bremse, Reset.
 */
function smPlanStats(string $id): array
{
    $req = smPlanRequests($id);
    $usd = $req * smPlanPrice($id);
    // Burn rate wie bei Brave: ab dem ersten Tag, an dem in diesem Monat wirklich gesucht wurde
    $first = smQ('SELECT MIN(day) FROM usage_log WHERE provider = ? AND user_id <> -1 AND day >= ?', [$id === 'brave' ? 'brave' : 'x_' . $id, smPlanFrom($id)])->fetchColumn();
    $start = strtotime($first ?: smPlanFrom($id));
    $days = max(1.0, (time() - $start) / 86400);
    $burn = $usd / $days;
    $remaining = max(0.0, SM_PLAN_CREDIT - $usd);
    $limit = smPlanLimit($id);
    return [
        'requests' => $req, 'limit' => $limit, 'left' => $limit > 0 ? max(0.0, $limit - $req) : INF,
        'used' => smCents($usd), 'remaining' => smCents($remaining), 'pct' => (int)floor(min(1.0, $usd / SM_PLAN_CREDIT) * 100),
        'burn' => smCents($burn), 'runway' => $burn > 0 ? (int)floor($remaining / $burn) : null, 'reset' => smPlanReset($id),
        'stop' => $limit > 0 && $req >= $limit,
    ];
}

/** Requests laut Dashboard übernehmen: der Zähler steht danach genau auf $n */
function smPlanSetRequests(string $id, int $n): void
{
    if ($id === 'brave') smProvSetUsed('brave', (float)$n);
    else smExSetUsed($id, (float)$n, $n);
}

/** Gestört und pausiert? Liefert die Uhrzeit, bis wann, oder 0 */
function smPlanPausedUntil(string $id): int
{
    $t = $id === 'brave' ? (int)smProvStat('brave')['pause_until'] : (int)smExGet($id, 'pause');
    return $t > time() ? $t : 0;
}

/**
 * Speichert die Pläne aus einem Formular (Verwaltung → Suchdienste oder → Verbrauch).
 * pkey[id], preq[id], plimit[id], pon[id] (mit pshown[id]), pdel = id, cache[brave]. Liefert einen Fehlertext oder ''.
 */
function smSavePlans(): string
{
    $err = '';
    $del = is_string($_POST['pdel'] ?? null) ? $_POST['pdel'] : '';
    foreach (smPlans() as $id => $pl) {
        $label = $pl['plan'];
        if ($del === $id) {
            if ($id === 'brave') smProvSet('brave', 'key', ''); else smExSet($id, 'key', '');
            continue;
        }
        $key = trim((string)($_POST['pkey'][$id] ?? ''));
        if ($key !== '') {
            if (!preg_match('/^[\x21-\x7e]{8,300}$/', $key)) $err = t('%s: Der API-Key sieht nicht richtig aus.', $label);
            elseif (($e = $id === 'brave' ? smProvTest('brave', $key) : smExTest($id, $key)) !== '') $err = t('%s: %s Der Key wurde nicht gespeichert.', $label, $e);
            elseif ($id === 'brave') { smProvSet('brave', 'key', $key); smProvUnpause('brave'); smProvOk('brave', 0); }
            else { smExSet($id, 'key', $key); smExSet($id, 'pause', '0'); }
        }
        if (isset($_POST['plimit'][$id]) && is_string($_POST['plimit'][$id]) && trim($_POST['plimit'][$id]) !== '') {
            $l = smParseAmount($_POST['plimit'][$id]);
            if ($l === null) $err = t('%s: Das Limit muss eine Zahl sein.', $label);
            else {
                $l = (string)(int)max(0, min(10000000, round($l)));
                if ($id === 'brave') smProvSet('brave', 'limit', $l); else smExSet($id, 'limit', $l);
            }
        }
        if (!empty($_POST['pshown'][$id])) {
            $on = empty($_POST['pon'][$id]) ? '0' : '1';
            if ($id === 'brave') smProvSet('brave', 'on', $on); else smExSet($id, 'on', $on);
        }
        if (isset($_POST['preq'][$id]) && is_string($_POST['preq'][$id]) && trim($_POST['preq'][$id]) !== '') {
            $n = smParseAmount($_POST['preq'][$id]);
            if ($n === null) $err = t('%s: Die Requests laut Dashboard müssen eine Zahl sein.', $label);
            else smPlanSetRequests($id, (int)max(0, min(10000000, round($n))));
        }
    }
    if (isset($_POST['cache']['brave'])) smProvSet('brave', 'cache', (string)max(0, min(60, (int)$_POST['cache']['brave'])));
    return $err;
}

/** Warnhinweise für Administratoren: Pläne, bei denen es knapp wird oder die Bremse schon greift */
function smPlanWarnings(): array
{
    $pct = max(1, min(100, (int)smSetting('warn_pct'))) / 100;
    $w = [];
    foreach (smPlans() as $id => $pl) {
        if (smPlanKey($id) === '' || !smPlanOn($id)) continue;
        $s = smPlanStats($id);
        if ($s['limit'] <= 0) continue;
        if ($s['stop']) $w[] = t('%s: Bremse erreicht, neu ab %s.', $pl['plan'], smDate($s['reset']));
        elseif ($s['requests'] / $s['limit'] >= $pct) $w[] = t('%s: %d %% der Bremse verbraucht, noch %s Requests bis %s.', $pl['plan'], (int)round($s['requests'] / $s['limit'] * 100), smNumF($s['left']), smDate($s['reset']));
    }
    return $w;
}
