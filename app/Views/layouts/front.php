<?php
use App\Models\Page;
$brand = setting('brand_name', 'Shop');
$cols = ['primary' => setting('color_primary', '#E8590C'), 'secondary' => setting('color_secondary', '#7048E8'), 'accent' => setting('color_accent', '#F59F00'), 'bg' => setting('color_bg', '#F8FAFC'), 'text' => setting('color_text', '#1F2937'), 'button' => setting('color_button', '#E8590C'), 'button-text' => setting('color_button_text', '#FFFFFF')];
$css = ':root{'; foreach ($cols as $k => $v) { if (!valid_hex($v)) continue; $css .= "--c-$k:$v;--c-$k-rgb:" . hex_to_rgb($v) . ';'; } $css .= '}';
$favicon = setting('favicon'); $logo = setting('logo');
$nav = [['/', 'Home', 'home'], ['/about', 'About Us', 'info'], ['/services', 'Services', 'concierge-bell'], ['/products', 'Products', 'package'], ['/quick-order', 'Quick Order', 'zap'], ['/voice-order', 'Voice Order', 'mic'], ['/contact', 'Contact Us', 'phone'], ['/cart', 'Shopping Cart', 'shopping-cart']];
$path = '/' . trim(rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/'); if (base_path() !== '' && str_starts_with($path, base_path())) $path = '/' . trim(substr($path, strlen(base_path())), '/');
$isActive = fn($h) => $h === '/' ? $path === '/' : (str_starts_with($path, $h) || ($h === '/products' && str_starts_with($path, '/product')));
$drawer = !in_array($path, ['/cart', '/checkout', '/quick-order', '/voice-order', '/order-success'], true);
$bottom = [['/', 'Home', 'home'], ['/products', 'Products', 'package'], ['/quick-order', 'Quick', 'zap'], ['/voice-order', 'Voice', 'mic'], ['/cart', 'Cart', 'shopping-cart']];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? $brand) ?><?= isset($title) && $title !== $brand && !str_contains($title, $brand) ? ' | ' . e($brand) : '' ?></title>
<meta name="description" content="<?= e(setting('tagline')) ?>"><meta name="theme-color" content="<?= e($cols['primary']) ?>">
<meta name="csrf" content="<?= e(csrf_token()) ?>"><meta name="base" content="<?= e(base_path()) ?>">
<?php if ($favicon): ?><link rel="icon" href="<?= e(upload_url($favicon)) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Tamil:wght@400;600&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
<style><?= $css ?></style>
<?= tailwind_assets() ?>
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<script src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js" defer></script>
<script src="<?= e(asset('assets/js/fireworks.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</head>
<body class="min-h-screen flex flex-col">
<a href="#main" class="skip-link">Skip to content</a>
<?php if (setting('promo_text')): ?>
<div class="text-center text-xs sm:text-sm py-2 px-3 font-medium text-white" style="background:linear-gradient(90deg,var(--c-primary),var(--c-secondary))"><span class="inline-flex items-center gap-2"><?= icon('flame', 'w-4 h-4 shrink-0') ?><span><?= e(setting('promo_text')) ?></span></span></div>
<?php endif; ?>
<header class="sticky top-0 z-50 border-b border-black/5 glass">
  <div class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 h-16 flex items-center justify-between gap-3">
    <a href="<?= e(url('/')) ?>" class="flex items-center gap-2.5 min-w-0" aria-label="<?= e($brand) ?> home">
      <?php if ($logo): ?><img src="<?= e(upload_url($logo)) ?>" alt="" class="h-10 w-auto max-w-[56px] object-contain"><?php else: ?><span class="w-10 h-10 rounded-xl flex items-center justify-center text-white shrink-0 shadow-soft" style="background:linear-gradient(135deg,var(--c-primary),var(--c-accent))"><?= icon('sparkles', 'w-5 h-5') ?></span><?php endif; ?>
      <span class="font-display font-bold text-base sm:text-lg leading-tight truncate"><?= e($brand) ?></span>
    </a>
    <nav class="hidden lg:flex items-center gap-0.5" aria-label="Main">
      <?php foreach ($nav as [$h, $l, $ic]): ?>
        <a href="<?= e(url($h)) ?>" class="px-2.5 xl:px-3 py-2 rounded-lg text-[13px] xl:text-sm font-semibold flex items-center gap-1.5 transition hover:bg-primary/10 <?= $isActive($h) ? 'text-primary bg-primary/10' : 'text-ink/80' ?>" <?= $isActive($h) ? 'aria-current="page"' : '' ?> <?= $h === '/cart' && $drawer ? 'data-cart-open' : '' ?>>
          <span class="hidden xl:inline-flex"><?= icon($ic, 'w-4 h-4') ?></span><?= e($l) ?><?php if ($h === '/cart'): ?><span data-cart-badge class="hidden ml-0.5 min-w-[1.35rem] h-[1.35rem] px-1 rounded-full text-[11px] text-white flex items-center justify-center" style="background:var(--c-primary)">0</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="flex items-center gap-1">
      <form action="<?= e(url('/products')) ?>" method="get" class="hidden xl:flex items-center relative" role="search"><input name="q" type="search" placeholder="Search crackers…" aria-label="Search products" class="input !min-h-[38px] !py-1.5 !pl-9 !w-52 !rounded-full text-sm"><span class="absolute left-3 text-ink/40 pointer-events-none"><?= icon('search', 'w-4 h-4') ?></span></form>
      <a href="<?= e(url('/products?focus=search')) ?>" class="xl:hidden p-2.5 rounded-lg hover:bg-primary/10" aria-label="Search"><?= icon('search', 'w-5 h-5') ?></a>
      <button type="button" data-nav-toggle aria-expanded="false" aria-controls="mobile-nav" class="lg:hidden p-2.5 rounded-lg hover:bg-primary/10" aria-label="Menu"><?= icon('menu', 'w-6 h-6') ?></button>
    </div>
  </div>
  <nav id="mobile-nav" class="hidden lg:hidden border-t border-black/5 bg-white" aria-label="Mobile">
    <div class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 py-3 grid grid-cols-2 gap-1.5">
      <?php foreach ($nav as [$h, $l, $ic]): ?>
        <a href="<?= e(url($h)) ?>" class="flex items-center gap-2 px-3 py-3 rounded-xl text-sm font-semibold <?= $isActive($h) ? 'bg-primary/10 text-primary' : 'bg-black/[.03] hover:bg-black/5' ?>"><?= icon($ic, 'w-4 h-4') ?><?= e($l) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('/track')) ?>" class="flex items-center gap-2 px-3 py-3 rounded-xl text-sm font-semibold bg-black/[.03] hover:bg-black/5"><?= icon('truck', 'w-4 h-4') ?>Track Order</a>
      
    </div>
  </nav>
