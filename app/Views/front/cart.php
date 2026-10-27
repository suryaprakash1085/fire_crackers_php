<section class="max-w-4xl mx-auto px-4 py-8"><h1 class="section-title text-3xl flex items-center gap-2"><?= icon('shopping-cart', 'w-7 h-7 text-primary') ?> Shopping Cart</h1>
  <div class="card p-4 md:p-6 mt-6"><div data-cart-panel data-mode="full"><div class="py-10 text-center"><span class="spinner"></span></div></div></div>
  <noscript><p class="mt-4 text-red-700">Please enable JavaScript to manage your cart.</p></noscript>
  <p class="text-sm muted mt-4 text-center">Prefer faster ordering? Try <a class="text-primary font-semibold" href="<?= e(url('/quick-order')) ?>">Quick Order</a> or <a class="text-primary font-semibold" href="<?= e(url('/voice-order')) ?>">Voice Order</a> — they use this same cart.</p></section>
