<?php
$role = $admin['role'] ?? 'staff'; $lvl = ['staff' => 1, 'admin' => 2, 'superadmin' => 3][$role];
$path = '/' . trim(rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/'); if (base_path() !== '' && str_starts_with($path, base_path())) $path = substr($path, strlen(base_path()));
$menu = [['/admin', 'Dashboard', 'layout-dashboard', 1], ['/admin/orders', 'Orders', 'receipt', 1], ['/admin/reports', 'Reports', 'chart-column', 1], ['/admin/products', 'Products', 'package', 2], ['/admin/categories', 'Categories', 'tags', 2], ['/admin/banners', 'Banners', 'image', 2],
  ['/admin/services', 'Services', 'concierge-bell', 2], ['/admin/content', 'Website Content', 'file-text', 2], ['/admin/enquiries', 'Enquiries', 'inbox', 1],
  ['/admin/company', 'Company Profile', 'building-2', 2], ['/admin/customize', 'App Customization', 'palette', 2], ['/admin/smtp', 'Email Settings', 'mail', 2], ['/admin/users', 'Admin Users', 'users', 3]];
$active = fn($h) => $h === '/admin' ? in_array($path, ['/admin', '/admin/dashboard']) : str_starts_with($path, $h);
?><!doctype html><html lang="en" class="dark"><head><script>try{if(localStorage.getItem("fc_admin_theme")==="light")document.documentElement.classList.remove("dark")}catch(e){}</script><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><title><?= e($title ?? 'Admin') ?> · Admin</title>
<meta name="csrf" content="<?= e(csrf_token()) ?>"><meta name="base" content="<?= e(base_path()) ?>"><meta name="robots" content="noindex,nofollow">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
<?= tailwind_assets() ?>
<style>:root{--c-primary:#E8590C;--c-primary-rgb:232 89 12;--c-secondary:#7048E8;--c-secondary-rgb:112 72 232;--c-accent:#F59F00;--c-accent-rgb:245 159 0;--c-text:#1F2937;--c-text-rgb:31 41 55;--c-bg:#F6F7FB;--c-button:#E8590C;--c-button-text:#fff}</style>
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(asset('assets/css/admin-dark.css')) ?>"><style>body{background:#F5F7FA}.table-x th{font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#667085;text-align:left;padding:.7rem .85rem;background:#F9FAFB;white-space:nowrap;border-bottom:1px solid #eef0f4}.table-x td{padding:.7rem .85rem;border-top:1px solid #f0f2f6;vertical-align:middle}.table-x tbody tr:hover{background:#FCFCFD}.nav-link{position:relative}.nav-link.on::before{content:'';position:absolute;left:-12px;top:20%;bottom:20%;width:4px;border-radius:0 4px 4px 0;background:var(--c-primary)}</style>
<script src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js"></script><script src="<?= e(asset('assets/js/admin.js')) ?>" defer></script></head>
<body class="min-h-screen">
<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-64 bg-white border-r border-black/5 flex flex-col -translate-x-full lg:translate-x-0 transition-transform">
  <div class="h-16 flex items-center gap-2 px-5 border-b border-black/5"><span class="w-9 h-9 rounded-xl text-white flex items-center justify-center" style="background:linear-gradient(135deg,var(--c-primary),var(--c-accent))"><?= icon('sparkles', 'w-5 h-5') ?></span><div class="leading-tight min-w-0"><b class="font-display block truncate"><?= e(setting('brand_name')) ?></b><span class="text-[11px] muted">Admin Panel</span></div></div>
  <nav class="flex-1 overflow-y-auto p-3 space-y-0.5" aria-label="Admin"><?php foreach ($menu as [$h, $l, $ic, $min]): if ($lvl < $min) continue; ?>
    <a href="<?= e(url($h)) ?>" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= $active($h) ? 'on bg-primary/10 text-primary font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>"><?= icon($ic, 'w-[18px] h-[18px]') ?><?= e($l) ?></a><?php endforeach; ?></nav>
  <div class="p-3 border-t border-black/5 text-sm"><a href="<?= e(url('/')) ?>" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-50 text-gray-600"><?= icon('external-link', 'w-4 h-4') ?> View website</a></div>
</aside>
<div id="side-scrim" class="hidden fixed inset-0 z-30 bg-black/30 lg:hidden" data-side-toggle></div>
<div class="lg:pl-64 min-h-screen flex flex-col">
  <header class="sticky top-0 z-30 h-16 bg-white/90 backdrop-blur border-b border-black/5 flex items-center justify-between px-4 lg:px-8">
    <div class="flex items-center gap-2"><button type="button" data-side-toggle class="lg:hidden p-2 -ml-2 rounded-lg hover:bg-gray-100" aria-label="Menu"><?= icon('menu', 'w-6 h-6') ?></button><h1 class="font-display font-bold text-lg truncate"><?= e($title ?? '') ?></h1></div>
    <div class="flex items-center gap-3 text-sm"><button type="button" data-theme-toggle class="p-2.5 rounded-xl hover:bg-gray-100 border border-black/5" aria-label="Toggle dark / light theme" title="Toggle theme"><span class="theme-sun"><?= icon('sun', 'w-5 h-5') ?></span><span class="theme-moon"><?= icon('moon', 'w-5 h-5') ?></span></button><a href="<?= e(url('/admin/account')) ?>" class="text-right leading-tight hidden sm:block hover:text-primary"><b class="block"><?= e($admin['name']) ?></b><span class="text-xs muted capitalize"><?= e($role) ?></span></a>
      <form method="post" action="<?= e(url('/admin/logout')) ?>"><?= csrf_field() ?><button class="btn btn-outline btn-sm"><?= icon('log-out', 'w-4 h-4') ?> <span class="hidden sm:inline">Logout</span></button></form></div>
  </header>
  <main class="flex-1 p-4 lg:p-8 max-w-[1400px] w-full">
    <?php foreach (flashes() as [$t, $m]): ?><div role="alert" class="mb-4 rounded-xl px-4 py-3 text-sm font-medium border <?= $t === 'success' ? 'bg-green-50 border-green-200 text-green-800' : ($t === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-blue-50 border-blue-200 text-blue-800') ?>"><?= e($m) ?></div><?php endforeach; ?>
    <?= $content ?>
  </main>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>window.lucide&&lucide.createIcons())</script>
</body></html>
