<?php /** @var array $o @var array $inv */ $c = $inv['company']; $pct = rtrim(rtrim(number_format($inv['discount_percent'], 2), '0'), '.'); ?>
<div class="inv" id="inv-sheet">
  <div class="inv-head"><div><?php if ($lg = company_logo_url()): ?><img src="<?= e($lg) ?>" alt="" style="max-height:52px;max-width:160px;object-fit:contain;display:block;margin-bottom:6px;background:#fff;border-radius:6px"><?php endif; ?><div class="inv-brand"><?= e($c['name']) ?></div>
      <?php if ($c['legal'] && $c['legal'] !== $c['name']): ?><div><?= e($c['legal']) ?></div><?php endif; ?>
      <?php if ($c['address']): ?><div><?= nl2br(e($c['address'])) ?></div><?php endif; ?>
      <div><?= $c['phone'] ? 'Phone: ' . e($c['phone']) : '' ?><?= $c['email'] ? ' &nbsp;·&nbsp; ' . e($c['email']) : '' ?></div>
      <?php if ($c['gst'] || $c['license']): ?><div><?= $c['gst'] ? 'GSTIN: ' . e($c['gst']) : '' ?><?= $c['gst'] && $c['license'] ? ' &nbsp;·&nbsp; ' : '' ?><?= $c['license'] ? 'Licence: ' . e($c['license']) : '' ?></div><?php endif; ?></div>
    <div class="inv-title"><b>INVOICE</b><span>#<?= e($o['reference']) ?></span></div></div>
  <?php if ($c['note']): ?><p class="inv-note"><?= e($c['note']) ?></p><?php endif; ?>
  <div class="inv-cols"><div><h4>Bill to / Ship to</h4><b><?= e($o['customer_name']) ?></b><br><?= nl2br(e($o['address'])) ?><br><?= e($o['city']) ?>, <?= e($o['state']) ?> – <?= e($o['pincode']) ?><?= $o['landmark'] ? '<br>Landmark: ' . e($o['landmark']) : '' ?><br>Mobile: <?= e($o['mobile']) ?><?= $o['email'] ? '<br>' . e($o['email']) : '' ?></div>
    <div><h4>Order details</h4><dl><dt>Order ID</dt><dd><?= e($o['reference']) ?></dd><dt>Order date</dt><dd><?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?></dd><dt>Order status</dt><dd><?= e($inv['status_label']) ?></dd><dt>Payment method</dt><dd><?= e($inv['method_label']) ?></dd><dt>Payment status</dt><dd><?= e(ucfirst($o['payment_status'])) ?></dd></dl></div></div>
  <div class="om-scroll"><table class="inv-table"><thead><tr><th>#</th><th>Product</th><th class="r">Qty</th><th class="r">Original</th><th class="r">Discount</th><th class="r">Price</th><th class="r">Amount</th></tr></thead><tbody>
    <?php foreach ($inv['lines'] as $n => $l): ?><tr><td><?= $n + 1 ?></td><td><b><?= e($l['name']) ?></b><small><?= e($l['sku']) ?></small></td><td class="r"><?= (int)$l['qty'] ?></td><td class="r"><?= money($l['original_price']) ?></td><td class="r"><?= $l['discount_unit'] > 0 ? '− ' . money($l['discount_unit']) : '—' ?></td><td class="r"><?= money($l['unit_price']) ?></td><td class="r"><b><?= money($l['line_total']) ?></b></td></tr><?php endforeach; ?></tbody></table></div>
  <div class="inv-cols inv-bottom"><div><h4>Payment information</h4><dl><dt>Method</dt><dd><?= e($inv['method_label']) ?></dd><dt>Status</dt><dd><?= e(ucfirst($o['payment_status'])) ?></dd>
      <?php foreach ([['UPI ID', $c['upi']], ['Bank', $c['bank_name']], ['A/c holder', $c['bank_holder']], ['A/c number', $c['bank_account']], ['IFSC', $c['bank_ifsc']]] as [$k, $v]) if ($v): ?><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd><?php endif; ?></dl></div>
    <div><dl class="inv-totals"><dt>Subtotal (original price)</dt><dd><?= money($inv['subtotal']) ?></dd>
      <?php if ($inv['item_discount'] > 0): ?><dt>Discount<?= $pct !== '' && $pct !== '0' ? ' (' . e($pct) . '%)' : '' ?></dt><dd class="g">− <?= money($inv['item_discount']) ?></dd><?php endif; ?>
      <?php if ($inv['extra_discount'] > 0): ?><dt>Additional discount</dt><dd class="g">− <?= money($inv['extra_discount']) ?></dd><?php endif; ?>
      <dt>Delivery charge</dt><dd><?= money($o['delivery_charge']) ?></dd><dt class="tot">Total</dt><dd class="tot"><?= money($o['total']) ?></dd></dl></div></div>
  <?php if ($c['terms']): ?><div class="inv-terms"><h4>Terms &amp; conditions</h4><?= nl2br(e($c['terms'])) ?></div><?php endif; ?>
  <div class="inv-foot"><?= e($c['footer'] ?: 'Thank you for your order!') ?></div>
</div>
