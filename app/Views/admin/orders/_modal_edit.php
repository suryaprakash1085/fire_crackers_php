<?php /** @var array $o @var array $inv @var array $methods */
if ($o['status'] === 'cancelled'): ?>
<div class="om-note">This order is <b>Cancelled</b>. Cancelled orders can't be edited or reopened (stock was already restored).</div>
<div class="om-foot"><button type="button" class="btn btn-outline" data-om-close>Close</button></div>
<?php return; endif;
$methodOpts = $methods; if (!isset($methodOpts[$o['payment_method']])) $methodOpts[$o['payment_method']] = ['label' => $inv['method_label']];
$extra = $inv['extra_discount']; $n = 0; ?>
<form id="om-edit-form" data-order="<?= (int)$o['id'] ?>" data-save="<?= e(url('/admin/orders/' . $o['id'] . '/edit')) ?>" novalidate autocomplete="off">
  <div class="om-err" id="om-err" role="alert" hidden></div>
  <h4 class="om-h">Customer details</h4>
  <div class="om-fields">
    <div><label class="label" for="oe-name">Customer name *</label><input class="input" id="oe-name" name="customer_name" value="<?= e($o['customer_name']) ?>" maxlength="120" required></div>
    <div><label class="label" for="oe-mobile">Mobile *</label><input class="input" id="oe-mobile" name="mobile" value="<?= e($o['mobile']) ?>" inputmode="numeric" maxlength="13" required></div>
    <div><label class="label" for="oe-email">Email</label><input class="input" id="oe-email" name="email" type="email" value="<?= e($o['email']) ?>" maxlength="190"></div>
  </div>
  <h4 class="om-h">Shipping address</h4>
  <div class="om-fields">
    <div class="om-wide"><label class="label" for="oe-addr">Address *</label><textarea class="textarea !min-h-[70px]" id="oe-addr" name="address" maxlength="500" required><?= e($o['address']) ?></textarea></div>
    <div><label class="label" for="oe-city">City *</label><input class="input" id="oe-city" name="city" value="<?= e($o['city']) ?>" maxlength="100" required></div>
    <div><label class="label" for="oe-state">State *</label><select class="select" id="oe-state" name="state" required><?php foreach (indian_states() as $s): ?><option <?= $o['state'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
    <div><label class="label" for="oe-pin">Pincode *</label><input class="input" id="oe-pin" name="pincode" value="<?= e($o['pincode']) ?>" inputmode="numeric" maxlength="6" required></div>
    <div><label class="label" for="oe-land">Landmark</label><input class="input" id="oe-land" name="landmark" value="<?= e($o['landmark']) ?>" maxlength="190"></div>
    <div class="om-wide"><label class="label" for="oe-notes">Customer note</label><input class="input" id="oe-notes" name="notes" value="<?= e($o['notes']) ?>" maxlength="500"></div>
  </div>

  <h4 class="om-h">Products</h4>
  <div class="om-scroll"><table class="table-x w-full text-sm" id="om-items"><thead><tr><th>Product</th><th style="width:92px">Qty</th><th style="width:120px" class="text-right">Original ₹</th><th style="width:120px" class="text-right">Final price ₹</th><th style="width:110px" class="text-right">Amount</th><th style="width:44px"></th></tr></thead><tbody>
  <?php foreach ($o['items'] as $i): $k = $n++; ?>
    <tr data-row><td><b><?= e($i['name']) ?></b><div class="text-xs muted font-mono"><?= e($i['sku']) ?></div>
      <input type="hidden" name="items[<?= $k ?>][item_id]" value="<?= (int)$i['id'] ?>"><input type="hidden" name="items[<?= $k ?>][product_id]" value="<?= (int)$i['product_id'] ?>"></td>
      <td><input class="input om-sm" type="number" min="1" max="100000" step="1" name="items[<?= $k ?>][qty]" value="<?= (int)$i['quantity'] ?>" data-f="qty" aria-label="Quantity of <?= e($i['name']) ?>"></td>
      <td><input class="input om-sm text-right" type="number" min="0" step="0.01" name="items[<?= $k ?>][original_price]" value="<?= e(number_format((float)$i['original_price'], 2, '.', '')) ?>" data-f="orig" aria-label="Original price"></td>
      <td><input class="input om-sm text-right" type="number" min="0" step="0.01" name="items[<?= $k ?>][unit_price]" value="<?= e(number_format((float)$i['unit_price'], 2, '.', '')) ?>" data-f="unit" aria-label="Final price"></td>
      <td class="text-right"><b data-line>₹0.00</b></td>
      <td><button type="button" class="btn btn-danger btn-sm om-icon" data-remove title="Remove product" aria-label="Remove <?= e($i['name']) ?>"><?= icon('trash-2', 'w-4 h-4') ?></button></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <div class="om-add"><label class="label" for="om-search">Add a product</label><div class="om-search"><input class="input" id="om-search" type="search" placeholder="Search by name or SKU…" autocomplete="off"><div class="om-results" id="om-results" hidden></div></div></div>
  <template id="om-row-tpl"><tr data-row><td><b data-name></b><div class="text-xs muted font-mono" data-sku></div><span class="badge bg-blue-100 text-blue-800">New</span>
      <input type="hidden" data-n="item_id" value=""><input type="hidden" data-n="product_id"></td>
      <td><input class="input om-sm" type="number" min="1" max="100000" step="1" data-n="qty" value="1" data-f="qty"></td>
      <td><input class="input om-sm text-right" type="number" min="0" step="0.01" data-n="original_price" data-f="orig"></td>
      <td><input class="input om-sm text-right" type="number" min="0" step="0.01" data-n="unit_price" data-f="unit"></td>
      <td class="text-right"><b data-line>₹0.00</b></td>
      <td><button type="button" class="btn btn-danger btn-sm om-icon" data-remove title="Remove product"><?= icon('trash-2', 'w-4 h-4') ?></button></td></tr></template>

  <h4 class="om-h">Discount &amp; charges</h4>
  <div class="om-fields">
    <div><label class="label" for="oe-extra">Additional discount (₹)</label><input class="input" id="oe-extra" type="number" min="0" step="0.01" name="extra_discount" value="<?= e(number_format($extra, 2, '.', '')) ?>"><p class="hint">Extra order-level discount (e.g. bulk). Item discounts come from the per-product prices above.</p></div>
    <div><label class="label" for="oe-del">Delivery charge (₹)</label><input class="input" id="oe-del" type="number" min="0" step="0.01" name="delivery_charge" value="<?= e(number_format((float)$o['delivery_charge'], 2, '.', '')) ?>"></div>
  </div>
  <h4 class="om-h">Payment &amp; status</h4>
  <div class="om-fields">
    <div><label class="label" for="oe-pm">Payment method</label><select class="select" id="oe-pm" name="payment_method"><?php foreach ($methodOpts as $code => $m): ?><option value="<?= e($code) ?>" <?= $o['payment_method'] === $code ? 'selected' : '' ?>><?= e($m['label']) ?></option><?php endforeach; ?></select></div>
    <div><label class="label" for="oe-ps">Payment status</label><select class="select" id="oe-ps" name="payment_status"><?php foreach (['pending', 'paid', 'failed', 'refunded'] as $s): ?><option value="<?= $s ?>" <?= $o['payment_status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div><label class="label" for="oe-st">Order status</label><select class="select" id="oe-st" name="status"><?php foreach (order_statuses() as $k => $l): ?><option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div><label class="label" for="oe-track">Tracking info</label><input class="input" id="oe-track" name="tracking_info" value="<?= e($o['tracking_info']) ?>" maxlength="250" placeholder="Courier / LR number"></div>
    <div class="om-wide"><label class="label" for="oe-int">Internal notes (private)</label><input class="input" id="oe-int" name="internal_notes" value="<?= e($o['internal_notes']) ?>"></div>
  </div>
  <dl class="om-sum" id="om-sum"><div><dt>Subtotal (original price)</dt><dd data-s="sub">₹0.00</dd></div><div class="g"><dt>Item discount</dt><dd data-s="disc">− ₹0.00</dd></div><div class="g"><dt>Additional discount</dt><dd data-s="extra">− ₹0.00</dd></div><div><dt>Delivery charge</dt><dd data-s="del">₹0.00</dd></div><div class="t"><dt>New total</dt><dd data-s="total">₹0.00</dd></div></dl>
  <p class="hint">Stock is adjusted automatically when quantities change, products are added or removed. Setting the status to Cancelled restores stock.</p>
  <div class="om-foot"><button type="button" class="btn btn-outline" data-om-close>Cancel</button><button type="submit" class="btn btn-primary" id="om-save"><?= icon('save', 'w-4 h-4') ?> Save Changes</button></div>
</form>
