<?php /* Floating "Minimum order + selected items" bar for the Products / Home pages. It stays hidden until something is in the cart; app.js fills it from the shared cart (fc:cart). Same look as the Quick Order bar. */ ?>
<div class="qo-bar" id="qo-bar" aria-live="polite">
  <div class="min-line" id="qo-min" role="status" hidden><span class="qo-min-ic" aria-hidden="true"></span><span class="qo-min-body"><span id="qo-min-txt"></span><span class="min-track" id="qo-min-track"><span class="min-fill" id="qo-min-fill"></span></span></span></div>
  <div class="pr-1"><div class="text-[11px] opacity-70 leading-none">Selected · in your cart</div><div class="font-bold text-sm md:text-base whitespace-nowrap"><span class="qo-s1"><span id="qo-n">0</span> items<span class="qo-u"> · <span id="qo-units">0</span> units</span></span><span class="qo-sep"> · </span><span class="qo-s2"><span id="qo-total" class="text-accent">₹0.00</span></span></div></div>
  <button type="button" id="qo-add" class="btn btn-primary !rounded-full" data-cart-open aria-label="View cart"><span class="qo-vc-d"><?= icon('shopping-cart', 'w-4 h-4') ?> View cart</span><?= icon('chevron-right', 'qo-vc-m') ?></button>
</div>
