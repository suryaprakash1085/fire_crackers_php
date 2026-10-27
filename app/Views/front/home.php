<?php $show = fn($k) => setting($k, '1') === '1'; $brand = setting('brand_name'); ?>
<!-- ============ HERO ============ -->
<section class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 pt-5">
  <?php if ($banners): ?>
  <div class="hero" data-carousel role="region" aria-roledescription="carousel" aria-label="Offers">
    <div class="hero-track">
      <?php foreach ($banners as $b): ?>
      <div class="hero-slide" role="group" aria-roledescription="slide">
        <?php if ($b['image']): ?><img src="<?= e(upload_url($b['image'])) ?>" alt="" loading="lazy"><?php else: ?><div class="hero-bg"></div>
          <div class="hidden md:flex absolute right-10 lg:right-24 top-1/2 -translate-y-1/2 z-[1] items-center justify-center w-64 h-64 lg:w-80 lg:h-80">
            <span class="absolute inset-0 rounded-full opacity-90" style="background:radial-gradient(circle at 35% 30%,rgb(var(--c-accent-rgb)/.55),rgb(var(--c-primary-rgb)/.35) 55%,rgb(var(--c-secondary-rgb)/.18))"></span>
            <span class="relative float text-white drop-shadow-lg"><?= icon('flame', 'w-28 h-28 lg:w-36 lg:h-36') ?></span>
            <span class="absolute -top-2 right-6 float text-accent" style="animation-delay:.6s"><?= icon('sparkles', 'w-10 h-10') ?></span>
            <span class="absolute bottom-4 -left-4 float text-secondary" style="animation-delay:1.2s"><?= icon('star', 'w-8 h-8') ?></span>
          </div>
          <i class="sparkle" style="top:18%;left:58%"></i><i class="sparkle" style="top:62%;left:72%"></i><i class="sparkle" style="top:30%;left:88%"></i><i class="sparkle" style="top:75%;left:50%"></i><?php endif; ?>
        <div class="hero-copy"><div class="hero-panel">
          <?php if ($b['promo_text']): ?><span class="eyebrow mb-3"><?= icon('flame', 'w-3.5 h-3.5') ?><?= e($b['promo_text']) ?></span><?php endif; ?>
          <h2 class="text-3xl md:text-5xl font-extrabold leading-[1.1]"><?= e($b['title']) ?></h2>
          <?php if ($b['subtitle']): ?><p class="mt-3 muted text-sm md:text-lg"><?= e($b['subtitle']) ?></p><?php endif; ?>
          <div class="mt-5 flex flex-wrap gap-2.5">
            <?php if ($b['cta_label'] && $b['cta_url']): ?><a href="<?= e(preg_match('#^https?://#', $b['cta_url']) ? $b['cta_url'] : url($b['cta_url'])) ?>" class="btn btn-primary btn-lg"><?= e($b['cta_label']) ?> <?= icon('arrow-right', 'w-4 h-4') ?></a><?php endif; ?>
            <a href="<?= e(url('/quick-order')) ?>" class="btn btn-outline btn-lg"><?= icon('zap', 'w-4 h-4') ?> Quick Order</a>
          </div>
        </div></div>
      </div>
      <?php endforeach; ?>
    </div>
    <canvas class="fw-hero" data-fw-hero aria-hidden="true"></canvas>
    <button type="button" class="hero-arrow left-3" data-prev aria-label="Previous slide"><?= icon('chevron-left') ?></button>
    <button type="button" class="hero-arrow right-3" data-next aria-label="Next slide"><?= icon('chevron-right') ?></button>
    <div class="hero-dots" role="tablist"></div>
  </div>
  <?php else: ?>
  <div class="hero"><div class="hero-slide"><div class="hero-bg"></div><canvas class="fw-hero" data-fw-hero aria-hidden="true"></canvas>
    <div class="hero-copy"><div class="hero-panel"><span class="eyebrow mb-3"><?= icon('flame', 'w-3.5 h-3.5') ?>Festival ready</span><h1 class="text-3xl md:text-5xl font-extrabold leading-[1.1]"><?= e($brand) ?></h1><p class="mt-3 muted md:text-lg"><?= e(setting('tagline')) ?></p>
      <div class="mt-5 flex flex-wrap gap-2.5"><a href="<?= e(url('/products')) ?>" class="btn btn-primary btn-lg">Shop now <?= icon('arrow-right', 'w-4 h-4') ?></a><a href="<?= e(url('/voice-order')) ?>" class="btn btn-outline btn-lg"><?= icon('mic', 'w-4 h-4') ?> Voice Order</a></div></div></div></div></div>
  <?php endif; ?>
