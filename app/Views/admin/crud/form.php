<form method="post" enctype="multipart/form-data" action="<?= e(url("/admin/$e/save")) ?>" class="card p-5 md:p-6 max-w-3xl grid gap-4" data-once><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($row['id'] ?? 0) ?>">
<?php foreach ($c['fields'] as $k => $def): [$label, $type] = $def; $val = $row[$k] ?? ''; $er = $errors[$k] ?? null; ?>
  <?php if ($type === 'image'): $name = $k; $value = $val; $label = $def[0]; include __DIR__ . '/../_image_field.php'; ?>
  <?php elseif ($type === 'check'): ?><label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="<?= $k ?>" value="1" <?= $val ? 'checked' : '' ?>> <?= e($label) ?></label>
  <?php else: ?><div><label class="label" for="<?= $k ?>"><?= e($label) ?><?= !empty($def['req']) ? ' *' : '' ?></label>
    <?php if ($type === 'textarea'): ?><textarea class="textarea <?= $er ? 'has-error' : '' ?>" id="<?= $k ?>" name="<?= $k ?>"><?= e($val) ?></textarea>
    <?php elseif ($type === 'datetime'): ?><input class="input <?= $er ? 'has-error' : '' ?>" type="datetime-local" id="<?= $k ?>" name="<?= $k ?>" value="<?= $val ? e(date('Y-m-d\TH:i', strtotime($val))) : '' ?>">
    <?php else: ?><input class="input <?= $er ? 'has-error' : '' ?>" type="<?= $type === 'number' ? 'number' : 'text' ?>" id="<?= $k ?>" name="<?= $k ?>" value="<?= e($val) ?>" <?= !empty($def['req']) ? 'required' : '' ?> <?= isset($def['max']) ? 'maxlength="' . $def['max'] . '"' : '' ?>><?php endif; ?>
    <?php if ($er): ?><p class="err"><?= e($er) ?></p><?php endif; ?></div><?php endif; ?>
<?php endforeach; ?><div class="flex gap-2"><button class="btn btn-primary">Save</button><a class="btn btn-outline" href="<?= e(url("/admin/$e")) ?>">Cancel</a></div></form>
