<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';
require_once __DIR__ . '/includes_csrf.php';
require_once __DIR__ . '/includes_payments.php';

$user = current_user();
if (!$user || ($user['role'] ?? '') !== 'student') {
    header('Location: ' . url_for('login.php'));
    exit;
}

$pdo = get_pdo();
$errors = [];
$order = null;
$purchaseSummary = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . url_for('tests.php'));
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    $errors[] = 'Invalid form token. Please go back and try again.';
}

$rawItemsJson = (string)($_POST['items_json'] ?? '');
$rawItems = json_decode($rawItemsJson, true);
if (!is_array($rawItems) || $rawItems === []) {
    $errors[] = 'No paid items were selected.';
}

if ($errors === []) {
    try {
        $studentId = (int)$user['sub'];
        $studentGradeStmt = $pdo->prepare('SELECT grade FROM users WHERE id = ? LIMIT 1');
        $studentGradeStmt->execute([$studentId]);
        $studentGrade = trim((string)$studentGradeStmt->fetchColumn());
        $testsHaveActiveColumn = table_has_column($pdo, 'tests', 'is_active');
        $testsHaveGradeColumn = table_has_column($pdo, 'tests', 'target_grade');
        $papersHaveActiveColumn = practice_paper_table_exists($pdo) && table_has_column($pdo, 'practice_papers', 'is_active');

        $purchaseItems = [];
        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = (string)($item['type'] ?? '');
            $id = (int)($item['id'] ?? 0);
            if (!in_array($type, ['test', 'practice_paper'], true) || $id <= 0) {
                throw new RuntimeException('Invalid purchase item selected.');
            }

            $key = $type . ':' . $id;
            if (isset($purchaseItems[$key])) {
                continue;
            }

            if ($type === 'test') {
                $stmt = $pdo->prepare(
                    'SELECT id, title, price_inr'
                    . ($testsHaveActiveColumn ? ', is_active' : '')
                    . ($testsHaveGradeColumn ? ', target_grade' : '')
                    . ' FROM tests WHERE id = ? LIMIT 1'
                );
                $stmt->execute([$id]);
                $test = $stmt->fetch();
                if (!$test) {
                    throw new RuntimeException('A selected test no longer exists.');
                }
                if (($testsHaveActiveColumn && empty($test['is_active']))
                    || ($testsHaveGradeColumn && $studentGrade !== '' && !empty($test['target_grade']) && (string)$test['target_grade'] !== $studentGrade)) {
                    throw new RuntimeException('One selected test is not available for your class.');
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
                    throw new RuntimeException('Practice papers are not configured yet.');
                }
                $stmt = $pdo->prepare(
                    'SELECT id, name, amount_inr, access_type'
                    . ($papersHaveActiveColumn ? ', is_active' : ', status')
                    . ' FROM practice_papers WHERE id = ? LIMIT 1'
                );
                $stmt->execute([$id]);
                $paper = $stmt->fetch();
                if (!$paper) {
                    throw new RuntimeException('A selected practice paper no longer exists.');
                }
                if (($papersHaveActiveColumn && empty($paper['is_active']))
                    || (!$papersHaveActiveColumn && (string)($paper['status'] ?? '') !== 'published')) {
                    throw new RuntimeException('One selected practice paper is not active.');
                }
                if (practice_paper_purchase_is_paid($pdo, $id, $studentId)) {
                    continue;
                }

                $itemAmountPaise = ((string)($paper['access_type'] ?? 'free') === 'paid')
                    ? amount_in_paise(max(0.0, (float)($paper['amount_inr'] ?? 0)))
                    : 0;
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
            header('Location: ' . url_for('tests.php?purchase=ready'));
            exit;
        }

        $amountPaise = array_sum(array_column($purchaseItems, 'amount_paise'));
        if ($amountPaise < 100) {
            throw new RuntimeException('Selected items do not require payment.');
        }

        $receipt = 'cart-student-' . $studentId . '-' . time();
        $notes = [
            'student_id' => (string)$studentId,
            'student_email' => (string)$user['email'],
            'support_email' => payment_support_email(),
            'purchase_type' => 'bulk',
            'item_count' => (string)count($purchaseItems),
            'purchase_summary' => 'EduquestIQ bulk catalogue purchase',
        ];

        $order = razorpay_create_order_paise($amountPaise, substr($receipt, 0, 40), $notes);

        foreach ($purchaseItems as $item) {
            $itemNotes = $notes;
            $itemNotes['item_type'] = $item['type'];
            $itemNotes['item_id'] = (string)$item['id'];
            if ($item['type'] === 'test') {
                test_purchase_upsert_pending($pdo, (int)$item['id'], $studentId, inr_from_paise((int)$item['amount_paise']), (string)$order['id'], $itemNotes);
            } else {
                practice_paper_purchase_upsert_pending($pdo, (int)$item['id'], $studentId, inr_from_paise((int)$item['amount_paise']), (string)$order['id'], $itemNotes);
            }
            $purchaseSummary[] = [
                'title' => $item['title'],
                'amount' => price_from_paise((int)$item['amount_paise']),
                'type' => $item['type'],
            ];
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

require_once __DIR__ . '/includes_header.php';
?>
<div class="eq-page-head">
    <h2>Secure Bulk Checkout</h2>
    <p class="subtitle">This mobile-safe checkout opens Razorpay from a dedicated payment page, which works more reliably in mobile browsers and in-app webviews.</p>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars(url_for('tests.php')); ?>">Back to Tests</a>
<?php elseif ($order): ?>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-3">Your Selected Items</h4>
                    <ul class="list-group list-group-flush mb-4">
                        <?php foreach ($purchaseSummary as $item): ?>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($item['title']); ?></span>
                                <strong><?php echo htmlspecialchars('₹' . $item['amount']); ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="d-flex justify-content-between align-items-center border-top pt-3">
                        <span class="text-muted">Total payable</span>
                        <strong class="fs-4"><?php echo htmlspecialchars('₹' . price_from_paise((int)$order['amount'])); ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-3">Continue to Razorpay</h4>
                    <p class="text-muted">Tap once to open Razorpay checkout. This direct tap flow is more stable on mobile devices.</p>
                    <div id="checkout-message" class="alert d-none" role="alert"></div>
                    <button type="button" class="btn btn-warning btn-lg w-100 fw-semibold" id="launch-bulk-payment">Pay Securely</button>
                    <a class="btn btn-outline-secondary w-100 mt-3" href="<?php echo htmlspecialchars(url_for('tests.php')); ?>">Cancel and return</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
    (function () {
        const button = document.getElementById('launch-bulk-payment');
        const message = document.getElementById('checkout-message');
        if (!button || !message) {
            return;
        }

        function showMessage(type, text) {
            message.className = 'alert alert-' + type;
            message.textContent = text;
            message.classList.remove('d-none');
        }

        async function postJson(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': <?php echo json_encode(csrf_token()); ?>},
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });
            const data = await response.json().catch(function () { return {}; });
            if (!response.ok || data.success === false) {
                throw new Error(data.error || 'Payment verification failed.');
            }
            return data;
        }

        button.addEventListener('click', function () {
            button.disabled = true;
            showMessage('info', 'Opening Razorpay...');

            const rzp = new Razorpay({
                key: <?php echo json_encode(payment_gateway_key_id()); ?>,
                amount: <?php echo (int)$order['amount']; ?>,
                currency: <?php echo json_encode((string)$order['currency']); ?>,
                name: 'EduquestIQ',
                description: 'EduquestIQ bulk purchase',
                order_id: <?php echo json_encode((string)$order['id']); ?>,
                callback_url: <?php echo json_encode(url_for('razorpay_return.php?source=tests')); ?>,
                redirect: true,
                prefill: {
                    name: <?php echo json_encode((string)$user['name']); ?>,
                    email: <?php echo json_encode((string)$user['email']); ?>
                },
                handler: async function (response) {
                    try {
                        const verify = await postJson(<?php echo json_encode(url_for('api/verify-payment.php')); ?>, {
                            razorpay_order_id: response.razorpay_order_id || '',
                            razorpay_payment_id: response.razorpay_payment_id || '',
                            razorpay_signature: response.razorpay_signature || ''
                        });
                        window.location.href = verify.redirect_url || <?php echo json_encode(url_for('tests.php?purchase=success')); ?>;
                    } catch (error) {
                        showMessage('danger', error.message);
                        button.disabled = false;
                    }
                },
                modal: {
                    ondismiss: function () {
                        showMessage('warning', 'Payment was cancelled. You can try again when ready.');
                        button.disabled = false;
                    }
                },
                theme: {color: '#4374ff'}
            });

            rzp.on('payment.failed', function (response) {
                const reason = response && response.error && response.error.description
                    ? response.error.description
                    : 'Payment failed. Please try again.';
                showMessage('danger', reason);
                button.disabled = false;
            });

            rzp.open();
        });
    })();
    </script>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes_footer.php';
