# EduquestIQ Backend Taxonomy and Relation Chart

This backend model uses practical LMS fields aligned with common K-12 interoperability references:
- IMS OneRoster (classes/courses/users/enrollments): https://www.imsglobal.org/oneroster-v11-final-specification
- Ed-Fi data model concepts (student/school/section/course taxonomy): https://docs.ed-fi.org/reference/data-exchange/data-standard/
- xAPI learning activity/event metadata patterns: https://xapi.com/statements-101/

## Taxonomy introduced
- `schools`
- `subjects`
- `grade_levels`
- `academic_sessions`
- `course_categories` (supports parent category)
- `tags` + `course_tag_map`
- `course_taxonomy_map` (links core taxonomy to course)
- `question_blueprint` (bloom level, competency, objective)
- `test_settings` (attempts/pass/availability/proctoring)
- `content_metadata` (visibility/language/version/license/tags)

## Relation chart
```mermaid
erDiagram
  schools ||--o{ users : has
  users ||--o{ courses : teaches
  attributes ||--o{ sub_attributes : contains
  attributes ||--o{ courses : maps

  courses ||--o{ video_lectures : contains
  courses ||--o{ study_materials : contains
  courses ||--o{ course_enrollments : enrollment

  subjects ||--o{ course_taxonomy_map : classifies
  grade_levels ||--o{ course_taxonomy_map : classifies
  academic_sessions ||--o{ course_taxonomy_map : classifies
  course_categories ||--o{ course_taxonomy_map : classifies
  courses ||--|| course_taxonomy_map : mapped

  tags ||--o{ course_tag_map : used_by
  courses ||--o{ course_tag_map : tagged

  questions ||--o{ question_options : has
  questions ||--o{ question_attribute_mapping : maps
  questions ||--|| question_blueprint : blueprint
  tests ||--o{ test_questions : includes

  tests ||--|| test_settings : settings
  tests ||--o{ test_attempts : attempts
  test_attempts ||--o{ test_answers : answers

  learning_paths ||--o{ path_courses : sequence
  courses ||--o{ path_courses : in_path

  achievements ||--o{ user_achievements : awards
  users ||--o{ user_achievements : earns

  community_posts ||--o{ post_comments : comments
  community_posts ||--o{ post_likes : likes
  users ||--o{ community_posts : writes

  video_lectures ||--o{ content_metadata : meta
  study_materials ||--o{ content_metadata : meta
```

## Why these fields
- Course discoverability and governance: subject, grade, session, category, tags.
- Assessment quality: bloom level, competency code, learning objective, expected time.
- Assessment operations: pass mark, attempts, windows, proctoring mode.
- Content lifecycle and visibility: language, access level, version, license, tag metadata.
- Role-specific onboarding profile (`users.role_profile`) for parent/teacher/school admin fields.
