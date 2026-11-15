<?php
namespace App\Services;

use App\Core\DB;
use App\Models\Setting;

/** One shared session cart used by listing, details, Quick Order and Voice Order. */
final class CartService {
    public static function raw(): array { return $_SESSION['cart'] ?? []; }

    public static function add(int $id, int $qty, string $source = 'cart'): array {
        $max = max(1, (int)setting('max_qty_per_item', 500));
        $p = DB::one('SELECT p.id,p.name,p.stock,p.status FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=? AND c.is_active=1', [$id]);
        if (!$p || $p['status'] !== 'active') return ['ok' => false, 'status' => 'unavailable', 'message' => 'Product not available.', 'added' => 0];
        if ($qty < 1) return ['ok' => false, 'status' => 'invalid', 'message' => 'Quantity must be at least 1.', 'added' => 0, 'name' => $p['name']];
        $have = (int)(self::raw()[$id] ?? 0);
        $limit = min($max, (int)$p['stock']);
        if ($limit <= 0) return ['ok' => false, 'status' => 'out_of_stock', 'message' => $p['name'] . ' is out of stock.', 'added' => 0, 'name' => $p['name'], 'limit' => 0, 'in_cart' => $have];
        $new = min($limit, $have + $qty);
        $_SESSION['cart'][$id] = $new;
        $added = $new - $have;
        if ($source === 'quick_order') $_SESSION['cart_sources']['quick_order'] = true;
        if ($source === 'voice_order' && $added > 0) $_SESSION['voice_total'][$id] = ($_SESSION['voice_total'][$id] ?? 0) + $added;
        $r = ['ok' => true, 'added' => $added, 'name' => $p['name'], 'limit' => $limit, 'in_cart' => $new];
        if ($added < $qty) return $r + ['status' => 'reduced', 'message' => "Only $limit of {$p['name']} available — cart updated to $new."];
        return $r + ['status' => 'added', 'message' => "Added $added × {$p['name']}."];
    }
    /** Take back quantities previously added by a voice command. */
    public static function undoVoice(array $added): void {
        foreach ($added as $id => $q) {
            $cur = (int)(self::raw()[$id] ?? 0); if ($cur <= 0) continue;
            $left = $cur - (int)$q; if ($left > 0) $_SESSION['cart'][$id] = $left; else unset($_SESSION['cart'][$id]);
            $t = ($_SESSION['voice_total'][$id] ?? 0) - (int)$q; if ($t > 0) $_SESSION['voice_total'][$id] = $t; else unset($_SESSION['voice_total'][$id]);
        }
        if (!self::raw()) self::clear();
    }
    public static function set(int $id, int $qty): array {
        if ($qty <= 0) { self::remove($id); return ['ok' => true, 'message' => 'Removed.']; }
        $p = DB::one('SELECT name,stock,status FROM products WHERE id=?', [$id]);
        if (!$p || $p['status'] !== 'active') { self::remove($id); return ['ok' => false, 'message' => 'Product no longer available.']; }
        $limit = min(max(1, (int)setting('max_qty_per_item', 500)), (int)$p['stock']);
        $_SESSION['cart'][$id] = max(1, min($qty, max(1, $limit)));
        return ['ok' => true, 'message' => $qty > $limit ? "Only $limit available." : 'Updated.'];
    }
    public static function remove(int $id): void { unset($_SESSION['cart'][$id], $_SESSION['voice_total'][$id]); if (!self::raw()) self::clear(); }
    public static function clear(): void { unset($_SESSION['cart'], $_SESSION['cart_sources'], $_SESSION['voice_total'], $_SESSION['voice_last']); }
    public static function source(): string {
        foreach (array_keys($_SESSION['voice_total'] ?? []) as $id) if (isset(self::raw()[$id])) return 'voice_order';
        return !empty($_SESSION['cart_sources']['quick_order']) ? 'quick_order' : 'cart';
    }
    public static function count(): int { return array_sum(self::raw()); }

    /** Builds priced lines from current DB values. Never trusts client prices. */
    public static function summary(): array {
        $raw = self::raw(); $items = []; $issues = [];
        if ($raw) {
            $ids = array_map('intval', array_keys($raw));
            $in = implode(',', array_fill(0, count($ids), '?'));
            $rows = DB::all("SELECT p.id,p.sku,p.name,p.original_price,p.selling_price,p.stock,p.status,
                (SELECT path FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC, i.sort_order, i.id LIMIT 1) AS image
                FROM products p WHERE p.id IN ($in)", $ids);
            $by = array_column($rows, null, 'id');
            foreach ($raw as $id => $qty) {
                $p = $by[$id] ?? null;
                if (!$p || $p['status'] !== 'active') { unset($_SESSION['cart'][$id]); $issues[] = 'An unavailable product was removed from your cart.'; continue; }
                $qty = (int)$qty;
                if ($qty > (int)$p['stock']) {
                    if ((int)$p['stock'] <= 0) { unset($_SESSION['cart'][$id]); $issues[] = $p['name'] . ' is now out of stock and was removed.'; continue; }
                    $qty = (int)$p['stock']; $_SESSION['cart'][$id] = $qty; $issues[] = 'Quantity of ' . $p['name'] . ' reduced to available stock (' . $qty . ').';
                }
                $items[] = ['id' => (int)$p['id'], 'sku' => $p['sku'], 'name' => $p['name'], 'image' => $p['image'] ? upload_url($p['image']) : '',
                    'original_price' => (float)$p['original_price'], 'unit_price' => (float)$p['selling_price'], 'qty' => $qty, 'stock' => (int)$p['stock'],
                    'line_total' => round((float)$p['selling_price'] * $qty, 2)];
            }
        }
        $orig = 0.0; $sell = 0.0; $count = 0;
        foreach ($items as $i) { $orig += $i['original_price'] * $i['qty']; $sell += $i['line_total']; $count += $i['qty']; }
        $discount = max(0, $orig - $sell);
        $bulkMin = (float)setting('bulk_discount_min', 0); $bulkPct = (float)setting('bulk_discount_percent', 0);
        $bulk = ($bulkMin > 0 && $bulkPct > 0 && $sell >= $bulkMin) ? round($sell * $bulkPct / 100, 2) : 0.0;
        $afterDisc = $sell - $bulk;
        $freeAbove = (float)setting('free_delivery_above', 0);
        $delivery = $items ? (($freeAbove > 0 && $afterDisc >= $freeAbove) ? 0.0 : (float)setting('delivery_charge', 0)) : 0.0;
        $min = (float)setting('min_order_amount', 0);
        if ($items && $min > 0 && $afterDisc < $min) $issues[] = 'Minimum order amount is ' . money($min) . '.';
        return ['items' => $items, 'count' => $count, 'subtotal' => round($orig, 2), 'discount' => round($discount + $bulk, 2), 'bulk_discount' => $bulk,
            'delivery' => round($delivery, 2), 'total' => round($afterDisc + $delivery, 2), 'min_order' => $min, 'meets_min' => !($items && $min > 0 && $afterDisc < $min),
            'free_delivery_remaining' => ($freeAbove > 0 && $afterDisc < $freeAbove && $items) ? round($freeAbove - $afterDisc, 2) : 0, 'issues' => $issues, 'source' => self::source()];
    }
}
