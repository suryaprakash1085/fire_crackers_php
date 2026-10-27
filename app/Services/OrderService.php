<?php
namespace App\Services;

use App\Core\DB;

final class OrderService {
    public static function validate(array $in): array {
        $err = []; $d = [];
        $d['customer_name'] = trim((string)($in['customer_name'] ?? ''));
        $d['mobile'] = preg_replace('/\D+/', '', (string)($in['mobile'] ?? ''));
        if (strlen($d['mobile']) > 10 && str_starts_with($d['mobile'], '91')) $d['mobile'] = substr($d['mobile'], -10);
        $d['email'] = trim((string)($in['email'] ?? ''));
        $d['address'] = trim((string)($in['address'] ?? ''));
        $d['pincode'] = preg_replace('/\D+/', '', (string)($in['pincode'] ?? ''));
        $d['city'] = trim((string)($in['city'] ?? ''));
        $d['state'] = trim((string)($in['state'] ?? ''));
        $d['landmark'] = trim((string)($in['landmark'] ?? ''));
        $d['notes'] = trim((string)($in['notes'] ?? ''));
        $d['payment_method'] = (string)($in['payment_method'] ?? '');
        if (mb_strlen($d['customer_name']) < 2 || mb_strlen($d['customer_name']) > 120) $err['customer_name'] = 'Enter your full name.';
        if (!preg_match('/^[6-9]\d{9}$/', $d['mobile'])) $err['mobile'] = 'Enter a valid 10-digit Indian mobile number.';
        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err['email'] = 'Enter a valid email address.';
        if (mb_strlen($d['address']) < 10) $err['address'] = 'Enter your complete delivery address.';
        if (!preg_match('/^[1-9]\d{5}$/', $d['pincode'])) $err['pincode'] = 'Enter a valid 6-digit pincode.';
        if (mb_strlen($d['city']) < 2) $err['city'] = 'Enter your city.';
        if (!in_array($d['state'], indian_states(), true)) $err['state'] = 'Select your state.';
        else {
            $restricted = array_filter(array_map('trim', explode(',', (string)setting('restricted_states', ''))));
            if (in_array($d['state'], $restricted, true)) $err['state'] = 'Sorry, we cannot sell or deliver firecrackers to ' . $d['state'] . '.';
        }
        $methods = self::paymentMethods();
        if (!isset($methods[$d['payment_method']])) $err['payment_method'] = 'Select a payment option.';
        if (empty($in['ack_terms'])) $err['ack_terms'] = 'Please accept the terms and conditions.';
        if (empty($in['ack_age'])) $err['ack_age'] = 'You must confirm you are 18 or older.';
        if (empty($in['ack_safety'])) $err['ack_safety'] = 'Please acknowledge the safety guidelines.';
        return [$d, $err];
    }
    public static function paymentMethods(): array {
        $m = [];
        if (setting('payment_cod_enabled', '1') === '1') $m['cod'] = ['label' => setting('payment_cod_label', 'Pay on Delivery'), 'note' => ''];
        if (setting('payment_upi_enabled', '0') === '1') $m['upi'] = ['label' => setting('payment_upi_label', 'UPI / Bank transfer'), 'note' => setting('payment_upi_instructions', '')];
        return $m;
    }
    private static function newReference(): string {
        $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $s = '';
        for ($i = 0; $i < 10; $i++) $s .= $alpha[random_int(0, 31)];
        return 'FC-' . substr($s, 0, 5) . '-' . substr($s, 5);
    }

