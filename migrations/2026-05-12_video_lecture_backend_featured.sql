-- EduquestIQ video backend featured flag
-- Adds a featured toggle and relaxes course mapping requirement for video lectures.

SET @has_featured_col := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'video_lectures'
    AND column_name = 'is_featured'
);
SET @sql := IF(
  @has_featured_col = 0,
  'ALTER TABLE video_lectures ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @course_nullable := (
  SELECT IS_NULLABLE
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'video_lectures'
    AND column_name = 'course_id'
  LIMIT 1
);
SET @sql := IF(
  @course_nullable = 'NO',
  'ALTER TABLE video_lectures MODIFY course_id INT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
