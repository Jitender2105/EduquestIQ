-- EduquestIQ video lecture mapping upgrade
-- Adds optional mapping to tests, attributes, and sub-attributes for YouTube-based lecture grouping.

SET @has_video_test_id := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'video_lectures'
    AND column_name = 'test_id'
);
SET @sql := IF(
  @has_video_test_id = 0,
  'ALTER TABLE video_lectures ADD COLUMN test_id INT NULL AFTER course_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_video_attribute_id := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'video_lectures'
    AND column_name = 'attribute_id'
);
SET @sql := IF(
  @has_video_attribute_id = 0,
  'ALTER TABLE video_lectures ADD COLUMN attribute_id INT NULL AFTER test_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_video_sub_attribute_id := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'video_lectures'
    AND column_name = 'sub_attribute_id'
);
SET @sql := IF(
  @has_video_sub_attribute_id = 0,
  'ALTER TABLE video_lectures ADD COLUMN sub_attribute_id INT NULL AFTER attribute_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_video_description := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'video_lectures'
    AND column_name = 'description'
);
SET @sql := IF(
  @has_video_description = 0,
  'ALTER TABLE video_lectures ADD COLUMN description TEXT NULL AFTER title',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_idx_video_test := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'video_lectures'
    AND index_name = 'idx_video_test'
);
SET @sql := IF(
  @has_idx_video_test = 0,
  'ALTER TABLE video_lectures ADD KEY idx_video_test (test_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_idx_video_attr := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'video_lectures'
    AND index_name = 'idx_video_attr'
);
SET @sql := IF(
  @has_idx_video_attr = 0,
  'ALTER TABLE video_lectures ADD KEY idx_video_attr (attribute_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_idx_video_subattr := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'video_lectures'
    AND index_name = 'idx_video_subattr'
);
SET @sql := IF(
  @has_idx_video_subattr = 0,
  'ALTER TABLE video_lectures ADD KEY idx_video_subattr (sub_attribute_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
