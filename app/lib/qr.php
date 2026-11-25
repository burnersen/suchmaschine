<?php
/* Suchmaschine – QR-Codes als SVG, komplett auf dem eigenen Server (kein fremder Dienst sieht die Inhalte).
   Byte-Modus, Versionen 1 bis 40, Fehlerkorrektur mindestens M (wird erhöht, wenn es ohne größeren Code geht),
   beste Maske nach den Strafpunkten der Norm. Nach dem Verfahren von Project Nayuki (MIT-Lizenz), neu in PHP geschrieben. */
defined('SM_VERSION') || exit;

const SM_QR_MAX = 1200;    // Bytes Inhalt

final class SmQr
{
    /* Fehlerkorrektur-Codewörter pro Block und Zahl der Blöcke, je Stufe [L, M, Q, H] und Version (Index 0 unbenutzt) */
    private const ECC = [
        [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        [-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    ];
    private const BLOCKS = [
        [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        [-1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        [-1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    ];
    private const FORMAT = [1, 0, 3, 2];   // Formatbits der Stufen L, M, Q, H

    public $size = 0;
    public $mod = [];        // [y][x] => bool (dunkel)
    private $fn = [];        // [y][x] => bool (feste Muster)
    public $ver = 1;
    public $ecl = 1;
    public $mask = 0;

    /** Baut den Code. Wirft bei zu langem Inhalt. */
    public static function encode(string $data, int $minEcl = 1): self
    {
        $len = strlen($data);
        for ($ver = 1; $ver <= 40; $ver++) {
            $bits = 4 + ($ver < 10 ? 8 : 16) + $len * 8;
            if ($bits <= self::dataCodewords($ver, $minEcl) * 8) break;
        }
        if ($ver > 40) throw new RuntimeException('too long');
        $ecl = $minEcl;
        foreach ([1, 2, 3] as $e) {   // M, Q, H: höher, wenn es in dieselbe Version passt
            if ($e > $ecl && $bits <= self::dataCodewords($ver, $e) * 8) $ecl = $e;
        }
        // Bits: Modus (Byte), Länge, Daten, Abschluss, Füllbytes
        $bb = [];
        $put = function (int $val, int $n) use (&$bb) { for ($i = $n - 1; $i >= 0; $i--) $bb[] = ($val >> $i) & 1; };
        $put(4, 4);
        $put($len, $ver < 10 ? 8 : 16);
        for ($i = 0; $i < $len; $i++) $put(ord($data[$i]), 8);
        $cap = self::dataCodewords($ver, $ecl) * 8;
        $put(0, min(4, $cap - count($bb)));
        $put(0, (8 - count($bb) % 8) % 8);
        for ($pad = 0xEC; count($bb) < $cap; $pad ^= 0xEC ^ 0x11) $put($pad, 8);
        $bytes = [];
        foreach (array_chunk($bb, 8) as $c) $bytes[] = bindec(implode('', $c));

        $q = new self();
        $q->ver = $ver;
        $q->ecl = $ecl;
        $q->size = $ver * 4 + 17;
        $q->mod = array_fill(0, $q->size, array_fill(0, $q->size, false));
        $q->fn = $q->mod;
        $q->drawFunctionPatterns();
        $q->drawCodewords($q->addEccAndInterleave($bytes));
        // Beste Maske wählen
        $best = 0;
        $min = PHP_INT_MAX;
        for ($m = 0; $m < 8; $m++) {
            $q->applyMask($m);
            $q->drawFormatBits($m);
            $p = $q->penalty();
            if ($p < $min) { $min = $p; $best = $m; }
            $q->applyMask($m);
        }
        $q->applyMask($best);
        $q->drawFormatBits($best);
        $q->mask = $best;
        return $q;
    }

    /** SVG mit Ruhezone, skaliert frei (viewBox) */
    public function svg(int $border = 4, string $dark = '#000', string $light = '#fff'): string
    {
        $n = $this->size + $border * 2;
        $path = '';
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->mod[$y][$x]) $path .= 'M' . ($x + $border) . ',' . ($y + $border) . 'h1v1h-1z';
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 ' . $n . ' ' . $n . '" shape-rendering="crispEdges">'
             . '<rect width="100%" height="100%" fill="' . $light . '"/><path d="' . $path . '" fill="' . $dark . '"/></svg>';
    }

    private static function rawModules(int $ver): int
    {
        $r = (16 * $ver + 128) * $ver + 64;
        if ($ver >= 2) {
            $na = intdiv($ver, 7) + 2;
            $r -= (25 * $na - 10) * $na - 55;
            if ($ver >= 7) $r -= 36;
        }
        return $r;
    }

    private static function dataCodewords(int $ver, int $ecl): int
    {
        // Stufen intern: 0 = L, 1 = M, 2 = Q, 3 = H
        return intdiv(self::rawModules($ver), 8) - self::ECC[$ecl][$ver] * self::BLOCKS[$ecl][$ver];
    }

    private function set(int $x, int $y, bool $dark): void
    {
        $this->mod[$y][$x] = $dark;
        $this->fn[$y][$x] = true;
    }

    private function drawFunctionPatterns(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->set(6, $i, $i % 2 === 0);
            $this->set($i, 6, $i % 2 === 0);
        }
        $this->finder(3, 3);
        $this->finder($this->size - 4, 3);
        $this->finder(3, $this->size - 4);
        $pos = $this->alignPositions();
        $na = count($pos);
        for ($i = 0; $i < $na; $i++) {
            for ($j = 0; $j < $na; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $na - 1) || ($i === $na - 1 && $j === 0)) continue;
                for ($dy = -2; $dy <= 2; $dy++) for ($dx = -2; $dx <= 2; $dx++) $this->set($pos[$i] + $dx, $pos[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
        $this->drawFormatBits(0);
        if ($this->ver >= 7) {
            $rem = $this->ver;
            for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            $bits = ($this->ver << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($bits >> $i) & 1) === 1;
                $a = $this->size - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->set($a, $b, $bit);
                $this->set($b, $a, $bit);
            }
        }
    }

    private function finder(int $x, int $y): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $d = max(abs($dx), abs($dy));
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size) $this->set($xx, $yy, $d !== 2 && $d !== 4);
            }
        }
    }

