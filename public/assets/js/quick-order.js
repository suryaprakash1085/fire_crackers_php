/* Quick Order: every product in one table → pick quantities → add all to the shared cart in one request. */
(function () {
  const $ = (id) => document.getElementById(id);
  const body = $('qo-body'); if (!body) return;
  const rows = [...body.querySelectorAll('tr[data-id]')], catRows = [...body.querySelectorAll('tr.cat-row')];
  const qOf = (r) => { const i = r.querySelector('input'); return i ? Math.max(0, parseInt(i.value || '0', 10) || 0) : 0; };

  function recalc() {
    let n = 0, units = 0, total = 0;
    rows.forEach((r) => {
      const q = qOf(r), p = +r.dataset.price, line = r.querySelector('[data-line]');
      r.classList.toggle('picked', q > 0);
      if (line) line.textContent = q > 0 ? FC.money(q * p) : '—';
      if (q > 0) { n++; units += q; total += q * p; }
    });
    $('qo-n').textContent = n; $('qo-units').textContent = units; $('qo-total').textContent = FC.money(total);
    $('qo-add').disabled = n === 0; $('qo-clear').disabled = n === 0; $('qo-bar').classList.toggle('show', n > 0);
  }
  function filter() {
    const t = $('qo-search').value.trim().toLowerCase(), c = $('qo-cat').value, only = $('qo-only').checked; let vis = 0; const shown = new Set();
    rows.forEach((r) => { const ok = (!t || r.dataset.hay.includes(t)) && (!c || r.dataset.cat === c) && (!only || qOf(r) > 0); r.hidden = !ok; if (ok) { vis++; shown.add(r.dataset.cat); } });
    catRows.forEach((r) => { r.hidden = !shown.has(r.dataset.catRow); });
    $('qo-empty').classList.toggle('hidden', vis > 0); $('qo-count').textContent = vis + (vis === 1 ? ' product' : ' products');
  }
  function setQty(r, v) { const i = r.querySelector('input'); if (!i) return; const lim = +r.dataset.limit; v = Math.max(0, Math.min(lim, v)); i.value = v > 0 ? v : ''; }

  body.addEventListener('click', (e) => {
    const b = e.target.closest('[data-qo-step]'); if (!b) return; const r = b.closest('tr'); setQty(r, qOf(r) + +b.dataset.qoStep); recalc(); if ($('qo-only').checked) filter();
  });
  body.addEventListener('input', (e) => { if (e.target.matches('input')) { const r = e.target.closest('tr'); const lim = +r.dataset.limit; if (+e.target.value > lim) { e.target.value = lim; FC.toast('Only ' + lim + ' available for this product.', 'info'); } recalc(); } });
  body.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' || !e.target.matches('input')) return; e.preventDefault();
    let r = e.target.closest('tr').nextElementSibling; while (r && (r.hidden || !r.querySelector('input'))) r = r.nextElementSibling; if (r) r.querySelector('input').focus();
  });
  body.addEventListener('focusin', (e) => { if (e.target.matches('input')) e.target.select(); });
  ['input', 'change'].forEach((ev) => { $('qo-search').addEventListener(ev, filter); $('qo-cat').addEventListener(ev, filter); });
  $('qo-only').addEventListener('change', filter);
  $('qo-tabs').addEventListener('click', (e) => { const b = e.target.closest('[data-tab]'); if (!b) return; $('qo-cat').value = b.dataset.tab; [...$('qo-tabs').children].forEach((x) => x.classList.toggle('active', x === b)); filter(); });
  $('qo-cat').addEventListener('change', () => { [...$('qo-tabs').children].forEach((x) => x.classList.toggle('active', x.dataset.tab === $('qo-cat').value)); });
  $('qo-clear').addEventListener('click', () => { rows.forEach((r) => setQty(r, 0)); recalc(); filter(); });

  $('qo-add').addEventListener('click', async () => {
    const items = rows.map((r) => ({ id: +r.dataset.id, qty: qOf(r) })).filter((x) => x.qty > 0); if (!items.length) return;
    const btn = $('qo-add'), old = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Adding…';
    const j = await FC.api('/api/cart/add-many', { items: JSON.stringify(items), source: 'quick_order' });
    btn.innerHTML = old; btn.disabled = false;
    if (!j.results) { FC.toast(j.message || 'Could not add items. Please try again.', 'error'); return; }
    const bad = j.results.filter((x) => x.status !== 'added');
    // clear rows that were added; keep rows with problems so the customer can adjust
    j.results.forEach((x) => { if (x.added > 0) { const r = body.querySelector('tr[data-id="' + x.id + '"]'); if (r) setQty(r, 0); } });
    recalc(); filter();
    if (j.added_count) { FC.toast(j.added_count + ' product(s) added to your cart', 'success'); window.FW && FW.burstAt(btn, { count: 70, power: 4.2 }); FW.celebrate && FW.celebrate(2); }
    bad.forEach((x) => FC.toast(x.message, x.added > 0 ? 'info' : 'error'));
  });

  /* bulk paste → fills the table so the customer can review */
  $('qo-bulk-btn').addEventListener('click', () => {
    const lines = $('qo-bulk').value.split(/\n+/).map((l) => l.trim()).filter(Boolean); if (!lines.length) return; const out = []; let ok = 0;
    for (const line of lines) {
      const m = line.match(/^(.+?)[\s,:x×*=-]+(\d{1,4})$/i) || [null, line, '1']; const term = m[1].trim().toLowerCase(), qty = +m[2];
      let r = rows.find((x) => x.dataset.sku === term) || rows.find((x) => x.dataset.hay.includes(term) && rows.filter((y) => y.dataset.hay.includes(term)).length === 1);
      if (!r || !r.querySelector('input')) { out.push('✗ ' + line + ' — not found or out of stock'); continue; }
      setQty(r, qOf(r) + qty); out.push('✓ ' + line); ok++;
    }
    $('qo-bulk-result').innerHTML = out.map((l) => `<div class="${l[0] === '✓' ? 'text-green-700' : 'text-red-700'}">${FC.esc(l)}</div>`).join('');
    recalc(); if (ok) { FC.toast(ok + ' line(s) filled in the table — review and press “Add selected to cart”.', 'success'); $('qo-bulk').value = ''; document.getElementById('qo-body').scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  });
  recalc();
})();
