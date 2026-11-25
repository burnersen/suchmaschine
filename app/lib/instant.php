<?php
/* Suchmaschine – Sofort-Antworten: werden vor jedem Suchdienst erkannt und kosten darum kein Guthaben.
   Rechner (eigener Parser, kein eval), Währungen (EZB-Kurse über Frankfurter, einmal am Tag), Einheiten, eigene IP,
   Kalenderwoche, Datum und Uhrzeit, Wetter (Bright Sky/DWD, Open-Meteo, wttr.in), Würfel, Münze, Zufallszahl,
   Timer, Countdown, Passwort-Generator, QR-Code, Kurzlinks. Dazu die Wikipedia-Kurzinfo für Suchdienste ohne Infobox. */
defined('SM_VERSION') || exit;

/**
 * Erkennt eine Sofort-Antwort. Liefert ['type' => …, …] oder null (dann wird ganz normal gesucht).
 */
function smInstant(string $q, ?array $me): ?array
{
    $raw = trim($q);
    $l = mb_strtolower(preg_replace('/\s+/u', ' ', $raw));
    $l = rtrim($l, '?! ');
    if ($l === '' || mb_strlen($l) > 300) return null;

    // Feste Kurzbefehle
    if (preg_match('/^(meine ip|meine ip-?adresse|was ist meine ip(-?adresse)?|my ip|my ip address|what is my ip( address)?|ip|ip-?adresse)$/u', $l)) {
        $ip = smClientIp();
        return ['type' => 'ip', 'ip' => $ip, 'v6' => strpos($ip, ':') !== false];
    }
    if (preg_match('/^(?:kw|kalenderwoche|welche kw(?: ist heute| haben wir)?|aktuelle kw|welche kalenderwoche(?: ist heute| haben wir)?|week|week number|calendar week|current week)(?:\s+(?<w>\d{1,2}))?(?:\s+(?<y>\d{4}))?$/u', $l, $m)) {
        return smCardWeek(isset($m['w']) && $m['w'] !== '' ? (int)$m['w'] : 0, isset($m['y']) && $m['y'] !== '' ? (int)$m['y'] : 0);
    }
    if (preg_match('/^(datum|uhrzeit|zeit|heute|welcher tag ist heute|wie spät ist es|wie viel uhr ist es|wieviel uhr ist es|date|time|today|what time is it|what day is it|what is the date)$/u', $l)) {
        return ['type' => 'time', 'ts' => time()];
    }
    if (preg_match('/^(passwort|password|passwort-?generator|password generator|pw|passphrase|kennwort)(\s+(\d{1,3}))?$/u', $l, $m)) {
        return ['type' => 'password', 'len' => isset($m[3]) ? max(6, min(128, (int)$m[3])) : 20, 'phrase' => $m[1] === 'passphrase'];
    }
    if (preg_match('/^(qr|qr-?code)\s+(.+)$/isu', $raw, $m)) {
        $text = trim($m[2]);
        if ($text !== '' && strlen($text) <= SM_QR_MAX) return ['type' => 'qr', 'text' => $text];
    }
    if (preg_match('/^(kurz|kürzen|kurzlink|short|shorten|shortlink)\s+(\S+)$/iu', $raw, $m)) {
        return ['type' => 'short', 'url' => smCleanShortTarget($m[2]), 'raw' => $m[2]];
    }
    if (preg_match('/^(münze|muenze|münzwurf|kopf oder zahl|coin|coin ?flip|flip a coin|heads or tails)$/u', $l)) {
        return ['type' => 'coin', 'heads' => random_int(0, 1) === 1];
    }
    if (preg_match('/^(?:(würfel|wuerfel|würfeln|dice|roll|roll dice)(?:\s+(\d{1,2}))?(?:\s*[wd](\d{1,4}))?|(\d{1,2})\s*[wd]\s*(\d{1,4}))$/u', $l, $m)) {
        $n = (int)(($m[4] ?? '') !== '' ? $m[4] : (($m[2] ?? '') !== '' ? $m[2] : 1));
        $f = (int)(($m[5] ?? '') !== '' ? $m[5] : (($m[3] ?? '') !== '' ? $m[3] : 6));
        $n = max(1, min(20, $n));
        $f = max(2, min(1000, $f));
        $rolls = [];
        for ($i = 0; $i < $n; $i++) $rolls[] = random_int(1, $f);
        return ['type' => 'dice', 'n' => $n, 'faces' => $f, 'rolls' => $rolls, 'sum' => array_sum($rolls)];
    }
    if (preg_match('/^(zufallszahl|zufall|random|random number|zahl zwischen)(?:\s+(-?\d{1,12})\s*(?:-|bis|und|to|and|\.\.)\s*(-?\d{1,12}))?$/u', $l, $m)) {
        $a = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 1;
        $b = isset($m[3]) && $m[3] !== '' ? (int)$m[3] : 100;
        if ($a > $b) [$a, $b] = [$b, $a];
        return ['type' => 'random', 'min' => $a, 'max' => $b, 'value' => random_int($a, $b)];
    }
    if (preg_match('/^(timer|stoppuhr|stopwatch|wecker)(?:\s+(\d{1,4}(?:[.,]\d+)?)\s*(s|sek|sekunden|sec|secs|seconds?|m|min|mins|minuten|minutes?|h|std|stunden|hours?)?)?$/u', $l, $m)) {
        $sec = 0;
        if (!empty($m[2])) {
            $v = (float)str_replace(',', '.', $m[2]);
            $u = $m[3] ?? 'min';
            $mul = preg_match('/^(s|sek|sekunden|sec|secs|second|seconds)$/', $u) ? 1 : (preg_match('/^(h|std|stunden|hour|hours)$/', $u) ? 3600 : 60);
            $sec = (int)min(359999, round($v * $mul));
        }
        return ['type' => 'timer', 'sec' => $m[1] === 'stoppuhr' || $m[1] === 'stopwatch' ? 0 : $sec, 'watch' => $sec === 0];
    }
    if (preg_match('/^(countdown|wie lange (?:noch )?bis|wie viele tage (?:noch )?bis|tage bis|days until|how long until|time until)\s+(.+)$/u', $l, $m)) {
        $c = smCountdownTarget($m[2]);
        if ($c) return ['type' => 'countdown', 'ts' => $c[0], 'label' => $c[1]];
    }
    if (preg_match('/^(wetter|weather|wettervorhersage|wetterbericht|forecast|weather forecast)(?:\s+(?:in|für|fuer|for|bei))?(?:\s+(.{2,60}))?$/u', $l, $m)) {
        return smCardWeather(trim($m[2] ?? ''), $me);
    }

    // Währungen und Einheiten: „50 usd in eur“, „100 km in meilen“
    if (preg_match('/^(-?[\d.,]*\d)?\s*([^\d\s][^\s]*?(?:\s[a-zäöü]+)?)\s+(?:in|to|nach|zu|=|->|→|als|into)\s+([^\s].*?)$/u', $l, $m)) {
        $amount = ($m[1] ?? '') !== '' ? smParseNumber($m[1]) : 1.0;
        if ($amount !== null) {
            if ($c = smCardCurrency($amount, $m[2], $m[3])) return $c;
            if (($m[1] ?? '') !== '' && ($c = smCardUnit($amount, $m[2], $m[3]))) return $c;
        }
    }
    if (preg_match('/^(-?[\d.,]*\d)\s*([€$£¥]|[a-z]{3})\s*$/u', $l, $m) && ($amount = smParseNumber($m[1])) !== null) {
        // „100 usd“: in Euro umrechnen (bzw. Euro in Dollar)
        $from = smCurrencyCode($m[2]);
        if ($from !== '' && ($c = smCardCurrency($amount, $m[2], $from === 'EUR' ? 'usd' : 'eur'))) return $c;
    }

    // Rechner zuletzt: nur wenn der Text wirklich wie eine Rechnung aussieht
    if ($c = smCardCalc($raw)) return $c;
    return null;
}

