<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';
require_once __DIR__ . '/includes_csrf.php';
require_once __DIR__ . '/includes_skills.php';

$user = require_auth(['student']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url_for('courses.php'));
    exit;
}

$csrf = $_POST['csrf_token'] ?? null;
if (!verify_csrf_token($csrf)) {
    http_response_code(400);
    echo 'Invalid CSRF token.';
    exit;
}

$courseId = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
$videoId = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
$materialId = isset($_POST['material_id']) ? (int)$_POST['material_id'] : 0;

if ($courseId <= 0 || ($videoId <= 0 && $materialId <= 0)) {
    header('Location: ' . url_for('courses.php'));
    exit;
}

$pdo = get_pdo();

// Ensure related entities exist
$stmt = $pdo->prepare('SELECT id FROM courses WHERE id = ?');
$stmt->execute([$courseId]);
if (!$stmt->fetch()) {
    header('Location: ' . url_for('courses.php'));
    exit;
}

// Student must be enrolled in the course to record progress
$stmt = $pdo->prepare('SELECT 1 FROM course_enrollments WHERE course_id = ? AND student_id = ? LIMIT 1');
$stmt->execute([$courseId, (int)$user['sub']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo 'Enroll in the course before marking progress.';
    exit;
}

if ($videoId > 0) {
    $stmt = $pdo->prepare('SELECT id FROM video_lectures WHERE id = ? AND course_id = ?');
    $stmt->execute([$videoId, $courseId]);
    if (!$stmt->fetch()) {
        header('Location: ' . url_for('courses.php'));
        exit;
    }
}

if ($materialId > 0) {
    $stmt = $pdo->prepare('SELECT id FROM study_materials WHERE id = ? AND course_id = ?');
    $stmt->execute([$materialId, $courseId]);
    if (!$stmt->fetch()) {
        header('Location: ' . url_for('courses.php'));
        exit;
    }
}

// Insert/update progress row
if ($videoId > 0) {
    $stmt = $pdo->prepare(
        'INSERT INTO progress (student_id, course_id, video_id, completion_percentage, last_accessed)
         VALUES (?, ?, ?, 100, NOW())
         ON DUPLICATE KEY UPDATE completion_percentage = 100, last_accessed = NOW()'
    );
    $stmt->execute([(int)$user['sub'], $courseId, $videoId]);
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO progress (student_id, course_id, material_id, completion_percentage, last_accessed)
         VALUES (?, ?, ?, 100, NOW())
         ON DUPLICATE KEY UPDATE completion_percentage = 100, last_accessed = NOW()'
    );
    $stmt->execute([(int)$user['sub'], $courseId, $materialId]);
}

// Update course-level skill progress and achievements
update_skill_progress_from_course_completion((int)$user['sub'], $courseId);

header('Location: ' . url_for('course.php?id=' . $courseId));
exit;
