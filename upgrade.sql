-- EduquestIQ incremental upgrade script (run once on existing installs)
-- MySQL 8+

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 1) Progress upsert support (needed by progress_mark.php)
ALTER TABLE progress
  ADD UNIQUE KEY uniq_progress_video (student_id, course_id, video_id),
  ADD UNIQUE KEY uniq_progress_material (student_id, course_id, material_id);

-- 2) Parent-child linking (for parent dashboards)
CREATE TABLE IF NOT EXISTS parent_student_links (
  id INT PRIMARY KEY AUTO_INCREMENT,
  parent_id INT NOT NULL,
  student_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_parent_student (parent_id, student_id),
  CONSTRAINT fk_psl_parent FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_psl_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Teacher feedback (for parent dashboard + teacher workflow)
CREATE TABLE IF NOT EXISTS teacher_feedback (
  id INT PRIMARY KEY AUTO_INCREMENT,
  teacher_id INT NOT NULL,
  student_id INT NOT NULL,
  feedback_text TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tfeedback_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tfeedback_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Seed default achievements (idempotent by title)
INSERT INTO achievements (title, description, icon, criteria_type, criteria_value)
SELECT 'Top Performer', 'Maintain a high average test score.', 'trophy', 'score', 85
WHERE NOT EXISTS (SELECT 1 FROM achievements WHERE title = 'Top Performer');

INSERT INTO achievements (title, description, icon, criteria_type, criteria_value)
SELECT 'Fast Learner', 'Complete at least one course.', 'bolt', 'course_completion', 1
WHERE NOT EXISTS (SELECT 1 FROM achievements WHERE title = 'Fast Learner');

INSERT INTO achievements (title, description, icon, criteria_type, criteria_value)
SELECT 'Consistent Learner', 'Complete at least three courses.', 'calendar-check', 'course_completion', 3
WHERE NOT EXISTS (SELECT 1 FROM achievements WHERE title = 'Consistent Learner');

INSERT INTO achievements (title, description, icon, criteria_type, criteria_value)
SELECT 'Community Contributor', 'Engage with the learning community regularly.', 'chat', 'activity', 5
WHERE NOT EXISTS (SELECT 1 FROM achievements WHERE title = 'Community Contributor');
