# EduquestIQ – Hostinger Deployment Guide

This project is designed to run on shared hosting (e.g. Hostinger) with:

- PHP 8.1+
- MySQL 8+
- Apache with `.htaccess` support

No Node.js, build tools, or front‑end bundlers are required.

## 1. Prepare the database

1. Log in to your Hostinger control panel.
2. Create a new MySQL database and user.
3. Open phpMyAdmin for this database.
4. Import the `schema.sql` file from this project:
   - Click **Import**
   - Choose `schema.sql`
   - Run the import (this will create all tables including parent links / teacher feedback and seed base attributes + achievements).

If you are updating an existing installation instead of importing fresh:

- Add the new `progress` unique keys (for reliable progress upserts).
- Create `parent_student_links` and `teacher_feedback` tables from `schema.sql`.
- Re-import/insert the seeded `achievements` rows if you want the default badges.
- Or run `upgrade.sql` once in phpMyAdmin (recommended for incremental updates).

## 2. Configure the application

1. Upload all project files to your `public_html` directory (or a subdirectory if you prefer).
2. Edit `config.php`:
   - Set `DB_NAME`, `DB_USER`, and `DB_PASS` to match your Hostinger DB credentials.
   - Change `JWT_SECRET` to a long, random string.
   - Adjust `BASE_URL` if you install in a subdirectory (e.g. `/eduquestiq`).

There is no `vendor` directory or Composer dependency required by default. If you later add Composer packages, run `composer install` locally and upload the generated `vendor` folder to the server.

## 2.1 In-app content management (teacher/admin)

- `manage_lms.php` lets teachers/admins create:
  - Courses
  - Video lectures
  - Study materials (including file upload / link)
  - Questions and options
  - Question attribute mappings
  - Tests and test-question mappings
  - Learning paths and path-course mappings
  - Achievements (school admin)
- `material_upload.php` validates uploads (type + extension + size) and stores files under `uploads/materials/`.

## 3. Verify file permissions

- PHP files: `644`
- Directories: `755`
- `uploads/` and `uploads/materials/`: `755` (or writable by PHP on your host)
- Ensure `.htaccess` is present in the project root and Apache allows overrides (Hostinger does by default).

## 4. Initial data seeding

The system assumes the following are created (via SQL or a simple admin process):

- `courses` rows (with `teacher_id` and `attribute_id`).
- `video_lectures` and `study_materials` linked to courses (can be created in `manage_lms.php`).
- `questions`, `question_options`, and `question_attribute_mapping` (can be created in `manage_lms.php`).
- `tests` and `test_questions` (can be created in `manage_lms.php`).
- Optional `learning_paths` and `path_courses` (can be created in `manage_lms.php`).
- Optional `achievements` definitions (seeded by `schema.sql`, can be customized).
- Optional `parent_student_links` rows (or use `parent_children.php` page).
- Optional `teacher_feedback` rows (or use `teacher_feedback.php` page).

You can add this data using phpMyAdmin directly.

## 5. Production checklist

- [ ] Use HTTPS on your domain.
- [ ] Set a strong `JWT_SECRET` in `config.php`.
- [ ] Change database user password to a strong random value.
- [ ] Confirm `schema.sql` has been imported without errors.
- [ ] Create at least one teacher and some students via the registration page.
- [ ] Insert initial courses, tests, and mappings into the database.
- [ ] Configure achievements in the `achievements` table.
- [ ] Review `.htaccess` and ensure sensitive files like `config.php` and `schema.sql` are not accessible.
- [ ] Enable regular database backups in Hostinger.

## 6. Role-based flows summary

- **Student**
  - Register / log in.
  - Browse `courses.php`, enroll (`enroll_course.php`).
  - Open a course (`course.php`), watch videos, download materials, mark items complete.
  - Attempt tests via `tests.php` → `test_attempt.php`.
  - Dashboard (`dashboard.php`) shows radar chart, progress, achievements, community feed.

- **Parent**
  - Register as parent (optionally link to a school’s students via `school_id` in DB).
  - Link child using `parent_children.php` (recommended for accurate dashboard mapping).
  - Dashboard shows child skill trends, performance summary, attendance summary, teacher feedback, and community feed.

- **Teacher**
  - Create courses, questions, tests, and mappings via database.
  - Add teacher feedback via `teacher_feedback.php`.
  - Dashboard shows class performance, course completion stats, test analytics, and student ranking.

- **School admin**
  - Overview dashboard with total/active users, course stats, skill distribution, and engagement metrics.

## 7. Troubleshooting

- **Blank page or 500 error**
  - Enable PHP error display in Hostinger temporarily, or check error logs.
  - Confirm PHP version is 8.1+.
  - Verify database credentials and host (`localhost` for Hostinger MySQL).

- **Login doesn’t work**
  - Check that the `users` table exists and contains the user.
  - Ensure `config.php` has correct DB settings.
  - Make sure cookies are enabled in the browser.
