CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'customer',
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  category VARCHAR(50) DEFAULT 'other',
  options JSON DEFAULT (JSON_ARRAY()),
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  status VARCHAR(50) DEFAULT 'new',
  total_price DECIMAL(10,2) DEFAULT 0.00,
  table_number VARCHAR(50) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE orders ADD COLUMN IF NOT EXISTS table_number VARCHAR(50) NULL;

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  item_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(50),
  qty INT DEFAULT 1,
  unit_price DECIMAL(10,2) DEFAULT 0.00,
  options_json JSON DEFAULT (JSON_OBJECT()),
  status VARCHAR(50) DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (username, password_hash, role) VALUES
('admin', '$2y$12$ehjP87ph.3/GuG00nWSY6.QTtdCrDZXWma6INBEpNR/7ptY6oiCLG', 'admin')
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role);

INSERT INTO items (name, description, price, category, options) VALUES
('Chicken Rendang', 'Slow-cooked chicken in a rich, aromatic coconut and spice sauce.', 14.90, 'food', '[{"key":"heat","label":"Spice level","type":"select","choices":["Mild","Medium","Hot"]},{"key":"add_egg","label":"Extra egg","type":"checkboxes","choices":["Add egg"]}]'),
('Char Kway Teow', 'Wok-fried flat noodles with soy, egg, prawns, and bean sprouts.', 16.50, 'food', '[{"key":"protein","label":"Protein","type":"select","choices":["Prawns","Chicken","Tofu"]},{"key":"spice","label":"Spice","type":"select","choices":["Normal","Extra spicy"]}]'),
('Barley Lime', 'Chilled barley drink with a bright, refreshing lime finish.', 5.50, 'drink', '[{"key":"sweetness","label":"Sweetness","type":"select","choices":["Less sweet","Normal","Very sweet"]},{"key":"ice","label":"Ice","type":"select","choices":["Less ice","Normal","Extra ice"]}]'),
('Ais Limau', 'A refreshing iced lime drink with a crisp citrus taste.', 4.80, 'drink', '[{"key":"sweetness","label":"Sweetness","type":"select","choices":["Less sweet","Normal","Extra sweet"]},{"key":"ice","label":"Ice","type":"select","choices":["Normal","Extra ice"]}]'),

-- employee_meta stores short public quote and internal notes for staff members (admin-managed)
CREATE TABLE IF NOT EXISTS employee_meta (
  user_id INT PRIMARY KEY,
  job_title VARCHAR(60) DEFAULT NULL,
  quote VARCHAR(255) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  is_on_shift TINYINT(1) DEFAULT 0,
  shift_note VARCHAR(255) DEFAULT NULL,
  clock_in_at DATETIME DEFAULT NULL,
  clock_out_at DATETIME DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE employee_meta
  ADD COLUMN IF NOT EXISTS job_title VARCHAR(60) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS is_on_shift TINYINT(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS shift_note VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS clock_in_at DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS clock_out_at DATETIME DEFAULT NULL;

-- audit_log for recording login/logout and key user actions
CREATE TABLE IF NOT EXISTS audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(100) NOT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
