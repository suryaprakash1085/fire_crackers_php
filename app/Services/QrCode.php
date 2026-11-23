<?php
namespace App\Services;

/**
 * Dependency-free QR Code (ISO/IEC 18004) encoder: byte mode, error-correction level M, versions 1-40 chosen automatically.
 * No composer package, no GD, no online API: the QR is computed locally and drawn as vector squares (see PdfInvoice).
 * The algorithm follows the public-domain reference design by Project Nayuki (Reed-Solomon over GF(256), 8 mask patterns scored by penalty).
 *
 *   $qr = QrCode::encode('upi://pay?pa=name@okhdfcbank&am=100.00&cu=INR');
 *   $qr->size();            // modules per side (21 for version 1 ... 177 for version 40)
 *   $qr->isDark($x, $y);    // true = black module
 */
final class QrCode {
    // Error-correction level M only. Index = version (index 0 unused).
    private const ECC_PER_BLOCK = [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28];
    private const NUM_BLOCKS = [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49];
    private const FORMAT_BITS_M = 0; // ECC level M = 00

    private int $version;
    private int $size;
    private int $mask = 0;
    /** @var bool[][] [y][x] */
    private array $modules = [];
    /** @var bool[][] */
    private array $isFunction = [];

    private function __construct() {}

    /** @throws \InvalidArgumentException when the text does not fit even in version 40 */
    public static function encode(string $text): self {
        $data = array_values(unpack('C*', $text) ?: []);
        $q = new self();
        // smallest version that fits
        $ver = 0;
        for ($v = 1; $v <= 40; $v++) {
            $ccBits = $v <= 9 ? 8 : 16;
            $used = 4 + $ccBits + 8 * count($data);
            if ($used <= self::numDataCodewords($v) * 8 && count($data) < (1 << $ccBits)) { $ver = $v; break; }
        }
        if ($ver === 0) throw new \InvalidArgumentException('Text is too long for a QR code.');
        $q->version = $ver; $q->size = $ver * 4 + 17;

        // bit stream: mode 0100 (byte), char count, data, terminator, padding
        $bits = [];
        $put = function (int $val, int $len) use (&$bits) { for ($i = $len - 1; $i >= 0; $i--) $bits[] = ($val >> $i) & 1; };
        $put(0x4, 4); $put(count($data), $ver <= 9 ? 8 : 16);
        foreach ($data as $b) $put($b, 8);
        $capBits = self::numDataCodewords($ver) * 8;
        $put(0, min(4, $capBits - count($bits)));
        while (count($bits) % 8 !== 0) $bits[] = 0;
        $codewords = [];
        for ($i = 0; $i < count($bits); $i += 8) { $b = 0; for ($j = 0; $j < 8; $j++) $b = ($b << 1) | $bits[$i + $j]; $codewords[] = $b; }
        for ($pad = 0xEC; count($codewords) < self::numDataCodewords($ver); $pad ^= 0xEC ^ 0x11) $codewords[] = $pad;

        $q->build($q->addEccAndInterleave($codewords));
        return $q;
    }

    public function size(): int { return $this->size; }
    public function version(): int { return $this->version; }
    public function maskPattern(): int { return $this->mask; }
    public function isDark(int $x, int $y): bool { return $x >= 0 && $y >= 0 && $x < $this->size && $y < $this->size && $this->modules[$y][$x]; }

    /** Largest text (bytes) that fits in $version at level M. */
    public static function byteCapacity(int $version): int {
        $bits = self::numDataCodewords($version) * 8 - 4 - ($version <= 9 ? 8 : 16);
        return intdiv($bits, 8);
    }

    // ---------------------------------------------------------------- construction
    private function build(array $allCodewords): void {
        $this->modules = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->isFunction = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->drawFunctionPatterns();
        $this->drawCodewords($allCodewords);
        $best = 0; $minPenalty = PHP_INT_MAX;
        for ($m = 0; $m < 8; $m++) {
            $this->applyMask($m); $this->drawFormatBits($m);
            $p = $this->penaltyScore();
            if ($p < $minPenalty) { $best = $m; $minPenalty = $p; }
            $this->applyMask($m); // XOR again = undo
        }
        $this->mask = $best;
        $this->applyMask($best); $this->drawFormatBits($best);
        $this->isFunction = [];
    }

    private function setFunction(int $x, int $y, bool $dark): void { $this->modules[$y][$x] = $dark; $this->isFunction[$y][$x] = true; }

