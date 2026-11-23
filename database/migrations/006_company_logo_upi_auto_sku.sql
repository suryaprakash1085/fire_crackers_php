-- 006: company logo + GPay/UPI ID settings, default discount, and automatic product SKUs.
--
-- Company details live in the key/value table `site_settings` (there is no separate "company" table), so the
-- new company fields are setting KEYS (INSERT IGNORE = never overwrites a value you already saved):
--   company_logo_path         relative path of the uploaded logo, e.g. uploads/company/ab12....png   ('' = no logo)
--   company_upi_id            GPay / UPI ID used for the invoice QR code (already used by the invoice; kept as the single source)
--   company_discount_percent  "Default discount %" (0-90) that every product price is calculated from
INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('company_logo_path', '');
INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('company_upi_id', '');
INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('company_discount_percent', '50');

-- Automatic SKU: each category owns one SKU prefix (FP, SP, ...) and a hundred-block (1 -> FP101, 2 -> SP201).
-- Existing categories are filled in automatically the first time a product is created in them (from their current SKUs),
-- so existing SKUs are never touched. Guarded with information_schema so the file is safe on MySQL and MariaDB and can be re-run.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'sku_prefix');
SET @s := IF(@c = 0, 'ALTER TABLE categories ADD COLUMN sku_prefix VARCHAR(8) NULL', 'DO 0');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'sku_block');
SET @s := IF(@c = 0, 'ALTER TABLE categories ADD COLUMN sku_block INT UNSIGNED NULL', 'DO 0');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- A prefix can belong to one category only (NULLs are allowed many times). Together with the existing UNIQUE index on products.sku
-- this makes duplicate SKUs impossible even if two admins save at the same instant.
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND INDEX_NAME = 'uq_cat_sku_prefix');
SET @s := IF(@c = 0, 'ALTER TABLE categories ADD UNIQUE INDEX uq_cat_sku_prefix (sku_prefix)', 'DO 0');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
