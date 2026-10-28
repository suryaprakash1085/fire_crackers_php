<?php
/**
 * Pure-PHP end-to-end smoke + security test suite.
 *   php tests/smoke_test.php --wipe            (starts its own PHP server on :8099)
 *   php tests/smoke_test.php --wipe --base=http://127.0.0.1:8000   (use an already running server)
 * WARNING: --wipe DROPS ALL TABLES in the database from .env, re-migrates, seeds demo data and creates a test admin.
 * Never run against real data. Refuses to run when APP_ENV=production.
 */
require __DIR__ . '/../app/bootstrap.php';
use App\Core\{DB, Migrator, Seeder};

if (PHP_SAPI !== 'cli') exit("CLI only\n");
if ((string)env('APP_ENV') === 'production') exit("Refusing to run tests with APP_ENV=production\n");
if (!in_array('--wipe', $argv, true)) exit("This test DROPS ALL TABLES in the configured database. Re-run with --wipe to confirm.\n");
$baseOpt = null; foreach ($argv as $a) if (str_starts_with($a, '--base=')) $baseOpt = substr($a, 7);

/* ---------- fresh database ---------- */
Migrator::dropAll(); Migrator::run(); Seeder::run(true, fn($m) => null);
\App\Models\Setting::set('min_order_amount', '0');
DB::insert('admins', ['name' => 'Owner', 'email' => 'owner@example.com', 'password_hash' => password_hash('SuperSecret123', PASSWORD_DEFAULT), 'role' => 'superadmin']);
DB::insert('admins', ['name' => 'Helper', 'email' => 'staff@example.com', 'password_hash' => password_hash('StaffSecret123', PASSWORD_DEFAULT), 'role' => 'staff']);

/* ---------- server ---------- */
$proc = null;
if ($baseOpt) $BASE = rtrim($baseOpt, '/');
else {
    $BASE = 'http://127.0.0.1:8099';
    $proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:8099', '-t', ROOT . '/public', ROOT . '/public/index.php'], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', sys_get_temp_dir() . '/fc-test-server.log', 'w']], $pipes);
    for ($i = 0; $i < 40; $i++) { usleep(150000); if (@file_get_contents($BASE . '/') !== false) break; }
    register_shutdown_function(function () use ($proc) { if (is_resource($proc)) { proc_terminate($proc); } });
}

/* ---------- tiny HTTP client with its own cookie jar ---------- */
final class Http {
    public string $jar;
    public function __construct(private string $base, private bool $follow = true) { $this->jar = tempnam(sys_get_temp_dir(), 'fcj'); }
    public function __destruct() { @unlink($this->jar); }
    /** @return array{s:int,b:string,u:string,loc:?string} */
    public function req(string $method, string $path, array|string|null $data = null, array $headers = []): array {
        $ch = curl_init($this->base . $path); $loc = null;
        $h = []; foreach ($headers as $k => $v) $h[] = "$k: $v";
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $this->follow, CURLOPT_MAXREDIRS => 6, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $h,
            CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$loc) { if (stripos($line, 'Location:') === 0) $loc = trim(substr($line, 9)); return strlen($line); }]);
        if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) && !self::hasFile($data) ? http_build_query($data) : ($data ?? '')); }
        $b = (string)curl_exec($ch); $s = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $u = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); curl_close($ch);
        return ['s' => $s, 'b' => $b, 'u' => $u, 'loc' => $loc];
    }
    private static function hasFile(array $d): bool { foreach ($d as $v) if ($v instanceof CURLFile) return true; return false; }
    public function get(string $p, array $h = []): array { return $this->req('GET', $p, null, $h); }
    public function post(string $p, array|string $d = [], array $h = []): array { return $this->req('POST', $p, $d, $h); }
    public function json(string $method, string $p, array $d = [], array $h = []): array { $r = $method === 'GET' ? $this->get($p, $h) : $this->post($p, $d, $h); return json_decode($r['b'], true) ?? ['_raw' => $r['b'], '_status' => $r['s']]; }
}
$ok = 0; $bad = 0; $verbose = in_array('-v', $argv, true);
function check(string $name, bool $cond, $extra = ''): void { global $ok, $bad, $verbose; if ($cond) { $ok++; if ($verbose) echo "PASS $name\n"; } else { $bad++; echo "FAIL $name " . (is_scalar($extra) ? $extra : json_encode($extra, JSON_UNESCAPED_UNICODE)) . "\n"; } }
function csrf(string $html): string { preg_match('/name="csrf" content="([^"]+)"/', $html, $m); return $m[1] ?? ''; }
function formCsrf(string $html): string { preg_match('/name="_csrf" value="([^"]+)"/', $html, $m); return $m[1] ?? ''; }
function sid(string $sku): int { return (int)DB::val('SELECT id FROM products WHERE sku=?', [$sku]); }
function stock(string $sku): int { return (int)DB::val('SELECT stock FROM products WHERE sku=?', [$sku]); }
function fresh(): array { $c = new Http($GLOBALS['BASE']); $r = $c->get('/'); return [$c, ['X-CSRF-Token' => csrf($r['b'])]]; }
$FP = sid('FP101'); $SP = sid('SP201'); $GC = sid('GC301');

