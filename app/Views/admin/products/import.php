<?php /** $result, $error, $zip */ ?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-4"><a href="<?= e(url('/admin/products')) ?>" class="text-sm text-primary font-semibold">← Back to products</a></div>

<?php if ($error): ?><div role="alert" class="mb-4 rounded-xl px-4 py-3 text-sm font-medium border bg-red-50 border-red-200 text-red-800"><?= e($error) ?></div><?php endif; ?>

<?php if ($result): $bad = count($result['errors']); ?>
<div class="card p-5 mb-5">
  <h2 class="font-bold mb-3">Import result</h2>
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
    <div class="rounded-xl bg-green-50 text-green-800 p-3"><div class="text-2xl font-bold"><?= (int)$result['added'] ?></div><div class="text-xs">New products added</div></div>
    <div class="rounded-xl bg-blue-50 text-blue-800 p-3"><div class="text-2xl font-bold"><?= (int)$result['updated'] ?></div><div class="text-xs">Products updated</div></div>
    <div class="rounded-xl bg-gray-100 text-gray-700 p-3"><div class="text-2xl font-bold"><?= (int)$result['unchanged'] ?></div><div class="text-xs">No change</div></div>
    <div class="rounded-xl <?= $bad ? 'bg-red-50 text-red-800' : 'bg-gray-100 text-gray-700' ?> p-3"><div class="text-2xl font-bold"><?= $bad ?></div><div class="text-xs">Rows skipped (errors)</div></div>
  </div>
  <?php if ($result['newCats']): ?><p class="text-sm mt-3"><?= (int)$result['newCats'] ?> new categor<?= $result['newCats'] == 1 ? 'y was' : 'ies were' ?> created.</p><?php endif; ?>
  <?php if ($bad): ?>
    <p class="text-sm mt-4 font-semibold text-red-800">These rows were NOT saved – fix them in Excel and upload again (the other rows are already saved):</p>
    <div class="overflow-x-auto mt-2"><table class="table-x w-full text-sm"><thead><tr><th>Excel row</th><th>SKU / Name</th><th>Problem</th></tr></thead><tbody>
    <?php foreach ($result['errors'] as $er): ?><tr><td class="font-mono"><?= (int)$er['line'] ?></td><td><?= e($er['sku']) ?></td><td class="text-red-700"><?= e($er['msg']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid lg:grid-cols-2 gap-5">
  <div class="card p-5">
    <h2 class="font-bold mb-1">1 · Download Excel</h2>
    <p class="text-sm muted mb-4">Get the sheet, edit it in Excel, then upload it back on the right.</p>
    <div class="flex flex-wrap gap-2">
      <a class="btn btn-primary" href="<?= e(url('/admin/products/template')) ?>"><?= icon('download', 'w-4 h-4') ?> All products (to edit)</a>
      <a class="btn btn-outline" href="<?= e(url('/admin/products/template?blank=1')) ?>"><?= icon('file-plus', 'w-4 h-4') ?> Blank template (new products)</a>
    </div>
    <?php if (!$zip): ?><p class="text-sm text-red-700 mt-3">PHP “zip” extension is not enabled, so .xlsx downloads will not work. Enable <code>extension=zip</code> in php.ini and restart Apache. (You can still upload a .csv.)</p><?php endif; ?>
    <ul class="text-sm mt-5 space-y-1.5 list-disc pl-5 muted">
      <li><b>Edit:</b> change prices, stock, names, status… in the sheet. Keep the <b>ID</b> / <b>SKU</b> column.</li>
      <li><b>Add new:</b> add a row at the bottom, leave <b>ID</b> empty and fill SKU, Name, Category, Original &amp; Selling price.</li>
      <li>Empty cell on an existing product = keep the current value. Type <code>-</code> to clear a text field.</li>
      <li>Category / Status / Featured / Popular have dropdowns in the sheet.</li>
      <li>Images &amp; videos are added from each product’s page (not in Excel).</li>
    </ul>
  </div>
  <form class="card p-5" method="post" enctype="multipart/form-data" action="<?= e(url('/admin/products/import')) ?>">
    <?= csrf_field() ?>
    <h2 class="font-bold mb-1">2 · Upload edited Excel</h2>
    <p class="text-sm muted mb-4">Existing products are updated, new ones are added, directly in the database.</p>
    <label class="label" for="sheet">Excel file (.xlsx or .csv)</label>
    <input class="block text-sm w-full" type="file" id="sheet" name="sheet" accept=".xlsx,.csv" required>
    <label class="flex items-center gap-2 text-sm mt-4"><input type="checkbox" name="create_categories" value="1"> Create missing categories automatically</label>
    <p class="hint">If unticked, a row with an unknown category is skipped with an error.</p>
    <button class="btn btn-primary mt-5" data-confirm="Upload now? Matching products will be updated in the database."><?= icon('upload', 'w-4 h-4') ?> Upload &amp; update database</button>
    <p class="hint mt-2">Tip: download the Excel first as a backup before bulk changes.</p>
  </form>
</div>
