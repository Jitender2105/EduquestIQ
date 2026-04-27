-- EduquestIQ test builder upgrade
-- Adds instruction, test year, and availability window fields to tests.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET @db_name = DATABASE();

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'tests'
    AND column_name = 'instruction'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE tests ADD COLUMN instruction TEXT NULL AFTER description',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'tests'
    AND column_name = 'test_year'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE tests ADD COLUMN test_year VARCHAR(20) NULL AFTER instruction',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'tests'
    AND column_name = 'start_at'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE tests ADD COLUMN start_at DATETIME NULL AFTER test_year',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'tests'
    AND column_name = 'end_at'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE tests ADD COLUMN end_at DATETIME NULL AFTER start_at',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
