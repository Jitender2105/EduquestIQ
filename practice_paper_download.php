<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';
require_once __DIR__ . '/includes_payments.php';

$user = require_auth(['student']);
$paperId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($paperId <= 0) {
    header('Location: ' . url_for('tests.php'));
    exit;
}

$pdo = get_pdo();
if (!practice_paper_table_exists($pdo)) {
    header('Location: ' . url_for('tests.php'));
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, name, access_type, amount_inr, pdf_file_path
     FROM practice_papers
     WHERE id = ? AND status = "published"
     LIMIT 1'
);
$stmt->execute([$paperId]);
$paper = $stmt->fetch();
if (!$paper) {
    header('Location: ' . url_for('tests.php'));
    exit;
}

$requiresPayment = (string)$paper['access_type'] === 'paid' && (float)$paper['amount_inr'] > 0;
if ($requiresPayment && !practice_paper_purchase_is_paid($pdo, $paperId, (int)$user['sub'])) {
    header('Location: ' . url_for('tests.php?purchase=required'));
    exit;
}

$relativePath = trim((string)$paper['pdf_file_path'], '/');
$absolutePath = __DIR__ . '/' . $relativePath;
if ($relativePath === '' || !is_file($absolutePath)) {
    http_response_code(404);
    echo 'Practice paper file not found.';
    exit;
}

$downloadName = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)$paper['name']);
$downloadName = trim((string)$downloadName, '-');
if ($downloadName === '') {
    $downloadName = 'practice-paper';
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($absolutePath));
header('Content-Disposition: attachment; filename="' . $downloadName . '.pdf"');
header('X-Content-Type-Options: nosniff');
readfile($absolutePath);
exit;