</section>

<!-- ============ TRUST STRIP ============ -->
<section class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 mt-5">
  <div class="card grid grid-cols-2 lg:grid-cols-4 divide-x divide-y lg:divide-y-0 divide-black/5">
    <?php foreach ([['shield-check', 'Safe & certified', 'Quality-tested products'], ['package-check', 'Secure packing', 'Careful, leak-proof boxes'], ['truck', 'Reliable delivery', 'Tracked until it reaches you'], ['headset', 'Friendly support', 'Call or WhatsApp anytime']] as [$ic, $t, $d]): ?>
    <div class="p-4 md:p-5 flex items-center gap-3"><span class="w-11 h-11 rounded-xl tint-primary text-primary flex items-center justify-center shrink-0"><?= icon($ic, 'w-5 h-5') ?></span><span class="min-w-0"><b class="block text-sm"><?= e($t) ?></b><span class="text-xs muted"><?= e($d) ?></span></span></div>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($show('show_offers')): ?>
<section class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 mt-6"><div class="grid gap-3 sm:grid-cols-3">
  <?php foreach ([['gift', 'tint-primary'], ['baby', 'tint-secondary'], ['package', 'tint-accent']] as $i => [$ic, $tn]): $n = $i + 1; if (!setting("offer_{$n}_title")) continue; ?>
  <a href="<?= e(url('/products')) ?>" class="card p-4 md:p-5 flex items-center gap-4 hover:shadow-lift hover:-translate-y-0.5 transition group"><span class="w-12 h-12 rounded-2xl flex items-center justify-center <?= $tn ?> text-primary group-hover:scale-110 transition"><?= icon($ic, 'w-6 h-6') ?></span><span class="min-w-0"><b class="block"><?= e(setting("offer_{$n}_title")) ?></b><span class="text-sm muted"><?= e(setting("offer_{$n}_text")) ?></span></span><span class="ml-auto text-primary opacity-0 group-hover:opacity-100 transition"><?= icon('arrow-right', 'w-4 h-4') ?></span></a>
  <?php endforeach; ?></div></section>
<?php endif; ?>

<div class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 mt-10" aria-hidden="true"><div class="fuse"><i></i></div></div>
<!-- ============ ORDER YOUR WAY ============ -->
<section class="section w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 !pb-0">
  <div class="text-center max-w-2xl mx-auto"><span class="eyebrow"><?= icon('sparkles', 'w-3.5 h-3.5') ?>Three easy ways</span><h2 class="section-title center mt-3">Order the way you like</h2></div>
  <div class="mt-8 grid md:grid-cols-3 gap-4">
    <?php foreach ([['/products', 'shopping-bag', 'Browse & add', 'Explore every category, pick quantities and add to cart.', 'var(--c-primary)'], ['/quick-order', 'zap', 'Quick Order', 'See all products in one table and enter quantities in seconds.', 'var(--c-secondary)'], ['/voice-order', 'mic', 'Voice Order', 'Speak your whole order in English or தமிழ் — we fill the cart.', 'var(--c-accent)']] as [$h, $ic, $t, $d, $col]): ?>
    <a href="<?= e(url($h)) ?>" class="card p-6 hover:shadow-lift hover:-translate-y-1 transition group relative overflow-hidden">
      <span class="absolute -right-6 -top-6 w-28 h-28 rounded-full opacity-[.12] group-hover:scale-125 transition duration-500" style="background:<?= $col ?>"></span>
      <span class="w-14 h-14 rounded-2xl text-white flex items-center justify-center shadow-soft" style="background:<?= $col ?>"><?= icon($ic, 'w-7 h-7') ?></span>
      <b class="text-lg block mt-4 font-display"><?= e($t) ?></b><span class="text-sm muted block mt-1"><?= e($d) ?></span>
      <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary">Open <?= icon('arrow-right', 'w-4 h-4 group-hover:translate-x-1 transition') ?></span></a>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($show('show_categories') && $categories): ?>
