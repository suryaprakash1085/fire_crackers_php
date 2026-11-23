<?php
namespace App\Controllers\Admin;

use App\Core\DB;
use App\Models\Setting;
use App\Services\CompanyLogo;

/** Company profile: legal details, tax/licence numbers, payment details and invoice text. Used on the footer and printed invoices. */
final class CompanyController extends AdminController {
    protected string $minRole = 'admin';
    public const NUMS = ['company_discount_percent' => 'Discount percentage', 'min_order_amount' => 'Minimum order amount', 'delivery_charge' => 'Delivery charge', 'free_delivery_above' => 'Free delivery above'];
    public const FIELDS = ['company_discount_percent', 'min_order_amount', 'delivery_charge', 'free_delivery_above', 'brand_name', 'company_legal_name', 'company_owner', 'company_gst', 'company_pan', 'company_license', 'company_established', 'contact_address', 'contact_phone', 'contact_email', 'whatsapp_number', 'business_hours', 'company_website', 'company_upi_id', 'company_bank_name', 'company_bank_holder', 'company_bank_account', 'company_bank_ifsc', 'invoice_header_note', 'invoice_terms', 'invoice_footer', 'license_info', 'footer_text', 'social_facebook', 'social_instagram', 'social_youtube'];
    public function index(): void { $this->render('admin/company', ['title' => 'Company Profile', 'fields' => self::FIELDS]); }
    public function save(): void {
        $this->post(); $kv = [];
        foreach (self::FIELDS as $k) if (isset($_POST[$k]) && is_string($_POST[$k])) $kv[$k] = mb_substr(trim($_POST[$k]), 0, 1500);
        foreach (self::NUMS as $k => $label) if (isset($kv[$k])) { $v = str_replace(',', '', $kv[$k]); if ($v === '') $v = '0'; if (!is_numeric($v) || (float)$v < 0 || (float)$v > 10000000) { flash('error', $label . ' must be a number (0 or more).'); redirect('/admin/company'); } $kv[$k] = (string)round((float)$v, 2); }
        if (isset($kv['company_discount_percent']) && (float)$kv['company_discount_percent'] > 90) { flash('error', 'Discount percentage cannot exceed 90%.'); redirect('/admin/company'); }
        if (!empty($kv['contact_email']) && !filter_var($kv['contact_email'], FILTER_VALIDATE_EMAIL)) { flash('error', 'Company email is invalid.'); redirect('/admin/company'); }
        if (isset($kv['whatsapp_number'])) $kv['whatsapp_number'] = preg_replace('/\D+/', '', $kv['whatsapp_number']);
        if (!empty($kv['company_gst']) && !preg_match('/^[0-9A-Z]{15}$/', strtoupper($kv['company_gst']))) { flash('error', 'GSTIN must be 15 letters/digits (or leave empty).'); redirect('/admin/company'); }
        if (!empty($kv['company_gst'])) $kv['company_gst'] = strtoupper($kv['company_gst']);
        foreach (['social_facebook', 'social_instagram', 'social_youtube', 'company_website'] as $s) if (!empty($kv[$s]) && !preg_match('#^https?://#', $kv[$s])) $kv[$s] = '';
        if (isset($kv['company_upi_id']) && $kv['company_upi_id'] !== '' && !valid_upi_id($kv['company_upi_id'])) { flash('error', 'GPay / UPI ID is not valid. Use the format name@bank, e.g. name@okhdfcbank.'); redirect('/admin/company'); }

        // ---- logo: upload a new one and/or remove the current one (the old file is deleted from disk once the new settings are saved)
        $oldLogo = (string)setting('company_logo_path', ''); $newLogo = $oldLogo;
        if (!empty($_FILES['company_logo']['name'])) {
            $r = CompanyLogo::store($_FILES['company_logo']);
            if (!$r['ok']) { flash('error', $r['message']); redirect('/admin/company'); }
            $newLogo = (string)$r['path'];
        } elseif (!empty($_POST['remove_logo'])) $newLogo = '';
        $kv['company_logo_path'] = $newLogo;

        $oldDisc = company_discount(); Setting::setMany($kv); $newDisc = company_discount();
        if ($oldLogo !== '' && $oldLogo !== $newLogo) CompanyLogo::delete($oldLogo);
        $msg = 'Company profile saved.';
        if ($newLogo !== $oldLogo) $msg .= $newLogo === '' ? ' Logo removed.' : ' Logo updated.';
        if ($oldDisc !== $newDisc) $msg .= ' Default discount is now ' . rtrim(rtrim(number_format($newDisc, 2), '0'), '.') . '%: every product price follows it automatically on the website. Use "Apply discount to all products" to also refresh the prices stored in the database.';
        flash('success', $msg); redirect('/admin/company');
    }

    /** Re-writes the stored selling_price / discount_percent of EVERY product from its MRP and the current default discount.
     *  (The website already calculates prices live from the discount, so this only refreshes the stored copy used by exports/reports.) */
    public function applyDiscount(): void {
        $this->post();
        if (empty($_POST['confirm'])) { flash('error', 'Please confirm that you want to apply the discount to all products.'); redirect('/admin/company'); }
        $d = company_discount(); $total = (int)DB::val('SELECT COUNT(*) FROM products');
        DB::exec('UPDATE products SET selling_price = ROUND(original_price - ROUND(original_price * ? / 100, 2), 2), discount_percent = ?', [number_format($d, 2, '.', ''), number_format($d, 2, '.', '')]);
        flash('success', 'Default discount ' . rtrim(rtrim(number_format($d, 2), '0'), '.') . '% applied to all ' . $total . ' product' . ($total === 1 ? '' : 's') . '.'); redirect('/admin/company');
    }
}
