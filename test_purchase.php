<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_csrf.php';
require_once __DIR__ . '/includes_payments.php';

$user = require_auth(['student']);
$pdo = get_pdo();

$testId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['test_id'] ?? 0);
if ($testId <= 0) {
    header('Location: ' . url_for('tests.php'));
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, title, description, start_at, end_at, total_marks, duration_minutes, price_inr
     FROM tests
     WHERE id = ?'
);
$stmt->execute([$testId]);
$test = $stmt->fetch();

if (!$test) {
    header('Location: ' . url_for('tests.php'));
    exit;
}

$priceInr = max(0.0, (float)($test['price_inr'] ?? 0));
if ($priceInr <= 0) {
    header('Location: ' . url_for('test_attempt.php?id=' . $testId));
    exit;
}

if (test_purchase_is_paid($pdo, $testId, (int)$user['sub'])) {
    header('Location: ' . url_for('test_attempt.php?id=' . $testId));
    exit;
}

$errors = [];
$purchaseRow = test_purchase_row($pdo, $testId, (int)$user['sub']);
$pendingOrderId = $purchaseRow['gateway_order_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['payment_action'] ?? '') === 'verify') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $orderId = trim((string)($_POST['razorpay_order_id'] ?? ''));
        $paymentId = trim((string)($_POST['razorpay_payment_id'] ?? ''));
        $signature = trim((string)($_POST['razorpay_signature'] ?? ''));
        if ($orderId === '' || $paymentId === '' || $signature === '') {
            $errors[] = 'Payment details are incomplete.';
        } elseif ($pendingOrderId !== '' && $orderId !== $pendingOrderId) {
            $errors[] = 'Payment order mismatch.';
        } elseif (!razorpay_signature_is_valid($orderId, $paymentId, $signature)) {
            $errors[] = 'Payment signature verification failed.';
        } else {
            test_purchase_mark_paid($pdo, $testId, (int)$user['sub'], $orderId, $paymentId, $signature, $priceInr);
            header('Location: ' . url_for('test_attempt.php?id=' . $testId . '&paid=1'));
            exit;
        }
    }
}

$order = null;
if ($pendingOrderId !== '' && $purchaseRow && ($purchaseRow['payment_status'] ?? '') === 'pending') {
    $order = [
        'id' => $pendingOrderId,
        'amount' => amount_in_paise($priceInr),
        'currency' => payment_gateway_currency(),
    ];
} elseif (payment_gateway_ready()) {
    try {
        $receipt = 'test-' . $testId . '-student-' . (int)$user['sub'] . '-' . time();
        $order = razorpay_create_order($priceInr, $receipt, [
            'test_id' => (string)$testId,
            'student_id' => (string)$user['sub'],
            'test_title' => (string)$test['title'],
            'support_email' => payment_support_email(),
        ]);
        test_purchase_upsert_pending($pdo, $testId, (int)$user['sub'], $priceInr, (string)$order['id'], [
            'test_title' => (string)$test['title'],
            'student_name' => (string)$user['name'],
        ]);
        $pendingOrderId = (string)$order['id'];
    } catch (Throwable $e) {
        $errors[] = 'Could not start payment checkout: ' . $e->getMessage();
    }
} else {
    $errors[] = 'Payment gateway is not configured. Please contact ' . payment_support_email() . '.';
}
?>

<style>
    .eq-purchase-shell {
        max-width: 1080px;
        margin: 0 auto;
    }
    .eq-purchase-hero {
        border: 1px solid rgba(47, 59, 120, 0.08);
        border-radius: 28px;
        background: linear-gradient(135deg, rgba(67,116,255,0.10), rgba(160,74,255,0.12));
        box-shadow: 0 18px 40px rgba(37, 49, 104, 0.08);
        padding: 28px;
    }
    .eq-purchase-card {
        border: 1px solid rgba(47, 59, 120, 0.08);
        border-radius: 24px;
        background: #fff;
        box-shadow: 0 14px 30px rgba(37, 49, 104, 0.08);
    }
</style>

