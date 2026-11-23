<?php
use App\Services\PriceList;
$mode = PriceList::mode(); $info = PriceList::uploadedInfo(); $hasProducts = PriceList::hasProducts();
$size = $info ? ($info['size'] >= 1048576 ? number_format($info['size'] / 1048576, 2) . ' MB' : number_format(max(1, $info['size'] / 1024), 0) . ' KB') : '';
?>
<div class="mb-5"><h2 class="font-display font-bold text-2xl">Price List</h2><p class="text-sm muted">Visitors download this PDF from the “Download Price List” button on the Products page, Quick Order page and website footer. The button is hidden while no price list is available.</p></div>
<div class="space-y-5">
  <form method="post" action="<?= e(url('/admin/price-list')) ?>" enctype="multipart/form-data" class="space-y-5"><?= csrf_field() ?>
    <section class="card p-5 md:p-6"><h3 class="font-bold text-lg flex items-center gap-2 mb-4"><span class="w-9 h-9 rounded-xl tint-primary text-primary flex items-center justify-center"><?= icon('file-down', 'w-4 h-4') ?></span>Which price list do visitors get?</h3>
      <div class="space-y-3">
        <label class="flex items-start gap-3 text-sm cursor-pointer"><input type="radio" name="mode" value="uploaded" class="mt-1" <?= $mode === 'uploaded' ? 'checked' : '' ?>><span><b>My uploaded PDF</b><span class="hint block">Visitors get the PDF you upload below, exactly as it is.</span></span></label>
        <label class="flex items-start gap-3 text-sm cursor-pointer"><input type="radio" name="mode" value="auto" class="mt-1" <?= $mode === 'auto' ? 'checked' : '' ?>><span><b>Generate automatically</b><span class="hint block">A fresh PDF is built from your active products each time (category, ID, name, MRP, selling price, discount %), with your company name and logo. Always up to date. English names only: the PDF font cannot print Tamil.</span></span></label>
      </div>
      <?php if ($hasProducts): ?><p class="mt-3"><a class="btn btn-outline btn-sm" target="_blank" rel="noopener" href="<?= e(url('/admin/price-list/preview?src=auto')) ?>"><?= icon('eye', 'w-4 h-4') ?> Preview generated PDF</a></p><?php endif; ?>
    </section>
    <section class="card p-5 md:p-6"><h3 class="font-bold text-lg flex items-center gap-2 mb-4"><span class="w-9 h-9 rounded-xl tint-primary text-primary flex items-center justify-center"><?= icon('upload', 'w-4 h-4') ?></span>Uploaded PDF</h3>
      <?php if ($info): ?>
        <div class="rounded-xl border border-black/10 bg-black/[.02] p-3 text-sm mb-4">
          <p class="flex items-center gap-2"><?= icon('file-text', 'w-4 h-4 text-primary') ?><b class="break-all"><?= e($info['name']) ?></b></p>
          <p class="muted mt-1">Size: <b class="text-ink"><?= e($size) ?></b> · Uploaded: <b class="text-ink"><?= e(date('d M Y, h:i A', strtotime($info['updated_at']))) ?></b></p>
          <p class="mt-2"><a class="btn btn-outline btn-sm" target="_blank" rel="noopener" href="<?= e(url('/admin/price-list/preview?src=uploaded')) ?>"><?= icon('eye', 'w-4 h-4') ?> Preview</a>
            <button type="submit" form="pl-remove-form" class="btn btn-danger btn-sm" data-confirm="Remove the uploaded price list? The file is deleted from the server."><?= icon('trash-2', 'w-4 h-4') ?> Remove</button></p>
        </div>
      <?php else: ?><p class="text-sm muted mb-4">No PDF uploaded yet.</p><?php endif; ?>
      <label class="label" for="pricelist"><?= $info ? 'Replace with a new PDF' : 'Upload a PDF' ?></label>
      <input id="pricelist" name="pricelist" type="file" accept="application/pdf,.pdf" class="block text-sm w-full">
      <p class="hint">PDF only, max 10 MB. <?= $info ? 'The current file is deleted once the new one is saved.' : '' ?></p>
    </section>
    <div class="flex justify-end gap-2"><button class="btn btn-primary"><?= icon('save', 'w-4 h-4') ?> Save price list</button></div>
  </form>
  <form method="post" action="<?= e(url('/admin/price-list/remove')) ?>" id="pl-remove-form"><?= csrf_field() ?></form>
</div>
