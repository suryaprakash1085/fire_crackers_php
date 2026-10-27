<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\{Category, Product};

final class ProductController extends Controller {
    public function index(): void {
        $cats = Category::active(); $cid = (int)($_GET['category'] ?? 0);
        if (!$cid && !empty($_GET['cat'])) foreach ($cats as $c) if ($c['slug'] === $_GET['cat']) $cid = (int)$c['id'];
        $active = null; foreach ($cats as $c) if ((int)$c['id'] === $cid) $active = $c;
        if ($cid && !$active) $cid = 0;
        $sort = in_array($_GET['sort'] ?? '', ['new', 'price_asc', 'price_desc', 'name'], true) ? $_GET['sort'] : 'new';
        $limits = [12, 24, 48, 96]; $lim = (int)($_GET['limit'] ?? 12); $per = in_array($lim, $limits, true) ? $lim : 12; $page = $this->page();
        $f = ['min' => max(0, (float)($_GET['min'] ?? 0)) ?: null, 'max' => max(0, (float)($_GET['max'] ?? 0)) ?: null, 'instock' => !empty($_GET['instock'])];
        $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 60);
        [$rows, $total] = Product::listing($cid ?: null, $page, $per, $sort, $q, $f);
        $pages = max(1, (int)ceil($total / $per));
        if (!empty($_GET['ajax'])) {   // infinite scroll: only the cards for this page
            $html = ''; foreach ($rows as $p) $html .= \App\Core\View::partial('partials/product-card', ['p' => $p]);
            $this->json(['ok' => true, 'html' => $html, 'page' => $page, 'pages' => $pages, 'has_more' => $page < $pages, 'total' => $total]);
        }
        $this->view('front/products', ['title' => $active ? $active['name'] : 'All Products', 'categories' => $cats, 'activeCat' => $active, 'products' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'sort' => $sort, 'cid' => $cid, 'q' => $q, 'per' => $per, 'limits' => $limits, 'f' => $f, 'range' => Product::priceRange()]);
    }
    public function show(): void {
        $p = isset($_GET['id']) ? Product::find((int)$_GET['id']) : (isset($_GET['slug']) ? Product::findBySlug((string)$_GET['slug']) : null);
        if (!$p) { http_response_code(404); $this->view('front/404', ['title' => 'Product not found']); return; }
        $this->view('front/product', ['title' => $p['name'], 'p' => $p, 'images' => Product::images((int)$p['id']), 'related' => Product::related($p)]);
    }
}
