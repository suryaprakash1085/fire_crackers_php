<?php
namespace App\Controllers\Admin;

use App\Core\{DB, View};
use App\Models\Product;
use App\Services\{OrderService, OrderEditor, PdfInvoice, Xlsx};

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
    // ---------- popup (modal) endpoints ----------
    private function loadOrNull(string $id): ?array {
        $o = DB::one('SELECT * FROM orders WHERE id=?', [(int)$id]);
        if (!$o) return null;
        $o['items'] = DB::all('SELECT * FROM order_items WHERE order_id=? ORDER BY id', [$o['id']]);
        $o['history'] = DB::all('SELECT * FROM order_status_history WHERE order_id=? ORDER BY created_at DESC, id DESC', [$o['id']]);
        return $o;
    }
    private function fragment(string $tpl, string $id, array $extra = []): void {
        $o = $this->loadOrNull($id);
        if (!$o) { http_response_code(404); echo '<div class="om-empty">Order not found.</div>'; return; }
        header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store');
        View::render($tpl, ['o' => $o, 'inv' => OrderEditor::invoiceData($o)] + $extra);
    }
    /** Order Details modal body. */
    public function modalView(string $id): void { $this->fragment('admin/orders/_modal_view', $id); }
    /** Full edit form (modal body). */
    public function editForm(string $id): void { $this->fragment('admin/orders/_modal_edit', $id, ['methods' => OrderService::paymentMethods()]); }
    /** Invoice preview (modal body). */
    public function invoicePreview(string $id): void { $this->fragment('admin/orders/_invoice', $id); }
    public function productSearch(): void {
        $q = mb_substr($this->input('q'), 0, 60);
        if ($q === '') $this->json(['ok' => true, 'products' => []]);
        $this->json(['ok' => true, 'products' => array_map(fn($p) => ['id' => (int)$p['id'], 'sku' => $p['sku'], 'name' => $p['name'], 'original' => (float)$p['original_price'], 'price' => (float)$p['selling_price'], 'stock' => (int)$p['stock']], Product::lookup($q, 8))]);
    }
    public function editSave(string $id): void {
        if (!is_post()) $this->json(['ok' => false, 'message' => 'Invalid request.'], 405);
        $this->requireCsrf(true);
        $r = OrderEditor::update((int)$id, $_POST, $this->admin['name']);
        if ($r['ok']) flash('success', $r['message']);
        $this->json($r, $r['ok'] ? 200 : 422);
    }
    public function cancel(string $id): void {
        if (!is_post()) $this->json(['ok' => false, 'message' => 'Invalid request.'], 405);
        $this->requireCsrf(true);
        $r = OrderService::changeStatus((int)$id, 'cancelled', mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 250) ?: 'Cancelled by admin', $this->admin['name']);
        if ($r['ok']) $r['status_html'] = status_badge('cancelled');
        $this->json($r, $r['ok'] ? 200 : 422);
    }
    /** Real PDF file download of the invoice. */
    public function pdf(string $id): void {
        $o = $this->loadOrNull($id);
        if (!$o) { flash('error', 'Order not found.'); redirect('/admin/orders'); }
        $bin = PdfInvoice::invoice(OrderEditor::invoiceData($o));
        header('Content-Type: application/pdf'); header('Content-Length: ' . strlen($bin)); header('Cache-Control: private, no-store');
        header('Content-Disposition: attachment; filename="Invoice-' . preg_replace('/[^A-Za-z0-9_-]/', '', $o['reference']) . '.pdf"');
        echo $bin; exit;
    }

    private const EXPORT_HEAD = ['Order No', 'Date', 'Customer Name', 'Mobile', 'Email', 'Address', 'City', 'State', 'Pincode', 'Landmark', 'Items', 'Subtotal', 'Discount', 'Total', 'Payment Status', 'Order Status', 'Notes'];

    /** Header + one row per order for the CURRENT filters (search, status, source, payment, date range). Numbers stay numbers; the date is an Excel date-time. */
    private function exportRows(): array {
        [$where, $p] = $this->filters();
        $orders = DB::all("SELECT * FROM orders WHERE $where ORDER BY id DESC LIMIT 20000", $p);
        $items = [];
        foreach (array_chunk(array_column($orders, 'id'), 1000) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            foreach (DB::all("SELECT order_id,name,quantity FROM order_items WHERE order_id IN ($in) ORDER BY id", $chunk) as $it) $items[(int)$it['order_id']][] = $it['name'] . ' x ' . (int)$it['quantity'];
        }
        $rows = [self::EXPORT_HEAD];
        foreach ($orders as $o) {
            // Excel stores date-times as days since 1899-12-30; the stored wall-clock time is kept as is (no timezone shift)
            $serial = (new \DateTimeImmutable($o['created_at'], new \DateTimeZone('UTC')))->getTimestamp() / 86400 + 25569;
            $rows[] = [$o['reference'], round($serial, 6), $o['customer_name'], $o['mobile'], (string)$o['email'], $o['address'], $o['city'], $o['state'], $o['pincode'], (string)$o['landmark'],
                implode("\n", $items[(int)$o['id']] ?? []), round((float)$o['subtotal'], 2), round((float)$o['discount_total'], 2), round((float)$o['total'], 2),
                ucfirst($o['payment_status']), order_statuses()[$o['status']] ?? $o['status'], (string)$o['notes']];
        }
        return $rows;
    }
    /** Excel download of the filtered orders: orders_YYYY-MM-DD.xlsx. Only logged-in admin-panel users reach this controller (see AdminController). */
    public function exportExcel(): void {
        $rows = $this->exportRows(); $day = date('Y-m-d');
        if (!Xlsx::available()) { $this->csvDownload($rows, "orders_$day.csv"); return; }   // fallback when PHP's zip extension is off
        $bin = Xlsx::build([['name' => 'Orders', 'rows' => $rows, 'autoWidth' => true, 'maxWidth' => 50, 'textCols' => [3, 8], 'numCols' => [11, 12, 13], 'dateCols' => [1], 'wrapCols' => [5, 10, 16]]]);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="orders_' . $day . '.xlsx"');
        header('Content-Length: ' . strlen($bin)); header('Cache-Control: private, no-store');
        echo $bin; exit;
    }
    /** UTF-8 CSV with a BOM so Excel shows Tamil text correctly. Dates are written as text; cells that could be read as formulas are neutralised. */
    private function csvDownload(array $rows, string $name): never {
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $name . '"'); header('Cache-Control: private, no-store');
        $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
        $safe = fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
        foreach ($rows as $i => $r) {
            if ($i > 0) $r[1] = gmdate('Y-m-d H:i', (int)round(((float)$r[1] - 25569) * 86400));
            fputcsv($o, array_map($safe, $r));
        }
        exit;
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
