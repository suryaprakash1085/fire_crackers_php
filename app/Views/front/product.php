<?php [$sl, $sc] = App\Models\Product::stockLabel($p); $out = (int)$p['stock'] <= 0; $disc = (float)$p['discount_percent']; $imgs = $images ?: []; ?>
<section class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 py-6">
  <nav class="text-sm muted mb-4" aria-label="Breadcrumb"><a href="<?= e(url('/')) ?>" class="hover:text-primary">Home</a> / <a href="<?= e(url('/products')) ?>" class="hover:text-primary">Products</a> / <a href="<?= e(url('/products?category=' . (int)$p['category_id'])) ?>" class="hover:text-primary"><?= e($p['category_name']) ?></a></nav>
  <div class="grid md:grid-cols-2 gap-6 lg:gap-10">
    <div>
      <div class="card overflow-hidden aspect-square pimg !rounded-3xl" id="main-img"><?php if ($imgs): ?><img src="<?= e(upload_url($imgs[0]['path'])) ?>" alt="<?= e($p['name']) ?>" id="main-img-el"><?php else: ?><span class="ph"><?= icon('sparkles', 'w-20 h-20') ?></span><?php endif; ?></div>
      <?php if (count($imgs) > 1): ?><div class="flex gap-2 mt-3 overflow-x-auto"><?php foreach ($imgs as $i => $im): ?><button type="button" class="w-20 h-20 rounded-xl overflow-hidden border-2 shrink-0 <?= $i === 0 ? 'border-primary' : 'border-transparent' ?>" data-thumb="<?= e(upload_url($im['path'])) ?>" aria-label="Image <?= $i + 1 ?>"><img src="<?= e(upload_url($im['path'])) ?>" alt="" class="w-full h-full object-cover" loading="lazy"></button><?php endforeach; ?></div>
      <script>document.querySelectorAll('[data-thumb]').forEach(b=>b.addEventListener('click',()=>{document.getElementById('main-img-el').src=b.dataset.thumb;document.querySelectorAll('[data-thumb]').forEach(x=>x.classList.replace('border-primary','border-transparent'));b.classList.replace('border-transparent','border-primary')}))</script><?php endif; ?>
      <?php if (!empty($p['video_path'])): ?><div class="mt-5"><h2 class="font-bold mb-2 flex items-center gap-2"><?= icon('play', 'w-5 h-5') ?> Product video</h2><video src="<?= e(upload_url($p['video_path'])) ?>" controls preload="metadata" playsinline<?= $imgs ? ' poster="' . e(upload_url($imgs[0]['path'])) . '"' : '' ?> class="w-full rounded-2xl bg-black aspect-video"></video></div><?php endif; ?>
    </div>
    <div data-card>
      <span class="badge tint-secondary" style="color:var(--c-secondary)"><?= e($p['category_name']) ?></span>
      <h1 class="text-2xl md:text-4xl font-bold mt-2 leading-tight"><?= e($p['name']) ?></h1><?php if (!empty($p['name_ta'])): ?><p class="muted mt-1"><?= e($p['name_ta']) ?></p><?php endif; ?>
      <p class="text-sm muted mt-1">Product ID / SKU: <span class="font-mono font-semibold"><?= e($p['sku']) ?></span> · ID <?= (int)$p['id'] ?></p>
      <div class="mt-4 flex items-baseline gap-3 flex-wrap"><span class="text-4xl font-bold font-display" style="color:var(--c-primary)"><?= money($p['selling_price']) ?></span><?php if ($disc > 0): ?><s class="muted"><?= money($p['original_price']) ?></s><span class="badge text-white" style="background:var(--c-primary)"><?= e(rtrim(rtrim(number_format($disc, 1), '0'), '.')) ?>% OFF</span><?php endif; ?></div>
      <p class="mt-3"><span class="badge <?= $sc ?> !text-xs"><?= e($sl) ?></span></p>
      <div class="mt-5 flex items-center gap-3 flex-wrap">
        <?php if (!$out): ?><div class="qty"><button type="button" data-step="-1" aria-label="Decrease quantity">−</button><input type="number" value="1" min="1" max="<?= (int)$p['stock'] ?>" aria-label="Quantity"><button type="button" data-step="1" aria-label="Increase quantity">+</button></div><?php endif; ?>
        <button type="button" data-add="<?= (int)$p['id'] ?>" class="btn btn-primary btn-lg flex-1 sm:flex-none sm:min-w-[220px]" <?= $out ? 'disabled' : '' ?>><?= icon('shopping-cart', 'w-4 h-4') ?><?= $out ? 'Out of stock' : 'Add to Cart' ?></button>
        <a href="<?= e(url('/cart')) ?>" class="btn btn-outline btn-lg">Go to cart</a>
      </div>
      <?php if ($p['description']): ?><div class="mt-6"><h2 class="font-bold mb-1">Description</h2><p class="muted leading-relaxed whitespace-pre-line"><?= e($p['description']) ?></p></div><?php endif; ?>
      <div class="mt-6 rounded-2xl p-4 md:p-5 border" style="background:rgb(var(--c-accent-rgb)/.09);border-color:rgb(var(--c-accent-rgb)/.35)"><h2 class="font-bold flex items-center gap-2"><?= icon('shield-alert', 'w-5 h-5') ?> Safety information</h2>
        <p class="text-sm mt-1 whitespace-pre-line"><?= e($p['safety_notes'] ?: 'Use outdoors only, under adult supervision. Keep a safe distance and keep water nearby.') ?></p><a class="text-sm font-semibold text-primary mt-2 inline-block" href="<?= e(url('/policy/safety')) ?>">Read full safety guidelines →</a></div>
    </div>
  </div>
  <?php if ($related): ?><div class="mt-14"><h2 class="section-title">Related products</h2><div class="mt-6 grid grid-cols-2 lg:grid-cols-4 2xl:grid-cols-6 gap-3 md:gap-5"><?php foreach ($related as $p) include __DIR__ . '/../partials/product-card.php'; ?></div></div><?php endif; ?>
</section>
