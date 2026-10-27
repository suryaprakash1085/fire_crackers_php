<?php
namespace App\Controllers\Admin;

use App\Models\Setting;

/** Company profile: legal details, tax/licence numbers, payment details and invoice text. Used on the footer and printed invoices. */
final class CompanyController extends AdminController {
    protected string $minRole = 'admin';
    public const NUMS = ['min_order_amount' => 'Minimum order amount', 'delivery_charge' => 'Delivery charge', 'free_delivery_above' => 'Free delivery above'];
    public const FIELDS = ['min_order_amount', 'delivery_charge', 'free_delivery_above', 'brand_name', 'company_legal_name', 'company_owner', 'company_gst', 'company_pan', 'company_license', 'company_established', 'contact_address', 'contact_phone', 'contact_email', 'whatsapp_number', 'business_hours', 'company_website', 'company_upi_id', 'company_bank_name', 'company_bank_holder', 'company_bank_account', 'company_bank_ifsc', 'invoice_header_note', 'invoice_terms', 'invoice_footer', 'license_info', 'footer_text', 'social_facebook', 'social_instagram', 'social_youtube'];
    public function index(): void { $this->render('admin/company', ['title' => 'Company Profile', 'fields' => self::FIELDS]); }
    public function save(): void {
        $this->post(); $kv = [];
        foreach (self::FIELDS as $k) if (isset($_POST[$k]) && is_string($_POST[$k])) $kv[$k] = mb_substr(trim($_POST[$k]), 0, 1500);
        foreach (self::NUMS as $k => $label) if (isset($kv[$k])) { $v = str_replace(',', '', $kv[$k]); if ($v === '') $v = '0'; if (!is_numeric($v) || (float)$v < 0 || (float)$v > 10000000) { flash('error', $label . ' must be a number (0 or more).'); redirect('/admin/company'); } $kv[$k] = (string)round((float)$v, 2); }
        if (!empty($kv['contact_email']) && !filter_var($kv['contact_email'], FILTER_VALIDATE_EMAIL)) { flash('error', 'Company email is invalid.'); redirect('/admin/company'); }
        if (isset($kv['whatsapp_number'])) $kv['whatsapp_number'] = preg_replace('/\D+/', '', $kv['whatsapp_number']);
        if (!empty($kv['company_gst']) && !preg_match('/^[0-9A-Z]{15}$/', strtoupper($kv['company_gst']))) { flash('error', 'GSTIN must be 15 letters/digits (or leave empty).'); redirect('/admin/company'); }
        if (!empty($kv['company_gst'])) $kv['company_gst'] = strtoupper($kv['company_gst']);
        foreach (['social_facebook', 'social_instagram', 'social_youtube', 'company_website'] as $s) if (!empty($kv[$s]) && !preg_match('#^https?://#', $kv[$s])) $kv[$s] = '';
        Setting::setMany($kv); flash('success', 'Company profile saved.'); redirect('/admin/company');
    }
}
