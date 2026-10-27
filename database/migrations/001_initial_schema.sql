-- Firecrackers E-Commerce — complete schema (MySQL 8 / MariaDB 10.4+)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('staff','admin','superadmin') NOT NULL DEFAULT 'admin',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bucket VARCHAR(40) NOT NULL,
  identifier VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_rl (bucket, identifier, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS media_library (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(190) NOT NULL,
  path VARCHAR(255) NOT NULL UNIQUE,
  mime VARCHAR(80) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
  source ENUM('upload','ai') NOT NULL DEFAULT 'upload',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  description TEXT NULL,
  image VARCHAR(255) NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cat_active (is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(210) NOT NULL UNIQUE,
  category_id INT UNSIGNED NOT NULL,
  description TEXT NULL,
  safety_notes TEXT NULL,
  aliases TEXT NULL COMMENT 'comma separated voice/search aliases incl. Tamil names',
  original_price DECIMAL(10,2) NOT NULL,
  selling_price DECIMAL(10,2) NOT NULL,
  discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  low_stock_threshold INT NOT NULL DEFAULT 10,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_popular TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  KEY idx_prod_status (status, category_id),
  KEY idx_prod_name (name),
  KEY idx_prod_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_pi_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  KEY idx_pi (product_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  description TEXT NULL,
  image VARCHAR(255) NULL,
  icon VARCHAR(60) NOT NULL DEFAULT 'sparkles',
  availability VARCHAR(160) NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_srv (is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS banners (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  subtitle VARCHAR(255) NULL,
  promo_text VARCHAR(255) NULL,
  image VARCHAR(255) NULL,
  cta_label VARCHAR(60) NULL,
  cta_url VARCHAR(255) NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  KEY idx_ban (is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS site_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  title VARCHAR(190) NOT NULL,
  content MEDIUMTEXT NULL,
  image VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(24) NOT NULL UNIQUE,
  order_source ENUM('cart','quick_order','voice_order') NOT NULL DEFAULT 'cart',
  customer_name VARCHAR(120) NOT NULL,
  mobile VARCHAR(15) NOT NULL,
  email VARCHAR(190) NULL,
  address TEXT NOT NULL,
  pincode VARCHAR(10) NOT NULL,
  city VARCHAR(100) NOT NULL,
  state VARCHAR(80) NOT NULL,
  landmark VARCHAR(190) NULL,
  notes TEXT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  discount_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  delivery_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(30) NOT NULL,
  payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  status ENUM('placed','confirmed','processing','packed','shipped','delivered','cancelled') NOT NULL DEFAULT 'placed',
  tracking_info VARCHAR(255) NULL,
  internal_notes TEXT NULL,
  idempotency_token CHAR(64) NOT NULL UNIQUE,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ord_status (status),
  KEY idx_ord_source (order_source),
  KEY idx_ord_created (created_at),
  KEY idx_ord_mobile (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  sku VARCHAR(60) NOT NULL,
  name VARCHAR(190) NOT NULL,
  original_price DECIMAL(10,2) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  KEY idx_oi_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_status_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL,
  note VARCHAR(255) NULL,
  changed_by VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  KEY idx_osh (order_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_enquiries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NULL,
  mobile VARCHAR(15) NULL,
  subject VARCHAR(190) NULL,
  message TEXT NOT NULL,
  status ENUM('new','in_progress','resolved') NOT NULL DEFAULT 'new',
  internal_notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_enq (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  sender_name VARCHAR(120) NULL,
  sender_email VARCHAR(190) NULL,
  host VARCHAR(190) NULL,
  port SMALLINT UNSIGNED NOT NULL DEFAULT 587,
  encryption ENUM('none','tls','ssl') NOT NULL DEFAULT 'tls',
  username VARCHAR(190) NULL,
  password_enc TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_image_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NULL,
  prompt TEXT NOT NULL,
  style VARCHAR(60) NULL,
  aspect VARCHAR(20) NULL,
  quality VARCHAR(20) NULL,
  provider VARCHAR(40) NOT NULL,
  model VARCHAR(60) NOT NULL,
  file_path VARCHAR(255) NULL,
  media_id INT UNSIGNED NULL,
  status ENUM('success','failed') NOT NULL,
  error_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ai (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
