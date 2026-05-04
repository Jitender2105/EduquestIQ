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

if (!payment_gateway_ready()) {
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
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge text-bg-primary">Paid Test Access</span>
                    <span class="badge <?php echo payment_gateway_mode() === 'live' ? 'text-bg-success' : 'text-bg-warning text-dark'; ?>">
                        Razorpay <?php echo htmlspecialchars(strtoupper(payment_gateway_mode())); ?> mode
                    </span>
                </div>
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
                    <?php if (empty($errors)): ?>
                        <input type="hidden" id="payment-csrf-token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <div id="payment-message" class="alert d-none mb-3" role="alert"></div>
                        <button type="button" id="pay-now" class="btn btn-primary btn-lg w-100">
                            Pay <?php echo htmlspecialchars(test_price_label($priceInr)); ?>
                        </button>
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

<?php if (empty($errors)): ?>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
    (function () {
        const payButton = document.getElementById('pay-now');
        const csrfToken = document.getElementById('payment-csrf-token').value;
        const message = document.getElementById('payment-message');

        function showMessage(type, text) {
            message.className = 'alert alert-' + type + ' mb-3';
            message.textContent = text;
        }

        async function postJson(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });
            const data = await response.json().catch(function () {
                return {};
            });
            if (!response.ok || data.success === false) {
                throw new Error(data.error || 'Payment request failed.');
            }
            return data;
        }

        payButton.addEventListener('click', async function () {
            payButton.disabled = true;
            showMessage('info', 'Preparing secure payment...');

            let order;
            try {
                order = await postJson(<?php echo json_encode(url_for('api/create-order.php')); ?>, {
                    amount: <?php echo (int)amount_in_paise($priceInr); ?>,
                    currency: <?php echo json_encode(payment_gateway_currency()); ?>,
                    receipt: <?php echo json_encode('test-' . $testId); ?>,
                    test_id: <?php echo (int)$testId; ?>
                });
            } catch (error) {
                showMessage('danger', error.message);
                payButton.disabled = false;
                return;
            }

            if (order.already_paid && order.redirect_url) {
                window.location.href = order.redirect_url;
                return;
            }

            const rzp = new Razorpay({
                key: order.key_id,
                amount: order.amount,
                currency: order.currency,
                name: 'EduquestIQ',
                description: <?php echo json_encode((string)$test['title']); ?>,
                order_id: order.order_id,
                prefill: {
                    name: <?php echo json_encode((string)$user['name']); ?>,
                    email: <?php echo json_encode((string)$user['email']); ?>
                },
                handler: async function (response) {
                    try {
                        const verify = await postJson(<?php echo json_encode(url_for('api/verify-payment.php')); ?>, {
                            test_id: <?php echo (int)$testId; ?>,
                            razorpay_order_id: response.razorpay_order_id || '',
                            razorpay_payment_id: response.razorpay_payment_id || '',
                            razorpay_signature: response.razorpay_signature || ''
                        });
                        showMessage('success', 'Payment verified. Opening your test...');
                        window.location.href = verify.redirect_url || <?php echo json_encode(url_for('test_attempt.php?id=' . $testId . '&paid=1')); ?>;
                    } catch (error) {
                        showMessage('danger', error.message);
                        payButton.disabled = false;
                    }
                },
                theme: {
                    color: '#4374ff'
                },
                modal: {
                    ondismiss: function () {
                        showMessage('warning', 'Payment was cancelled. You can try again when ready.');
                        payButton.disabled = false;
                    }
                }
            });

            rzp.on('payment.failed', async function (response) {
                const reason = response && response.error && response.error.description
                    ? response.error.description
                    : 'Payment failed. Please try again.';

                const metadata = response && response.error && response.error.metadata
                    ? response.error.metadata
                    : {};
                const failedOrderId = metadata.order_id || '';
                const failedPaymentId = metadata.payment_id || '';

                if (failedOrderId && failedPaymentId) {
                    showMessage('info', 'Payment response received. Checking Razorpay status...');
                    try {
                        const reconcile = await postJson(<?php echo json_encode(url_for('api/reconcile-payment.php')); ?>, {
                            test_id: <?php echo (int)$testId; ?>,
                            razorpay_order_id: failedOrderId,
                            razorpay_payment_id: failedPaymentId,
                            error: response && response.error ? response.error : null
                        });

                        if (reconcile.success && reconcile.redirect_url) {
                            showMessage('success', 'Payment captured. Opening your test...');
                            window.location.href = reconcile.redirect_url;
                            return;
                        }
                    } catch (error) {
                        showMessage('danger', error.message || reason);
                        payButton.disabled = false;
                        return;
                    }
                } else {
                    showMessage('danger', reason);
                }

                payButton.disabled = false;
            });

            rzp.open();
        });
    })();
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
