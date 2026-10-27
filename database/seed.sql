-- Firecrackers shop: DEFAULT DATA (settings, pages, services, banners). INSERT IGNORE = never overwrites your edits.
-- Import:  mysql.exe -u USER -pPASS DBNAME < database\seed.sql
SET NAMES utf8mb4;


INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('brand_name','Sri Murugan Crackers'),
('bulk_discount_min','0'),
('bulk_discount_percent','0'),
('business_hours','Mon–Sat 9:00 AM – 7:00 PM'),
('color_accent','#F59F00'),
('color_bg','#F8FAFC'),
('color_button','#E8590C'),
('color_button_text','#FFFFFF'),
('color_primary','#E8590C'),
('color_secondary','#7048E8'),
('color_text','#1F2937'),
('contact_address','Main Road, Sivakasi, Tamil Nadu, India'),
('contact_email','sales@example.com'),
('contact_phone','+91 90000 00000'),
('delivery_charge','80'),
('favicon',''),
('footer_style','tinted'),
('footer_text','Quality firecrackers, safe packaging and reliable delivery for every festival.'),
('free_delivery_above','2000'),
('header_style','white'),
('license_info','Licensed under the Explosives Rules. Licence No: (set in Admin → Website Content)'),
('logo',''),
('max_qty_per_item','500'),
('min_order_amount','3000'),
('offer_1_text','Save big on family combo packs'),
('offer_1_title','Gift Box Combos'),
('offer_2_text','Low-noise sparklers & fountains'),
('offer_2_title','Kids Safe Range'),
('offer_3_text','Extra discount on large orders'),
('offer_3_title','Bulk Orders'),
('payment_cod_enabled','1'),
('payment_cod_label','Pay on Delivery / Pay at Pickup'),
('payment_upi_enabled','0'),
('payment_upi_instructions','Our team will contact you on your mobile number with payment details after confirming the order.'),
('payment_upi_label','UPI / Bank Transfer (pay after order confirmation)'),
('promo_text','Festival Sale – up to 40% off on selected gift boxes!'),
('restricted_states',''),
('safety_homepage','Always light outdoors, keep water nearby, maintain a safe distance and keep children supervised by an adult.'),
('show_about','1'),
('show_categories','1'),
('show_contact','1'),
('show_featured','1'),
('show_new','1'),
('show_offers','1'),
('show_popular','1'),
('show_safety','1'),
('show_services','1'),
('social_facebook',''),
('social_instagram',''),
('social_youtube',''),
('tagline','Light up every celebration safely'),
('whatsapp_number','');

INSERT IGNORE INTO `pages` (`id`, `slug`, `title`, `content`, `image`, `updated_at`) VALUES (1,'about','About Us','<h2>Who we are</h2><p>We are a family-run firecrackers business serving customers with certified, quality products. Edit this text from Admin → Website Content.</p><h2>Our history</h2><p>Started as a small shop, we have grown by putting safety and customer trust first.</p><h2>Mission</h2><p>To bring joy to every celebration safely and responsibly.</p><h2>Vision</h2><p>To be the most trusted festive fireworks supplier in our region.</p><h2>Quality commitment</h2><p>Every product is sourced from licensed manufacturers and stored and packed according to safety norms.</p><h2>Customer service</h2><p>Our team is available during business hours by phone, WhatsApp and email.</p>',NULL,'2026-10-22 07:29:15'),
(2,'privacy','Privacy Policy','<p>We collect only the information needed to process and deliver your order (name, mobile number, address, optional email). We do not sell your data. Voice Order uses your browser\'s speech recognition; we never record or store audio — only the recognised text is sent to our server to match products.</p><p>Edit this policy from the Admin Panel to match your legal requirements.</p>',NULL,'2026-10-22 07:29:15'),
(3,'terms','Terms and Conditions','<p>By placing an order you confirm that you are 18 years of age or older and that fireworks are legal for you to purchase and use at your delivery location.</p><p>Prices and availability may change. Orders are subject to confirmation. Edit these terms from the Admin Panel.</p>',NULL,'2026-10-22 07:29:15'),
(4,'shipping','Shipping and Delivery Policy','<p>Fireworks are classified as hazardous goods and can be shipped only to locations where sale and delivery are permitted. Delivery to restricted states or areas is not available. Delivery timelines are confirmed after order confirmation.</p>',NULL,'2026-10-22 07:29:15'),
(5,'refund','Cancellation and Refund Policy','<p>Orders may be cancelled before they are packed. Damaged or incorrect items must be reported within 48 hours of delivery with photographs. Refund timelines are described here – edit from the Admin Panel.</p>',NULL,'2026-10-22 07:29:15'),
(6,'safety','Firecracker Safety Guidelines','<ul><li>Use fireworks only outdoors, in open areas, away from buildings and vehicles.</li><li>Adults must supervise children at all times.</li><li>Keep a bucket of water or sand nearby.</li><li>Light one firework at a time and move away immediately.</li><li>Never relight a dud; douse it with water.</li><li>Wear cotton clothing and avoid loose synthetic fabrics.</li><li>Store fireworks in a cool, dry place away from heat and flames.</li></ul>',NULL,'2026-10-22 07:29:15');

INSERT IGNORE INTO `services` (`id`, `title`, `description`, `image`, `icon`, `availability`, `display_order`, `is_active`) VALUES (1,'Home Delivery','Safe, compliant delivery to permitted locations after order confirmation.',NULL,'truck','Where permitted by law',1,1),
(2,'Bulk & Event Orders','Special pricing for weddings, temples, societies and events.',NULL,'package','On request',2,1),
(3,'Gift Boxes','Curated family gift boxes for every budget.',NULL,'gift','Year-round',3,1),
(4,'Safety Guidance','Free guidance on product use and safe celebration setups.',NULL,'shield-check','Call or WhatsApp',4,1);

INSERT IGNORE INTO `banners` (`id`, `title`, `subtitle`, `promo_text`, `image`, `cta_label`, `cta_url`, `display_order`, `is_active`, `starts_at`, `ends_at`) VALUES (1,'Light Up Your Festival','Premium crackers, safely packed','Up to 40% off on gift boxes',NULL,'Shop Now','/products',1,1,NULL,NULL),
(2,'Order by Voice','Say your whole order once — in English or Tamil','Try Voice Order',NULL,'Voice Order','/voice-order',2,1,NULL,NULL),
(3,'Quick Order','Know the product IDs? Order in seconds','Fast checkout',NULL,'Quick Order','/quick-order',3,1,NULL,NULL);

INSERT IGNORE INTO `email_settings` (`id`, `sender_name`, `sender_email`, `host`, `port`, `encryption`, `username`, `password_enc`) VALUES (1,NULL,NULL,NULL,587,'tls',NULL,NULL);


