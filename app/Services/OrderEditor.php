<?php
namespace App\Services;

use App\Core\DB;

/** Full admin edit of an existing order: customer, address, items, prices, discount, payment, status. Recalculates totals and keeps stock in sync. */
final class OrderEditor {
    /** Data used by the view modal, edit modal, HTML invoice preview and PDF. */
    public static function invoiceData(array $o): array {
        $lines = []; $sub = 0.0; $sell = 0.0;
        foreach ($o['items'] as $i) {
            $q = (int)$i['quantity']; $op = (float)$i['original_price']; $up = (float)$i['unit_price'];
            $lines[] = ['name' => $i['name'], 'sku' => $i['sku'], 'qty' => $q, 'original_price' => $op, 'unit_price' => $up, 'discount_unit' => max(0, round($op - $up, 2)),
                'line_original' => round($op * $q, 2), 'line_discount' => round(max(0, $op - $up) * $q, 2), 'line_total' => round((float)$i['line_total'], 2)];
            $sub += $op * $q; $sell += (float)$i['line_total'];
        }
        $itemDisc = max(0.0, round($sub - $sell, 2));
        $extra = max(0.0, round((float)$o['discount_total'] - $itemDisc, 2));
        $methods = OrderService::paymentMethods();
        $m = (string)$o['payment_method'];
        $c = ['name' => setting('brand_name'), 'legal' => setting('company_legal_name') ?: setting('brand_name'), 'address' => setting('contact_address'), 'phone' => setting('contact_phone'), 'email' => setting('contact_email'),
            'gst' => setting('company_gst'), 'license' => setting('company_license'), 'upi' => setting('company_upi_id'), 'bank_name' => setting('company_bank_name'), 'bank_holder' => setting('company_bank_holder'),
            'bank_account' => setting('company_bank_account'), 'bank_ifsc' => setting('company_bank_ifsc'), 'terms' => setting('invoice_terms'), 'footer' => setting('invoice_footer'), 'note' => setting('invoice_header_note')];
        return ['order' => $o, 'company' => $c, 'lines' => $lines, 'subtotal' => round($sub, 2), 'item_discount' => $itemDisc, 'extra_discount' => $extra,
            'discount_percent' => company_discount(), 'status_label' => order_statuses()[$o['status']] ?? $o['status'],
            'method_label' => $methods[$m]['label'] ?? ($m === 'cod' ? 'Pay on Delivery' : ($m === 'upi' ? 'UPI / Bank transfer' : ucfirst($m)))];
    }

