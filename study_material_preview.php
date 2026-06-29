<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';
require_once __DIR__ . '/includes_payments.php';

$pdo = get_pdo();
ensure_study_material_tables($pdo);

$materialId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($materialId <= 0) {
    http_response_code(404);
    exit;
}

$hasActiveColumn = table_has_column($pdo, 'study_materials', 'is_active');
$hasStatusColumn = table_has_column($pdo, 'study_materials', 'status');

$stmt = $pdo->prepare(
    'SELECT id, file_path, material_type'
    . ($hasActiveColumn ? ', is_active' : ', 1 AS is_active')
    . ($hasStatusColumn ? ', status' : ", 'published' AS status") . '
     FROM study_materials
     WHERE id = ?
     LIMIT 1'
);
$stmt->execute([$materialId]);
$material = $stmt->fetch();

if (!$material || empty($material['is_active']) || (string)($material['status'] ?? '') !== 'published') {
    http_response_code(404);
    exit;
}

$path = trim((string)($material['file_path'] ?? ''));
if ($path === '' || preg_match('#^https?://#i', $path)) {
    http_response_code(404);
    exit;
}

$relativePath = ltrim($path, '/');
$absolutePath = __DIR__ . '/' . $relativePath;
if (!is_file($absolutePath) || strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) !== 'pdf') {
    http_response_code(404);
    exit;
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($absolutePath));
header('Content-Disposition: inline; filename="study-material-preview.pdf"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');
readfile($absolutePath);
exit;
