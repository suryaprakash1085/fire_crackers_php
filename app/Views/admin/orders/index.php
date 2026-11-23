<form method="get" class="card p-4 mb-4 grid sm:grid-cols-2 lg:grid-cols-7 gap-2 items-end">
  <div class="lg:col-span-2"><label class="label">Search</label><input class="input" name="q" value="<?= e($f['q']) ?>" placeholder="Reference, name or mobile"></div>
  <div><label class="label">Status</label><select class="select" name="status"><option value="">All</option><?php foreach (order_statuses() as $k => $l): ?><option value="<?= $k ?>" <?= $f['st'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div><label class="label">Source</label><select class="select" name="source"><option value="">All sources</option><?php foreach (['cart' => 'Cart', 'quick_order' => 'Quick Order', 'voice_order' => 'Voice Order'] as $k => $l): ?><option value="<?= $k ?>" <?= $f['src'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div><label class="label">From</label><input type="date" class="input" name="from" value="<?= e($f['from']) ?>"></div><div><label class="label">To</label><input type="date" class="input" name="to" value="<?= e($f['to']) ?>"></div>
  <div class="flex gap-2"><button class="btn btn-primary flex-1">Filter</button><a class="btn btn-outline" href="<?= e(url('/admin/orders')) ?>">Reset</a></div></form>
<div class="flex justify-between items-center mb-3"><p class="text-sm muted"><?= (int)$total ?> order<?= $total == 1 ? '' : 's' ?></p><div class="flex gap-2"><a class="btn btn-primary btn-sm" href="<?= e(url('/admin/orders/export-excel?' . http_build_query(['q' => $f['q'], 'status' => $f['st'], 'source' => $f['src'], 'from' => $f['from'], 'to' => $f['to']]))) ?>" data-excel-download><?= icon('file-spreadsheet', 'w-4 h-4') ?> Download Excel</a><a class="btn btn-soft btn-sm" href="<?= e(url('/admin/orders/export?' . http_build_query(['q' => $f['q'], 'status' => $f['st'], 'source' => $f['src'], 'from' => $f['from'], 'to' => $f['to']]))) ?>"><?= icon('download', 'w-4 h-4') ?> Export CSV</a></div></div>
<div class="card overflow-hidden"><div class="overflow-x-auto"><table class="table-x w-full text-sm"><thead><tr><th>Reference</th><th>Date</th><th>Customer</th><th>Source</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th class="text-right">Actions</th></tr></thead><tbody>
<?php foreach ($rows as $o): $srcCls = ['cart' => 'bg-gray-100 text-gray-700', 'quick_order' => 'bg-blue-100 text-blue-700', 'voice_order' => 'bg-purple-100 text-purple-700'][$o['order_source']]; ?>
<tr data-order="<?= (int)$o['id'] ?>" data-status="<?= e($o['status']) ?>"><td><a class="font-mono font-semibold text-primary" href="<?= e(url('/admin/orders/' . $o['id'])) ?>"><?= e($o['reference']) ?></a></td><td class="whitespace-nowrap text-xs"><?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?></td><td><b><?= e($o['customer_name']) ?></b><div class="text-xs muted"><?= e($o['mobile']) ?> · <?= e($o['city']) ?></div></td>
<td><span class="badge <?= $srcCls ?>"><?= e(ucwords(str_replace('_', ' ', $o['order_source']))) ?></span></td><td><?= (int)$o['items'] ?></td><td><b><?= money($o['total']) ?></b></td><td><span class="badge <?= $o['payment_status'] === 'paid' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800' ?>"><?= e($o['payment_status']) ?></span></td><td class="js-status"><?= status_badge($o['status']) ?></td>
<td><?php $cx = $o['status'] === 'cancelled'; $ref = e($o['reference']); $oid = (int)$o['id']; ?><div class="om-actions">
  <button type="button" class="om-act" data-om="view" data-id="<?= $oid ?>" data-ref="<?= $ref ?>" title="View order" aria-label="View order <?= $ref ?>"><?= icon('eye') ?></button>
  <button type="button" class="om-act" data-om="edit" data-id="<?= $oid ?>" data-ref="<?= $ref ?>" title="<?= $cx ? 'Order is cancelled' : 'Edit order' ?>" aria-label="Edit order <?= $ref ?>" <?= $cx ? 'disabled' : '' ?>><?= icon('square-pen') ?></button>
  <button type="button" class="om-act" data-om="pdf" data-id="<?= $oid ?>" data-ref="<?= $ref ?>" title="Invoice PDF" aria-label="Invoice PDF for order <?= $ref ?>"><?= icon('file-down') ?></button>
  <button type="button" class="om-act" data-om="cancel" data-id="<?= $oid ?>" data-ref="<?= $ref ?>" title="<?= $cx ? 'Order is cancelled' : 'Cancel order' ?>" aria-label="Cancel order <?= $ref ?>" <?= $cx ? 'disabled' : '' ?>><?= icon('ban') ?></button></div></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="9" class="text-center muted py-12">No orders match.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../../partials/pagination.php'; ?>

<link rel="stylesheet" href="<?= e(asset('assets/css/admin-orders.css')) ?>">
<div class="om-modal" id="om-modal" hidden>
  <div class="om-backdrop" data-om-close></div>
  <div class="om-dialog" role="dialog" aria-modal="true" aria-labelledby="om-title">
    <header><h3 id="om-title">Order</h3><div class="om-head-actions" id="om-head-actions"></div><button type="button" class="om-x" data-om-close aria-label="Close">&times;</button></header>
    <div class="om-body" id="om-body"></div>
  </div>
</div>
<div class="om-confirm" id="om-confirm" hidden>
  <div class="om-backdrop"></div>
  <div class="om-dialog om-sm" role="alertdialog" aria-modal="true" aria-labelledby="om-confirm-title" aria-describedby="om-confirm-desc">
    <div class="om-confirm-body"><div class="om-confirm-ico"><?= icon('triangle-alert', 'w-6 h-6') ?></div>
      <h3 id="om-confirm-title">Cancel this order?</h3>
      <p id="om-confirm-desc">Order <b id="om-confirm-ref"></b> will be marked <b>Cancelled</b> and its stock will be returned. A cancelled order cannot be reopened.</p>
      <label class="label" for="om-cancel-reason" style="text-align:left;margin-top:.8rem">Reason (optional, shown in order history)</label><input class="input" id="om-cancel-reason" maxlength="250" placeholder="e.g. Customer requested cancellation">
      <p class="om-err" id="om-confirm-err" role="alert" hidden style="margin-top:.7rem"></p></div>
    <div class="om-foot"><button type="button" class="btn btn-outline" id="om-keep">No, keep order</button><button type="button" class="btn btn-cancel-order" id="om-do-cancel">Yes, cancel order</button></div>
  </div>
</div>
<div class="om-toast" id="om-toast" role="status" aria-live="polite"></div>
<script src="<?= e(asset('assets/js/admin-orders.js')) ?>" defer></script>
