<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function payment_gateway_key_id(): string
{
    return (string)($GLOBALS['config']['razorpay_key_id'] ?? '');
}

function payment_gateway_key_secret(): string
{
    return (string)($GLOBALS['config']['razorpay_key_secret'] ?? '');
}

function payment_support_email(): string
{
    return (string)($GLOBALS['config']['payment_support_email'] ?? 'jitender@eduquestiq.com');
}

function payment_gateway_ready(): bool
{
    return payment_gateway_key_id() !== '' && payment_gateway_key_secret() !== '';
}

function payment_gateway_mode(): string
{
    $keyId = payment_gateway_key_id();
    if (str_starts_with($keyId, 'rzp_live_')) {
        return 'live';
    }
    if (str_starts_with($keyId, 'rzp_test_')) {
        return 'test';
    }
    return 'unknown';
}

function payment_gateway_currency(): string
{
    return 'INR';
}

function amount_in_paise(float $inr): int
{
    return (int)round($inr * 100);
}

function price_from_paise(int $paise): string
{
    return number_format($paise / 100, 2);
}

function test_price_label(float $price): string
{
    return '₹' . number_format($price, 2);
}

function test_purchase_table_exists(PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'test_purchases'
         LIMIT 1"
    );
    $stmt->execute();
    return (bool)$stmt->fetchColumn();
}

function test_purchase_is_paid(PDO $pdo, int $testId, int $studentId): bool
{
    if (!test_purchase_table_exists($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM test_purchases
         WHERE test_id = ? AND student_id = ? AND payment_status = 'paid'
         LIMIT 1"
    );
    $stmt->execute([$testId, $studentId]);
    return (bool)$stmt->fetchColumn();
}

function test_purchase_row(PDO $pdo, int $testId, int $studentId): ?array
{
    if (!test_purchase_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM test_purchases
         WHERE test_id = ? AND student_id = ?
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([$testId, $studentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function test_purchase_upsert_pending(PDO $pdo, int $testId, int $studentId, float $amountInr, string $orderId, array $notes = []): int
{
    if (!test_purchase_table_exists($pdo)) {
        throw new RuntimeException('Purchase tracking table is missing.');
    }

    $existing = test_purchase_row($pdo, $testId, $studentId);
    $notesJson = $notes ? json_encode($notes, JSON_UNESCAPED_UNICODE) : null;

    if ($existing) {
        $stmt = $pdo->prepare(
            "UPDATE test_purchases
             SET gateway = 'razorpay',
                 gateway_order_id = ?,
                 gateway_payment_id = NULL,
                 gateway_signature = NULL,
                 amount_inr = ?,
                 currency = ?,
                 payment_status = 'pending',
                 notes_json = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $stmt->execute([$orderId, $amountInr, payment_gateway_currency(), $notesJson, (int)$existing['id']]);
        return (int)$existing['id'];
    }

    $stmt = $pdo->prepare(
        "INSERT INTO test_purchases
         (test_id, student_id, gateway, gateway_order_id, amount_inr, currency, payment_status, notes_json, created_at, updated_at)
         VALUES (?, ?, 'razorpay', ?, ?, ?, 'pending', ?, NOW(), NOW())"
    );
    $stmt->execute([$testId, $studentId, $orderId, $amountInr, payment_gateway_currency(), $notesJson]);
    return (int)$pdo->lastInsertId();
}

function test_purchase_mark_paid(PDO $pdo, int $testId, int $studentId, string $orderId, string $paymentId, string $signature, float $amountInr): void
{
    if (!test_purchase_table_exists($pdo)) {
        throw new RuntimeException('Purchase tracking table is missing.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO test_purchases
         (test_id, student_id, gateway, gateway_order_id, gateway_payment_id, gateway_signature, amount_inr, currency, payment_status, paid_at, created_at, updated_at)
         VALUES (?, ?, 'razorpay', ?, ?, ?, ?, ?, 'paid', NOW(), NOW(), NOW())
         ON DUPLICATE KEY UPDATE
             gateway = VALUES(gateway),
             gateway_order_id = VALUES(gateway_order_id),
             gateway_payment_id = VALUES(gateway_payment_id),
             gateway_signature = VALUES(gateway_signature),
             amount_inr = VALUES(amount_inr),
             currency = VALUES(currency),
             payment_status = VALUES(payment_status),
             paid_at = VALUES(paid_at),
             updated_at = VALUES(updated_at)"
    );
    $stmt->execute([
        $testId,
        $studentId,
        $orderId,
        $paymentId,
        $signature,
        $amountInr,
        payment_gateway_currency(),
    ]);
}

function razorpay_signature_is_valid(string $orderId, string $paymentId, string $signature): bool
{
    $secret = payment_gateway_key_secret();
    if ($secret === '') {
        return false;
    }

    $generated = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
    return hash_equals($generated, $signature);
}

function razorpay_create_order(float $amountInr, string $receipt, array $notes = []): array
{
    if (!payment_gateway_ready()) {
        throw new RuntimeException('Razorpay gateway is not configured.');
    }

    $payload = [
        'amount' => amount_in_paise($amountInr),
        'currency' => payment_gateway_currency(),
        'receipt' => $receipt,
        'notes' => $notes,
    ];

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    if (!$ch) {
        throw new RuntimeException('Failed to initialize Razorpay request.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => payment_gateway_key_id() . ':' . payment_gateway_key_secret(),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('Razorpay order creation failed' . ($error !== '' ? ': ' . $error : '.'));
    }

    $data = json_decode((string)$response, true);
    if (!is_array($data) || empty($data['id'])) {
        throw new RuntimeException('Invalid Razorpay order response.');
    }

    return $data;
}
