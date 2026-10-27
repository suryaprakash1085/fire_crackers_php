<?php
namespace App\Models;

use App\Core\DB;

final class Page {
    public static function bySlug(string $slug): ?array { return DB::one('SELECT * FROM pages WHERE slug=?', [$slug]); }
    public static function footerLinks(): array { return DB::all("SELECT slug, title FROM pages WHERE slug IN ('privacy','terms','shipping','refund','safety') ORDER BY FIELD(slug,'privacy','terms','shipping','refund','safety')"); }
}