<section class="section w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16">
  <div class="flex items-end justify-between gap-3"><h2 class="section-title">Shop by category</h2><a href="<?= e(url('/products')) ?>" class="text-sm font-semibold text-primary hidden sm:inline">All products →</a></div>
  <div class="mt-7 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 2xl:grid-cols-8 gap-3 md:gap-4">
    <?php foreach ($categories as $c): ?>
    <a href="<?= e(url('/products?category=' . (int)$c['id'])) ?>" class="card cat-tile group">
      <div class="pimg"><?php if ($c['image']): ?><img src="<?= e(upload_url($c['image'])) ?>" alt="" loading="lazy"><?php else: ?><span class="ph-art"><?= art($c['name'], 'w-1/2 h-1/2') ?></span><?php endif; ?></div>
      <div class="p-3.5 flex items-center justify-between gap-2"><span class="min-w-0"><b class="block text-sm md:text-[15px] truncate"><?= e($c['name']) ?></b><span class="text-xs muted"><?= (int)$c['product_count'] ?> products</span></span><span class="w-8 h-8 rounded-full tint-primary text-primary flex items-center justify-center shrink-0 group-hover:bg-primary group-hover:text-white transition"><?= icon('arrow-right', 'w-4 h-4') ?></span></div></a>
    <?php endforeach; ?></div></section>
<?php endif; ?>

<?php foreach ([['show_featured', 'featured', 'Featured products', 'star'], ['show_popular', 'popular', 'Popular products', 'flame'], ['show_new', 'newest', 'New arrivals', 'sparkles']] as [$flag, $var, $label, $lic]): $list = $$var; if ($show($flag) && $list): ?>
<section class="section w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 !pt-0">
  <div class="flex items-end justify-between gap-3"><h2 class="section-title"><?= e($label) ?></h2><a href="<?= e(url('/products')) ?>" class="text-sm font-semibold text-primary hidden sm:inline">View all →</a></div>
  <div class="mt-7 grid grid-cols-2 lg:grid-cols-4 2xl:grid-cols-6 gap-3 md:gap-5"><?php foreach ($list as $p) include __DIR__ . '/../partials/product-card.php'; ?></div>
</section>
<?php endif; endforeach; ?>

<div class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 mt-10" aria-hidden="true"><div class="fuse"><i></i></div></div>
<!-- ============ CELEBRATE CTA (fire blast) ============ -->
<section class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16">
  <div class="relative overflow-hidden rounded-3xl px-6 py-12 md:py-16 text-center text-white" style="background:linear-gradient(135deg,var(--c-primary),var(--c-secondary))">
    <canvas class="fw-hero" data-fw-hero aria-hidden="true" style="mix-blend-mode:screen"></canvas>
    <div class="relative z-[3] max-w-2xl mx-auto">
      <span class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest bg-white/20 rounded-full px-3 py-1"><?= icon('flame', 'w-3.5 h-3.5') ?>Make it a blast</span>
      <h2 class="font-display font-extrabold text-3xl md:text-5xl mt-4 leading-tight">Ready to light up your celebration?</h2>
      <p class="mt-3 text-white/85 md:text-lg">Order in minutes with Quick Order or just tell us what you need with Voice Order.</p>
      <div class="mt-6 flex flex-wrap justify-center gap-3"><a href="<?= e(url('/products')) ?>" class="btn btn-white btn-lg">Start shopping <?= icon('arrow-right', 'w-4 h-4') ?></a><button type="button" data-blast class="btn btn-lg bg-white/15 text-white border-white/40 hover:bg-white/25"><?= icon('sparkles', 'w-4 h-4') ?> Fire a blast!</button></div>
    </div>
  </div>
