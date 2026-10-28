<?php /** $name,$label,$value */ ?>
<div data-image-field class="space-y-2"><div class="label"><?= e($label) ?></div>
  <div class="flex items-start gap-3"><div data-preview class="w-24 h-24 rounded-xl border bg-gray-50 overflow-hidden flex items-center justify-center text-gray-300 shrink-0"><?php if (!empty($value)): ?><img src="<?= e(upload_url($value)) ?>" class="w-full h-full object-cover" alt=""><?php else: ?><?= icon('image', 'w-8 h-8') ?><?php endif; ?></div>
    <div class="space-y-2 min-w-0"><input type="file" name="<?= e($name) ?>_file" accept="image/jpeg,image/png,image/webp" class="block text-sm w-full">
      <?php if (!empty($value)): ?><label class="flex items-center gap-2 text-xs text-red-700"><input type="checkbox" name="<?= e($name) ?>_remove" value="1" data-remove-chk> Remove current image</label><?php endif; ?></div></div>
  <?php if (!empty($errors[$name])): ?><p class="err"><?= e($errors[$name]) ?></p><?php endif; ?><p class="hint">JPG, PNG or WebP, max 6 MB.</p></div>
