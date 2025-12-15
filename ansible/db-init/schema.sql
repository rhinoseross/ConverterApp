CREATE DATABASE IF NOT EXISTS converterApp
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE converterApp;

CREATE TABLE IF NOT EXISTS fx_rates (
  currency_code CHAR(3) NOT NULL,
  rate_to_usd DECIMAL(18,8) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
             ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (currency_code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS conversions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  amount_from DECIMAL(18,2) NOT NULL,
  from_currency CHAR(3) NOT NULL,
  to_currency CHAR(3) NOT NULL,
  amount_to DECIMAL(18,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_created_at (created_at),
  INDEX idx_pair (from_currency, to_currency)
) ENGINE=InnoDB;

INSERT INTO fx_rates (currency_code, rate_to_usd)
VALUES
  ('USD', 1.00000000),
  ('EUR', 0.92000000),
  ('GBP', 0.79000000),
  ('JPY', 155.40000000),
  ('CAD', 1.37000000),
  ('AUD', 1.52000000)
ON DUPLICATE KEY UPDATE
  rate_to_usd = VALUES(rate_to_usd);
