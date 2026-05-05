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

function payment_gateway_webhook_secret(): string
{
    return (string)($GLOBALS['config']['razorpay_webhook_secret'] ?? '');
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

function inr_from_paise(int $paise): float
{
    return $paise / 100;
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

function test_purchase_find_by_order(PDO $pdo, string $orderId): ?array
{
    if (!test_purchase_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM test_purchases
         WHERE gateway_order_id = ?
         LIMIT 1'
    );
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function test_purchase_rows_by_order(PDO $pdo, string $orderId): array
{
    if (!test_purchase_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM test_purchases
         WHERE gateway_order_id = ?
         ORDER BY id ASC'
    );
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
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

function test_purchase_mark_failed(PDO $pdo, int $testId, int $studentId, string $orderId, ?string $paymentId, array $details = []): void
{
    if (!test_purchase_table_exists($pdo)) {
        throw new RuntimeException('Purchase tracking table is missing.');
    }

    $notesJson = $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;
    $stmt = $pdo->prepare(
        "UPDATE test_purchases
         SET gateway_payment_id = ?,
             payment_status = 'failed',
             notes_json = ?,
             updated_at = CURRENT_TIMESTAMP
         WHERE test_id = ? AND student_id = ? AND gateway_order_id = ?"
    );
    $stmt->execute([$paymentId, $notesJson, $testId, $studentId, $orderId]);
}

function practice_paper_table_exists(PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'practice_papers'
         LIMIT 1"
    );
    $stmt->execute();
    return (bool)$stmt->fetchColumn();
}

function practice_paper_purchase_table_exists(PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'practice_paper_purchases'
         LIMIT 1"
    );
    $stmt->execute();
    return (bool)$stmt->fetchColumn();
}

function practice_paper_purchase_is_paid(PDO $pdo, int $paperId, int $studentId): bool
{
    if (!practice_paper_purchase_table_exists($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM practice_paper_purchases
         WHERE practice_paper_id = ? AND student_id = ? AND payment_status = 'paid'
         LIMIT 1"
    );
    $stmt->execute([$paperId, $studentId]);
    return (bool)$stmt->fetchColumn();
}

function practice_paper_purchase_row(PDO $pdo, int $paperId, int $studentId): ?array
{
    if (!practice_paper_purchase_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM practice_paper_purchases
         WHERE practice_paper_id = ? AND student_id = ?
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([$paperId, $studentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function practice_paper_purchase_rows_by_order(PDO $pdo, string $orderId): array
{
    if (!practice_paper_purchase_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM practice_paper_purchases
         WHERE gateway_order_id = ?
         ORDER BY id ASC'
    );
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

function practice_paper_purchase_upsert_pending(PDO $pdo, int $paperId, int $studentId, float $amountInr, string $orderId, array $notes = []): int
{
    if (!practice_paper_purchase_table_exists($pdo)) {
        throw new RuntimeException('Practice paper purchase tracking table is missing.');
    }

    $existing = practice_paper_purchase_row($pdo, $paperId, $studentId);
    $notesJson = $notes ? json_encode($notes, JSON_UNESCAPED_UNICODE) : null;

    if ($existing) {
        $stmt = $pdo->prepare(
            "UPDATE practice_paper_purchases
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
        "INSERT INTO practice_paper_purchases
         (practice_paper_id, student_id, gateway, gateway_order_id, amount_inr, currency, payment_status, notes_json, created_at, updated_at)
         VALUES (?, ?, 'razorpay', ?, ?, ?, 'pending', ?, NOW(), NOW())"
    );
    $stmt->execute([$paperId, $studentId, $orderId, $amountInr, payment_gateway_currency(), $notesJson]);
    return (int)$pdo->lastInsertId();
}

function practice_paper_purchase_mark_paid(PDO $pdo, int $paperId, int $studentId, string $orderId, string $paymentId, string $signature, float $amountInr): void
{
    if (!practice_paper_purchase_table_exists($pdo)) {
        throw new RuntimeException('Practice paper purchase tracking table is missing.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO practice_paper_purchases
         (practice_paper_id, student_id, gateway, gateway_order_id, gateway_payment_id, gateway_signature, amount_inr, currency, payment_status, paid_at, created_at, updated_at)
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
        $paperId,
        $studentId,
        $orderId,
        $paymentId,
        $signature,
        $amountInr,
        payment_gateway_currency(),
    ]);
}

function practice_paper_purchase_mark_failed(PDO $pdo, int $paperId, int $studentId, string $orderId, ?string $paymentId, array $details = []): void
{
    if (!practice_paper_purchase_table_exists($pdo)) {
        throw new RuntimeException('Practice paper purchase tracking table is missing.');
    }

    $notesJson = $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;
    $stmt = $pdo->prepare(
        "UPDATE practice_paper_purchases
         SET gateway_payment_id = ?,
             payment_status = 'failed',
             notes_json = ?,
             updated_at = CURRENT_TIMESTAMP
         WHERE practice_paper_id = ? AND student_id = ? AND gateway_order_id = ?"
    );
    $stmt->execute([$paymentId, $notesJson, $paperId, $studentId, $orderId]);
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

function razorpay_webhook_signature_is_valid(string $payload, string $signature): bool
{
    $secret = payment_gateway_webhook_secret();
    if ($secret === '' || $signature === '') {
        return false;
    }

    $generated = hash_hmac('sha256', $payload, $secret);
    return hash_equals($generated, $signature);
}

function razorpay_create_order(float $amountInr, string $receipt, array $notes = []): array
{
    return razorpay_create_order_paise(amount_in_paise($amountInr), $receipt, $notes);
}

function razorpay_create_order_paise(int $amountPaise, string $receipt, array $notes = []): array
{
    if (!payment_gateway_ready()) {
        throw new RuntimeException('Razorpay gateway is not configured.');
    }
    if ($amountPaise < 100) {
        throw new RuntimeException('Razorpay order amount must be at least 100 paise.');
    }

    $payload = [
        'amount' => $amountPaise,
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

function razorpay_capture_payment(string $paymentId, int $amountPaise, string $currency = 'INR'): array
{
    if (!payment_gateway_ready()) {
        throw new RuntimeException('Razorpay gateway is not configured.');
    }
    if ($amountPaise < 100) {
        throw new RuntimeException('Razorpay capture amount must be at least 100 paise.');
    }

    $ch = curl_init('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId) . '/capture');
    if (!$ch) {
        throw new RuntimeException('Failed to initialize Razorpay capture request.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => payment_gateway_key_id() . ':' . payment_gateway_key_secret(),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'amount' => $amountPaise,
            'currency' => $currency,
        ], JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('Razorpay payment capture failed' . ($error !== '' ? ': ' . $error : '.'));
    }

    $data = json_decode((string)$response, true);
    if (!is_array($data) || empty($data['id'])) {
        throw new RuntimeException('Invalid Razorpay capture response.');
    }

    return $data;
}

function razorpay_fetch_payment(string $paymentId): array
{
    if (!payment_gateway_ready()) {
        throw new RuntimeException('Razorpay gateway is not configured.');
    }

    $paymentId = trim($paymentId);
    if ($paymentId === '') {
        throw new RuntimeException('Payment id is required.');
    }

    $ch = curl_init('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId));
    if (!$ch) {
        throw new RuntimeException('Failed to initialize Razorpay request.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => payment_gateway_key_id() . ':' . payment_gateway_key_secret(),
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('Razorpay payment lookup failed' . ($error !== '' ? ': ' . $error : '.'));
    }

    $data = json_decode((string)$response, true);
    if (!is_array($data) || empty($data['id'])) {
        throw new RuntimeException('Invalid Razorpay payment lookup response.');
    }

    return $data;
}
