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
$rawItems = $input['items'] ?? [];

if ($currency !== payment_gateway_currency()) {
    api_json_response(400, ['success' => false, 'error' => 'Unsupported currency.']);
}
if ((!is_array($rawItems) || $rawItems === []) && $amountPaise < 100) {
    api_json_response(400, ['success' => false, 'error' => 'Amount must be at least 100 paise.']);
}
if ($receipt === '') {
    $receipt = 'eduquestiq-' . time();
}

try {
    $pdo = get_pdo();
    $studentId = (int)$user['sub'];
    $notes = [
        'student_id' => (string)$user['sub'],
        'student_email' => (string)$user['email'],
        'support_email' => payment_support_email(),
    ];
    $purchaseItems = [];

    if (is_array($rawItems) && $rawItems !== []) {
        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = (string)($item['type'] ?? '');
            $id = (int)($item['id'] ?? 0);
            if (!in_array($type, ['test', 'practice_paper'], true) || $id <= 0) {
                api_json_response(400, ['success' => false, 'error' => 'Invalid purchase item.']);
            }

            $key = $type . ':' . $id;
            if (isset($purchaseItems[$key])) {
                continue;
            }

            if ($type === 'test') {
                $stmt = $pdo->prepare('SELECT id, title, price_inr FROM tests WHERE id = ? LIMIT 1');
                $stmt->execute([$id]);
                $test = $stmt->fetch();
                if (!$test) {
                    api_json_response(400, ['success' => false, 'error' => 'Invalid test selected.']);
                }
                if (test_purchase_is_paid($pdo, $id, $studentId)) {
                    continue;
                }

                $itemAmountPaise = amount_in_paise(max(0.0, (float)($test['price_inr'] ?? 0)));
                if ($itemAmountPaise < 100) {
                    continue;
                }
                $purchaseItems[$key] = [
                    'type' => 'test',
                    'id' => $id,
                    'title' => (string)$test['title'],
                    'amount_paise' => $itemAmountPaise,
                ];
            } else {
                if (!practice_paper_table_exists($pdo)) {
                    api_json_response(400, ['success' => false, 'error' => 'Practice papers are not configured yet.']);
                }
                $stmt = $pdo->prepare('SELECT id, name, amount_inr, access_type FROM practice_papers WHERE id = ? AND status = "published" LIMIT 1');
                $stmt->execute([$id]);
                $paper = $stmt->fetch();
                if (!$paper) {
                    api_json_response(400, ['success' => false, 'error' => 'Invalid practice paper selected.']);
                }
                if (practice_paper_purchase_is_paid($pdo, $id, $studentId)) {
                    continue;
                }

                $itemAmountPaise = ((string)($paper['access_type'] ?? 'free') === 'paid') ? amount_in_paise(max(0.0, (float)($paper['amount_inr'] ?? 0))) : 0;
                if ($itemAmountPaise < 100) {
                    continue;
                }
                $purchaseItems[$key] = [
                    'type' => 'practice_paper',
                    'id' => $id,
                    'title' => (string)$paper['name'],
                    'amount_paise' => $itemAmountPaise,
                ];
            }
        }

        if ($purchaseItems === []) {
            api_json_response(200, [
                'success' => true,
                'already_paid' => true,
                'redirect_url' => url_for('tests.php?purchase=ready'),
            ]);
        }

        $amountPaise = array_sum(array_column($purchaseItems, 'amount_paise'));
        if ($amountPaise < 100) {
            api_json_response(400, ['success' => false, 'error' => 'Selected items do not require payment.']);
        }
        $receipt = 'cart-student-' . $studentId . '-' . time();
        $notes['purchase_type'] = 'bulk';
        $notes['item_count'] = (string)count($purchaseItems);
        $notes['purchase_summary'] = 'EduquestIQ bulk catalogue purchase';
    } elseif ($testId > 0) {
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
        if (test_purchase_is_paid($pdo, $testId, $studentId)) {
            api_json_response(200, [
                'success' => true,
                'already_paid' => true,
                'redirect_url' => url_for('tests.php?purchase=ready'),
            ]);
        }

        $receipt = 'test-' . $testId . '-student-' . $studentId . '-' . time();
        $notes['test_id'] = (string)$testId;
        $notes['test_title'] = (string)$test['title'];
        $purchaseItems['test:' . $testId] = [
            'type' => 'test',
            'id' => $testId,
            'title' => (string)$test['title'],
            'amount_paise' => $serverAmountPaise,
        ];
    }

    $order = razorpay_create_order_paise($amountPaise, substr($receipt, 0, 40), $notes);

    if ($purchaseItems !== []) {
        foreach ($purchaseItems as $item) {
            $itemNotes = $notes;
            $itemNotes['item_type'] = $item['type'];
            $itemNotes['item_id'] = (string)$item['id'];
            if ($item['type'] === 'test') {
                test_purchase_upsert_pending($pdo, (int)$item['id'], $studentId, inr_from_paise((int)$item['amount_paise']), (string)$order['id'], $itemNotes);
            } else {
                practice_paper_purchase_upsert_pending($pdo, (int)$item['id'], $studentId, inr_from_paise((int)$item['amount_paise']), (string)$order['id'], $itemNotes);
            }
        }
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
