-- ============================================================
--  ungetrunken – Warenwirtschaft für Zeltlager
--  Schema (MySQL 8 / MariaDB 10.6+)
-- ============================================================

SET NAMES utf8mb4;

-- ---------- Ausgabestellen (Lager, Theken, Bars, Cafés) ------
CREATE TABLE outlets (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(128) NOT NULL,
  kind        ENUM('zentrallager','theke','bar','cafe','extern') NOT NULL DEFAULT 'theke',
  is_central  TINYINT(1) NOT NULL DEFAULT 0,
  note        VARCHAR(255) NULL,
  active      TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_outlet_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Benutzer ----------------------------------------
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(64) NOT NULL,
  display_name  VARCHAR(128) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','user','theke') NOT NULL DEFAULT 'user',
  outlet_id     INT NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_username (username),
  CONSTRAINT fk_user_outlet FOREIGN KEY (outlet_id) REFERENCES outlets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Warengruppen ------------------------------------
CREATE TABLE categories (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  name   VARCHAR(96) NOT NULL,
  kind   ENUM('getraenk','lebensmittel','technik','gas','geraet','verbrauch','sonstiges')
         NOT NULL DEFAULT 'sonstiges',
  sort   INT NOT NULL DEFAULT 100,
  UNIQUE KEY uq_cat_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Artikel -----------------------------------------
-- ek_price und alle Bestände beziehen sich IMMER auf base_unit
-- (z. B. 1 Flasche, 1 Stück, 1 kg). Gebinde rechnen darauf um.
CREATE TABLE products (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  artikelnr    VARCHAR(32) NOT NULL,
  name         VARCHAR(160) NOT NULL,
  category_id  INT NULL,
  base_unit    VARCHAR(24) NOT NULL DEFAULT 'Stück',
  ek_price     DECIMAL(10,4) NOT NULL DEFAULT 0,
  deposit      DECIMAL(10,2) NOT NULL DEFAULT 0,
  min_stock    DECIMAL(12,3) NOT NULL DEFAULT 0,
  is_asset     TINYINT(1) NOT NULL DEFAULT 0,
  note         VARCHAR(255) NULL,
  active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_artikelnr (artikelnr),
  KEY ix_prod_cat (category_id),
  CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Gebinde (Palette / Kasten / Einzelflasche) -------
-- deposit hier ist das Pfand auf das Gebinde selbst (z. B. Kastenpfand)
-- und kommt zum Pfand je Basiseinheit aus products.deposit hinzu.
CREATE TABLE packagings (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  product_id  INT NOT NULL,
  name        VARCHAR(96) NOT NULL,
  factor      DECIMAL(12,3) NOT NULL DEFAULT 1,
  ean         VARCHAR(32) NULL,
  ek_price    DECIMAL(10,2) NULL,
  deposit     DECIMAL(10,2) NOT NULL DEFAULT 0,
  is_default  TINYINT(1) NOT NULL DEFAULT 0,
  KEY ix_pack_prod (product_id),
  UNIQUE KEY uq_pack_ean (ean),
  CONSTRAINT fk_pack_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Buchungsköpfe -----------------------------------
CREATE TABLE bookings (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  beleg          VARCHAR(32) NULL,
  type           ENUM('zubuchung','ausgabe','rueckbuchung','umbuchung','abbuchung','inventur') NOT NULL,
  booking_date   DATE NOT NULL,
  from_outlet_id INT NULL,
  to_outlet_id   INT NULL,
  issued_by      VARCHAR(128) NULL,
  received_by    VARCHAR(128) NULL,
  beleg_text     VARCHAR(255) NULL,
  cost_lieferpauschale DECIMAL(10,2) NOT NULL DEFAULT 0,
  cost_energie         DECIMAL(10,2) NOT NULL DEFAULT 0,
  cost_frei_label      VARCHAR(64) NULL,
  cost_frei_amount     DECIMAL(10,2) NOT NULL DEFAULT 0,
  user_id        INT NOT NULL,
  status         ENUM('entwurf','gebucht','storniert') NOT NULL DEFAULT 'entwurf',
  storno_of_id   INT NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  booked_at      DATETIME NULL,
  UNIQUE KEY uq_beleg (beleg),
  KEY ix_book_status (status, booking_date),
  CONSTRAINT fk_book_from FOREIGN KEY (from_outlet_id) REFERENCES outlets(id),
  CONSTRAINT fk_book_to   FOREIGN KEY (to_outlet_id)   REFERENCES outlets(id),
  CONSTRAINT fk_book_user FOREIGN KEY (user_id)        REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Buchungspositionen ------------------------------
CREATE TABLE booking_items (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  booking_id   INT NOT NULL,
  pos          INT NOT NULL DEFAULT 1,
  product_id   INT NOT NULL,
  packaging_id INT NULL,
  qty_packs    DECIMAL(12,3) NOT NULL DEFAULT 0,
  qty_base     DECIMAL(12,3) NOT NULL DEFAULT 0,
  ek_price     DECIMAL(10,4) NOT NULL DEFAULT 0,
  KEY ix_bi_booking (booking_id),
  KEY ix_bi_prod (product_id),
  CONSTRAINT fk_bi_booking FOREIGN KEY (booking_id)   REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_bi_prod    FOREIGN KEY (product_id)   REFERENCES products(id),
  CONSTRAINT fk_bi_pack    FOREIGN KEY (packaging_id) REFERENCES packagings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Lagerbewegungen (Kontenbuch, unveränderlich) -----
CREATE TABLE stock_movements (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  product_id      INT NOT NULL,
  outlet_id       INT NOT NULL,
  qty_base        DECIMAL(12,3) NOT NULL,
  booking_id      INT NOT NULL,
  booking_item_id INT NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY ix_mv_prod_outlet (product_id, outlet_id),
  KEY ix_mv_booking (booking_id),
  CONSTRAINT fk_mv_prod    FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_mv_outlet  FOREIGN KEY (outlet_id)  REFERENCES outlets(id),
  CONSTRAINT fk_mv_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Bestellungen der Theken --------------------------
CREATE TABLE orders (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  outlet_id   INT NOT NULL,
  user_id     INT NOT NULL,
  note        VARCHAR(255) NULL,
  needed_at   DATE NULL,
  status      ENUM('offen','in_arbeit','geliefert','abgelehnt') NOT NULL DEFAULT 'offen',
  booking_id  INT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY ix_ord_status (status),
  CONSTRAINT fk_ord_outlet FOREIGN KEY (outlet_id) REFERENCES outlets(id),
  CONSTRAINT fk_ord_user   FOREIGN KEY (user_id)   REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  order_id     INT NOT NULL,
  product_id   INT NOT NULL,
  packaging_id INT NULL,
  qty_packs    DECIMAL(12,3) NOT NULL DEFAULT 0,
  KEY ix_oi_order (order_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_prod  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Bestand als View --------------------------------
CREATE OR REPLACE VIEW v_stock AS
SELECT m.product_id, m.outlet_id, SUM(m.qty_base) AS qty_base
FROM stock_movements m
GROUP BY m.product_id, m.outlet_id;
