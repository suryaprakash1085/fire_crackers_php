<?php
namespace App\Services;

use App\Core\DB;
use App\Models\Product;

/**
 * Parses spoken/typed commands (English + Tamil) against ACTIVE database products.
 * Runs entirely on this server: no speech/text is sent to any external AI service.
 */
final class VoiceParser {
    private const EN = ['a' => 1, 'an' => 1, 'one' => 1, 'two' => 2, 'to' => 0, 'three' => 3, 'four' => 4, 'for' => 0, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
        'eleven' => 11, 'twelve' => 12, 'thirteen' => 13, 'fourteen' => 14, 'fifteen' => 15, 'sixteen' => 16, 'seventeen' => 17, 'eighteen' => 18, 'nineteen' => 19, 'twenty' => 20,
        'thirty' => 30, 'forty' => 40, 'fifty' => 50, 'sixty' => 60, 'seventy' => 70, 'eighty' => 80, 'ninety' => 90, 'hundred' => 100, 'dozen' => 12];
    private const TA = ['ஒன்று' => 1, 'ஒரு' => 1, 'ஒண்ணு' => 1, 'இரண்டு' => 2, 'ரெண்டு' => 2, 'மூன்று' => 3, 'மூணு' => 3, 'நான்கு' => 4, 'நாலு' => 4, 'ஐந்து' => 5, 'அஞ்சு' => 5,
        'ஆறு' => 6, 'ஏழு' => 7, 'எட்டு' => 8, 'ஒன்பது' => 9, 'ஒம்பது' => 9, 'பத்து' => 10, 'பதினைந்து' => 15, 'இருபது' => 20, 'இருபத்தி' => 20, 'முப்பது' => 30, 'ஐம்பது' => 50, 'நூறு' => 100];
    private const FILLER = ['of', 'x', 'nos', 'no', 'number', 'numbers', 'piece', 'pc', 'pcs', 'box', 'packet', 'pack', 'set', 'பாக்கெட்', 'பெட்டி', 'எண்ணம்', 'செட்'];
    private const STOP_EN = ['add', 'to', 'cart', 'the', 'and', 'please', 'i', 'want', 'need', 'me', 'give', 'my', 'put', 'in', 'with', 'also', 'then', 'more', 'some', 'order', 'for', 'buy', 'get', 'would', 'like', 'can', 'you', 'it', 'a', 'an', 'of', 'x', 'okay', 'ok', 'thank', 'thanks', 'hello', 'kindly', 'just', 'only', 'now', 'total', 'item', 'product', 'show', 'tell', 'hey', 'sir', 'madam'];
    private const GENERIC = ['big', 'small', 'mini', 'special', 'pack', 'pcs', 'box', 'color', 'colour', 'colored', 'new', 'super', 'normal', 'medium', 'large', 'deluxe', 'set', 'piece', 'pieces', 'combo', 'crackers', 'cracker'];
    private const STOP_TA = ['சேர்', 'கார்ட்', 'கார்டு', 'வேண்டும்', 'எனக்கு', 'உம்', 'மற்றும்', 'மேலும்', 'தயவு', 'போடு', 'போட', 'கொடு', 'வாங்க', 'ஆர்டர்', 'பிளீஸ்', 'நன்றி', 'வணக்கம்', 'சரி'];