<div class="eq-page-head">
    <h2>Purchase Test Access</h2>
    <p class="subtitle">Pay for this test once, then start the attempt from your student dashboard.</p>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="eq-purchase-shell">
    <div class="eq-purchase-hero mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <div class="badge text-bg-primary mb-3">Paid Test Access</div>
                <h3 class="mb-3"><?php echo htmlspecialchars((string)$test['title']); ?></h3>
                <p class="mb-3 text-muted"><?php echo htmlspecialchars(text_preview(strip_tags((string)$test['description']), 260, '...')); ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge text-bg-light">Duration: <?php echo (int)$test['duration_minutes']; ?> min</span>
                    <span class="badge text-bg-light">Marks: <?php echo (int)$test['total_marks']; ?></span>
                    <span class="badge text-bg-success">Price: <?php echo htmlspecialchars(test_price_label($priceInr)); ?></span>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="eq-purchase-card p-4">
                    <h5 class="mb-3">What happens next</h5>
                    <ol class="small text-muted mb-4">
                        <li>We create a secure Razorpay order for this test.</li>
                        <li>You complete payment using cards, UPI, netbanking, or wallet methods supported by Razorpay.</li>
                        <li>After payment, the test becomes available immediately.</li>
                    </ol>
                    <?php if ($order && empty($errors)): ?>
                        <form id="payment-form" method="post">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="payment_action" value="verify">
                            <input type="hidden" name="test_id" value="<?php echo (int)$testId; ?>">
                            <input type="hidden" name="razorpay_order_id" id="razorpay_order_id">
                            <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
                            <input type="hidden" name="razorpay_signature" id="razorpay_signature">
                            <button type="button" id="pay-now" class="btn btn-primary btn-lg w-100">
                                Pay <?php echo htmlspecialchars(test_price_label($priceInr)); ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0">Payment checkout is not ready yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="eq-purchase-card p-4 h-100">
                <h5 class="mb-3">Test Details</h5>
                <div class="small text-muted">
                    Start: <?php echo !empty($test['start_at']) ? htmlspecialchars((new DateTimeImmutable((string)$test['start_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y, h:i A')) : 'Not set'; ?><br>
                    End: <?php echo !empty($test['end_at']) ? htmlspecialchars((new DateTimeImmutable((string)$test['end_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y, h:i A')) : 'Not set'; ?><br>
                    Price: <?php echo htmlspecialchars(test_price_label($priceInr)); ?>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="eq-purchase-card p-4 h-100">
                <h5 class="mb-3">Secure payment notice</h5>
                <p class="text-muted mb-3">Your payment is handled by Razorpay checkout and confirmed server-side using the payment signature. That means the test unlocks only after a real successful payment.</p>
                <p class="text-muted mb-0">For payment support, contact <a href="mailto:<?php echo htmlspecialchars(payment_support_email()); ?>"><?php echo htmlspecialchars(payment_support_email()); ?></a>.</p>
            </div>
        </div>
    </div>
</div>

<?php if ($order && empty($errors)): ?>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
    (function () {
        const options = {
            key: <?php echo json_encode(payment_gateway_key_id()); ?>,
            amount: <?php echo (int)amount_in_paise($priceInr); ?>,
            currency: <?php echo json_encode(payment_gateway_currency()); ?>,
            name: 'EduquestIQ',
            description: <?php echo json_encode((string)$test['title']); ?>,
            order_id: <?php echo json_encode((string)$order['id']); ?>,
            prefill: {
                name: <?php echo json_encode((string)$user['name']); ?>,
                email: <?php echo json_encode((string)$user['email']); ?>
            },
            handler: function (response) {
                document.getElementById('razorpay_order_id').value = response.razorpay_order_id || '';
                document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id || '';
                document.getElementById('razorpay_signature').value = response.razorpay_signature || '';
                document.getElementById('payment-form').submit();
            },
            theme: {
                color: '#4374ff'
            },
            modal: {
                ondismiss: function () {
                    // Keep user on page so they can retry.
                }
            }
        };

        const rzp = new Razorpay(options);
        document.getElementById('pay-now').addEventListener('click', function () {
            rzp.open();
        });
    })();
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
