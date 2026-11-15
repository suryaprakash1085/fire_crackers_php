<?php /** @var array|null $result, @var array|null $preview, @var string|null $confirmToken, @var string|null $error, @var bool $zip, @var string $stockMode, @var bool $createCategories */ ?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
  <a href="<?= e(url('/admin/products')) ?>" class="text-sm text-primary font-semibold">← Back to products</a>
</div>

<?php if ($error): ?>
  <div role="alert" class="mb-4 rounded-xl px-4 py-3 text-sm font-medium border bg-red-50 border-red-200 text-red-800"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($preview): ?>
  <?php
    $sum = $preview['summary'] ?? [];
    $totalErrors = (int)($sum['errors'] ?? 0);
    $totalAdd = (int)($sum['added'] ?? 0);
    $totalUpdate = (int)($sum['updated'] ?? 0);
    $totalUnchanged = (int)($sum['unchanged'] ?? 0);
    $canConfirm = ($totalAdd + $totalUpdate) > 0;
  ?>
  <div class="card p-5 mb-5 border-2 border-primary/30">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
      <div>
        <h2 class="font-bold text-lg">Step 2: Preview Import (Dry Run)</h2>
        <p class="text-xs text-gray-500">Review planned additions, updates, and errors below before saving to the database.</p>
      </div>
      <div class="text-xs font-semibold px-2.5 py-1 rounded-full bg-primary/10 text-primary">
        Stock mode: <?= $stockMode === 'add' ? 'Add (+/- adjustment)' : 'Replace (with export baseline delta)' ?>
      </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center mb-4">
      <div class="rounded-xl bg-green-50 text-green-800 p-3"><div class="text-2xl font-bold"><?= $totalAdd ?></div><div class="text-xs">Ready to add</div></div>
      <div class="rounded-xl bg-blue-50 text-blue-800 p-3"><div class="text-2xl font-bold"><?= $totalUpdate ?></div><div class="text-xs">Ready to update</div></div>
      <div class="rounded-xl bg-gray-100 text-gray-700 p-3"><div class="text-2xl font-bold"><?= $totalUnchanged ?></div><div class="text-xs">Unchanged</div></div>
      <div class="rounded-xl <?= $totalErrors ? 'bg-red-50 text-red-800' : 'bg-gray-100 text-gray-700' ?> p-3"><div class="text-2xl font-bold"><?= $totalErrors ?></div><div class="text-xs">Errors (will skip)</div></div>
    </div>

    <?php if (!empty($preview['newCats'])): ?>
      <p class="text-sm mb-3 font-medium text-emerald-800"><?= (int)$preview['newCats'] ?> new categor<?= $preview['newCats'] == 1 ? 'y will be' : 'ies will be' ?> created upon confirmation.</p>
    <?php endif; ?>

    <div class="overflow-x-auto max-h-96 border rounded-xl mb-4">
      <table class="table-x w-full text-sm">
        <thead class="sticky top-0 bg-gray-50 z-10">
          <tr>
            <th class="w-16">Row</th>
            <th>SKU</th>
            <th>Product Name</th>
            <th class="w-24">Action</th>
            <th>Planned Changes / Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($preview['rows'] as $row): ?>
            <tr class="<?= $row['action'] === 'error' ? 'bg-red-50/50' : ($row['action'] === 'add' ? 'bg-green-50/30' : ($row['action'] === 'update' ? 'bg-blue-50/30' : '')) ?>">
              <td class="font-mono text-xs"><?= (int)$row['line'] ?></td>
              <td class="font-semibold font-mono text-xs"><?= e($row['sku']) ?></td>
              <td><?= e($row['name']) ?></td>
              <td>
                <?php if ($row['action'] === 'add'): ?>
                  <span class="inline-block px-2 py-0.5 text-xs font-bold rounded bg-green-100 text-green-800">Add</span>
                <?php elseif ($row['action'] === 'update'): ?>
                  <span class="inline-block px-2 py-0.5 text-xs font-bold rounded bg-blue-100 text-blue-800">Update</span>
                <?php elseif ($row['action'] === 'unchanged'): ?>
                  <span class="inline-block px-2 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">No change</span>
                <?php else: ?>
                  <span class="inline-block px-2 py-0.5 text-xs font-bold rounded bg-red-100 text-red-800">Error</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($row['changes'])): ?>
                  <ul class="text-xs space-y-0.5">
                    <?php foreach ($row['changes'] as $ch): ?>
                      <li><?= e($ch) ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
                <?php if (!empty($row['errors'])): ?>
                  <ul class="text-xs text-red-700 font-semibold space-y-0.5 mt-1">
                    <?php foreach ($row['errors'] as $er): ?>
                      <li><?= e($er) ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
                <?php if (!empty($row['warnings'])): ?>
                  <ul class="text-xs text-amber-800 space-y-0.5 mt-1">
                    <?php foreach ($row['warnings'] as $wn): ?>
                      <li>⚠ <?= e($wn) ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
      <form method="post" action="<?= e(url('/admin/products/import')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel_preview">
        <input type="hidden" name="confirm_token" value="<?= e($confirmToken) ?>">
        <button class="btn btn-outline text-sm">Cancel &amp; Discard</button>
      </form>
      <form method="post" action="<?= e(url('/admin/products/import')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="confirm">
        <input type="hidden" name="confirm_token" value="<?= e($confirmToken) ?>">
        <input type="hidden" name="stock_mode" value="<?= e($stockMode) ?>">
        <input type="hidden" name="create_categories" value="<?= !empty($createCategories) ? '1' : '0' ?>">
        <button class="btn btn-primary" <?= !$canConfirm && !$totalUnchanged ? 'disabled' : '' ?>>
          <?= icon('check', 'w-4 h-4') ?> Confirm &amp; Save (<?= $totalAdd ?> to add, <?= $totalUpdate ?> to update)
        </button>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($result): $bad = count($result['errors']); ?>