/* ---------- Rechner: eigener kleiner Parser, kein eval ---------- */

/** Zahl mit Komma oder Punkt („1.234,5“, „1,234.5“, „3,5“, „3.5“). null = keine Zahl. */
function smParseNumber(string $s): ?float
{
    $s = trim($s);
    if (!preg_match('/^-?[\d.,]+$/', $s) || !preg_match('/\d/', $s)) return null;
    $neg = $s[0] === '-';
    $s = ltrim($s, '-');
    $dot = strrpos($s, '.');
    $com = strrpos($s, ',');
    if ($dot !== false && $com !== false) {
        $dec = $dot > $com ? '.' : ',';
        $s = str_replace($dec === '.' ? ',' : '.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif ($com !== false) {
        // Nur Kommas: Tausendertrennung im Englischen („1,000,000“), sonst Dezimalkomma
        if (smLang() === 'en' && preg_match('/^\d{1,3}(,\d{3})+$/', $s)) $s = str_replace(',', '', $s);
        elseif (substr_count($s, ',') > 1) $s = str_replace(',', '', $s);
        else $s = str_replace(',', '.', $s);
    } elseif ($dot !== false) {
        // Nur Punkte: „1.000.000“ und im Deutschen „1.000“ sind Tausender, sonst Dezimalpunkt
        if (preg_match('/^[1-9]\d{0,2}(\.\d{3}){2,}$/', $s) || (smLang() === 'de' && preg_match('/^[1-9]\d{0,2}\.\d{3}$/', $s))) $s = str_replace('.', '', $s);
        elseif (substr_count($s, '.') > 1) return null;
    }
    if (!is_numeric($s)) return null;
    return $neg ? -(float)$s : (float)$s;
}

/** Zahl hübsch für die Anzeige, bis zu 12 gültige Stellen */
function smFmtNumber(float $v, int $maxDec = 10): string
{
    if (!is_finite($v)) return '∞';
    if ($v != 0 && (abs($v) >= 1e15 || abs($v) < 1e-7)) {
        return str_replace(['.', 'E+', 'E'], [smLang() === 'de' ? ',' : '.', ' × 10^', ' × 10^'], sprintf('%.8E', $v));
    }
    $dec = max(0, min($maxDec, 12 - (int)floor(log10(max(abs($v), 1e-12))) - 1));
    $s = number_format(round($v, $dec), $dec, smLang() === 'de' ? ',' : '.', smLang() === 'de' ? '.' : ',');
    if ($dec > 0) $s = rtrim(rtrim($s, '0'), smLang() === 'de' ? ',' : '.');
    return $s === '-0' ? '0' : $s;
}

function smCardCalc(string $raw): ?array
{
    $e = trim($raw);
    $e = preg_replace('/^=\s*|\s*=\s*$/u', '', $e);
    $e = str_replace(['×', '·', '÷', '−', '–', '√', '²', '³', 'π'], ['*', '*', '/', '-', '-', 'sqrt', '^2', '^3', 'pi'], $e);
    $low = mb_strtolower($e);
    // „10 % von 200“, „15% of 80“
    $low = preg_replace('/([\d.,]+)\s*%\s*(?:von|of|vom)\s*([\d.,]+)/u', '($2*$1/100)', $low);
    $low = preg_replace('/\b(wurzel|wurzel aus|sqrt of|root)\b\s*/u', 'sqrt', $low);
    if (!preg_match('/^[\d\s.,+\-*\/^%()a-z]+$/', $low)) return null;
    if (preg_match('/[a-z]/', preg_replace('/sqrt|pi|mod/', '', $low))) return null;
    if (!preg_match('/\d|pi/', $low)) return null;
    // Muss nach Rechnung aussehen: ein Operator zwischen Werten, eine Funktion, oder ein = am Anfang
    $isCalc = preg_match('/[\d)i]\s*([+*\/^%]|mod|-(?=\s*[\d(sp]))\s*[\d(sp.]?/', $low) || strpos($low, 'sqrt') !== false || preg_match('/^\s*=/', $raw);
    if (!$isCalc) return null;
    // Nichts, was eher ein Datum, eine Telefonnummer oder eine Version ist
    if (preg_match('/^\d{1,4}([.\/-])\d{1,2}\1\d{1,4}$/', trim($low)) || preg_match('/^\d{3,}(-\d{2,}){2,}$/', trim($low))
        || preg_match('/^(19|20)\d\d\s*[-\/]\s*(19|20)?\d\d$/', trim($low))) return null;
    $p = new SmCalc($low);
    try {
        $v = $p->run();
    } catch (RuntimeException $ex) {
        return $ex->getMessage() === 'div0' ? ['type' => 'calc', 'expr' => trim($raw), 'result' => t('nicht definiert (Division durch 0)'), 'value' => ''] : null;
    }
    if (!is_finite($v)) return null;
    return ['type' => 'calc', 'expr' => trim(preg_replace('/^=\s*|\s*=\s*$/u', '', $raw)), 'result' => smFmtNumber($v), 'value' => rtrim(rtrim(sprintf('%.12F', $v), '0'), '.')];
}

/**
 * Rechnet + - * / ^ % mod, Klammern, sqrt(), pi. Prozent wie im Taschenrechner: 200 + 10% = 220, 50 * 10% = 5.
 * Rekursiver Abstieg, keine Ausführung von Code.
 */
class SmCalc
{
    private $t = [];
    private $i = 0;

    public function __construct(string $s)
    {
        $pos = 0;
        $len = strlen($s);
        while ($pos < $len) {
            if (!preg_match('/\s*(\d[\d.,]*|\.\d+|sqrt|pi|mod|[+\-*\/^%()])\s*/A', $s, $m, 0, $pos)) throw new RuntimeException('syntax');
            $this->t[] = $m[1];
            $pos += strlen($m[0]);
            if (count($this->t) > 200) throw new RuntimeException('long');
        }
    }

    public function run(): float
    {
        $v = $this->expr(0);
        if ($this->i < count($this->t)) throw new RuntimeException('syntax');
        return $v;
    }

    private function peek(): ?string { return $this->t[$this->i] ?? null; }
    private function next(): ?string { return $this->t[$this->i++] ?? null; }

    private function expr(int $depth): float
    {
        if ($depth > 50) throw new RuntimeException('deep');
        $v = $this->term($depth);
        while (($op = $this->peek()) === '+' || $op === '-') {
            $this->i++;
            [$r, $pct] = $this->termPct($depth);
            $r = $pct ? $v * $r / 100 : $r;     // 200 + 10% → 200 + 20
            $v = $op === '+' ? $v + $r : $v - $r;
        }
        return $v;
    }

    /** Ein Summand. Steht er allein als „x%“ da, merkt sich das der Aufrufer. */
    private function termPct(int $depth): array
    {
        $start = $this->i;
        $v = $this->term($depth);
        $pct = $this->i - $start === 2 && ($this->t[$start + 1] ?? '') === '%';
        return $pct ? [$v * 100, true] : [$v, false];
    }

    private function term(int $depth): float
    {
        $v = $this->power($depth);
        while (true) {
            $op = $this->peek();
            if ($op === '*' || $op === '/' || $op === 'mod') {
                $this->i++;
                $r = $this->power($depth);
                if ($op === '*') $v *= $r;
                elseif ($r == 0) throw new RuntimeException('div0');
                elseif ($op === '/') $v /= $r;
                else $v = fmod($v, $r);
            } elseif ($op === '(' || $op === 'sqrt' || $op === 'pi') {
                $v *= $this->power($depth);          // 2(3+4), 2pi
            } else {
                return $v;
            }
        }
    }

    private function power(int $depth): float
    {
        $b = $this->unary($depth);
        if ($this->peek() === '^') {
            $this->i++;
            $e = $this->power($depth + 1);
            if (abs($e) > 1000) throw new RuntimeException('big');
            $b = $b ** $e;
            if (is_nan($b)) throw new RuntimeException('nan');
        }
        return $b;
    }

    private function unary(int $depth): float
    {
        $t = $this->peek();
        if ($t === '-') { $this->i++; return -$this->unary($depth); }
        if ($t === '+') { $this->i++; return $this->unary($depth); }
        $v = $this->primary($depth);
        while ($this->peek() === '%') {
            // „x%“ ist x/100, außer direkt als Summand (das erledigt termPct)
            $this->i++;
            $v /= 100;
        }
        return $v;
    }

    private function primary(int $depth): float
    {
        $t = $this->next();
        if ($t === null) throw new RuntimeException('end');
        if ($t === '(') {
            $v = $this->expr($depth + 1);
            if ($this->next() !== ')') throw new RuntimeException('paren');
            return $v;
        }
        if ($t === 'pi') return M_PI;
        if ($t === 'sqrt') {
            $v = $this->unary($depth + 1);
            if ($v < 0) throw new RuntimeException('nan');
            return sqrt($v);
        }
        $n = smParseNumber($t);
        if ($n === null) throw new RuntimeException('num');
        return $n;
    }
}

/* ---------- Währungen: Kurse der EZB über Frankfurter, einmal am Tag geholt und gespeichert ---------- */

function smCurrencyCode(string $s): string
{
    $s = trim(mb_strtolower($s), " .");
    $alias = [
        '€' => 'EUR', 'euro' => 'EUR', 'euros' => 'EUR', 'eur' => 'EUR',
        '$' => 'USD', 'dollar' => 'USD', 'dollars' => 'USD', 'us-dollar' => 'USD', 'us dollar' => 'USD', 'usd' => 'USD',
        '£' => 'GBP', 'pfund' => 'GBP', 'pound' => 'GBP', 'pounds' => 'GBP', 'gbp' => 'GBP',
        '¥' => 'JPY', 'yen' => 'JPY', 'franken' => 'CHF', 'franc' => 'CHF', 'francs' => 'CHF', 'fr.' => 'CHF',
        'kronen' => 'SEK', 'złoty' => 'PLN', 'zloty' => 'PLN', 'yuan' => 'CNY', 'renminbi' => 'CNY', 'lira' => 'TRY', 'rubel' => 'RUB',
        'forint' => 'HUF', 'rupie' => 'INR', 'rupee' => 'INR', 'real' => 'BRL',
    ];
    if (isset($alias[$s])) return $alias[$s];
    if (preg_match('/^[a-z]{3}$/', $s)) {
        $up = strtoupper($s);
        return $up === 'EUR' || isset(smFxRates()['rates'][$up]) ? $up : '';
    }
    return '';
}

/** Kurse mit Euro als Basis: ['date' => …, 'rates' => ['USD' => 1.05, …]]. Leer, wenn nicht abrufbar. */
function smFxRates(): array
{
    static $fx = null;
    if ($fx !== null) return $fx;
    $d = smFetchJson('https://api.frankfurter.dev/v1/latest', 43200);
    $fx = is_array($d) && is_array($d['rates'] ?? null) ? ['date' => (string)($d['date'] ?? ''), 'rates' => array_filter($d['rates'], 'is_numeric')] : ['date' => '', 'rates' => []];
    return $fx;
}

function smCardCurrency(float $amount, string $from, string $to): ?array
{
    $to = preg_replace('/^(euro|dollar|us-dollar|pfund|franken)\s*$/u', '$1', trim($to));
    $f = smCurrencyCode($from);
    $t = smCurrencyCode($to);
    if ($f === '' || $t === '' || abs($amount) > 1e12) return null;
    $fx = smFxRates();
    if (!$fx['rates']) return ['type' => 'currency', 'error' => t('Die Wechselkurse sind gerade nicht abrufbar.'), 'from' => $f, 'to' => $t, 'amount' => $amount];
    $rate = function (string $c) use ($fx) { return $c === 'EUR' ? 1.0 : (float)($fx['rates'][$c] ?? 0); };
    if ($rate($f) <= 0 || $rate($t) <= 0) return null;
    $one = $rate($t) / $rate($f);
    return ['type' => 'currency', 'from' => $f, 'to' => $t, 'amount' => $amount, 'result' => $amount * $one, 'rate' => $one, 'date' => $fx['date'],
            'list' => array_merge(['EUR'], array_keys($fx['rates']))];
}

/* ---------- Einheiten ---------- */

/** Einheit => [Gruppe, Faktor zur Grundeinheit, Anzeige] */
function smUnits(): array
{
    $u = [];
    $add = function (string $g, array $names, float $f, string $show) use (&$u) { foreach ($names as $n) $u[$n] = [$g, $f, $show]; };
    // Länge (Meter)
    $add('len', ['mm', 'millimeter'], 0.001, 'mm');
    $add('len', ['cm', 'zentimeter', 'centimeter'], 0.01, 'cm');
    $add('len', ['dm', 'dezimeter'], 0.1, 'dm');
    $add('len', ['m', 'meter', 'metre', 'meters'], 1, 'm');
    $add('len', ['km', 'kilometer', 'kilometre', 'kilometers'], 1000, 'km');
    $add('len', ['in', 'inch', 'inches', 'zoll', '"'], 0.0254, 'in');
    $add('len', ['ft', 'foot', 'feet', 'fuß', 'fuss'], 0.3048, 'ft');
    $add('len', ['yd', 'yard', 'yards'], 0.9144, 'yd');
    $add('len', ['mi', 'mile', 'miles', 'meile', 'meilen'], 1609.344, 'mi');
    $add('len', ['nmi', 'seemeile', 'seemeilen', 'nautical mile', 'nautical miles'], 1852, 'nmi');
    // Gewicht (Kilogramm)
    $add('mass', ['mg', 'milligramm', 'milligram'], 1e-6, 'mg');
    $add('mass', ['g', 'gramm', 'gram', 'grams'], 0.001, 'g');
    $add('mass', ['kg', 'kilo', 'kilogramm', 'kilogram', 'kilograms'], 1, 'kg');
    $add('mass', ['t', 'tonne', 'tonnen', 'ton', 'tons'], 1000, 't');
    $add('mass', ['oz', 'unze', 'unzen', 'ounce', 'ounces'], 0.028349523125, 'oz');
    $add('mass', ['lb', 'lbs', 'pound-mass', 'pounds-mass'], 0.45359237, 'lb');
    $add('mass', ['st', 'stone'], 6.35029318, 'st');
    // Datenmengen (Byte)
    $add('data', ['bit', 'bits'], 0.125, 'bit');
    $add('data', ['kbit', 'kilobit'], 125, 'kbit');
    $add('data', ['mbit', 'megabit'], 125000, 'Mbit');
    $add('data', ['gbit', 'gigabit'], 125000000, 'Gbit');
    $add('data', ['b', 'byte', 'bytes'], 1, 'B');
    $add('data', ['kb', 'kilobyte', 'kilobytes'], 1e3, 'kB');
    $add('data', ['mb', 'megabyte', 'megabytes'], 1e6, 'MB');
    $add('data', ['gb', 'gigabyte', 'gigabytes'], 1e9, 'GB');
    $add('data', ['tb', 'terabyte', 'terabytes'], 1e12, 'TB');
    $add('data', ['pb', 'petabyte', 'petabytes'], 1e15, 'PB');
    $add('data', ['kib', 'kibibyte'], 1024, 'KiB');
    $add('data', ['mib', 'mebibyte'], 1048576, 'MiB');
    $add('data', ['gib', 'gibibyte'], 1073741824, 'GiB');
    $add('data', ['tib', 'tebibyte'], 1099511627776, 'TiB');
    // Volumen (Liter)
    $add('vol', ['ml', 'milliliter'], 0.001, 'ml');
    $add('vol', ['cl', 'zentiliter'], 0.01, 'cl');
    $add('vol', ['dl', 'deziliter'], 0.1, 'dl');
    $add('vol', ['l', 'liter', 'litre', 'liters'], 1, 'l');
    $add('vol', ['m3', 'm³', 'kubikmeter'], 1000, 'm³');
    $add('vol', ['gal', 'gallon', 'gallons', 'gallone', 'gallonen'], 3.785411784, 'gal');
    $add('vol', ['qt', 'quart'], 0.946352946, 'qt');
    $add('vol', ['pt', 'pint', 'pints'], 0.473176473, 'pt');
    $add('vol', ['cup', 'cups', 'tasse', 'tassen'], 0.2365882365, 'cup');
    $add('vol', ['floz', 'fl oz', 'fluid ounce'], 0.0295735295625, 'fl oz');
    // Geschwindigkeit (Meter pro Sekunde)
    $add('speed', ['m/s', 'mps'], 1, 'm/s');
    $add('speed', ['km/h', 'kmh', 'kph', 'stundenkilometer'], 1 / 3.6, 'km/h');
    $add('speed', ['mph'], 0.44704, 'mph');
    $add('speed', ['kn', 'knoten', 'knot', 'knots'], 1852 / 3600, 'kn');
    // Fläche (Quadratmeter)
    $add('area', ['cm2', 'cm²'], 1e-4, 'cm²');
    $add('area', ['m2', 'm²', 'qm', 'quadratmeter'], 1, 'm²');
    $add('area', ['ha', 'hektar', 'hectare', 'hectares'], 1e4, 'ha');
    $add('area', ['km2', 'km²', 'quadratkilometer'], 1e6, 'km²');
    $add('area', ['ft2', 'ft²', 'sqft'], 0.09290304, 'ft²');
    $add('area', ['acre', 'acres'], 4046.8564224, 'acre');
    // Zeit (Sekunden)
    $add('time', ['ms', 'millisekunden', 'milliseconds'], 0.001, 'ms');
    $add('time', ['s', 'sek', 'sekunde', 'sekunden', 'sec', 'second', 'seconds'], 1, 's');
    $add('time', ['min', 'minute', 'minuten', 'minutes'], 60, 'min');
    $add('time', ['h', 'std', 'stunde', 'stunden', 'hour', 'hours'], 3600, 'h');
    $add('time', ['tag', 'tage', 'tagen', 'd', 'day', 'days'], 86400, t('Tage'));
    $add('time', ['woche', 'wochen', 'week', 'weeks'], 604800, t('Wochen'));
    $add('time', ['jahr', 'jahre', 'jahren', 'year', 'years'], 31557600, t('Jahre'));
    // Temperatur (eigene Formeln)
    foreach (['c' => '°C', '°c' => '°C', 'celsius' => '°C', 'grad' => '°C', 'grad celsius' => '°C', 'f' => '°F', '°f' => '°F', 'fahrenheit' => '°F', 'k' => 'K', 'kelvin' => 'K'] as $n => $s) {
        $u[$n] = ['temp', 0, $s];
    }
    return $u;
}

function smCardUnit(float $v, string $from, string $to): ?array
{
    $u = smUnits();
    $from = trim(mb_strtolower($from));
    $to = trim(mb_strtolower($to));
    if (!isset($u[$from], $u[$to]) || $u[$from][0] !== $u[$to][0]) return null;
    [$g, $ff, $fs] = $u[$from];
    [, $tf, $ts] = $u[$to];
    if ($g === 'temp') {
        $c = $fs === '°C' ? $v : ($fs === '°F' ? ($v - 32) * 5 / 9 : $v - 273.15);
        $r = $ts === '°C' ? $c : ($ts === '°F' ? $c * 9 / 5 + 32 : $c + 273.15);
    } else {
        $r = $v * $ff / $tf;
    }
    return ['type' => 'unit', 'value' => $v, 'from' => $fs, 'to' => $ts, 'result' => $r, 'group' => $g];
}

/* ---------- Kalenderwoche ---------- */

function smCardWeek(int $week, int $year): array
{
    $year = $year >= 1900 && $year <= 2200 ? $year : (int)date('o');
    $cur = $week === 0;
    if ($cur) { $week = (int)date('W'); $year = (int)date('o'); }
    $max = (int)date('W', mktime(0, 0, 0, 12, 28, $year));
    $week = max(1, min($max, $week));
    $mon = (new DateTimeImmutable())->setISODate($year, $week, 1)->setTime(0, 0);
    return ['type' => 'week', 'week' => $week, 'year' => $year, 'from' => $mon->getTimestamp(), 'to' => $mon->modify('+6 days')->getTimestamp(),
            'current' => $cur, 'weeks' => $max];
}

/* ---------- Countdown ---------- */

/** Ziel aus Text: „24.12.“, „24.12.2026“, „2026-12-24“, „weihnachten“, „silvester“, „ostern“ … */
function smCountdownTarget(string $s): ?array
{
    $s = trim($s);
    $now = time();
    $y = (int)date('Y');
    $next = function (int $mo, int $d) use ($y, $now) { $t = mktime(0, 0, 0, $mo, $d, $y); return $t + 86400 <= $now ? mktime(0, 0, 0, $mo, $d, $y + 1) : $t; };
    $named = [
        'weihnachten' => [12, 24, t('Heiligabend')], 'heiligabend' => [12, 24, t('Heiligabend')], 'christmas' => [12, 25, 'Christmas'],
        'silvester' => [12, 31, t('Silvester')], 'neujahr' => [1, 1, t('Neujahr')], 'new year' => [1, 1, 'New Year'],
        'nikolaus' => [12, 6, t('Nikolaus')], 'valentinstag' => [2, 14, t('Valentinstag')], 'halloween' => [10, 31, 'Halloween'],
        'tag der deutschen einheit' => [10, 3, t('Tag der Deutschen Einheit')],
    ];
    if (isset($named[$s])) return [$next($named[$s][0], $named[$s][1]), $named[$s][2]];
    if ($s === 'ostern' || $s === 'easter') {
        $e = smEaster($y);
        if ($e + 86400 <= $now) $e = smEaster($y + 1);
        return [$e, t('Ostersonntag')];
    }
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})?$/', $s, $m) && checkdate((int)$m[2], (int)$m[1], isset($m[3]) && $m[3] !== '' ? (int)$m[3] : 2000)) {
        if (isset($m[3]) && $m[3] !== '') {
            $yy = (int)$m[3] < 100 ? 2000 + (int)$m[3] : (int)$m[3];
            return [mktime(0, 0, 0, (int)$m[2], (int)$m[1], $yy), smDate(mktime(0, 0, 0, (int)$m[2], (int)$m[1], $yy))];
        }
        $t = $next((int)$m[2], (int)$m[1]);
        return [$t, smDate($t)];
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        $t = mktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
        return [$t, smDate($t)];
    }
    return null;
}

