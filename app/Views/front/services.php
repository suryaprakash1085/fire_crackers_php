<section class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 py-10"><h1 class="section-title text-3xl">Our Services</h1><p class="muted mt-4 max-w-2xl">Everything we offer to make your celebration safe and simple.</p>
  <?php if (!$services): ?><div class="card p-10 mt-8 text-center muted">Services will be listed here soon.</div><?php else: ?>
  <div class="mt-8 grid sm:grid-cols-2 lg:grid-cols-3 gap-5"><?php foreach ($services as $s) include __DIR__ . '/../partials/service-card.php'; ?></div><?php endif; ?></section>
