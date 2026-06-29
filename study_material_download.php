<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';
require_once __DIR__ . '/includes_payments.php';

$pdo = get_pdo();
ensure_study_material_tables($pdo);
$hasAccessColumn = table_has_column($pdo, 'study_materials', 'access_type');
$hasAmountColumn = table_has_column($pdo, 'study_materials', 'amount_inr');
$hasActiveColumn = table_has_column($pdo, 'study_materials', 'is_active');
$hasStatusColumn = table_has_column($pdo, 'study_materials', 'status');

$materialId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($materialId <= 0) {
    header('Location: ' . url_for('study-material'));
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, title, file_path, material_type'
    . ($hasAccessColumn ? ', access_type' : ", 'free' AS access_type")
    . ($hasAmountColumn ? ', amount_inr' : ', 0.00 AS amount_inr')
    . ($hasActiveColumn ? ', is_active' : ', 1 AS is_active')
    . ($hasStatusColumn ? ', status' : ", 'published' AS status") . "
     FROM study_materials
     WHERE id = ?
     LIMIT 1"
);
$stmt->execute([$materialId]);
$material = $stmt->fetch();
if (!$material || empty($material['is_active']) || (string)($material['status'] ?? '') !== 'published') {
    header('Location: ' . url_for('study-material'));
    exit;
}

$requiresPayment = (string)($material['access_type'] ?? 'free') === 'paid' && (float)($material['amount_inr'] ?? 0) > 0;
if ($requiresPayment) {
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== 'student') {
        header('Location: ' . url_for('login.php'));
        exit;
    }
    if (!study_material_purchase_is_paid($pdo, $materialId, (int)$user['sub'])) {
        header('Location: ' . url_for('study-material?purchase=required'));
        exit;
    }
}

$path = trim((string)($material['file_path'] ?? ''));
if ($path === '') {
    http_response_code(404);
    echo 'Study material not found.';
    exit;
}

if (preg_match('#^https?://#i', $path)) {
    header('Location: ' . $path);
    exit;
}

$relativePath = ltrim($path, '/');
$absolutePath = __DIR__ . '/' . $relativePath;
if (!is_file($absolutePath)) {
    http_response_code(404);
    echo 'Study material file not found.';
    exit;
}

$downloadName = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)$material['title']);
$downloadName = trim((string)$downloadName, '-');
if ($downloadName === '') {
    $downloadName = 'study-material';
}

$extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
$contentTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
];
$contentType = $contentTypes[$extension] ?? 'application/octet-stream';

header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($absolutePath));
header('Content-Disposition: inline; filename="' . $downloadName . ($extension !== '' ? '.' . $extension : '') . '"');
header('X-Content-Type-Options: nosniff');
readfile($absolutePath);
exit;