/* ---------- public pages ---------- */
[$c, $H] = fresh();
foreach ([['/', 'Light Up Your Festival'], ['/products', 'Flower Pot Big'], ['/product-details.php?id=' . $FP, 'Safety information'], ['/product-details.php?id=' . $FP, 'பூந்தொட்டி'], ['/quick-order', 'Quick Order'], ['/voice-order', 'Start Speaking'], ['/cart', 'Shopping Cart'],
          ['/about', 'About Us'], ['/services', 'Home Delivery'], ['/policy/privacy', 'Privacy Policy'], ['/policy/safety', 'Safety'], ['/contact', 'Contact Us'], ['/track', 'Track Your Order'], ['/products?category=2', 'Sparklers']] as [$p, $needle]) {
    $r = $c->get($p); check("GET $p", $r['s'] === 200 && str_contains($r['b'], $needle), $r['s']);
}
$r = $c->get('/checkout'); check('empty-cart checkout redirects to products', str_contains($r['u'], '/products'));
check('unknown URL -> 404', $c->get('/nope')['s'] === 404);
check('missing product -> 404', $c->get('/product-details.php?id=99999')['s'] === 404);
$nav = $c->get('/')['b']; foreach (['Home', 'About Us', 'Services', 'Products', 'Quick Order', 'Voice Order', 'Shopping Cart'] as $n) { if (!str_contains($nav, $n)) { check("nav has $n", false); } } check('primary nav complete', true);
check('no customer login/register/search routes', $c->get('/login')['s'] === 404 && $c->get('/register')['s'] === 404 && $c->get('/search')['s'] === 404);

/* ---------- cart API ---------- */
check('API requires CSRF', $c->post('/api/cart/add', ['product_id' => $FP, 'qty' => 2])['s'] === 419);
$j = $c->json('POST', '/api/cart/add', ['product_id' => $FP, 'qty' => 2], $H); check('cart add', ($j['ok'] ?? false) && $j['cart']['count'] === 2, $j);
$j = $c->json('POST', '/api/cart/add', ['product_id' => $SP, 'qty' => 99999], $H); check('qty clamped to stock/max', $j['cart']['items'][1]['qty'] <= 400, $j);
$j = $c->json('POST', '/api/cart/update', ['product_id' => $SP, 'qty' => 4], $H); $q = 0; foreach ($j['cart']['items'] as $i) if ($i['id'] === $SP) $q = $i['qty']; check('cart update', $q === 4);
$j = $c->json('POST', '/api/cart/remove', ['product_id' => $SP], $H); check('cart remove', count($j['cart']['items']) === 1);
$r = $c->post('/api/cart/add', ['product_id' => '1 OR 1=1', 'qty' => 1], $H); check('sqli-ish id harmless', in_array($r['s'], [200, 422], true));
$j = $c->json('GET', '/api/products/lookup?q=' . urlencode("' OR '1'='1")); check('lookup sqli safe', $j['products'] === []);
$j = $c->json('GET', '/api/products/lookup?q=FP101'); check('lookup by SKU', $j['products'][0]['sku'] === 'FP101');
$j = $c->json('GET', '/api/products/lookup?q=%E0%AE%AA%E0%AF%82'); check('lookup by Tamil text', count($j['products']) >= 1);

/* ---------- voice: single commands ---------- */
foreach ([['Add five flower pots and two sparklers.', [$FP => 5, $SP => 2]], ['ஐந்து பூந்தொட்டியும் இரண்டு கம்பி மத்தாப்பும் கார்ட்டில் சேர்.', [$FP => 5, $SP => 2]], ['Add three ground chakras.', [$GC => 3]]] as [$t, $exp]) {
    [$c2, $H2] = fresh(); $j = $c2->json('POST', '/api/voice/parse', ['text' => $t], $H2); $got = []; foreach ($j['cart']['items'] as $i) $got[$i['id']] = $i['qty']; ksort($got); ksort($exp);
    check('voice: ' . mb_substr($t, 0, 28), $got === $exp, $got);
}
$j = $c->json('POST', '/api/voice/parse', ['text' => 'add two diwali bombs'], $H); check('voice unmatched = clear error', !$j['ok'] && $j['unmatched'] && $j['level'] === 'error');
/* ---------- voice: ONE-SHOT full order ---------- */
[$c3, $H3] = fresh();
$j = $c3->json('POST', '/api/voice/parse', ['text' => 'add five flower pots, two sparklers, three ground chakras, one family gift box and four rockets please', 'lang' => 'en'], $H3);
$added = array_sum(array_column($j['items'], 'added'));
check('one-shot: 5 products in ONE sentence', count($j['items']) === 5 && $j['level'] === 'success' && $added === 15 && str_contains($j['summary'], 'All 5'), $j['summary'] ?? '');
check('voice order totals computed server-side', $j['cart']['total'] > 0 && $j['cart']['count'] === 15);
$j2 = $c3->json('POST', '/api/voice/undo', ['x' => 1], $H3); check('voice undo empties cart', $j2['cart']['count'] === 0);
$j = $c3->json('POST', '/api/voice/parse', ['text' => 'ஐந்து பூந்தொட்டியும் இரண்டு கம்பி மத்தாப்பும் மூன்று தரை சக்கரமும் கார்ட்டில் சேர்', 'lang' => 'ta'], $H3);
check('Tamil one-shot 3 items + Tamil messages', count($j['items']) === 3 && str_contains($j['summary'], 'சேர்க்கப்பட்டன'), $j['summary'] ?? '');
$c3->json('POST', '/api/cart/clear', ['x' => 1], $H3);
DB::exec("UPDATE products SET stock=0 WHERE sku='RK401'"); DB::exec("UPDATE products SET stock=3 WHERE sku='GC301'");
$j = $c3->json('POST', '/api/voice/parse', ['text' => 'add ten ground chakras and two rockets and a diwali bomb', 'lang' => 'en'], $H3);
$st = []; foreach ($j['items'] as $i) $st[$i['name']] = $i['status'];
check('issues are clear: reduced + out_of_stock + not found', ($st['Ground Chakra (10 pcs)'] ?? '') === 'reduced' && ($st['Colour Rocket (5 pcs)'] ?? '') === 'out_of_stock' && $j['unmatched'] && $j['level'] === 'warning', [$st, $j['summary'] ?? '']);
$red = array_values(array_filter($j['items'], fn($i) => $i['status'] === 'reduced'))[0] ?? ['message' => ''];
check('reduced message names the stock limit', str_contains($red['message'], 'Only 3'), $red['message']);
$c3->json('POST', '/api/cart/clear', ['x' => 1], $H3); DB::exec("UPDATE products SET stock=150 WHERE sku='RK401'"); DB::exec("UPDATE products SET stock=300 WHERE sku='GC301'");

