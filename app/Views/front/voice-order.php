<?php $products = App\Core\DB::all("SELECT id,sku,name FROM products WHERE status='active' AND stock>0 ORDER BY name LIMIT 500"); ?>
<section class="w-full mx-auto px-4 sm:px-6 lg:px-10 2xl:px-16 py-8" id="voice-root">
  <nav class="text-sm muted mb-3" aria-label="Breadcrumb"><a href="<?= e(url('/')) ?>" class="hover:text-primary">Home</a> <span class="mx-1">/</span> <span class="text-ink font-medium">Voice Order</span></nav>
  <h1 class="section-title text-3xl flex items-center gap-2"><?= icon('mic', 'w-7 h-7 text-primary') ?> Voice Order</h1>
  <p class="muted mt-4 max-w-3xl text-sm md:text-base">Tap the microphone <b class="text-ink">once</b> and say your <b class="text-ink">whole order in one go</b> — in English or தமிழ். We add everything to your cart and show clearly what worked. Nothing is ordered until you confirm at checkout.</p>
  <ol class="grid grid-cols-3 gap-2 mt-5 max-w-3xl text-center text-xs sm:text-sm" aria-label="Steps"><?php foreach ([['1', 'Speak your order'], ['2', 'Check the result'], ['3', 'Checkout']] as [$n, $l]): ?><li class="rounded-xl tint-primary py-2.5 px-2 font-semibold"><span class="inline-flex w-6 h-6 rounded-full text-white items-center justify-center mr-1 text-xs" style="background:var(--c-primary)"><?= $n ?></span><?= $l ?></li><?php endforeach; ?></ol>

  <div class="grid xl:grid-cols-[1fr_360px] gap-6 mt-6 items-start">
    <div class="space-y-5 min-w-0">
      <div class="card p-5 md:p-8 text-center relative overflow-hidden">
        <div class="absolute inset-x-0 -top-24 h-48 pointer-events-none" style="background:radial-gradient(ellipse at center,rgb(var(--c-primary-rgb)/.14),transparent 70%)"></div>
        <div class="relative">
          <div class="flex justify-center mb-5"><label class="sr-only" for="lang">Language</label><select id="lang" class="select !w-auto"><option value="en-IN">English (India)</option><option value="ta-IN">தமிழ் (Tamil)</option><option value="en-US">English (US)</option></select></div>
          <button type="button" id="mic" class="mic-btn mx-auto" aria-label="Start or stop listening" aria-pressed="false"><?= icon('mic', 'w-12 h-12') ?></button>
          <p id="state" class="font-semibold mt-5 text-lg" aria-live="polite"></p>
          <div class="wave justify-center mt-2" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
          <div class="flex flex-wrap justify-center gap-2 mt-4"><button type="button" id="btn-start" class="btn btn-primary"><?= icon('mic', 'w-4 h-4') ?> Start Speaking</button><button type="button" id="btn-stop" class="btn btn-outline" disabled><?= icon('square', 'w-4 h-4') ?> Done / Stop Listening</button></div>
          <p class="hint mt-3">It stops by itself when you pause for about 3 seconds.</p>
          <div class="mt-6 text-left"><label class="label" for="heard">What I heard <span class="font-normal muted">(you can correct it and press “Fix &amp; re-add”)</span></label>
            <div class="flex gap-2"><input id="heard" class="input text-base md:text-lg" placeholder="Your words appear here…" autocomplete="off" aria-live="polite"><button type="button" id="heard-fix" class="btn btn-soft shrink-0" disabled><?= icon('wand-sparkles', 'w-4 h-4') ?><span class="hidden sm:inline">Fix &amp; re-add</span></button></div></div>
          <div class="mt-4 text-left rounded-2xl border border-black/10 p-3"><div class="flex flex-wrap items-center gap-3"><button type="button" id="mic-test" class="btn btn-soft btn-sm"><?= icon('audio-lines', 'w-4 h-4') ?> Test my microphone</button><div class="flex-1 min-w-[140px] h-2.5 rounded-full bg-black/10 overflow-hidden"><div id="mic-level" class="h-full rounded-full" style="width:0;background:linear-gradient(90deg,#12b76a,#f79009,#f04438);transition:width .08s"></div></div></div><p id="mic-test-msg" class="hint mt-2"></p></div>
          <p id="voice-debug" class="hint mt-3 text-left font-mono"></p>
          <div id="support-note" class="hidden mt-4 text-sm rounded-xl bg-amber-50 text-amber-900 p-3 text-left"></div>
          <p class="hint mt-4 text-left"><?= icon('info', 'w-3.5 h-3.5 inline') ?> Privacy: your browser recognises the speech (Chrome uses Google's speech service for this). We never record or store audio — only the recognised <i>text</i> is sent to our server to match products.</p>
        </div>
      </div>

      <div id="result" class="hidden" aria-live="polite"></div>

      <div class="card p-5"><h2 class="font-bold font-display">Try saying</h2><div class="grid md:grid-cols-2 gap-3 mt-3 text-sm"><p class="rounded-xl bg-black/[.03] p-3 muted">“Add five flower pots, two sparklers and three ground chakras”</p><p class="rounded-xl bg-black/[.03] p-3 muted">“ஐந்து பூந்தொட்டியும் இரண்டு கம்பி மத்தாப்பும் மூன்று தரை சக்கரமும் கார்ட்டில் சேர்”</p></div></div>

      <div class="card p-5"><h2 class="font-bold font-display">Prefer typing? <span class="text-sm font-normal muted">(also the fallback if the microphone is blocked)</span></h2><p id="fallback-note" class="hidden text-sm rounded-xl bg-amber-50 text-amber-900 p-3 mt-3"></p>
        <form id="typed-form" class="flex gap-2 mt-3"><input id="typed" class="input" placeholder="e.g. add 5 flower pots and 2 sparklers" aria-label="Typed order" autocomplete="off"><button class="btn btn-primary shrink-0">Add</button></form>
        <div class="grid sm:grid-cols-[1fr_100px_auto] gap-2 mt-4 items-end"><div><label class="label" for="manual-product">Or choose a product</label><select id="manual-product" class="select"><option value="">Select product…</option><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>)</option><?php endforeach; ?></select></div>
          <div><label class="label" for="manual-qty">Qty</label><input id="manual-qty" type="number" min="1" value="1" class="input"></div><button type="button" id="manual-add" class="btn btn-soft">Add to cart</button></div></div>
    </div>
    <aside class="xl:sticky xl:top-24"><div class="card p-5"><h2 class="font-bold mb-2 flex items-center gap-2 font-display"><?= icon('shopping-bag', 'w-5 h-5 text-primary') ?> Your cart</h2><div data-cart-panel data-mode="compact"><div class="py-8 text-center"><span class="spinner"></span></div></div>
      <a href="<?= e(url('/products')) ?>" class="btn btn-outline w-full mt-3">Continue Shopping</a></div></aside>
  </div>
</section>
<?php $GLOBALS["pageScripts"] = ['assets/js/voice.js']; ?>
