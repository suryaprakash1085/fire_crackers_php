<?php
namespace App\Controllers\Admin;

use App\Core\DB;

final class DashboardController extends AdminController {
    public function index(): void {
        $c = fn(string $sql, array $p = []) => (int)DB::val($sql, $p);
        $stats = [
            'orders' => $c('SELECT COUNT(*) FROM orders'), 'new' => $c("SELECT COUNT(*) FROM orders WHERE status='placed'"),
            'processing' => $c("SELECT COUNT(*) FROM orders WHERE status IN ('confirmed','processing','packed','shipped')"),
            'completed' => $c("SELECT COUNT(*) FROM orders WHERE status='delivered'"),
            'products' => $c("SELECT COUNT(*) FROM products WHERE status<>'archived'"), 'active_products' => $c("SELECT COUNT(*) FROM products WHERE status='active'"),
            'low_stock' => $c("SELECT COUNT(*) FROM products WHERE status='active' AND stock<=low_stock_threshold"), 'categories' => $c('SELECT COUNT(*) FROM categories'),
            'new_enquiries' => $c("SELECT COUNT(*) FROM contact_enquiries WHERE status='new'"),
        ];
        $rev = fn(string $w) => (float)DB::val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status<>'cancelled' $w");
        $sales = ['all' => $rev(''), 'today' => $rev('AND DATE(created_at)=CURDATE()'), 'month' => $rev('AND created_at >= (CURDATE() - INTERVAL 29 DAY)')];
        $daily = DB::all("SELECT DATE(created_at) d, COUNT(*) n, COALESCE(SUM(total),0) t FROM orders WHERE status<>'cancelled' AND created_at >= (CURDATE() - INTERVAL 6 DAY) GROUP BY DATE(created_at)");
        $byDay = array_column($daily, null, 'd'); $series = [];
        for ($i = 6; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $series[] = ['label' => date('D', strtotime($d)), 'total' => (float)($byDay[$d]['t'] ?? 0), 'n' => (int)($byDay[$d]['n'] ?? 0)]; }
        $this->render('admin/dashboard', ['title' => 'Dashboard', 'stats' => $stats, 'sales' => $sales, 'series' => $series,
            'sources' => DB::all('SELECT order_source, COUNT(*) n FROM orders GROUP BY order_source'),
            'recent' => DB::all('SELECT id,reference,customer_name,total,status,order_source,created_at FROM orders ORDER BY id DESC LIMIT 8'),
            'enquiries' => DB::all('SELECT id,name,subject,status,created_at FROM contact_enquiries ORDER BY id DESC LIMIT 5'),
            'lowStock' => DB::all("SELECT id,sku,name,stock,low_stock_threshold FROM products WHERE status='active' AND stock<=low_stock_threshold ORDER BY stock LIMIT 6")]);
    }
}