</header>

<main id="main" class="flex-1">
  <?php $fl = flashes(); if ($fl): ?><div class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 mt-4 space-y-2">
    <?php foreach ($fl as [$t, $m]): ?><div role="alert" class="rounded-xl px-4 py-3 text-sm font-medium border <?= $t === 'success' ? 'bg-green-50 border-green-200 text-green-800' : ($t === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-blue-50 border-blue-200 text-blue-800') ?>"><?= e($m) ?></div><?php endforeach; ?></div><?php endif; ?>
  <?= $content ?>
</main>

<footer class="mt-16 bg-white border-t border-black/5">
  <div class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 py-12 grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
    <div><div class="font-display font-bold text-lg mb-2 flex items-center gap-2"><span class="w-8 h-8 rounded-lg text-white flex items-center justify-center" style="background:linear-gradient(135deg,var(--c-primary),var(--c-accent))"><?= icon('sparkles', 'w-4 h-4') ?></span><?= e($brand) ?></div><p class="text-sm muted leading-relaxed"><?= e(setting('footer_text')) ?></p>
      <div class="flex gap-2 mt-4"><?php foreach (['facebook' => 'facebook', 'instagram' => 'instagram', 'youtube' => 'youtube'] as $k => $ic): if (setting("social_$k")): ?><a href="<?= e(setting("social_$k")) ?>" rel="noopener" target="_blank" class="p-2.5 rounded-xl bg-primary/10 text-primary hover:bg-primary hover:text-white transition" aria-label="<?= e($k) ?>"><?= icon($ic, 'w-4 h-4') ?></a><?php endif; endforeach; ?></div></div>
    <div><div class="font-semibold mb-3">Useful links</div><ul class="space-y-2 text-sm"><?php foreach ([['/products', 'All Products'], ['/quick-order', 'Quick Order'], ['/voice-order', 'Voice Order'], ['/track', 'Track Your Order'], ['/contact', 'Contact Us']] as [$h, $l]): ?><li><a class="muted hover:text-primary transition" href="<?= e(url($h)) ?>"><?= e($l) ?></a></li><?php endforeach; ?></ul></div>
    <div><div class="font-semibold mb-3">Policies</div><ul class="space-y-2 text-sm"><?php foreach (Page::footerLinks() as $pg): ?><li><a class="muted hover:text-primary transition" href="<?= e(url('/policy/' . $pg['slug'])) ?>"><?= e($pg['title']) ?></a></li><?php endforeach; ?></ul></div>
    <div><div class="font-semibold mb-3">Contact</div><ul class="space-y-2.5 text-sm muted">
      <?php if (setting('contact_phone')): ?><li class="flex gap-2"><?= icon('phone', 'w-4 h-4 mt-0.5 shrink-0 text-primary') ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a></li><?php endif; ?>
      <?php if (setting('contact_email')): ?><li class="flex gap-2"><?= icon('mail', 'w-4 h-4 mt-0.5 shrink-0 text-primary') ?><a href="mailto:<?= e(setting('contact_email')) ?>" class="break-all"><?= e(setting('contact_email')) ?></a></li><?php endif; ?>
      <?php if (setting('contact_address')): ?><li class="flex gap-2"><?= icon('map-pin', 'w-4 h-4 mt-0.5 shrink-0 text-primary') ?><span><?= e(setting('contact_address')) ?></span></li><?php endif; ?>
      <?php if (setting('business_hours')): ?><li class="flex gap-2"><?= icon('clock', 'w-4 h-4 mt-0.5 shrink-0 text-primary') ?><span><?= e(setting('business_hours')) ?></span></li><?php endif; ?></ul></div>
  </div>
  <div class="border-t border-black/5 py-4 px-4 text-center text-xs muted bg-slate-50/70"><?php if (setting('license_info')): ?><p class="mb-1"><?= e(setting('license_info')) ?></p><?php endif; ?>
    <p>Fireworks are hazardous. Sold only to adults (18+) where legally permitted. © <?= date('Y') ?> <?= e($brand) ?>.</p></div>
