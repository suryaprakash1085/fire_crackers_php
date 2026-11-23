<?php
use App\Models\Page;
$brand = setting('brand_name', 'Shop');
$cols = ['primary' => setting('color_primary', '#E8590C'), 'secondary' => setting('color_secondary', '#7048E8'), 'accent' => setting('color_accent', '#F59F00'), 'bg' => setting('color_bg', '#F8FAFC'), 'text' => setting('color_text', '#1F2937'), 'button' => setting('color_button', '#E8590C'), 'button-text' => setting('color_button_text', '#FFFFFF')];
$css = ':root{'; foreach ($cols as $k => $v) { if (!valid_hex($v)) continue; $css .= "--c-$k:$v;--c-$k-rgb:" . hex_to_rgb($v) . ';'; } $css .= '}';
$favicon = setting('favicon'); $logo = company_logo_path();   // Company Profile logo, else the older App Customization logo
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
<header class="site-header sticky top-0 z-50 border-b border-black/5 glass">
  <div class="nb-bar">
    <a href="<?= e(url('/')) ?>" class="nb-brand" aria-label="<?= e($brand) ?> home">
      <?php if ($logo): ?><img src="<?= e(upload_url($logo)) ?>" alt="" class="nb-logo"><?php else: ?><span class="nb-logo-fallback shadow-soft" style="background:linear-gradient(135deg,var(--c-primary),var(--c-accent))"><?= icon('sparkles', 'w-5 h-5') ?></span><?php endif; ?>
      <span class="nb-brand-text font-display"><?= e($brand) ?></span>
    </a>
    <nav class="nb-links" aria-label="Main">
      <?php foreach ($nav as [$h, $l, $ic]): $on = $isActive($h); ?>
        <a href="<?= e(url($h)) ?>" class="nb-link<?= $on ? ' is-active' : '' ?>" <?= $on ? 'aria-current="page"' : '' ?> <?= $h === '/cart' && $drawer ? 'data-cart-open' : '' ?>>
          <span class="nb-ic"><?= icon($ic, 'w-4 h-4') ?></span><span class="nb-label"><?= e($l) ?></span><?php if ($h === '/cart'): ?><span data-cart-badge class="hidden ml-0.5 min-w-[1.35rem] h-[1.35rem] px-1 rounded-full text-[11px] text-white flex items-center justify-center" style="background:var(--c-primary)">0</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="nb-actions">
      <form action="<?= e(url('/products')) ?>" method="get" class="nb-search" role="search"><span class="nb-search-ic"><?= icon('search', 'w-4 h-4') ?></span><input name="q" type="search" placeholder="Search crackers…" aria-label="Search products" class="nb-search-input"></form>
      <a href="<?= e(url('/products?focus=search')) ?>" class="nb-iconbtn nb-search-link" aria-label="Search"><?= icon('search', 'w-5 h-5') ?></a>
      <button type="button" data-nav-toggle aria-expanded="false" aria-controls="mobile-nav" class="nb-iconbtn nb-burger" aria-label="Menu"><?= icon('menu', 'w-6 h-6') ?></button>
    </div>
  </div>
  <nav id="mobile-nav" class="nb-mobile hidden" aria-label="Mobile">
    <div class="nb-mobile-grid">
      <?php foreach ($nav as [$h, $l, $ic]): ?>
        <a href="<?= e(url($h)) ?>" class="nb-mlink<?= $isActive($h) ? ' is-active' : '' ?>"><?= icon($ic, 'w-4 h-4') ?><span><?= e($l) ?></span></a>
      <?php endforeach; ?>
      <a href="<?= e(url('/track')) ?>" class="nb-mlink"><?= icon('truck', 'w-4 h-4') ?><span>Track Order</span></a>
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
    <div><div class="font-semibold mb-3">Useful links</div><ul class="space-y-2 text-sm"><?php foreach (array_merge([['/products', 'All Products'], ['/quick-order', 'Quick Order'], ['/voice-order', 'Voice Order']], App\Services\PriceList::available() ? [['/price-list', 'Download Price List']] : [], [['/track', 'Track Your Order'], ['/contact', 'Contact Us']]) as [$h, $l]): ?><li><a class="muted hover:text-primary transition" href="<?= e(url($h)) ?>"><?= e($l) ?></a></li><?php endforeach; ?></ul></div>
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
<?php $waNum = preg_replace('/\D+/', '', (string)(setting('whatsapp_number') ?: setting('contact_phone'))); if (strlen($waNum) === 10) $waNum = '91' . $waNum; ?>
<a href="<?= $waNum !== '' ? 'https://wa.me/' . e($waNum) : e(url('/contact')) ?>" <?= $waNum !== '' ? 'target="_blank" rel="noopener"' : '' ?> class="wa-float" data-wa-float aria-label="Chat on WhatsApp"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.52 3.48A11.86 11.86 0 0 0 12.04 0C5.5 0 .19 5.31.19 11.85c0 2.09.55 4.13 1.59 5.93L0 24l6.4-1.68a11.84 11.84 0 0 0 5.64 1.44h.01c6.54 0 11.85-5.31 11.85-11.85 0-3.17-1.23-6.15-3.38-8.43zM12.05 21.75h-.01a9.9 9.9 0 0 1-5.04-1.38l-.36-.21-3.8 1 1.01-3.7-.24-.38a9.87 9.87 0 0 1-1.51-5.23c0-5.45 4.44-9.89 9.9-9.89 2.64 0 5.12 1.03 6.99 2.9a9.82 9.82 0 0 1 2.9 7c0 5.46-4.44 9.89-9.84 9.89zm5.42-7.4c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.14-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.08 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35z"/></svg></a>
<?php foreach (($GLOBALS["pageScripts"] ?? []) as $s): ?><script src="<?= e(asset($s)) ?>" defer></script><?php endforeach; ?>
</body></html>