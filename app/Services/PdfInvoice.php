<?php
namespace App\Services;

/**
 * Tiny dependency-free PDF writer (A4, Helvetica) used for order invoices.
 * Works on plain PHP/XAMPP with no composer packages. Text is converted to WinAnsi, so ₹ is printed as "Rs." and
 * characters outside Latin-1 (e.g. Tamil) appear as "?" in the PDF (the on-screen preview shows them correctly).
 */
final class PdfInvoice {
    private const W = 595.0; private const H = 842.0;
    private const HW = [32=>278,33=>278,34=>355,35=>556,36=>556,37=>889,38=>667,39=>191,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>278,59=>278,60=>584,61=>584,62=>584,63=>556,64=>1015,65=>667,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>500,75=>667,76=>556,77=>833,78=>722,79=>778,80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>278,92=>278,93=>278,94=>469,95=>556,96=>333,97=>556,98=>556,99=>500,100=>556,101=>556,102=>278,103=>556,104=>556,105=>222,106=>222,107=>500,108=>222,109=>833,110=>556,111=>556,112=>556,113=>556,114=>333,115=>500,116=>278,117=>556,118=>500,119=>722,120=>500,121=>500,122=>500,123=>334,124=>260,125=>334,126=>584];
    private const BW = [32=>278,33=>333,34=>474,35=>556,36=>556,37=>889,38=>722,39=>238,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>333,59=>333,60=>584,61=>584,62=>584,63=>611,64=>975,65=>722,66=>722,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>556,75=>722,76=>611,77=>833,78=>722,79=>778,80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>333,92=>278,93=>333,94=>584,95=>556,96=>333,97=>556,98=>611,99=>556,100=>611,101=>556,102=>333,103=>611,104=>611,105=>278,106=>278,107=>556,108=>278,109=>889,110=>611,111=>611,112=>611,113=>611,114=>389,115=>556,116=>333,117=>611,118=>556,119=>778,120=>556,121=>556,122=>500,123=>389,124=>280,125=>389,126=>584];

    private array $pages = []; private string $cur = '';
    /** @var array<string,array{data:string,w:int,h:int}> JPEG images embedded once and referenced by name (Im1 ...) */
    private array $images = [];
    public function __construct() { $this->cur = ''; }

