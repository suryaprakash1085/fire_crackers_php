<?php
namespace App\Models;

use App\Core\DB;

final class Category {
    public static function active(): array {
        return DB::all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id AND p.status="active") AS product_count FROM categories c WHERE c.is_active=1 ORDER BY c.display_order, c.name');
    }
    public static function find(int $id): ?array { return DB::one('SELECT * FROM categories WHERE id=? AND is_active=1', [$id]); }
}
