<?php
namespace App\Controllers\Admin;

use App\Core\DB;

/** Sales & operations reports for a chosen date range. Cancelled orders are excluded from revenue figures. */
final class ReportsController extends AdminController {
    private function range(): array {
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$this->input('to')) ? $this->input('to') : date('Y-m-d');
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$this->input('from')) ? $this->input('from') : date('Y-m-d', strtotime('-29 days'));
        if ($from > $to) [$from, $to] = [$to, $from];
        if ((strtotime($to) - strtotime($from)) > 366 * 86400) $from = date('Y-m-d', strtotime($to . ' -366 days'));
        return [$from, $to, $from . ' 00:00:00', $to . ' 23:59:59'];
    }
    public function index(): void {
        [$from, $to, $a, $b] = $this->range(); $p = [$a, $b]; $live = "o.created_at BETWEEN ? AND ? AND o.status<>'cancelled'";
        $tot = DB::one("SELECT COUNT(*) orders, COALESCE(SUM(o.total),0) revenue, COALESCE(AVG(o.total),0) avg_order, COALESCE(SUM(o.discount_total),0) discount, COALESCE(SUM(o.delivery_charge),0) delivery,
            COALESCE(SUM(CASE WHEN o.payment_status='paid' THEN o.total END),0) paid, COALESCE(SUM(CASE WHEN o.payment_status='pending' THEN o.total END),0) pending FROM orders o WHERE $live", $p);
        $items = (int)DB::val("SELECT COALESCE(SUM(i.quantity),0) FROM order_items i JOIN orders o ON o.id=i.order_id WHERE $live", $p);
        $cancelled = (int)DB::val("SELECT COUNT(*) FROM orders o WHERE o.created_at BETWEEN ? AND ? AND o.status='cancelled'", $p);
        $dayRows = DB::all("SELECT DATE(o.created_at) d, COUNT(*) c, SUM(o.total) t FROM orders o WHERE $live GROUP BY DATE(o.created_at) ORDER BY d", $p); $days = [];
        foreach ($dayRows as $r) $days[$r['d']] = $r;
        $series = []; for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) { $d = date('Y-m-d', $t); $series[] = ['d' => $d, 'c' => (int)($days[$d]['c'] ?? 0), 't' => (float)($days[$d]['t'] ?? 0)]; }
        $this->render('admin/reports', [
            'title' => 'Reports', 'from' => $from, 'to' => $to, 'tot' => $tot, 'items' => $items, 'cancelled' => $cancelled, 'series' => $series,
            'status' => DB::all("SELECT o.status k, COUNT(*) c, SUM(o.total) t FROM orders o WHERE o.created_at BETWEEN ? AND ? GROUP BY o.status ORDER BY c DESC", $p),
            'source' => DB::all("SELECT o.order_source k, COUNT(*) c, SUM(o.total) t FROM orders o WHERE $live GROUP BY o.order_source ORDER BY t DESC", $p),
            'payment' => DB::all("SELECT o.payment_method k, COUNT(*) c, SUM(o.total) t FROM orders o WHERE $live GROUP BY o.payment_method ORDER BY t DESC", $p),
            'top' => DB::all("SELECT i.sku, i.name, SUM(i.quantity) q, SUM(i.line_total) t FROM order_items i JOIN orders o ON o.id=i.order_id WHERE $live GROUP BY i.sku, i.name ORDER BY t DESC LIMIT 10", $p),
            'cats' => DB::all("SELECT COALESCE(c.name,'(deleted)') k, SUM(i.quantity) q, SUM(i.line_total) t FROM order_items i JOIN orders o ON o.id=i.order_id LEFT JOIN products pr ON pr.id=i.product_id LEFT JOIN categories c ON c.id=pr.category_id WHERE $live GROUP BY c.name ORDER BY t DESC LIMIT 10", $p),
            'places' => DB::all("SELECT o.state, o.city, COUNT(*) c, SUM(o.total) t FROM orders o WHERE $live GROUP BY o.state, o.city ORDER BY t DESC LIMIT 10", $p),
            'lowStock' => DB::all("SELECT id, sku, name, stock, low_stock_threshold FROM products WHERE status='active' AND stock<=low_stock_threshold ORDER BY stock ASC LIMIT 10"),
            'customers' => DB::all("SELECT o.customer_name, o.mobile, COUNT(*) c, SUM(o.total) t FROM orders o WHERE $live GROUP BY o.mobile, o.customer_name ORDER BY t DESC LIMIT 10", $p),
        ]);
    }
    public function export(): void {
        [$from, $to, $a, $b] = $this->range(); $what = $this->input('what') === 'products' ? 'products' : 'daily';
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="report-' . $what . '-' . $from . '_' . $to . '.csv"'); echo "\xEF\xBB\xBF"; $o = fopen('php://output', 'w');
        $safe = fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
        if ($what === 'products') { fputcsv($o, ['SKU', 'Product', 'Units sold', 'Revenue']); foreach (DB::all("SELECT i.sku, i.name, SUM(i.quantity) q, SUM(i.line_total) t FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.created_at BETWEEN ? AND ? AND o.status<>'cancelled' GROUP BY i.sku, i.name ORDER BY t DESC", [$a, $b]) as $r) fputcsv($o, [$safe($r['sku']), $safe($r['name']), $r['q'], $r['t']]); }
        else { fputcsv($o, ['Date', 'Orders', 'Revenue']); foreach (DB::all("SELECT DATE(o.created_at) d, COUNT(*) c, SUM(o.total) t FROM orders o WHERE o.created_at BETWEEN ? AND ? AND o.status<>'cancelled' GROUP BY DATE(o.created_at) ORDER BY d", [$a, $b]) as $r) fputcsv($o, [$r['d'], $r['c'], $r['t']]); }
        fclose($o); exit;
    }
}
