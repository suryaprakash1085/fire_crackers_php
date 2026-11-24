/* Admin popups: create / edit / view open in a popup, forms save without leaving the page, delete/archive ask in a popup.
 * No dependencies. Pages stay reachable as normal links (open in new tab / no JS), the popup is only an enhancement. */
(function () {
  const base = document.querySelector('meta[name=base]').content;
  const $ = (s, r) => (r || document).querySelector(s);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const icons = () => window.lucide && window.lucide.createIcons();
  const HDR = { 'X-Modal': '1', 'X-Requested-With': 'XMLHttpRequest' };
  const pathOf = (u) => { const p = new URL(u, location.href).pathname; return base && p.startsWith(base) ? p.slice(base.length) || '/' : p; };
  const sameOrigin = (u) => new URL(u, location.href).origin === location.origin;
  const LINK = /^\/admin\/(products|categories|services|banners)\/(create|\d+\/edit|\d+)\/?$/;   // links that open in a popup
  const FORMPAGE = /^\/admin\/(products|categories|services|banners)\/(create|\d+\/edit)\/?$/;    // a page that is a form (stay in the popup)
  const LISTFORM = /^\/admin\/((products|categories|services|banners)\/\d+\/(delete|archive|restore)|users\/\d+\/toggle)\/?$/;   // row actions: save in place, refresh the list

  /* ---------- popup shell ---------- */
  let modal, body, titleEl, dialog, dirty = false, lastFocus = null, confirmEl, cResolve = null;
  function build() {
    if (modal) return;
    modal = document.createElement('div'); modal.className = 'am-modal'; modal.hidden = true; modal.setAttribute('role', 'dialog'); modal.setAttribute('aria-modal', 'true'); modal.setAttribute('aria-labelledby', 'am-title');
    modal.innerHTML = '<div class="am-backdrop" data-am-close></div><div class="am-dialog"><header><h3 id="am-title"></h3><button type="button" class="am-x" data-am-close aria-label="Close">×</button></header><div class="am-body" id="am-body"></div></div>';
    confirmEl = document.createElement('div'); confirmEl.className = 'am-confirm'; confirmEl.hidden = true; confirmEl.setAttribute('role', 'alertdialog'); confirmEl.setAttribute('aria-modal', 'true');
    confirmEl.innerHTML = '<div class="am-backdrop" data-am-cancel></div><div class="am-dialog am-sm"><div class="am-body"><h3 id="am-c-title"></h3><p id="am-c-text"></p></div><div class="am-foot"><button type="button" class="btn btn-outline btn-sm" data-am-cancel>Cancel</button><button type="button" class="btn btn-primary btn-sm" id="am-c-ok">OK</button></div></div>';
    document.body.append(modal, confirmEl);
    body = $('#am-body', modal); titleEl = $('#am-title', modal); dialog = $('.am-dialog', modal);
    body.addEventListener('input', () => { dirty = true; }); body.addEventListener('change', () => { dirty = true; });
  }
  function open(title) {
    build(); lastFocus = document.activeElement; dirty = false;
    titleEl.textContent = title || ''; dialog.className = 'am-dialog';
    body.innerHTML = '<div class="am-loading"><span class="spinner"></span><p>Loading…</p></div>';
    modal.hidden = false; document.body.style.overflow = 'hidden';
  }
  function close(force) {
    if (!modal || modal.hidden) return Promise.resolve(true);
    const go = () => { modal.hidden = true; document.body.style.overflow = ''; body.innerHTML = ''; dirty = false; if (lastFocus && lastFocus.focus) try { lastFocus.focus(); } catch (e) {} return true; };
    if (force || !dirty) return Promise.resolve(go());
    return ask({ title: 'Discard changes?', text: 'You have unsaved changes in this form. Close it without saving?', ok: 'Discard', danger: true }).then((y) => (y ? go() : false));
  }
  function ask(o) {
    build();
    $('#am-c-title', confirmEl).textContent = o.title || 'Are you sure?'; $('#am-c-text', confirmEl).textContent = o.text || '';
    const ok = $('#am-c-ok', confirmEl); ok.textContent = o.ok || 'OK'; ok.className = 'btn btn-sm ' + (o.danger ? 'btn-danger' : 'btn-primary');
    confirmEl.hidden = false; ok.focus();
    return new Promise((res) => { cResolve = res; });
  }
  function answer(v) { if (!cResolve) return; confirmEl.hidden = true; const r = cResolve; cResolve = null; r(v); }

  /* ---------- put HTML into the popup (scripts are re-created so they run) ---------- */
  function setHTML(html) {
    body.innerHTML = html;
    body.querySelectorAll('script').forEach((o) => { const n = document.createElement('script'); n.text = o.textContent; o.replaceWith(n); });
    const t = $('[data-am-title]', body); if (t && t.dataset.amTitle) titleEl.textContent = t.dataset.amTitle;
    const sz = $('[data-am-size]', body); dialog.className = 'am-dialog' + (sz ? ' am-' + sz.dataset.amSize : '');
    dirty = false; icons(); body.scrollTop = 0;
    const f = $('input:not([type=hidden]):not([readonly]),select,textarea', body); if (f && matchMedia('(min-width:700px)').matches) try { f.focus({ preventScroll: true }); } catch (e) {}
  }
  const expired = (r) => /\/admin\/login\/?$/.test(new URL(r.url).pathname);
  async function loadInto(url) {
    try {
      const r = await fetch(url, { credentials: 'same-origin', headers: HDR });
      if (expired(r)) { location.href = r.url; return; }
      if (!r.ok) throw new Error('Could not load (HTTP ' + r.status + ').');
      setHTML(await r.text());
    } catch (e) { body.innerHTML = '<div class="am-empty"><p>' + esc(e.message || 'Network error.') + '</p><button type="button" class="btn btn-outline btn-sm" data-am-retry="' + esc(url) + '">Try again</button></div>'; }
  }
  function openUrl(url, title) { const wasOpen = modal && !modal.hidden; if (!wasOpen) open(title); else { dirty = false; body.innerHTML = '<div class="am-loading"><span class="spinner"></span></div>'; } return loadInto(url); }

  /* ---------- refresh the page behind the popup (keeps filters / page number) ---------- */
  function alertsOf(html) { const t = document.createElement('div'); t.innerHTML = html; return { root: t, alerts: [...t.querySelectorAll(':scope > [role=alert]')] }; }
  async function refreshMain(alerts) {
    const main = $('main'); if (!main) { location.reload(); return; }
    try {
      const r = await fetch(location.pathname + location.search, { credentials: 'same-origin', headers: HDR });
      if (expired(r) || !r.ok) { location.reload(); return; }
      const t = document.createElement('div'); t.innerHTML = await r.text();
      t.querySelectorAll(':scope > [role=alert]').forEach((a) => a.remove());
      main.replaceChildren(...alerts, ...t.childNodes); icons(); window.scrollTo({ top: 0, behavior: 'smooth' });
      if (alerts.length) setTimeout(() => alerts.forEach((a) => { if (a.dataset.type === 'success') { a.style.transition = 'opacity .4s'; a.style.opacity = 0; setTimeout(() => a.remove(), 450); } }), 4500);
    } catch (e) { location.reload(); }
  }
  function banner(alerts) {   // errors that came back as a redirect + flash: keep the form (and the typed values) and show the message on top
    $$('.am-banner', body).forEach((x) => x.remove());
    const w = document.createElement('div'); w.className = 'am-banner'; alerts.forEach((a) => w.appendChild(a)); body.prepend(w); body.scrollTop = 0;
  }
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));

  /* ---------- submit a form without leaving the page ---------- */
  async function submit(form, submitter, inModal) {
    const fd = new FormData(form); if (submitter && submitter.name) fd.set(submitter.name, submitter.value);
    const btns = $$('button[type=submit],button:not([type])', form), old = submitter ? submitter.innerHTML : '';
    btns.forEach((b) => { b.disabled = true; }); if (submitter) submitter.innerHTML = '<span class="spinner"></span> Saving…'; if (inModal) body.classList.add('am-busy');
    const restore = () => { btns.forEach((b) => { b.disabled = false; }); if (submitter) submitter.innerHTML = old; body.classList && body.classList.remove('am-busy'); icons(); };
    let r, html;
    try { r = await fetch(form.getAttribute('action') || location.href, { method: 'POST', body: fd, credentials: 'same-origin', headers: HDR }); html = await r.text(); }
    catch (e) { restore(); const m = document.createElement('div'); m.setAttribute('role', 'alert'); m.dataset.type = 'error'; m.className = 'mb-4 rounded-xl px-4 py-3 text-sm font-medium border bg-red-50 border-red-200 text-red-800'; m.textContent = 'Network problem. Nothing was saved — please try again.'; if (inModal) banner([m]); else alert(m.textContent); return; }
    if (expired(r)) { location.href = r.url; return; }
    const { root, alerts } = alertsOf(html), hasErr = alerts.some((a) => a.dataset.type === 'error'), hasOk = alerts.some((a) => a.dataset.type !== 'error');
    if (inModal && r.redirected && hasErr && !hasOk) { restore(); banner(alerts); return; }                 // e.g. "Could not save", "email already exists": keep the form as typed
    if (inModal && !r.redirected) { restore(); setHTML(html); return; }                                        // validation errors: the form comes back with the values + messages
    if (inModal && r.redirected && FORMPAGE.test(pathOf(r.url))) { restore(); setHTML(html); return; }
    await close(true); await refreshMain(alerts);                                                              // saved: close, refresh the list, show the message
  }

  /* ---------- events ---------- */
  document.addEventListener('click', (e) => {
    const t = e.target;
    if (t.closest('[data-am-close]')) { e.preventDefault(); close(); return; }
    if (t.closest('[data-am-cancel]')) { answer(false); return; }
    if (t.closest('#am-c-ok')) { answer(true); return; }
    const retry = t.closest('[data-am-retry]'); if (retry) { loadInto(retry.dataset.amRetry); return; }
    const tpl = t.closest('[data-modal-template]');                                                           // button that shows an inline <template> form in a popup
    if (tpl) { e.preventDefault(); const tp = document.getElementById(tpl.dataset.modalTemplate); if (tp) { open(tpl.dataset.modalTitle || ''); setHTML('<div hidden data-am-size="' + (tpl.dataset.modalSize || 'md') + '"></div>' + tp.innerHTML); } return; }
    const cf = t.closest('[data-confirm]');                                                                   // styled replacement for confirm()
    if (cf) {
      e.preventDefault(); e.stopPropagation();
      const danger = cf.classList.contains('btn-danger') || /delete|remove/i.test(cf.textContent);
      ask({ title: danger ? 'Please confirm' : 'Are you sure?', text: cf.dataset.confirm, ok: (cf.textContent || 'OK').trim() || 'OK', danger }).then((yes) => {
        if (!yes) return;
        if (cf.tagName === 'A') { location.href = cf.href; return; }
        const f = cf.form; if (!f) return;
        if (f.requestSubmit) f.requestSubmit(cf); else { if (cf.name) { const h = document.createElement('input'); h.type = 'hidden'; h.name = cf.name; h.value = cf.value; f.appendChild(h); } f.submit(); }
      });
      return;
    }
    const a = t.closest('a[href]');                                                                           // create / edit / view links
    if (a && !e.defaultPrevented && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey && e.button === 0 && a.target !== '_blank' && !a.hasAttribute('download') && !a.hasAttribute('data-no-modal') && sameOrigin(a.href) && LINK.test(pathOf(a.href))) {
      e.preventDefault(); openUrl(a.href, (a.dataset.modalTitle || a.textContent.trim() || '').slice(0, 80));
    }
  });
  document.addEventListener('submit', (e) => {
    const f = e.target; if (e.defaultPrevented || !(f instanceof HTMLFormElement) || (f.method || '').toLowerCase() !== 'post') return;
    const inModal = !!(modal && modal.contains(f)), p = pathOf(f.getAttribute('action') || location.href);
    if (!inModal && !LISTFORM.test(p)) return;                                                                // everything else submits normally
    e.preventDefault(); submit(f, e.submitter || null, inModal);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (confirmEl && !confirmEl.hidden) { answer(false); return; }
    if (modal && !modal.hidden) close();
  });
})();
