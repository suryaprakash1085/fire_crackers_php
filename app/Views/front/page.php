<?php $isAbout = ($page['slug'] ?? '') === 'about' || !empty($wide); $t = $page['title'] ?? $title; ?>
<section class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 py-8">
  <nav class="text-sm muted mb-3" aria-label="Breadcrumb"><a href="<?= e(url('/')) ?>" class="hover:text-primary">Home</a> <span class="mx-1">/</span> <span class="text-ink font-medium"><?= e($t) ?></span></nav>
  <div class="relative overflow-hidden rounded-3xl p-7 md:p-12 text-white" style="background:linear-gradient(120deg,var(--c-primary),var(--c-secondary))">
    <div class="absolute -right-4 -bottom-8 opacity-25 hidden sm:block"><?= art($isAbout ? 'gift' : 'sky', 'w-56 h-56') ?></div>
    <span class="relative inline-flex text-xs font-bold uppercase tracking-widest bg-white/20 rounded-full px-3 py-1"><?= $isAbout ? 'Our story' : 'Information' ?></span>
    <h1 class="relative font-display font-extrabold text-3xl md:text-5xl mt-3"><?= e($t) ?></h1>
    <p class="relative mt-2 text-white/85 max-w-2xl"><?= e(setting('tagline')) ?></p>
  </div>
  <div class="grid xl:grid-cols-[1fr_360px] gap-6 mt-6 items-start">
    <div class="min-w-0">
      <?php if (!empty($page['image'])): ?><img src="<?= e(upload_url($page['image'])) ?>" alt="" class="w-full rounded-3xl max-h-[420px] object-cover mb-6 shadow-soft"><?php endif; ?>
      <div class="card p-6 md:p-10 prose-fc text-[15px] md:text-base"><?= $page['content'] ?? '<p class="muted">Content coming soon.</p>' ?></div>
    </div>
    <aside class="space-y-4 xl:sticky xl:top-24">
      <div class="card p-5"><h2 class="font-display font-bold mb-3">Contact us</h2><ul class="space-y-3 text-sm">
        <?php foreach ([['phone', setting('contact_phone')], ['mail', setting('contact_email')], ['map-pin', setting('contact_address')], ['clock', setting('business_hours')]] as [$ic, $v]): if ($v): ?><li class="flex gap-3 items-start"><span class="w-9 h-9 rounded-xl tint-primary text-primary flex items-center justify-center shrink-0"><?= icon($ic, 'w-4 h-4') ?></span><span class="break-words pt-1.5"><?= e($v) ?></span></li><?php endif; endforeach; ?></ul>
        <a href="<?= e(url('/contact')) ?>" class="btn btn-primary w-full mt-4">Send a message</a></div>
      <div class="card p-5"><h2 class="font-display font-bold mb-3">Quick links</h2><div class="grid grid-cols-2 gap-2"><?php foreach ([['/products', 'Products', 'package'], ['/quick-order', 'Quick Order', 'zap'], ['/voice-order', 'Voice Order', 'mic'], ['/track', 'Track order', 'truck']] as [$h, $l, $ic]): ?><a href="<?= e(url($h)) ?>" class="flex items-center gap-2 rounded-xl bg-black/[.03] hover:bg-primary/10 hover:text-primary px-3 py-2.5 text-sm font-semibold transition"><?= icon($ic, 'w-4 h-4') ?><?= e($l) ?></a><?php endforeach; ?></div></div>
      <?php if (setting('license_info')): ?><div class="card p-5 text-sm muted"><b class="text-ink block mb-1">Licence</b><?= e(setting('license_info')) ?></div><?php endif; ?>
    </aside>
  </div>
</section>
