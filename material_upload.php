<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';
require_once __DIR__ . '/includes_csrf.php';
require_once __DIR__ . '/includes_files.php';

$user = require_auth(['teacher', 'school_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url_for('manage_lms.php'));
    exit;
}

$csrf = $_POST['csrf_token'] ?? null;
if (!verify_csrf_token($csrf)) {
    http_response_code(400);
    echo 'Invalid CSRF token.';
    exit;
}

$pdo = get_pdo();
$courseId = (int)($_POST['course_id'] ?? 0);
$title = trim((string)($_POST['title'] ?? ''));
$materialType = (string)($_POST['material_type'] ?? 'pdf');
$linkUrl = trim((string)($_POST['link_url'] ?? ''));

if ($courseId <= 0 || $title === '' || !in_array($materialType, ['pdf', 'doc', 'ppt', 'link'], true)) {
    header('Location: ' . url_for('manage_lms.php?msg=material_invalid'));
    exit;
}

if ($user['role'] === 'teacher') {
    $stmt = $pdo->prepare('SELECT id FROM courses WHERE id = ? AND teacher_id = ?');
    $stmt->execute([$courseId, (int)$user['sub']]);
} else {
    $stmt = $pdo->prepare('SELECT id FROM courses WHERE id = ?');
    $stmt->execute([$courseId]);
}
if (!$stmt->fetch()) {
    header('Location: ' . url_for('manage_lms.php?msg=material_course_forbidden'));
    exit;
}

try {
    if ($materialType === 'link') {
        if (!filter_var($linkUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Invalid URL');
        }
        $filePath = $linkUrl;
    } else {
        if (empty($_FILES['material_file'])) {
            throw new RuntimeException('No file uploaded');
        }
        $validated = validate_material_upload($_FILES['material_file'], $materialType);
        $filePath = store_material_upload($validated);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO study_materials (course_id, title, file_path, material_type, uploaded_at)
         VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([$courseId, $title, $filePath, $materialType]);

    header('Location: ' . url_for('manage_lms.php?msg=material_uploaded'));
    exit;
} catch (Throwable $e) {
    header('Location: ' . url_for('manage_lms.php?msg=material_upload_failed'));
    exit;
}

