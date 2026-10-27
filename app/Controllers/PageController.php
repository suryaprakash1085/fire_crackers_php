<?php
namespace App\Controllers;

use App\Core\{Controller, DB, RateLimiter};
use App\Models\Page;

final class PageController extends Controller {
    public function about(): void {
        $p = Page::bySlug('about');
        $this->view('front/page', ['title' => 'About Us', 'page' => $p, 'wide' => true]);
    }
    public function services(): void {
        $this->view('front/services', ['title' => 'Our Services', 'services' => DB::all('SELECT * FROM services WHERE is_active=1 ORDER BY display_order, id')]);
    }
    public function policy(string $slug): void {
        $p = in_array($slug, ['privacy', 'terms', 'shipping', 'refund', 'safety'], true) ? Page::bySlug($slug) : null;
        if (!$p) { http_response_code(404); $this->view('front/404', ['title' => 'Not found']); return; }
        $this->view('front/page', ['title' => $p['title'], 'page' => $p]);
    }
    public function contact(): void {
        $this->view('front/contact', ['title' => 'Contact Us', 'errors' => []]);
    }
    public function contactSubmit(): void {
        $this->requireCsrf();
        $name = $this->input('name'); $email = $this->input('email'); $mobile = preg_replace('/\D+/', '', $this->input('mobile'));
        $subject = $this->input('subject'); $msg = $this->input('message'); $err = [];
        if ($this->input('website') !== '') redirect('/contact'); // honeypot
        if (mb_strlen($name) < 2) $err['name'] = 'Please enter your name.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err['email'] = 'Invalid email address.';
        if ($email === '' && !preg_match('/^[6-9]\d{9}$/', $mobile)) $err['mobile'] = 'Provide a valid mobile number or an email.';
        if (mb_strlen($msg) < 10) $err['message'] = 'Please write a few words (min 10 characters).';
        if (RateLimiter::tooMany('contact', client_ip(), 5, 3600)) $err['message'] = 'Too many messages. Please try again later.';
        if ($err) { keep_old($_POST); $this->view('front/contact', ['title' => 'Contact Us', 'errors' => $err]); return; }
        DB::insert('contact_enquiries', ['name' => mb_substr($name, 0, 120), 'email' => $email ?: null, 'mobile' => $mobile ?: null, 'subject' => mb_substr($subject, 0, 190) ?: null, 'message' => mb_substr($msg, 0, 4000)]);
        RateLimiter::hit('contact', client_ip()); clear_old();
        flash('success', 'Thank you! Your message has been sent. We will get back to you soon.');
        redirect('/contact');
    }
}