/* ---------- voice parser: realistic ways people speak (English, Tamil, Tanglish) ---------- */
$ids = []; foreach (DB::all('SELECT id,sku FROM products') as $r0) $ids[(int)$r0['id']] = $r0['sku'];
foreach ([
  ['5 flower pot 2 sparklers 3 ground chakra', ['FP101' => 5, 'SP201' => 2, 'GC301' => 3]], ['flower pots 5 sparklers 2 ground chakras 3', ['FP101' => 5, 'SP201' => 2, 'GC301' => 3]], ['sparklers 2 and flower pots 5', ['SP201' => 2, 'FP101' => 5]],
  ['I want ten lakshmi crackers and five bijili please', ['SC501' => 10, 'SC503' => 5]], ['ten pieces of sparklers and a dozen bijili crackers', ['SP201' => 10, 'SC503' => 12]], ['add family gift box', ['GB801' => 1]],
  ['okay thank you add two sparklers thanks', ['SP201' => 2]], ['hello', []], ['fp 101 3 and sp 201 2', ['FP101' => 3, 'SP201' => 2]], ['F P 1 0 1 five', ['FP101' => 5]], ['add twenty five rockets', ['RK401' => 25]],
  ['two rockets and three whistling rockets', ['RK401' => 2, 'RK402' => 3]], ['பூந்தொட்டி ஐந்து கம்பி மத்தாப்பு இரண்டு', ['FP101' => 5, 'SP201' => 2]], ['ஒரு கிப்ட் பாக்ஸ் சேர்', ['GB801' => 1]],
  ['பத்து லட்சுமி வெடி கார்ட்டில் போடு', ['SC501' => 10]], ['அஞ்சு பிஜிலி வெடியும் ரெண்டு ராக்கெட்டும்', ['SC503' => 5, 'RK401' => 2]], ['five poo thotti and two kambi mathappu', ['FP101' => 5, 'SP201' => 2]],
  ['add 3 sparklers and 4 sparklers', ['SP201' => 7]],
] as [$utt, $exp]) {
    $pr = \App\Services\VoiceParser::parse($utt); $got = []; foreach ($pr['matches'] as $m0) $got[$ids[$m0['product_id']]] = $m0['qty']; ksort($got); ksort($exp);
    check('parser: ' . mb_substr($utt, 0, 40), $got === $exp, $got);
}
$twentyFive = \App\Services\VoiceParser::parse('add twenty five rockets'); $gotTwentyFive = [];
foreach ($twentyFive['matches'] as $match) $gotTwentyFive[$ids[$match['product_id']]] = $match['qty'];
check('voice compound number words are quantity only', $gotTwentyFive === ['RK401' => 25] && $twentyFive['unmatched'] === [], $twentyFive);
check('polite words are not reported as "not found"', \App\Services\VoiceParser::parse('okay thank you hello')['unmatched'] === []);