    public static function enc(string $s): string {
        $s = str_replace(['₹', '–', '—', '‘', '’', '“', '”', '•', '…', '×', '−'], ['Rs.', '-', '-', "'", "'", '"', '"', '*', '...', 'x', '-'], $s);
        $s = preg_replace('/[\x00-\x08\x0B-\x1F]/', ' ', $s);
        $o = @iconv('UTF-8', 'Windows-1252//IGNORE', $s);
        if ($o === false) $o = preg_replace('/[^\x20-\x7E]/', '?', $s);
        // characters that could not be converted were dropped by //IGNORE; mark blank-only results
        return $o;
    }
    private static function esc(string $s): string { return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s); }
    public function width(string $s, float $size, bool $bold = false): float {
        $t = $bold ? self::BW : self::HW; $w = 0;
        foreach (str_split(self::enc($s)) as $ch) $w += $t[ord($ch)] ?? 556;
        return $w * $size / 1000;
    }
    /** @return string[] */
    public function wrap(string $s, float $maxW, float $size, bool $bold = false): array {
        $out = [];
        foreach (preg_split('/\R/', trim($s)) as $para) {
            $cur = '';
            foreach (preg_split('/\s+/', trim($para)) as $word) {
                if ($word === '') continue;
                $try = $cur === '' ? $word : $cur . ' ' . $word;
                if ($this->width($try, $size, $bold) <= $maxW) { $cur = $try; continue; }
                if ($cur !== '') $out[] = $cur;
                while ($this->width($word, $size, $bold) > $maxW && mb_strlen($word) > 1) { // hard-split very long words
                    $n = mb_strlen($word); while ($n > 1 && $this->width(mb_substr($word, 0, $n), $size, $bold) > $maxW) $n--;
                    $out[] = mb_substr($word, 0, $n); $word = mb_substr($word, $n);
                }
                $cur = $word;
            }
            $out[] = $cur;
        }
        return $out ?: [''];
    }
    private static function col(array $c, string $op): string { return sprintf('%.3F %.3F %.3F %s ', $c[0] / 255, $c[1] / 255, $c[2] / 255, $op); }
    /** $y is measured from the TOP of the page. $align: L, R, C inside width $w starting at $x. */
    public function text(float $x, float $y, string $s, float $size = 10, bool $bold = false, string $align = 'L', float $w = 0, array $color = [30, 30, 30]): void {
        $tw = $this->width($s, $size, $bold);
        if ($align === 'R') $x = $x + $w - $tw; elseif ($align === 'C') $x = $x + ($w - $tw) / 2;
        $this->cur .= 'BT ' . self::col($color, 'rg') . '/' . ($bold ? 'F2' : 'F1') . sprintf(' %.2F Tf %.2F %.2F Td (', $size, $x, self::H - $y) . self::esc(self::enc($s)) . ") Tj ET\n";
    }
    public function line(float $x1, float $y1, float $x2, float $y2, float $wd = 0.6, array $color = [210, 214, 220]): void {
        $this->cur .= self::col($color, 'RG') . sprintf('%.2F w %.2F %.2F m %.2F %.2F l S', $wd, $x1, self::H - $y1, $x2, self::H - $y2) . "\n";
    }
    public function rect(float $x, float $y, float $w, float $h, ?array $fill = null, ?array $stroke = null): void {
        $op = $fill && $stroke ? 'B' : ($fill ? 'f' : 'S');
        $this->cur .= ($fill ? self::col($fill, 'rg') : '') . ($stroke ? self::col($stroke, 'RG') : '') . sprintf('0.6 w %.2F %.2F %.2F %.2F re %s', $x, self::H - $y - $h, $w, $h, $op) . "\n";
    }
    /** Registers a JPEG (raw bytes) and returns its resource name. */
    public function addImage(string $jpeg, int $w, int $h): string { $n = 'Im' . (count($this->images) + 1); $this->images[$n] = ['data' => $jpeg, 'w' => $w, 'h' => $h]; return $n; }
    /** Draws a registered image inside the box ($x,$y,$w,$h; $y from the top), keeping its aspect ratio and centring it. */
    public function image(string $name, float $x, float $y, float $w, float $h): void {
        $im = $this->images[$name] ?? null; if (!$im) return;
        $r = min($w / $im['w'], $h / $im['h']); $dw = $im['w'] * $r; $dh = $im['h'] * $r;
        $this->cur .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n", $dw, $dh, $x + ($w - $dw) / 2, self::H - ($y + $h - ($h - $dh) / 2), $name);
    }
    /** Draws a QR code as vector squares (black on the current background) in a $size x $size square whose top-left is ($x,$y). */
    public function qr(QrCode $qr, float $x, float $y, float $size): void {
        $n = $qr->size(); $m = $size / $n; $ops = '';
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n;) {
                if (!$qr->isDark($c, $r)) { $c++; continue; }
                $s = $c; while ($c < $n && $qr->isDark($c, $r)) $c++;
                $ops .= sprintf('%.3F %.3F %.3F %.3F re ', $x + $s * $m, self::H - ($y + ($r + 1) * $m) - 0.08, ($c - $s) * $m + 0.08, $m + 0.08);   // +0.08pt overlap: no hairline gaps between rows
            }
        }
        $this->cur .= '0 0 0 rg ' . $ops . "f\n";
    }

    public function newPage(): void { $this->pages[] = $this->cur; $this->cur = ''; }

    public function output(): string {
        $this->pages[] = $this->cur; $this->cur = '';
        $objs = []; $n = count($this->pages);
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = []; for ($i = 0; $i < $n; $i++) $kids[] = (5 + $i * 2) . ' 0 R';
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $xo = ''; $k = 0;
        foreach ($this->images as $name => $im) {
            $id = 5 + $n * 2 + $k++; $xo .= '/' . $name . ' ' . $id . ' 0 R ';
            $objs[$id] = '<< /Type /XObject /Subtype /Image /Width ' . $im['w'] . ' /Height ' . $im['h'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($im['data']) . " >>\nstream\n" . $im['data'] . "\nendstream";
        }
        $res = '/Font << /F1 3 0 R /F2 4 0 R >>' . ($xo !== '' ? ' /XObject << ' . $xo . '>>' : '');
        foreach ($this->pages as $i => $content) {
            $po = 5 + $i * 2; $co = $po + 1;
            $objs[$po] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << ' . $res . ' >> /Contents ' . $co . ' 0 R >>';
            $objs[$co] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        }
        ksort($objs);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $off = [];
        foreach ($objs as $id => $body) { $off[$id] = strlen($pdf); $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n"; }
        $x = strlen($pdf); $max = max(array_keys($objs));
        $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) $pdf .= sprintf("%010d 00000 n \n", $off[$i] ?? 0);
        return $pdf . "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $x . "\n%%EOF";
    }

    private static function rs($n): string { return 'Rs. ' . number_format((float)$n, 2); }

    /** Builds the invoice PDF from OrderService::invoiceData(). */
    /** Standard UPI deep link (GPay, PhonePe, Paytm, BHIM): amount and note are pre-filled. The UPI ID was validated by valid_upi_id(), so '@' is kept as is. */
    public static function upiLink(string $upiId, string $payee, float $amount, string $orderNo): string {
        return 'upi://pay?pa=' . $upiId
            . '&pn=' . rawurlencode(mb_substr(trim($payee), 0, 40))
            . '&am=' . number_format($amount, 2, '.', '')
            . '&cu=INR'
            . '&tn=' . rawurlencode('Order ' . $orderNo);
    }

    /** The company logo as a JPEG the PDF can embed (flattened on white), or null when there is no usable logo (none set, SVG, or GD missing). */
    public static function logoJpeg(): ?array {
        try {
            $rel = company_logo_path();
            if ($rel === '' || !function_exists('imagecreatefromstring') || strtolower(pathinfo($rel, PATHINFO_EXTENSION)) === 'svg') return null;
            $file = PUBLIC_PATH . '/' . $rel;
            if (!is_file($file) || filesize($file) > 4 * 1024 * 1024) return null;
            $src = @imagecreatefromstring((string)file_get_contents($file)); if (!$src) return null;
            $w = imagesx($src); $h = imagesy($src); $r = min(1, 360 / max($w, $h)); $nw = max(1, (int)round($w * $r)); $nh = max(1, (int)round($h * $r));
            $dst = imagecreatetruecolor($nw, $nh); imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            ob_start(); imagejpeg($dst, null, 90); $jpg = (string)ob_get_clean(); imagedestroy($src); imagedestroy($dst);
            return $jpg === '' ? null : ['data' => $jpg, 'w' => $nw, 'h' => $nh];
        } catch (\Throwable $e) { return null; }
    }

    public static function invoice(array $d): string {
        $p = new self(); $o = $d['order']; $c = $d['company']; $L = 40.0; $R = 555.0; $primary = [232, 89, 12]; $grey = [102, 112, 133]; $ink = [31, 41, 55];
        $logo = self::logoJpeg(); $logoName = $logo ? $p->addImage($logo['data'], $logo['w'], $logo['h']) : null;
        $head = function (self $p) use ($c, $o, $primary, $L, $R, $logoName) {
            $p->rect(0, 0, 595, 78, $primary);
            $L0 = $L;
            if ($logoName) { $p->rect($L, 12, 54, 54, [255, 255, 255]); $p->image($logoName, $L + 3, 15, 48, 48); $L += 64; }   // company logo on a white tile; text moves right
            $p->text($L, 34, (string)$c['name'], 18, true, 'L', 0, [255, 255, 255]);
            $sub = array_filter([$c['legal'] !== $c['name'] ? $c['legal'] : '', $c['address'], trim(($c['phone'] ? 'Ph: ' . $c['phone'] : '') . ($c['email'] ? '   ' . $c['email'] : ''))]);
            $y = 48; foreach (array_slice($sub, 0, 3) as $t) { $p->text($L, $y, mb_strimwidth(str_replace(["\r", "\n"], ' ', (string)$t), 0, $logoName ? 70 : 95, '...'), 8, false, 'L', 0, [255, 235, 222]); $y += 10; }
            $p->text($L0, 36, 'INVOICE', 22, true, 'R', $R - $L0, [255, 255, 255]);
            $p->text($L0, 54, '#' . $o['reference'], 10, true, 'R', $R - $L0, [255, 235, 222]);
        };
        $head($p);
        $y = 100.0;
        // Order info (right) and customer (left)
        $p->text($L, $y, 'BILL TO / SHIP TO', 8, true, 'L', 0, $grey);
        $p->text(330, $y, 'ORDER DETAILS', 8, true, 'L', 0, $grey);
        $y += 14; $yl = $y; $yr = $y;
        $p->text($L, $yl, (string)$o['customer_name'], 11, true); $yl += 14;
        $addr = trim($o['address']) . "\n" . $o['city'] . ', ' . $o['state'] . ' - ' . $o['pincode'] . ($o['landmark'] ? "\nLandmark: " . $o['landmark'] : '');
        foreach ($p->wrap($addr, 260, 9) as $t) { $p->text($L, $yl, $t, 9, false, 'L', 0, $ink); $yl += 11.5; }
        $p->text($L, $yl, 'Mobile: ' . $o['mobile'] . ($o['email'] ? '   Email: ' . $o['email'] : ''), 9, false, 'L', 0, $ink); $yl += 11.5;
        $rows = [['Order ID', $o['reference']], ['Order date', date('d M Y, h:i A', strtotime($o['created_at']))], ['Order status', $d['status_label']], ['Payment method', $d['method_label']], ['Payment status', ucfirst((string)$o['payment_status'])]];
        foreach ($rows as [$k, $v]) { $p->text(330, $yr, $k, 9, false, 'L', 0, $grey); $p->text(420, $yr, mb_strimwidth((string)$v, 0, 38, '...'), 9, true); $yr += 13; }
        $y = max($yl, $yr) + 12;

        // Items table
        $cols = [['#', 24, 'C'], ['Product', 186, 'L'], ['Qty', 34, 'R'], ['Original', 68, 'R'], ['Discount', 66, 'R'], ['Price', 68, 'R'], ['Amount', 69, 'R']];
        $tableHead = function (self $p, float $y) use ($cols, $L, $R, $grey) {
            $p->rect($L, $y, $R - $L, 20, [244, 246, 250]);
            $x = $L; foreach ($cols as [$t, $w, $a]) { $p->text($x + 4, $y + 13.5, $t, 8.5, true, $a, $w - 8, [71, 84, 103]); $x += $w; }
            return $y + 20;
        };
        $y = $tableHead($p, $y); $i = 0;
        foreach ($d['lines'] as $l) {
            $nameLines = $p->wrap((string)$l['name'], $cols[1][1] - 8, 9, true); $h = max(1, count($nameLines)) * 11.5 + 14;
            if ($y + $h > 700) { $p->newPage(); $head($p); $y = $tableHead($p, 96); }
            $x = $L; $i++;
            $p->text($x + 4, $y + 12, (string)$i, 9, false, 'C', $cols[0][1] - 8); $x += $cols[0][1];
            $ty = $y + 12; foreach ($nameLines as $t) { $p->text($x + 4, $ty, $t, 9, true); $ty += 11.5; }
            $p->text($x + 4, $ty - 1, 'SKU: ' . $l['sku'], 7.5, false, 'L', 0, $grey); $x += $cols[1][1];
            $vals = [(string)$l['qty'], number_format($l['original_price'], 2), $l['discount_unit'] > 0 ? '-' . number_format($l['discount_unit'], 2) : '-', number_format($l['unit_price'], 2), number_format($l['line_total'], 2)];
            foreach ($vals as $k => $v) { $w = $cols[$k + 2][1]; $p->text($x + 4, $y + 12, $v, 9, $k === 4, 'R', $w - 8); $x += $w; }
            $y += $h; $p->line($L, $y, $R, $y);
        }
        // Payment QR / PAID stamp (decided up front so we know how much room the totals area needs)
        $upiId = trim((string)($c['upi'] ?? '')); $isPaid = strtolower((string)$o['payment_status']) === 'paid';
        $cancelled = in_array(strtolower((string)$o['status']), ['cancelled', 'canceled'], true) || strtolower((string)$o['payment_status']) === 'refunded';
        $qr = null;
        if (!$isPaid && !$cancelled && $upiId !== '' && valid_upi_id($upiId) && (float)$o['total'] > 0) {
            try { $qr = QrCode::encode(self::upiLink($upiId, (string)$c['name'], (float)$o['total'], (string)$o['reference'])); } catch (\Throwable $e) { $qr = null; }   // never let the QR break the invoice
        }
        $payBoxH = $qr ? 158.0 : ($isPaid ? 62.0 : 0.0);
        // Totals
        if ($y + 150 + $payBoxH > 800) { $p->newPage(); $head($p); $y = 100; }
        $y += 20; $top = $y; $tx = 340.0; $tw = $R - $tx;
        $tot = [['Subtotal (original price)', self::rs($d['subtotal'])]];
        if ($d['item_discount'] > 0) $tot[] = ['Discount' . ($d['discount_percent'] > 0 ? ' (' . rtrim(rtrim(number_format($d['discount_percent'], 2), '0'), '.') . '%)' : ''), '- ' . self::rs($d['item_discount'])];
        if ($d['extra_discount'] > 0) $tot[] = ['Additional discount', '- ' . self::rs($d['extra_discount'])];
        $tot[] = ['Delivery charge', self::rs($o['delivery_charge'])];
        foreach ($tot as [$k, $v]) { $p->text($tx, $y, $k, 9.5, false, 'L', 0, $grey); $p->text($tx, $y, $v, 9.5, false, 'R', $tw); $y += 15; }
        $p->rect($tx - 6, $y - 4, $tw + 12, 26, $primary);
        $p->text($tx, $y + 13, 'TOTAL', 11, true, 'L', 0, [255, 255, 255]); $p->text($tx, $y + 13, self::rs($o['total']), 12, true, 'R', $tw, [255, 255, 255]);
        $yL = $top; $boxBottom = 0.0;
        if ($payBoxH > 0) {
            $bx = $tx - 6; $bw = $tw + 12; $by = $y + 32;
            $p->rect($bx, $by, $bw, $payBoxH, $isPaid ? [22, 163, 74] : [229, 231, 235]); $p->rect($bx + 0.8, $by + 0.8, $bw - 1.6, $payBoxH - 1.6, $isPaid ? [240, 253, 244] : [255, 255, 255]);
            if ($isPaid) {
                $p->text($bx, $by + 40, 'PAID', 30, true, 'C', $bw, [22, 163, 74]);
            } else {
                $qs = 92.0;   // 92 pt = 32.5 mm
                $p->text($bx, $by + 15, 'SCAN TO PAY', 9, true, 'C', $bw, $primary);
                $p->qr($qr, $bx + ($bw - $qs) / 2, $by + 22, $qs);
                $p->text($bx, $by + 22 + $qs + 11, $upiId, 8.5, true, 'C', $bw);
                $p->text($bx, $by + 22 + $qs + 23, 'Pay ' . self::rs($o['total']) . ' via GPay / PhonePe / Paytm', 8, false, 'C', $bw, $grey);
            }
            $boxBottom = $by + $payBoxH;
        }

        // Payment info (left, beside totals)
        $p->text($L, $yL, 'PAYMENT INFORMATION', 8, true, 'L', 0, $grey); $yL += 13;
        $pay = [['Method', $d['method_label']], ['Status', ucfirst((string)$o['payment_status'])]];
        if ($c['upi']) $pay[] = ['UPI ID', $c['upi']];
        if ($c['bank_name']) $pay[] = ['Bank', $c['bank_name']];
        if ($c['bank_holder']) $pay[] = ['A/c holder', $c['bank_holder']];
        if ($c['bank_account']) $pay[] = ['A/c number', $c['bank_account']];
        if ($c['bank_ifsc']) $pay[] = ['IFSC', $c['bank_ifsc']];
        foreach ($pay as [$k, $v]) { $p->text($L, $yL, $k, 8.5, false, 'L', 0, $grey); $p->text($L + 62, $yL, mb_strimwidth((string)$v, 0, 44, '...'), 8.5, true); $yL += 12; }
        $y = max($y + 34, $yL, $boxBottom) + 14;
        $idLine = array_filter([$c['gst'] ? 'GSTIN: ' . $c['gst'] : '', $c['license'] ? 'Licence: ' . $c['license'] : '']);
        if ($idLine) { $p->text($L, $y, implode('     ', $idLine), 8.5, false, 'L', 0, $grey); $y += 14; }
        if ($c['terms']) {
            if ($y + 60 > 810) { $p->newPage(); $head($p); $y = 100; }
            $p->text($L, $y, 'TERMS & CONDITIONS', 8, true, 'L', 0, $grey); $y += 11;
            foreach (array_slice($p->wrap((string)$c['terms'], $R - $L, 8), 0, 10) as $t) { $p->text($L, $y, $t, 8, false, 'L', 0, $grey); $y += 10; }
        }
        $p->line($L, 805, $R, 805);
        $p->text($L, 820, (string)($c['footer'] ?: 'Thank you for your order!'), 9, true, 'C', $R - $L, $primary);
        return $p->output();
    }
}
