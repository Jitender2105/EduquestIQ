-- EduquestIQ practice papers and bulk purchase migration
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS practice_papers (
  id INT PRIMARY KEY AUTO_INCREMENT,
  test_id INT NOT NULL,
  name VARCHAR(180) NOT NULL,
  description TEXT NULL,
  class_name VARCHAR(40) NOT NULL,
  paper_year VARCHAR(20) NOT NULL,
  access_type ENUM('free','paid') NOT NULL DEFAULT 'free',
  amount_inr DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  pdf_file_path VARCHAR(255) NOT NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_practice_papers_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
  CONSTRAINT fk_practice_papers_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS practice_paper_purchases (
  id INT PRIMARY KEY AUTO_INCREMENT,
  practice_paper_id INT NOT NULL,
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
  CONSTRAINT fk_ppp_paper FOREIGN KEY (practice_paper_id) REFERENCES practice_papers(id) ON DELETE CASCADE,
  CONSTRAINT fk_ppp_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_pp_student_unique := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'practice_paper_purchases'
    AND index_name = 'uniq_practice_student_purchase'
);
SET @sql := IF(
  @has_pp_student_unique = 0,
  'ALTER TABLE practice_paper_purchases ADD UNIQUE KEY uniq_practice_student_purchase (practice_paper_id, student_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_pp_order_index := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'practice_paper_purchases'
    AND index_name = 'idx_practice_gateway_order'
);
SET @sql := IF(
  @has_pp_order_index = 0,
  'ALTER TABLE practice_paper_purchases ADD KEY idx_practice_gateway_order (gateway_order_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_test_order_unique := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'test_purchases'
    AND index_name = 'uniq_gateway_order'
);
SET @sql := IF(
  @has_test_order_unique > 0,
  'ALTER TABLE test_purchases DROP INDEX uniq_gateway_order',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_test_order_index := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'test_purchases'
    AND index_name = 'idx_test_gateway_order'
);
SET @sql := IF(
  @has_test_order_index = 0,
  'ALTER TABLE test_purchases ADD KEY idx_test_gateway_order (gateway_order_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