    private function alignPositions(): array
    {
        if ($this->ver === 1) return [];
        $na = intdiv($this->ver, 7) + 2;
        $step = $this->ver === 32 ? 26 : (int)ceil(($this->ver * 4 + 4) / ($na * 2 - 2)) * 2;
        $res = [6];
        for ($pos = $this->size - 7; count($res) < $na; $pos -= $step) array_splice($res, 1, 0, [$pos]);
        return $res;
    }

    private function drawFormatBits(int $mask): void
    {
        $data = (self::FORMAT[$this->ecl] << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $b = function (int $i) use ($bits) { return (($bits >> $i) & 1) === 1; };
        for ($i = 0; $i <= 5; $i++) $this->set(8, $i, $b($i));
        $this->set(8, 7, $b(6));
        $this->set(8, 8, $b(7));
        $this->set(7, 8, $b(8));
        for ($i = 9; $i < 15; $i++) $this->set(14 - $i, 8, $b($i));
        for ($i = 0; $i < 8; $i++) $this->set($this->size - 1 - $i, 8, $b($i));
        for ($i = 8; $i < 15; $i++) $this->set(8, $this->size - 15 + $i, $b($i));
        $this->set(8, $this->size - 8, true);
    }

    private static function gfMul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z;
    }

    private static function rsDivisor(int $deg): array
    {
        $r = array_fill(0, $deg, 0);
        $r[$deg - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $deg; $i++) {
            for ($j = 0; $j < $deg; $j++) {
                $r[$j] = self::gfMul($r[$j], $root);
                if ($j + 1 < $deg) $r[$j] ^= $r[$j + 1];
            }
            $root = self::gfMul($root, 0x02);
        }
        return $r;
    }

    private static function rsRemainder(array $data, array $div): array
    {
        $r = array_fill(0, count($div), 0);
        foreach ($data as $b) {
            $f = $b ^ array_shift($r);
            $r[] = 0;
            foreach ($div as $i => $d) $r[$i] ^= self::gfMul($d, $f);
        }
        return $r;
    }

    private function addEccAndInterleave(array $data): array
    {
        $nb = self::BLOCKS[$this->ecl][$this->ver];
        $eccLen = self::ECC[$this->ecl][$this->ver];
        $raw = intdiv(self::rawModules($this->ver), 8);
        $short = $nb - $raw % $nb;
        $shortLen = intdiv($raw, $nb);
        $div = self::rsDivisor($eccLen);
        $blocks = [];
        $k = 0;
        for ($i = 0; $i < $nb; $i++) {
            $n = $shortLen - $eccLen + ($i < $short ? 0 : 1);
            $dat = array_slice($data, $k, $n);
            $k += $n;
            $ecc = self::rsRemainder($dat, $div);
            if ($i < $short) $dat[] = 0;
            $blocks[] = array_merge($dat, $ecc);
        }
        $res = [];
        $bl = count($blocks[0]);
        for ($i = 0; $i < $bl; $i++) {
            foreach ($blocks as $j => $b) {
                if ($i !== $shortLen - $eccLen || $j >= $short) $res[] = $b[$i];
            }
        }
        return $res;
    }

