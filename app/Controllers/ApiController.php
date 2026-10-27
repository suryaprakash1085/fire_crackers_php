<?php
namespace App\Controllers;

use App\Core\{Controller, RateLimiter};
use App\Models\Product;
use App\Services\{CartService, VoiceParser};

final class ApiController extends Controller {
    private function cartJson(array $extra = []): never { $this->json(['ok' => true] + $extra + ['cart' => CartService::summary()]); }
    private function src(): string { $s = $this->input('source', 'cart'); return in_array($s, ['quick_order', 'voice_order'], true) ? $s : 'cart'; }

    public function cart(): void { $this->cartJson(); }
    public function add(): void {
        $this->requireCsrf(true);
        $r = CartService::add((int)$this->input('product_id'), max(1, (int)$this->input('qty', '1')), $this->src());
        $this->json($r + ['cart' => CartService::summary()], $r['ok'] ? 200 : 422);
    }
    /** Quick Order: add many products in one request. items = JSON [{id, qty}, …]. Each line is validated server-side. */
    public function addMany(): void {
        $this->requireCsrf(true);
        if (RateLimiter::tooMany('cart_many', client_ip(), 60, 600)) $this->json(['ok' => false, 'message' => 'Too many requests. Please slow down.'], 429);
        RateLimiter::hit('cart_many', client_ip());
        $raw = json_decode($this->input('items', '[]'), true);
        if (!is_array($raw) || !$raw) $this->json(['ok' => false, 'message' => 'Choose at least one product quantity.'], 422);
        $results = []; $added = 0;
        foreach (array_slice($raw, 0, 400) as $it) {
            $id = (int)($it['id'] ?? 0); $qty = (int)($it['qty'] ?? 0);
            if ($id < 1 || $qty < 1) continue;
            $r = CartService::add($id, min($qty, 100000), 'quick_order');
            if (($r['added'] ?? 0) > 0) $added++;
            $results[] = ['id' => $id, 'name' => $r['name'] ?? '', 'added' => (int)($r['added'] ?? 0), 'status' => $r['status'] ?? 'unavailable', 'message' => $r['message'] ?? ''];
        }
        $this->json(['ok' => $added > 0, 'added_count' => $added, 'results' => $results, 'cart' => CartService::summary()]);
    }
    public function update(): void {
        $this->requireCsrf(true);
        $r = CartService::set((int)$this->input('product_id'), (int)$this->input('qty'));
        $this->json($r + ['cart' => CartService::summary()]);
    }
    public function remove(): void { $this->requireCsrf(true); CartService::remove((int)$this->input('product_id')); $this->cartJson(['message' => 'Removed.']); }
    public function clear(): void { $this->requireCsrf(true); CartService::clear(); $this->cartJson(['message' => 'Cart cleared.']); }

    public function lookup(): void {
        $q = mb_substr($this->input('q'), 0, 60);
        if (mb_strlen($q) < 1) $this->json(['ok' => true, 'products' => []]);
        $out = array_map(fn($p) => ['id' => (int)$p['id'], 'sku' => $p['sku'], 'name' => $p['name'], 'price' => (float)$p['selling_price'], 'original' => (float)$p['original_price'], 'stock' => (int)$p['stock'], 'image' => $p['image'] ? upload_url($p['image']) : ''], Product::lookup($q, 8));
        $this->json(['ok' => true, 'products' => $out]);
    }

    private function vmsg(string $lang, string $k, array $v = []): string {
        $en = ['added' => '{n} × {name} added', 'reduced' => 'Only {limit} of {name} in stock — {added} added ({in_cart} now in your cart)', 'reduced0' => '{name}: you already have the maximum available ({limit}) in your cart', 'out' => '{name} is out of stock — not added',
               'all' => 'All {n} item(s) added to your cart.', 'some' => '{ok} of {n} item(s) added. Please check the items marked below.', 'none' => 'Nothing was added. Please check the notes below.', 'nomatch' => 'I could not find any product in what you said. Try again, or type it below.'];
        $ta = ['added' => '{name} × {n} சேர்க்கப்பட்டது', 'reduced' => '{name} இருப்பு {limit} மட்டுமே — {added} சேர்க்கப்பட்டது (கார்ட்டில் {in_cart})', 'reduced0' => '{name}: கிடைக்கும் அதிகபட்சம் ({limit}) ஏற்கனவே கார்ட்டில் உள்ளது', 'out' => '{name} கையிருப்பில் இல்லை — சேர்க்கப்படவில்லை',
               'all' => 'எல்லா {n} பொருட்களும் கார்ட்டில் சேர்க்கப்பட்டன.', 'some' => '{n} இல் {ok} பொருட்கள் சேர்க்கப்பட்டன. கீழே குறிக்கப்பட்டவற்றைப் பார்க்கவும்.', 'none' => 'எதுவும் சேர்க்கப்படவில்லை. கீழே உள்ள குறிப்புகளைப் பார்க்கவும்.', 'nomatch' => 'நீங்கள் சொன்னதில் பொருள் எதுவும் கிடைக்கவில்லை. மீண்டும் சொல்லுங்கள் அல்லது கீழே தட்டச்சு செய்யுங்கள்.'];
        $t = ($lang === 'ta' ? $ta : $en)[$k];
        foreach ($v as $a => $b) $t = str_replace('{' . $a . '}', (string)$b, $t);
        return $t;
    }