    /** @return array{ok:bool,message:string} */
    public static function update(int $id, array $in, string $by): array {
        $pdo = DB::pdo();
        // ---- customer / address / payment validation
        $d = [];
        $d['customer_name'] = trim((string)($in['customer_name'] ?? ''));
        $d['mobile'] = preg_replace('/\D+/', '', (string)($in['mobile'] ?? ''));
        if (strlen($d['mobile']) > 10 && str_starts_with($d['mobile'], '91')) $d['mobile'] = substr($d['mobile'], -10);
        $d['email'] = trim((string)($in['email'] ?? '')) ?: null;
        $d['address'] = trim((string)($in['address'] ?? ''));
        $d['pincode'] = preg_replace('/\D+/', '', (string)($in['pincode'] ?? ''));
        $d['city'] = trim((string)($in['city'] ?? ''));
        $d['state'] = trim((string)($in['state'] ?? ''));
        $d['landmark'] = mb_substr(trim((string)($in['landmark'] ?? '')), 0, 190) ?: null;
        $d['notes'] = trim((string)($in['notes'] ?? '')) ?: null;
        $d['internal_notes'] = trim((string)($in['internal_notes'] ?? '')) ?: null;
        $d['tracking_info'] = mb_substr(trim((string)($in['tracking_info'] ?? '')), 0, 250) ?: null;
        $d['payment_method'] = mb_substr(trim((string)($in['payment_method'] ?? '')), 0, 30);
        $d['payment_status'] = (string)($in['payment_status'] ?? 'pending');
        if (mb_strlen($d['customer_name']) < 2 || mb_strlen($d['customer_name']) > 120) return self::fail('Customer name is required (2–120 characters).');
        if (!preg_match('/^[6-9]\d{9}$/', $d['mobile'])) return self::fail('Enter a valid 10-digit mobile number.');
        if ($d['email'] !== null && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) return self::fail('Customer email is invalid.');
        if (mb_strlen($d['address']) < 10) return self::fail('Shipping address must be at least 10 characters.');
        if (!preg_match('/^[1-9]\d{5}$/', $d['pincode'])) return self::fail('Enter a valid 6-digit pincode.');
        if (mb_strlen($d['city']) < 2) return self::fail('City is required.');
        if (!in_array($d['state'], indian_states(), true)) return self::fail('Select a valid state.');
        if ($d['payment_method'] === '') return self::fail('Select a payment method.');
        if (!in_array($d['payment_status'], ['pending', 'paid', 'failed', 'refunded'], true)) return self::fail('Invalid payment status.');
        $newStatus = (string)($in['status'] ?? '');
        if (!isset(order_statuses()[$newStatus])) return self::fail('Invalid order status.');
        $delivery = round((float)str_replace(',', '', (string)($in['delivery_charge'] ?? 0)), 2);
        $extra = round((float)str_replace(',', '', (string)($in['extra_discount'] ?? 0)), 2);
        if ($delivery < 0 || $delivery > 1000000) return self::fail('Delivery charge is invalid.');
        if ($extra < 0) return self::fail('Additional discount cannot be negative.');

        // ---- item rows from the form
        $rows = []; $seenProducts = [];
        foreach ((array)($in['items'] ?? []) as $r) {
            if (!is_array($r)) continue;
            $qty = (int)($r['qty'] ?? 0);
            if ($qty < 1 && (int)($r['item_id'] ?? 0) === 0 && (int)($r['product_id'] ?? 0) === 0) continue;
            $rows[] = ['item_id' => (int)($r['item_id'] ?? 0), 'product_id' => (int)($r['product_id'] ?? 0), 'qty' => $qty,
                'orig' => round((float)str_replace(',', '', (string)($r['original_price'] ?? 0)), 2), 'unit' => round((float)str_replace(',', '', (string)($r['unit_price'] ?? 0)), 2)];
        }
        if (!$rows) return self::fail('An order needs at least one product.');

        try {
            $pdo->beginTransaction();
            $o = DB::one('SELECT * FROM orders WHERE id=? FOR UPDATE', [$id]);
            if (!$o) { $pdo->rollBack(); return self::fail('Order not found.'); }
            if ($o['status'] === 'cancelled') { $pdo->rollBack(); return self::fail('Cancelled orders cannot be edited.'); }
            $old = [];
            foreach (DB::all('SELECT * FROM order_items WHERE order_id=? FOR UPDATE', [$id]) as $it) $old[(int)$it['id']] = $it;

            $keep = []; $subtotal = 0.0; $sell = 0.0; $stockDelta = []; // product_id => qty to take from stock (+) or give back (-)
            $inserts = []; $updates = [];
            foreach ($rows as $r) {
                if ($r['qty'] < 1 || $r['qty'] > 100000) throw new \DomainException('Quantity must be between 1 and 100000.');
                if ($r['orig'] < 0 || $r['unit'] < 0 || $r['orig'] > 9999999 || $r['unit'] > 9999999) throw new \DomainException('Product prices are invalid.');
                if ($r['unit'] > $r['orig']) throw new \DomainException('Final price cannot be more than the original price.');
                if ($r['item_id'] && isset($old[$r['item_id']])) {
                    $it = $old[$r['item_id']]; $keep[$r['item_id']] = true;
                    if ($it['product_id']) $stockDelta[(int)$it['product_id']] = ($stockDelta[(int)$it['product_id']] ?? 0) + ($r['qty'] - (int)$it['quantity']);
                    $updates[$r['item_id']] = ['quantity' => $r['qty'], 'original_price' => $r['orig'], 'unit_price' => $r['unit'], 'line_total' => round($r['unit'] * $r['qty'], 2)];
                } else { // newly added product
                    $p = $r['product_id'] ? DB::one('SELECT id,sku,name FROM products WHERE id=?', [$r['product_id']]) : null;
                    if (!$p) throw new \DomainException('A selected product no longer exists.');
                    $stockDelta[(int)$p['id']] = ($stockDelta[(int)$p['id']] ?? 0) + $r['qty'];
                    $inserts[] = ['order_id' => $id, 'product_id' => $p['id'], 'sku' => $p['sku'], 'name' => $p['name'], 'original_price' => $r['orig'], 'unit_price' => $r['unit'], 'quantity' => $r['qty'], 'line_total' => round($r['unit'] * $r['qty'], 2)];
                }
                $subtotal += $r['orig'] * $r['qty']; $sell += round($r['unit'] * $r['qty'], 2);
            }
            foreach ($old as $iid => $it) if (!isset($keep[$iid]) && $it['product_id']) $stockDelta[(int)$it['product_id']] = ($stockDelta[(int)$it['product_id']] ?? 0) - (int)$it['quantity']; // removed lines give stock back
            // stock sync (lock rows in id order)
            ksort($stockDelta);
            foreach ($stockDelta as $pid => $delta) {
                if ($delta === 0) continue;
                $st = DB::one('SELECT name,stock FROM products WHERE id=? FOR UPDATE', [$pid]);
                if (!$st) continue;
                if ($delta > 0 && (int)$st['stock'] < $delta) throw new \DomainException('Not enough stock for ' . $st['name'] . ' (available: ' . (int)$st['stock'] . ', needed extra: ' . $delta . ').');
                DB::exec('UPDATE products SET stock = stock - ? WHERE id=?', [$delta, $pid]);
            }
            foreach ($old as $iid => $it) if (!isset($keep[$iid])) DB::exec('DELETE FROM order_items WHERE id=?', [$iid]);
            foreach ($updates as $iid => $u) DB::update('order_items', $u, 'id=?', [$iid]);
            foreach ($inserts as $row) DB::insert('order_items', $row);

            if ($extra > $sell) throw new \DomainException('Additional discount cannot exceed the items total.');
            $after = round($sell - $extra, 2); $total = round($after + $delivery, 2);
            $d += ['subtotal' => round($subtotal, 2), 'discount_total' => round($subtotal - $sell + $extra, 2), 'delivery_charge' => $delivery, 'total' => $total];
            DB::update('orders', $d, 'id=?', [$id]);
            DB::insert('order_status_history', ['order_id' => $id, 'status' => $o['status'], 'note' => 'Order edited by admin (new total ' . money($total) . ')', 'changed_by' => $by]);
            $pdo->commit();
        } catch (\DomainException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return self::fail($e->getMessage());
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            log_error('order edit: ' . $e->getMessage());
            return self::fail('Could not save the order. Nothing was changed.');
        }
        // status last: cancelling restores stock through the same, tested path as the Cancel button
        if ($newStatus !== $o['status']) {
            $r = OrderService::changeStatus($id, $newStatus, 'Status changed while editing the order', $by);
            if (!$r['ok']) return ['ok' => true, 'message' => 'Order saved, but the status was not changed: ' . $r['message']];
        }
        return ['ok' => true, 'message' => 'Order saved. New total: ' . money($total) . '.'];
    }
    private static function fail(string $m): array { return ['ok' => false, 'message' => $m]; }
}
