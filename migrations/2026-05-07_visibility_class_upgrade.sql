-- EduquestIQ visibility and class targeting upgrade
SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET @has_tests_grade := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'tests' AND column_name = 'target_grade'
);
SET @sql := IF(
  @has_tests_grade = 0,
  'ALTER TABLE tests ADD COLUMN target_grade VARCHAR(40) NULL AFTER test_year',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_tests_active := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'tests' AND column_name = 'is_active'
);
SET @sql := IF(
  @has_tests_active = 0,
  'ALTER TABLE tests ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER target_grade',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_articles_active := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'articles' AND column_name = 'is_active'
);
SET @sql := IF(
  @has_articles_active = 0,
  'ALTER TABLE articles ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER image_path',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_practice_active := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'practice_papers' AND column_name = 'is_active'
);
SET @sql := IF(
  @has_practice_active = 0,
  'ALTER TABLE practice_papers ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER pdf_file_path',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_courses_active := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'courses' AND column_name = 'is_active'
);
SET @sql := IF(
  @has_courses_active = 0,
  'ALTER TABLE courses ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER attribute_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_videos_active := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'video_lectures' AND column_name = 'is_active'
);
SET @sql := IF(
  @has_videos_active = 0,
  'ALTER TABLE video_lectures ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER sequence_order',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_materials_active := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'study_materials' AND column_name = 'is_active'
);
SET @sql := IF(
  @has_materials_active = 0,
  'ALTER TABLE study_materials ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER material_type',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_paths_active := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'learning_paths' AND column_name = 'is_active'
);
SET @sql := IF(
  @has_paths_active = 0,
  'ALTER TABLE learning_paths ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER description',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
