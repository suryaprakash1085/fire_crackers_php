<?php
namespace App\Services;

use App\Core\DB;
use App\Models\Product;

/**
 * Downloadable price list.
 *  - mode "uploaded": the admin uploads a PDF (real PDF only: "%PDF" header AND finfo mime application/pdf, max 10 MB, random file name,
 *    stored in public/uploads/pricelists/ where scripts are blocked by .htaccess).
 *  - mode "auto": a PDF is generated on the fly from the active products with the PdfInvoice writer (Helvetica, so Tamil names are left out).
 * Settings: pricelist_mode, pricelist_path, pricelist_updated_at (+ pricelist_name = the original file name, for display only).
 */
final class PriceList {
    public const DIR = 'uploads/pricelists';
    public const MAX_BYTES = 10 * 1024 * 1024;

    public static function mode(): string { return (string)setting('pricelist_mode', 'uploaded') === 'auto' ? 'auto' : 'uploaded'; }

    /** Absolute path of the uploaded PDF, or null when there is none (or the stored path is not inside the price-list folder). */
    public static function uploadedFile(): ?string {
        $rel = (string)setting('pricelist_path', '');
        if ($rel === '' || !str_starts_with($rel, self::DIR . '/')) return null;
        $base = realpath(PUBLIC_PATH . '/' . self::DIR);
        $f = PUBLIC_PATH . '/' . $rel;
        $real = is_file($f) ? realpath($f) : false;
        return ($base && $real && str_starts_with($real, $base . DIRECTORY_SEPARATOR)) ? $real : null;
    }
    /** @return array{name:string,size:int,updated_at:string}|null details of the uploaded PDF for the admin page */
    public static function uploadedInfo(): ?array {
        $f = self::uploadedFile(); if ($f === null) return null;
        return ['name' => (string)setting('pricelist_name', '') ?: basename($f), 'size' => (int)filesize($f), 'updated_at' => (string)setting('pricelist_updated_at', '') ?: date('Y-m-d H:i:s', (int)filemtime($f))];
    }
    public static function hasProducts(): bool {
        return (bool)DB::val('SELECT 1 FROM products p JOIN categories c ON c.id=p.category_id AND c.is_active=1 WHERE p.status="active" LIMIT 1');
    }
    /** True when visitors can download a price list right now (used to show / hide the "Download Price List" buttons). */
    public static function available(): bool {
        static $v = null;
        if ($v === null) {
            try { $v = self::mode() === 'auto' ? self::hasProducts() : (self::uploadedFile() !== null || self::hasProducts()); } catch (\Throwable $e) { log_error('pricelist: ' . $e->getMessage()); $v = false; }
        }
        return $v;
    }

