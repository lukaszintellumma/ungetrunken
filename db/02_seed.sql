-- ============================================================
--  Startdaten. Benutzer legst du beim ersten Aufruf selbst an.
-- ============================================================

INSERT INTO outlets (name, kind, is_central) VALUES
  ('Zentrallager',      'zentrallager', 1),
  ('Bar Lagerfeuer',    'bar',          0),
  ('Café Kaffeekanne',  'cafe',         0),
  ('Theke Hauptzelt',   'theke',        0),
  ('Lieferant / extern','extern',       0);

INSERT INTO categories (name, kind, sort) VALUES
  ('Alkoholfreie Getränke', 'getraenk',     10),
  ('Bier & Fassbier',       'getraenk',     20),
  ('Heißgetränke',          'getraenk',     30),
  ('Lebensmittel',          'lebensmittel', 40),
  ('Kühltechnik',           'technik',      50),
  ('Schankanlagen',         'technik',      60),
  ('Gas & CO2',             'gas',          70),
  ('Grill & Küche',         'geraet',       80),
  ('Verbrauchsmaterial',    'verbrauch',    90);

INSERT INTO products (artikelnr, name, category_id, base_unit, ek_price, deposit, min_stock, is_asset) VALUES
  ('1001', 'Mineralwasser Classic 0,5 l', 1, 'Flasche', 0.2500, 0.15, 240, 0),
  ('1002', 'Apfelschorle 0,5 l',          1, 'Flasche', 0.3200, 0.15, 240, 0),
  ('1003', 'Cola 0,33 l',                 1, 'Flasche', 0.4100, 0.15, 120, 0),
  ('2001', 'Pils Fass 30 l',              2, 'Fass',   48.0000, 30.00,   4, 0),
  ('2002', 'Radler 0,33 l',               2, 'Flasche', 0.5500, 0.08, 120, 0),
  ('3001', 'Filterkaffee gemahlen',       3, 'kg',     11.9000, 0.00,   5, 0),
  ('5001', 'Kühlschrank 200 l',           5, 'Stück', 180.0000, 0.00,   0, 1),
  ('5002', 'Zapfanlage 2-leitig',         6, 'Stück', 450.0000, 0.00,   0, 1),
  ('7001', 'CO2-Flasche 10 kg',           7, 'Stück',  85.0000, 50.00,   2, 1),
  ('7002', 'Gasflasche 11 kg Propan',     7, 'Stück',  45.0000, 35.00,   2, 1),
  ('8001', 'Gasgrill 3-flammig',          8, 'Stück', 220.0000, 0.00,   0, 1),
  ('8002', 'Bräter 40 l',                 8, 'Stück',  95.0000, 0.00,   0, 1),
  ('9001', 'Becher 0,3 l Mehrweg',        9, 'Stück',   0.8500, 1.00, 500, 0);

INSERT INTO packagings (product_id, name, factor, deposit, is_default) VALUES
  ((SELECT id FROM products WHERE artikelnr='1001'), 'Einzelflasche',              1,  0.00,   0),
  ((SELECT id FROM products WHERE artikelnr='1001'), 'Kasten 20 × 0,5 l',         20,  3.00,   1),
  ((SELECT id FROM products WHERE artikelnr='1001'), 'Palette 40 × Kasten 20er', 800, 120.00,  0),
  ((SELECT id FROM products WHERE artikelnr='1002'), 'Einzelflasche',              1,  0.00,   0),
  ((SELECT id FROM products WHERE artikelnr='1002'), 'Kasten 20 × 0,5 l',         20,  3.00,   1),
  ((SELECT id FROM products WHERE artikelnr='1002'), 'Palette 40 × Kasten 20er', 800, 120.00,  0),
  ((SELECT id FROM products WHERE artikelnr='1003'), 'Einzelflasche',              1,  0.00,   0),
  ((SELECT id FROM products WHERE artikelnr='1003'), 'Kasten 24 × 0,33 l',        24,  3.30,   1),
  ((SELECT id FROM products WHERE artikelnr='1003'), 'Palette 36 × Kasten 24er', 864, 118.80,  0),
  ((SELECT id FROM products WHERE artikelnr='2001'), 'Fass 30 l',            1,  0.00,   1),
  ((SELECT id FROM products WHERE artikelnr='2002'), 'Einzelflasche',              1,  0.00,   0),
  ((SELECT id FROM products WHERE artikelnr='2002'), 'Kasten 24 × 0,33 l',        24,  3.30,   1),
  ((SELECT id FROM products WHERE artikelnr='3001'), 'Beutel 1 kg',          1,  0.00,   1),
  ((SELECT id FROM products WHERE artikelnr='5001'), 'Stück',                1,  0.00,   1),
  ((SELECT id FROM products WHERE artikelnr='5002'), 'Stück',                1,  0.00,   1),
  ((SELECT id FROM products WHERE artikelnr='7001'), 'Flasche',              1,  0.00,   1),
  ((SELECT id FROM products WHERE artikelnr='7002'), 'Flasche',              1,  0.00,   1),
  ((SELECT id FROM products WHERE artikelnr='8001'), 'Stück',                1,  0.00,   1),
  ((SELECT id FROM products WHERE artikelnr='8002'), 'Stück',                1,  0.00,   1),
  ((SELECT id FROM products WHERE artikelnr='9001'), 'Karton 100 Stück',   100,  0.00,   1);
