-- Tamil display name for products (shown on the shop and used by Voice Order matching)
ALTER TABLE products ADD COLUMN name_ta VARCHAR(190) NULL AFTER name;
