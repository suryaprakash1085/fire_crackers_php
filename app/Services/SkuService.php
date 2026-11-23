<?php
namespace App\Services;

use App\Core\DB;

/**
 * Automatic product SKU = category prefix + running number, e.g. FP101, FP102 (Flower Pots), SP201 (Sparklers).
 *
 * - Each category owns ONE prefix (categories.sku_prefix, UNIQUE) and a hundred-block (categories.sku_block: 1 -> 101, 2 -> 201 ...).
 * - Existing categories are learned from their existing SKUs (FP101..FP103 -> prefix FP, block 1), so existing SKUs never change.
 * - A new category gets a prefix from its name (Fountains -> FO) and the next free hundred-block (FO901).
 * - next() must run INSIDE the transaction that inserts the product. It locks the category row (SELECT ... FOR UPDATE), so two admins
 *   creating products in the same category at the same instant are serialised and can never receive the same number.
 *   The UNIQUE indexes on products.sku and categories.sku_prefix remain as the final safety net.
 */
final class SkuService {
    /** Reserves and returns the next SKU for a category. Call inside an open transaction. */
    public static function next(int $categoryId): string {
        $cat = DB::one('SELECT id,name,sku_prefix,sku_block FROM categories WHERE id=? FOR UPDATE', [$categoryId]);
        if (!$cat) throw new \DomainException('Select a category.');
        [$prefix, $block] = self::resolve($cat, true);
        return self::build($prefix, $block);
    }

    /** What the next SKU would be (no locking, nothing saved). Used for the hint on the product form. */
    public static function peek(int $categoryId): string {
        $cat = DB::one('SELECT id,name,sku_prefix,sku_block FROM categories WHERE id=?', [$categoryId]);
        if (!$cat) return '';
        [$prefix, $block] = self::resolve($cat, false);
        return self::build($prefix, $block);
    }

    private static function build(string $prefix, int $block): string {
        $n = max($block * 100, self::maxNumber($prefix)) + 1;
        for ($i = 0; $i < 1000; $i++, $n++) {
            $sku = $prefix . $n;
            if (!DB::val('SELECT 1 FROM products WHERE sku=?', [$sku])) return $sku;
        }
        throw new \RuntimeException('Could not allocate a SKU.');
    }

    /** Highest number used by any SKU of the form PREFIX<digits> (all statuses, archived too). */
    private static function maxNumber(string $prefix): int {
        $max = 0;
        foreach (DB::all('SELECT sku FROM products WHERE sku LIKE ?', [$prefix . '%']) as $r)
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/i', (string)$r['sku'], $m)) $max = max($max, (int)$m[1]);
        return $max;
    }

    /** @return array{0:string,1:int} [prefix, block] — saved on the category when $persist. */
    private static function resolve(array $cat, bool $persist): array {
        $prefix = strtoupper((string)($cat['sku_prefix'] ?? ''));
        if ($prefix !== '') return [$prefix, (int)$cat['sku_block']];

        $taken = array_map('strtoupper', array_column(DB::all('SELECT sku_prefix FROM categories WHERE sku_prefix IS NOT NULL AND id<>?', [$cat['id']]), 'sku_prefix'));
        $candidates = [];

        // 1) learn from the category's current SKUs (FP101, FP102 -> FP, block 1)
        $count = []; $min = [];
        foreach (DB::all('SELECT sku FROM products WHERE category_id=?', [$cat['id']]) as $r)
            if (preg_match('/^([A-Za-z]{1,6})(\d{2,})$/', (string)$r['sku'], $m)) {
                $p = strtoupper($m[1]); $n = (int)$m[2];
                $count[$p] = ($count[$p] ?? 0) + 1; $min[$p] = isset($min[$p]) ? min($min[$p], $n) : $n;
            }
        arsort($count);
        $fromSkus = array_keys($count);
        foreach ($fromSkus as $p) $candidates[] = $p;

        // 2) otherwise derive from the name
        foreach (self::namePrefixes((string)$cat['name']) as $p) $candidates[] = $p;

        foreach ($candidates as $i => $p) {
            if (in_array($p, $taken, true)) continue;
            $fromSku = $i < count($fromSkus);
            $blk = $fromSku ? intdiv((int)$min[$p], 100) : self::nextBlock();
            if (!$persist) return [$p, $blk];
            try {
                DB::exec('UPDATE categories SET sku_prefix=?, sku_block=? WHERE id=?', [$p, $blk, $cat['id']]);
                return [$p, $blk];
            } catch (\PDOException $e) {
                if ((int)$e->getCode() !== 23000) throw $e;   // prefix grabbed by another category a moment ago: try the next candidate
                $taken[] = $p;
            }
        }
        throw new \RuntimeException('Could not allocate a SKU prefix for this category.');
    }

    /** Next free hundred-block across all categories and all existing numeric SKUs. */
    private static function nextBlock(): int {
        $max = (int)DB::val('SELECT COALESCE(MAX(sku_block),0) FROM categories');
        foreach (DB::all("SELECT sku FROM products WHERE sku REGEXP '^[A-Za-z]{1,6}[0-9]{3,}$'") as $r)
            if (preg_match('/(\d+)$/', (string)$r['sku'], $m)) $max = max($max, intdiv((int)$m[1], 100));
        return $max + 1;
    }

    /** Candidate 2-letter prefixes from a category name, best first, ending with a generic fallback list (always terminates). */
    public static function namePrefixes(string $name): array {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $ascii = preg_replace('/[^A-Za-z ]+/', ' ', $ascii === false ? '' : $ascii);
        $words = preg_split('/\s+/', trim((string)$ascii), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $c = [];
        if (count($words) >= 2) $c[] = strtoupper($words[0][0] . $words[1][0]);
        if ($words) { $w = strtoupper($words[0]); for ($i = 1; $i < strlen($w); $i++) $c[] = $w[0] . $w[$i]; }
        $all = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)$ascii));
        for ($i = 1; $i < strlen($all); $i++) $c[] = $all[0] . $all[$i];
        foreach (range('A', 'Z') as $a) foreach (range('A', 'Z') as $b) $c[] = $a . $b;
        return array_values(array_unique($c));
    }
}
