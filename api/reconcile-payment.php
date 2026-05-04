<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes_auth.php';
require_once dirname(__DIR__) . '/includes_csrf.php';
require_once dirname(__DIR__) . '/includes_payments.php';

header('Content-Type: application/json; charset=utf-8');

function api_reconcile_json_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    api_reconcile_json_response(405, ['success' => false, 'error' => 'Method not allowed.']);
}

$user = current_user();
if (!$user || ($user['role'] ?? '') !== 'student') {
    api_reconcile_json_response(401, ['success' => false, 'error' => 'Authentication required.']);
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token(is_string($csrf) ? $csrf : null)) {
    api_reconcile_json_response(400, ['success' => false, 'error' => 'Invalid CSRF token.']);
}

$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$testId = isset($input['test_id']) ? (int)$input['test_id'] : 0;
$orderId = trim((string)($input['razorpay_order_id'] ?? ''));
$paymentId = trim((string)($input['razorpay_payment_id'] ?? ''));
$gatewayError = is_array($input['error'] ?? null) ? $input['error'] : [];

if ($testId <= 0 || $orderId === '' || $paymentId === '') {
    api_reconcile_json_response(400, ['success' => false, 'error' => 'Missing payment reconciliation fields.']);
}

try {
    $pdo = get_pdo();
    $studentId = (int)$user['sub'];
    $purchase = test_purchase_row($pdo, $testId, $studentId);

    if (!$purchase || (string)($purchase['gateway_order_id'] ?? '') !== $orderId) {
        api_reconcile_json_response(400, ['success' => false, 'error' => 'Payment order mismatch.']);
    }

    if (($purchase['payment_status'] ?? '') === 'paid') {
        api_reconcile_json_response(200, [
            'success' => true,
            'recovered' => false,
            'redirect_url' => url_for('test_attempt.php?id=' . $testId . '&paid=1'),
        ]);
    }

    $payment = razorpay_fetch_payment($paymentId);
    if ((string)($payment['order_id'] ?? '') !== $orderId) {
        api_reconcile_json_response(400, ['success' => false, 'error' => 'Razorpay payment does not belong to this order.']);
    }

    $expectedAmount = amount_in_paise((float)($purchase['amount_inr'] ?? 0));
    $actualAmount = (int)($payment['amount'] ?? 0);
    if ($expectedAmount > 0 && $actualAmount !== $expectedAmount) {
        api_reconcile_json_response(400, ['success' => false, 'error' => 'Razorpay payment amount mismatch.']);
    }

    $paymentStatus = (string)($payment['status'] ?? '');
    if ($paymentStatus === 'captured') {
        test_purchase_mark_paid(
            $pdo,
            $testId,
            $studentId,
            $orderId,
            $paymentId,
            'reconciled-captured-payment',
            (float)($purchase['amount_inr'] ?? inr_from_paise($actualAmount))
        );

        api_reconcile_json_response(200, [
            'success' => true,
            'recovered' => true,
            'redirect_url' => url_for('test_attempt.php?id=' . $testId . '&paid=1'),
        ]);
    }

    test_purchase_mark_failed($pdo, $testId, $studentId, $orderId, $paymentId, [
        'gateway_error' => $gatewayError,
        'razorpay_status' => $paymentStatus,
        'razorpay_amount' => $actualAmount,
    ]);

    api_reconcile_json_response(400, [
        'success' => false,
        'error' => 'Payment was not captured by Razorpay.',
        'payment_status' => $paymentStatus,
    ]);
} catch (Throwable $e) {
    api_reconcile_json_response(500, ['success' => false, 'error' => 'Could not reconcile payment.']);
}
