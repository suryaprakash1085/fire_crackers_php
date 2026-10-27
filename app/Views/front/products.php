<?php
$base = array_filter(['category' => $cid ?: null, 'q' => $q !== '' ? $q : null, 'sort' => $sort !== 'new' ? $sort : null, 'limit' => $per !== 12 ? $per : null, 'min' => $f['min'], 'max' => $f['max'], 'instock' => $f['instock'] ? 1 : null], fn($v) => $v !== null && $v !== '');
$link = fn(array $o) => url('/products') . '?' . http_build_query(array_filter($o + $base, fn($v) => $v !== null && $v !== ''));
$hasFilter = $q !== '' || $f['min'] || $f['max'] || $f['instock'] || $cid;
?>
<section class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 py-8">
  <nav class="text-sm muted mb-3" aria-label="Breadcrumb"><a href="<?= e(url('/')) ?>" class="hover:text-primary">Home</a> <span class="mx-1">/</span> <span class="text-ink font-medium">Products</span></nav>
  <div class="relative overflow-hidden rounded-3xl p-6 md:p-8 mb-6 text-white" style="background:linear-gradient(120deg,var(--c-primary),var(--c-secondary))">
    <div class="absolute -right-6 -bottom-6 opacity-25 hidden sm:block"><?= art('rocket', 'w-48 h-48') ?></div>
    <h1 class="font-display font-extrabold text-2xl md:text-4xl relative"><?= e($q !== '' ? 'Results for “' . $q . '”' : ($activeCat['name'] ?? 'All Crackers')) ?></h1>
    <p class="relative mt-1.5 text-white/85 text-sm md:text-base max-w-xl"><?= e(!empty($activeCat['description']) && $q === '' ? $activeCat['description'] : 'Pick your favourites — add to cart with one tap and watch the sparks fly.') ?></p>
    <form method="get" class="relative mt-4 flex gap-2 max-w-xl" role="search"><?php foreach ($base as $k => $v): if ($k === 'q') continue; ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
      <div class="relative flex-1"><input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Search by name, Tamil name or ID…" class="input !pl-10 !border-0 !rounded-full" <?= ($_GET['focus'] ?? '') === 'search' ? 'autofocus' : '' ?>><span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-ink/40 pointer-events-none"><?= icon('search', 'w-4 h-4') ?></span></div>
      <button class="btn btn-white !rounded-full">Search</button></form>
  </div>

  <div class="lg:grid lg:grid-cols-[270px_1fr] gap-6 items-start">
    <aside class="mb-5 lg:mb-0 lg:sticky lg:top-24">
      <details class="lg:hidden filter-box" id="filters-m"><summary class="font-bold cursor-pointer flex items-center gap-2 list-none"><?= icon('sliders-horizontal', 'w-4 h-4 text-primary') ?> Filters &amp; sort<?= $hasFilter ? ' <span class="badge tint-primary text-primary">on</span>' : '' ?></summary><div class="mt-4" data-filter-slot></div></details>
      <div class="hidden lg:block" id="filters-d"><div data-filter-slot>
        <form method="get" class="space-y-4" id="filter-form">
          <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
          <div class="filter-box"><div class="font-bold text-sm mb-2 flex items-center gap-2"><?= icon('layout-grid', 'w-4 h-4 text-primary') ?> Categories</div>
            <nav class="space-y-0.5 max-h-72 overflow-auto"><a class="cat-link <?= !$cid ? 'on' : '' ?>" href="<?= e($link(['category' => null])) ?>"><span>All products</span></a>
              <?php foreach ($categories as $c): ?><a class="cat-link <?= (int)$c['id'] === $cid ? 'on' : '' ?>" href="<?= e($link(['category' => (int)$c['id']])) ?>"><span class="truncate"><?= e($c['name']) ?></span><span class="text-xs opacity-60"><?= (int)$c['product_count'] ?></span></a><?php endforeach; ?></nav></div>
          <?php if ($cid): ?><input type="hidden" name="category" value="<?= (int)$cid ?>"><?php endif; ?>
          <div class="filter-box"><div class="font-bold text-sm mb-2 flex items-center gap-2"><?= icon('indian-rupee', 'w-4 h-4 text-primary') ?> Price (₹)</div>
            <div class="grid grid-cols-2 gap-2"><input class="input !min-h-[38px]" type="number" min="0" name="min" value="<?= e($f['min'] ?? '') ?>" placeholder="Min <?= (int)$range[0] ?>" aria-label="Minimum price"><input class="input !min-h-[38px]" type="number" min="0" name="max" value="<?= e($f['max'] ?? '') ?>" placeholder="Max <?= (int)$range[1] ?>" aria-label="Maximum price"></div>
            <label class="flex items-center gap-2 text-sm mt-3 cursor-pointer"><input type="checkbox" name="instock" value="1" class="w-4 h-4 accent-[var(--c-primary)]" <?= $f['instock'] ? 'checked' : '' ?>> In stock only</label></div>
          <div class="filter-box grid grid-cols-2 gap-2"><div><label class="label" for="sort-<?= uniqid() ?>">Sort</label><select name="sort" class="select !min-h-[38px] !text-sm"><?php foreach (['new' => 'Newest', 'price_asc' => 'Price ↑', 'price_desc' => 'Price ↓', 'name' => 'A–Z'] as $k => $v): ?><option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
            <div><label class="label">Per page</label><select name="limit" class="select !min-h-[38px] !text-sm"><?php foreach ($limits as $l): ?><option <?= $per === $l ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div></div>
          <div class="flex gap-2"><button class="btn btn-primary flex-1">Apply</button><?php if ($hasFilter || $per !== 12 || $sort !== 'new'): ?><a class="btn btn-outline" href="<?= e(url('/products')) ?>">Reset</a><?php endif; ?></div>
        </form></div></div>
    </aside>

    <div class="min-w-0">
      <div class="flex flex-wrap items-center justify-between gap-2 mb-4"><p class="text-sm muted" id="count-line">Showing <b class="text-ink" id="shown"><?= count($products) ?></b> of <b class="text-ink"><?= (int)$total ?></b> product<?= $total === 1 ? '' : 's' ?></p>
        <div class="hidden sm:flex items-center gap-1.5 text-sm"><span class="muted">Per page</span><?php foreach ($limits as $l): ?><a class="chip !px-3 !py-1 <?= $per === $l ? 'active' : '' ?>" href="<?= e($link(['limit' => $l === 12 ? null : $l, 'page' => null])) ?>"><?= $l ?></a><?php endforeach; ?></div></div>
      <?php if (!$products): ?><div class="card p-12 text-center"><?= art('sky', 'w-24 h-24 mx-auto') ?><p class="font-semibold mt-3">No products found</p><p class="text-sm muted mt-1">Try a different word or reset the filters.</p><a class="btn btn-primary mt-4" href="<?= e(url('/products')) ?>">View all products</a></div>
      <?php else: ?>
      <div id="grid" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3 md:gap-5" data-next="<?= $page + 1 ?>" data-more="<?= $page < $pages ? 1 : 0 ?>" data-src="<?= e($link(['page' => null])) ?>"><?php foreach ($products as $p) include __DIR__ . '/../partials/product-card.php'; ?></div>
      <div id="sentinel" class="py-8 text-center <?= $page < $pages ? '' : 'hidden' ?>"><button type="button" id="load-more" class="btn btn-outline"><?= icon('chevrons-down', 'w-4 h-4') ?> Load more</button><span id="loading" class="hidden spinner"></span></div>
      <p id="end-msg" class="<?= $page < $pages ? 'hidden' : '' ?> text-center text-sm muted py-6">🎆 You have seen all <?= (int)$total ?> product<?= $total === 1 ? '' : 's' ?>.</p>
      <noscript><?php include __DIR__ . '/../partials/pagination.php'; ?></noscript>
      <?php endif; ?>
    </div>
  </div>
