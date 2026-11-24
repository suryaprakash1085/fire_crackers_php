<?php
$products = App\Models\Product::allActive(); $max = max(1, (int)setting('max_qty_per_item', 500));
$cart = $cart ?? App\Services\CartService::summary();                                  // normalised session cart (stock-clamped, unavailable items removed)
$inCart = []; foreach ($cart['items'] as $ci) $inCart[(int)$ci['id']] = (int)$ci['qty'];   // product id => quantity really stored in the cart
$cats = []; foreach ($products as $p) $cats[$p['category_name']] = true; $cats = array_keys($cats); $lastCat = null;
$sel = []; $rest = [];                                                                    // selected (in the cart, in stock) products go to the TOP; the normal order follows
foreach ($products as $i => $p) { $p['_order'] = $i; if (($inCart[(int)$p['id']] ?? 0) > 0 && (int)$p['stock'] > 0) $sel[] = $p; else $rest[] = $p; }
?>
<section class="qo-page w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 py-6 md:py-8">
<?php $fw = function (float $cx, float $cy, float $r, int $n, string $col, float $w = 1.5): string { $o = ''; for ($i = 0; $i < $n; $i++) { $a = 2 * M_PI * $i / $n; $c = cos($a); $sn = sin($a); $o .= '<line x1="' . round($cx + $c * $r * .38, 1) . '" y1="' . round($cy + $sn * $r * .38, 1) . '" x2="' . round($cx + $c * $r * (.78 + ($i % 2) * .22), 1) . '" y2="' . round($cy + $sn * $r * (.78 + ($i % 2) * .22), 1) . '"/>' . ($i % 2 ? '<circle cx="' . round($cx + $c * $r * 1.12, 1) . '" cy="' . round($cy + $sn * $r * 1.12, 1) . '" r="1.5" stroke="none" fill="' . $col . '"/>' : ''); } return '<g stroke="' . $col . '" stroke-width="' . $w . '" stroke-linecap="round" fill="none">' . $o . '</g>'; }; ?>
  <header class="qo-head">
    <div class="qo-head-text">
      <nav class="qo-crumb" aria-label="Breadcrumb"><a href="<?= e(url('/home')) ?>">Home</a> / <span>Quick Order</span></nav>
      <h1 class="qo-title"><span class="qo-bolt"><?= icon('zap', 'w-6 h-6') ?></span><span>Quick <b>Order</b></span></h1>
      <p class="qo-sub">Choose your crackers and add quantities easily.</p>
    </div>
    <div class="qo-head-act"><?php if (App\Services\PriceList::available()): ?><a href="<?= e(url('/price-list')) ?>" class="qo-pl btn btn-outline !rounded-full"><span class="qo-pdf" aria-hidden="true">PDF</span> Download Price List</a><?php endif; ?></div>
    <svg class="qo-head-art" viewBox="0 0 260 130" aria-hidden="true" focusable="false">
      <path d="M176 130V112h8V100h8V88h8V76h8V66h6V58h4v8h6v10h8v12h8v12h8v12h8v18z" fill="#FBD9BF" opacity=".75"/>
      <?= $fw(150, 50, 44, 28, '#E8590C', 1.7) ?><?= $fw(232, 40, 30, 22, '#F59F00', 1.5) ?><?= $fw(92, 34, 18, 14, '#7048E8', 1.3) ?>
      <path d="M118 112l2.2 5.4 5.4 2.2-5.4 2.2-2.2 5.4-2.2-5.4-5.4-2.2 5.4-2.2z" fill="#F59F00"/><path d="M60 78l1.6 4 4 1.6-4 1.6-1.6 4-1.6-4-4-1.6 4-1.6z" fill="#F59F00"/>
    </svg>
  </header>
  <div class="grid grid-cols-1 gap-6 mt-6 items-start">
    <div class="min-w-0">
      <!-- filters: search + (selected only, count) on one row on desktop, category pills below -->
      <?php $catIcon = function (string $c): string { $c = strtolower($c); foreach (['flower' => 'flower-2', 'spark' => 'sparkles', 'chakra' => 'disc-3', 'rocket' => 'rocket', 'sound' => 'volume-2', 'kid' => 'baby', 'gift' => 'gift'] as $k => $i) if (str_contains($c, $k)) return $i; return 'sparkle'; }; ?>
      <div class="qo-filters">
        <div class="qo-search"><label class="sr-only" for="qo-search">Search products</label><span class="qo-search-ic"><?= icon('search', 'w-4 h-4') ?></span><input id="qo-search" type="search" class="input" placeholder="Search by name, Tamil name or code…" autocomplete="off"><button type="button" class="qo-x" id="qo-search-clear" aria-label="Clear search" hidden>×</button></div>
        <div class="qo-tabswrap" id="qo-tabswrap">
          <button type="button" class="qo-arrow qo-prev" aria-label="Scroll categories left" hidden><?= icon('chevron-left', 'w-4 h-4') ?></button>
          <div class="qo-tabs" id="qo-tabs" role="tablist" aria-label="Categories"><button type="button" class="chip active" data-tab=""><?= icon('layout-grid', 'w-4 h-4') ?>All</button><?php foreach ($cats as $c): ?><button type="button" class="chip" data-tab="<?= e($c) ?>"><?= icon($catIcon($c), 'w-4 h-4') ?><?= e($c) ?></button><?php endforeach; ?></div>
          <button type="button" class="qo-arrow qo-next" aria-label="Scroll categories right" hidden><?= icon('chevron-right', 'w-4 h-4') ?></button>
        </div>
        <div class="qo-meta">
          <label class="qo-switch"><input type="checkbox" id="qo-only"> Selected only</label>
          <span class="qo-count" id="qo-count"><?= count($products) ?> products</span>
          <label class="sr-only" for="qo-cat">Category</label><select id="qo-cat" hidden><option value="">All categories</option><?php foreach ($cats as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select>
        </div>
      </div>
      <style>
        .qo-table tbody tr.picked td:first-child{box-shadow:inset 3px 0 0 var(--c-primary)}
        .qo-table tbody tr.picked .qo-qty{border-color:var(--c-primary);background:#fff}
        .qo-table tbody tr.dirty .qo-qty{box-shadow:0 0 0 2px rgb(var(--c-primary-rgb)/.25)}
        .qo-table tbody tr.is-oos{opacity:.6}
      </style>
      <p id="qo-rule" title="The quantity in each row is saved to your cart automatically."><span class="qo-info" aria-hidden="true">i</span><span>Type a quantity (or use + / −) — it is <b>added to your cart automatically</b>. Click the cart icon to see your items. Set a row to 0 to remove it.</span></p>
      <!-- table -->
      <div class="card qo-list mt-3 overflow-hidden">
        <div class="md:max-h-[68vh] md:overflow-auto">
          <table class="qo-table w-full text-sm">
            <thead><tr><th class="w-16">Image</th><th>ID</th><th>Product</th><th>Category</th><th class="text-right">Price</th><th>Stock</th><th class="text-center">Quantity</th><th class="text-right">Total</th></tr></thead>
            <tbody id="qo-body">
            <?php
            $renderRow = function (array $p, string $grp) use ($max, $inCart): void {
              $stock = (int)$p['stock']; $out = $stock <= 0; $lim = min($max, $stock); $disc = (float)$p['discount_percent']; [$sl, $sc] = App\Models\Product::stockLabel($p);
              $q = $out ? 0 : min($lim, (int)($inCart[(int)$p['id']] ?? 0)); ?>
              <tr data-id="<?= (int)$p['id'] ?>" data-order="<?= (int)$p['_order'] ?>" data-grp="<?= e($grp) ?>" data-price="<?= e((string)(float)$p['selling_price']) ?>" data-limit="<?= $lim ?>" data-cat="<?= e($p['category_name']) ?>" data-sku="<?= e(strtolower($p['sku'])) ?>" data-hay="<?= e(mb_strtolower($p['sku'] . ' ' . $p['name'] . ' ' . ($p['name_ta'] ?? '') . ' ' . ($p['aliases'] ?? ''))) ?>" class="<?= $q > 0 ? 'picked' : '' ?> <?= $out ? 'is-oos' : '' ?>" <?= $out ? 'aria-disabled="true"' : '' ?>>
                <td data-col="img"><a href="<?= e(url('/product-details.php?id=' . (int)$p['id'])) ?>" class="block w-12 h-12 md:w-11 md:h-11 rounded-xl tint-accent overflow-hidden flex items-center justify-center"><?php if (!empty($p['image'])): ?><img src="<?= e(upload_thumb_url($p['image'])) ?>" alt="" class="w-full h-full object-cover" loading="lazy" width="48" height="48"><?php else: ?><?= icon('sparkles', 'w-5 h-5 text-primary/50') ?><?php endif; ?></a></td>
                <td data-col="sku" class="font-mono text-xs font-semibold whitespace-nowrap"><?= e($p['sku']) ?></td>
                <td data-col="name" class="min-w-[170px]"><a href="<?= e(url('/product-details.php?id=' . (int)$p['id'])) ?>" class="font-semibold hover:text-primary leading-snug block"><?= e($p['name']) ?></a><?php if (!empty($p['name_ta'])): ?><span class="text-xs muted block"><?= e($p['name_ta']) ?></span><?php endif; ?><span class="md:hidden font-mono text-[11px] muted"><?= e($p['sku']) ?></span></td>
                <td data-col="cat" class="text-xs muted whitespace-nowrap"><?= e($p['category_name']) ?></td>
                <td data-col="price" class="md:text-right whitespace-nowrap"><b><?= money($p['selling_price']) ?></b><?php if ($disc > 0): ?> <s class="text-xs muted"><?= money($p['original_price']) ?></s><?php endif; ?></td>
                <td data-col="stock" class="whitespace-nowrap"><span class="badge <?= $sc ?>"><?= e($sl) ?></span></td>
                <td data-col="qty" class="text-center"><?php if ($out): ?><span class="text-xs muted">Out of stock</span><?php else: ?><div class="qty qo-qty"><button type="button" data-qo-step="-1" aria-label="Decrease quantity of <?= e($p['name']) ?>">−</button><input type="number" inputmode="numeric" min="0" max="<?= $lim ?>" step="1" value="<?= $q ?>" aria-label="Quantity of <?= e($p['name']) ?>"><button type="button" data-qo-step="1" aria-label="Increase quantity of <?= e($p['name']) ?>">+</button></div><?php endif; ?></td>
                <td data-col="total" class="text-right font-semibold whitespace-nowrap"><span data-line><?= $q > 0 ? money((float)$p['selling_price'] * $q) : '—' ?></span><?php if (!$out): ?><button type="button" class="qo-rm" data-qo-remove aria-label="Remove <?= e($p['name']) ?> (set to 0)">×</button><?php endif; ?></td>
              </tr>
            <?php };
            if ($sel): ?><tr class="cat-row sel-row" data-cat-row="__sel"><td colspan="5"><span class="gt">Selected Products (<?= count($sel) ?>)</span><button type="button" class="qo-clearall" data-qo-clearall>Clear all</button></td></tr><?php foreach ($sel as $p) $renderRow($p, '__sel'); endif;
            foreach ($rest as $p):
              if ($lastCat !== $p['category_name']): $lastCat = $p['category_name']; ?><tr class="cat-row" data-cat-row="<?= e($lastCat) ?>"><td colspan="5" role="button" tabindex="0" aria-expanded="true"><span class="gt"><?= e($lastCat) ?></span></td></tr><?php endif;
              $renderRow($p, $p['category_name']);
            endforeach; ?>
            </tbody>
          </table>
          <div id="qo-empty" class="hidden p-12 text-center muted"><?= icon('package-open', 'w-10 h-10 mx-auto opacity-40') ?><p class="font-semibold mt-3">No products match your search</p></div>
          <?php if (!$products): ?><div class="p-12 text-center muted">No products are available right now.</div><?php endif; ?>
        </div>
      </div>

      <!-- floating action pill -->
      <?php $qoMin = (float)setting('min_order_amount', 0); $qoBulkMin = (float)setting('bulk_discount_min', 0); $qoBulkPct = (float)setting('bulk_discount_percent', 0); ?>
      <div class="qo-bar" id="qo-bar" aria-live="polite" data-min-order="<?= e((string)$qoMin) ?>" data-bulk-discount-min="<?= e((string)$qoBulkMin) ?>" data-bulk-discount-percent="<?= e((string)$qoBulkPct) ?>">
        <div class="min-line" id="qo-min" role="status" hidden><span class="qo-min-ic" aria-hidden="true"></span><span class="qo-min-body"><span id="qo-min-txt"></span><span class="min-track" id="qo-min-track"><span class="min-fill" id="qo-min-fill"></span></span></span></div>
        <div class="pr-1"><div class="text-[11px] opacity-70 leading-none">Selected · <span id="qo-dirty">saved in your cart</span></div><div class="font-bold text-sm md:text-base whitespace-nowrap"><span class="qo-s1"><span id="qo-n">0</span> items<span class="qo-u"> · <span id="qo-units">0</span> units</span></span><span class="qo-sep"> · </span><span class="qo-s2"><span id="qo-total" class="text-accent">₹0.00</span></span></div></div>
        <button type="button" id="qo-clear" class="p-2 rounded-full hover:bg-white/15" aria-label="Undo changes (back to the quantities in your cart)" title="Undo changes" disabled><?= icon('rotate-ccw', 'w-4 h-4') ?></button>
        <button type="button" id="qo-add" class="btn btn-primary !rounded-full" disabled><?= icon('shopping-cart', 'w-4 h-4') ?> Add to cart</button>
      </div>

      <!-- bulk paste -->
      <details class="card qo-bulk mt-4 group" id="qo-bulk-box">
        <summary class="p-4 md:p-5 cursor-pointer font-bold flex items-center gap-2 list-none"><?= icon('list-plus', 'w-5 h-5 text-primary') ?> Have a list? Paste product IDs &amp; quantities<span class="ml-auto text-ink/40 group-open:rotate-180 transition"><?= icon('chevron-down', 'w-5 h-5') ?></span></summary>
        <div class="qo-bulk-body">
          <div class="qo-bulk-in"><p class="text-sm muted mb-3">One per line: <code class="bg-black/5 px-1 rounded">PRODUCT-ID quantity</code>, e.g. <code class="bg-black/5 px-1 rounded">FP101 5</code>. The quantities are filled into the table above so you can review them before pressing Add to cart.</p>
            <textarea id="qo-bulk" rows="3" class="textarea font-mono text-sm" placeholder="e.g. FP101 5&#10;SP201 2&#10;GC301 3"></textarea><button type="button" id="qo-bulk-btn" class="btn btn-primary mt-3"><?= icon('table-properties', 'w-4 h-4') ?> Fill the table</button></div>
          <div id="qo-bulk-result" class="qo-bulk-res text-sm space-y-0.5" aria-live="polite"></div>
        </div>
      </details>
    </div>

  </div>
</section>
<script>window.__QO_CART = <?= json_encode($cart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php $GLOBALS["pageScripts"] = ['assets/js/quick-order.js']; ?>
