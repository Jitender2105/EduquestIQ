<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';
require_once __DIR__ . '/includes_csrf.php';

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
if ($courseId <= 0) {
    header('Location: ' . url_for('courses.php'));
    exit;
}

$pdo = get_pdo();

// Ensure course exists
$stmt = $pdo->prepare('SELECT id FROM courses WHERE id = ?');
$stmt->execute([$courseId]);
if (!$stmt->fetch()) {
    header('Location: ' . url_for('courses.php'));
    exit;
}

// Enroll (idempotent thanks to unique key)
$stmt = $pdo->prepare(
    'INSERT IGNORE INTO course_enrollments (course_id, student_id, enrolled_at) VALUES (?, ?, NOW())'
);
$stmt->execute([$courseId, (int)$user['sub']]);

header('Location: ' . url_for('course.php?id=' . $courseId));
exit;
