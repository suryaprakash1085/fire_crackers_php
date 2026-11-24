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

  /* ---- AUTO SYNC: no "Add to cart" button. Any quantity change (typing, + / -, x, Clear all) is saved to the shared server cart a moment later.
   *  The header cart icon + badge, the cart popup and the "Your order list" panel all follow through FC.setCart -> 'fc:cart'. */
  let syncTimer = null, inflight = false, sentNow = null, again = false;
  const schedule = () => { clearTimeout(syncTimer); syncTimer = setTimeout(flush, 400); };
  const changedLines = () => allRows.filter((r) => inp(r) && qOf(r) !== cartQty(r)).map((r) => ({ id: +r.dataset.id, qty: qOf(r) }));
  async function flush() {
    clearTimeout(syncTimer); syncTimer = null;
    if (inflight) { again = true; return; }
    const lines = changedLines(); if (!lines.length) return;
    inflight = true; sentNow = new Map(lines.map((l) => [l.id, l.qty]));
    const j = await FC.api('/api/cart/set-many', { items: JSON.stringify(lines), source: 'quick_order' });   // FC.api -> FC.setCart -> fc:cart -> applyCart() below
    const sent = sentNow; inflight = false; sentNow = null;
    if (!j.cart) { FC.toast(j.message || 'Could not update the cart. Please try again.', 'error'); recalc(); return; }
    // a line the server refused (box still shows what was sent, cart holds something else): show the real cart quantity
    allRows.forEach((r) => { const id = +r.dataset.id; if (sent.has(id) && qOf(r) === sent.get(id) && qOf(r) !== cartQty(r)) put(r, cartQty(r)); });
    recalc(); filter();
    (j.results || []).filter((x) => ['reduced', 'out_of_stock', 'unavailable', 'invalid'].includes(x.status)).forEach((x) => FC.toast((x.name ? x.name + ': ' : '') + x.message, x.status === 'reduced' ? 'info' : 'error'));
    if (again || changedLines().length) { again = false; schedule(); }   // the shopper typed more while the request was running
  }
  const undoBtn = $('qo-clear'); if (undoBtn) undoBtn.style.display = 'none';          // no manual undo any more
  { const v = $('qo-add'); v.setAttribute('data-cart-open', ''); v.innerHTML = '<span class="qo-vc-d"><i data-lucide="shopping-cart" class="w-4 h-4"></i> View cart</span><i data-lucide="chevron-right" class="qo-vc-m"></i>'; v.setAttribute('aria-label', 'View cart'); FC.icons && FC.icons(); }   // the old "Add to cart" button now opens the cart popup

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
    if (ok) minTxt.textContent = 'Minimum order reached';
    else minTxt.innerHTML = '<span class="m1">Minimum order</span> <span class="m2">' + FC.money(min) + '</span><span class="m-sep"> · </span><span class="m3">Add ' + FC.money(r2(min - payable)) + ' more</span>';
    minFill.style.width = Math.max(0, Math.min(100, payable / min * 100)).toFixed(1) + '%';
  }

  function hdr(key, label) {
    let h = hdrs[key];
    if (!h) {
      h = document.createElement('tr'); h.className = 'cat-row' + (key === '__sel' ? ' sel-row' : ''); h.dataset.catRow = key;
      const td = document.createElement('td'); td.colSpan = 5; const gt = document.createElement('span'); gt.className = 'gt'; td.appendChild(gt);
      if (key === '__sel') { const b = document.createElement('button'); b.type = 'button'; b.className = 'qo-clearall'; b.dataset.qoClearall = ''; b.textContent = 'Clear all'; td.appendChild(b); }
      else { td.setAttribute('role', 'button'); td.tabIndex = 0; td.setAttribute('aria-expanded', 'true'); }
      h.appendChild(td); hdrs[key] = h;
    }
    h.querySelector('.gt').textContent = label; return h;
  }
  /* Selected rows (quantity > 0) first, then the normal category order. Called on load and when the set of selected products changes. */
  function layout() {
    const picked = allRows.filter((r) => inp(r) && qOf(r) > 0), pset = new Set(picked), frag = document.createDocumentFragment();
    if (picked.length) { frag.append(hdr('__sel', 'Selected Products (' + picked.length + ')')); picked.forEach((r) => { r.dataset.grp = '__sel'; frag.append(r); }); }
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
      const lim = +r.dataset.limit; r.querySelectorAll('[data-qo-step]').forEach((b) => { b.disabled = +b.dataset.qoStep < 0 ? q <= 0 : q >= lim; });   // − off at 0, + off at the available stock
    });
    $('qo-n').textContent = n; $('qo-units').textContent = units; $('qo-total').textContent = FC.money(total);
    $('qo-dirty').textContent = dirty ? 'saving to cart…' : 'saved in your cart';
    $('qo-add').disabled = false; $('qo-clear').disabled = dirty === 0; $('qo-bar').classList.toggle('show', n > 0 || dirty > 0);
    paintMin(n > 0, pos(bar.dataset.minOrder), payableOf(r2(sell)));
  }
  function filter() {
    const t = $('qo-search').value.trim().toLowerCase(), c = $('qo-cat').value, only = $('qo-only').checked; let vis = 0; const shown = new Set();
    allRows.forEach((r) => {
      const ok = (!t || r.dataset.hay.includes(t)) && (!c || r.dataset.cat === c) && (!only || qOf(r) > 0);
      const folded = !t && hdrs[r.dataset.grp] && hdrs[r.dataset.grp].classList.contains('collapsed');   // collapsed group (ignored while searching)
      r.hidden = !ok || folded; if (ok) { vis++; shown.add(r.dataset.grp); }
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
      if (inp(r) && (force || before !== after) && !(sentNow && sentNow.has(id) && qOf(r) !== sentNow.get(id))) put(r, after);   // keep what was typed after the request left      // force = redraw EVERY row from the server cart (drafts that were sent are now the cart)
    });
    synced.clear(); next.forEach((q, id) => synced.set(id, q));
    if (force && structural) layout();   // rows are not re-ordered while shopping (they would jump under the cursor)
    recalc(); filter();
    // force = every row was just redrawn from this server cart, so its numbers ARE the selection: use the server's meets_min / min_order
    if (force && c.min_order !== undefined) paintMin(c.items.length > 0, pos(c.min_order), r2(c.subtotal - c.discount), c.meets_min);
  }

  body.addEventListener('click', (e) => {
    const b = e.target.closest('[data-qo-step]'); if (!b) return; const r = b.closest('tr'), step = +b.dataset.qoStep, want = qOf(r) + step;
    if (want > +r.dataset.limit) FC.toast('Only ' + r.dataset.limit + ' available for this product.', 'info');
    put(r, clamp(r, want)); recalc(); if ($('qo-only').checked) filter(); schedule();
  });
  body.addEventListener('input', (e) => {
    if (!e.target.matches('input')) return; const r = e.target.closest('tr'), lim = +r.dataset.limit;
    if (+e.target.value > lim) { e.target.value = lim; FC.toast('Only ' + lim + ' available for this product.', 'info'); }
    recalc(); schedule();
  });
  body.addEventListener('change', (e) => {                             // leaving the box: clean value (whole number, 0..stock) written back
    if (!e.target.matches('input')) return; const r = e.target.closest('tr'); put(r, clamp(r, Number(e.target.value))); recalc(); if ($('qo-only').checked) filter(); schedule();
  });
  body.addEventListener('keydown', (e) => {
    if (!e.target.matches('input')) return;
    if (['e', 'E', '+', '-', '.', ','].includes(e.key)) { e.preventDefault(); return; }
    if (e.key !== 'Enter') return; e.preventDefault();
    let r = e.target.closest('tr').nextElementSibling; while (r && (r.hidden || !r.querySelector('input'))) r = r.nextElementSibling; if (r) r.querySelector('input').focus();
  });
  body.addEventListener('focusin', (e) => { if (e.target.matches('input')) e.target.select(); });
  ['input', 'change'].forEach((ev) => { $('qo-search').addEventListener(ev, filter); $('qo-cat').addEventListener(ev, filter); });
  /* ---- group headers (collapse / expand), Clear all, remove a row ---- */
  const toggleGroup = (h) => { const c = h.classList.toggle('collapsed'); const td = h.firstElementChild; if (td.hasAttribute('aria-expanded')) td.setAttribute('aria-expanded', String(!c)); filter(); };
  body.addEventListener('click', (e) => {
    if (e.target.closest('[data-qo-clearall]')) { allRows.forEach((r) => put(r, 0)); recalc(); if ($('qo-only').checked) filter(); schedule(); return; }
    const rm = e.target.closest('[data-qo-remove]'); if (rm) { put(rm.closest('tr'), 0); recalc(); if ($('qo-only').checked) filter(); schedule(); return; }
    const h = e.target.closest('tr.cat-row'); if (h && !h.classList.contains('sel-row')) toggleGroup(h);
  });
  body.addEventListener('keydown', (e) => { if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('tr.cat-row:not(.sel-row) td')) { e.preventDefault(); toggleGroup(e.target.parentElement); } });

  /* ---- category pills: one row, arrows + edge fade show that more can be scrolled ---- */
  const tabs = $('qo-tabs'), tabsWrap = $('qo-tabswrap'), tPrev = tabsWrap.querySelector('.qo-prev'), tNext = tabsWrap.querySelector('.qo-next');
  const tabsState = () => { const max = tabs.scrollWidth - tabs.clientWidth; tPrev.hidden = tabs.scrollLeft < 4; tNext.hidden = max < 4 || tabs.scrollLeft > max - 4; tabsWrap.classList.toggle('fade-l', !tPrev.hidden); tabsWrap.classList.toggle('fade-r', !tNext.hidden); };
  tabs.addEventListener('scroll', tabsState, { passive: true }); window.addEventListener('resize', tabsState); window.addEventListener('load', tabsState); tabsState();
  tPrev.addEventListener('click', () => tabs.scrollBy({ left: -tabs.clientWidth * 0.7, behavior: 'smooth' }));
  tNext.addEventListener('click', () => tabs.scrollBy({ left: tabs.clientWidth * 0.7, behavior: 'smooth' }));
  if (window.matchMedia && matchMedia('(min-width:1024px)').matches) $('qo-bulk-box').open = true;   // bulk box starts open on laptops / desktops

  /* design helpers: clear-search button, keep the chosen category pill in view */
  const sBox = $('qo-search'), sClr = $('qo-search-clear');
  const sSync = () => { sClr.hidden = !sBox.value; };
  sBox.addEventListener('input', sSync); sSync();
  sClr.addEventListener('click', () => { sBox.value = ''; sSync(); sBox.dispatchEvent(new Event('input', { bubbles: true })); sBox.focus(); });
  $('qo-tabs').addEventListener('click', (e) => { const b = e.target.closest('[data-tab]'); if (b && b.scrollIntoView) b.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' }); });
  $('qo-only').addEventListener('change', () => { if ($('qo-only').checked) layout(); filter(); });
  $('qo-tabs').addEventListener('click', (e) => { const b = e.target.closest('[data-tab]'); if (!b) return; $('qo-cat').value = b.dataset.tab; [...$('qo-tabs').children].forEach((x) => x.classList.toggle('active', x === b)); filter(); });
  $('qo-cat').addEventListener('change', () => { [...$('qo-tabs').children].forEach((x) => x.classList.toggle('active', x.dataset.tab === $('qo-cat').value)); });
  $('qo-clear').addEventListener('click', () => { allRows.forEach((r) => put(r, cartQty(r))); layout(); recalc(); filter(); });   // undo: back to what the cart holds

  $('qo-add').addEventListener('click', async () => {
    const btn = $('qo-add'); if (btn.hasAttribute('data-cart-open') || btn.dataset.busy) return;
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
    if (ok) { layout(); recalc(); filter(); schedule(); FC.toast(ok + ' line(s) added to your cart.', 'success'); $('qo-bulk').value = ''; body.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  });

  /* start: the page was rendered from the real cart, so the inputs already show the cart quantities */
  const init = window.__QO_CART; if (init && Array.isArray(init.items)) init.items.forEach((i) => synced.set(+i.id, +i.qty));
  document.addEventListener('fc:cart', (e) => applyCart(e.detail, false));            // right-hand panel (-, +, delete) -> table input of the same product, live
  layout(); recalc(); filter();
  window.addEventListener('pagehide', () => {   // leaving (e.g. Checkout) before the 0.4 s timer fired: still save the last change
    const lines = changedLines(); if (!lines.length || !navigator.sendBeacon) return;
    navigator.sendBeacon(FC.url('/api/cart/set-many'), new URLSearchParams({ items: JSON.stringify(lines), source: 'quick_order', _csrf: document.querySelector('meta[name=csrf]').content }));
  });
  const paint = () => { if (init && init.items && window.FC && FC.setCart) FC.setCart(init); };   // draw the panel at once instead of waiting for a request
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', paint); else paint();
  window.addEventListener('pageshow', (e) => { if (e.persisted) FC.api('/api/cart').then((j) => { if (j.cart) applyCart(j.cart, true); }); });   // back/forward: never show an old cart
})();