    /** One-shot full voice order: the WHOLE spoken sentence is parsed once; every matched product goes straight into the
     *  shared cart. Each item gets a clear status (added / reduced / out of stock). Never places an order. */
    public function voice(): void {
        $this->requireCsrf(true);
        if (RateLimiter::tooMany('voice', client_ip(), 120, 600)) $this->json(['ok' => false, 'message' => 'Too many requests. Please slow down.'], 429);
        RateLimiter::hit('voice', client_ip());
        $lang = $this->input('lang') === 'ta' ? 'ta' : 'en';
        $text = mb_substr($this->input('text'), 0, 600);
        if ($text === '') $this->json(['ok' => false, 'message' => 'No speech text received.'], 422);
        $r = VoiceParser::parse($text);
        $items = []; $lastAdded = []; $okCount = 0;
        foreach ($r['matches'] as $m) {
            $res = CartService::add($m['product_id'], $m['qty'], 'voice_order');
            $name = $res['name'] ?? ('#' . $m['product_id']); $st = $res['status'] ?? 'unavailable';
            $vars = ['n' => $m['qty'], 'name' => $name, 'limit' => $res['limit'] ?? 0, 'added' => $res['added'] ?? 0, 'in_cart' => $res['in_cart'] ?? 0];
            if ($st === 'added') { $okCount++; $msg = $this->vmsg($lang, 'added', $vars); }
            elseif ($st === 'reduced') { if (($res['added'] ?? 0) > 0) { $okCount++; $msg = $this->vmsg($lang, 'reduced', $vars); } else $msg = $this->vmsg($lang, 'reduced0', $vars); }
            else $msg = $this->vmsg($lang, 'out', $vars);
            if (($res['added'] ?? 0) > 0) $lastAdded[$m['product_id']] = $res['added'];
            $items[] = ['product_id' => $m['product_id'], 'name' => $name, 'requested' => $m['qty'], 'added' => (int)($res['added'] ?? 0), 'status' => in_array($st, ['added', 'reduced', 'out_of_stock']) ? $st : 'out_of_stock', 'message' => $msg, 'guess' => $m['guess'] ?? null];
        }
        $_SESSION['voice_last'] = $lastAdded;
        $total = count($items);
        if (!$total && !$r['unmatched']) $summary = $this->vmsg($lang, 'nomatch');
        elseif ($total && $okCount === $total && !$r['unmatched']) $summary = $this->vmsg($lang, 'all', ['n' => $total]);
        elseif ($okCount > 0) $summary = $this->vmsg($lang, 'some', ['ok' => $okCount, 'n' => $total + count($r['unmatched'])]);
        else $summary = $this->vmsg($lang, 'none');
        $level = ($total && $okCount === $total && !$r['unmatched']) ? 'success' : ($okCount > 0 ? 'warning' : 'error');
        $this->json(['ok' => $okCount > 0, 'level' => $level, 'heard' => $text, 'summary' => $summary, 'items' => $items, 'unmatched' => $r['unmatched'], 'can_undo' => (bool)$lastAdded, 'cart' => CartService::summary()]);
    }
    /** Remove exactly what the last voice command added. */
    public function voiceUndo(): void {
        $this->requireCsrf(true);
        CartService::undoVoice($_SESSION['voice_last'] ?? []); unset($_SESSION['voice_last']);
        $this->cartJson(['message' => 'Last voice order removed from your cart.']);
    }
}
