<?php
$groups = [
 ['Default discount', 'percent', [['company_discount_percent', 'Default discount % (0–90) — product selling price = MRP − this %', 'number']]],
 ['Order rules (checkout)', 'shopping-cart', [['min_order_amount', 'Minimum order amount (₹) — 0 = no minimum', 'number'], ['delivery_charge', 'Delivery charge (₹)', 'number'], ['free_delivery_above', 'Free delivery above (₹) — 0 = never free', 'number']]],
 ['Business identity', 'building-2', [['brand_name', 'Shop / brand name', 'text'], ['company_legal_name', 'Legal company name', 'text'], ['company_owner', 'Owner / proprietor', 'text'], ['company_established', 'Established (year)', 'text'], ['company_gst', 'GSTIN (15 characters)', 'text'], ['company_pan', 'PAN', 'text'], ['company_license', 'Explosives / fireworks licence no.', 'text']]],
 ['Contact', 'phone', [['contact_phone', 'Phone', 'text'], ['whatsapp_number', 'WhatsApp number (digits, with country code)', 'text'], ['contact_email', 'Email', 'email'], ['company_website', 'Website (https://…)', 'text'], ['business_hours', 'Business hours', 'text'], ['contact_address', 'Address', 'textarea']]],
 ['Payment details (shown on invoice)', 'wallet', [['company_upi_id', 'GPay / UPI ID (e.g. name@okhdfcbank) — used for the payment QR on invoices', 'text'], ['company_bank_name', 'Bank name', 'text'], ['company_bank_holder', 'Account holder', 'text'], ['company_bank_account', 'Account number', 'text'], ['company_bank_ifsc', 'IFSC', 'text']]],
 ['Invoice & footer text', 'file-text', [['invoice_header_note', 'Invoice header note', 'text'], ['invoice_terms', 'Invoice terms & conditions', 'textarea'], ['invoice_footer', 'Invoice footer (thank-you line)', 'text'], ['license_info', 'Licence line shown in website footer', 'textarea'], ['footer_text', 'Footer description', 'textarea']]],
 ['Social links', 'share-2', [['social_facebook', 'Facebook URL', 'text'], ['social_instagram', 'Instagram URL', 'text'], ['social_youtube', 'YouTube URL', 'text']]],
]; ?>
<div class="mb-5"><h2 class="font-display font-bold text-2xl">Company Profile</h2><p class="text-sm muted">These details appear on printed invoices and in the website footer / contact sections.</p></div>
<?php $logoPath = (string)setting('company_logo_path', ''); $logoOk = $logoPath !== '' && is_file(PUBLIC_PATH . '/' . $logoPath); ?>
<form method="post" action="<?= e(url('/admin/company')) ?>" enctype="multipart/form-data" class="space-y-5"><?= csrf_field() ?>
  <section class="card p-5 md:p-6"><h3 class="font-bold text-lg flex items-center gap-2 mb-4"><span class="w-9 h-9 rounded-xl tint-primary text-primary flex items-center justify-center"><?= icon('image', 'w-4 h-4') ?></span>Company logo</h3>
    <div class="flex items-start gap-4 flex-wrap">
      <div class="w-40 h-24 rounded-xl border bg-white flex items-center justify-center overflow-hidden shrink-0" id="logo-box"><?php if ($logoOk): ?><img id="logo-preview" src="<?= e(upload_url($logoPath)) ?>" alt="Current company logo" class="max-w-full max-h-full object-contain"><?php else: ?><img id="logo-preview" alt="" class="max-w-full max-h-full object-contain hidden"><span id="logo-empty" class="text-gray-300"><?= icon('image', 'w-8 h-8') ?></span><?php endif; ?></div>
      <div class="space-y-2 min-w-0 flex-1"><label class="label" for="company_logo"><?= $logoOk ? 'Replace logo' : 'Upload logo' ?></label>
        <input id="company_logo" name="company_logo" type="file" accept="image/jpeg,image/png,image/webp,image/svg+xml" class="block text-sm w-full">
        <p class="hint">JPG, PNG, WebP or SVG, max 2 MB. Shown in the website header, the admin panel and on invoices. Without a logo the default icon is used.</p>
        <p class="err hidden" id="logo-err" role="alert"></p>
        <?php if ($logoOk): ?><label class="flex items-center gap-2 text-sm text-red-700"><input type="checkbox" name="remove_logo" value="1" id="remove-logo"> Remove current logo</label><?php endif; ?></div></div></section>
