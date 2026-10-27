<?php
namespace App\Controllers;

use App\Core\{Controller, DB};
use App\Models\{Category, Product, Page};

final class HomeController extends Controller {
    public function index(): void {
        $banners = DB::all('SELECT * FROM banners WHERE is_active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) ORDER BY display_order, id');
        $about = Page::bySlug('about');
        $this->view('front/home', [
            'title' => setting('brand_name') . ' – ' . setting('tagline'), 'banners' => $banners, 'categories' => Category::active(),
            'featured' => Product::flag('is_featured', 8), 'popular' => Product::flag('is_popular', 8), 'newest' => Product::newest(8),
            'services' => DB::all('SELECT * FROM services WHERE is_active=1 ORDER BY display_order, id LIMIT 4'),
            'aboutText' => mb_substr(trim(strip_tags((string)($about['content'] ?? ''))), 0, 320), 'aboutImage' => $about['image'] ?? null,
        ]);
    }
}
