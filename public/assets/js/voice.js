/* Voice Order — ONE-SHOT full flow (rewritten for reliability).
   Tap once → say the whole order → auto-stops after a pause → the recognised TEXT (never audio) is parsed once on OUR server
   → every matched product goes straight into the shared cart → a clear per-item result.
   A voice command never places an order. Typed input, a product picker and "Fix & re-add" are always available.

   Reliability notes
   • A fresh SpeechRecognition object is created for every session (re-using one is the #1 cause of "tap does nothing").
   • Every failure path shows a human message (blocked mic, no HTTPS, unsupported browser, no speech, network, unsupported language).
   • Android Chrome repeats the growing sentence in every result — mergeFinals() collapses that so quantities are never doubled. */
(function () {
  const $ = (id) => document.getElementById(id);
  const root = $('voice-root'); if (!root) return;
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  const SILENCE_MS = 3000, MAX_MS = 60000, IS_ANDROID = /Android/i.test(navigator.userAgent);
  let rec = null, listening = false, starting = false, errored = false, busy = false, finalText = '', interimText = '', silenceT = null, maxT = null, hasVoiceCart = false;
  const isTa = () => $('lang').value.startsWith('ta');
  const T = (en, ta) => (isTa() ? ta : en);

  try { const saved = localStorage.getItem('fc_voice_lang'); if (saved && [...$('lang').options].some((o) => o.value === saved)) $('lang').value = saved; } catch (e) {}
  $('lang').addEventListener('change', () => { try { localStorage.setItem('fc_voice_lang', $('lang').value); } catch (e) {} setState(listening); });

  const dbg = (m) => { const d = $('voice-debug'); if (d) d.textContent = 'status: ' + m; };
  const clearTimers = () => { clearTimeout(silenceT); clearTimeout(maxT); };
  const setState = (on) => {
    listening = on; root.classList.toggle('listening-on', on); $('mic').classList.toggle('listening', on);
    $('state').textContent = busy ? T('Adding to your cart…', 'கார்ட்டில் சேர்க்கிறேன்…') : starting ? T('Starting microphone…', 'மைக்கை தொடங்குகிறது…') : on ? T('Listening… say your full order', 'கேட்கிறேன்… முழு ஆர்டரையும் சொல்லுங்கள்') : T('Tap the microphone and say your full order', 'மைக்கை தட்டி முழு ஆர்டரையும் சொல்லுங்கள்');
    $('btn-start').disabled = !SR || on || starting || busy; $('btn-stop').disabled = !(on || starting); $('mic').setAttribute('aria-pressed', on);
  };
  const note = (msg, id = 'fallback-note') => { const n = $(id); n.textContent = msg; n.classList.remove('hidden'); };
  const fallback = (msg) => { note(msg); const t = $('typed'); t && t.focus({ preventScroll: true }); };
  const panel = (cls, msg) => { $('result').classList.remove('hidden'); $('result').innerHTML = `<div class="card p-5"><div class="rounded-xl border p-3 ${BOX[cls]} text-sm font-semibold">${FC.esc(msg)}</div></div>`; };

  /* ---------- result panel ---------- */
  const ICON = { added: ['check-circle-2', 'text-green-600'], reduced: ['alert-triangle', 'text-amber-600'], out_of_stock: ['x-circle', 'text-red-600'] };
  const BOX = { success: 'bg-green-50 border-green-200 text-green-900', warning: 'bg-amber-50 border-amber-200 text-amber-900', error: 'bg-red-50 border-red-200 text-red-900' };
  function render(j) {
    hasVoiceCart = !!j.can_undo;
    const rows = (j.items || []).map((i) => { const [ic, cl] = ICON[i.status] || ICON.out_of_stock;
      return `<li class="flex gap-2 items-start py-2.5 border-t border-black/5 first:border-0"><i data-lucide="${ic}" class="w-5 h-5 shrink-0 mt-0.5 ${cl}"></i><div class="min-w-0 flex-1"><b class="block text-sm">${FC.esc(i.name)}</b><span class="text-sm ${i.status === 'added' ? 'muted' : ''}">${FC.esc(i.message)}</span>${i.guess ? `<span class="block text-xs text-amber-700 mt-0.5">${T('Best match for “' + i.guess + '” — wrong? Undo and say the full name.', '“' + i.guess + '” க்கு அருகில் உள்ள பொருள் — தவறா? Undo செய்து முழு பெயர் சொல்லுங்கள்.')}</span>` : ''}</div><span class="text-xs muted shrink-0">${T('asked', 'கேட்டது')} ${i.requested}</span></li>`; }).join('');
    const un = (j.unmatched || []).map((u) => `<li class="flex gap-2 items-start py-2.5 border-t border-black/5"><i data-lucide="search-x" class="w-5 h-5 shrink-0 mt-0.5 text-red-600"></i><div class="min-w-0 flex-1"><b class="block text-sm">${T('Not found', 'கிடைக்கவில்லை')}: “${FC.esc(u.phrase)}”</b>` +
      (u.suggestions.length ? `<span class="text-sm muted">${T('Did you mean (tap to add 1):', 'இதுவா? (தட்டினால் 1 சேரும்):')}</span><div class="flex flex-wrap gap-2 mt-1.5">${u.suggestions.map((s) => `<button type="button" class="chip" data-suggest="${s.id}">${FC.esc(s.name)} · ${FC.money(s.price)}</button>`).join('')}</div>` : `<span class="text-sm muted">${T('No similar product. Type the name below or pick from the list.', 'ஒத்த பொருள் இல்லை. கீழே தட்டச்சு செய்யவும் அல்லது பட்டியலில் தேர்வு செய்யவும்.')}</span>`) + '</div></li>').join('');
    $('result').innerHTML = `<div class="card p-5"><div class="rounded-xl border p-3 flex gap-2 items-start ${BOX[j.level] || BOX.error}"><i data-lucide="${j.level === 'success' ? 'party-popper' : 'info'}" class="w-5 h-5 shrink-0 mt-0.5"></i><b class="text-sm">${FC.esc(j.summary || j.message || '')}</b></div>
      <ul class="mt-2">${rows}${un}</ul>
      <div class="grid sm:grid-cols-3 gap-2 mt-4"><a href="${FC.url('/checkout')}" class="btn btn-primary"><i data-lucide="lock" class="w-4 h-4"></i> ${T('Review & Checkout', 'சரிபார்த்து செக்அவுட்')}</a>
        <button type="button" id="r-again" class="btn btn-soft"><i data-lucide="mic" class="w-4 h-4"></i> ${T('Add more by voice', 'மேலும் சேர்க்க')}</button>
        ${j.can_undo ? `<button type="button" id="r-undo" class="btn btn-danger"><i data-lucide="undo-2" class="w-4 h-4"></i> ${T('Undo this voice order', 'இந்த ஆர்டரை நீக்கு')}</button>` : `<a href="${FC.url('/products')}" class="btn btn-outline">${T('Continue shopping', 'தொடர்ந்து வாங்க')}</a>`}</div>
      <p class="hint mt-2">${T('Nothing is ordered yet — you confirm everything at checkout.', 'இன்னும் ஆர்டர் செய்யப்படவில்லை — செக்அவுட்டில் உறுதி செய்யுங்கள்.')}</p></div>`;
    $('result').classList.remove('hidden'); FC.icons();
    $('r-again')?.addEventListener('click', () => { $('result').classList.add('hidden'); start(); });
    $('r-undo')?.addEventListener('click', async () => { const u = await FC.api('/api/voice/undo', { x: 1 }); hasVoiceCart = false; FC.toast(u.message || 'Removed', 'success'); $('result').classList.add('hidden'); $('heard').value = ''; $('heard-fix').disabled = true; });
    $('result').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    if (j.ok && window.FW) { FW.burstAt($('mic'), { count: 60, power: 4 }); if (j.level === 'success') FW.celebrate(3); }
  }

  /* ---------- parse on the server ---------- */
  async function process(text, opts = {}) {
    text = (text || '').trim(); if (!text || busy) return;
    busy = true; setState(false); $('heard').value = text; $('heard-fix').disabled = true;
    $('result').classList.remove('hidden'); $('result').innerHTML = '<div class="card p-6 text-center"><span class="spinner"></span></div>';
    if (opts.fix && hasVoiceCart) { await FC.api('/api/voice/undo', { x: 1 }); hasVoiceCart = false; }   // replace the previous voice order instead of doubling it
    const j = await FC.api('/api/voice/parse', { text, lang: isTa() ? 'ta' : 'en' });
    busy = false; setState(false); $('heard-fix').disabled = !$('heard').value.trim(); dbg('server replied: ' + ((j.items || []).length) + ' matched, ' + ((j.unmatched || []).length) + ' not found');
    if (!j.items && !j.unmatched) { panel('error', j.message || T('Something went wrong. Please try again.', 'ஏதோ தவறு நடந்தது. மீண்டும் முயலுங்கள்.')); return; }
    render(j);
  }

  /* Some browsers repeat the growing sentence in every result ("five" / "five flower" / "five flower pots"): keep only the longest. */
  function mergeFinals(list) {
    const parts = [];
    for (const raw of list) {
      const t = raw.trim(); if (!t) continue; const low = t.toLowerCase(), last = parts.length ? parts[parts.length - 1].toLowerCase() : null;
      if (last !== null && low.startsWith(last)) parts[parts.length - 1] = t;
      else if (last !== null && last.startsWith(low)) continue;
      else parts.push(t);
    }
    return parts.join(' ');
  }

  /* ---------- microphone ---------- */
  let sessionDone = true, watchdog = null, heardSpeech = false, noSpeechT = null;
  function finish(why) {
    if (sessionDone) return; sessionDone = true; clearTimers(); clearTimeout(watchdog);
    const text = (finalText + ' ' + interimText).trim(); starting = false; setState(false); dbg('finished (' + why + '), heard: ' + (text || 'nothing') + ' → sending to server');
    if (text) process(text); else if (!errored) panel('warning', T('I did not hear anything. Tap the microphone and try again.', 'எதுவும் கேட்கவில்லை. மைக்கை தட்டி மீண்டும் முயலுங்கள்.'));
  }
  function makeRec() {
    const r = new SR(); r.continuous = !IS_ANDROID; r.interimResults = true; r.maxAlternatives = 1; r.lang = $('lang').value;
    r.onstart = () => { if (r !== rec) return; starting = false; setState(true); dbg('microphone open');
      noSpeechT = setTimeout(() => { if (!heardSpeech && listening) { dbg('mic is open but no speech heard'); note(T('The microphone is open but I cannot hear you. Press “Test my microphone” below — if the bar does not move, Windows/Chrome is using the wrong microphone (Windows Settings → System → Sound → Input; Chrome → Site settings → Microphone).', 'மைக் திறந்திருக்கிறது ஆனால் குரல் கேட்கவில்லை. கீழே “Test my microphone” அழுத்துங்கள் — பட்டை நகரவில்லை என்றால் தவறான மைக் தேர்வாகியிருக்கும் (Windows Sound → Input; Chrome → Microphone).'), 'support-note'); } }, 6000); };
    r.onaudiostart = () => dbg('audio started — now speak'); r.onsoundstart = () => dbg('sound detected'); r.onspeechstart = () => { heardSpeech = true; clearTimeout(noSpeechT); dbg('speech detected'); }; r.onnomatch = () => dbg('no match');
    r.onresult = (e) => {
      if (r !== rec) return; dbg('got text');
      const finals = [], interims = [];
      for (let i = 0; i < e.results.length; i++) (e.results[i].isFinal ? finals : interims).push(e.results[i][0].transcript);
      finalText = mergeFinals(finals); interimText = mergeFinals(interims); const all = (finalText + ' ' + interimText).trim();
      $('heard').value = all; $('heard-fix').disabled = !all;
      clearTimeout(silenceT); if (all) silenceT = setTimeout(stop, SILENCE_MS);   // a pause = done
    };
    r.onerror = (e) => {
      if (r !== rec) return; const code = e.error; dbg('error: ' + code); errored = true; clearTimers(); starting = false; setState(false);
      if (!['not-allowed', 'service-not-allowed', 'audio-capture'].includes(code) && (finalText + interimText).trim()) { errored = false; return; }   // keep what we heard; onend will process it
      if (code === 'not-allowed' || code === 'service-not-allowed') fallback(T('Microphone access was blocked. Click the lock icon in the address bar → allow Microphone, then try again — or type your order below.', 'மைக் அனுமதி மறுக்கப்பட்டது. முகவரிப் பட்டையில் உள்ள பூட்டு படத்தை தட்டி மைக்கை அனுமதியுங்கள் — அல்லது கீழே தட்டச்சு செய்யுங்கள்.'));
      else if (code === 'no-speech') panel('warning', T('I did not hear anything. Tap the microphone and try again.', 'எதுவும் கேட்கவில்லை. மைக்கை தட்டி மீண்டும் முயலுங்கள்.'));
      else if (code === 'audio-capture') fallback(T('No microphone was found. Connect one, or type your order below.', 'மைக் எதுவும் கிடைக்கவில்லை. இணைக்கவும் அல்லது கீழே தட்டச்சு செய்யுங்கள்.'));
      else if (code === 'network') fallback(T('The browser speech service could not be reached (check your internet, or this browser may not support it — Chrome / Edge work best). Please type your order below.', 'குரல் சேவையை அடைய முடியவில்லை (இணையத்தை சரிபார்க்கவும்; Chrome / Edge சிறந்தவை). கீழே தட்டச்சு செய்யுங்கள்.'));
      else if (code === 'language-not-supported') fallback(T('This browser does not support the selected language. Choose English, or type your order below.', 'இந்த மொழியை உலாவி ஆதரிக்கவில்லை. ஆங்கிலத்தைத் தேர்வு செய்யவும் அல்லது தட்டச்சு செய்யுங்கள்.'));
      else if (code !== 'aborted') fallback(T('Voice input failed (' + code + '). Please type your order below.', 'குரல் உள்ளீடு தோல்வி (' + code + '). கீழே தட்டச்சு செய்யுங்கள்.'));
    };
    r.onend = () => { if (r !== rec) return; dbg('ended'); finish('onend'); };
    return r;
  }
  function start() {
    if (!SR || listening || starting || busy) return;
    finalText = ''; interimText = ''; errored = false; heardSpeech = false; clearTimeout(noSpeechT); $('support-note').classList.add('hidden'); $('heard').value = ''; $('heard-fix').disabled = true; $('result').classList.add('hidden'); $('fallback-note').classList.add('hidden');
    try { rec = makeRec(); sessionDone = false; starting = true; setState(false); rec.start(); maxT = setTimeout(stop, MAX_MS); }
    catch (e) { starting = false; setState(false); fallback(T('Could not start the microphone (' + (e && e.message || e) + '). Reload the page and try again, or type your order below.', 'மைக்கை தொடங்க முடியவில்லை. பக்கத்தை மீண்டும் ஏற்றவும் அல்லது கீழே தட்டச்சு செய்யுங்கள்.')); }
  }
  function stop() { clearTimers(); clearTimeout(noSpeechT); if (!rec) return; dbg('stopping…'); try { if (listening) rec.stop(); else if (starting) rec.abort(); } catch (e) {} clearTimeout(watchdog); watchdog = setTimeout(() => finish('watchdog'), 1800); }

  if (!SR) {
    $('mic').disabled = true; note(T('Voice recognition is not available in this browser. Please use Google Chrome or Microsoft Edge (or type your order below).', 'இந்த உலாவியில் குரல் அங்கீகாரம் இல்லை. Google Chrome அல்லது Microsoft Edge பயன்படுத்தவும் (அல்லது கீழே தட்டச்சு செய்யுங்கள்).'), 'support-note');
    fallback('Speech recognition is not supported in this browser. Please type your order below.');
  } else if (window.isSecureContext === false) {
    $('mic').disabled = true; note('The microphone only works on a secure page (HTTPS). Open this site with https:// — or ask your host to enable the free SSL certificate — or type your order below.', 'support-note');
    fallback(T('Voice needs a secure page (HTTPS, or http://localhost). Please type your order below.', 'குரலுக்கு பாதுகாப்பான பக்கம் (HTTPS) தேவை. கீழே தட்டச்சு செய்யுங்கள்.'));
  } else {
    $('mic').addEventListener('click', () => (listening || starting ? stop() : start())); $('btn-start').addEventListener('click', start); $('btn-stop').addEventListener('click', stop);
  }

  /* ---------- microphone test: shows a live level meter so you can see whether the browser really hears you ---------- */
  $('mic-test').addEventListener('click', async () => {
    const msg = $('mic-test-msg'), lvl = $('mic-level');
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { msg.textContent = 'This browser cannot access the microphone here (needs HTTPS or localhost).'; return; }
    if (listening || starting) stop();
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true }), track = stream.getAudioTracks()[0], AC = window.AudioContext || window.webkitAudioContext, ac = new AC(), src = ac.createMediaStreamSource(stream), an = ac.createAnalyser(); an.fftSize = 512; src.connect(an);
      const buf = new Uint8Array(an.fftSize); let peak = 0; const t0 = Date.now(); msg.textContent = 'Using “' + (track.label || 'default microphone') + '” — say something for 5 seconds…';
      const tick = () => { an.getByteTimeDomainData(buf); let m = 0; for (const v of buf) m = Math.max(m, Math.abs(v - 128)); const pct = Math.min(100, m / 90 * 100); peak = Math.max(peak, pct); lvl.style.width = pct + '%';
        if (Date.now() - t0 < 5000) requestAnimationFrame(tick); else { stream.getTracks().forEach((x) => x.stop()); ac.close(); lvl.style.width = '0';
          msg.textContent = peak > 12 ? '✓ Microphone works (“' + (track.label || 'default') + '”). Now tap Start Speaking.' : '✗ No sound heard from “' + (track.label || 'default microphone') + '”. Pick the correct microphone in Windows Settings → System → Sound → Input (make sure it is not muted), and in Chrome → Settings → Privacy → Site settings → Microphone. Or type your order below.'; } };
      tick();
    } catch (e) { msg.textContent = '✗ Cannot open the microphone: ' + (e && e.name === 'NotAllowedError' ? 'permission blocked — click the lock icon in the address bar and allow Microphone.' : e && e.name === 'NotFoundError' ? 'no microphone found.' : (e && e.message || e)); }
  });

  /* ---------- typed fallback + editable transcript (same parser, same result panel) ---------- */
  $('typed-form').addEventListener('submit', (e) => { e.preventDefault(); const v = $('typed').value; $('typed').value = ''; process(v); });
  $('heard').addEventListener('input', () => { $('heard-fix').disabled = !$('heard').value.trim() || busy; });
  $('heard').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); process($('heard').value, { fix: true }); } });
  $('heard-fix').addEventListener('click', () => process($('heard').value, { fix: true }));
  document.addEventListener('click', async (e) => {
    const s = e.target.closest('[data-suggest]'); if (!s) return;
    const j = await FC.add(+s.dataset.suggest, 1, 'voice_order'); if (j.ok) { s.remove(); window.FW && FW.burstAt(s, { count: 30, power: 2.8 }); }
  });
  $('manual-add').addEventListener('click', async () => {
    const sel = $('manual-product'), n = Math.max(1, +$('manual-qty').value || 1); if (!sel.value) { FC.toast('Choose a product first', 'error'); return; }
    await FC.add(+sel.value, n, 'voice_order');
  });
  setState(false);
})();