    public static function parse(string $text): array {
        $norm = self::normalise($text);
        $tokens = $norm === '' ? [] : explode(' ', $norm);
        $n = count($tokens);
        if (!$n) return ['matches' => [], 'unmatched' => [], 'normalized' => $norm];

        $products = DB::all('SELECT id,sku,name,name_ta,aliases FROM products WHERE status="active" ORDER BY id');
        $cands = [];
        foreach ($products as $p) {
            $terms = [$p['name'], preg_replace('/\s*\(.*?\)\s*/u', ' ', $p['name'])];
            if (!empty($p['name_ta'])) $terms[] = $p['name_ta'];
            foreach (explode(',', (string)$p['aliases']) as $a) if (trim($a) !== '') $terms[] = $a;
            foreach (array_unique($terms) as $term) {
                $tt = array_values(array_filter(explode(' ', self::normalise($term)), 'strlen'));
                if (!$tt) continue;
                $len = count($tt);
                for ($i = 0; $i + $len <= $n; $i++) {
                    if (self::seqMatch($tokens, $i, $tt)) { $cands[] = ['s' => $i, 'l' => $len, 'id' => (int)$p['id'], 'score' => $len * 100 + mb_strlen(implode('', $tt))]; continue; }
                    if ($len > 1 && self::tokMatch($tokens[$i], implode('', $tt))) $cands[] = ['s' => $i, 'l' => 1, 'id' => (int)$p['id'], 'score' => 60 + mb_strlen(implode('', $tt))];
                }
            }
            // PARTIAL matches ("chakra" → Ground Chakra, "flower pot" → Flower Pot Big). Lower score than exact ones, flagged as a guess.
            $nl = mb_strlen($p['name']);
            foreach (array_unique($terms) as $term) {
                $tt = array_values(array_filter(explode(' ', self::normalise($term)), 'strlen')); $m = count($tt); if ($m < 1) continue;
                for ($L = min($m, 3); $L >= 1; $L--) for ($o = 0; $o + $L <= $m; $o++) {
                    if ($L === $m && $m > 1) continue;   // the full term was already tried above
                    $sub = array_slice($tt, $o, $L);
                    if ($L === 1 && (mb_strlen($sub[0]) < 4 || in_array($sub[0], self::GENERIC, true) || self::number($sub[0]) !== null)) continue;
                    if ($L > 1 && !array_diff($sub, self::GENERIC)) continue;
                    for ($i = 0; $i + $L <= $n; $i++) if (self::seqMatch($tokens, $i, $sub)) $cands[] = ['s' => $i, 'l' => $L, 'id' => (int)$p['id'], 'score' => $L * 40 + mb_strlen(implode('', $sub)), 'g' => true, 'nl' => $nl, 'ph' => implode(' ', array_slice($tokens, $i, $L))];
                }
            }
            $sku = preg_replace('/[^a-z0-9]/', '', strtolower($p['sku']));
            for ($i = 0; $i < $n; $i++) for ($w = 1; $w <= 8 && $i + $w <= $n; $w++) {
                if (implode('', array_slice($tokens, $i, $w)) === $sku && $sku !== '') $cands[] = ['s' => $i, 'l' => $w, 'id' => (int)$p['id'], 'score' => 1000 + $w];
            }
        }
        usort($cands, fn($a, $b) => $b['score'] <=> $a['score'] ?: (($a['nl'] ?? 0) <=> ($b['nl'] ?? 0)) ?: $a['id'] <=> $b['id']);
        $used = []; $sel = [];
        foreach ($cands as $c) {
            $ok = true; for ($k = $c['s']; $k < $c['s'] + $c['l']; $k++) if (isset($used[$k])) { $ok = false; break; }
            if (!$ok) continue;
            for ($k = $c['s']; $k < $c['s'] + $c['l']; $k++) $used[$k] = true;
            $sel[] = $c;
        }
        usort($sel, fn($a, $b) => $a['s'] <=> $b['s']);

        // numbers (with compound "twenty five")
        $numEnd = []; $numStart = [];
        for ($i = 0; $i < $n; $i++) {
            if (isset($used[$i])) continue;
            $v = self::number($tokens[$i]); if ($v === null || $v === 0) continue;
            $len = 1;
            if ($v % 10 === 0 && $v >= 20 && $v < 100 && isset($tokens[$i + 1]) && !isset($used[$i + 1])) {
                $u = self::number($tokens[$i + 1]); if ($u !== null && $u >= 1 && $u <= 9) { $v += $u; $len = 2; }
            }
            $numStart[$i] = ['v' => $v, 'end' => $i + $len - 1]; $numEnd[$i + $len - 1] = ['v' => $v, 'start' => $i];
            $i += $len - 1;
        }
        // Quantity assignment. People speak either "5 flower pots, 2 sparklers" (number BEFORE) or "flower pots 5, sparklers 2" (number AFTER).
        // Decide the style from the first product, then use it for every product (falling back to the other side if a number is missing).
        $cand = [];
        foreach ($sel as $k => $c) {
            $j = $c['s'] - 1; $skip = 0; while ($j >= 0 && $skip < 2 && in_array($tokens[$j], self::FILLER, true)) { $j--; $skip++; }
            $bef = ($j >= 0 && isset($numEnd[$j])) ? ['v' => $numEnd[$j]['v'], 'start' => $numEnd[$j]['start'], 'end' => $j] : null;
            $j = $c['s'] + $c['l']; $skip = 0; while ($j < $n && $skip < 1 && in_array($tokens[$j], self::FILLER, true)) { $j++; $skip++; }
            $aft = isset($numStart[$j]) ? ['v' => $numStart[$j]['v'], 'start' => $j, 'end' => $numStart[$j]['end']] : null;
            $cand[$k] = [$bef, $aft];
        }
        $firstK = array_key_first($sel);
        $style = ($firstK !== null && $cand[$firstK][0]) ? 0 : (($firstK !== null && $cand[$firstK][1]) ? 1 : 0);
        $claimed = []; $qty = [];
        foreach ($sel as $k => $c) {
            foreach ([$cand[$k][$style], $cand[$k][1 - $style]] as $num) {
                if (!$num || isset($claimed[$num['start']])) continue;
                $qty[$k] = $num['v']; $claimed[$num['start']] = true;
                for ($z = $num['start']; $z <= $num['end']; $z++) $used[$z] = true;
                break;
            }
        }
        $merged = []; $guess = [];
        foreach ($sel as $k => $c) { $q = max(1, min(999, $qty[$k] ?? 1)); $merged[$c['id']] = ($merged[$c['id']] ?? 0) + $q; if (!empty($c['g'])) $guess[$c['id']] = $c['ph']; }
        $matches = [];
        foreach ($merged as $id => $q) $matches[] = ['product_id' => $id, 'qty' => $q, 'guess' => $guess[$id] ?? null];

        // leftovers → helpful suggestions
        $unmatched = []; $run = [];
        $flush = function () use (&$run, &$unmatched, &$sel) {
            if (!$run) return;
            // leftover size/pack words ("big", "small", "pack") right after a product name are noise, not a missing product
            if (!array_diff($run, self::GENERIC) && $sel) { $run = []; return; }
            $phrase = implode(' ', $run); $best = '';
            foreach ($run as $w) if (mb_strlen($w) > mb_strlen($best)) $best = $w;
            $unmatched[] = ['phrase' => $phrase, 'suggestions' => array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['name'], 'sku' => $p['sku'], 'price' => (float)$p['selling_price'], 'stock' => (int)$p['stock']], Product::lookup(mb_substr($best, 0, 5) ?: $best, 5))];
            $run = [];
        };
        for ($i = 0; $i < $n; $i++) {
            if (isset($used[$i]) || self::isStop($tokens[$i]) || mb_strlen($tokens[$i]) < 3 || self::number($tokens[$i]) !== null) { $flush(); continue; }
            $run[] = $tokens[$i];
        }
        $flush();
        return ['matches' => $matches, 'unmatched' => $unmatched, 'normalized' => $norm];
    }

    private static function normalise(string $s): string {
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[^\p{L}\p{M}\p{N}\s]+/u', ' ', $s);
        $words = preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($words as &$w) if (preg_match('/^[a-z]+$/', $w) && strlen($w) > 3 && str_ends_with($w, 's') && !str_ends_with($w, 'ss')) $w = substr($w, 0, -1);
        return implode(' ', $words);
    }
    private static function tokMatch(string $tok, string $term): bool {
        if ($tok === $term) return true;
        $ascii = preg_match('/^[a-z0-9]+$/', $term) === 1;
        if (!$ascii) { // Tamil: a trailing pulli (்) turns into a vowel sign when a suffix like உம் is attached (சக்கரம் + உம் = சக்கரமும்)
            $stem = str_ends_with($term, "\u{0BCD}") ? mb_substr($term, 0, -1) : $term;
            return mb_strlen($stem) >= 3 && str_starts_with($tok, $stem);
        }
        return strlen($term) >= 6 && abs(strlen($tok) - strlen($term)) <= 1 && levenshtein($tok, $term) <= 1;
    }
    private static function seqMatch(array $tokens, int $i, array $tt): bool {
        foreach ($tt as $k => $t) if (!self::tokMatch($tokens[$i + $k], $t)) return false;
        return true;
    }
    private static function number(string $t): ?int {
        if (preg_match('/^\d{1,4}$/', $t)) return (int)$t;
        if (isset(self::EN[$t]) && !in_array($t, ['to', 'for'], true)) return self::EN[$t];
        if (isset(self::TA[$t])) return self::TA[$t];
        foreach (self::TA as $w => $v) if (mb_strlen($w) >= 4 && str_starts_with($t, $w)) return $v;
        return null;
    }
    private static function isStop(string $t): bool {
        if (in_array($t, self::STOP_EN, true) || in_array($t, self::FILLER, true)) return true;
        foreach (self::STOP_TA as $w) if (str_starts_with($t, $w)) return true;
        return false;
    }
}