</section>
<script>
(function () {
  // mobile: reuse the same filter form inside the collapsible panel
  var d = document.querySelector('#filters-d [data-filter-slot]'), m = document.querySelector('#filters-m [data-filter-slot]');
  if (d && m && window.matchMedia('(max-width:1023px)').matches) m.appendChild(d.firstElementChild);
  var grid = document.getElementById('grid'); if (!grid) return;
  var next = +grid.dataset.next, more = grid.dataset.more === '1', busy = false, shown = document.getElementById('shown'), sent = document.getElementById('sentinel'), end = document.getElementById('end-msg'), spin = document.getElementById('loading'), btn = document.getElementById('load-more');
  async function load() {
    if (!more || busy) return; busy = true; spin.classList.remove('hidden'); btn.classList.add('hidden');
    try {
      var src = grid.dataset.src, r = await fetch(src + (src.indexOf('?') > -1 ? '&' : '?') + 'page=' + next + '&ajax=1', { headers: { Accept: 'application/json' } }), j = await r.json();
      var t = document.createElement('div'); t.innerHTML = j.html; var n = 0; while (t.firstElementChild) { grid.appendChild(t.firstElementChild); n++; }
      shown.textContent = +shown.textContent + n; next = j.page + 1; more = j.has_more; window.FC && FC.icons();
    } catch (e) { more = true; }
    busy = false; spin.classList.add('hidden'); btn.classList.remove('hidden');
    if (!more) { sent.classList.add('hidden'); end.classList.remove('hidden'); }
  }
  btn.addEventListener('click', load);
  if ('IntersectionObserver' in window) new IntersectionObserver(function (e) { if (e[0].isIntersecting) load(); }, { rootMargin: '600px 0px' }).observe(sent);
})();
</script>