    private function drawFunctionPatterns(): void {
        for ($i = 0; $i < $this->size; $i++) { $this->setFunction(6, $i, $i % 2 === 0); $this->setFunction($i, 6, $i % 2 === 0); }
        $this->drawFinder(3, 3); $this->drawFinder($this->size - 4, 3); $this->drawFinder(3, $this->size - 4);
        $pos = $this->alignmentPositions(); $n = count($pos);
        for ($i = 0; $i < $n; $i++) for ($j = 0; $j < $n; $j++) {
            if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) continue;
            $this->drawAlignment($pos[$i], $pos[$j]);
        }
        $this->drawFormatBits(0); // reserve the area
        $this->drawVersion();
    }
    private function drawFinder(int $cx, int $cy): void {
        for ($dy = -4; $dy <= 4; $dy++) for ($dx = -4; $dx <= 4; $dx++) {
            $x = $cx + $dx; $y = $cy + $dy; $d = max(abs($dx), abs($dy));
            if ($x >= 0 && $x < $this->size && $y >= 0 && $y < $this->size) $this->setFunction($x, $y, $d !== 2 && $d !== 4);
        }
    }
    private function drawAlignment(int $cx, int $cy): void {
        for ($dy = -2; $dy <= 2; $dy++) for ($dx = -2; $dx <= 2; $dx++) $this->setFunction($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
    }
    private function alignmentPositions(): array {
        if ($this->version === 1) return [];
        $n = intdiv($this->version, 7) + 2;
        $step = $this->version === 32 ? 26 : intdiv($this->version * 4 + $n * 2 + 1, $n * 2 - 2) * 2;
        $res = [6];
        for ($pos = $this->size - 7; count($res) < $n; $pos -= $step) array_splice($res, 1, 0, [$pos]);
        return $res;
    }
    private function drawFormatBits(int $mask): void {
        $data = (self::FORMAT_BITS_M << 3) | $mask; $rem = $data;
        for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $bit = fn(int $i): bool => (($bits >> $i) & 1) !== 0;
        for ($i = 0; $i <= 5; $i++) $this->setFunction(8, $i, $bit($i));
        $this->setFunction(8, 7, $bit(6)); $this->setFunction(8, 8, $bit(7)); $this->setFunction(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) $this->setFunction(14 - $i, 8, $bit($i));
        for ($i = 0; $i < 8; $i++) $this->setFunction($this->size - 1 - $i, 8, $bit($i));
        for ($i = 8; $i < 15; $i++) $this->setFunction(8, $this->size - 15 + $i, $bit($i));
        $this->setFunction(8, $this->size - 8, true); // always dark
    }
    private function drawVersion(): void {
        if ($this->version < 7) return;
        $rem = $this->version;
        for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        $bits = ($this->version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) !== 0; $a = $this->size - 11 + $i % 3; $b = intdiv($i, 3);
            $this->setFunction($a, $b, $dark); $this->setFunction($b, $a, $dark);
        }
    }

    private static function numRawModules(int $ver): int {
        $r = (16 * $ver + 128) * $ver + 64;
        if ($ver >= 2) { $n = intdiv($ver, 7) + 2; $r -= (25 * $n - 10) * $n - 55; if ($ver >= 7) $r -= 36; }
        return $r;
    }
    private static function numDataCodewords(int $ver): int {
        return intdiv(self::numRawModules($ver), 8) - self::ECC_PER_BLOCK[$ver] * self::NUM_BLOCKS[$ver];
    }

    private function addEccAndInterleave(array $data): array {
        $ver = $this->version; $nb = self::NUM_BLOCKS[$ver]; $eccLen = self::ECC_PER_BLOCK[$ver];
        $raw = intdiv(self::numRawModules($ver), 8); $numShort = $nb - $raw % $nb; $shortLen = intdiv($raw, $nb);
        $blocks = []; $div = self::rsDivisor($eccLen);
        for ($i = 0, $k = 0; $i < $nb; $i++) {
            $len = $shortLen - $eccLen + ($i < $numShort ? 0 : 1);
            $dat = array_slice($data, $k, $len); $k += $len;
            $ecc = self::rsRemainder($dat, $div);
            if ($i < $numShort) $dat[] = 0;
            $blocks[] = array_merge($dat, $ecc);
        }
        $out = [];
        for ($i = 0; $i < count($blocks[0]); $i++) foreach ($blocks as $j => $blk) if ($i !== $shortLen - $eccLen || $j >= $numShort) $out[] = $blk[$i];
        return $out;
    }

    private function drawCodewords(array $data): void {
        $i = 0; $total = count($data) * 8;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) $right = 5;
            for ($vert = 0; $vert < $this->size; $vert++) for ($j = 0; $j < 2; $j++) {
                $x = $right - $j; $upward = (($right + 1) & 2) === 0; $y = $upward ? $this->size - 1 - $vert : $vert;
                if (!$this->isFunction[$y][$x] && $i < $total) { $this->modules[$y][$x] = (($data[$i >> 3] >> (7 - ($i & 7))) & 1) !== 0; $i++; }
            }
        }
    }

    private function applyMask(int $m): void {
        for ($y = 0; $y < $this->size; $y++) for ($x = 0; $x < $this->size; $x++) {
            $inv = match ($m) {
                0 => ($x + $y) % 2 === 0, 1 => $y % 2 === 0, 2 => $x % 3 === 0, 3 => ($x + $y) % 3 === 0,
                4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0, 5 => $x * $y % 2 + $x * $y % 3 === 0,
                6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0, default => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
            };
            if (!$this->isFunction[$y][$x] && $inv) $this->modules[$y][$x] = !$this->modules[$y][$x];
        }
    }

    private function penaltyScore(): int {
        $res = 0; $n = $this->size;
        for ($y = 0; $y < $n; $y++) {
            $col = false; $run = 0; $hist = [0, 0, 0, 0, 0, 0, 0];
            for ($x = 0; $x < $n; $x++) {
                if ($this->modules[$y][$x] === $col) { $run++; if ($run === 5) $res += 3; elseif ($run > 5) $res++; }
                else { $this->histAdd($run, $hist); if (!$col) $res += $this->countPatterns($hist) * 40; $col = $this->modules[$y][$x]; $run = 1; }
            }
            $res += $this->terminateAndCount($col, $run, $hist) * 40;
        }
        for ($x = 0; $x < $n; $x++) {
            $col = false; $run = 0; $hist = [0, 0, 0, 0, 0, 0, 0];
            for ($y = 0; $y < $n; $y++) {
                if ($this->modules[$y][$x] === $col) { $run++; if ($run === 5) $res += 3; elseif ($run > 5) $res++; }
                else { $this->histAdd($run, $hist); if (!$col) $res += $this->countPatterns($hist) * 40; $col = $this->modules[$y][$x]; $run = 1; }
            }
            $res += $this->terminateAndCount($col, $run, $hist) * 40;
        }
        for ($y = 0; $y < $n - 1; $y++) for ($x = 0; $x < $n - 1; $x++) {
            $c = $this->modules[$y][$x];
            if ($c === $this->modules[$y][$x + 1] && $c === $this->modules[$y + 1][$x] && $c === $this->modules[$y + 1][$x + 1]) $res += 3;
        }
        $dark = 0; foreach ($this->modules as $row) foreach ($row as $c) if ($c) $dark++;
        $total = $n * $n; $k = intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1;
        return $res + max(0, $k) * 10;
    }
    private function countPatterns(array $h): int {
        $n = $h[1]; $core = $n > 0 && $h[2] === $n && $h[3] === $n * 3 && $h[4] === $n && $h[5] === $n;
        return ($core && $h[0] >= $n * 4 && $h[6] >= $n ? 1 : 0) + ($core && $h[6] >= $n * 4 && $h[0] >= $n ? 1 : 0);
    }
    private function terminateAndCount(bool $color, int $run, array &$h): int {
        if ($color) { $this->histAdd($run, $h); $run = 0; }
        $run += $this->size; $this->histAdd($run, $h);
        return $this->countPatterns($h);
    }
    private function histAdd(int $run, array &$h): void {
        if ($h[0] === 0) $run += $this->size;
        array_pop($h); array_unshift($h, $run);
    }

    // ---------------------------------------------------------------- Reed-Solomon over GF(2^8), polynomial 0x11D
    private static function gfMul(int $x, int $y): int {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) { $z = ($z << 1) ^ (($z >> 7) * 0x11D); $z ^= (($y >> $i) & 1) * $x; }
        return $z;
    }
    private static function rsDivisor(int $degree): array {
        $r = array_fill(0, $degree, 0); $r[$degree - 1] = 1; $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) { $r[$j] = self::gfMul($r[$j], $root); if ($j + 1 < $degree) $r[$j] ^= $r[$j + 1]; }
            $root = self::gfMul($root, 0x02);
        }
        return $r;
    }
    private static function rsRemainder(array $data, array $div): array {
        $r = array_fill(0, count($div), 0);
        foreach ($data as $b) {
            $f = $b ^ array_shift($r); $r[] = 0;
            foreach ($div as $i => $coef) $r[$i] ^= self::gfMul($coef, $f);
        }
        return $r;
    }
}