/* ---------- checkout (as a VOICE order) ---------- */
[$cv, $Hv] = fresh(); $cv->json('POST', '/api/voice/parse', ['text' => 'add 2 sparklers'], $Hv);
$r = $cv->get('/checkout'); check('checkout page', $r['s'] === 200 && str_contains($r['b'], 'Place Order'));
preg_match('/name="checkout_token" value="([^"]+)"/', $r['b'], $m); $tok = $m[1]; $cs = formCsrf($r['b']);
$form = ['_csrf' => $cs, 'checkout_token' => $tok, 'customer_name' => 'Ravi Kumar', 'mobile' => '9876543210', 'email' => '', 'address' => '12 Main Street, Madurai', 'pincode' => '625001', 'city' => 'Madurai', 'state' => 'Tamil Nadu', 'payment_method' => 'cod', 'ack_age' => '1', 'ack_safety' => '1', 'ack_terms' => '1'];
$bf = $form; $bf['mobile'] = '123'; unset($bf['ack_age']);
$r = $cv->post('/checkout', $bf); check('checkout validation errors', str_contains($r['b'], 'valid 10-digit') && str_contains($r['b'], 'confirm you are 18'));
$r = $cv->post('/checkout', ['state' => 'Atlantis'] + $form); check('invalid state rejected', str_contains($r['b'], 'Select your state'));
$r = $cv->post('/checkout', ['_csrf' => 'bad'] + $form); check('checkout CSRF enforced', !str_contains($r['u'], 'order-success'));
DB::exec("UPDATE site_settings SET setting_value='Kerala' WHERE setting_key='restricted_states'"); \App\Models\Setting::all();
$r = $cv->post('/checkout', ['state' => 'Kerala'] + $form); check('restricted state blocked', str_contains($r['b'], 'cannot sell or deliver'));
DB::exec("UPDATE site_settings SET setting_value='' WHERE setting_key='restricted_states'");
$before = stock('SP201');
$r = $cv->post('/checkout', $form); check('order placed -> success page', str_contains($r['u'], 'order-success') && str_contains($r['b'], 'Thank you'), $r['u']);
preg_match('/FC-[A-Z0-9]{5}-[A-Z0-9]{5}/', $r['b'], $m); $ref = $m[0] ?? '';
check('stock reduced by 2', $before - stock('SP201') === 2);
$r = $cv->post('/checkout', $form); check('duplicate submit creates no 2nd order', (int)DB::val('SELECT COUNT(*) FROM orders') === 1);
check('order_source = voice_order', DB::val('SELECT order_source FROM orders LIMIT 1') === 'voice_order');
check('order snapshot stored', (int)DB::val('SELECT COUNT(*) FROM order_items WHERE name IS NOT NULL AND unit_price>0') === 1);
$r = (new Http($BASE))->get('/order-success?ref=' . $ref); check('success page blocked for other sessions', str_contains($r['b'], 'Track Your Order') && !str_contains($r['b'], 'Delivery details'));
/* cart + quick-order sources */
[$cq, $Hq] = fresh(); $cq->json('POST', '/api/cart/add', ['product_id' => $FP, 'qty' => 1, 'source' => 'quick_order'], $Hq); $r = $cq->get('/checkout'); preg_match('/name="checkout_token" value="([^"]+)"/', $r['b'], $m);
$cq->post('/checkout', ['_csrf' => formCsrf($r['b']), 'checkout_token' => $m[1]] + $form); check('order_source = quick_order', DB::val("SELECT order_source FROM orders WHERE order_source='quick_order'") === 'quick_order');
[$cc, $Hc] = fresh(); $cc->json('POST', '/api/cart/add', ['product_id' => $GC, 'qty' => 1], $Hc); $r = $cc->get('/checkout'); preg_match('/name="checkout_token" value="([^"]+)"/', $r['b'], $m);
$cc->post('/checkout', ['_csrf' => formCsrf($r['b']), 'checkout_token' => $m[1]] + $form); check('order_source = cart', DB::val("SELECT order_source FROM orders WHERE order_source='cart'") === 'cart');
/* tracking */
$ct = new Http($BASE); $tt = csrf($ct->get('/track')['b']);
check('track: wrong mobile denied', str_contains($ct->post('/track', ['_csrf' => $tt, 'reference' => $ref, 'mobile' => '9000000000'])['b'], 'No order found'));
check('track: correct shows order', str_contains(($b = $ct->post('/track', ['_csrf' => $tt, 'reference' => $ref, 'mobile' => '9876543210'])['b']), $ref) && str_contains($b, 'Order Placed'));
check('track: guessing order id impossible', str_contains($ct->post('/track', ['_csrf' => $tt, 'reference' => '1', 'mobile' => '9876543210'])['b'], 'No order found'));

