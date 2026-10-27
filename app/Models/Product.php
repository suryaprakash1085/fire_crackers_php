<?php
namespace App\Models;

use App\Core\DB;

final class Product {
    private const SELECT = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug,
        (SELECT path FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC, i.sort_order, i.id LIMIT 1) AS image
        FROM products p JOIN categories c ON c.id=p.category_id AND c.is_active=1 ';

    public static function listing(?int $categoryId, int $page, int $per = 12, string $sort = 'new', string $q = '', array $f = []): array {
        $where = 'WHERE p.status="active"'; $params = [];
        if ($categoryId) { $where .= ' AND p.category_id=?'; $params[] = $categoryId; }
        if ($q !== '') { $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; $where .= ' AND (p.name LIKE ? OR p.name_ta LIKE ? OR p.sku LIKE ? OR p.aliases LIKE ?)'; array_push($params, $like, $like, $like, $like); }
        if (!empty($f['min'])) { $where .= ' AND p.selling_price>=?'; $params[] = (float)$f['min']; }
        if (!empty($f['max'])) { $where .= ' AND p.selling_price<=?'; $params[] = (float)$f['max']; }
        if (!empty($f['instock'])) $where .= ' AND p.stock>0';
        $order = ['new' => 'p.created_at DESC', 'price_asc' => 'p.selling_price ASC', 'price_desc' => 'p.selling_price DESC', 'name' => 'p.name ASC'][$sort] ?? 'p.created_at DESC';
        $total = (int)DB::val('SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id AND c.is_active=1 ' . $where, $params);
        $rows = DB::all(self::SELECT . $where . " ORDER BY $order LIMIT " . (int)$per . ' OFFSET ' . (int)(($page - 1) * $per), $params);
        return [$rows, $total];
    }
    public static function priceRange(): array { $r = DB::one('SELECT MIN(selling_price) lo, MAX(selling_price) hi FROM products WHERE status="active"'); return [(int)floor((float)($r['lo'] ?? 0)), (int)ceil((float)($r['hi'] ?? 0))]; }
    /** Every active product (for the Quick Order table). */
    public static function allActive(): array { return DB::all(self::SELECT . 'WHERE p.status="active" ORDER BY c.display_order, c.name, p.name'); }
    public static function find(int $id): ?array { return DB::one(self::SELECT . 'WHERE p.id=? AND p.status="active"', [$id]); }
    public static function findBySlug(string $slug): ?array { return DB::one(self::SELECT . 'WHERE p.slug=? AND p.status="active"', [$slug]); }
    public static function images(int $id): array { return DB::all('SELECT * FROM product_images WHERE product_id=? ORDER BY is_primary DESC, sort_order, id', [$id]); }
    public static function flag(string $col, int $n): array {
        if (!in_array($col, ['is_featured', 'is_popular'], true)) return [];
        return DB::all(self::SELECT . "WHERE p.status='active' AND p.$col=1 ORDER BY p.updated_at DESC LIMIT " . (int)$n);
    }
    public static function newest(int $n): array { return DB::all(self::SELECT . 'WHERE p.status="active" ORDER BY p.created_at DESC LIMIT ' . (int)$n); }
    public static function related(array $p, int $n = 4): array {
        return DB::all(self::SELECT . 'WHERE p.status="active" AND p.category_id=? AND p.id<>? ORDER BY RAND() LIMIT ' . (int)$n, [$p['category_id'], $p['id']]);
    }
    public static function lookup(string $q, int $limit = 8): array {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        return DB::all(self::SELECT . 'WHERE p.status="active" AND (p.sku LIKE ? OR p.name LIKE ? OR p.aliases LIKE ?) ORDER BY (p.sku=?) DESC, p.name LIMIT ' . (int)$limit, [$like, $like, $like, $q]);
    }
    public static function stockLabel(array $p): array {
        if ((int)$p['stock'] <= 0) return ['Out of stock', 'bg-red-100 text-red-700'];
        if ((int)$p['stock'] <= (int)$p['low_stock_threshold']) return ['Only ' . (int)$p['stock'] . ' left', 'bg-amber-100 text-amber-800'];
        return ['In stock', 'bg-green-100 text-green-700'];
    }
}
