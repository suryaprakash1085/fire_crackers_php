<?php
use App\Core\DB;
return function (): string {
    DB::exec('INSERT IGNORE INTO email_settings (id, port, encryption) VALUES (1, 587, "tls")');
    $out = [];
    if (!(int)DB::val('SELECT COUNT(*) FROM services')) {
        foreach ([['Home Delivery', 'Safe, compliant delivery to permitted locations after order confirmation.', 'truck', 'Where permitted by law'], ['Bulk & Event Orders', 'Special pricing for weddings, temples, societies and events.', 'package', 'On request'], ['Gift Boxes', 'Curated family gift boxes for every budget.', 'gift', 'Year-round'], ['Safety Guidance', 'Free guidance on product use and safe celebration setups.', 'shield-check', 'Call or WhatsApp']] as $i => [$t, $d, $ic, $av])
            DB::insert('services', ['title' => $t, 'description' => $d, 'icon' => $ic, 'availability' => $av, 'display_order' => $i + 1, 'is_active' => 1]);
        $out[] = '4 services';
    }
    if (!(int)DB::val('SELECT COUNT(*) FROM banners')) {
        foreach ([['Light Up Your Festival', 'Premium crackers, safely packed', 'Up to 40% off on gift boxes', 'Shop Now', '/products'], ['Order by Voice', 'Say your whole order once — in English or Tamil', 'Try Voice Order', 'Voice Order', '/voice-order'], ['Quick Order', 'Know the product IDs? Order in seconds', 'Fast checkout', 'Quick Order', '/quick-order']] as $i => [$t, $s, $p, $l, $u])
            DB::insert('banners', ['title' => $t, 'subtitle' => $s, 'promo_text' => $p, 'cta_label' => $l, 'cta_url' => $u, 'display_order' => $i + 1, 'is_active' => 1]);
        $out[] = '3 banners';
    }
    return $out ? implode(', ', $out) : 'already present';
};
