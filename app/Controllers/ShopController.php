<?php
namespace App\Controllers;

use App\Core\{Controller, DB, RateLimiter};
use App\Services\{CartService, OrderService};

final class ShopController extends Controller {
    public function cart(): void { $this->view('front/cart', ['title' => 'Shopping Cart', 'cart' => CartService::summary()]); }
    public function quickOrder(): void { $this->view('front/quick-order', ['title' => 'Quick Order']); }
    public function voiceOrder(): void { $this->view('front/voice-order', ['title' => 'Voice Order']); }

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
        if (RateLimiter::tooMany('checkout', client_ip(), 15, 3600)) { flash('error', 'Too many order attempts. Please try later.'); redirect('/checkout'); }
        [$data, $errors] = OrderService::validate($_POST);
        $methods = OrderService::paymentMethods();
        if (!$errors && !$cart['meets_min']) $errors['cart'] = 'Minimum order amount is ' . money($cart['min_order']) . '.';
        if ($errors) { keep_old($_POST); $this->view('front/checkout', ['title' => 'Checkout', 'cart' => $cart, 'errors' => $errors, 'methods' => $methods]); return; }
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
        }
        $this->view('front/track', ['title' => 'Track Order', 'o' => $o, 'error' => $err]);
    }
}