/** Ostersonntag (Gaußsche Osterformel, ohne die calendar-Erweiterung) */
function smEaster(int $y): int
{
    $a = $y % 19; $b = intdiv($y, 100); $c = $y % 100; $d = intdiv($b, 4); $e = $b % 4; $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30; $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $mo = intdiv($h + $l - 7 * $m + 114, 31);
    $day = (($h + $l - 7 * $m + 114) % 31) + 1;
    return mktime(0, 0, 0, $mo, $day, $y);
}

/* ---------- Abrufen von kostenlosen Diensten (fest eingebaute Adressen, mit Zwischenspeicher) ---------- */

function smFetchJson(string $url, int $ttl, int $timeout = 5): ?array
{
    $ck = 'fetch|' . $url;
    if ($hit = smCacheGet($ck)) return $hit;
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => '', CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'Suchmaschine/' . SM_VERSION . ' (+https://github.com/' . SM_REPO . ')',
        CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => function ($c, $t, $d) { return $d > 3000000 ? 1 : 0; },
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $d = $status === 200 ? json_decode((string)$body, true) : null;
    if (!is_array($d)) return null;
    smCacheSet($ck, $d, $ttl);
    return $d;
}

/* ---------- Wetter: Bright Sky (Deutscher Wetterdienst), Open-Meteo (DWD-Modell ICON), wttr.in ---------- */