<div class="card p-5 mb-5">
  <h2 class="font-bold mb-3">Import result</h2>
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
    <div class="rounded-xl bg-green-50 text-green-800 p-3"><div class="text-2xl font-bold"><?= (int)$result['added'] ?></div><div class="text-xs">New products added</div></div>
    <div class="rounded-xl bg-blue-50 text-blue-800 p-3"><div class="text-2xl font-bold"><?= (int)$result['updated'] ?></div><div class="text-xs">Products updated</div></div>
    <div class="rounded-xl bg-gray-100 text-gray-700 p-3"><div class="text-2xl font-bold"><?= (int)$result['unchanged'] ?></div><div class="text-xs">No change</div></div>
    <div class="rounded-xl <?= $bad ? 'bg-red-50 text-red-800' : 'bg-gray-100 text-gray-700' ?> p-3"><div class="text-2xl font-bold"><?= $bad ?></div><div class="text-xs">Rows skipped (errors)</div></div>
  </div>
  <?php if (!empty($result['newCats'])): ?><p class="text-sm mt-3"><?= (int)$result['newCats'] ?> new categor<?= $result['newCats'] == 1 ? 'y was' : 'ies were' ?> created.</p><?php endif; ?>
  <?php if ($bad): ?>
    <p class="text-sm mt-4 font-semibold text-red-800">These rows were NOT saved – fix them in Excel and upload again (the other rows are already saved):</p>
    <div class="overflow-x-auto mt-2"><table class="table-x w-full text-sm"><thead><tr><th>Excel row</th><th>SKU / Name</th><th>Problem</th></tr></thead><tbody>
    <?php foreach ($result['errors'] as $er): ?><tr><td class="font-mono"><?= (int)$er['line'] ?></td><td><?= e($er['sku']) ?></td><td class="text-red-700"><?= e($er['msg']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
  <?php if (!empty($result['warnings'])): ?>
    <div role="status" class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
      <p class="font-bold">Stock &amp; product warnings — review these rows</p>
      <div class="overflow-x-auto mt-2"><table class="table-x w-full text-sm"><thead><tr><th>Excel row</th><th>SKU</th><th>Warning</th></tr></thead><tbody>
      <?php foreach ($result['warnings'] as $warning): ?><tr><td class="font-mono"><?= (int)$warning['line'] ?></td><td><?= e($warning['sku']) ?></td><td><?= e($warning['msg']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid lg:grid-cols-2 gap-5">
  <div class="card p-5">
    <h2 class="font-bold mb-1">1 · Download Excel</h2>
    <p class="text-sm muted mb-4">Get the sheet, edit it in Excel, then upload it back on the right.</p>
    <div class="flex flex-wrap items-end gap-2">
      <form method="get" action="<?= e(url('/admin/products/template')) ?>" class="flex flex-wrap items-center gap-3">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="include_archived" value="1"> Include archived</label>
        <button class="btn btn-primary"><?= icon('download', 'w-4 h-4') ?> All products (to edit)</button>
      </form>
      <a class="btn btn-outline" href="<?= e(url('/admin/products/template?blank=1')) ?>"><?= icon('file-plus', 'w-4 h-4') ?> Blank template (new products)</a>
    </div>
    <?php if (!$zip): ?><p class="text-sm text-red-700 mt-3">PHP “zip” extension is not enabled, so .xlsx downloads will not work. Enable <code>extension=zip</code> in php.ini and restart Apache. (You can still upload a .csv.)</p><?php endif; ?>
    <ul class="text-sm mt-5 space-y-1.5 list-disc pl-5 muted">
      <li><b>Edit:</b> change prices, stock, names, status… in the sheet. Keep the <b>ID</b> / <b>SKU</b> column. SKU column is formatted as Text to preserve leading zeros.</li>
      <li><b>Add new:</b> add a row at the bottom, leave <b>ID</b> empty and fill SKU, Name, Category, Original &amp; Selling price.</li>
      <li>Empty cell on an existing product = keep the current value. Type <code>-</code> to clear a text field.</li>
      <li>Category / Status / Featured / Popular have dropdowns in the sheet. Inactive categories trigger a warning.</li>
      <li><b>Stock at export</b> is a baseline snapshot. In replace mode, any customer orders since download are preserved using delta calculations: <code>new_stock = current_stock + (sheet_stock - stock_at_export)</code>.</li>
      <li><b>Add mode:</b> If Stock equals “Stock at export”, the change is treated as 0 so unedited exported totals are not added again.</li>
      <li>Images &amp; videos are added from each product’s page (not in Excel).</li>
    </ul>
  </div>
  <form class="card p-5" method="post" enctype="multipart/form-data" action="<?= e(url('/admin/products/import')) ?>">
    <?= csrf_field() ?>
    <h2 class="font-bold mb-1">2 · Upload edited Excel</h2>
    <p class="text-sm muted mb-4">Preview changes first, or save directly to the database.</p>
    <label class="label" for="sheet">Excel file (.xlsx or .csv)</label>
    <input class="block text-sm w-full" type="file" id="sheet" name="sheet" accept=".xlsx,.csv" required>
    <label class="label mt-4" for="stock_mode">Stock mode</label>
    <select class="select" id="stock_mode" name="stock_mode">
      <option value="replace" <?= ($stockMode ?? 'replace') === 'replace' ? 'selected' : '' ?>>Replace — use Stock as new total (with export baseline delta protection)</option>
      <option value="add" <?= ($stockMode ?? 'replace') === 'add' ? 'selected' : '' ?>>Add (+/−) — apply Stock as signed change</option>
    </select>
    <div class="mt-2 rounded-lg bg-blue-50/60 p-2.5 text-xs text-blue-900 border border-blue-200">
      <p class="font-semibold mb-0.5">Note on Add mode vs Replace mode:</p>
      <p>In <b>Add mode</b>, if Stock equals “Stock at export”, the change is treated as 0 so unedited exported totals are not added again. In <b>Replace mode</b>, changes are applied as a delta against the live stock if “Stock at export” is present.</p>
    </div>
    <label class="flex items-center gap-2 text-sm mt-4"><input type="checkbox" name="create_categories" value="1" <?= !empty($createCategories) ? 'checked' : '' ?>> Create missing categories automatically</label>
    <p class="hint">If unticked, a row with an unknown category is skipped with an error.</p>
    <div class="flex flex-wrap gap-2 mt-5">
      <button type="submit" name="action" value="preview" class="btn btn-primary"><?= icon('eye', 'w-4 h-4') ?> Preview changes (dry run)</button>
      <button type="submit" name="action" value="import" class="btn btn-outline" data-confirm="Upload now? Matching products will be updated in the database."><?= icon('upload', 'w-4 h-4') ?> Direct upload</button>
    </div>
    <p class="hint mt-2">Tip: use Preview first to verify row actions before committing changes.</p>
  </form>
</div>