<?php foreach ($groups as [$title, $ic, $fs]): $k0 = $fs[0][0]; ?>
  <section class="card p-5 md:p-6"><h3 class="font-bold text-lg flex items-center gap-2 mb-4"><span class="w-9 h-9 rounded-xl tint-primary text-primary flex items-center justify-center"><?= icon($ic, 'w-4 h-4') ?></span><?= e($title) ?></h3>
    <div class="grid sm:grid-cols-2 gap-4"><?php foreach ($fs as [$k, $l, $t]): ?><div class="<?= $t === 'textarea' ? 'sm:col-span-2' : '' ?>"><label class="label" for="f-<?= e($k) ?>"><?= e($l) ?></label>
      <?php if ($t === 'textarea'): ?><textarea id="f-<?= e($k) ?>" name="<?= e($k) ?>" class="textarea" maxlength="1500"><?= e(setting($k)) ?></textarea><?php else: ?><input id="f-<?= e($k) ?>" name="<?= e($k) ?>" type="<?= $t ?>" <?= $t === 'number' ? ($k === 'company_discount_percent' ? 'min="0" max="90" step="0.01"' : 'min="0" step="1"') . ' inputmode="decimal"' : '' ?> <?= $k === 'company_upi_id' ? 'pattern="[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z]{2,64}" placeholder="name@okhdfcbank" title="Format: name@bank, e.g. name@okhdfcbank" autocapitalize="off" spellcheck="false"' : '' ?> class="input" value="<?= e(setting($k)) ?>" maxlength="190" autocomplete="off"><?php endif; ?></div><?php endforeach; ?></div>
    <?php if ($k0 === 'company_discount_percent'): ?><div class="mt-4 rounded-xl border border-black/10 bg-black/[.02] p-3 text-sm"><p>Current default discount: <b><?= e(rtrim(rtrim(number_format(company_discount(), 2), '0'), '.')) ?>%</b>. The website prices already follow this value automatically. To also refresh the selling price <i>stored</i> in the database for every product, press the button below.</p>
      <button type="submit" form="apply-discount-form" class="btn btn-outline btn-sm mt-2" id="apply-discount-btn"><?= icon('refresh-cw', 'w-4 h-4') ?> Apply discount to all products</button></div><?php endif; ?></section>
<?php endforeach; ?>
<div class="sticky bottom-3 z-20"><div class="card glass p-3 flex justify-end gap-2 shadow-lift"><a class="btn btn-outline" href="<?= e(url('/admin')) ?>">Cancel</a><button class="btn btn-primary"><?= icon('save', 'w-4 h-4') ?> Save company profile</button></div></div>
</form>
<form id="apply-discount-form" method="post" action="<?= e(url('/admin/company/apply-discount')) ?>"><?= csrf_field() ?><input type="hidden" name="confirm" value="1"></form>
<script>(()=>{
  const f=document.getElementById('company_logo'),img=document.getElementById('logo-preview'),empty=document.getElementById('logo-empty'),err=document.getElementById('logo-err'),rm=document.getElementById('remove-logo');
  f&&f.addEventListener('change',()=>{const x=f.files[0];err.classList.add('hidden');if(!x)return;
    if(!/^image\/(jpeg|png|webp|svg\+xml)$/.test(x.type)){err.textContent='Only JPG, PNG, WebP or SVG files are allowed.';err.classList.remove('hidden');f.value='';return}
    if(x.size>2*1024*1024){err.textContent='The logo must be 2 MB or smaller.';err.classList.remove('hidden');f.value='';return}
    img.src=URL.createObjectURL(x);img.classList.remove('hidden');empty&&empty.classList.add('hidden');if(rm)rm.checked=false});
  rm&&rm.addEventListener('change',()=>{img.style.opacity=rm.checked?.25:1});
  const b=document.getElementById('apply-discount-btn');
  b&&b.addEventListener('click',e=>{if(!confirm('Apply the default discount ('+(document.getElementById('f-company_discount_percent').value||0)+'%) to ALL products?\n\nThis re-writes the stored selling price and discount % of every product (including inactive and archived ones). Save the company profile first if you just changed the percentage.'))e.preventDefault()});
})()</script>
