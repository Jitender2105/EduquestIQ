<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_csrf.php';
require_once __DIR__ . '/includes_fallback.php';
require_once __DIR__ . '/includes_payments.php';

$pdo = get_pdo();

$tests = [];
$practicePapers = [];
if ($authUser) {
    $stmt = $pdo->query(
        'SELECT t.id, t.title, t.description, t.start_at, t.end_at, t.total_marks, t.duration_minutes, t.price_inr, t.created_at,
                u.name AS teacher_name
         FROM tests t
         LEFT JOIN users u ON t.created_by = u.id
         ORDER BY t.created_at DESC'
    );
    $tests = $stmt->fetchAll();

    if (practice_paper_table_exists($pdo)) {
        $practicePapers = $pdo->query(
            'SELECT pp.*, t.title AS test_title
             FROM practice_papers pp
             JOIN tests t ON t.id = pp.test_id
             WHERE pp.status = "published"
             ORDER BY pp.created_at DESC, pp.id DESC'
        )->fetchAll();
    }
}

$attempted = [];
$attemptIds = [];
$paidTests = [];
$paidPracticePapers = [];
$nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
if ($authUser && $authUser['role'] === 'student') {
    $stmt = $pdo->prepare(
        'SELECT test_id, id
         FROM test_attempts
         WHERE student_id = ?
         ORDER BY attempt_date DESC, id DESC'
    );
    $stmt->execute([(int)$authUser['sub']]);
    foreach ($stmt->fetchAll() as $row) {
        $testId = (int)$row['test_id'];
        if (!isset($attemptIds[$testId])) {
            $attemptIds[$testId] = (int)$row['id'];
            $attempted[$testId] = true;
        }
    }

    $stmt = $pdo->prepare(
        'SELECT test_id
         FROM test_purchases
         WHERE student_id = ? AND payment_status = "paid"'
    );
    if ($stmt) {
        $stmt->execute([(int)$authUser['sub']]);
        foreach ($stmt->fetchAll() as $row) {
            $paidTests[(int)$row['test_id']] = true;
        }
    }

    if (practice_paper_purchase_table_exists($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT practice_paper_id
             FROM practice_paper_purchases
             WHERE student_id = ? AND payment_status = "paid"'
        );
        $stmt->execute([(int)$authUser['sub']]);
        foreach ($stmt->fetchAll() as $row) {
            $paidPracticePapers[(int)$row['practice_paper_id']] = true;
        }
    }
}
?>

<div class="eq-page-head">
    <h2>Tests</h2>
    <p class="subtitle">Attempt MCQ and subjective assessments mapped to attributes and sub-attributes for live skill tracking.</p>
</div>

<?php if (!empty($_GET['purchase'])): ?>
    <div class="alert alert-success">Purchase status updated. You can start purchased tests or download purchased practice papers below.</div>
<?php endif; ?>

<?php if (!$tests && !$practicePapers): ?>
    <?php if (!$authUser): ?>
        <?php
        render_static_fallback([
            'eyebrow' => 'Assessment Center',
            'title' => 'Sign in to view available tests',
            'description' => 'Test listings are hidden until you log in. After login, students can see test prices, buy access where needed, and attempt the exam.',
            'points' => [
                'Students can buy paid tests securely before attempting.',
                'All attempts stay tied to your student account.',
                'SIRA reports open automatically after submission.',
            ],
            'cards' => [
                ['title' => 'Secure access', 'meta' => 'Login required', 'text' => 'Keep paid assessments and reports private to student accounts.'],
                ['title' => 'Payment protected', 'meta' => 'Razorpay checkout', 'text' => 'Checkout opens only when a test has a price set.'],
                ['title' => 'Personal reports', 'meta' => 'SIRA', 'text' => 'Each attempt unlocks skill scoring and feedback.'],
            ],
            'primary_label' => 'Login',
            'primary_link' => url_for('login.php'),
            'secondary_label' => 'Register',
            'secondary_link' => url_for('register.php'),
        ]);
        ?>
    <?php else: ?>
    <?php
    render_static_fallback([
        'eyebrow' => 'Assessment Center',
        'title' => 'No tests published yet',
        'description' => 'This section will display active MCQ and subjective tests as soon as your assessment bank is added.',
        'points' => [
            'Questions can map to multiple attributes and sub-attributes.',
            'Weighted scoring updates skill progress automatically.',
            'Students can attempt tests and view attempt status in real time.',
        ],
        'cards' => [
            ['title' => 'Math Mastery Check', 'meta' => '40 marks · 30 min', 'text' => 'Tracks mathematics sub-skills with weighted performance mapping.'],
            ['title' => 'Creative Thinking Sprint', 'meta' => '25 marks · 20 min', 'text' => 'Blends MCQ + subjective responses for innovation profiling.'],
            ['title' => 'Leadership Readiness', 'meta' => '30 marks · 25 min', 'text' => 'Measures communication, teamwork, and initiative indicators.'],
        ],
        'primary_label' => 'Go to Dashboard',
        'primary_link' => url_for('dashboard.php'),
        'secondary_label' => 'Add Tests in Backend',
        'secondary_link' => url_for('manage_lms.php'),
    ]);
    ?>
    <?php endif; ?>
