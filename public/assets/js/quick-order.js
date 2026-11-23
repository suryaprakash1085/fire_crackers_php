/* Quick Order: every product in one table.
 *  - The number in each row is the TOTAL quantity wanted in the cart. "Add to cart" SETS the cart to those numbers (never adds on top, 0 removes).
 *  - On load the inputs show the real cart quantities; the right-hand "Your order list" and the table are kept in sync.
 *  - After "Add to cart" the cart panel, header badge, table inputs and bottom bar are all redrawn from ONE server response. */
(function () {
  const $ = (id) => document.getElementById(id);
  const body = $('qo-body'); if (!body) return;
  const allRows = [...body.querySelectorAll('tr[data-id]')].sort((a, b) => +a.dataset.order - +b.dataset.order);   // master (normal) order
  const hdrs = {}; body.querySelectorAll('tr.cat-row').forEach((h) => { hdrs[h.dataset.catRow] = h; });
  const synced = new Map();                                      // product id -> quantity that is stored in the cart right now
  const inp = (r) => r.querySelector('input');                    // null for out-of-stock rows (no input, nothing to buy)
  const qOf = (r) => { const i = inp(r); if (!i) return 0; const n = Math.floor(Number(String(i.value).trim())); return Number.isFinite(n) && n > 0 ? n : 0; };
  const cartQty = (r) => synced.get(+r.dataset.id) || 0;
  const clamp = (r, v) => Math.max(0, Math.min(+r.dataset.limit, Math.floor(v) || 0));
  const put = (r, v) => { const i = inp(r); if (i) i.value = String(v); };   // writes a number into the box, exactly as given

  /* ---- minimum-order balance: SAME rule as CartService::summary() -> payable = (selling-price total) - bulk discount; delivery is not counted.
   *  Row prices (data-price) already include the company discount, so it is not applied a second time here. */
  const bar = $('qo-bar'), minBox = $('qo-min'), minTxt = $('qo-min-txt'), minTrack = $('qo-min-track'), minFill = $('qo-min-fill');
  const r2 = (x) => Math.round((Number(x) + Number.EPSILON) * 100) / 100;
  const pos = (v) => { const n = Number(v); return Number.isFinite(n) && n > 0 ? n : 0; };
  const payableOf = (sell) => {
    const bMin = pos(bar.dataset.bulkDiscountMin), bPct = pos(bar.dataset.bulkDiscountPercent);
    return r2(sell - (bMin > 0 && bPct > 0 && sell >= bMin ? r2(sell * bPct / 100) : 0));
  };
  function paintMin(active, min, payable, ok) {
    if (!active || !(min > 0)) { minBox.hidden = true; return; }              // no minimum, or nothing selected: nothing to say
    if (ok === undefined) ok = payable >= min;
    minBox.hidden = false; minBox.classList.toggle('ok', ok); minTrack.hidden = ok;
    minTxt.textContent = ok ? '✓ Minimum order reached' : 'Minimum order ' + FC.money(min) + ' · Add ' + FC.money(r2(min - payable)) + ' more';
    minFill.style.width = Math.max(0, Math.min(100, payable / min * 100)).toFixed(1) + '%';
  }

  function hdr(key, label) {
    let h = hdrs[key];
    if (!h) { h = document.createElement('tr'); h.className = 'cat-row' + (key === '__sel' ? ' sel-row' : ''); h.dataset.catRow = key; const td = document.createElement('td'); td.colSpan = 8; h.appendChild(td); hdrs[key] = h; }
    h.firstElementChild.textContent = label; return h;
  }
  /* Selected rows (quantity > 0) first, then the normal category order. Called on load and when the set of selected products changes. */
  function layout() {
    const picked = allRows.filter((r) => inp(r) && qOf(r) > 0), pset = new Set(picked), frag = document.createDocumentFragment();
    if (picked.length) { frag.append(hdr('__sel', '✓ Selected products (' + picked.length + ')')); picked.forEach((r) => { r.dataset.grp = '__sel'; frag.append(r); }); }
    const cats = []; allRows.forEach((r) => { if (!cats.includes(r.dataset.cat)) cats.push(r.dataset.cat); });
    cats.forEach((c) => {
      const rs = allRows.filter((r) => r.dataset.cat === c && !pset.has(r)); if (!rs.length) return;
      frag.append(hdr(c, c)); rs.forEach((r) => { r.dataset.grp = c; frag.append(r); });
    });
    body.replaceChildren(frag);
  }
  function recalc() {
    let n = 0, units = 0, total = 0, dirty = 0, sell = 0;
    allRows.forEach((r) => {
      const q = qOf(r), p = +r.dataset.price, line = r.querySelector('[data-line]');
      r.classList.toggle('picked', q > 0);
      if (line) line.textContent = q > 0 ? FC.money(q * p) : '—';
      if (q > 0) { n++; units += q; total += q * p; sell += r2(q * p); }
      const d = !!inp(r) && q !== cartQty(r); r.classList.toggle('dirty', d); if (d) dirty++;
    });
    $('qo-n').textContent = n; $('qo-units').textContent = units; $('qo-total').textContent = FC.money(total);
    $('qo-dirty').textContent = dirty ? dirty + (dirty === 1 ? ' change' : ' changes') + ' not in cart yet' : 'matches your cart';
    $('qo-add').disabled = dirty === 0; $('qo-clear').disabled = dirty === 0; $('qo-bar').classList.toggle('show', n > 0 || dirty > 0);
    paintMin(n > 0, pos(bar.dataset.minOrder), payableOf(r2(sell)));
  }
  function filter() {
    const t = $('qo-search').value.trim().toLowerCase(), c = $('qo-cat').value, only = $('qo-only').checked; let vis = 0; const shown = new Set();
    allRows.forEach((r) => {
      const ok = (!t || r.dataset.hay.includes(t)) && (!c || r.dataset.cat === c) && (!only || qOf(r) > 0);
      r.hidden = !ok; if (ok) { vis++; shown.add(r.dataset.grp); }
    });
    Object.keys(hdrs).forEach((k) => { hdrs[k].hidden = !shown.has(k); });
    $('qo-empty').classList.toggle('hidden', vis > 0); $('qo-count').textContent = vis + (vis === 1 ? ' product' : ' products');
  }
  /* The cart changed (panel +/-/delete, Add to cart, restored page): make the table follow. */
  function applyCart(c, force) {
    if (c.min_order !== undefined) bar.dataset.minOrder = String(c.min_order);            // the server's current minimum (admin may have changed it)
    const next = new Map(c.items.map((i) => [+i.id, +i.qty])); let structural = !!force;
    allRows.forEach((r) => {
      const id = +r.dataset.id, before = synced.get(id) || 0, after = next.get(id) || 0;
      if ((before > 0) !== (after > 0)) structural = true;
      if (inp(r) && (force || before !== after)) put(r, after);      // force = redraw EVERY row from the server cart (drafts that were sent are now the cart)
    });
    synced.clear(); next.forEach((q, id) => synced.set(id, q));
    if (structural) layout();
    recalc(); filter();
    // force = every row was just redrawn from this server cart, so its numbers ARE the selection: use the server's meets_min / min_order
    if (force && c.min_order !== undefined) paintMin(c.items.length > 0, pos(c.min_order), r2(c.subtotal - c.discount), c.meets_min);
  }

  body.addEventListener('click', (e) => {
    const b = e.target.closest('[data-qo-step]'); if (!b) return; const r = b.closest('tr'), step = +b.dataset.qoStep, want = qOf(r) + step;
    if (want > +r.dataset.limit) FC.toast('Only ' + r.dataset.limit + ' available for this product.', 'info');
    put(r, clamp(r, want)); recalc(); if ($('qo-only').checked) filter();
  });
  body.addEventListener('input', (e) => {
    if (!e.target.matches('input')) return; const r = e.target.closest('tr'), lim = +r.dataset.limit;
    if (+e.target.value > lim) { e.target.value = lim; FC.toast('Only ' + lim + ' available for this product.', 'info'); }
    recalc();
  });
  body.addEventListener('change', (e) => {                             // leaving the box: clean value (whole number, 0..stock) written back
    if (!e.target.matches('input')) return; const r = e.target.closest('tr'); put(r, clamp(r, Number(e.target.value))); recalc(); if ($('qo-only').checked) filter();
  });
  body.addEventListener('keydown', (e) => {
    if (!e.target.matches('input')) return;
    if (['e', 'E', '+', '-', '.', ','].includes(e.key)) { e.preventDefault(); return; }
    if (e.key !== 'Enter') return; e.preventDefault();
    let r = e.target.closest('tr').nextElementSibling; while (r && (r.hidden || !r.querySelector('input'))) r = r.nextElementSibling; if (r) r.querySelector('input').focus();
  });
  body.addEventListener('focusin', (e) => { if (e.target.matches('input')) e.target.select(); });
  ['input', 'change'].forEach((ev) => { $('qo-search').addEventListener(ev, filter); $('qo-cat').addEventListener(ev, filter); });
  $('qo-only').addEventListener('change', () => { if ($('qo-only').checked) layout(); filter(); });
  $('qo-tabs').addEventListener('click', (e) => { const b = e.target.closest('[data-tab]'); if (!b) return; $('qo-cat').value = b.dataset.tab; [...$('qo-tabs').children].forEach((x) => x.classList.toggle('active', x === b)); filter(); });
  $('qo-cat').addEventListener('change', () => { [...$('qo-tabs').children].forEach((x) => x.classList.toggle('active', x.dataset.tab === $('qo-cat').value)); });
  $('qo-clear').addEventListener('click', () => { allRows.forEach((r) => put(r, cartQty(r))); layout(); recalc(); filter(); });   // undo: back to what the cart holds

  $('qo-add').addEventListener('click', async () => {
    const btn = $('qo-add'); if (btn.dataset.busy) return;
    // every row that matters: wanted quantity > 0, or something is in the cart for it (then 0 means "remove"). The numbers sent are exactly the numbers shown.
    const lines = allRows.filter((r) => inp(r) && (qOf(r) > 0 || cartQty(r) > 0)).map((r) => ({ id: +r.dataset.id, qty: qOf(r) }));
    if (!lines.length) return;
    btn.dataset.busy = 1; btn.disabled = true; const old = btn.innerHTML; btn.innerHTML = '<span class="spinner"></span> Updating…';
    const j = await FC.api('/api/cart/set-many', { items: JSON.stringify(lines), source: 'quick_order' });
    delete btn.dataset.busy; btn.innerHTML = old;
    if (!j.cart) { recalc(); FC.toast(j.message || 'Could not update the cart. Please try again.', 'error'); return; }   // nothing changed: the typed quantities stay
    applyCart(j.cart, true);                                          // ONE response -> table inputs, bottom bar and (via fc:cart) the cart panel + header badge
    const bad = (j.results || []).filter((x) => ['reduced', 'out_of_stock', 'unavailable', 'invalid'].includes(x.status));
    if (j.changed_count) { FC.toast('Cart updated — ' + j.changed_count + ' product' + (j.changed_count === 1 ? '' : 's') + ' changed.', 'success'); window.FW && FW.burstAt(btn, { count: 70, power: 4.2 }); window.FW && FW.celebrate && FW.celebrate(2); }
    else if (!bad.length) FC.toast('Your cart already matches the table.', 'info');
    bad.forEach((x) => FC.toast((x.name ? x.name + ': ' : '') + x.message, x.status === 'reduced' ? 'info' : 'error'));
  });

  /* bulk paste -> SETS the quantity of each product in the table (review, then press Add to cart) */
  $('qo-bulk-btn').addEventListener('click', () => {
    const lines = $('qo-bulk').value.split(/\n+/).map((l) => l.trim()).filter(Boolean); if (!lines.length) return; const out = []; let ok = 0;
    for (const line of lines) {
      const m = line.match(/^(.+?)[\s,:x×*=-]+(\d{1,4})$/i) || [null, line, '1']; const term = m[1].trim().toLowerCase(), qty = +m[2];
      const r = allRows.find((x) => x.dataset.sku === term) || allRows.find((x) => x.dataset.hay.includes(term) && allRows.filter((y) => y.dataset.hay.includes(term)).length === 1);
      if (!r || !inp(r)) { out.push('✗ ' + line + ' — not found or out of stock'); continue; }
      put(r, clamp(r, qty)); out.push('✓ ' + line); ok++;
    }
    $('qo-bulk-result').innerHTML = out.map((l) => `<div class="${l[0] === '✓' ? 'text-green-700' : 'text-red-700'}">${FC.esc(l)}</div>`).join('');
    if (ok) { layout(); recalc(); filter(); FC.toast(ok + ' line(s) filled in the table — review and press “Add to cart”.', 'success'); $('qo-bulk').value = ''; body.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  });

  /* start: the page was rendered from the real cart, so the inputs already show the cart quantities */
  const init = window.__QO_CART; if (init && Array.isArray(init.items)) init.items.forEach((i) => synced.set(+i.id, +i.qty));
  document.addEventListener('fc:cart', (e) => applyCart(e.detail, false));            // right-hand panel (-, +, delete) -> table input of the same product, live
  layout(); recalc(); filter();
  const paint = () => { if (init && init.items && window.FC && FC.setCart) FC.setCart(init); };   // draw the panel at once instead of waiting for a request
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', paint); else paint();
  window.addEventListener('pageshow', (e) => { if (e.persisted) FC.api('/api/cart').then((j) => { if (j.cart) applyCart(j.cart, true); }); });   // back/forward: never show an old cart
})();