    /** Creates the order inside one transaction. Returns ['ok'=>bool,'reference'=>?,'message'=>?] */
    public static function place(array $cust, string $token): array {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return ['ok' => false, 'message' => 'Invalid checkout session. Please reload the page.'];
        $existing = DB::one('SELECT reference FROM orders WHERE idempotency_token=?', [$token]);
        if ($existing) return ['ok' => true, 'reference' => $existing['reference'], 'duplicate' => true];

        $raw = CartService::raw();
        if (!$raw) return ['ok' => false, 'message' => 'Your cart is empty.'];
        $source = CartService::source();
        $pdo = DB::pdo();
        try {
            $pdo->beginTransaction();
            $ids = array_map('intval', array_keys($raw)); sort($ids);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $rows = DB::all("SELECT id,sku,name,original_price,selling_price,stock,status FROM products WHERE id IN ($in) ORDER BY id FOR UPDATE", $ids);
            $by = array_column($rows, null, 'id');
            $lines = []; $orig = 0.0; $sell = 0.0;
            foreach ($raw as $id => $qty) {
                $p = $by[$id] ?? null; $qty = (int)$qty;
                if (!$p || $p['status'] !== 'active') throw new \DomainException('An item in your cart is no longer available. Please review your cart.');
                if ($qty < 1 || $qty > (int)$p['stock']) throw new \DomainException('Not enough stock for ' . $p['name'] . ' (available: ' . (int)$p['stock'] . '). Please update your cart.');
                $lt = round((float)$p['selling_price'] * $qty, 2);
                $lines[] = ['p' => $p, 'qty' => $qty, 'lt' => $lt];
                $orig += (float)$p['original_price'] * $qty; $sell += $lt;
            }
            $bulkMin = (float)setting('bulk_discount_min', 0); $bulkPct = (float)setting('bulk_discount_percent', 0);
            $bulk = ($bulkMin > 0 && $bulkPct > 0 && $sell >= $bulkMin) ? round($sell * $bulkPct / 100, 2) : 0.0;
            $after = $sell - $bulk;
            $min = (float)setting('min_order_amount', 0);
            if ($min > 0 && $after < $min) throw new \DomainException('Minimum order amount is ' . money($min) . '.');
            $freeAbove = (float)setting('free_delivery_above', 0);
            $delivery = ($freeAbove > 0 && $after >= $freeAbove) ? 0.0 : (float)setting('delivery_charge', 0);
            $total = round($after + $delivery, 2);

            $ref = '';
            for ($t = 0; $t < 5; $t++) { $ref = self::newReference(); if (!DB::val('SELECT 1 FROM orders WHERE reference=?', [$ref])) break; }
            $orderId = DB::insert('orders', [
                'reference' => $ref, 'order_source' => $source, 'customer_name' => $cust['customer_name'], 'mobile' => $cust['mobile'],
                'email' => $cust['email'] ?: null, 'address' => $cust['address'], 'pincode' => $cust['pincode'], 'city' => $cust['city'], 'state' => $cust['state'],
                'landmark' => $cust['landmark'] ?: null, 'notes' => $cust['notes'] ?: null, 'subtotal' => round($orig, 2), 'discount_total' => round($orig - $sell + $bulk, 2),
                'delivery_charge' => $delivery, 'total' => $total, 'payment_method' => $cust['payment_method'], 'payment_status' => 'pending', 'status' => 'placed',
                'idempotency_token' => $token, 'ip_address' => client_ip(),
            ]);
            foreach ($lines as $l) {
                DB::insert('order_items', ['order_id' => $orderId, 'product_id' => $l['p']['id'], 'sku' => $l['p']['sku'], 'name' => $l['p']['name'],
                    'original_price' => $l['p']['original_price'], 'unit_price' => $l['p']['selling_price'], 'quantity' => $l['qty'], 'line_total' => $l['lt']]);
                $ok = DB::exec('UPDATE products SET stock = stock - ? WHERE id=? AND stock >= ?', [$l['qty'], $l['p']['id'], $l['qty']]);
                if ($ok !== 1) throw new \DomainException('Stock changed for ' . $l['p']['name'] . '. Please review your cart.');
            }
            DB::insert('order_status_history', ['order_id' => $orderId, 'status' => 'placed', 'note' => 'Order placed by customer (' . $source . ')', 'changed_by' => 'customer']);
            $pdo->commit();
        } catch (\DomainException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((int)$e->getCode() === 23000) { // duplicate token from a double submit
                $ex = DB::one('SELECT reference FROM orders WHERE idempotency_token=?', [$token]);
                if ($ex) return ['ok' => true, 'reference' => $ex['reference'], 'duplicate' => true];
            }
            log_error('order failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not place your order. Please try again.'];
        }
        $order = DB::one('SELECT * FROM orders WHERE id=?', [$orderId]);
        MailService::orderConfirmation($order, DB::all('SELECT * FROM order_items WHERE order_id=?', [$orderId]));
        CartService::clear();
        return ['ok' => true, 'reference' => $ref];
    }

    public static function byReference(string $ref): ?array {
        $o = DB::one('SELECT * FROM orders WHERE reference=?', [strtoupper(trim($ref))]);
        if (!$o) return null;
        $o['items'] = DB::all('SELECT * FROM order_items WHERE order_id=? ORDER BY id', [$o['id']]);
        $o['history'] = DB::all('SELECT status,note,created_at FROM order_status_history WHERE order_id=? ORDER BY created_at, id', [$o['id']]);
        return $o;
    }

    /** Admin status change; restores stock when cancelling. */
    public static function changeStatus(int $id, string $status, string $note, string $by): array {
        if (!isset(order_statuses()[$status])) return ['ok' => false, 'message' => 'Invalid status.'];
        $pdo = DB::pdo();
        try {
            $pdo->beginTransaction();
            $o = DB::one('SELECT id,status FROM orders WHERE id=? FOR UPDATE', [$id]);
            if (!$o) { $pdo->rollBack(); return ['ok' => false, 'message' => 'Order not found.']; }
            if ($o['status'] === 'cancelled' && $status !== 'cancelled') { $pdo->rollBack(); return ['ok' => false, 'message' => 'A cancelled order cannot be reopened.']; }
            if ($o['status'] === $status) { $pdo->rollBack(); return ['ok' => false, 'message' => 'Order already has this status.']; }
            if ($status === 'cancelled') {
                foreach (DB::all('SELECT product_id, quantity FROM order_items WHERE order_id=? AND product_id IS NOT NULL', [$id]) as $it)
                    DB::exec('UPDATE products SET stock = stock + ? WHERE id=?', [$it['quantity'], $it['product_id']]);
            }
            DB::update('orders', ['status' => $status], 'id=?', [$id]);
            DB::insert('order_status_history', ['order_id' => $id, 'status' => $status, 'note' => $note ?: null, 'changed_by' => $by]);
            $pdo->commit();
            return ['ok' => true, 'message' => 'Status updated' . ($status === 'cancelled' ? ' and stock restored.' : '.')];
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); log_error('status: ' . $e->getMessage()); return ['ok' => false, 'message' => 'Could not update status.']; }
    }
}
