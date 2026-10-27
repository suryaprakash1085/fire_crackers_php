<?php if ($pages > 1):
  $q = $_GET; unset($q['page']); $mk = fn($n) => '?' . http_build_query($q + ['page' => $n]);
  $from = max(1, $page - 2); $to = min($pages, $page + 2); $cls = $cls ?? 'chip'; ?>
<nav class="flex flex-wrap items-center justify-center gap-1.5 mt-8" aria-label="Pagination">
  <?php if ($page > 1): ?><a class="<?= $cls ?>" href="<?= e($mk($page - 1)) ?>" rel="prev">‹ Prev</a><?php endif; ?>
  <?php if ($from > 1): ?><a class="<?= $cls ?>" href="<?= e($mk(1)) ?>">1</a><?php if ($from > 2): ?><span class="muted">…</span><?php endif; endif; ?>
  <?php for ($i = $from; $i <= $to; $i++): ?><a class="<?= $cls ?> <?= $i === $page ? 'active' : '' ?>" <?= $i === $page ? 'aria-current="page"' : '' ?> href="<?= e($mk($i)) ?>"><?= $i ?></a><?php endfor; ?>
  <?php if ($to < $pages): ?><?php if ($to < $pages - 1): ?><span class="muted">…</span><?php endif; ?><a class="<?= $cls ?>" href="<?= e($mk($pages)) ?>"><?= $pages ?></a><?php endif; ?>
  <?php if ($page < $pages): ?><a class="<?= $cls ?>" href="<?= e($mk($page + 1)) ?>" rel="next">Next ›</a><?php endif; ?>
</nav>
<?php endif; ?>
