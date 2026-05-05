<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes_auth.php';
require_once dirname(__DIR__) . '/includes_csrf.php';
require_once dirname(__DIR__) . '/includes_payments.php';

header('Content-Type: application/json; charset=utf-8');

function api_verify_json_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    api_verify_json_response(405, ['success' => false, 'error' => 'Method not allowed.']);
}

$user = current_user();
if (!$user || ($user['role'] ?? '') !== 'student') {
    api_verify_json_response(401, ['success' => false, 'error' => 'Authentication required.']);
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token(is_string($csrf) ? $csrf : null)) {
    api_verify_json_response(400, ['success' => false, 'error' => 'Invalid CSRF token.']);
}

$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$orderId = trim((string)($input['razorpay_order_id'] ?? ''));
$paymentId = trim((string)($input['razorpay_payment_id'] ?? ''));
$signature = trim((string)($input['razorpay_signature'] ?? ''));
$testId = isset($input['test_id']) ? (int)$input['test_id'] : 0;

if ($orderId === '' || $paymentId === '' || $signature === '') {
    api_verify_json_response(400, ['success' => false, 'error' => 'Missing Razorpay payment fields.']);
}

if (!razorpay_signature_is_valid($orderId, $paymentId, $signature)) {
    api_verify_json_response(400, ['success' => false, 'error' => 'Payment signature mismatch.']);
}

try {
    $pdo = get_pdo();
    $redirectUrl = url_for('tests.php');

    if ($testId > 0) {
        $purchase = test_purchase_row($pdo, $testId, (int)$user['sub']);
        if (!$purchase || (string)($purchase['gateway_order_id'] ?? '') !== $orderId) {
            api_verify_json_response(400, ['success' => false, 'error' => 'Payment order mismatch.']);
        }

        test_purchase_mark_paid(
            $pdo,
            $testId,
            (int)$user['sub'],
            $orderId,
            $paymentId,
            $signature,
            (float)($purchase['amount_inr'] ?? 0)
        );
        $redirectUrl = url_for('tests.php?purchase=success');
    } else {
        $testPurchases = test_purchase_rows_by_order($pdo, $orderId);
        $paperPurchases = practice_paper_purchase_rows_by_order($pdo, $orderId);
        if (!$testPurchases && !$paperPurchases) {
            api_verify_json_response(400, ['success' => false, 'error' => 'Payment order mismatch.']);
        }

        foreach ($testPurchases as $purchase) {
            if ((int)$purchase['student_id'] !== (int)$user['sub']) {
                api_verify_json_response(400, ['success' => false, 'error' => 'Payment order mismatch.']);
            }
            test_purchase_mark_paid(
                $pdo,
                (int)$purchase['test_id'],
                (int)$user['sub'],
                $orderId,
                $paymentId,
                $signature,
                (float)($purchase['amount_inr'] ?? 0)
            );
        }

        foreach ($paperPurchases as $purchase) {
            if ((int)$purchase['student_id'] !== (int)$user['sub']) {
                api_verify_json_response(400, ['success' => false, 'error' => 'Payment order mismatch.']);
            }
            practice_paper_purchase_mark_paid(
                $pdo,
                (int)$purchase['practice_paper_id'],
                (int)$user['sub'],
                $orderId,
                $paymentId,
                $signature,
                (float)($purchase['amount_inr'] ?? 0)
            );
        }

        $redirectUrl = url_for('tests.php?purchase=success');
    }

    api_verify_json_response(200, [
        'success' => true,
        'redirect_url' => $redirectUrl,
    ]);
} catch (Throwable $e) {
    api_verify_json_response(500, ['success' => false, 'error' => 'Could not verify payment.']);
}
