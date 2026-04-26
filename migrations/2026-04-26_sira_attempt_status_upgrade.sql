-- EduquestIQ SIRA attempt status upgrade
-- Adds answer_status tracking to test_answers for one-question-at-a-time test flow.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET @db_name = DATABASE();

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'test_answers'
    AND column_name = 'answer_status'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE test_answers ADD COLUMN answer_status ENUM(''not_attempted'',''not_answered'',''answered'',''marked_for_review'') NOT NULL DEFAULT ''not_attempted'' AFTER subjective_answer',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
