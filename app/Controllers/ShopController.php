<?php
namespace App\Controllers;

use App\Core\{Controller, DB, RateLimiter};
use App\Services\PdfInvoice;
use App\Services\OrderEditor;
use App\Services\{CartService, OrderService, PriceList};

final class ShopController extends Controller {
    /** The cart is per-visitor and changes all the time: never let a browser/proxy show an old copy after back/forward. */
    private function noStore(): void { header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); header('Pragma: no-cache'); header('Expires: 0'); }
    public function cart(): void { $this->noStore(); $this->view('front/cart', ['title' => 'Shopping Cart', 'cart' => CartService::summary()]); }
    public function quickOrder(): void { $this->noStore(); $this->view('front/quick-order', ['title' => 'Quick Order', 'cart' => CartService::summary()]); }
    public function voiceOrder(): void { $this->view('front/voice-order', ['title' => 'Voice Order']); }
    /** Public price-list download (the uploaded PDF, or one generated from the active products). */
    public function priceList(): void {
        if (!PriceList::send()) { flash('info', 'The price list is not available right now. Please contact us for the latest prices.'); redirect('/products'); }
    }

    public function checkout(): void {
        $cart = CartService::summary();
        if (!$cart['items']) { flash('error', 'Your cart is empty.'); redirect('/products'); }
        if (empty($_SESSION['checkout_token'])) $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
        $this->view('front/checkout', ['title' => 'Checkout', 'cart' => $cart, 'errors' => [], 'methods' => OrderService::paymentMethods()]);
    }
    public function place(): void {
        $this->requireCsrf();
        $cart = CartService::summary();
        $token = (string)($_POST['checkout_token'] ?? '');
        $sessionToken = (string)($_SESSION['checkout_token'] ?? '');
        if (!$cart['items'] && !DB::val('SELECT 1 FROM orders WHERE idempotency_token=?', [$token])) { flash('error', 'Your cart is empty.'); redirect('/products'); }
        if ($sessionToken === '' || !hash_equals($sessionToken, $token)) {
            // Double-submit after success: send to the existing order if this token already produced one.
            $ex = DB::one('SELECT reference FROM orders WHERE idempotency_token=?', [$token]);
            if ($ex && in_array($ex['reference'], $_SESSION['recent_orders'] ?? [], true)) redirect('/order-success?ref=' . urlencode($ex['reference']));
            flash('error', 'Checkout session expired. Please review your cart and try again.'); redirect('/checkout');
        }
        // Stock / availability changed since the customer looked at the cart: never order a different cart than the one they saw.
        $adjusted = array_filter($cart['issues'] ?? [], fn($m) => !str_starts_with((string)$m, 'Minimum order amount'));   // (the minimum-order rule has its own message below)
        if ($adjusted) { flash('error', implode(' ', array_unique($adjusted)) . ' Please review your cart and place the order again.'); redirect('/cart'); }
        if (RateLimiter::tooMany('checkout', client_ip(), 40, 3600)) { flash('error', 'Too many order attempts from this connection. Please try again in a little while.'); redirect('/checkout'); }
        [$data, $errors] = OrderService::validate($_POST);
        $methods = OrderService::paymentMethods();
        // The minimum-order rule is always reported (even together with field errors) and says exactly how much is missing
        if (!$cart['meets_min']) $errors['cart'] = 'Minimum order amount is ' . money($cart['min_order']) . '. Add ' . money(max(0, $cart['min_order'] - ($cart['subtotal'] - $cart['discount']))) . ' more to place your order.';
        if ($errors) { keep_old(array_diff_key($_POST, ['ack_terms' => 1, 'ack_age' => 1, 'ack_safety' => 1])); $this->view('front/checkout', ['title' => 'Checkout', 'cart' => $cart, 'errors' => $errors, 'methods' => $methods]); return; }
        RateLimiter::hit('checkout', client_ip());
        $res = OrderService::place($data, $token);
        if (!$res['ok']) { flash('error', $res['message']); redirect('/cart'); }
        unset($_SESSION['checkout_token']); clear_old();
        $_SESSION['recent_orders'][] = $res['reference'];
        redirect('/order-success?ref=' . urlencode($res['reference']));
    }

    public function success(): void {
        $ref = strtoupper($this->input('ref'));
        if (!in_array($ref, $_SESSION['recent_orders'] ?? [], true)) { flash('info', 'Use your order reference and mobile number to view an order.'); redirect('/track'); }
        $o = OrderService::byReference($ref);
        if (!$o) redirect('/track');
        $this->view('front/order-success', ['title' => 'Order placed', 'o' => $o]);
    }
    /** Customer invoice PDF. Allowed only for an order this browser just placed or has verified with reference + mobile on the Track page. */
    public function invoice(): void {
        $ref = strtoupper(trim($this->input('ref')));
        if (!in_array($ref, $_SESSION['recent_orders'] ?? [], true) && !in_array($ref, $_SESSION['invoice_ok'] ?? [], true)) { flash('info', 'Enter your order reference and mobile number to view your order and invoice.'); redirect('/track'); }
        $o = OrderService::byReference($ref);
        if (!$o) redirect('/track');
        $bin = PdfInvoice::invoice(OrderEditor::invoiceData($o));
        header('Content-Type: application/pdf'); header('Content-Length: ' . strlen($bin)); header('Cache-Control: private, no-store'); header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="Invoice-' . preg_replace('/[^A-Za-z0-9_-]/', '', $o['reference']) . '.pdf"');
        echo $bin; exit;
    }
    public function track(): void { $this->view('front/track', ['title' => 'Track Order', 'o' => null, 'error' => null]); }
    public function trackSubmit(): void {
        $this->requireCsrf();
        $ref = strtoupper($this->input('reference')); $mobile = preg_replace('/\D+/', '', $this->input('mobile'));
        $mobile = strlen($mobile) > 10 ? substr($mobile, -10) : $mobile; $err = null; $o = null;
        $key = client_ip();
        if (RateLimiter::tooMany('track', $key, 10, 900)) $err = 'Too many attempts. Please wait 15 minutes and try again.';
        else {
            $o = OrderService::byReference($ref);
            if (!$o || $o['mobile'] !== $mobile) { $o = null; $err = 'No order found for that reference and mobile number.'; RateLimiter::hit('track', $key); }
            else $_SESSION['invoice_ok'][] = $o['reference'];   // verified with reference + mobile: this browser may download the invoice
        }
        $this->view('front/track', ['title' => 'Track Order', 'o' => $o, 'error' => $err]);
    }
}
