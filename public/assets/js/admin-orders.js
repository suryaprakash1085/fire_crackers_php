/* Admin → Orders: View / Edit / PDF preview / Cancel popups. Plain JS, no dependencies. */
(function () {
  const csrf = document.querySelector('meta[name=csrf]').content, base = document.querySelector('meta[name=base]').content;
  const U = (p) => base + p;
  const $ = (s, r) => (r || document).querySelector(s), $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
  const modal = $('#om-modal'), body = $('#om-body'), title = $('#om-title'), headActs = $('#om-head-actions'), dialog = $('.om-dialog', modal);
  const conf = $('#om-confirm'), toastEl = $('#om-toast');
  if (!modal) return;
  let lastFocus = null, lastAct = null, dirty = false, toastT = 0;
  const fmt = (n) => '₹' + (Math.round((+n || 0) * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const icons = () => window.lucide && lucide.createIcons();
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function toast(msg, kind) { toastEl.textContent = msg; toastEl.className = 'om-toast show ' + (kind || ''); clearTimeout(toastT); toastT = setTimeout(() => toastEl.classList.remove('show'), 3600); }

  function openModal(t, size) {
    lastFocus = document.activeElement; dirty = false;
    title.textContent = t; headActs.innerHTML = ''; body.innerHTML = '<div class="om-loading"><span class="spinner"></span><p>Loading…</p></div>';
    dialog.className = 'om-dialog' + (size ? ' om-' + size : '');
    modal.hidden = false; document.body.style.overflow = 'hidden'; $('.om-x', modal).focus();
  }
  function closeModal(force) {
    if (!force && dirty && !confirm('You have unsaved changes. Close without saving?')) return;
    modal.hidden = true; document.body.style.overflow = ''; body.innerHTML = ''; dirty = false;
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  async function load(url) {
    try {
      const r = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (r.status === 401 || r.redirected) throw new Error('Your session expired. Please reload the page and sign in again.');
      if (!r.ok) throw new Error('Could not load (HTTP ' + r.status + ').');
      body.innerHTML = await r.text(); icons(); return true;
    } catch (e) {
      body.innerHTML = '<div class="om-empty"><p>' + esc(e.message || 'Network error.') + '</p><button type="button" class="btn btn-outline btn-sm" data-om-retry>Try again</button></div>'; return false;
    }
  }

  /* ---------- actions ---------- */
  const actions = {
    async view(id, ref) {
      openModal('Order ' + ref, 'md');
      headActs.innerHTML = '<a class="btn btn-soft btn-sm" href="' + U('/admin/orders/' + id) + '">Open full page</a>';
      await load(U('/admin/orders/' + id + '/modal'));
    },
    async edit(id, ref) {
      openModal('Edit order ' + ref); if (await load(U('/admin/orders/' + id + '/edit'))) initEdit();
    },
    async pdf(id, ref) {
      openModal('Invoice preview · ' + ref, 'md');
      headActs.innerHTML = '<button type="button" class="btn btn-outline btn-sm" data-om-print>Print</button><a class="btn btn-primary btn-sm" href="' + U('/admin/orders/' + id + '/pdf') + '" data-om-dl>Download PDF</a>';
      await load(U('/admin/orders/' + id + '/invoice'));
    },
    cancel(id, ref, btn) { askCancel(id, ref, btn); },
  };

  document.addEventListener('click', (e) => {
    const a = e.target.closest('.om-act[data-om]');
    if (a && !a.disabled) { e.preventDefault(); lastAct = a; actions[a.dataset.om](a.dataset.id, a.dataset.ref, a); return; }
    if (e.target.closest('[data-om-close]')) { closeModal(); return; }
    if (e.target.closest('[data-om-print]')) { window.print(); return; }
    if (e.target.closest('[data-om-dl]')) { toast('Preparing your PDF download…', 'ok'); return; }
    if (e.target.closest('[data-om-retry]') && lastAct) actions[lastAct.dataset.om](lastAct.dataset.id, lastAct.dataset.ref, lastAct);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (!conf.hidden) { closeConfirm(); return; }
    if (!modal.hidden) closeModal();
  });

  /* ---------- cancel order ---------- */
  let cancelCtx = null;
  function askCancel(id, ref, btn) {
    cancelCtx = { id, ref, btn }; lastFocus = btn;
    $('#om-confirm-ref').textContent = ref; $('#om-cancel-reason').value = ''; $('#om-confirm-err').hidden = true;
    conf.hidden = false; $('#om-keep').focus();
  }
  function closeConfirm() { conf.hidden = true; cancelCtx = null; if (lastFocus && lastFocus.focus) lastFocus.focus(); }
  $('#om-keep').addEventListener('click', closeConfirm);
  $('.om-backdrop', conf).addEventListener('click', closeConfirm);
  $('#om-do-cancel').addEventListener('click', async () => {
    if (!cancelCtx) return; const b = $('#om-do-cancel'), { id, ref } = cancelCtx; b.disabled = true; b.textContent = 'Cancelling…';
    try {
      const fd = new FormData(); fd.append('_csrf', csrf); fd.append('reason', $('#om-cancel-reason').value);
      const r = await fetch(U('/admin/orders/' + id + '/cancel'), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' } });
      const j = await r.json().catch(() => ({ ok: false, message: 'Unexpected server response.' }));
      if (!j.ok) { const er = $('#om-confirm-err'); er.textContent = j.message || 'Could not cancel the order.'; er.hidden = false; return; }
      const tr = document.querySelector('tr[data-order="' + id + '"]');
      if (tr) { const st = $('.js-status', tr); if (st) st.innerHTML = j.status_html; tr.dataset.status = 'cancelled'; $$('.om-act[data-om=edit],.om-act[data-om=cancel]', tr).forEach((x) => { x.disabled = true; x.title = 'Order is cancelled'; }); }
      conf.hidden = true; cancelCtx = null; toast('Order ' + ref + ' cancelled. ' + (j.message || ''), 'ok');
    } catch (e) { const er = $('#om-confirm-err'); er.textContent = 'Network error. Please try again.'; er.hidden = false; }
    finally { b.disabled = false; b.textContent = 'Yes, cancel order'; }
  });

  /* ---------- edit form ---------- */
  function initEdit() {
    const f = $('#om-edit-form'); if (!f) return;
    const tbody = $('#om-items tbody'), err = $('#om-err'); let seq = 1000;
    const rows = () => $$('tr[data-row]', tbody);
    function recalc() {
      let sub = 0, sell = 0;
      rows().forEach((r) => {
        const q = Math.max(0, parseInt($('[data-f=qty]', r).value) || 0), o = +$('[data-f=orig]', r).value || 0, u = +$('[data-f=unit]', r).value || 0, line = Math.round(u * q * 100) / 100;
        $('[data-line]', r).textContent = fmt(line); sub += o * q; sell += line;
        $('[data-f=unit]', r).classList.toggle('has-error', u > o + 1e-9);
      });
      const extra = Math.min(Math.max(+$('[name=extra_discount]', f).value || 0, 0), sell), del = Math.max(+$('[name=delivery_charge]', f).value || 0, 0);
      const set = (k, v) => { $('[data-s=' + k + ']', f).textContent = v; };
      set('sub', fmt(sub)); set('disc', '− ' + fmt(Math.max(0, sub - sell))); set('extra', '− ' + fmt(extra)); set('del', fmt(del)); set('total', fmt(sell - extra + del));
    }
    f.addEventListener('input', (e) => { dirty = true; if (e.target.id !== 'om-search') recalc(); });
    f.addEventListener('change', () => { dirty = true; });
    tbody.addEventListener('click', (e) => {
      const b = e.target.closest('[data-remove]'); if (!b) return;
      if (rows().length <= 1) { showErr('An order needs at least one product. Add another product before removing this one.'); return; }
      if (!confirm('Remove this product from the order? Its stock will be returned when you save.')) return;
      b.closest('tr').remove(); dirty = true; recalc();
    });
    // product search / add
    const q = $('#om-search'), res = $('#om-results'); let t = 0;
    q.addEventListener('input', () => {
      clearTimeout(t); const v = q.value.trim(); if (!v) { res.hidden = true; return; }
      t = setTimeout(async () => {
        try {
          const j = await (await fetch(U('/admin/orders/product-search?q=' + encodeURIComponent(v)), { credentials: 'same-origin' })).json();
          res.innerHTML = (j.products || []).map((p) => '<button type="button" data-p=\'' + esc(JSON.stringify(p)) + '\'><span><b>' + esc(p.name) + '</b><small>' + esc(p.sku) + ' · stock ' + p.stock + '</small></span><span>' + fmt(p.price) + '</span></button>').join('') || '<div class="om-empty" style="padding:.8rem">No matching products.</div>';
          res.hidden = false;
        } catch (e) { res.innerHTML = '<div class="om-empty" style="padding:.8rem">Search failed.</div>'; res.hidden = false; }
      }, 250);
    });
    res.addEventListener('click', (e) => {
      const b = e.target.closest('[data-p]'); if (!b) return; const p = JSON.parse(b.dataset.p);
      const ex = rows().find((r) => $('[data-n=product_id]', r) ? $('[data-n=product_id]', r).value == p.id : $('input[name$="[product_id]"]', r).value == p.id);
      if (ex) { const qi = $('[data-f=qty]', ex); qi.value = (+qi.value || 0) + 1; toast('Already in the order — quantity increased.', 'ok'); }
      else {
        const tr = $('#om-row-tpl').content.firstElementChild.cloneNode(true), k = seq++;
        $('[data-name]', tr).textContent = p.name; $('[data-sku]', tr).textContent = p.sku;
        $$('[data-n]', tr).forEach((i) => { i.name = 'items[' + k + '][' + i.dataset.n + ']'; });
        $('[data-n=product_id]', tr).value = p.id; $('[data-n=original_price]', tr).value = (+p.original).toFixed(2); $('[data-n=unit_price]', tr).value = (+p.price).toFixed(2);
        tbody.appendChild(tr); icons();
      }
      res.hidden = true; q.value = ''; dirty = true; recalc();
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('.om-search')) res.hidden = true; });
    function showErr(m) { err.textContent = m; err.hidden = false; err.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
    f.addEventListener('submit', async (e) => {
      e.preventDefault(); err.hidden = true;
      if (!f.checkValidity()) { f.reportValidity(); return; }
      const bad = rows().find((r) => (+$('[data-f=unit]', r).value || 0) > (+$('[data-f=orig]', r).value || 0) + 1e-9);
      if (bad) { showErr('Final price cannot be more than the original price.'); return; }
      const sv = $('#om-save'); sv.disabled = true; const old = sv.innerHTML; sv.textContent = 'Saving…';
      try {
        const fd = new FormData(f); fd.append('_csrf', csrf);
        const r = await fetch(f.dataset.save, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' } });
        const j = await r.json().catch(() => ({ ok: false, message: 'Unexpected server response.' }));
        if (!j.ok) { showErr(j.message || 'Could not save.'); sv.disabled = false; sv.innerHTML = old; icons(); return; }
        dirty = false; location.reload();
      } catch (x) { showErr('Network error. Please try again.'); sv.disabled = false; sv.innerHTML = old; icons(); }
    });
    recalc();
  }
})();
