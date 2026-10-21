-- ============================================================
--  Migration: Pfand je Gebinde + externe Kosten
--
--  Nur für bereits laufende Installationen nötig. Bei einer
--  frischen Datenbank reicht 01_schema.sql, das diese Spalten
--  schon enthält – dieses Skript NICHT zusätzlich ausführen.
--
--  Einspielen z. B. mit:
--    mysql -u <user> -p <datenbank> < db/03_migration_pfand_kosten.sql
-- ============================================================

ALTER TABLE packagings
  ADD COLUMN IF NOT EXISTS deposit DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER ek_price;

ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS cost_lieferpauschale DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER beleg_text,
  ADD COLUMN IF NOT EXISTS cost_energie         DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER cost_lieferpauschale,
  ADD COLUMN IF NOT EXISTS cost_frei_label      VARCHAR(64) NULL AFTER cost_energie,
  ADD COLUMN IF NOT EXISTS cost_frei_amount     DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER cost_frei_label;
