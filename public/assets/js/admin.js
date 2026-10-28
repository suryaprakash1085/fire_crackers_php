(function () {
  const csrf = document.querySelector('meta[name=csrf]').content, base = document.querySelector('meta[name=base]').content;
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  window.lucide && lucide.createIcons();
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-side-toggle]')) { const sb = document.getElementById('sidebar'); sb.classList.toggle('-translate-x-full'); const sc = document.getElementById('side-scrim'); sc && sc.classList.toggle('hidden', sb.classList.contains('-translate-x-full')); }
    const c = e.target.closest('[data-confirm]'); if (c && !confirm(c.dataset.confirm)) e.preventDefault();
  });
  /* ---- colour inputs: keep hex text and picker in sync, live preview ---- */
  document.querySelectorAll('[data-color]').forEach((w) => {
    const pick = w.querySelector('input[type=color]'), txt = w.querySelector('input[type=text]');
    pick.addEventListener('input', () => { txt.value = pick.value.toUpperCase(); document.dispatchEvent(new Event('colorchange')); });
    txt.addEventListener('input', () => { if (/^#[0-9a-fA-F]{6}$/.test(txt.value)) { pick.value = txt.value; document.dispatchEvent(new Event('colorchange')); } });
  });
  const pv = document.getElementById('theme-preview');
  if (pv) { const upd = () => { document.querySelectorAll('[data-color]').forEach((w) => pv.style.setProperty('--' + w.dataset.color, w.querySelector('input[type=text]').value)); }; document.addEventListener('colorchange', upd); upd(); }
})();

document.addEventListener('click', function (e) { if (!e.target.closest('[data-theme-toggle]')) return; var h = document.documentElement; h.classList.toggle('dark'); try { localStorage.setItem('fc_admin_theme', h.classList.contains('dark') ? 'dark' : 'light'); } catch (x) {} });