function smWeatherSources(): array
{
    return ['brightsky' => t('Deutscher Wetterdienst (Bright Sky)'), 'openmeteo' => t('Open-Meteo (DWD-Modell ICON)'), 'wttr' => 'wttr.in'];
}

/** Ort suchen (Open-Meteo Geocoding, kostenlos, ohne Key) */
function smGeocode(string $place): ?array
{
    $d = smFetchJson('https://geocoding-api.open-meteo.com/v1/search?' . http_build_query(['name' => $place, 'count' => 1, 'language' => smLang(), 'format' => 'json']), 86400 * 7);
    $r = $d['results'][0] ?? null;
    if (!is_array($r) || !isset($r['latitude'], $r['longitude'])) return null;
    return ['name' => (string)($r['name'] ?? $place), 'region' => (string)($r['admin1'] ?? ''), 'country' => (string)($r['country'] ?? ''),
            'cc' => (string)($r['country_code'] ?? ''), 'lat' => round((float)$r['latitude'], 3), 'lon' => round((float)$r['longitude'], 3)];
}

function smCardWeather(string $place, ?array $me): array
{
    $src = isset($_GET['ws']) && isset(smWeatherSources()[$_GET['ws']]) ? $_GET['ws']
        : (isset(smWeatherSources()[$me['prefs']['weather_src'] ?? '']) ? $me['prefs']['weather_src'] : smSetting('weather_src'));
    if (!isset(smWeatherSources()[$src])) $src = 'brightsky';
    if ($place === '') $place = trim((string)($me['prefs']['city'] ?? ''));
    $card = ['type' => 'weather', 'src' => $src, 'place' => $place, 'error' => '', 'now' => null, 'days' => []];
    if ($place === '') { $card['error'] = 'noplace'; return $card; }
    // Ohne Anmeldung zählt jede Wetterabfrage wie eine Suche (Bremse gegen Missbrauch des Servers)
    if (!$me && !smPublicQuota()) { $card['error'] = t('Für diese Stunde sind genug Suchen ohne Anmeldung gelaufen. Bitte später wieder, oder anmelden.'); return $card; }
    $geo = smGeocode($place);
    if (!$geo) { $card['error'] = t('Den Ort „%s“ habe ich nicht gefunden.', $place); return $card; }
    $card['geo'] = $geo;
    // Gewählte Quelle zuerst. Klappt es nicht (Bright Sky kennt z. B. nur Mitteleuropa), kommen die anderen dran.
    foreach (array_unique(array_merge([$src], array_keys(smWeatherSources()))) as $try) {
        $w = $try === 'brightsky' ? smWeatherBrightSky($geo) : ($try === 'openmeteo' ? smWeatherOpenMeteo($geo) : smWeatherWttr($geo));
        if ($w) return array_merge($card, $w, ['src' => $try, 'wanted' => $src]);
    }
    $card['error'] = t('Die Wetterdienste antworten gerade nicht. Bitte später noch einmal versuchen.');
    return $card;
}

