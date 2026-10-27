/* Shared storefront JS: cart API, toasts, carousel, cart panels. No dependencies except Lucide icons. */
(function () {
  const FC = (window.FC = {});
  const csrf = () => document.querySelector('meta[name=csrf]').content;
  const base = document.querySelector('meta[name=base]').content;
  FC.url = (p) => base + p;
  FC.esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  FC.money = (n) => '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  FC.icons = () => window.lucide && window.lucide.createIcons();

  FC.toast = (msg, type = 'info') => {
    let w = document.querySelector('.toast-wrap');
    if (!w) { w = document.createElement('div'); w.className = 'toast-wrap'; w.setAttribute('role', 'status'); w.setAttribute('aria-live', 'polite'); document.body.appendChild(w); }
    const t = document.createElement('div'); t.className = 'toast ' + type; t.textContent = msg; w.appendChild(t);
    setTimeout(() => { t.style.opacity = 0; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); }, 3800);
  };

  FC.api = async (path, data = null) => {
    const opt = { headers: { 'X-CSRF-Token': csrf(), Accept: 'application/json' }, credentials: 'same-origin' };
    if (data) { opt.method = 'POST'; const body = new URLSearchParams(data); if (!body.has('_csrf')) body.set('_csrf', csrf()); opt.body = body; }
    let r, j;
    try { r = await fetch(FC.url(path), opt); j = await r.json(); } catch (e) { return { ok: false, message: 'Network error. Please try again.' }; }
    if (j.cart) FC.setCart(j.cart);
    return j;
  };
  FC.cart = null;
  FC.setCart = (c) => {
    const prev = FC.cart ? FC.cart.count : null; FC.cart = c;
    document.querySelectorAll('[data-cart-badge]').forEach((b) => { b.textContent = c.count; b.classList.toggle('hidden', !c.count); if (prev !== null && prev !== c.count) { b.classList.remove('bump'); void b.offsetWidth; b.classList.add('bump'); } });
    document.dispatchEvent(new CustomEvent('fc:cart', { detail: c }));
  };
  FC.add = async (id, qty, source = 'cart') => {
    const j = await FC.api('/api/cart/add', { product_id: id, qty, source });
    FC.toast(j.message || (j.ok ? 'Added to cart' : 'Could not add'), j.ok ? 'success' : 'error');
    return j;
  };

  /* ---------- cart panel (used by Cart, Quick Order and Voice Order) ---------- */
  FC.mountCart = (el) => {
    const full = el.dataset.mode === 'full';
    const render = (c) => {
      if (!c.items.length) {
        el.innerHTML = '<div class="text-center py-10 muted"><i data-lucide="shopping-bag" class="w-10 h-10 mx-auto mb-3 opacity-50"></i><p class="font-semibold">Your cart is empty</p><div class="flex flex-wrap justify-center gap-2 mt-4"><a href="' + FC.url('/products') + '" class="btn btn-primary btn-sm">Browse products</a><a href="' + FC.url('/quick-order') + '" class="btn btn-outline btn-sm">Quick Order</a></div></div>';
        FC.icons(); return;
      }
      const rows = c.items.map((i) => `
        <li class="flex gap-3 py-3 border-b border-black/5 last:border-0" data-id="${i.id}">
          <div class="w-16 h-16 rounded-xl overflow-hidden shrink-0 tint-accent flex items-center justify-center">${i.image ? `<img src="${FC.esc(i.image)}" alt="" class="w-full h-full object-cover" loading="lazy">` : '<i data-lucide="sparkles" class="w-6 h-6 text-primary/60"></i>'}</div>
          <div class="flex-1 min-w-0">
            <a class="font-semibold text-sm leading-snug block truncate hover:text-primary" href="${FC.url('/product-details.php?id=' + i.id)}">${FC.esc(i.name)}</a>
            <div class="text-xs muted">${FC.esc(i.sku)} · ${FC.money(i.unit_price)}${i.original_price > i.unit_price ? ' <s>' + FC.money(i.original_price) + '</s>' : ''}</div>
            <div class="flex items-center justify-between mt-2 gap-2">
              <div class="qty"><button type="button" data-q="-1" aria-label="Decrease">−</button><input type="number" min="1" max="${i.stock}" value="${i.qty}" aria-label="Quantity for ${FC.esc(i.name)}"><button type="button" data-q="1" aria-label="Increase">+</button></div>
              <b class="text-sm">${FC.money(i.line_total)}</b>
              <button type="button" data-remove class="p-2 text-red-600 hover:bg-red-50 rounded-lg" aria-label="Remove ${FC.esc(i.name)}"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
            </div>
          </div>
        </li>`).join('');
      const issues = c.issues.map((m) => `<p class="text-xs text-amber-700 bg-amber-50 rounded-lg p-2 mt-2">${FC.esc(m)}</p>`).join('');
      const free = c.free_delivery_remaining > 0 ? `<p class="text-xs text-green-700 bg-green-50 rounded-lg p-2 mt-2">Add ${FC.money(c.free_delivery_remaining)} more for free delivery.</p>` : '';
      el.innerHTML = `<ul>${rows}</ul>${issues}${free}
        <dl class="mt-4 space-y-1.5 text-sm">
          <div class="flex justify-between"><dt class="muted">Subtotal (MRP)</dt><dd>${FC.money(c.subtotal)}</dd></div>
          ${c.discount > 0 ? `<div class="flex justify-between text-green-700"><dt>Discount</dt><dd>− ${FC.money(c.discount)}</dd></div>` : ''}
          <div class="flex justify-between"><dt class="muted">Delivery</dt><dd>${c.delivery > 0 ? FC.money(c.delivery) : 'Free'}</dd></div>
          <div class="flex justify-between text-base font-bold pt-2 border-t border-black/10"><dt>Total</dt><dd>${FC.money(c.total)}</dd></div>
        </dl>
        <p class="hint">Final prices and stock are re-verified at checkout.</p>
        <div class="grid gap-2 mt-4 ${full ? 'sm:grid-cols-2' : ''}">
          <a href="${FC.url('/checkout')}" class="btn btn-primary ${c.meets_min ? '' : 'pointer-events-none opacity-50'}"><i data-lucide="lock" class="w-4 h-4"></i> Proceed to Checkout</a>
          <button type="button" data-clear class="btn btn-danger"><i data-lucide="x" class="w-4 h-4"></i> Clear cart</button>
        </div>`;
      FC.icons();
    };
    document.addEventListener('fc:cart', (e) => render(e.detail));
    el.addEventListener('click', async (e) => {
      const li = e.target.closest('li[data-id]');
      if (e.target.closest('[data-q]') && li) { const inp = li.querySelector('input'); const v = Math.max(0, +inp.value + +e.target.closest('[data-q]').dataset.q); await FC.api('/api/cart/update', { product_id: li.dataset.id, qty: v }); }
      else if (e.target.closest('[data-remove]') && li) await FC.api('/api/cart/remove', { product_id: li.dataset.id });
      else if (e.target.closest('[data-clear]') && confirm('Remove all items from your cart?')) await FC.api('/api/cart/clear', { x: 1 });
    });
    el.addEventListener('change', async (e) => {
      const li = e.target.closest('li[data-id]'); if (!li || e.target.type !== 'number') return;
      const j = await FC.api('/api/cart/update', { product_id: li.dataset.id, qty: Math.max(0, parseInt(e.target.value || '0', 10)) });
      if (j.message && j.message !== 'Updated.') FC.toast(j.message, 'info');
    });
    FC.api('/api/cart').then((j) => j.cart && null);
  };

  /* ---------- slide-over cart drawer ---------- */
  FC.openDrawer = () => { const d = document.getElementById('cart-drawer'); if (!d) return false; document.body.classList.add('drawer-open'); d.setAttribute('aria-hidden', 'false'); return true; };
  FC.closeDrawer = () => { const d = document.getElementById('cart-drawer'); document.body.classList.remove('drawer-open'); d && d.setAttribute('aria-hidden', 'true'); };
  document.addEventListener('click', (e) => {
    const o = e.target.closest('[data-cart-open]'); if (o && document.getElementById('cart-drawer')) { e.preventDefault(); FC.openDrawer(); return; }
    if (e.target.closest('[data-drawer-close]')) FC.closeDrawer();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') FC.closeDrawer(); });

  /* ---------- product card interactions ---------- */
  document.addEventListener('click', async (e) => {
    const q = e.target.closest('.qty [data-step]');
    if (q) { const i = q.parentElement.querySelector('input'); i.value = Math.max(1, Math.min(+i.max || 999, (+i.value || 1) + +q.dataset.step)); return; }
    const add = e.target.closest('[data-add]');
    if (add) {
      const card = add.closest('[data-card]'); const inp = add.hasAttribute('data-quick') ? null : (card && card.querySelector('.qty input'));
      add.disabled = true; const old = add.innerHTML;
      const r = await FC.add(+add.dataset.add, inp ? Math.max(1, +inp.value || 1) : 1, add.dataset.source || 'cart');
      add.disabled = false; add.innerHTML = old; FC.icons();
      if (r && r.ok && window.FW) FW.burstAt(add);   // 🎆 fire blast where you clicked
      if (r && r.ok) setTimeout(() => FC.openDrawer && FC.openDrawer(), 450);
    }
    const nav = e.target.closest('[data-nav-toggle]');
    if (nav) { const m = document.getElementById('mobile-nav'); const open = m.classList.toggle('hidden') === false; nav.setAttribute('aria-expanded', open); }
  });

  /* ---------- hero carousel: arrows, dots, swipe, autoplay ---------- */
  document.querySelectorAll('[data-carousel]').forEach((root) => {
    const track = root.querySelector('.hero-track'); const n = track.children.length; let i = 0, timer, sx = null;
    const dots = root.querySelector('.hero-dots');
    const go = (k) => { i = (k + n) % n; track.style.transform = `translateX(-${i * 100}%)`; [...track.children].forEach((s, x) => s.setAttribute('aria-hidden', x !== i)); dots && [...dots.children].forEach((d, x) => d.setAttribute('aria-current', x === i)); };
    if (dots) for (let k = 0; k < n; k++) { const b = document.createElement('button'); b.type = 'button'; b.setAttribute('aria-label', 'Go to slide ' + (k + 1)); b.onclick = () => { go(k); play(); }; dots.appendChild(b); }
    const play = () => { clearInterval(timer); if (n > 1 && !matchMedia('(prefers-reduced-motion: reduce)').matches) timer = setInterval(() => go(i + 1), 6000); };
    root.querySelector('[data-prev]')?.addEventListener('click', () => { go(i - 1); play(); });
    root.querySelector('[data-next]')?.addEventListener('click', () => { go(i + 1); play(); });
    root.addEventListener('touchstart', (e) => { sx = e.touches[0].clientX; }, { passive: true });
    root.addEventListener('touchend', (e) => { if (sx === null) return; const dx = e.changedTouches[0].clientX - sx; if (Math.abs(dx) > 40) { go(i + (dx < 0 ? 1 : -1)); play(); } sx = null; });
    root.addEventListener('mouseenter', () => clearInterval(timer)); root.addEventListener('mouseleave', play);
    if (n < 2) root.querySelectorAll('[data-prev],[data-next],.hero-dots').forEach((x) => x.remove());
    go(0); play();
  });

  document.addEventListener('DOMContentLoaded', () => {
    FC.icons();
    document.querySelectorAll('[data-cart-panel]').forEach(FC.mountCart);
    FC.api('/api/cart');
    // prevent double submits
    document.querySelectorAll('form[data-once]').forEach((f) => f.addEventListener('submit', (e) => {
      if (f.dataset.sent) { e.preventDefault(); return; } f.dataset.sent = 1;
      const b = f.querySelector('[type=submit]'); if (b) { b.disabled = true; b.innerHTML = '<span class="spinner"></span> Please wait…'; }
    }));
  });
})();
