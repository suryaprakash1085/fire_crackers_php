<?php
namespace App\Services;

use App\Core\DB;

/** Excel template download + bulk add/update of products from an uploaded .xlsx (or .csv). */
final class ProductSheet {
    public const MAX_ROWS = 5000;
    /** header label => [db key, width] */
    private const COLS = [
        'ID'              => ['id', 8],
        'SKU'             => ['sku', 16],
        'Product Name'    => ['name', 34],
        'Tamil Name'      => ['name_ta', 24],
        'Category'        => ['category', 22],
        'Original Price'  => ['original_price', 14],
        'Selling Price'   => ['selling_price', 14],
        'Stock'           => ['stock', 10],
        'Stock at export' => ['stock_baseline', 16],
        'Low Stock Alert' => ['low_stock_threshold', 14],
        'Status'          => ['status', 12],
        'Featured'        => ['is_featured', 11],
        'Popular'         => ['is_popular', 11],
        'Description'     => ['description', 46],
        'Safety Notes'    => ['safety_notes', 40],
        'Voice Aliases'   => ['aliases', 36],
    ];
    private const ALIASES = [ // extra accepted header spellings (lower-case, no spaces/symbols)
        'id' => 'id', 'productid' => 'id', 'sku' => 'sku', 'code' => 'sku', 'productcode' => 'sku', 'name' => 'name', 'product' => 'name', 'productname' => 'name',
        'tamilname' => 'name_ta', 'nameta' => 'name_ta', 'nametamil' => 'name_ta', 'category' => 'category', 'categoryname' => 'category',
        'originalprice' => 'original_price', 'mrp' => 'original_price', 'price' => 'original_price', 'sellingprice' => 'selling_price', 'saleprice' => 'selling_price', 'offerprice' => 'selling_price',
        'stock' => 'stock', 'qty' => 'stock', 'quantity' => 'stock', 'stockatexport' => 'stock_baseline', 'lowstockalert' => 'low_stock_threshold', 'lowstock' => 'low_stock_threshold', 'lowstockthreshold' => 'low_stock_threshold',
        'status' => 'status', 'featured' => 'is_featured', 'popular' => 'is_popular', 'description' => 'description', 'safetynotes' => 'safety_notes', 'safety' => 'safety_notes',
        'voicealiases' => 'aliases', 'aliases' => 'aliases',
    ];
    private static function norm(string $h): string { return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($h))); }

    /** Builds the workbook. $withProducts=false gives an empty template with one example row. */
    public static function export(bool $withProducts, bool $includeArchived = false): string {
        $head = array_keys(self::COLS);
        $rows = [$head];
        if ($withProducts) {
            $where = $includeArchived ? '' : "WHERE p.status<>'archived'";
            foreach (DB::all("SELECT p.*, c.name category_name FROM products p JOIN categories c ON c.id=p.category_id $where ORDER BY c.name, p.name") as $p) {
                $rows[] = [(int)$p['id'], $p['sku'], $p['name'], $p['name_ta'], $p['category_name'], (float)$p['original_price'], (float)$p['selling_price'], (int)$p['stock'], (int)$p['stock'], (int)$p['low_stock_threshold'],
                    $p['status'], $p['is_featured'] ? 'Yes' : 'No', $p['is_popular'] ? 'Yes' : 'No', $p['description'], $p['safety_notes'], $p['aliases']];
            }
        }
        if (count($rows) === 1) { // example row (ID blank = new product)
            $cat = (string)DB::val('SELECT name FROM categories ORDER BY id LIMIT 1');
            $rows[] = ['', 'SAMPLE-001', 'Sample Flower Pot (delete this row)', 'பூந்தொட்டி', $cat ?: 'Flower Pots', 250, 175, 100, 100, 10, 'active', 'No', 'No', 'Short description', 'Light from a distance', 'flower pot, flowerpot'];
        }
        $cats = DB::all('SELECT name FROM categories ORDER BY name');
        $lists = [['Categories', 'Status', 'Yes / No']];
        foreach (range(0, max(count($cats), 3) - 1) as $i) $lists[] = [$cats[$i]['name'] ?? '', ['active', 'inactive', 'archived'][$i] ?? '', ['Yes', 'No'][$i] ?? ''];
        $n = max(count($rows) + 500, 600); $keys = array_column(self::COLS, 0);
        $col = fn(string $k) => array_search($k, $keys, true);
        $instr = [['How to use this file'],
            ['1. Do NOT rename or delete the header row (row 1) of the "Products" sheet. Column order does not matter.'],
            ['2. To EDIT products: change the cells and upload the file back. Rows are matched by ID (or by SKU if ID is empty).'],
            ['3. To ADD new products: add a row at the bottom, leave ID empty, fill SKU, Product Name, Category, Original Price and Selling Price.'],
            ['4. On existing products, an EMPTY cell means "keep the current value". To clear a text field, type a single dash  -'],
            ['5. Category must match an existing category name (pick from the dropdown). Status: active / inactive / archived. Featured / Popular: Yes or No.'],
            ['6. Selling Price cannot be more than Original Price. Stock at export is a read-only snapshot used to detect changes since download.'],
            ['7. Replace mode: Stock is the new total. Add mode: replace the exported total with a signed adjustment (+/-) applied to current stock.'],
            ['8. Images and videos are not part of this sheet – add them from the product page. New products from this sheet are created without images.'],
            ['9. Up to ' . self::MAX_ROWS . ' rows per upload. Rows with errors are skipped and listed after the upload; the good rows are saved.']];
        return Xlsx::build([
            ['name' => 'Products', 'rows' => $rows, 'widths' => array_column(self::COLS, 1), 'validations' => [
                ['col' => $col('category'), 'list' => 'Lists!$A$2:$A$' . max(2, count($cats) + 1), 'rows' => $n, 'error' => 'Choose an existing category.'],
                ['col' => $col('status'), 'list' => '"active,inactive,archived"', 'rows' => $n],
                ['col' => $col('is_featured'), 'list' => '"Yes,No"', 'rows' => $n],
                ['col' => $col('is_popular'), 'list' => '"Yes,No"', 'rows' => $n],
            ]],
            ['name' => 'Instructions', 'rows' => $instr, 'widths' => [130], 'header' => false, 'boldFirstCol' => true],
            ['name' => 'Lists', 'rows' => $lists, 'widths' => [28, 14, 12]],
        ]);
    }

    /** Reads the uploaded file into [header-key => value] row arrays (with sheet row numbers). */
    private static function load(string $tmp, string $name): array {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            $raw = (string)file_get_contents($tmp);
            if (str_starts_with($raw, "\xEF\xBB\xBF")) $raw = substr($raw, 3);
            if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
            $fh = fopen('php://temp', 'r+'); fwrite($fh, $raw); rewind($fh);
            $first = (string)strtok($raw, "\n"); $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
            $rows = []; while (($r = fgetcsv($fh, 0, $delim)) !== false) $rows[] = array_map('strval', $r);
            fclose($fh);
        } elseif ($ext === 'xlsx') {
            if (!Xlsx::available()) throw new \RuntimeException('The PHP "zip" extension is off on this server, so .xlsx cannot be read. Enable extension=zip in php.ini (XAMPP: Config → PHP → php.ini), or upload a .csv instead.');
            $rows = Xlsx::read($tmp, 'Products');
        } else throw new \RuntimeException('Please upload the .xlsx file (Excel Workbook) downloaded from this page.');
        // find header row (first row that contains a recognised "sku" or "name" column)
        $hi = null; $map = [];
        foreach ($rows as $i => $r) {
            $m = []; foreach ($r as $c => $h) { $k = self::ALIASES[self::norm((string)$h)] ?? null; if ($k && !in_array($k, $m, true)) $m[$c] = $k; }
            if (in_array('sku', $m, true) || in_array('name', $m, true)) { $hi = $i; $map = $m; break; }
            if ($i > 10) break;
        }
        if ($hi === null) throw new \RuntimeException('Header row not found. Download the template again and keep row 1 (ID, SKU, Product Name …) as it is.');
        if (!in_array('sku', $map, true) && !in_array('id', $map, true)) throw new \RuntimeException('The sheet needs an SKU (or ID) column.');
        $out = [];
        foreach (array_slice($rows, $hi + 1, null, true) as $i => $r) {
            $row = []; $any = false;
            foreach ($map as $c => $k) { $v = trim((string)($r[$c] ?? '')); if ($v !== '') $any = true; $row[$k] = $v; }
            if (!$any) continue;
            if (count($out) >= self::MAX_ROWS) throw new \RuntimeException('Too many rows. Maximum ' . self::MAX_ROWS . ' per upload – split the file.');
            $out[] = ['line' => $i + 1, 'd' => $row];
        }
        return [$out, array_values($map)];
    }

    private static function num(string $v): ?float { $v = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $v)); return ($v === '' || !is_numeric($v)) ? null : (float)$v; }
    private static function yn(string $v): ?int { $v = mb_strtolower($v); if (in_array($v, ['yes', 'y', '1', 'true', 'x', 'ok'], true)) return 1; if (in_array($v, ['no', 'n', '0', 'false'], true)) return 0; return null; }

    /**
     * @return array{added:int,updated:int,unchanged:int,errors:array<array{line:int,sku:string,msg:string}>,warnings:array<array{line:int,sku:string,msg:string}>,newCats:int}
     */
    public static function import(string $tmp, string $origName, bool $createCategories, string $stockMode = 'replace'): array {
        if (!in_array($stockMode, ['replace', 'add'], true)) throw new \RuntimeException('Choose Replace or Add (+/-) for stock mode.');
        [$rows, $cols] = self::load($tmp, $origName);
        if (!$rows) throw new \RuntimeException('No product rows found in the file.');
        $cats = []; foreach (DB::all('SELECT id,name FROM categories') as $c) $cats[mb_strtolower(trim($c['name']))] = (int)$c['id'];
        $res = ['added' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => [], 'warnings' => [], 'newCats' => 0];
        $pdo = DB::pdo(); $seenSku = []; $seenId = []; $plan = [];

        foreach ($rows as ['line' => $line, 'd' => $d]) {
            $err = function (string $m) use (&$res, $line, $d) { $res['errors'][] = ['line' => $line, 'sku' => ($d['sku'] ?? '') !== '' ? $d['sku'] : ($d['name'] ?? ''), 'msg' => $m]; };
            $sku = strtoupper(trim($d['sku'] ?? '')); $id = (int)preg_replace('/\D/', '', $d['id'] ?? '');
            if ($id) {
                if (isset($seenId[$id])) { $err("ID $id appears more than once in the file (first on row {$seenId[$id]})."); continue; }
                $seenId[$id] = $line;
            }
            $cur = null;
            if ($id) { $cur = DB::one('SELECT * FROM products WHERE id=?', [$id]); if (!$cur) { $err("ID $id does not exist. Clear the ID to add it as a new product."); continue; } }
            elseif ($sku !== '') $cur = DB::one('SELECT * FROM products WHERE sku=?', [$sku]);
            if ($sku === '' && $cur) $sku = $cur['sku'];
            if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,59}$/', $sku)) { $err('SKU: 2–60 letters, numbers, - or _.'); continue; }
            if (isset($seenSku[$sku])) { $err("SKU $sku appears twice in the file (first on row {$seenSku[$sku]})."); continue; }
            $dup = DB::val('SELECT id FROM products WHERE sku=?' . ($cur ? ' AND id<>?' : ''), $cur ? [$sku, $cur['id']] : [$sku]);
            if ($dup) { $err("SKU $sku is already used by another product."); continue; }
            $seenSku[$sku] = $line;

            $new = []; $bad = null;
            $txt = function (string $k, int $max) use ($d, $cur, &$new) {
                if (!array_key_exists($k, $d) || $d[$k] === '') return;
                $new[$k] = $d[$k] === '-' ? null : mb_substr($d[$k], 0, $max);
            };
            // name
            if (($d['name'] ?? '') !== '') $new['name'] = mb_substr($d['name'], 0, 190);
            elseif (!$cur) $bad = 'Product Name is required for a new product.';
            if (!$bad && isset($new['name']) && mb_strlen($new['name']) < 2) $bad = 'Product Name is too short.';
            $txt('name_ta', 190); $txt('description', 20000); $txt('safety_notes', 20000); $txt('aliases', 20000);
            // category
            if (!$bad) {
                $cn = trim($d['category'] ?? '');
                if ($cn !== '') {
                    $key = mb_strtolower($cn);
                    if (isset($cats[$key])) $new['category_id'] = $cats[$key];
                    elseif ($createCategories) { $new['_newcat'] = $cn; }
                    else $bad = "Category \"$cn\" does not exist. Pick one from the dropdown, or tick “Create missing categories”.";
                } elseif (!$cur) $bad = 'Category is required for a new product.';
            }
            // prices
            if (!$bad) {
                $o = ($d['original_price'] ?? '') !== '' ? self::num($d['original_price']) : null; $s = ($d['selling_price'] ?? '') !== '' ? self::num($d['selling_price']) : null;
                if (($d['original_price'] ?? '') !== '' && $o === null) $bad = 'Original Price is not a number.';
                elseif (($d['selling_price'] ?? '') !== '' && $s === null) $bad = 'Selling Price is not a number.';
                else {
                    $o = $o ?? ($cur ? (float)$cur['original_price'] : null); $s = $s ?? ($cur ? (float)$cur['selling_price'] : null);
                    if ($o === null || $s === null) $bad = 'Original Price and Selling Price are required for a new product.';
                    elseif ($o <= 0 || $o > 9999999) $bad = 'Original Price must be above 0.';
                    elseif ($s <= 0 || $s > $o) $bad = 'Selling Price must be above 0 and not more than the Original Price.';
                    else { $new['original_price'] = round($o, 2); $new['selling_price'] = round($s, 2); $new['discount_percent'] = round(($o - $s) / $o * 100, 2); }
                }
            }
            // Warn when the stock snapshot from export no longer matches current stock.
            if ($cur && ($d['stock_baseline'] ?? '') !== '') {
                $baseline = self::num($d['stock_baseline']);
                if ($baseline !== null && floor($baseline) == $baseline && (int)$baseline !== (int)$cur['stock']) {
                    $res['warnings'][] = ['line' => $line, 'sku' => $sku, 'msg' => 'Stock changed by orders after this file was downloaded. Exported stock was ' . (int)$baseline . '; current stock is ' . (int)$cur['stock'] . '.'];
                }
            }
            // Stock can be replaced by an absolute total or adjusted by a signed delta.
            if (!$bad) {
                if (($d['stock'] ?? '') !== '') {
                    $stockValue = self::num($d['stock']);
                    if ($stockValue === null || floor($stockValue) != $stockValue) $bad = 'Stock must be a whole number.';
                    elseif ($stockMode === 'add' && abs($stockValue) > 10000000) $bad = 'Stock adjustment must be between -10,000,000 and 10,000,000.';
                    elseif ($stockMode === 'replace' && ($stockValue < 0 || $stockValue > 10000000)) $bad = 'Stock must be a whole number (0 or more).';
                    else {
                        $nextStock = $stockMode === 'add' ? (int)($cur['stock'] ?? 0) + (int)$stockValue : (int)$stockValue;
                        if ($nextStock < 0 || $nextStock > 10000000) $bad = 'The stock change would result in a value outside 0 to 10,000,000.';
                        else $new['stock'] = $nextStock;
                    }
                } elseif (!$cur) $new['stock'] = 0;
                if (!$bad && ($d['low_stock_threshold'] ?? '') !== '') {
                    $n = self::num($d['low_stock_threshold']);
                    if ($n === null || $n < 0 || $n > 10000000 || floor($n) != $n) $bad = 'Low Stock Alert must be a whole number (0 or more).';
                    else $new['low_stock_threshold'] = (int)$n;
                } elseif (!$cur) $new['low_stock_threshold'] = 10;
            }
            // status / flags
            if (!$bad) {
                $st = mb_strtolower($d['status'] ?? '');
                if ($st !== '') { if (in_array($st, ['active', 'inactive', 'archived'], true)) $new['status'] = $st; else $bad = 'Status must be active, inactive or archived.'; }
                elseif (!$cur) $new['status'] = 'active';
            }
            foreach (['is_featured' => 'Featured', 'is_popular' => 'Popular'] as $k => $lab) {
                if ($bad) break;
                if (($d[$k] ?? '') === '') { if (!$cur) $new[$k] = 0; continue; }
                $b = self::yn($d[$k]); if ($b === null) $bad = "$lab must be Yes or No."; else $new[$k] = $b;
            }
            if ($bad) { $err($bad); continue; }
            $plan[] = ['line' => $line, 'sku' => $sku, 'cur' => $cur, 'new' => $new];
        }

        try {
            $pdo->beginTransaction();
            foreach ($plan as $p) {
                $new = $p['new'];
                if (isset($new['_newcat'])) {
                    $key = mb_strtolower($new['_newcat']);
                    if (!isset($cats[$key])) { $cats[$key] = self::createCategory($new['_newcat']); $res['newCats']++; }
                    $new['category_id'] = $cats[$key]; unset($new['_newcat']);
                }
                if ($p['cur']) {
                    $changed = [];
                    foreach ($new as $k => $v) { $o = $p['cur'][$k] ?? null; if ((string)$o !== (string)$v && !(is_numeric($o) && is_numeric($v) && (float)$o == (float)$v)) $changed[$k] = $v; }
                    if ($p['sku'] !== $p['cur']['sku']) $changed['sku'] = $p['sku'];
                    if ($changed) { DB::update('products', $changed, 'id=?', [$p['cur']['id']]); $res['updated']++; } else $res['unchanged']++;
                } else {
                    $new['sku'] = $p['sku']; $new['slug'] = self::uniqueSlug(slugify($new['name']) . '-' . strtolower($p['sku']));
                    DB::insert('products', $new); $res['added']++;
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            log_error('product import: ' . $e->getMessage());
            throw new \RuntimeException('Import failed and nothing was saved (database error). Details are in storage/logs.');
        }
        return $res;
    }

    private static function uniqueSlug(string $base): string {
        $s = $base; for ($i = 2; DB::val('SELECT 1 FROM products WHERE slug=?', [$s]); $i++) $s = $base . '-' . $i; return $s;
    }
    private static function createCategory(string $name): int {
        $b = slugify($name); $s = $b; for ($i = 2; DB::val('SELECT 1 FROM categories WHERE slug=?', [$s]); $i++) $s = $b . '-' . $i;
        return DB::insert('categories', ['name' => mb_substr($name, 0, 120), 'slug' => $s]);
    }
}
