<?php
use App\Core\DB;
return function (): string {
    $p = [
     ['about', 'About Us', '<h2>Who we are</h2><p>We are a family-run firecrackers business serving customers with certified, quality products. Edit this text from Admin → Website Content.</p><h2>Our history</h2><p>Started as a small shop, we have grown by putting safety and customer trust first.</p><h2>Mission</h2><p>To bring joy to every celebration safely and responsibly.</p><h2>Vision</h2><p>To be the most trusted festive fireworks supplier in our region.</p><h2>Quality commitment</h2><p>Every product is sourced from licensed manufacturers and stored and packed according to safety norms.</p><h2>Customer service</h2><p>Our team is available during business hours by phone, WhatsApp and email.</p>'],
     ['privacy', 'Privacy Policy', "<p>We collect only the information needed to process and deliver your order (name, mobile number, address, optional email). We do not sell your data. Voice Order uses your browser's speech recognition; we never record or store audio — only the recognised text is sent to our server to match products.</p><p>Edit this policy from the Admin Panel to match your legal requirements.</p>"],
     ['terms', 'Terms and Conditions', '<p>By placing an order you confirm that you are 18 years of age or older and that fireworks are legal for you to purchase and use at your delivery location.</p><p>Prices and availability may change. Orders are subject to confirmation. Edit these terms from the Admin Panel.</p>'],
     ['shipping', 'Shipping and Delivery Policy', '<p>Fireworks are classified as hazardous goods and can be shipped only to locations where sale and delivery are permitted. Delivery to restricted states or areas is not available. Delivery timelines are confirmed after order confirmation.</p>'],
     ['refund', 'Cancellation and Refund Policy', '<p>Orders may be cancelled before they are packed. Damaged or incorrect items must be reported within 48 hours of delivery with photographs. Refund timelines are described here – edit from the Admin Panel.</p>'],
     ['safety', 'Firecracker Safety Guidelines', '<ul><li>Use fireworks only outdoors, in open areas, away from buildings and vehicles.</li><li>Adults must supervise children at all times.</li><li>Keep a bucket of water or sand nearby.</li><li>Light one firework at a time and move away immediately.</li><li>Never relight a dud; douse it with water.</li><li>Wear cotton clothing and avoid loose synthetic fabrics.</li><li>Store fireworks in a cool, dry place away from heat and flames.</li></ul>'],
    ];
    $n = 0; foreach ($p as [$slug, $title, $html]) $n += DB::exec('INSERT IGNORE INTO pages (slug, title, content) VALUES (?,?,?)', [$slug, $title, $html]);
    return "$n new pages";
};