/** Symbol und Text zu einer Wetterlage (eigene Kennung) */
function smWeatherLook(string $k): array
{
    $m = [
        'clear' => ['☀️', t('Klar')], 'night' => ['🌙', t('Klar')], 'partly' => ['⛅', t('Teils bewölkt')], 'cloudy' => ['☁️', t('Bewölkt')],
        'fog' => ['🌫️', t('Nebel')], 'drizzle' => ['🌦️', t('Nieselregen')], 'rain' => ['🌧️', t('Regen')], 'sleet' => ['🌨️', t('Schneeregen')],
        'snow' => ['❄️', t('Schnee')], 'hail' => ['🌨️', t('Hagel')], 'thunder' => ['⛈️', t('Gewitter')], 'wind' => ['💨', t('Windig')],
    ];
    return $m[$k] ?? ['🌡️', ''];
}

function smWeatherBrightSky(array $g): ?array
{
    $map = ['clear-day' => 'clear', 'clear-night' => 'night', 'partly-cloudy-day' => 'partly', 'partly-cloudy-night' => 'partly', 'cloudy' => 'cloudy',
            'fog' => 'fog', 'wind' => 'wind', 'rain' => 'rain', 'sleet' => 'sleet', 'snow' => 'snow', 'hail' => 'hail', 'thunderstorm' => 'thunder'];
    $ll = ['lat' => $g['lat'], 'lon' => $g['lon'], 'tz' => 'Europe/Berlin'];
    $cur = smFetchJson('https://api.brightsky.dev/current_weather?' . http_build_query($ll), 1200);
    $fc = smFetchJson('https://api.brightsky.dev/weather?' . http_build_query($ll + ['date' => date('Y-m-d'), 'last_date' => date('Y-m-d', strtotime('+4 days'))]), 1800);
    $c = $cur['weather'] ?? null;
    if (!is_array($c) || !is_array($fc['weather'] ?? null)) return null;
    $days = [];
    foreach ($fc['weather'] as $h) {
        if (!is_array($h) || !isset($h['timestamp'])) continue;
        $d = substr((string)$h['timestamp'], 0, 10);
        $days[$d]['t'][] = (float)($h['temperature'] ?? 0);
        $days[$d]['p'] = ($days[$d]['p'] ?? 0) + (float)($h['precipitation'] ?? 0);
        $days[$d]['pp'] = max($days[$d]['pp'] ?? 0, (int)($h['precipitation_probability'] ?? 0));
        $hr = (int)substr((string)$h['timestamp'], 11, 2);
        if ($hr >= 9 && $hr <= 18 && isset($h['icon'])) $days[$d]['i'][] = $map[$h['icon']] ?? 'cloudy';
    }
    $out = [];
    foreach ($days as $d => $x) {
        if (count($out) >= 4 || empty($x['t'])) continue;
        $ic = !empty($x['i']) ? array_search(max(array_count_values($x['i'])), array_count_values($x['i'])) : 'cloudy';
        $out[] = ['date' => strtotime($d), 'min' => min($x['t']), 'max' => max($x['t']), 'rain' => round($x['p'], 1), 'prob' => $x['pp'], 'look' => $ic];
    }
    return [
        'now' => ['temp' => (float)($c['temperature'] ?? 0), 'look' => $map[$c['icon'] ?? ''] ?? 'cloudy', 'wind' => (float)($c['wind_speed_10'] ?? $c['wind_speed_30'] ?? 0),
                  'hum' => isset($c['relative_humidity']) ? (int)$c['relative_humidity'] : null, 'rain' => (float)($c['precipitation_60'] ?? 0)],
        'days' => $out, 'station' => (string)($cur['sources'][0]['station_name'] ?? ''),
        'credit' => 'Deutscher Wetterdienst über Bright Sky', 'link' => 'https://brightsky.dev',
    ];
}

