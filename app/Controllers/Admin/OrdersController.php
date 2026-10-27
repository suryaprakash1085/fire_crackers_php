<?php
namespace App\Controllers\Admin;

use App\Core\{DB, View};
use App\Services\OrderService;

/** The ONE central order page: cart, quick_order and voice_order orders all live here. */
final class OrdersController extends AdminController {
    private function filters(): array {
        $w = ['1=1']; $p = [];
        $q = $this->input('q'); $st = $this->input('status'); $src = $this->input('source'); $pay = $this->input('payment'); $from = $this->input('from'); $to = $this->input('to');
        if ($q !== '') { $w[] = '(reference LIKE ? OR customer_name LIKE ? OR mobile LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%"); }
        if (isset(order_statuses()[$st])) { $w[] = 'status=?'; $p[] = $st; }
        if (in_array($src, ['cart', 'quick_order', 'voice_order'], true)) { $w[] = 'order_source=?'; $p[] = $src; }
        if (in_array($pay, ['pending', 'paid', 'failed', 'refunded'], true)) { $w[] = 'payment_status=?'; $p[] = $pay; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $w[] = 'created_at >= ?'; $p[] = $from . ' 00:00:00'; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $w[] = 'created_at <= ?'; $p[] = $to . ' 23:59:59'; }
        return [implode(' AND ', $w), $p, compact('q', 'st', 'src', 'pay', 'from', 'to')];
    }
    public function index(): void {
        [$where, $p, $f] = $this->filters(); $per = 20; $page = $this->page();
        $total = (int)DB::val("SELECT COUNT(*) FROM orders WHERE $where", $p);
        $rows = DB::all("SELECT o.*, (SELECT SUM(quantity) FROM order_items WHERE order_id=o.id) items FROM orders o WHERE $where ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
        $this->render('admin/orders/index', ['title' => 'Orders', 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int)ceil($total / $per)), 'f' => $f]);
    }
    private function load(string $id): array {
        $o = DB::one('SELECT * FROM orders WHERE id=?', [(int)$id]);
        if (!$o) { flash('error', 'Order not found.'); redirect('/admin/orders'); }
        $o['items'] = DB::all('SELECT * FROM order_items WHERE order_id=? ORDER BY id', [$o['id']]);
        $o['history'] = DB::all('SELECT * FROM order_status_history WHERE order_id=? ORDER BY created_at DESC, id DESC', [$o['id']]);
        return $o;
    }
    public function show(string $id): void { $o = $this->load($id); $this->render('admin/orders/view', ['title' => 'Order ' . $o['reference'], 'o' => $o]); }
    public function printView(string $id): void { $o = $this->load($id); View::render('admin/orders/print', ['o' => $o, 'title' => 'Order ' . $o['reference']]); }
    public function status(string $id): void {
        $this->post();
        $r = OrderService::changeStatus((int)$id, (string)($_POST['status'] ?? ''), mb_substr(trim((string)($_POST['note'] ?? '')), 0, 250), $this->admin['name']);
        flash($r['ok'] ? 'success' : 'error', $r['message']); redirect("/admin/orders/$id");
    }
    public function details(string $id): void {
        $this->post();
        $pay = (string)($_POST['payment_status'] ?? 'pending');
        if (!in_array($pay, ['pending', 'paid', 'failed', 'refunded'], true)) $pay = 'pending';
        $o = DB::one('SELECT payment_status,status FROM orders WHERE id=?', [(int)$id]);
        if (!$o) redirect('/admin/orders');
        DB::update('orders', ['payment_status' => $pay, 'tracking_info' => mb_substr(trim((string)($_POST['tracking_info'] ?? '')), 0, 250) ?: null, 'internal_notes' => trim((string)($_POST['internal_notes'] ?? '')) ?: null], 'id=?', [(int)$id]);
        if ($o['payment_status'] !== $pay) DB::insert('order_status_history', ['order_id' => (int)$id, 'status' => $o['status'], 'note' => 'Payment status set to ' . $pay, 'changed_by' => $this->admin['name']]);
        flash('success', 'Order details saved.'); redirect("/admin/orders/$id");
    }
    public function export(): void {
        [$where, $p] = $this->filters();
        $rows = DB::all("SELECT * FROM orders WHERE $where ORDER BY id DESC LIMIT 5000", $p);
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="orders-' . date('Ymd-His') . '.csv"');
        $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
        fputcsv($o, ['Reference', 'Date', 'Source', 'Customer', 'Mobile', 'City', 'State', 'Subtotal', 'Discount', 'Delivery', 'Total', 'Payment', 'Payment status', 'Status']);
        $safe = fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
        foreach ($rows as $r) fputcsv($o, array_map($safe, [$r['reference'], $r['created_at'], $r['order_source'], $r['customer_name'], $r['mobile'], $r['city'], $r['state'], $r['subtotal'], $r['discount_total'], $r['delivery_charge'], $r['total'], $r['payment_method'], $r['payment_status'], $r['status']]));
        exit;
    }
}
