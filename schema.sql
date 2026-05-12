-- EduquestIQ LMS - Database Schema (MySQL 8+)
-- Run this on your Hostinger MySQL database before deploying PHP files.

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- Optional: create database (uncomment and adjust name if needed)
-- CREATE DATABASE IF NOT EXISTS eduquestiq CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE eduquestiq;

DROP TABLE IF EXISTS user_achievements;
DROP TABLE IF EXISTS achievements;
DROP TABLE IF EXISTS post_likes;
DROP TABLE IF EXISTS post_comments;
DROP TABLE IF EXISTS community_posts;
DROP TABLE IF EXISTS skill_progress;
DROP TABLE IF EXISTS progress;
DROP TABLE IF EXISTS study_materials;
DROP TABLE IF EXISTS video_lectures;
DROP TABLE IF EXISTS course_enrollments;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS test_answers;
DROP TABLE IF EXISTS test_attempts;
DROP TABLE IF EXISTS test_questions;
DROP TABLE IF EXISTS practice_paper_purchases;
DROP TABLE IF EXISTS practice_papers;
DROP TABLE IF EXISTS test_purchases;
DROP TABLE IF EXISTS tests;
DROP TABLE IF EXISTS question_attribute_mapping;
DROP TABLE IF EXISTS question_options;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS path_courses;
DROP TABLE IF EXISTS learning_paths;
DROP TABLE IF EXISTS article_faqs;
DROP TABLE IF EXISTS articles;
DROP TABLE IF EXISTS attributes;
DROP TABLE IF EXISTS sub_attributes;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS parent_student_links;
DROP TABLE IF EXISTS teacher_feedback;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS schools;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE schools (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(180) NOT NULL,
  city VARCHAR(100) NULL,
  state VARCHAR(100) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_school_name_city (name, city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1️⃣ CORE USER SYSTEM

CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('student','parent','teacher','school_admin','content_admin','super_admin') NOT NULL,
  school_id INT NULL,
  age TINYINT UNSIGNED NULL,
  grade VARCHAR(20) NULL,
  role_profile JSON NULL,
  terms_accepted TINYINT(1) NOT NULL DEFAULT 0,
  terms_accepted_at TIMESTAMP NULL DEFAULT NULL,
  profile_image VARCHAR(255) NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  email_verified TINYINT(1) DEFAULT 0,
  skills JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login rate limiting helper
CREATE TABLE login_attempts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(150) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempt_count INT NOT NULL DEFAULT 0,
  last_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_email_ip (email, ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional attendance table to support parent dashboards
CREATE TABLE attendance (
  id INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT NOT NULL,
  date DATE NOT NULL,
  status ENUM('present','absent','late') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_attendance (student_id, date),
  CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parent-child linking for parent dashboards
CREATE TABLE parent_student_links (
  id INT PRIMARY KEY AUTO_INCREMENT,
  parent_id INT NOT NULL,
  student_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_parent_student (parent_id, student_id),
  CONSTRAINT fk_psl_parent FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_psl_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teacher feedback visible in parent dashboard
CREATE TABLE teacher_feedback (
  id INT PRIMARY KEY AUTO_INCREMENT,
  teacher_id INT NOT NULL,
  student_id INT NOT NULL,
  feedback_text TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tfeedback_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tfeedback_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2️⃣ ATTRIBUTE & SUB-ATTRIBUTE SYSTEM

CREATE TABLE attributes (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sub_attributes (
  id INT PRIMARY KEY AUTO_INCREMENT,
  attribute_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  CONSTRAINT fk_subattr_attr FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3️⃣ QUESTIONS & TEST SYSTEM

CREATE TABLE questions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  question_text TEXT NOT NULL,
  question_type ENUM('mcq','subjective') DEFAULT 'mcq',
  difficulty ENUM('easy','medium','hard'),
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_questions_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE question_options (
  id INT PRIMARY KEY AUTO_INCREMENT,
  question_id INT NOT NULL,
  option_text TEXT,
  is_correct TINYINT(1) DEFAULT 0,
  CONSTRAINT fk_qoptions_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE question_attribute_mapping (
  id INT PRIMARY KEY AUTO_INCREMENT,
  question_id INT NOT NULL,
  attribute_id INT NOT NULL,
  sub_attribute_id INT NOT NULL,
  weight DECIMAL(5,2) NOT NULL,
  CONSTRAINT fk_qattr_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  CONSTRAINT fk_qattr_attr FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE,
  CONSTRAINT fk_qattr_subattr FOREIGN KEY (sub_attribute_id) REFERENCES sub_attributes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tests (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(150),
  description TEXT,
  instruction TEXT,
  test_year VARCHAR(20) NULL,
  target_grade VARCHAR(40) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  start_at DATETIME NULL,
  end_at DATETIME NULL,
  price_inr DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_by INT,
  total_marks INT,
  duration_minutes INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tests_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_purchases (
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
  UNIQUE KEY uniq_test_student_purchase (test_id, student_id),
  KEY idx_test_gateway_order (gateway_order_id),
  CONSTRAINT fk_tp_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
  CONSTRAINT fk_tp_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE practice_papers (
  id INT PRIMARY KEY AUTO_INCREMENT,
  test_id INT NOT NULL,
  name VARCHAR(180) NOT NULL,
  description TEXT NULL,
  class_name VARCHAR(40) NOT NULL,
  paper_year VARCHAR(20) NOT NULL,
  access_type ENUM('free','paid') NOT NULL DEFAULT 'free',
  amount_inr DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  pdf_file_path VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_practice_papers_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
  CONSTRAINT fk_practice_papers_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE practice_paper_purchases (
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
  UNIQUE KEY uniq_practice_student_purchase (practice_paper_id, student_id),
  KEY idx_practice_gateway_order (gateway_order_id),
  CONSTRAINT fk_ppp_paper FOREIGN KEY (practice_paper_id) REFERENCES practice_papers(id) ON DELETE CASCADE,
  CONSTRAINT fk_ppp_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_questions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  test_id INT NOT NULL,
  question_id INT NOT NULL,
  marks INT NOT NULL,
  CONSTRAINT fk_tq_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
  CONSTRAINT fk_tq_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_attempts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  test_id INT NOT NULL,
  student_id INT NOT NULL,
  score DECIMAL(5,2),
  attempt_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ta_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
  CONSTRAINT fk_ta_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_answers (
  id INT PRIMARY KEY AUTO_INCREMENT,
  attempt_id INT NOT NULL,
  question_id INT NOT NULL,
  selected_option_id INT NULL,
  subjective_answer TEXT NULL,
  answer_status ENUM('not_attempted','not_answered','answered','marked_for_review') NOT NULL DEFAULT 'not_attempted',
  CONSTRAINT fk_tans_attempt FOREIGN KEY (attempt_id) REFERENCES test_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_tans_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  CONSTRAINT fk_tans_option FOREIGN KEY (selected_option_id) REFERENCES question_options(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4️⃣ COURSE & CONTENT SYSTEM

CREATE TABLE courses (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(150),
  description TEXT,
  teacher_id INT,
  attribute_id INT,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_courses_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_courses_attribute FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE course_enrollments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  course_id INT NOT NULL,
  student_id INT NOT NULL,
  enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_enrollment (course_id, student_id),
  CONSTRAINT fk_cenr_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_cenr_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE video_lectures (
  id INT PRIMARY KEY AUTO_INCREMENT,
  course_id INT NOT NULL,
  test_id INT NULL,
  attribute_id INT NULL,
  sub_attribute_id INT NULL,
  title VARCHAR(150),
  description TEXT NULL,
  video_url VARCHAR(255),
  duration INT,
  sequence_order INT,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_video_test (test_id),
  KEY idx_video_attr (attribute_id),
  KEY idx_video_subattr (sub_attribute_id),
  CONSTRAINT fk_vlec_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE study_materials (
  id INT PRIMARY KEY AUTO_INCREMENT,
  course_id INT NOT NULL,
  title VARCHAR(150),
  file_path VARCHAR(255),
  material_type ENUM('pdf','doc','ppt','link'),
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_smat_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5️⃣ PROGRESS TRACKING SYSTEM

CREATE TABLE progress (
  id INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT NOT NULL,
  course_id INT NOT NULL,
  video_id INT NULL,
  material_id INT NULL,
  completion_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_progress_video (student_id, course_id, video_id),
  UNIQUE KEY uniq_progress_material (student_id, course_id, material_id),
  CONSTRAINT fk_prog_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_prog_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_prog_video FOREIGN KEY (video_id) REFERENCES video_lectures(id) ON DELETE SET NULL,
  CONSTRAINT fk_prog_material FOREIGN KEY (material_id) REFERENCES study_materials(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE skill_progress (
  id INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT NOT NULL,
  attribute_id INT NOT NULL,
  sub_attribute_id INT NOT NULL,
  score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_skill (student_id, attribute_id, sub_attribute_id),
  CONSTRAINT fk_sprog_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_sprog_attr FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE,
  CONSTRAINT fk_sprog_subattr FOREIGN KEY (sub_attribute_id) REFERENCES sub_attributes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6️⃣ COMMUNITY LEARNING SYSTEM

CREATE TABLE community_posts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  content TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cpost_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_comments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  comment TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pcomm_post FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_pcomm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_likes (
  id INT PRIMARY KEY AUTO_INCREMENT,
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  UNIQUE KEY uniq_like (post_id, user_id),
  CONSTRAINT fk_plike_post FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_plike_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7️⃣ ACHIEVEMENT SYSTEM

CREATE TABLE achievements (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(150),
  description TEXT,
  icon VARCHAR(255),
  criteria_type ENUM('score','course_completion','activity'),
  criteria_value INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_achievements (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  achievement_id INT NOT NULL,
  awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_achievement (user_id, achievement_id),
  CONSTRAINT fk_uach_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_uach_achievement FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8️⃣ FLEXIBLE LEARNING PATH SYSTEM

CREATE TABLE learning_paths (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(150),
  description TEXT,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE path_courses (
  id INT PRIMARY KEY AUTO_INCREMENT,
  path_id INT NOT NULL,
  course_id INT NOT NULL,
  sequence_order INT,
  CONSTRAINT fk_pcourse_path FOREIGN KEY (path_id) REFERENCES learning_paths(id) ON DELETE CASCADE,
  CONSTRAINT fk_pcourse_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9️⃣ ARTICLE SYSTEM

CREATE TABLE articles (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  content_html LONGTEXT NOT NULL,
  school_id INT NULL,
  article_type ENUM('generic','school','contest','news') NOT NULL DEFAULT 'generic',
  image_path VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_articles_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL,
  CONSTRAINT fk_articles_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE article_faqs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  article_id INT NOT NULL,
  question TEXT NOT NULL,
  answer LONGTEXT NOT NULL,
  sequence_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_article_faq_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed some basic attributes (optional)
INSERT INTO attributes (name, description) VALUES
('Academic', 'Academic performance and knowledge'),
('Creative', 'Creativity and artistic skills'),
('Leadership', 'Leadership and communication'),
('Technical', 'Technical and programming skills');

-- Seed a generic "Overall" sub-attribute for each attribute
INSERT INTO sub_attributes (attribute_id, name, description)
SELECT id, 'Overall', CONCAT(name, ' overall skill')
FROM attributes;

-- Seed example achievements
INSERT INTO achievements (title, description, icon, criteria_type, criteria_value) VALUES
('Top Performer', 'Maintain a high average test score.', 'trophy', 'score', 85),
('Fast Learner', 'Complete at least one course.', 'bolt', 'course_completion', 1),
('Consistent Learner', 'Complete at least three courses.', 'calendar-check', 'course_completion', 3),
('Community Contributor', 'Engage with the learning community regularly.', 'chat', 'activity', 5);
