-- EduquestIQ migration: registration fields + school master
-- Run on production database: u927315402_EduquestIQ

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS schools (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(180) NOT NULL,
  city VARCHAR(100) NULL,
  state VARCHAR(100) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_school_name_city (name, city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_age_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'age'
);
SET @sql_age := IF(
  @col_age_exists = 0,
  'ALTER TABLE users ADD COLUMN age TINYINT UNSIGNED NULL AFTER school_id',
  'SELECT "users.age exists"'
);
PREPARE stmt_age FROM @sql_age;
EXECUTE stmt_age;
DEALLOCATE PREPARE stmt_age;

SET @col_grade_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'grade'
);
SET @sql_grade := IF(
  @col_grade_exists = 0,
  'ALTER TABLE users ADD COLUMN grade VARCHAR(20) NULL AFTER age',
  'SELECT "users.grade exists"'
);
PREPARE stmt_grade FROM @sql_grade;
EXECUTE stmt_grade;
DEALLOCATE PREPARE stmt_grade;

SET @col_terms_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'terms_accepted'
);
SET @sql_terms := IF(
  @col_terms_exists = 0,
  'ALTER TABLE users ADD COLUMN terms_accepted TINYINT(1) NOT NULL DEFAULT 0 AFTER grade',
  'SELECT "users.terms_accepted exists"'
);
PREPARE stmt_terms FROM @sql_terms;
EXECUTE stmt_terms;
DEALLOCATE PREPARE stmt_terms;

SET @col_role_profile_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'role_profile'
);
SET @sql_role_profile := IF(
  @col_role_profile_exists = 0,
  'ALTER TABLE users ADD COLUMN role_profile JSON NULL AFTER grade',
  'SELECT "users.role_profile exists"'
);
PREPARE stmt_role_profile FROM @sql_role_profile;
EXECUTE stmt_role_profile;
DEALLOCATE PREPARE stmt_role_profile;

SET @col_terms_at_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'terms_accepted_at'
);
SET @sql_terms_at := IF(
  @col_terms_at_exists = 0,
  'ALTER TABLE users ADD COLUMN terms_accepted_at TIMESTAMP NULL DEFAULT NULL AFTER terms_accepted',
  'SELECT "users.terms_accepted_at exists"'
);
PREPARE stmt_terms_at FROM @sql_terms_at;
EXECUTE stmt_terms_at;
DEALLOCATE PREPARE stmt_terms_at;

INSERT INTO schools (id, name, status)
SELECT DISTINCT u.school_id, CONCAT('School #', u.school_id), 'active'
FROM users u
LEFT JOIN schools s ON s.id = u.school_id
WHERE u.school_id IS NOT NULL
  AND s.id IS NULL;

SET @fk_exists := (
  SELECT COUNT(*)
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND CONSTRAINT_NAME = 'fk_users_school'
);
SET @sql_fk := IF(
  @fk_exists = 0,
  'ALTER TABLE users ADD CONSTRAINT fk_users_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL',
  'SELECT "fk_users_school exists"'
);
PREPARE stmt_fk FROM @sql_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

INSERT INTO schools (name, city, state, status)
SELECT 'EduquestIQ Demo School', 'Bengaluru', 'Karnataka', 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM schools WHERE name = 'EduquestIQ Demo School' AND city = 'Bengaluru'
);
