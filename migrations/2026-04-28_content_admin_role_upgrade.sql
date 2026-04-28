SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET @role_col := (
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'role'
    LIMIT 1
);

SET @needs_upgrade := IF(@role_col LIKE '%content_admin%' AND @role_col LIKE '%super_admin%', 0, 1);

SET @sql := IF(
    @needs_upgrade = 1,
    'ALTER TABLE users MODIFY role ENUM(''student'',''parent'',''teacher'',''school_admin'',''content_admin'',''super_admin'') NOT NULL',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