/* ---------- admin: access control ---------- */
foreach (['/admin', '/admin/orders', '/admin/products', '/admin/customize', '/admin/smtp', '/admin/products/template', '/admin/products/import', '/admin/users', '/admin/banners'] as $p) { $r = (new Http($BASE, false))->get($p); check("anon blocked $p", $r['s'] === 302 && str_contains((string)$r['loc'], 'login'), $r['s']); }
foreach (['/admin/orders/1/status', '/admin/products/save', '/admin/products/import'] as $p) check("anon POST blocked $p", (new Http($BASE, false))->post($p, [])['s'] === 302);
$a = new Http($BASE); $lt = formCsrf($a->get('/admin/login')['b']);
check('bad login rejected', str_contains($a->post('/admin/login', ['_csrf' => $lt, 'email' => 'owner@example.com', 'password' => 'wrong'])['b'], 'Invalid email or password'));
$r = $a->post('/admin/login', ['_csrf' => $lt, 'email' => 'owner@example.com', 'password' => 'SuperSecret123']); check('admin login ok', str_contains($r['b'], 'Dashboard'));
foreach ([['/admin', 'Recent orders'], ['/admin/orders', 'Voice Order'], ['/admin/products', 'Flower Pot'], ['/admin/products/create', 'Tamil name'], ['/admin/products/' . $FP, 'Units sold'], ['/admin/products/' . $FP . '/edit', 'Voice Order aliases'], ['/admin/categories', 'Flower Pots'], ['/admin/categories/create', 'Category image'],
          ['/admin/banners', 'Light Up'], ['/admin/banners/1/edit', 'Banner image'], ['/admin/services', 'Home Delivery'], ['/admin/enquiries', 'Enquiries'], ['/admin/content', 'Privacy'], ['/admin/products/import', 'Download Excel'], ['/admin/customize', 'Primary colour'], ['/admin/smtp', 'SMTP'], ['/admin/users', 'Admin users'], ['/admin/account', 'Change password']] as [$p, $n]) {
    $r = $a->get($p); check("admin GET $p", $r['s'] === 200 && stripos($r['b'], $n) !== false, $r['s']);
}
$AT = csrf($a->get('/admin')['b']);
/* minimum-order rule */
[$cvMin, $HvMin] = fresh(); $cvMin->json('POST', '/api/cart/add', ['product_id' => $SP, 'qty' => 1], $HvMin);
$checkoutMin = $cvMin->get('/checkout'); preg_match('/name="checkout_token" value="([^"]+)"/', $checkoutMin['b'], $minTokenMatch);
$formMin = array_replace($form, ['_csrf' => formCsrf($checkoutMin['b']), 'checkout_token' => $minTokenMatch[1] ?? '']);
$a->post('/admin/company', ['_csrf' => $AT, 'min_order_amount' => '999999']);
$ordersBeforeMinimum = (int)DB::val('SELECT COUNT(*) FROM orders'); $stockBeforeMinimum = stock('SP201');
$r = $cvMin->post('/checkout', $formMin);
check('minimum order amount blocks below-minimum checkout', str_contains($r['b'], 'Minimum order amount is') && (int)DB::val('SELECT COUNT(*) FROM orders') === $ordersBeforeMinimum && stock('SP201') === $stockBeforeMinimum, $r['b']);
$a->post('/admin/company', ['_csrf' => $AT, 'min_order_amount' => '0']);
/* role-based access: staff cannot open product/settings pages */
$s = new Http($BASE, false); $lt2 = formCsrf($s->get('/admin/login')['b']); $s->post('/admin/login', ['_csrf' => $lt2, 'email' => 'staff@example.com', 'password' => 'StaffSecret123']);
check('staff can open orders', $s->get('/admin/orders')['s'] === 200);
foreach (['/admin/products', '/admin/customize', '/admin/smtp', '/admin/users'] as $p) { $r = $s->get($p); check("staff blocked from $p", $r['s'] === 403 || $r['s'] === 302, $r['s']); }
/* unified order management */
check('filter source=voice_order finds order', str_contains($a->get('/admin/orders?source=voice_order&status=placed')['b'], $ref));
check('filter source=cart excludes voice order', !str_contains($a->get('/admin/orders?source=cart')['b'], $ref));
check('one Orders page lists all three sources', ($b = $a->get('/admin/orders')['b']) && str_contains($b, 'Voice Order') && str_contains($b, 'Quick Order') && str_contains($b, 'Cart'));
check('no separate quick/voice order admin pages', $a->get('/admin/quick-orders')['s'] === 404 && $a->get('/admin/voice-orders')['s'] === 404);
$oid = (int)DB::val('SELECT id FROM orders WHERE reference=?', [$ref]);
check('order view', str_contains($a->get("/admin/orders/$oid")['b'], $ref));
check('status update', str_contains($a->post("/admin/orders/$oid/status", ['_csrf' => $AT, 'status' => 'shipped', 'note' => 'Dispatched via XYZ'])['b'], 'Status updated'));
check('payment save', str_contains($a->post("/admin/orders/$oid/details", ['_csrf' => $AT, 'payment_status' => 'paid', 'tracking_info' => 'LR123', 'internal_notes' => 'ok'])['b'], 'Order details saved'));
$b = $ct->post('/track', ['_csrf' => $tt, 'reference' => $ref, 'mobile' => '9876543210'])['b']; check('admin status visible in tracking', str_contains($b, 'Shipped') && str_contains($b, 'LR123') && str_contains($b, 'Dispatched via XYZ'));
check('admin CSRF enforced', !str_contains($a->post("/admin/orders/$oid/status", ['_csrf' => 'bad', 'status' => 'delivered'])['b'], 'Status updated'));
$r = $a->get('/admin/orders/export'); check('CSV export', str_contains($r['b'], $ref) && str_starts_with($r['b'], "\xEF\xBB\xBF"));
check('print view', str_contains($a->get("/admin/orders/$oid/print")['b'], $ref));
$a->post("/admin/orders/$oid/status", ['_csrf' => $AT, 'status' => 'cancelled', 'note' => 'test']); check('cancel restores stock', stock('SP201') === $before);
check('cancelled order cannot be reopened', str_contains($a->post("/admin/orders/$oid/status", ['_csrf' => $AT, 'status' => 'packed'])['b'], 'cannot be reopened'));
/* products */
$pf = ['_csrf' => $AT, 'sku' => 'tst901', 'name' => 'Test <b>Rocket</b>', 'name_ta' => 'சோதனை', 'category_id' => '1', 'original_price' => '100', 'selling_price' => '80', 'stock' => '5', 'low_stock_threshold' => '10', 'status' => 'active', 'description' => 'd', 'aliases' => 'test rocket'];
$imgPath = sys_get_temp_dir() . '/fc-pending-' . getmypid() . '.png';
file_put_contents($imgPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
$videoPath = sys_get_temp_dir() . '/fc-pending-' . getmypid() . '.mp4';
file_put_contents($videoPath, "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom");
$invalidWithImage = ['selling_price' => '500'] + $pf + [
    'images[0]' => new CURLFile($imgPath, 'image/png', 'pending-product.png'),
    'video' => new CURLFile($videoPath, 'video/mp4', 'pending-product.mp4'),
];
$r = $a->post('/admin/products/save', $invalidWithImage);
preg_match('/name="pending_upload_token" value="([a-f0-9]{32})"/', $r['b'], $pendingMatch); $pendingToken = $pendingMatch[1] ?? '';
check('product form keeps image and video after validation error', $pendingToken !== '' && str_contains($r['b'], 'pending-product.png') && str_contains($r['b'], 'pending-product.mp4') && str_contains($r['b'], 'not more than the original'));
$r = $a->post('/admin/products/save', $pf + ['pending_upload_token' => $pendingToken]);
check('product create (discount computed)', str_contains($r['b'], 'Product saved') && (float)DB::val("SELECT discount_percent FROM products WHERE sku='TST901'") === 20.0);
check('kept image saved after corrected product submit', (int)DB::val("SELECT COUNT(*) FROM product_images i JOIN products p ON p.id=i.product_id WHERE p.sku='TST901'") === 1);
$savedVideo = (string)DB::val("SELECT video_path FROM products WHERE sku='TST901'");
check('kept video saved after corrected product submit', $savedVideo !== '' && is_file(PUBLIC_PATH . '/' . $savedVideo));
@unlink($imgPath); @unlink($videoPath);
$b = (new Http($BASE))->get('/products')['b']; check('XSS escaped in product name', str_contains($b, '&lt;b&gt;Rocket') && !str_contains($b, '<b>Rocket'));
check('selling > original rejected', str_contains($a->post('/admin/products/save', ['selling_price' => '500'] + $pf)['b'], 'not more than the original'));
check('duplicate SKU rejected', str_contains($a->post('/admin/products/save', ['sku' => 'FP101'] + $pf)['b'], 'already exists'));
check('category with products cannot be deleted', str_contains($a->post('/admin/categories/1/delete', ['_csrf' => $AT])['b'], 'has products'));
$a->post('/admin/products/' . sid('TST901') . '/archive', ['_csrf' => $AT]); check('archived product hidden from customers', (new Http($BASE))->get('/product-details.php?id=' . sid('TST901'))['s'] === 404);
$exportFile = tempnam(sys_get_temp_dir(), 'fc_export_');
$defaultExport = $a->get('/admin/products/template'); file_put_contents($exportFile, $defaultExport['b']);
$defaultRows = \App\Services\Xlsx::read($exportFile, 'Products');
$allExport = $a->get('/admin/products/template?include_archived=1'); file_put_contents($exportFile, $allExport['b']);
$allRows = \App\Services\Xlsx::read($exportFile, 'Products');
check('Excel export omits archived products by default', !in_array((string)sid('TST901'), array_column($defaultRows, 0), true));
check('Excel export option includes archived products', in_array((string)sid('TST901'), array_column($allRows, 0), true));
@unlink($exportFile);
/* customization */
$cz = ['_csrf' => $AT, 'brand_name' => 'Test Brand', 'color_primary' => '#112233', 'color_secondary' => '#445566', 'color_accent' => '#778899', 'color_bg' => '#FFFFFF', 'color_text' => '#111111', 'color_button' => '#0A0B0C', 'color_button_text' => '#FFFFFF', 'header_style' => 'white', 'footer_style' => 'white', 'delivery_charge' => '80', 'free_delivery_above' => '2000', 'min_order_amount' => '0', 'bulk_discount_min' => '0', 'bulk_discount_percent' => '0', 'max_qty_per_item' => '500', 'restricted_states' => '', 'payment_cod_enabled' => '1', 'payment_cod_label' => 'COD', 'show_offers' => '1'];
$a->post('/admin/customize', $cz); $b = (new Http($BASE))->get('/')['b']; check('customization propagates (colours + brand)', str_contains($b, '--c-primary:#112233') && str_contains($b, 'Test Brand') && str_contains($b, '--c-button:#0A0B0C'));
$a->post('/admin/customize', ['color_primary' => 'red;}</style><script>x</script>'] + $cz); $b = (new Http($BASE))->get('/')['b']; check('malicious colour not injected', !str_contains($b, '<script>x</script>') && str_contains($b, '--c-primary:#112233'));
/* honest SMTP failure */
check('SMTP test reports failure honestly', ($b = $a->post('/admin/smtp/test', ['_csrf' => $AT, 'test_to' => 'a@b.com'])['b']) && str_contains($b, 'Test failed') && str_contains($b, 'not configured'));
/* Excel template / import */
$x = $a->get('/admin/products/template'); check('Excel template downloads as xlsx', $x['s'] === 200 && str_starts_with($x['b'], 'PK'), $x['s']);
$x = $a->get('/admin/products/template?blank=1'); check('blank Excel template downloads', $x['s'] === 200 && str_starts_with($x['b'], 'PK'));
$tmp = sys_get_temp_dir() . '/fc_imp_' . getmypid() . '.csv'; file_put_contents($tmp, "SKU,Product Name,Category,Original Price,Selling Price,Stock\nXLS-1,Excel Item,Flower Pots,100,80,7\n");
$r = $a->post('/admin/products/import', ['_csrf' => $AT, 'sheet' => new CURLFile($tmp, 'text/csv', 'p.csv')]); check('Excel/CSV import adds product', str_contains($r['b'], 'New products added') && (int)DB::val("SELECT stock FROM products WHERE sku='XLS-1'") === 7);
file_put_contents($tmp, "SKU,Stock\nXLS-1,12\n"); $a->post('/admin/products/import', ['_csrf' => $AT, 'sheet' => new CURLFile($tmp, 'text/csv', 'p.csv')]); check('Excel/CSV import updates stock only', (int)DB::val("SELECT stock FROM products WHERE sku='XLS-1'") === 12 && (float)DB::val("SELECT selling_price FROM products WHERE sku='XLS-1'") == 80.0);
$xlsId = (int)DB::val("SELECT id FROM products WHERE sku='XLS-1'");
$xlsxTmp = sys_get_temp_dir() . '/fc_imp_' . getmypid() . '.xlsx';
$xlsx = \App\Services\Xlsx::build([['name' => 'Products', 'rows' => [
    ['ID', 'SKU', 'Stock', 'Stock at export'], [$xlsId, 'XLS-1', 21, 12], [$xlsId, 'XLS-1', 22, 12],
]]]);
file_put_contents($xlsxTmp, $xlsx);
$r = $a->post('/admin/products/import', ['_csrf' => $AT, 'sheet' => new CURLFile($xlsxTmp, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'products.xlsx')]);
check('real XLSX import reports repeated product IDs', str_contains($r['b'], 'appears more than once in the file') && (int)DB::val("SELECT stock FROM products WHERE sku='XLS-1'") === 21);
file_put_contents($tmp, "ID,SKU,Stock,Stock at export\n$xlsId,XLS-1,3,12\n");
$r = $a->post('/admin/products/import', ['_csrf' => $AT, 'stock_mode' => 'add', 'sheet' => new CURLFile($tmp, 'text/csv', 'p.csv')]);
check('stock Add mode applies signed delta and warns on changed snapshot', (int)DB::val("SELECT stock FROM products WHERE sku='XLS-1'") === 24 && str_contains($r['b'], 'Stock changed by orders after this file was downloaded'));
file_put_contents($tmp, "ID,SKU,Stock\n$xlsId,XLS-1,-2\n");
$a->post('/admin/products/import', ['_csrf' => $AT, 'stock_mode' => 'add', 'sheet' => new CURLFile($tmp, 'text/csv', 'p.csv')]);
check('stock Add mode applies negative adjustment', (int)DB::val("SELECT stock FROM products WHERE sku='XLS-1'") === 22);

/* 1. stock delta with concurrent order */
DB::exec("UPDATE products SET stock=17 WHERE id=$xlsId");
file_put_contents($tmp, "ID,SKU,Stock,Stock at export\n$xlsId,XLS-1,30,22\n");
$r = $a->post('/admin/products/import', ['_csrf' => $AT, 'stock_mode' => 'replace', 'sheet' => new CURLFile($tmp, 'text/csv', 'p.csv')]);
check('stock delta with concurrent order', (int)DB::val("SELECT stock FROM products WHERE id=$xlsId") === 25 && str_contains($r['b'], 'Stock changed by orders after this file was downloaded'));

/* 2. add-mode unedited upload */
file_put_contents($tmp, "ID,SKU,Stock,Stock at export\n$xlsId,XLS-1,25,25\n");
$a->post('/admin/products/import', ['_csrf' => $AT, 'stock_mode' => 'add', 'sheet' => new CURLFile($tmp, 'text/csv', 'p.csv')]);
check('add-mode unedited upload', (int)DB::val("SELECT stock FROM products WHERE id=$xlsId") === 25);

/* 3. inactive category product via voice */
DB::exec("UPDATE categories SET is_active=0 WHERE id=2");
$voiceInactive = \App\Services\VoiceParser::parse('add two sparklers');
$cartInactive = \App\Services\CartService::add($SP, 1);
check('inactive category product via voice', empty($voiceInactive['matches']) && !$cartInactive['ok'] && $cartInactive['status'] === 'unavailable');
DB::exec("UPDATE categories SET is_active=1 WHERE id=2");

/* 4. ID "12.0" */
file_put_contents($tmp, "ID,SKU,Product Name\n{$xlsId}.0,XLS-1,Renamed Excel Item\n");
$r = $a->post('/admin/products/import', ['_csrf' => $AT, 'sheet' => new CURLFile($tmp, 'text/csv', 'p.csv')]);
check('ID "12.0"', DB::val("SELECT name FROM products WHERE id=$xlsId") === 'Renamed Excel Item');

/* 5. numeric SKU */
file_put_contents($tmp, "SKU,Product Name,Category,Original Price,Selling Price,Stock\n00789,Zero Leading Rocket,Flower Pots,150,120,10\n");
$r = $a->post('/admin/products/import', ['_csrf' => $AT, 'sheet' => new CURLFile($tmp, 'text/csv', 'p.csv')]);
check('numeric SKU', DB::val("SELECT sku FROM products WHERE sku='00789'") === '00789' && (int)DB::val("SELECT stock FROM products WHERE sku='00789'") === 10);

/* dry-run preview and confirm flow */
file_put_contents($tmp, "ID,SKU,Product Name,Stock\n$xlsId,XLS-1,Preview Item,40\n");
$rPrev = $a->post('/admin/products/import', ['_csrf' => $AT, 'action' => 'preview', 'sheet' => new CURLFile($tmp, 'text/csv', 'p.csv')]);
preg_match('/name="confirm_token" value="([a-f0-9]{32})"/', $rPrev['b'], $pm);
$cToken = $pm[1] ?? '';
check('dry-run preview does not save prematurely', $cToken !== '' && str_contains($rPrev['b'], 'Preview Import') && DB::val("SELECT name FROM products WHERE id=$xlsId") === 'Renamed Excel Item');
$rConf = $a->post('/admin/products/import', ['_csrf' => $AT, 'action' => 'confirm', 'confirm_token' => $cToken, 'stock_mode' => 'replace']);
check('preview confirm saves data', str_contains($rConf['b'], 'Products updated') && DB::val("SELECT name FROM products WHERE id=$xlsId") === 'Preview Item');

@unlink($tmp);
@unlink($xlsxTmp);
/* ---------- FULL VOICE JOURNEY: ONE sentence -> checkout -> tracking -> admin -> cancel ---------- */
[$jv, $Hj] = fresh(); $want = ['FP101' => 5, 'SP201' => 2, 'GC301' => 3, 'GB801' => 1, 'SC501' => 4]; $sb = []; foreach ($want as $k0 => $_) $sb[$k0] = stock($k0);
$j = $jv->json('POST', '/api/voice/parse', ['text' => 'add five flower pots, two sparklers, three ground chakras, one family gift box and four lakshmi crackers', 'lang' => 'en'], $Hj);
check('journey: voice added all 5 products from one sentence', count($j['items']) === 5 && $j['level'] === 'success', $j['summary'] ?? '');
$sell = 0.0; $mrp = 0.0; foreach ($want as $sku => $q0) { $pr = DB::one('SELECT original_price,selling_price FROM products WHERE sku=?', [$sku]); $sell += $pr['selling_price'] * $q0; $mrp += $pr['original_price'] * $q0; }
$deliv = $sell >= (float)setting('free_delivery_above') ? 0.0 : (float)setting('delivery_charge'); $expTotal = round($sell + $deliv, 2);
check('journey: cart total follows server price + delivery rules', abs($j['cart']['total'] - $expTotal) < 0.01 && abs($j['cart']['delivery'] - $deliv) < 0.01, [$j['cart']['total'], $expTotal]);
$r = $jv->get('/checkout'); preg_match('/name="checkout_token" value="([^"]+)"/', $r['b'], $m);
$r = $jv->post('/checkout', ['_csrf' => formCsrf($r['b']), 'checkout_token' => $m[1], 'mobile' => '9123456780', 'customer_name' => 'Journey Test'] + $form);
preg_match('/FC-[A-Z0-9]{5}-[A-Z0-9]{5}/', $r['b'], $m); $jref = $m[0] ?? ''; $ord = DB::one('SELECT * FROM orders WHERE reference=?', [$jref]) ?: [];
check('journey: order created from voice cart', str_contains($r['u'], 'order-success') && ($ord['order_source'] ?? '') === 'voice_order' && ($ord['payment_status'] ?? '') === 'pending', $r['u']);
check('journey: order money matches (MRP, discount, delivery, total)', abs($ord['subtotal'] - $mrp) < 0.01 && abs($ord['discount_total'] - ($mrp - $sell)) < 0.01 && abs($ord['delivery_charge'] - $deliv) < 0.01 && abs($ord['total'] - $expTotal) < 0.01, $ord);
$got = []; foreach (DB::all('SELECT sku,quantity FROM order_items WHERE order_id=?', [$ord['id']]) as $it) $got[$it['sku']] = (int)$it['quantity']; ksort($got); $w2 = $want; ksort($w2);
check('journey: all 5 order lines with exact quantities', $got === $w2, $got);
$okStock = true; foreach ($want as $k0 => $q0) if ($sb[$k0] - stock($k0) !== $q0) $okStock = false; check('journey: stock reduced for every product', $okStock);
check('journey: cart is empty after the order', $jv->json('GET', '/api/cart')['cart']['count'] === 0);
DB::exec("UPDATE products SET selling_price=1, original_price=1 WHERE sku='FP101'");
$jt = new Http($BASE); $jtt = csrf($jt->get('/track')['b']); $tb = $jt->post('/track', ['_csrf' => $jtt, 'reference' => $jref, 'mobile' => '9123456780'])['b'];
check('journey: price change later does NOT alter the order (snapshot)', str_contains($tb, number_format((float)$ord['total'], 2)) && str_contains($tb, 'Flower Pot Big'));
DB::exec("UPDATE products SET selling_price=96, original_price=120 WHERE sku='FP101'");
check('journey: admin finds it under Voice Order', str_contains($a->get('/admin/orders?source=voice_order&q=' . urlencode($jref))['b'], 'Journey Test'));
$jid = (int)$ord['id']; $vb = $a->get("/admin/orders/$jid")['b']; check('journey: admin order page shows all items', substr_count($vb, 'Lakshmi Crackers') >= 1 && str_contains($vb, 'Family Gift Box') && str_contains($vb, 'Ground Chakra'));
foreach (['confirmed', 'processing', 'packed', 'shipped'] as $stt) { $a->post("/admin/orders/$jid/status", ['_csrf' => $AT, 'status' => $stt, 'note' => '']); }
$tb = $jt->post('/track', ['_csrf' => $jtt, 'reference' => $jref, 'mobile' => '9123456780'])['b']; check('journey: customer sees latest status + history', str_contains($tb, 'Shipped') && (int)DB::val('SELECT COUNT(*) FROM order_status_history WHERE order_id=?', [$jid]) === 5);
$a->post("/admin/orders/$jid/status", ['_csrf' => $AT, 'status' => 'cancelled', 'note' => 'journey cancel']);
$back = true; foreach ($want as $k0 => $q0) if (stock($k0) !== $sb[$k0]) $back = false; check('journey: cancel restores stock for all 5 products', $back);
check('journey: customer sees Cancelled', str_contains($jt->post('/track', ['_csrf' => $jtt, 'reference' => $jref, 'mobile' => '9123456780'])['b'], 'cancelled'));

/* contact form */
$cf = new Http($BASE); $ctk = csrf($cf->get('/contact')['b']);
$cf->post('/contact', ['_csrf' => $ctk, 'name' => 'Asha', 'mobile' => '9876543210', 'message' => 'Do you deliver to my area?']); check('contact enquiry saved', (int)DB::val('SELECT COUNT(*) FROM contact_enquiries') === 1);
check('enquiry visible to admin', str_contains($a->get('/admin/enquiries')['b'], 'Do you deliver'));
/* logout + rate limit */
$a->post('/admin/logout', ['_csrf' => $AT]); check('logout works', str_contains($a->get('/admin/orders')['b'], 'Admin sign in'));
$rl = new Http($BASE); $lt = formCsrf($rl->get('/admin/login')['b']); $last = '';
for ($i = 0; $i < 6; $i++) $last = $rl->post('/admin/login', ['_csrf' => $lt, 'email' => 'owner@example.com', 'password' => 'x' . $i])['b'];
check('login rate-limited after 5 failures', str_contains($last, 'Too many attempts'));

echo "\n$ok passed, $bad failed\n";
if (is_resource($proc)) proc_terminate($proc);
exit($bad ? 1 : 0);
