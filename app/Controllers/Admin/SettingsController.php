<?php
namespace App\Controllers\Admin;

use App\Core\DB;
use App\Models\Setting;
use App\Services\{MailService, MediaService};

final class SettingsController extends AdminController {
    protected string $minRole = 'admin';
    private const COLORS = ['color_primary', 'color_secondary', 'color_accent', 'color_bg', 'color_text', 'color_button', 'color_button_text'];
    private const TOGGLES = ['show_offers', 'show_categories', 'show_featured', 'show_popular', 'show_new', 'show_about', 'show_services', 'show_safety', 'show_contact', 'payment_cod_enabled', 'payment_upi_enabled'];
    private const TEXTS = ['brand_name' => 100, 'payment_cod_label' => 120, 'payment_upi_label' => 120, 'payment_upi_instructions' => 500, 'restricted_states' => 1000];
    private const NUMS = ['delivery_charge', 'free_delivery_above', 'min_order_amount', 'bulk_discount_min', 'bulk_discount_percent', 'max_qty_per_item'];

    public function customize(): void { $this->render('admin/customize', ['title' => 'App Customization', 'errors' => []]); }
    public function saveCustomize(): void {
        $this->post(); $kv = []; $err = [];
        foreach (self::COLORS as $k) { $v = trim((string)($_POST[$k] ?? '')); if (valid_hex($v)) $kv[$k] = strtoupper($v); else $err[] = "Invalid colour for $k."; }
        foreach (self::TOGGLES as $k) $kv[$k] = empty($_POST[$k]) ? '0' : '1';
        foreach (self::TEXTS as $k => $max) $kv[$k] = mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
        if ($kv['brand_name'] === '') $err[] = 'Brand name is required.';
        foreach (self::NUMS as $k) { $n = (float)($_POST[$k] ?? 0); if ($n < 0) $err[] = "$k cannot be negative."; $kv[$k] = (string)$n; }
        if ((float)$kv['bulk_discount_percent'] > 90) $err[] = 'Bulk discount cannot exceed 90%.';
        $kv['header_style'] = in_array($_POST['header_style'] ?? '', ['white', 'tinted'], true) ? $_POST['header_style'] : 'white';
        $kv['footer_style'] = in_array($_POST['footer_style'] ?? '', ['white', 'tinted'], true) ? $_POST['footer_style'] : 'tinted';
        $states = array_filter(array_map('trim', explode(',', $kv['restricted_states'])));
        $bad = array_diff($states, indian_states()); if ($bad) $err[] = 'Unknown state(s): ' . implode(', ', $bad) . '. Use exact names separated by commas.';
        foreach (['logo', 'favicon'] as $f) {
            $cur = (string)setting($f, ''); $new = $cur;
            if (!empty($_FILES[$f . '_file']['name'])) { $r = MediaService::upload($_FILES[$f . '_file']); if ($r['ok']) $new = $r['media']['path']; else $err[] = ucfirst($f) . ': ' . $r['message']; }
            elseif (!empty($_POST[$f . '_lib'])) $new = MediaService::validPath((string)$_POST[$f . '_lib']) ?: $cur;
            if (!empty($_POST[$f . '_remove'])) $new = '';
            $kv[$f] = $new;
        }
        if ($err) { foreach ($err as $m) flash('error', $m); redirect('/admin/customize'); }
        Setting::setMany($kv); flash('success', 'Settings saved. The public website now uses the new look.'); redirect('/admin/customize');
    }

    public function smtp(): void { $this->render('admin/smtp', ['title' => 'Email (SMTP) Settings', 's' => DB::one('SELECT * FROM email_settings WHERE id=1') ?: [], 'envFallback' => (string)env('SMTP_HOST', '') !== '']); }
    public function saveSmtp(): void {
        $this->post(); $enc = (string)($_POST['encryption'] ?? 'tls');
        $d = ['sender_name' => mb_substr(trim((string)($_POST['sender_name'] ?? '')), 0, 120), 'sender_email' => trim((string)($_POST['sender_email'] ?? '')), 'host' => trim((string)($_POST['host'] ?? '')),
            'port' => (int)($_POST['port'] ?? 587), 'encryption' => in_array($enc, ['none', 'tls', 'ssl'], true) ? $enc : 'tls', 'username' => trim((string)($_POST['username'] ?? ''))];
        if ($d['sender_email'] !== '' && !filter_var($d['sender_email'], FILTER_VALIDATE_EMAIL)) { flash('error', 'Sender email is invalid.'); redirect('/admin/smtp'); }
        if ($d['port'] < 1 || $d['port'] > 65535) { flash('error', 'Invalid port.'); redirect('/admin/smtp'); }
        if (($_POST['password'] ?? '') !== '') $d['password_enc'] = encrypt_secret((string)$_POST['password']);
        if (!empty($_POST['clear_password'])) $d['password_enc'] = '';
        DB::update('email_settings', $d, 'id=1'); flash('success', 'SMTP settings saved.'); redirect('/admin/smtp');
    }
    public function testSmtp(): void {
        $this->post(); $to = trim((string)($_POST['test_to'] ?? $this->admin['email']));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { flash('error', 'Enter a valid test recipient.'); redirect('/admin/smtp'); }
        $r = MailService::send($to, 'Test email from ' . setting('brand_name'), '<p>SMTP is working. This test was sent from your admin panel at ' . e(now()) . '.</p>');
        flash($r === true ? 'success' : 'error', $r === true ? "Test email sent to $to." : 'Test failed: ' . $r);
        redirect('/admin/smtp');
    }
}