    private function drawCodewords(array $data): void
    {
        $i = 0;
        $total = count($data) * 8;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) $right = 5;
            for ($v = 0; $v < $this->size; $v++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $up = (($right + 1) & 2) === 0;
                    $y = $up ? $this->size - 1 - $v : $v;
                    if (!$this->fn[$y][$x] && $i < $total) {
                        $this->mod[$y][$x] = (($data[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $m): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                switch ($m) {
                    case 0: $inv = ($x + $y) % 2 === 0; break;
                    case 1: $inv = $y % 2 === 0; break;
                    case 2: $inv = $x % 3 === 0; break;
                    case 3: $inv = ($x + $y) % 3 === 0; break;
                    case 4: $inv = (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0; break;
                    case 5: $inv = $x * $y % 2 + $x * $y % 3 === 0; break;
                    case 6: $inv = ($x * $y % 2 + $x * $y % 3) % 2 === 0; break;
                    default: $inv = (($x + $y) % 2 + $x * $y % 3) % 2 === 0;
                }
                if ($inv && !$this->fn[$y][$x]) $this->mod[$y][$x] = !$this->mod[$y][$x];
            }
        }
    }

    private function penalty(): int
    {
        $res = 0;
        $n = $this->size;
        for ($pass = 0; $pass < 2; $pass++) {
            for ($a = 0; $a < $n; $a++) {
                $color = false;
                $run = 0;
                $hist = [0, 0, 0, 0, 0, 0, 0];
                for ($b = 0; $b < $n; $b++) {
                    $c = $pass === 0 ? $this->mod[$a][$b] : $this->mod[$b][$a];
                    if ($c === $color) {
                        $run++;
                        if ($run === 5) $res += 3; elseif ($run > 5) $res++;
                    } else {
                        $this->histAdd($run, $hist);
                        if (!$color) $res += $this->histCount($hist) * 40;
                        $color = $c;
                        $run = 1;
                    }
                }
                if ($color) { $this->histAdd($run, $hist); $run = 0; }
                $run += $n;
                $this->histAdd($run, $hist);
                $res += $this->histCount($hist) * 40;
            }
        }
        $dark = 0;
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($this->mod[$y][$x]) $dark++;
                if ($y < $n - 1 && $x < $n - 1) {
                    $c = $this->mod[$y][$x];
                    if ($c === $this->mod[$y][$x + 1] && $c === $this->mod[$y + 1][$x] && $c === $this->mod[$y + 1][$x + 1]) $res += 3;
                }
            }
        }
        $total = $n * $n;
        $k = (int)ceil(abs($dark * 20 - $total * 10) / $total) - 1;
        return $res + $k * 10;
    }

    private function histAdd(int $run, array &$h): void
    {
        if ($h[0] === 0) $run += $this->size;
        array_pop($h);
        array_unshift($h, $run);
    }

    private function histCount(array $h): int
    {
        $n = $h[1];
        $core = $n > 0 && $h[2] === $n && $h[3] === $n * 3 && $h[4] === $n && $h[5] === $n;
        return ($core && $h[0] >= $n * 4 && $h[6] >= $n ? 1 : 0) + ($core && $h[6] >= $n * 4 && $h[0] >= $n ? 1 : 0);
    }
}

/** QR-Code als SVG ausliefern (?a=qr&d=Text). Nur der Text in der Adresse, nichts wird gespeichert. */
function smServeQr(): void
{
    $d = isset($_GET['d']) && is_string($_GET['d']) ? $_GET['d'] : '';
    if ($d === '' || strlen($d) > SM_QR_MAX) {
        http_response_code(400);
        exit;
    }
    try {
        $svg = SmQr::encode($d)->svg();
    } catch (Throwable $e) {
        http_response_code(400);
        exit;
    }
    smHeaders();
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Content-Type: image/svg+xml');
    header('Cache-Control: private, max-age=86400');
    if (isset($_GET['dl'])) header('Content-Disposition: attachment; filename="qr-code.svg"');
    echo $svg;
    exit;
}
