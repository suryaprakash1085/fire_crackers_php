<?php
$groups = [
 ['Order rules (checkout)', 'shopping-cart', [['min_order_amount', 'Minimum order amount (₹) — 0 = no minimum', 'number'], ['delivery_charge', 'Delivery charge (₹)', 'number'], ['free_delivery_above', 'Free delivery above (₹) — 0 = never free', 'number']]],
 ['Business identity', 'building-2', [['brand_name', 'Shop / brand name', 'text'], ['company_legal_name', 'Legal company name', 'text'], ['company_owner', 'Owner / proprietor', 'text'], ['company_established', 'Established (year)', 'text'], ['company_gst', 'GSTIN (15 characters)', 'text'], ['company_pan', 'PAN', 'text'], ['company_license', 'Explosives / fireworks licence no.', 'text']]],
 ['Contact', 'phone', [['contact_phone', 'Phone', 'text'], ['whatsapp_number', 'WhatsApp number (digits, with country code)', 'text'], ['contact_email', 'Email', 'email'], ['company_website', 'Website (https://…)', 'text'], ['business_hours', 'Business hours', 'text'], ['contact_address', 'Address', 'textarea']]],
 ['Payment details (shown on invoice)', 'wallet', [['company_upi_id', 'UPI ID', 'text'], ['company_bank_name', 'Bank name', 'text'], ['company_bank_holder', 'Account holder', 'text'], ['company_bank_account', 'Account number', 'text'], ['company_bank_ifsc', 'IFSC', 'text']]],
 ['Invoice & footer text', 'file-text', [['invoice_header_note', 'Invoice header note', 'text'], ['invoice_terms', 'Invoice terms & conditions', 'textarea'], ['invoice_footer', 'Invoice footer (thank-you line)', 'text'], ['license_info', 'Licence line shown in website footer', 'textarea'], ['footer_text', 'Footer description', 'textarea']]],
 ['Social links', 'share-2', [['social_facebook', 'Facebook URL', 'text'], ['social_instagram', 'Instagram URL', 'text'], ['social_youtube', 'YouTube URL', 'text']]],
]; ?>
<div class="mb-5"><h2 class="font-display font-bold text-2xl">Company Profile</h2><p class="text-sm muted">These details appear on printed invoices and in the website footer / contact sections.</p></div>
<form method="post" action="<?= e(url('/admin/company')) ?>" class="space-y-5"><?= csrf_field() ?>
<?php foreach ($groups as [$title, $ic, $fs]): ?>
  <section class="card p-5 md:p-6"><h3 class="font-bold text-lg flex items-center gap-2 mb-4"><span class="w-9 h-9 rounded-xl tint-primary text-primary flex items-center justify-center"><?= icon($ic, 'w-4 h-4') ?></span><?= e($title) ?></h3>
    <div class="grid sm:grid-cols-2 gap-4"><?php foreach ($fs as [$k, $l, $t]): ?><div class="<?= $t === 'textarea' ? 'sm:col-span-2' : '' ?>"><label class="label" for="f-<?= e($k) ?>"><?= e($l) ?></label>
      <?php if ($t === 'textarea'): ?><textarea id="f-<?= e($k) ?>" name="<?= e($k) ?>" class="textarea" maxlength="1500"><?= e(setting($k)) ?></textarea><?php else: ?><input id="f-<?= e($k) ?>" name="<?= e($k) ?>" type="<?= $t ?>" <?= $t === 'number' ? 'min="0" step="1" inputmode="decimal"' : '' ?> class="input" value="<?= e(setting($k)) ?>" maxlength="190" autocomplete="off"><?php endif; ?></div><?php endforeach; ?></div></section>
<?php endforeach; ?>
<div class="sticky bottom-3 z-20"><div class="card glass p-3 flex justify-end gap-2 shadow-lift"><a class="btn btn-outline" href="<?= e(url('/admin')) ?>">Cancel</a><button class="btn btn-primary"><?= icon('save', 'w-4 h-4') ?> Save company profile</button></div></div>
</form>
