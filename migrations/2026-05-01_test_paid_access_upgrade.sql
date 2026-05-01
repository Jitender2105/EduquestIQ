-- EduquestIQ paid test access migration
SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET @has_price := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'tests'
    AND column_name = 'price_inr'
);
SET @sql := IF(
  @has_price = 0,
  'ALTER TABLE tests ADD COLUMN price_inr DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER end_at',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS test_purchases (
  id INT PRIMARY KEY AUTO_INCREMENT,
  test_id INT NOT NULL,
  student_id INT NOT NULL,
  gateway ENUM('razorpay') NOT NULL DEFAULT 'razorpay',
  gateway_order_id VARCHAR(120) NOT NULL,
  gateway_payment_id VARCHAR(120) NULL,
  gateway_signature VARCHAR(255) NULL,
  amount_inr DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  currency VARCHAR(10) NOT NULL DEFAULT 'INR',
  payment_status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
  notes_json JSON NULL,
  paid_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tp_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
  CONSTRAINT fk_tp_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_test_student_unique := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'test_purchases'
    AND index_name = 'uniq_test_student_purchase'
);
SET @sql := IF(
  @has_test_student_unique = 0,
  'ALTER TABLE test_purchases ADD UNIQUE KEY uniq_test_student_purchase (test_id, student_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_order_unique := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'test_purchases'
    AND index_name = 'uniq_gateway_order'
);
SET @sql := IF(
  @has_order_unique = 0,
  'ALTER TABLE test_purchases ADD UNIQUE KEY uniq_gateway_order (gateway_order_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