<?php else: ?>
    <?php if ($authUser && $authUser['role'] === 'student'): ?>
        <input type="hidden" id="payment-csrf-token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        <div id="payment-message" class="alert d-none" role="alert"></div>
        <div class="card p-3 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="mb-1">Bulk Purchase</h5>
                    <div class="text-muted small">Select multiple paid tests and practice papers, then pay once.</div>
                </div>
                <button type="button" class="btn btn-primary" id="bulk-buy-button">Buy Selected Items</button>
            </div>
        </div>
    <?php endif; ?>

    <div class="eq-page-head text-start">
        <h3>Tests</h3>
        <p class="subtitle">You can buy upcoming tests before the start date. Starting is enabled only during the test window.</p>
    </div>
    <div class="row g-3">
        <?php foreach ($tests as $test): ?>
            <?php
                $startAt = !empty($test['start_at']) ? new DateTimeImmutable((string)$test['start_at'], new DateTimeZone('UTC')) : null;
                $endAt = !empty($test['end_at']) ? new DateTimeImmutable((string)$test['end_at'], new DateTimeZone('UTC')) : null;
                $statusLabel = 'Open';
                $statusClass = 'text-bg-success';
                $canAttempt = true;
                if ($startAt && $nowUtc < $startAt) {
                    $statusLabel = 'Upcoming';
                    $statusClass = 'text-bg-warning';
                    $canAttempt = false;
                } elseif ($endAt && $nowUtc > $endAt) {
                    $statusLabel = 'Closed';
                    $statusClass = 'text-bg-secondary';
                    $canAttempt = false;
                }
                $testPrice = (float)($test['price_inr'] ?? 0);
                $isPaidTest = $testPrice > 0;
                $hasPaidTest = !$isPaidTest || !empty($paidTests[(int)$test['id']]);
            ?>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between gap-2 align-items-start mb-2">
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($test['title']); ?></h5>
                            <span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                        </div>
                        <p class="card-text small text-muted flex-grow-1">
                            <?php echo htmlspecialchars(text_preview(strip_tags((string)$test['description']), 140, '...')); ?>
                        </p>
                        <p class="small mb-2">
                            <?php if ($test['teacher_name']): ?>
                                Teacher: <?php echo htmlspecialchars($test['teacher_name']); ?><br>
                            <?php endif; ?>
                            Marks: <?php echo (int)$test['total_marks']; ?> |
                            Duration: <?php echo (int)$test['duration_minutes']; ?> min<br>
                            Start: <?php echo $startAt ? htmlspecialchars($startAt->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y, h:i A')) : 'Not set'; ?><br>
                            End: <?php echo $endAt ? htmlspecialchars($endAt->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y, h:i A')) : 'Not set'; ?>
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <?php if ($authUser && $authUser['role'] === 'student'): ?>
                                <?php if (isset($attempted[(int)$test['id']])): ?>
                                    <a href="<?php echo htmlspecialchars(url_for('sira_report.php?attempt_id=' . (int)$attemptIds[(int)$test['id']])); ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        View SIRA Report
                                    </a>
                                <?php else: ?>
                                    <?php if (!$hasPaidTest): ?>
                                        <div class="form-check">
                                            <input class="form-check-input bulk-purchase-item" type="checkbox" value="test:<?php echo (int)$test['id']; ?>" id="buy-test-<?php echo (int)$test['id']; ?>" data-title="<?php echo htmlspecialchars($test['title']); ?>" data-amount="<?php echo (int)amount_in_paise($testPrice); ?>">
                                            <label class="form-check-label small" for="buy-test-<?php echo (int)$test['id']; ?>">
                                                Buy for <?php echo htmlspecialchars(test_price_label($testPrice)); ?>
                                            </label>
                                        </div>
                                    <?php elseif ($canAttempt): ?>
                                        <a href="<?php echo htmlspecialchars(url_for('test_attempt.php?id=' . (int)$test['id'])); ?>"
                                           class="btn btn-sm btn-primary">
                                            Start Test
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled><?php echo $statusLabel === 'Upcoming' ? 'Purchased - starts later' : 'Not available'; ?></button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted small">Login as a student to attempt.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="eq-page-head text-start mt-5">
        <h3>Practice Papers</h3>
        <p class="subtitle">Download free or purchased PDFs for revision and preparation.</p>
    </div>
    <div class="row g-3">
        <?php foreach ($practicePapers as $paper): ?>
            <?php
                $paperPrice = (float)($paper['amount_inr'] ?? 0);
                $isPaidPaper = (string)($paper['access_type'] ?? 'free') === 'paid' && $paperPrice > 0;
                $hasPaperAccess = !$isPaidPaper || !empty($paidPracticePapers[(int)$paper['id']]);
            ?>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between gap-2 align-items-start mb-2">
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($paper['name']); ?></h5>
                            <span class="badge <?php echo $isPaidPaper ? 'text-bg-warning text-dark' : 'text-bg-success'; ?>"><?php echo $isPaidPaper ? htmlspecialchars(test_price_label($paperPrice)) : 'Free'; ?></span>
                        </div>
                        <p class="small text-muted mb-2">Mapped Test: <?php echo htmlspecialchars($paper['test_title']); ?></p>
                        <p class="card-text small text-muted flex-grow-1"><?php echo htmlspecialchars(text_preview(strip_tags((string)$paper['description']), 140, '...')); ?></p>
                        <p class="small mb-3">Class: <?php echo htmlspecialchars($paper['class_name']); ?> | Year: <?php echo htmlspecialchars($paper['paper_year']); ?></p>
                        <?php if ($authUser && $authUser['role'] === 'student'): ?>
                            <?php if ($hasPaperAccess): ?>
                                <a class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars(url_for('practice_paper_download.php?id=' . (int)$paper['id'])); ?>">Download PDF</a>
                            <?php else: ?>
                                <div class="form-check">
                                    <input class="form-check-input bulk-purchase-item" type="checkbox" value="practice_paper:<?php echo (int)$paper['id']; ?>" id="buy-paper-<?php echo (int)$paper['id']; ?>" data-title="<?php echo htmlspecialchars($paper['name']); ?>" data-amount="<?php echo (int)amount_in_paise($paperPrice); ?>">
                                    <label class="form-check-label small" for="buy-paper-<?php echo (int)$paper['id']; ?>">
                                        Buy for <?php echo htmlspecialchars(test_price_label($paperPrice)); ?>
                                    </label>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted small">Login as a student to download.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$practicePapers): ?>
            <div class="col-12"><div class="alert alert-light border">No practice papers are published yet.</div></div>
        <?php endif; ?>
    </div>

    <?php if ($authUser && $authUser['role'] === 'student'): ?>
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script>
        (function () {
            const button = document.getElementById('bulk-buy-button');
            const csrfToken = document.getElementById('payment-csrf-token') ? document.getElementById('payment-csrf-token').value : '';
            const message = document.getElementById('payment-message');
            if (!button || !message) return;

            function showMessage(type, text) {
                message.className = 'alert alert-' + type;
                message.textContent = text;
            }

            async function postJson(url, payload) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                });
                const data = await response.json().catch(function () { return {}; });
                if (!response.ok || data.success === false) {
                    throw new Error(data.error || 'Payment request failed.');
                }
                return data;
            }

            button.addEventListener('click', async function () {
                const selected = Array.from(document.querySelectorAll('.bulk-purchase-item:checked')).map(function (input) {
                    const parts = input.value.split(':');
                    return {type: parts[0], id: Number(parts[1])};
                });
                if (!selected.length) {
                    showMessage('warning', 'Select at least one paid item to buy.');
                    return;
                }

                button.disabled = true;
                showMessage('info', 'Preparing secure bulk checkout...');
                let order;
                try {
                    order = await postJson(<?php echo json_encode(url_for('api/create-order.php')); ?>, {
                        amount: 0,
                        currency: <?php echo json_encode(payment_gateway_currency()); ?>,
                        receipt: 'bulk-purchase',
                        items: selected
                    });
                } catch (error) {
                    showMessage('danger', error.message);
                    button.disabled = false;
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
                    description: 'EduquestIQ bulk purchase',
                    order_id: order.order_id,
                    prefill: {
                        name: <?php echo json_encode((string)$authUser['name']); ?>,
                        email: <?php echo json_encode((string)$authUser['email']); ?>
                    },
                    handler: async function (response) {
                        try {
                            const verify = await postJson(<?php echo json_encode(url_for('api/verify-payment.php')); ?>, {
                                razorpay_order_id: response.razorpay_order_id || '',
                                razorpay_payment_id: response.razorpay_payment_id || '',
                                razorpay_signature: response.razorpay_signature || ''
                            });
                            showMessage('success', 'Payment verified. Refreshing your catalogue...');
                            window.location.href = verify.redirect_url || <?php echo json_encode(url_for('tests.php?purchase=success')); ?>;
                        } catch (error) {
                            showMessage('danger', error.message);
                            button.disabled = false;
                        }
                    },
                    theme: {color: '#4374ff'},
                    modal: {
                        ondismiss: function () {
                            showMessage('warning', 'Payment was cancelled. You can try again when ready.');
                            button.disabled = false;
                        }
                    }
                });
                rzp.on('payment.failed', function (response) {
                    const reason = response && response.error && response.error.description ? response.error.description : 'Payment failed. Please try again.';
                    showMessage('danger', reason);
                    button.disabled = false;
                });
                rzp.open();
            });
        })();
        </script>
    <?php endif; ?>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes_footer.php';
