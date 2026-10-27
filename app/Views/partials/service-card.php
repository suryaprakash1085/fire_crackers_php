<div class="card p-5 h-full"><div class="w-12 h-12 rounded-xl tint-primary flex items-center justify-center overflow-hidden mb-3" style="color:var(--c-primary)">
  <?php if ($s['image']): ?><img src="<?= e(upload_url($s['image'])) ?>" alt="" class="w-full h-full object-cover" loading="lazy"><?php else: ?><?= icon($s['icon'] ?: 'sparkles', 'w-6 h-6') ?><?php endif; ?></div>
  <h3 class="font-bold"><?= e($s['title']) ?></h3><p class="text-sm muted mt-1"><?= e($s['description']) ?></p>
  <?php if (!empty($s['availability'])): ?><p class="text-xs font-semibold mt-3 inline-flex items-center gap-1" style="color:var(--c-secondary)"><?= icon('check-circle-2', 'w-3.5 h-3.5') ?><?= e($s['availability']) ?></p><?php endif; ?></div>