/** WMO-Wettercode (Open-Meteo) → eigene Kennung */
function smWmo(int $c): string
{
    if ($c === 0 || $c === 1) return 'clear';
    if ($c === 2) return 'partly';
    if ($c === 3) return 'cloudy';
    if ($c === 45 || $c === 48) return 'fog';
    if ($c >= 51 && $c <= 57) return 'drizzle';
    if (($c >= 61 && $c <= 67) || ($c >= 80 && $c <= 82)) return 'rain';
    if (($c >= 71 && $c <= 77) || $c === 85 || $c === 86) return 'snow';
    if ($c >= 95) return 'thunder';
    return 'cloudy';
}

function smWeatherOpenMeteo(array $g): ?array
{
    $d = smFetchJson('https://api.open-meteo.com/v1/forecast?' . http_build_query([
        'latitude' => $g['lat'], 'longitude' => $g['lon'], 'timezone' => 'auto', 'forecast_days' => 4, 'models' => 'icon_seamless',
        'current' => 'temperature_2m,weather_code,wind_speed_10m,relative_humidity_2m,precipitation,is_day',
        'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,precipitation_probability_max',
    ]), 1800);
    if (!is_array($d['current'] ?? null) || !is_array($d['daily']['time'] ?? null)) return null;
    $c = $d['current'];
    $look = smWmo((int)($c['weather_code'] ?? 3));
    if ($look === 'clear' && empty($c['is_day'])) $look = 'night';
    $days = [];
    foreach ($d['daily']['time'] as $i => $day) {
        $days[] = ['date' => strtotime((string)$day), 'min' => (float)($d['daily']['temperature_2m_min'][$i] ?? 0), 'max' => (float)($d['daily']['temperature_2m_max'][$i] ?? 0),
                   'rain' => (float)($d['daily']['precipitation_sum'][$i] ?? 0), 'prob' => (int)($d['daily']['precipitation_probability_max'][$i] ?? 0),
                   'look' => smWmo((int)($d['daily']['weather_code'][$i] ?? 3))];
    }
    return [
        'now' => ['temp' => (float)($c['temperature_2m'] ?? 0), 'look' => $look, 'wind' => (float)($c['wind_speed_10m'] ?? 0),
                  'hum' => isset($c['relative_humidity_2m']) ? (int)$c['relative_humidity_2m'] : null, 'rain' => (float)($c['precipitation'] ?? 0)],
        'days' => $days, 'station' => '', 'credit' => 'Open-Meteo.com (CC BY 4.0), Modell ICON des DWD', 'link' => 'https://open-meteo.com',
    ];
}

