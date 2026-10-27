<?php
$products = App\Models\Product::allActive(); $max = max(1, (int)setting('max_qty_per_item', 500));
$cats = []; foreach ($products as $p) $cats[$p['category_name']] = true; $cats = array_keys($cats); $lastCat = null;
?>
<section class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 py-8">
  <div class="relative overflow-hidden rounded-3xl p-6 md:p-9 text-white" style="background:linear-gradient(120deg,var(--c-secondary),var(--c-primary))">
    <div class="absolute right-4 top-3 opacity-25 hidden sm:block"><?= art('sound', 'w-40 h-40') ?></div>
    <nav class="relative text-xs text-white/75 mb-2" aria-label="Breadcrumb"><a href="<?= e(url('/')) ?>" class="hover:underline">Home</a> / <span class="text-white">Quick Order</span></nav>
    <h1 class="relative font-display font-extrabold text-3xl md:text-5xl flex items-center gap-3"><?= icon('zap', 'w-8 h-8 md:w-10 md:h-10') ?> Quick Order</h1>
    <p class="relative mt-2 text-white/85 max-w-2xl md:text-lg">Every product in one fast table. Type quantities, hit one button — done.</p>
    <div class="relative mt-5 flex flex-wrap gap-2 text-sm"><?php foreach ([[count($products) . ' products', 'package'], [count($cats) . ' categories', 'layout-grid'], ['1-tap add to cart', 'mouse-pointer-click']] as [$t, $ic]): ?><span class="inline-flex items-center gap-1.5 bg-white/18 backdrop-blur rounded-full px-3.5 py-1.5 font-semibold"><?= icon($ic, 'w-4 h-4') ?><?= e($t) ?></span><?php endforeach; ?></div>
  </div>
  <div class="grid xl:grid-cols-[1fr_360px] gap-6 mt-6 items-start">
    <div class="min-w-0">
      <!-- toolbar -->
      <div class="card p-3 md:p-4 flex flex-wrap items-center gap-2.5">
        <div class="relative flex-1 min-w-[200px]"><label class="sr-only" for="qo-search">Search products</label><input id="qo-search" type="search" class="input !pl-10" placeholder="Search by ID, name or Tamil name…" autocomplete="off"><span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-ink/40 pointer-events-none"><?= icon('search', 'w-4 h-4') ?></span></div>
        <label class="sr-only" for="qo-cat">Category</label><select id="qo-cat" class="select !w-auto min-w-[160px]"><option value="">All categories</option><?php foreach ($cats as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select>
        <label class="flex items-center gap-2 text-sm font-medium px-2 cursor-pointer select-none"><input type="checkbox" id="qo-only" class="w-4 h-4 accent-[var(--c-primary)]"> Selected only</label>
        <span class="text-xs muted ml-auto" id="qo-count"><?= count($products) ?> products</span>
      </div>

      <div class="mt-3 flex gap-2 overflow-x-auto pb-1 -mx-4 px-4 md:mx-0 md:px-0" id="qo-tabs" role="tablist" aria-label="Categories"><button type="button" class="chip active" data-tab="">All</button><?php foreach ($cats as $c): ?><button type="button" class="chip" data-tab="<?= e($c) ?>"><?= e($c) ?></button><?php endforeach; ?></div>
      <!-- table -->
      <div class="card mt-3 overflow-hidden">
        <div class="md:max-h-[68vh] md:overflow-auto">
          <table class="qo-table w-full text-sm">
            <thead><tr><th class="w-16">Image</th><th>ID</th><th>Product</th><th>Category</th><th class="text-right">Price</th><th>Stock</th><th class="text-center">Quantity</th><th class="text-right">Total</th></tr></thead>
            <tbody id="qo-body">
            <?php foreach ($products as $p):
              $stock = (int)$p['stock']; $out = $stock <= 0; $lim = min($max, $stock); $disc = (float)$p['discount_percent']; [$sl, $sc] = App\Models\Product::stockLabel($p);
              if ($lastCat !== $p['category_name']): $lastCat = $p['category_name']; ?><tr class="cat-row" data-cat-row="<?= e($lastCat) ?>"><td colspan="8"><?= e($lastCat) ?></td></tr><?php endif; ?>
              <tr data-id="<?= (int)$p['id'] ?>" data-price="<?= e((string)(float)$p['selling_price']) ?>" data-limit="<?= $lim ?>" data-cat="<?= e($p['category_name']) ?>" data-sku="<?= e(strtolower($p['sku'])) ?>" data-hay="<?= e(mb_strtolower($p['sku'] . ' ' . $p['name'] . ' ' . ($p['name_ta'] ?? '') . ' ' . ($p['aliases'] ?? ''))) ?>">
                <td data-col="img"><a href="<?= e(url('/product-details.php?id=' . (int)$p['id'])) ?>" class="block w-12 h-12 md:w-11 md:h-11 rounded-xl tint-accent overflow-hidden flex items-center justify-center"><?php if (!empty($p['image'])): ?><img src="<?= e(upload_url($p['image'])) ?>" alt="" class="w-full h-full object-cover" loading="lazy" width="48" height="48"><?php else: ?><?= icon('sparkles', 'w-5 h-5 text-primary/50') ?><?php endif; ?></a></td>
                <td data-col="sku" class="font-mono text-xs font-semibold whitespace-nowrap"><?= e($p['sku']) ?></td>
                <td data-col="name" class="min-w-[170px]"><a href="<?= e(url('/product-details.php?id=' . (int)$p['id'])) ?>" class="font-semibold hover:text-primary leading-snug block"><?= e($p['name']) ?></a><?php if (!empty($p['name_ta'])): ?><span class="text-xs muted block"><?= e($p['name_ta']) ?></span><?php endif; ?><span class="md:hidden font-mono text-[11px] muted"><?= e($p['sku']) ?></span></td>
                <td data-col="cat" class="text-xs muted whitespace-nowrap"><?= e($p['category_name']) ?></td>
                <td data-col="price" class="md:text-right whitespace-nowrap"><b><?= money($p['selling_price']) ?></b><?php if ($disc > 0): ?> <s class="text-xs muted"><?= money($p['original_price']) ?></s><?php endif; ?></td>
                <td data-col="stock" class="whitespace-nowrap"><span class="badge <?= $sc ?>"><?= e($sl) ?></span></td>
                <td data-col="qty" class="text-center"><?php if ($out): ?><span class="text-xs muted">—</span><?php else: ?><div class="qty qo-qty"><button type="button" data-qo-step="-1" aria-label="Decrease quantity of <?= e($p['name']) ?>">−</button><input type="number" inputmode="numeric" min="0" max="<?= $lim ?>" value="" placeholder="0" aria-label="Quantity of <?= e($p['name']) ?>"><button type="button" data-qo-step="1" aria-label="Increase quantity of <?= e($p['name']) ?>">+</button></div><?php endif; ?></td>
                <td data-col="total" class="text-right font-semibold whitespace-nowrap" data-line>—</td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <div id="qo-empty" class="hidden p-12 text-center muted"><?= icon('package-open', 'w-10 h-10 mx-auto opacity-40') ?><p class="font-semibold mt-3">No products match your search</p></div>
          <?php if (!$products): ?><div class="p-12 text-center muted">No products are available right now.</div><?php endif; ?>
        </div>
      </div>

      <!-- floating action pill -->
      <div class="qo-bar" id="qo-bar" aria-live="polite">
        <div class="pr-1"><div class="text-[11px] opacity-70 leading-none">Selected</div><div class="font-bold text-sm md:text-base whitespace-nowrap"><span id="qo-n">0</span> items · <span id="qo-units">0</span> units · <span id="qo-total" class="text-accent">₹0.00</span></div></div>
        <button type="button" id="qo-clear" class="p-2 rounded-full hover:bg-white/15" aria-label="Reset quantities" disabled><?= icon('rotate-ccw', 'w-4 h-4') ?></button>
        <button type="button" id="qo-add" class="btn btn-primary !rounded-full" disabled><?= icon('shopping-cart', 'w-4 h-4') ?> Add to cart</button>
      </div>

      <!-- bulk paste -->
      <details class="card mt-4 group">
        <summary class="p-4 md:p-5 cursor-pointer font-bold flex items-center gap-2 list-none"><?= icon('list-plus', 'w-5 h-5 text-primary') ?> Have a list? Paste product IDs &amp; quantities<span class="ml-auto text-ink/40 group-open:rotate-180 transition"><?= icon('chevron-down', 'w-5 h-5') ?></span></summary>
        <div class="px-4 md:px-5 pb-5"><p class="text-sm muted mb-3">One per line: <code class="bg-black/5 px-1 rounded">PRODUCT-ID quantity</code>, e.g. <code class="bg-black/5 px-1 rounded">FP101 5</code>. The quantities are filled into the table above so you can review them before adding.</p>
          <textarea id="qo-bulk" class="textarea font-mono text-sm" placeholder="FP101 5&#10;SP201 2&#10;GC301 3"></textarea><button type="button" id="qo-bulk-btn" class="btn btn-soft mt-3"><?= icon('table-properties', 'w-4 h-4') ?> Fill the table</button><div id="qo-bulk-result" class="text-sm mt-3 space-y-0.5"></div></div>
      </details>
    </div>

    <aside class="xl:sticky xl:top-24"><div class="card p-5"><h2 class="font-bold mb-2 flex items-center gap-2 font-display"><?= icon('shopping-bag', 'w-5 h-5 text-primary') ?> Your order list</h2><div data-cart-panel data-mode="compact"><div class="py-8 text-center"><span class="spinner"></span></div></div></div></aside>
  </div>
</section>
<?php $GLOBALS["pageScripts"] = ['assets/js/quick-order.js']; ?>
