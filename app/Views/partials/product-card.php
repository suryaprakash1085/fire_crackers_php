<?php /** @var array $p */ [$sl, $sc] = App\Models\Product::stockLabel($p); $out = (int)$p['stock'] <= 0; $disc = (float)$p['discount_percent']; $href = url('/product-details.php?id=' . (int)$p['id']); $save = max(0, (float)$p['original_price'] - (float)$p['selling_price']); ?>
<article class="card pcard group" data-card>
  <a href="<?= e($href) ?>" class="pimg block" aria-label="<?= e($p['name']) ?>">
    <?php if (!empty($p['image'])): ?><img src="<?= e(upload_url($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy" width="400" height="400"><?php else: ?><span class="ph-art"><?= art($p['name'] . ' ' . ($p['category_name'] ?? ''), 'w-1/2 h-1/2') ?></span><?php endif; ?>
    <?php if ($disc > 0): ?><span class="ribbon"><?= e(rtrim(rtrim(number_format($disc, 1), '0'), '.')) ?>% OFF</span><?php endif; ?>
    <?php if ($out): ?><span class="absolute inset-0 bg-white/65 flex items-center justify-center z-[2]"><span class="badge bg-gray-900 text-white !text-xs !px-3 !py-1">Sold out</span></span><?php endif; ?>
  </a>
  <?php if (!$out): ?><button type="button" data-add="<?= (int)$p['id'] ?>" data-quick class="fab-add" aria-label="Quick add <?= e($p['name']) ?>"><?= icon('plus', 'w-5 h-5') ?></button><?php endif; ?>
  <div class="p-3.5 md:p-4 flex flex-col flex-1">
    <div class="flex items-center justify-between gap-2 text-[11px] muted"><span class="truncate"><?= e($p['category_name']) ?></span><span class="font-mono shrink-0 bg-black/[.04] rounded px-1.5 py-0.5"><?= e($p['sku']) ?></span></div>
    <h3 class="font-semibold text-[15px] leading-snug mt-1.5 line-clamp-2 min-h-[2.6rem]"><a href="<?= e($href) ?>" class="hover:text-primary transition"><?= e($p['name']) ?></a><?php if (!empty($p['name_ta'])): ?><span class="block text-xs muted font-normal mt-0.5 truncate"><?= e($p['name_ta']) ?></span><?php endif; ?></h3>
    <div class="mt-2.5 flex items-baseline gap-2 flex-wrap"><span class="text-xl font-bold font-display" style="color:var(--c-primary)"><?= money($p['selling_price']) ?></span><?php if ($disc > 0): ?><s class="text-xs muted"><?= money($p['original_price']) ?></s><?php endif; ?><span class="badge ml-auto <?= $sc ?>"><?= e($sl) ?></span></div>
    <?php if ($save > 0): ?><div class="mt-1.5"><span class="save-pill"><?= icon('badge-percent', 'w-3 h-3') ?>You save <?= money($save) ?></span></div><?php endif; ?>
    <div class="mt-3.5 flex flex-col min-[430px]:flex-row min-[430px]:items-center gap-2 mt-auto pt-1">
      <?php if (!$out): ?><div class="qty qty-sm self-start min-[430px]:self-auto"><button type="button" data-step="-1" aria-label="Decrease quantity">−</button><input type="number" value="1" min="1" max="<?= (int)$p['stock'] ?>" aria-label="Quantity"><button type="button" data-step="1" aria-label="Increase quantity">+</button></div><?php endif; ?>
      <button type="button" data-add="<?= (int)$p['id'] ?>" class="btn btn-primary w-full min-[430px]:flex-1 !px-3 !min-h-[38px] btn-sm" <?= $out ? 'disabled' : '' ?>><?= icon('shopping-cart', 'w-4 h-4') ?><span><?= $out ? 'Sold out' : 'Add to cart' ?></span></button>
    </div>
  </div>
</article>