</footer>

<nav class="bottom-nav lg:hidden grid grid-cols-5" aria-label="Quick navigation">
  <?php foreach ($bottom as [$h, $l, $ic]): ?><a href="<?= e(url($h)) ?>" class="<?= $isActive($h) ? 'on' : '' ?>" <?= $h === '/cart' && $drawer ? 'data-cart-open' : '' ?>><span class="relative"><?= icon($ic, 'w-5 h-5') ?><?php if ($h === '/cart'): ?><span data-cart-badge class="hidden absolute -top-1.5 -right-2.5 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full text-[10px] text-white flex items-center justify-center" style="background:var(--c-primary)">0</span><?php endif; ?></span><?= e($l) ?></a><?php endforeach; ?>
</nav>
<?php if ($drawer): ?>
<div class="drawer-scrim" data-drawer-close></div>
<aside class="drawer" id="cart-drawer" aria-label="Shopping cart" aria-hidden="true">
  <div class="flex items-center justify-between px-5 h-16 border-b border-black/5 shrink-0"><h2 class="font-display font-bold text-lg flex items-center gap-2"><?= icon('shopping-bag', 'w-5 h-5 text-primary') ?> Your cart</h2><button type="button" data-drawer-close class="p-2 rounded-lg hover:bg-black/5" aria-label="Close cart"><?= icon('x', 'w-5 h-5') ?></button></div>
  <div class="flex-1 overflow-auto p-4"><div data-cart-panel data-mode="compact"><div class="py-10 text-center"><span class="spinner"></span></div></div></div>
  <div class="p-4 border-t border-black/5 grid grid-cols-2 gap-2 shrink-0"><a href="<?= e(url('/cart')) ?>" class="btn btn-outline">View full cart</a><a href="<?= e(url('/quick-order')) ?>" class="btn btn-soft"><?= icon('zap', 'w-4 h-4') ?> Quick Order</a></div>
</aside>
<?php endif; ?>
<?php if (setting('whatsapp_number')): ?><a href="https://wa.me/<?= e(setting('whatsapp_number')) ?>" target="_blank" rel="noopener" class="fixed bottom-20 lg:bottom-5 right-4 z-40 w-14 h-14 rounded-full bg-[#25D366] text-white flex items-center justify-center shadow-lift hover:scale-105 transition" aria-label="Chat on WhatsApp"><?= icon('message-circle', 'w-7 h-7') ?></a><?php endif; ?>
<?php foreach (($GLOBALS["pageScripts"] ?? []) as $s): ?><script src="<?= e(asset($s)) ?>" defer></script><?php endforeach; ?>
</body></html>
