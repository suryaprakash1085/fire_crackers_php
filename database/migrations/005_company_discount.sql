-- Global company discount (%). Stored ONLY here; every product price is calculated from it live.
INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('company_discount_percent', '50');