/** wttr.in: eigene Wettercodes (World Weather Online) → eigene Kennung */
function smWwo(int $c): string
{
    if ($c === 113) return 'clear';
    if ($c === 116) return 'partly';
    if ($c === 119 || $c === 122) return 'cloudy';
    if (in_array($c, [143, 248, 260], true)) return 'fog';
    if (in_array($c, [176, 263, 266, 281, 284, 293, 296], true)) return 'drizzle';
    if (in_array($c, [299, 302, 305, 308, 311, 314, 353, 356, 359], true)) return 'rain';
    if (in_array($c, [182, 185, 317, 320, 362, 365, 374, 377, 350], true)) return 'sleet';
    if (in_array($c, [179, 227, 230, 323, 326, 329, 332, 335, 338, 368, 371], true)) return 'snow';
    if (in_array($c, [200, 386, 389, 392, 395], true)) return 'thunder';
    return 'cloudy';
}

function smWeatherWttr(array $g): ?array
{
    $d = smFetchJson('https://wttr.in/' . $g['lat'] . ',' . $g['lon'] . '?format=j1&lang=' . smLang(), 1800, 8);
    $c = $d['current_condition'][0] ?? null;
    if (!is_array($c) || !is_array($d['weather'] ?? null)) return null;
    $days = [];
    foreach ($d['weather'] as $w) {
        if (!is_array($w)) continue;
        $codes = array_map(function ($h) { return (int)($h['weatherCode'] ?? 119); }, array_slice((array)($w['hourly'] ?? []), 3, 3));
        $prob = 0;
        $rain = 0.0;
        foreach ((array)($w['hourly'] ?? []) as $h) { $prob = max($prob, (int)($h['chanceofrain'] ?? 0)); $rain += (float)($h['precipMM'] ?? 0); }
        $days[] = ['date' => strtotime((string)($w['date'] ?? '')), 'min' => (float)($w['mintempC'] ?? 0), 'max' => (float)($w['maxtempC'] ?? 0),
                   'rain' => round($rain, 1), 'prob' => $prob, 'look' => smWwo($codes ? $codes[(int)floor(count($codes) / 2)] : 119)];
    }
    return [
        'now' => ['temp' => (float)($c['temp_C'] ?? 0), 'look' => smWwo((int)($c['weatherCode'] ?? 119)), 'wind' => (float)($c['windspeedKmph'] ?? 0),
                  'hum' => isset($c['humidity']) ? (int)$c['humidity'] : null, 'rain' => (float)($c['precipMM'] ?? 0)],
        'days' => $days, 'station' => '', 'credit' => 'wttr.in', 'link' => 'https://wttr.in',
    ];
}

