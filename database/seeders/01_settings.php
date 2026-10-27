<?php
use App\Core\DB;
// Default site settings. INSERT IGNORE: never overwrites values you already changed in the Admin Panel.
return function (): string {
    $s = [
        'brand_name' => 'Sri Murugan Crackers', 'tagline' => 'Light up every celebration safely', 'logo' => '', 'favicon' => '',
        'color_primary' => '#E8590C', 'color_secondary' => '#7048E8', 'color_accent' => '#F59F00', 'color_bg' => '#F8FAFC', 'color_text' => '#1F2937', 'color_button' => '#E8590C', 'color_button_text' => '#FFFFFF',
        'header_style' => 'white', 'footer_style' => 'tinted',
        'show_offers' => '1', 'show_categories' => '1', 'show_featured' => '1', 'show_popular' => '1', 'show_new' => '1', 'show_about' => '1', 'show_services' => '1', 'show_safety' => '1', 'show_contact' => '1',
        'promo_text' => 'Festival Sale – up to 40% off on selected gift boxes!',
        'offer_1_title' => 'Gift Box Combos', 'offer_1_text' => 'Save big on family combo packs', 'offer_2_title' => 'Kids Safe Range', 'offer_2_text' => 'Low-noise sparklers & fountains', 'offer_3_title' => 'Bulk Orders', 'offer_3_text' => 'Extra discount on large orders',
        'contact_phone' => '+91 90000 00000', 'contact_email' => 'sales@example.com', 'contact_address' => 'Main Road, Sivakasi, Tamil Nadu, India', 'whatsapp_number' => '', 'business_hours' => 'Mon–Sat 9:00 AM – 7:00 PM',
        'license_info' => 'Licensed under the Explosives Rules. Licence No: (set in Admin → Website Content)', 'footer_text' => 'Quality firecrackers, safe packaging and reliable delivery for every festival.',
        'social_facebook' => '', 'social_instagram' => '', 'social_youtube' => '',
        'delivery_charge' => '80', 'free_delivery_above' => '2000', 'min_order_amount' => '3000', 'bulk_discount_min' => '0', 'bulk_discount_percent' => '0', 'max_qty_per_item' => '500', 'restricted_states' => '',
        'payment_cod_enabled' => '1', 'payment_cod_label' => 'Pay on Delivery / Pay at Pickup', 'payment_upi_enabled' => '0', 'payment_upi_label' => 'UPI / Bank Transfer (pay after order confirmation)',
        'payment_upi_instructions' => 'Our team will contact you on your mobile number with payment details after confirming the order.',
        'safety_homepage' => 'Always light outdoors, keep water nearby, maintain a safe distance and keep children supervised by an adult.',
    ];
    $n = 0; foreach ($s as $k => $v) $n += DB::exec('INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES (?,?)', [$k, $v]);
    return "$n new of " . count($s) . ' settings';
};
