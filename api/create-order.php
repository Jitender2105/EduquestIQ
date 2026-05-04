<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes_auth.php';
require_once dirname(__DIR__) . '/includes_csrf.php';
require_once dirname(__DIR__) . '/includes_payments.php';

header('Content-Type: application/json; charset=utf-8');

function api_json_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    api_json_response(405, ['success' => false, 'error' => 'Method not allowed.']);
}

$user = current_user();
if (!$user || ($user['role'] ?? '') !== 'student') {
    api_json_response(401, ['success' => false, 'error' => 'Authentication required.']);
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token(is_string($csrf) ? $csrf : null)) {
    api_json_response(400, ['success' => false, 'error' => 'Invalid CSRF token.']);
}

$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$amountPaise = (int)($input['amount'] ?? 0);
$currency = strtoupper(trim((string)($input['currency'] ?? payment_gateway_currency())));
$receipt = trim((string)($input['receipt'] ?? ('eduquestiq-' . time())));
$testId = isset($input['test_id']) ? (int)$input['test_id'] : 0;

if ($currency !== payment_gateway_currency()) {
    api_json_response(400, ['success' => false, 'error' => 'Unsupported currency.']);
}
if ($amountPaise < 100) {
    api_json_response(400, ['success' => false, 'error' => 'Amount must be at least 100 paise.']);
}
if ($receipt === '') {
    $receipt = 'eduquestiq-' . time();
}

try {
    $pdo = get_pdo();
    $notes = [
        'student_id' => (string)$user['sub'],
        'student_email' => (string)$user['email'],
        'support_email' => payment_support_email(),
    ];

    if ($testId > 0) {
        $stmt = $pdo->prepare('SELECT id, title, price_inr FROM tests WHERE id = ? LIMIT 1');
        $stmt->execute([$testId]);
        $test = $stmt->fetch();
        if (!$test) {
            api_json_response(400, ['success' => false, 'error' => 'Invalid test.']);
        }

        $serverAmountPaise = amount_in_paise(max(0.0, (float)($test['price_inr'] ?? 0)));
        if ($serverAmountPaise < 100) {
            api_json_response(400, ['success' => false, 'error' => 'This test is not a paid test.']);
        }
        if ($amountPaise !== $serverAmountPaise) {
            api_json_response(400, ['success' => false, 'error' => 'Amount mismatch.']);
        }
        if (test_purchase_is_paid($pdo, $testId, (int)$user['sub'])) {
            api_json_response(200, [
                'success' => true,
                'already_paid' => true,
                'redirect_url' => url_for('test_attempt.php?id=' . $testId),
            ]);
        }

        $receipt = 'test-' . $testId . '-student-' . (int)$user['sub'] . '-' . time();
        $notes['test_id'] = (string)$testId;
        $notes['test_title'] = (string)$test['title'];
    }

    $order = razorpay_create_order_paise($amountPaise, substr($receipt, 0, 40), $notes);

    if ($testId > 0) {
        test_purchase_upsert_pending($pdo, $testId, (int)$user['sub'], inr_from_paise($amountPaise), (string)$order['id'], $notes);
    }

    api_json_response(200, [
        'success' => true,
        'order_id' => (string)$order['id'],
        'amount' => (int)$order['amount'],
        'currency' => (string)$order['currency'],
        'key_id' => payment_gateway_key_id(),
        'mode' => payment_gateway_mode(),
    ]);
} catch (Throwable $e) {
    api_json_response(500, ['success' => false, 'error' => 'Could not create Razorpay order.']);
}