/* ---------- Wikipedia-Kurzinfo (wenn der Suchdienst keine Infobox liefert) ---------- */

function smWikiCard(string $q): ?array
{
    $q = trim(preg_replace('/\b(site|filetype|ext|intitle|inbody|inpage|lang|loc):\S+/i', '', $q));
    $q = trim(str_replace(['"', '+'], ' ', $q));
    if (mb_strlen($q) < 3 || mb_strlen($q) > 80 || str_word_count($q) > 6) return null;
    $lang = smLang();
    $d = smFetchJson('https://' . $lang . '.wikipedia.org/w/api.php?' . http_build_query([
        'action' => 'query', 'format' => 'json', 'formatversion' => 2, 'generator' => 'search', 'gsrsearch' => $q, 'gsrlimit' => 1,
        'prop' => 'extracts|pageimages|info', 'exintro' => 1, 'explaintext' => 1, 'exsentences' => 4, 'inprop' => 'url',
        'piprop' => 'thumbnail', 'pithumbsize' => 320, 'redirects' => 1,
    ]), 86400, 4);
    $p = $d['query']['pages'][0] ?? null;
    if (!is_array($p) || empty($p['title']) || empty($p['extract'])) return null;
    // Nur zeigen, wenn der Artikel wirklich zur Suche passt
    $norm = function (string $s) { return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($s)); };
    $t = $norm((string)$p['title']);
    $n = $norm($q);
    if ($t === '' || (mb_strpos($n, $t) === false && mb_strpos($t, $n) === false)) {
        similar_text($t, $n, $pct);
        if ($pct < 70) return null;
    }
    if (preg_match('/(steht für|may refer to|ist der Name folgender|bezeichnet:)/u', (string)$p['extract'])) return null;
    $url = smLink($p['fullurl'] ?? '');
    $thumb = (string)($p['thumbnail']['source'] ?? '');
    return [
        'title' => smPlain((string)$p['title']), 'subtitle' => '', 'desc' => mb_substr(smPlain((string)$p['extract']), 0, 700), 'url' => $url,
        'website' => '', 'thumb' => $thumb !== '' && strpos($thumb, 'https://upload.wikimedia.org/') === 0 ? smImgProxyUrl($thumb) : '',
        'attrs' => [], 'profiles' => [], 'source' => 'Wikipedia', 'license' => 'CC BY-SA 4.0',
    ];
}

/* ---------- Kurzlinks: eigener kleiner Shortener (…/?s=abc123) ---------- */

const SM_SHORT_MAX = 500;    // pro Konto

/** Ziel für einen Kurzlink prüfen: nur http(s), ohne Leerzeichen. Ohne Protokoll wird https:// ergänzt. '' = ungültig. */
function smCleanShortTarget($u): string
{
    $u = trim(is_string($u) ? $u : '');
    if ($u !== '' && !preg_match('~^[a-z][a-z0-9+.\-]*://~i', $u) && preg_match('~^[a-z0-9.\-]+\.[a-z]{2,}(/|$)~i', $u)) $u = 'https://' . $u;
    if (strlen($u) > 2000 || !preg_match('~^https?://[^\s/?#]+\.[^\s/?#]+~i', $u) || preg_match('/[\x00-\x20"<>\\\\]/', $u)) return '';
    $host = (string)parse_url($u, PHP_URL_HOST);
    return $host !== '' ? $u : '';
}

/** Kurzlink anlegen (oder den vorhandenen für dieselbe Adresse liefern). Liefert [Code, Fehlertext]. */
function smShortCreate(int $uid, string $url): array
{
    $url = smCleanShortTarget($url);
    if ($url === '') return ['', t('Bitte eine vollständige Adresse eingeben, z. B. https://example.org/seite.')];
    $old = smQ('SELECT code FROM shortlinks WHERE user_id = ? AND url = ?', [$uid, $url])->fetchColumn();
    if ($old) return [(string)$old, ''];
    if ((int)smQ('SELECT COUNT(*) FROM shortlinks WHERE user_id = ?', [$uid])->fetchColumn() >= SM_SHORT_MAX) {
        return ['', t('Du hast schon %d Kurzlinks. Bitte erst alte löschen.', SM_SHORT_MAX)];
    }
    $abc = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    for ($try = 0; $try < 10; $try++) {
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $abc[random_int(0, strlen($abc) - 1)];
        try {
            smQ('INSERT INTO shortlinks (code, url, user_id, created) VALUES (?, ?, ?, ?)', [$code, $url, $uid, time()]);
            return [$code, ''];
        } catch (PDOException $e) {
        }
    }
    return ['', t('Es konnte kein freier Kurzlink gefunden werden.')];
}

function smShortUrl(string $code): string
{
    return smOrigin() . '?s=' . rawurlencode($code);
}

function smShortList(int $uid): array
{
    return smQ('SELECT code, url, created, hits FROM shortlinks WHERE user_id = ? ORDER BY created DESC LIMIT 200', [$uid])->fetchAll();
}

/** Kurzlink aufrufen: direkt weiter zum Ziel (ohne Anmeldung, ohne Referrer) */
function smServeShort(string $code): void
{
    $url = preg_match('/^[A-Za-z0-9]{4,12}$/', $code) ? smQ('SELECT url FROM shortlinks WHERE code = ?', [$code])->fetchColumn() : false;
    smHeaders();
    header('Cache-Control: no-store');
    if (!$url || smCleanShortTarget($url) === '') {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        exit(t('Diesen Kurzlink gibt es nicht (mehr).') . "\n");
    }
    smQ('UPDATE shortlinks SET hits = hits + 1 WHERE code = ?', [$code]);
    header('Location: ' . $url, true, 302);
    exit;
}
