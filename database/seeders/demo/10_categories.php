<?php
use App\Core\DB;
return function (): string {
    $c = [['Flower Pots', 'பூந்தொட்டி', 'Fountain-style crackers that bloom into colourful sparks.'], ['Sparklers', 'கம்பி மத்தாப்பு', 'Hand-held sparklers, safe and colourful.'], ['Ground Chakras', 'தரை சக்கரம்', 'Spinning ground wheels with bright sparks.'],
          ['Rockets', 'ராக்கெட்', 'Sky-high rockets with colourful bursts.'], ['Sound Crackers', 'வெடி', 'Classic sound crackers for the festive mood.'], ['Fancy Sky Shots', 'வான வேடிக்கை', 'Multi-shot aerial fireworks.'],
          ['Kids Special', 'சிறுவர் சிறப்பு', 'Low-noise, kid-friendly range (adult supervision required).'], ['Gift Boxes', 'கிப்ட் பாக்ஸ்', 'Family combo packs at special prices.']];
    $n = 0; foreach ($c as $i => [$name, $ta, $d]) if (!DB::val('SELECT 1 FROM categories WHERE slug=?', [slugify($name)])) { DB::insert('categories', ['name' => $name, 'slug' => slugify($name), 'description' => $d . ' (' . $ta . ')', 'display_order' => $i + 1, 'is_active' => 1]); $n++; }
    return "$n categories";
};
