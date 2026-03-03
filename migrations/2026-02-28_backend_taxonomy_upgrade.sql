-- Backend taxonomy and entity-detail upgrade
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS subjects (
  id INT PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(120) NOT NULL,
  domain VARCHAR(80) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_subject_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grade_levels (
  id INT PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  label VARCHAR(60) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  age_min TINYINT UNSIGNED NULL,
  age_max TINYINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_grade_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_sessions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_session_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_categories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  parent_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cc_parent FOREIGN KEY (parent_id) REFERENCES course_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  tag_type ENUM('course','skill','resource') NOT NULL DEFAULT 'course',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tag_slug_type (slug, tag_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_taxonomy_map (
  id INT PRIMARY KEY AUTO_INCREMENT,
  course_id INT NOT NULL,
  subject_id INT NOT NULL,
  grade_level_id INT NOT NULL,
  academic_session_id INT NOT NULL,
  category_id INT NOT NULL,
  level ENUM('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
  language VARCHAR(12) NOT NULL DEFAULT 'en',
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_course_taxonomy (course_id),
  CONSTRAINT fk_ctm_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_ctm_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ctm_grade FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ctm_session FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ctm_category FOREIGN KEY (category_id) REFERENCES course_categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_tag_map (
  id INT PRIMARY KEY AUTO_INCREMENT,
  course_id INT NOT NULL,
  tag_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_course_tag (course_id, tag_id),
  CONSTRAINT fk_ctag_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_ctag_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  test_id INT NOT NULL,
  test_type ENUM('practice','graded','diagnostic') NOT NULL DEFAULT 'graded',
  pass_marks INT NOT NULL DEFAULT 40,
  attempts_allowed INT NOT NULL DEFAULT 1,
  availability_start DATETIME NULL,
  availability_end DATETIME NULL,
  proctoring_mode ENUM('none','camera','browser_lock') NOT NULL DEFAULT 'none',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_test_settings (test_id),
  CONSTRAINT fk_tset_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS question_blueprint (
  id INT PRIMARY KEY AUTO_INCREMENT,
  question_id INT NOT NULL,
  bloom_level ENUM('remember','understand','apply','analyze','evaluate','create') NOT NULL DEFAULT 'understand',
  competency_code VARCHAR(80) NULL,
  learning_objective VARCHAR(255) NULL,
  estimated_time_seconds INT NOT NULL DEFAULT 60,
  hint_text VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_question_blueprint (question_id),
  CONSTRAINT fk_qb_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_metadata (
  id INT PRIMARY KEY AUTO_INCREMENT,
  entity_type ENUM('video','material','article') NOT NULL,
  entity_id INT NOT NULL,
  language VARCHAR(12) NOT NULL DEFAULT 'en',
  visibility ENUM('public','enrolled_only','private') NOT NULL DEFAULT 'public',
  version_label VARCHAR(30) NULL,
  license_type VARCHAR(80) NULL,
  tags_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_content_meta (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO subjects (code, name, domain)
SELECT 'MATH', 'Mathematics', 'STEM' WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE code='MATH');
INSERT INTO subjects (code, name, domain)
SELECT 'SCI', 'Science', 'STEM' WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE code='SCI');
INSERT INTO subjects (code, name, domain)
SELECT 'LANG', 'Language Arts', 'Humanities' WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE code='LANG');
INSERT INTO subjects (code, name, domain)
SELECT 'CS', 'Computer Science', 'Technical' WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE code='CS');

INSERT INTO grade_levels (code, label, sort_order, age_min, age_max)
SELECT 'G1','Grade 1',1,6,7 WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE code='G1');
INSERT INTO grade_levels (code, label, sort_order, age_min, age_max)
SELECT 'G6','Grade 6',6,11,12 WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE code='G6');
INSERT INTO grade_levels (code, label, sort_order, age_min, age_max)
SELECT 'G8','Grade 8',8,13,14 WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE code='G8');
INSERT INTO grade_levels (code, label, sort_order, age_min, age_max)
SELECT 'G10','Grade 10',10,15,16 WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE code='G10');

INSERT INTO academic_sessions (name, is_active)
SELECT '2026-2027', 1 WHERE NOT EXISTS (SELECT 1 FROM academic_sessions WHERE name='2026-2027');

INSERT INTO course_categories (name, parent_id)
SELECT 'Academic Excellence', NULL WHERE NOT EXISTS (SELECT 1 FROM course_categories WHERE name='Academic Excellence');
INSERT INTO course_categories (name, parent_id)
SELECT 'Creative Development', NULL WHERE NOT EXISTS (SELECT 1 FROM course_categories WHERE name='Creative Development');
INSERT INTO course_categories (name, parent_id)
SELECT 'Leadership Skills', NULL WHERE NOT EXISTS (SELECT 1 FROM course_categories WHERE name='Leadership Skills');
INSERT INTO course_categories (name, parent_id)
SELECT 'Technical Mastery', NULL WHERE NOT EXISTS (SELECT 1 FROM course_categories WHERE name='Technical Mastery');

INSERT INTO tags (name, slug, tag_type)
SELECT 'Problem Solving', 'problem-solving', 'skill' WHERE NOT EXISTS (SELECT 1 FROM tags WHERE slug='problem-solving' AND tag_type='skill');
INSERT INTO tags (name, slug, tag_type)
SELECT 'Coding Basics', 'coding-basics', 'course' WHERE NOT EXISTS (SELECT 1 FROM tags WHERE slug='coding-basics' AND tag_type='course');
INSERT INTO tags (name, slug, tag_type)
SELECT 'Worksheet', 'worksheet', 'resource' WHERE NOT EXISTS (SELECT 1 FROM tags WHERE slug='worksheet' AND tag_type='resource');
