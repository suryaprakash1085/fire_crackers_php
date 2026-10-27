<?php
use App\Core\DB;
return function (): string {
    // sku, name, tamil name, category, MRP, selling price, stock, aliases (English + Tamil, used by Voice Order), featured, popular
    $p = [
     ['FP101', 'Flower Pot Big', 'பூந்தொட்டி பெரியது', 'Flower Pots', 120, 96, 200, 'flower pot,flower pots,big flower pot,poo thotti,பூந்தொட்டி,பூத்தொட்டி', 1, 1],
     ['FP102', 'Flower Pot Deluxe', 'பூந்தொட்டி டீலக்ஸ்', 'Flower Pots', 220, 176, 120, 'deluxe flower pot,பூந்தொட்டி டீலக்ஸ்', 1, 0],
     ['FP103', 'Flower Pot Mini (10 pcs)', 'பூந்தொட்டி சிறியது', 'Flower Pots', 90, 72, 150, 'mini flower pot,small flower pot', 0, 0],
     ['SP201', 'Sparklers Pack (10 pcs)', 'கம்பி மத்தாப்பு', 'Sparklers', 60, 50, 400, 'sparkler,sparklers,kambi mathappu,mathappu,கம்பி மத்தாப்பு,மத்தாப்பு', 1, 1],
     ['SP202', 'Colour Sparklers (5 pcs)', 'கலர் மத்தாப்பு', 'Sparklers', 80, 64, 250, 'colour sparkler,color sparklers,கலர் மத்தாப்பு', 0, 1],
     ['SP203', 'Long Sparklers (10 pcs)', 'நீண்ட மத்தாப்பு', 'Sparklers', 110, 88, 180, 'long sparkler,நீண்ட மத்தாப்பு', 0, 0],
     ['GC301', 'Ground Chakra (10 pcs)', 'தரை சக்கரம்', 'Ground Chakras', 90, 72, 300, 'ground chakra,ground chakras,chakra,சக்கரம்,தரை சக்கரம்,bhoo chakkaram', 0, 1],
     ['GC302', 'Ground Chakra Special (5 pcs)', 'தரை சக்கரம் ஸ்பெஷல்', 'Ground Chakras', 140, 112, 140, 'special chakra,chakra special', 1, 0],
     ['RK401', 'Colour Rocket (5 pcs)', 'கலர் ராக்கெட்', 'Rockets', 150, 120, 150, 'rocket,rockets,colour rocket,ராக்கெட்', 0, 1],
     ['RK402', 'Whistling Rocket (3 pcs)', 'விசில் ராக்கெட்', 'Rockets', 130, 104, 100, 'whistling rocket,whistle rocket,விசில் ராக்கெட்', 0, 0],
     ['SC501', 'Lakshmi Crackers (50 pcs)', 'லட்சுமி வெடி', 'Sound Crackers', 100, 80, 220, 'lakshmi,lakshmi cracker,lakshmi crackers,லட்சுமி வெடி', 1, 1],
     ['SC502', 'Red Fort Crackers (100 pcs)', 'ரெட் ஃபோர்ட் வெடி', 'Sound Crackers', 160, 128, 130, 'red fort,red fort crackers', 0, 0],
     ['SC503', 'Bijili Crackers (100 pcs)', 'பிஜிலி வெடி', 'Sound Crackers', 70, 56, 260, 'bijili,bijili crackers,பிஜிலி', 0, 1],
     ['SK601', 'Sky Shot 25 Shots', 'வான வேடிக்கை 25', 'Fancy Sky Shots', 650, 520, 60, 'sky shot,25 shot,twenty five shot', 1, 0],
     ['SK602', 'Sky Shot 60 Shots', 'வான வேடிக்கை 60', 'Fancy Sky Shots', 1450, 1160, 35, '60 shot,sixty shot,big sky shot', 1, 0],
     ['KD701', 'Kids Fountain (5 pcs)', 'சிறுவர் புஷ்வானம்', 'Kids Special', 100, 80, 180, 'kids fountain,fountain,புஷ்வானம்', 0, 1],
     ['KD702', 'Kids Pop Pop (20 pcs)', 'பாப் பாப்', 'Kids Special', 40, 32, 300, 'pop pop,pop,பாப் பாப்', 0, 0],
     ['KD703', 'Kids Magic Snake (10 pcs)', 'பாம்பு மாத்திரை', 'Kids Special', 30, 24, 350, 'snake,magic snake,பாம்பு மாத்திரை', 0, 0],
     ['GB801', 'Family Gift Box (25 items)', 'குடும்ப கிப்ட் பாக்ஸ்', 'Gift Boxes', 1200, 900, 40, 'gift box,family gift box,family box,கிப்ட் பாக்ஸ்', 1, 1],
     ['GB802', 'Mega Gift Box (45 items)', 'மெகா கிப்ட் பாக்ஸ்', 'Gift Boxes', 2400, 1700, 25, 'mega gift box,big gift box,மெகா பாக்ஸ்', 1, 0],
     ['GB803', 'Mini Gift Box (12 items)', 'மினி கிப்ட் பாக்ஸ்', 'Gift Boxes', 600, 450, 70, 'mini gift box,small gift box', 0, 1],
    ];
    $n = 0;
    foreach ($p as [$sku, $name, $ta, $cat, $mrp, $sell, $stock, $alias, $feat, $pop]) {
        if (DB::val('SELECT 1 FROM products WHERE sku=?', [$sku])) continue;
        $cid = DB::val('SELECT id FROM categories WHERE name=?', [$cat]); if (!$cid) continue;
        DB::insert('products', ['sku' => $sku, 'name' => $name, 'name_ta' => $ta, 'slug' => slugify($name) . '-' . strtolower($sku), 'category_id' => $cid,
            'description' => "$name — quality tested and safely packed by a licensed manufacturer.", 'safety_notes' => "Use outdoors only. Light at arm's length and move away immediately. Keep children under adult supervision. Keep water nearby.",
            'aliases' => $alias, 'original_price' => $mrp, 'selling_price' => $sell, 'discount_percent' => round(($mrp - $sell) / $mrp * 100, 2), 'stock' => $stock, 'low_stock_threshold' => 10, 'is_featured' => $feat, 'is_popular' => $pop, 'status' => 'active']);
        $n++;
    }
    return "$n products";
};
