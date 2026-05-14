<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_payments.php';

$orderId = trim((string)($_REQUEST['razorpay_order_id'] ?? ''));
$paymentId = trim((string)($_REQUEST['razorpay_payment_id'] ?? ''));
$signature = trim((string)($_REQUEST['razorpay_signature'] ?? ''));
$source = trim((string)($_REQUEST['source'] ?? 'tests'));

$redirectMap = [
    'home' => url_for('index.php?purchase=success'),
    'tests' => url_for('tests.php?purchase=success'),
    'test_purchase' => url_for('tests.php?purchase=success'),
];
$failureMap = [
    'home' => url_for('index.php?purchase=failed'),
    'tests' => url_for('tests.php?purchase=failed'),
    'test_purchase' => url_for('tests.php?purchase=failed'),
];

$successRedirect = $redirectMap[$source] ?? url_for('tests.php?purchase=success');
$failureRedirect = $failureMap[$source] ?? url_for('tests.php?purchase=failed');

if ($orderId === '' || $paymentId === '' || $signature === '') {
    header('Location: ' . $failureRedirect);
    exit;
}

if (!razorpay_signature_is_valid($orderId, $paymentId, $signature)) {
    header('Location: ' . $failureRedirect);
    exit;
}

try {
    $pdo = get_pdo();

    $testPurchases = test_purchase_rows_by_order($pdo, $orderId);
    $paperPurchases = practice_paper_purchase_rows_by_order($pdo, $orderId);

    if (!$testPurchases && !$paperPurchases) {
        header('Location: ' . $failureRedirect);
        exit;
    }

    foreach ($testPurchases as $purchase) {
        test_purchase_mark_paid(
            $pdo,
            (int)$purchase['test_id'],
            (int)$purchase['student_id'],
            $orderId,
            $paymentId,
            $signature,
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
            $signature,
            (float)($purchase['amount_inr'] ?? 0)
        );
    }

    header('Location: ' . $successRedirect);
    exit;
} catch (Throwable $e) {
    header('Location: ' . $failureRedirect);
    exit;
}
