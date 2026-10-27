<?php $er = fn($k) => $errors[$k] ?? null; $addr = setting('contact_address'); $infos = [['phone', 'Call us', setting('contact_phone'), setting('contact_phone') ? 'tel:' . preg_replace('/[^\d+]/', '', setting('contact_phone')) : ''], ['mail', 'Email us', setting('contact_email'), setting('contact_email') ? 'mailto:' . setting('contact_email') : ''], ['map-pin', 'Visit us', $addr, $addr ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($addr) : ''], ['clock', 'Business hours', setting('business_hours'), '']]; ?>
<section class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 py-8">
  <nav class="text-sm muted mb-3" aria-label="Breadcrumb"><a href="<?= e(url('/')) ?>" class="hover:text-primary">Home</a> <span class="mx-1">/</span> <span class="text-ink font-medium">Contact Us</span></nav>
  <div class="relative overflow-hidden rounded-3xl p-7 md:p-12 text-white" style="background:linear-gradient(120deg,var(--c-primary),var(--c-secondary))">
    <div class="absolute -right-4 -bottom-8 opacity-25 hidden sm:block"><?= art('rocket', 'w-56 h-56') ?></div>
    <span class="relative inline-flex text-xs font-bold uppercase tracking-widest bg-white/20 rounded-full px-3 py-1">We are here to help</span>
    <h1 class="relative font-display font-extrabold text-3xl md:text-5xl mt-3">Contact Us</h1>
    <p class="relative mt-2 text-white/85 max-w-2xl">Questions about an order, bulk requirements or safety? Send us a message or call — we usually reply the same day.</p>
  </div>

  <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mt-6">
    <?php foreach ($infos as [$ic, $l, $v, $href]): if (!$v) continue; ?>
    <<?= $href ? 'a href="' . e($href) . '"' . (str_starts_with($href, 'http') ? ' target="_blank" rel="noopener"' : '') : 'div' ?> class="card p-5 flex gap-3 items-start <?= $href ? 'hover:shadow-lift hover:-translate-y-0.5 transition' : '' ?>">
      <span class="w-12 h-12 rounded-2xl text-white flex items-center justify-center shrink-0 shadow-soft" style="background:linear-gradient(135deg,var(--c-primary),var(--c-accent))"><?= icon($ic, 'w-5 h-5') ?></span>
      <span class="min-w-0"><b class="block font-display"><?= e($l) ?></b><span class="text-sm muted break-words"><?= e($v) ?></span></span>
    </<?= $href ? 'a' : 'div' ?>>
    <?php endforeach; ?>
  </div>

  <div class="grid xl:grid-cols-[1fr_380px] gap-6 mt-6 items-start">
    <form method="post" action="<?= e(url('/contact')) ?>" class="card p-5 md:p-8 grid gap-4" data-once novalidate><?= csrf_field() ?>
      <h2 class="font-display font-bold text-xl flex items-center gap-2"><?= icon('send', 'w-5 h-5 text-primary') ?> Send us a message</h2>
      <div class="hidden" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="label" for="name">Your name *</label><input class="input <?= $er('name') ? 'has-error' : '' ?>" id="name" name="name" value="<?= e(old('name')) ?>" required maxlength="120"><?php if ($er('name')): ?><p class="err"><?= e($er('name')) ?></p><?php endif; ?></div>
        <div><label class="label" for="mobile">Mobile</label><input class="input <?= $er('mobile') ? 'has-error' : '' ?>" id="mobile" name="mobile" type="tel" inputmode="tel" value="<?= e(old('mobile')) ?>" maxlength="13"><?php if ($er('mobile')): ?><p class="err"><?= e($er('mobile')) ?></p><?php endif; ?></div>
        <div><label class="label" for="email">Email</label><input class="input <?= $er('email') ? 'has-error' : '' ?>" id="email" name="email" type="email" value="<?= e(old('email')) ?>"><?php if ($er('email')): ?><p class="err"><?= e($er('email')) ?></p><?php endif; ?></div>
        <div><label class="label" for="subject">Subject</label><input class="input" id="subject" name="subject" value="<?= e(old('subject')) ?>" maxlength="190"></div>
      </div>
      <div><label class="label" for="message">Message *</label><textarea class="textarea <?= $er('message') ? 'has-error' : '' ?>" id="message" name="message" required minlength="10" maxlength="4000" rows="6"><?= e(old('message')) ?></textarea><?php if ($er('message')): ?><p class="err"><?= e($er('message')) ?></p><?php endif; ?></div>
      <button class="btn btn-primary btn-lg sm:w-fit"><?= icon('send', 'w-4 h-4') ?> Send message</button>
    </form>
    <aside class="space-y-4 xl:sticky xl:top-24">
      <?php if (setting('whatsapp_number')): ?><a class="card p-5 flex items-center gap-3 hover:shadow-lift transition" href="https://wa.me/<?= e(setting('whatsapp_number')) ?>" target="_blank" rel="noopener"><span class="w-12 h-12 rounded-2xl bg-[#25D366] text-white flex items-center justify-center"><?= icon('message-circle', 'w-6 h-6') ?></span><span><b class="block font-display">Chat on WhatsApp</b><span class="text-sm muted">Quick replies during business hours</span></span></a><?php endif; ?>
      <?php if ($addr): ?><a class="card p-5 flex items-center gap-3 hover:shadow-lift transition" href="https://www.google.com/maps/search/?api=1&query=<?= e(rawurlencode($addr)) ?>" target="_blank" rel="noopener"><span class="w-12 h-12 rounded-2xl tint-secondary text-secondary flex items-center justify-center"><?= icon('map', 'w-6 h-6') ?></span><span><b class="block font-display">Open in Google Maps</b><span class="text-sm muted">Get directions to our shop</span></span></a><?php endif; ?>
      <div class="card p-5"><h2 class="font-display font-bold mb-2">Quick answers</h2>
        <?php foreach ([['How do I track my order?', 'Use “Track Order” with your order reference and mobile number.'], ['Is there a minimum order?', (float)setting('min_order_amount', 0) > 0 ? 'Yes — the minimum order is ' . money((float)setting('min_order_amount')) . '.' : 'No minimum — order any quantity.'], ['Can I order by voice?', 'Yes! Use Voice Order and say your whole list in English or Tamil.']] as [$q, $a]): ?>
        <details class="py-2.5 border-t border-black/5 first:border-0 group"><summary class="cursor-pointer font-semibold text-sm list-none flex justify-between gap-2"><?= e($q) ?><span class="text-primary group-open:rotate-45 transition">+</span></summary><p class="text-sm muted mt-1.5"><?= e($a) ?></p></details>
        <?php endforeach; ?></div>
    </aside>
  </div>
</section>
