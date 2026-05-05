<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes_payments.php';

header('Content-Type: application/json; charset=utf-8');

function razorpay_webhook_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    razorpay_webhook_response(405, ['success' => false, 'error' => 'Method not allowed.']);
}

$payload = file_get_contents('php://input') ?: '';
$signature = (string)($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');

if (payment_gateway_webhook_secret() === '') {
    razorpay_webhook_response(503, ['success' => false, 'error' => 'Webhook secret is not configured.']);
}

if (!razorpay_webhook_signature_is_valid($payload, $signature)) {
    razorpay_webhook_response(400, ['success' => false, 'error' => 'Invalid webhook signature.']);
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    razorpay_webhook_response(400, ['success' => false, 'error' => 'Invalid webhook payload.']);
}

$eventType = (string)($event['event'] ?? '');
$payment = $event['payload']['payment']['entity'] ?? null;
$order = $event['payload']['order']['entity'] ?? null;

try {
    $pdo = get_pdo();
    $orderId = '';
    $paymentId = '';
    $paymentStatus = '';
    $amountPaise = 0;
    $currency = payment_gateway_currency();

    if (is_array($payment)) {
        $orderId = (string)($payment['order_id'] ?? '');
        $paymentId = (string)($payment['id'] ?? '');
        $paymentStatus = (string)($payment['status'] ?? '');
        $amountPaise = (int)($payment['amount'] ?? 0);
        $currency = (string)($payment['currency'] ?? $currency);
    }

    if ($orderId === '' && is_array($order)) {
        $orderId = (string)($order['id'] ?? '');
        $amountPaise = (int)($order['amount_paid'] ?? $order['amount'] ?? 0);
        $currency = (string)($order['currency'] ?? $currency);
    }

    if ($orderId === '') {
        razorpay_webhook_response(200, ['success' => true, 'ignored' => true, 'reason' => 'No Razorpay order id in event.']);
    }

    $testPurchases = test_purchase_rows_by_order($pdo, $orderId);
    $paperPurchases = practice_paper_purchase_rows_by_order($pdo, $orderId);
    if (!$testPurchases && !$paperPurchases) {
        razorpay_webhook_response(200, ['success' => true, 'ignored' => true, 'reason' => 'Order not tracked by EduquestIQ.']);
    }

    $expectedAmountPaise = 0;
    foreach ($testPurchases as $purchase) {
        $expectedAmountPaise += amount_in_paise((float)($purchase['amount_inr'] ?? 0));
    }
    foreach ($paperPurchases as $purchase) {
        $expectedAmountPaise += amount_in_paise((float)($purchase['amount_inr'] ?? 0));
    }

    if ($amountPaise > 0 && $expectedAmountPaise > 0 && $amountPaise !== $expectedAmountPaise) {
        razorpay_webhook_response(400, ['success' => false, 'error' => 'Webhook amount mismatch.']);
    }

    if ($eventType === 'payment.authorized' && $paymentId !== '') {
        $payment = razorpay_capture_payment($paymentId, $expectedAmountPaise, $currency);
        $paymentStatus = (string)($payment['status'] ?? $paymentStatus);
        $amountPaise = (int)($payment['amount'] ?? $amountPaise);
    }

    if (in_array($eventType, ['payment.captured', 'payment.authorized', 'order.paid'], true)
        && ($paymentStatus === 'captured' || $eventType === 'order.paid')) {
        if ($paymentId === '' && is_array($order) && !empty($order['payments'])) {
            $paymentId = (string)$order['payments'];
        }

        foreach ($testPurchases as $purchase) {
            test_purchase_mark_paid(
                $pdo,
                (int)$purchase['test_id'],
                (int)$purchase['student_id'],
                $orderId,
                $paymentId,
                'razorpay-webhook:' . $eventType,
                (float)($purchase['amount_inr'] ?? 0)
            );
        }

        foreach ($paperPurchases as $purchase) {
            practice_paper_purchase_mark_paid(
                $pdo,
                (int)$purchase['practice_paper_id'],
                (int)$purchase['student_id'],
                $orderId,
                $paymentId,
                'razorpay-webhook:' . $eventType,
                (float)($purchase['amount_inr'] ?? 0)
            );
        }

        razorpay_webhook_response(200, ['success' => true, 'status' => 'paid']);
    }

    if (in_array($eventType, ['payment.failed', 'order.payment_failed'], true)) {
        foreach ($testPurchases as $purchase) {
            test_purchase_mark_failed($pdo, (int)$purchase['test_id'], (int)$purchase['student_id'], $orderId, $paymentId !== '' ? $paymentId : null, [
                'event' => $eventType,
                'payment_status' => $paymentStatus,
                'payment_error' => is_array($payment) ? ($payment['error_description'] ?? null) : null,
            ]);
        }
        foreach ($paperPurchases as $purchase) {
            practice_paper_purchase_mark_failed($pdo, (int)$purchase['practice_paper_id'], (int)$purchase['student_id'], $orderId, $paymentId !== '' ? $paymentId : null, [
                'event' => $eventType,
                'payment_status' => $paymentStatus,
                'payment_error' => is_array($payment) ? ($payment['error_description'] ?? null) : null,
            ]);
        }

        razorpay_webhook_response(200, ['success' => true, 'status' => 'failed']);
    }

    razorpay_webhook_response(200, ['success' => true, 'ignored' => true, 'event' => $eventType]);
} catch (Throwable $e) {
    razorpay_webhook_response(500, ['success' => false, 'error' => 'Webhook processing failed.']);
}
