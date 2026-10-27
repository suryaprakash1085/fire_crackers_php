(function () {
  const csrf = document.querySelector('meta[name=csrf]').content, base = document.querySelector('meta[name=base]').content;
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  window.lucide && lucide.createIcons();
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-side-toggle]')) { const sb = document.getElementById('sidebar'); sb.classList.toggle('-translate-x-full'); const sc = document.getElementById('side-scrim'); sc && sc.classList.toggle('hidden', sb.classList.contains('-translate-x-full')); }
    const c = e.target.closest('[data-confirm]'); if (c && !confirm(c.dataset.confirm)) e.preventDefault();
  });
  /* ---- media picker modal ---- */
  const modal = document.getElementById('media-modal'); let onPick = null;
  async function openPicker(cb) {
    onPick = cb; modal.classList.remove('hidden'); const g = modal.querySelector('[data-grid]'); g.innerHTML = '<p class="p-4 text-sm">Loading…</p>';
    const j = await (await fetch(base + '/admin/media/json')).json();
    g.innerHTML = j.items.length ? j.items.map((m) => `<button type="button" class="rounded-lg overflow-hidden border hover:ring-2 ring-orange-400 aspect-square bg-gray-50" data-path="${esc(m.path)}" data-url="${esc(m.url)}" title="${esc(m.name)}"><img src="${esc(m.url)}" class="w-full h-full object-cover" loading="lazy" alt="${esc(m.name)}"></button>`).join('') : '<p class="p-4 text-sm">No images yet. Upload some in the Media Library or generate one with AI.</p>';
  }
  modal && modal.addEventListener('click', (e) => {
    if (e.target === modal || e.target.closest('[data-close]')) modal.classList.add('hidden');
    const b = e.target.closest('[data-path]'); if (b && onPick) { onPick(b.dataset.path, b.dataset.url); modal.classList.add('hidden'); }
  });
  document.querySelectorAll('[data-image-field]').forEach((w) => {
    const hidden = w.querySelector('[data-lib]'), prev = w.querySelector('[data-preview]');
    w.querySelector('[data-open-picker]')?.addEventListener('click', () => openPicker((p, u) => { hidden.value = p; prev.innerHTML = `<img src="${esc(u)}" class="w-full h-full object-cover" alt="">`; w.querySelector('[data-remove-chk]') && (w.querySelector('[data-remove-chk]').checked = false); }));
    w.querySelector('input[type=file]')?.addEventListener('change', (ev) => { const f = ev.target.files[0]; if (f) { prev.innerHTML = `<img src="${URL.createObjectURL(f)}" class="w-full h-full object-cover" alt="">`; hidden.value = ''; } });
  });
  document.querySelectorAll('[data-multi-picker]').forEach((w) => {
    const box = w.querySelector('[data-selected]');
    w.querySelector('[data-open-picker]').addEventListener('click', () => openPicker((p, u) => { if (box.querySelector(`[value="${CSS.escape(p)}"]`)) return; const d = document.createElement('div'); d.className = 'relative w-20 h-20 rounded-lg overflow-hidden border'; d.innerHTML = `<img src="${esc(u)}" class="w-full h-full object-cover" alt=""><input type="hidden" name="library_images[]" value="${esc(p)}"><button type="button" class="absolute top-0 right-0 bg-white/90 rounded-bl px-1.5 text-red-600" aria-label="Remove">×</button>`; d.querySelector('button').onclick = () => d.remove(); box.appendChild(d); }));
  });
  /* ---- colour inputs: keep hex text and picker in sync, live preview ---- */
  document.querySelectorAll('[data-color]').forEach((w) => {
    const pick = w.querySelector('input[type=color]'), txt = w.querySelector('input[type=text]');
    pick.addEventListener('input', () => { txt.value = pick.value.toUpperCase(); document.dispatchEvent(new Event('colorchange')); });
    txt.addEventListener('input', () => { if (/^#[0-9a-fA-F]{6}$/.test(txt.value)) { pick.value = txt.value; document.dispatchEvent(new Event('colorchange')); } });
  });
  const pv = document.getElementById('theme-preview');
  if (pv) { const upd = () => { document.querySelectorAll('[data-color]').forEach((w) => pv.style.setProperty('--' + w.dataset.color, w.querySelector('input[type=text]').value)); }; document.addEventListener('colorchange', upd); upd(); }
  /* ---- AI image page ---- */
  const ai = document.getElementById('ai-form');
  if (ai) {
    const out = document.getElementById('ai-out'); let last = null;
    const post = async (url, body) => { const r = await fetch(base + url, { method: 'POST', headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' }, body: new URLSearchParams(body) }); try { return await r.json(); } catch (e) { return { ok: false, message: 'Unexpected server response.' }; } };
    const gen = async () => {
      const btn = document.getElementById('ai-go'); btn.disabled = true; out.innerHTML = '<div class="p-10 text-center"><span class="spinner"></span><p class="mt-3 text-sm">Generating… this can take up to a minute.</p></div>';
      const j = await post('/admin/ai-image/generate', Object.fromEntries(new FormData(ai))); btn.disabled = false;
      if (!j.ok) { out.innerHTML = `<div class="p-4 rounded-xl bg-red-50 text-red-800 text-sm">${esc(j.message)}</div>`; return; }
      last = j; out.innerHTML = `<img src="${esc(j.url)}" class="w-full rounded-xl border" alt="Generated image"><p class="text-xs text-gray-500 mt-2">${esc(j.message)}</p>
        <div class="flex flex-wrap gap-2 mt-3"><button type="button" class="btn btn-primary btn-sm" id="ai-save">Save to Media Library</button><button type="button" class="btn btn-outline btn-sm" id="ai-regen">Regenerate</button><a class="btn btn-soft btn-sm" href="${esc(j.url)}?download=1">Download</a></div><p id="ai-msg" class="text-sm mt-2"></p>`;
      document.getElementById('ai-regen').onclick = gen;
      document.getElementById('ai-save').onclick = async () => { const s = await post('/admin/ai-image/' + last.id + '/save', {}); document.getElementById('ai-msg').textContent = s.message; document.getElementById('ai-msg').className = 'text-sm mt-2 ' + (s.ok ? 'text-green-700' : 'text-red-700'); };
    };
    ai.addEventListener('submit', (e) => { e.preventDefault(); gen(); });
    document.querySelectorAll('[data-ai-save]').forEach((b) => b.addEventListener('click', async () => { const s = await post('/admin/ai-image/' + b.dataset.aiSave + '/save', {}); b.textContent = s.ok ? 'Saved ✓' : 'Failed'; b.title = s.message; if (s.ok) b.disabled = true; }));
  }
})();

document.addEventListener('click', function (e) { if (!e.target.closest('[data-theme-toggle]')) return; var h = document.documentElement; h.classList.toggle('dark'); try { localStorage.setItem('fc_admin_theme', h.classList.contains('dark') ? 'dark' : 'light'); } catch (x) {} });