</section>

<?php if ($show('show_about') && $aboutText): ?>
<section class="section"><div class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 grid md:grid-cols-2 gap-8 lg:gap-14 items-center">
  <div class="rounded-3xl overflow-hidden aspect-[16/11] tint-accent flex items-center justify-center shadow-soft order-last md:order-first"><?php if ($aboutImage): ?><img src="<?= e(upload_url($aboutImage)) ?>" alt="About us" class="w-full h-full object-cover" loading="lazy"><?php else: ?><span class="text-primary"><?= icon('sparkles', 'w-16 h-16') ?></span><?php endif; ?></div>
  <div><span class="eyebrow">Our story</span><h2 class="section-title mt-3">About <?= e($brand) ?></h2><p class="mt-5 muted leading-relaxed"><?= e($aboutText) ?>…</p><a href="<?= e(url('/about')) ?>" class="btn btn-primary mt-6">Read our story <?= icon('arrow-right', 'w-4 h-4') ?></a></div>
</div></section>
<?php endif; ?>

<?php if ($show('show_services') && $services): ?>
<section class="section w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 !pt-0"><div class="flex items-end justify-between"><h2 class="section-title">Our services</h2><a href="<?= e(url('/services')) ?>" class="text-sm font-semibold text-primary">All services →</a></div>
  <div class="mt-7 grid sm:grid-cols-2 lg:grid-cols-4 gap-4"><?php foreach ($services as $s) include __DIR__ . '/../partials/service-card.php'; ?></div></section>
<?php endif; ?>

<?php if ($show('show_safety') && setting('safety_homepage')): ?>
<section class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16"><div class="card p-5 md:p-7 flex flex-col md:flex-row gap-4 items-start md:items-center" style="background:rgb(var(--c-accent-rgb)/.09);border-color:rgb(var(--c-accent-rgb)/.35)">
  <span class="w-12 h-12 rounded-xl bg-white flex items-center justify-center shrink-0 text-primary shadow-soft"><?= icon('shield-check', 'w-6 h-6') ?></span>
  <div class="flex-1"><h2 class="font-bold text-lg font-display">Celebrate safely</h2><p class="text-sm muted mt-1"><?= e(setting('safety_homepage')) ?></p></div>
  <a class="btn btn-outline" href="<?= e(url('/policy/safety')) ?>">Safety guidelines</a></div></section>
<?php endif; ?>

<?php if ($show('show_contact')): ?>
<section class="section w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16"><h2 class="section-title">Get in touch</h2>
  <div class="mt-7 grid sm:grid-cols-3 gap-4">
    <?php foreach ([['phone', 'Call us', setting('contact_phone')], ['mail', 'Email', setting('contact_email')], ['map-pin', 'Visit', setting('contact_address')]] as [$ic, $l, $v]): if (!$v) continue; ?>
    <div class="card p-5 flex gap-3"><span class="w-11 h-11 rounded-xl tint-primary text-primary flex items-center justify-center shrink-0"><?= icon($ic) ?></span><div class="min-w-0"><b class="block"><?= e($l) ?></b><span class="text-sm muted break-words"><?= e($v) ?></span></div></div>
    <?php endforeach; ?></div>
  <a href="<?= e(url('/contact')) ?>" class="btn btn-primary mt-6">Send us a message</a></section>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-blast]').forEach(function (b) { b.addEventListener('click', function () { window.FW && FW.celebrate(6); }); });
  try { if (!sessionStorage.getItem('fc_intro')) { sessionStorage.setItem('fc_intro', '1'); setTimeout(function () { window.FW && FW.celebrate(3); }, 900); } } catch (e) {}
});
</script>