    /** @param array $f one $_FILES entry. @return array{ok:bool,message:string,path:?string,name:?string} */
    public static function store(array $f): array {
        $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            $m = [UPLOAD_ERR_INI_SIZE => 'The PDF is larger than the server upload limit.', UPLOAD_ERR_FORM_SIZE => 'The PDF must be 10 MB or smaller.', UPLOAD_ERR_NO_FILE => 'No PDF file selected.'][$err] ?? 'PDF upload failed.';
            return self::fail($m);
        }
        if (!is_uploaded_file((string)$f['tmp_name'])) return self::fail('Invalid upload.');
        if ((int)$f['size'] > self::MAX_BYTES) return self::fail('The PDF must be 10 MB or smaller.');
        $bytes = (string)file_get_contents($f['tmp_name']);
        return self::storeBytes($bytes, (string)($f['name'] ?? ''));
    }

    /** Validates and saves raw bytes (also used by tests). */
    public static function storeBytes(string $bytes, string $origName = ''): array {
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) return self::fail('The PDF must be 10 MB or smaller.');
        if (substr($bytes, 0, 4) !== '%PDF' || (string)(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== 'application/pdf') return self::fail('This is not a valid PDF file.');
        $dir = PUBLIC_PATH . '/' . self::DIR;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return self::fail('The uploads folder is not writable.');
        self::ensureHtaccess($dir);
        $rel = self::DIR . '/pricelist-' . bin2hex(random_bytes(16)) . '.pdf';
        if (@file_put_contents(PUBLIC_PATH . '/' . $rel, $bytes, LOCK_EX) === false) return self::fail('Could not save the PDF file.');
        return ['ok' => true, 'message' => 'Price list uploaded.', 'path' => $rel, 'name' => self::cleanName($origName)];
    }

    /** Deletes a stored price list. Only files inside uploads/pricelists are ever removed. */
    public static function delete(?string $path): void {
        if (!$path || !str_starts_with($path, self::DIR . '/')) return;
        $f = PUBLIC_PATH . '/' . $path;
        $base = realpath(PUBLIC_PATH . '/' . self::DIR);
        $real = is_file($f) ? realpath($f) : false;
        if ($base && $real && str_starts_with($real, $base . DIRECTORY_SEPARATOR)) @unlink($real);
    }

    /** File name shown to the admin / offered to visitors, e.g. "Sri-Crackers-Price-List.pdf". */
    public static function downloadName(): string {
        $b = trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', (string)setting('brand_name', '')), '-');
        return ($b !== '' ? substr($b, 0, 40) . '-' : '') . 'Price-List.pdf';
    }

    /** Sends the price list and stops the script. Returns false (nothing sent) when there is none.
     *  $inline = show in the browser (admin preview) instead of downloading; $mode = force 'uploaded' or 'auto' (admin preview), default = the saved mode. */
    public static function send(bool $inline = false, ?string $mode = null): bool {
        $explicit = in_array($mode, ['uploaded', 'auto'], true); $mode = $explicit ? $mode : self::mode(); $bin = null; $file = null;
        try {
            if ($mode === 'auto') { if (!self::hasProducts()) return false; $bin = self::generate(); $len = strlen($bin); }
            else {
                $file = self::uploadedFile();
                if ($file === null && !$explicit && self::hasProducts()) { $bin = self::generate(); $len = strlen($bin); }   // nothing uploaded: fall back to the automatic price list
                elseif ($file === null) return false;
                else $len = (int)filesize($file);
            }
        } catch (\Throwable $e) { log_error('pricelist: ' . $e->getMessage()); return false; }
        header('Content-Type: application/pdf'); header('Content-Length: ' . $len); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-cache');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . self::downloadName() . '"');
        if ($bin !== null) echo $bin; else readfile($file);
        exit;
    }

    /** Builds the PDF from the active products, grouped by category. Uses the PdfInvoice writer, which only has Helvetica (Latin-1):
     *  Tamil names cannot be printed there, so the list shows the English names only. */
    public static function generate(): string {
        $products = Product::allActive();
        $p = new PdfInvoice(); $L = 40.0; $R = 555.0; $primary = [232, 89, 12]; $grey = [102, 112, 133]; $ink = [31, 41, 55];
        $company = trim((string)setting('brand_name', '')) ?: (trim((string)setting('company_legal_name', '')) ?: 'Price List');
        $logo = PdfInvoice::logoJpeg(); $logoName = $logo ? $p->addImage($logo['data'], $logo['w'], $logo['h']) : null;
        $date = date('d M Y'); $page = 1;
        $sub = array_filter([trim((string)setting('contact_phone', '')) !== '' ? 'Ph: ' . setting('contact_phone') : '', (string)setting('contact_email', ''), (string)setting('company_website', '')]);

        $head = function (bool $first) use ($p, $L, $R, $company, $logoName, $primary, $date, $sub) {
            $h = $first ? 70.0 : 40.0; $p->rect(0, 0, 595, $h, $primary); $x = $L;
            if ($logoName) { $t = $first ? 54.0 : 30.0; $p->rect($L, ($h - $t) / 2, $t, $t, [255, 255, 255]); $p->image($logoName, $L + 3, ($h - $t) / 2 + 3, $t - 6, $t - 6); $x += $t + 10; }
            $p->text($x, $first ? 32 : 25, mb_strimwidth($company, 0, 40, '...'), $first ? 18 : 13, true, 'L', 0, [255, 255, 255]);
            if ($first && $sub) $p->text($x, 48, mb_strimwidth(implode('   ', $sub), 0, 90, '...'), 8, false, 'L', 0, [255, 235, 222]);
            $p->text($L, $first ? 34 : 25, 'PRICE LIST', $first ? 20 : 12, true, 'R', $R - $L, [255, 255, 255]);
            if ($first) $p->text($L, 50, 'As on ' . $date, 9, false, 'R', $R - $L, [255, 235, 222]);
            return $h;
        };
        $cols = [['SKU', 64, 'L'], ['Product', 231, 'L'], ['MRP (Rs.)', 72, 'R'], ['Price (Rs.)', 80, 'R'], ['Discount', 68, 'R']];   // = 515 pt
        $tableHead = function (float $y) use ($p, $cols, $L, $R) {
            $p->rect($L, $y, $R - $L, 20, [244, 246, 250]); $x = $L;
            foreach ($cols as [$t, $w, $a]) { $p->text($x + 4, $y + 13.5, $t, 8.5, true, $a, $w - 8, [71, 84, 103]); $x += $w; }
            return $y + 20;
        };
        $foot = function () use ($p, $L, $R, $grey, &$page) {
            $p->line($L, 808, $R, 808);
            $p->text($L, 822, 'Prices in Indian Rupees; subject to change without notice. Stock availability may vary.', 7.5, false, 'L', 0, $grey);
            $p->text($L, 822, 'Page ' . $page, 8, true, 'R', $R - $L, $grey);
        };
        $limit = 796.0;
        $y = $tableHead($head(true) + 16); $lastCat = null; $i = 0;
        if (!$products) { $p->text($L, $y + 24, 'No products are available right now.', 10, false, 'L', 0, $grey); }
        foreach ($products as $r) {
            $nameLines = array_slice($p->wrap((string)$r['name'], $cols[1][1] - 8, 9, true), 0, 2); $h = count($nameLines) * 11.0 + 7.0;
            $newCat = $lastCat !== $r['category_name']; $need = $h + ($newCat ? 19.0 + 14.0 : 0.0);   // a category title is never left alone at the bottom of a page
            if ($y + $need > $limit) { $foot(); $p->newPage(); $page++; $y = $tableHead($head(false) + 12); $newCat = true; }
            if ($newCat) {
                $lastCat = $r['category_name'];
                $p->rect($L, $y, $R - $L, 19, [255, 243, 235]); $p->text($L + 4, $y + 13, mb_strimwidth((string)$lastCat, 0, 70, '...'), 9.5, true, 'L', 0, [180, 70, 10]); $y += 19; $i = 0;
            }
            if ($i++ % 2 === 1) $p->rect($L, $y, $R - $L, $h, [250, 251, 252]);
            $orig = (float)$r['original_price']; $sell = (float)$r['selling_price']; $pct = $orig > 0 ? round(($orig - $sell) / $orig * 100, 1) : 0.0;
            $x = $L + $cols[0][1];
            $p->text($L + 4, $y + 12, mb_strimwidth((string)$r['sku'], 0, 14, ''), 8.5, false, 'L', 0, $grey);
            $ty = $y + 12; foreach ($nameLines as $t) { $p->text($x + 4, $ty, $t, 9, true, 'L', 0, $ink); $ty += 11; } $x += $cols[1][1];
            $vals = [number_format($orig, 2), number_format($sell, 2), $pct > 0 ? rtrim(rtrim(number_format($pct, 1), '0'), '.') . '%' : '-'];
            foreach ($vals as $k => $v) { $w = $cols[$k + 2][1]; $p->text($x + 4, $y + 12, $v, 9, $k === 1, 'R', $w - 8, $ink); $x += $w; }
            $y += $h; $p->line($L, $y, $R, $y, 0.4, [232, 235, 240]);
        }
        $foot();
        return $p->output();
    }

    /** Display name of the uploaded file: letters, digits, space . _ - ( ), ends with .pdf, max 80 characters. */
    private static function cleanName(string $n): string {
        $n = trim((string)preg_replace('/[^A-Za-z0-9 ._()\-]+/', '', basename(str_replace('\\', '/', $n))));
        $n = preg_replace('/\.pdf$/i', '', $n); $n = trim(substr((string)$n, 0, 76), ' .');
        return ($n !== '' ? $n : 'price-list') . '.pdf';
    }
    private static function ensureHtaccess(string $dir): void {
        $f = $dir . '/.htaccess';
        if (is_file($f)) return;
        @file_put_contents($f, "# Price list PDFs only: no scripts can ever run from this folder.\n"
            . "Options -Indexes -ExecCGI\n"
            . "<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)\$\">\n  Require all denied\n</FilesMatch>\n"
            . "<IfModule mod_headers.c>\n  Header set X-Content-Type-Options \"nosniff\"\n</IfModule>\n");
    }
    private static function fail(string $m): array { return ['ok' => false, 'message' => $m, 'path' => null, 'name' => null]; }
}
