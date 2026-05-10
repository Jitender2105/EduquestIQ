<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_csrf.php';
require_once __DIR__ . '/includes_fallback.php';
require_once __DIR__ . '/includes_payments.php';

$pdo = get_pdo();
$testHasActiveColumn = table_has_column($pdo, 'tests', 'is_active');
$testHasGradeColumn = table_has_column($pdo, 'tests', 'target_grade');
$studentGrade = '';

function tests_catalog_kolkata_label(?DateTimeImmutable $value): string
{
    if (!$value) {
        return 'Not set';
    }

    return $value->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y, h:i A');
}

function tests_catalog_countdown(DateTimeImmutable $nowUtc, ?DateTimeImmutable $deadlineUtc): ?array
{
    if (!$deadlineUtc || $deadlineUtc <= $nowUtc) {
        return null;
    }

    return [
        'iso' => $deadlineUtc->format(DateTimeInterface::ATOM),
        'label' => tests_catalog_kolkata_label($deadlineUtc),
    ];
}

$tests = [];
$practicePapers = [];
if ($authUser && $authUser['role'] === 'student') {
    $gradeStmt = $pdo->prepare('SELECT grade FROM users WHERE id = ? LIMIT 1');
    $gradeStmt->execute([(int)$authUser['sub']]);
    $studentGrade = trim((string)$gradeStmt->fetchColumn());
}

try {
    $testWhere = [];
    if ($testHasActiveColumn) {
        $testWhere[] = 't.is_active = 1';
    }
    if ($authUser && $authUser['role'] === 'student' && $testHasGradeColumn && $studentGrade !== '') {
        $testWhere[] = "(t.target_grade = " . $pdo->quote($studentGrade) . " OR t.target_grade IS NULL OR t.target_grade = '')";
    }
    $stmt = $pdo->query(
        'SELECT t.id, t.title, t.description, t.start_at, t.end_at, t.total_marks, t.duration_minutes, t.price_inr, t.created_at,
                u.name AS teacher_name
         FROM tests t
         LEFT JOIN users u ON t.created_by = u.id
         ' . ($testWhere ? 'WHERE ' . implode(' AND ', $testWhere) : '') . '
         ORDER BY t.created_at DESC'
    );
    $tests = $stmt->fetchAll();

    if (practice_paper_table_exists($pdo)) {
        $paperActiveClause = table_has_column($pdo, 'practice_papers', 'is_active')
            ? 'pp.is_active = 1'
            : 'pp.status = "published"';
        $paperTestVisibilityClause = '';
        if ($testHasActiveColumn) {
            $paperTestVisibilityClause .= ' AND t.is_active = 1';
        }
        $practicePapers = $pdo->query(
            'SELECT pp.*, t.title AS test_title
             FROM practice_papers pp
             JOIN tests t ON t.id = pp.test_id
             WHERE ' . $paperActiveClause . $paperTestVisibilityClause . '
             ORDER BY pp.created_at DESC, pp.id DESC'
        )->fetchAll();
    }
} catch (Throwable $e) {
    $tests = [];
    $practicePapers = [];
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

$paidTestsList = [];
$freeTestsList = [];
foreach ($tests as $test) {
    $testPrice = (float)($test['price_inr'] ?? 0);
    if ($testPrice > 0) {
        $paidTestsList[] = $test;
    } else {
        $freeTestsList[] = $test;
    }
}

$paidPracticePaperList = [];
$freePracticePaperList = [];
foreach ($practicePapers as $paper) {
    $paperPrice = (float)($paper['amount_inr'] ?? 0);
    $isPaidPaper = (string)($paper['access_type'] ?? 'free') === 'paid' && $paperPrice > 0;
    if ($isPaidPaper) {
        $paidPracticePaperList[] = $paper;
    } else {
        $freePracticePaperList[] = $paper;
    }
}
?>

<style>
.eq-catalog-intro {
    background: linear-gradient(135deg, rgba(67, 116, 255, 0.08), rgba(168, 85, 247, 0.12));
    border: 1px solid rgba(67, 116, 255, 0.14);
    border-radius: 28px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.eq-catalog-intro-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.eq-catalog-card {
    background: rgba(255, 255, 255, 0.78);
    border: 1px solid rgba(255, 255, 255, 0.55);
    border-radius: 22px;
    padding: 1.25rem;
    box-shadow: 0 18px 44px rgba(76, 91, 135, 0.08);
}

.eq-catalog-card h4 {
    font-size: 1.05rem;
    margin-bottom: 0.55rem;
}

.eq-catalog-card p {
    color: #5e6785;
    font-size: 0.95rem;
    margin-bottom: 0;
}

.eq-catalog-list {
    margin: 0.9rem 0 0;
    padding-left: 1rem;
    color: #3f4866;
}

.eq-catalog-list li + li {
    margin-top: 0.4rem;
}

.eq-cart-bar {
    display: none;
    position: fixed;
    left: 50%;
    bottom: 1rem;
    transform: translateX(-50%);
    width: min(1120px, calc(100vw - 1.5rem));
    z-index: 1040;
    background: linear-gradient(135deg, #0f172a, #1e293b);
    color: #fff;
    border-radius: 24px;
    padding: 1.1rem 1.2rem;
    box-shadow: 0 22px 52px rgba(15, 23, 42, 0.24);
}

.eq-cart-bar.is-visible {
    display: block;
}

.eq-cart-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem 1.5rem;
    margin: 0.8rem 0;
}

.eq-cart-stat strong {
    display: block;
    font-size: 1.2rem;
    line-height: 1.1;
}

.eq-cart-stat span {
    display: block;
    color: rgba(255, 255, 255, 0.72);
    font-size: 0.82rem;
}

.eq-selected-items {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.85rem;
}

.eq-selected-pill {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 999px;
    color: #fff;
    font-size: 0.82rem;
    padding: 0.35rem 0.75rem;
}

.eq-purchase-choice {
    display: block;
    width: 100%;
    border: 2px solid rgba(245, 158, 11, 0.35);
    border-radius: 16px;
    padding: 0.8rem 0.95rem;
    background: linear-gradient(135deg, rgba(255, 247, 237, 0.92), rgba(255, 255, 255, 0.96));
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    cursor: pointer;
}

.eq-purchase-choice:hover {
    transform: translateY(-1px);
    box-shadow: 0 16px 34px rgba(245, 158, 11, 0.14);
}

.eq-purchase-choice.is-selected {
    border-color: #f59e0b;
    box-shadow: 0 18px 38px rgba(245, 158, 11, 0.18);
}

.eq-purchase-choice input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.eq-purchase-choice-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.eq-purchase-choice-title {
    font-weight: 700;
    color: #1f2937;
}

.eq-purchase-choice-amount {
    color: #b45309;
    font-weight: 700;
}

.eq-purchase-choice-text {
    color: #6b7280;
    font-size: 0.88rem;
    margin-top: 0.35rem;
}

.eq-buy-window {
    margin-top: 0.7rem;
    padding: 0.75rem 0.9rem;
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.08), rgba(168, 85, 247, 0.08));
    border: 1px solid rgba(37, 99, 235, 0.14);
}

.eq-buy-window strong {
    display: block;
    color: #1e3a8a;
    font-size: 0.88rem;
}

.eq-buy-window span {
    display: block;
    color: #475569;
    font-size: 0.82rem;
    margin-top: 0.2rem;
}

.eq-buy-window.is-closed {
    background: rgba(148, 163, 184, 0.1);
    border-color: rgba(148, 163, 184, 0.28);
}

.eq-buy-window.is-closed strong {
    color: #475569;
}

.eq-section-card {
    background: #fff;
    border-radius: 24px;
    padding: 1.25rem;
    box-shadow: 0 20px 48px rgba(15, 23, 42, 0.06);
}

.eq-empty-state {
    border: 1px dashed rgba(148, 163, 184, 0.8);
    border-radius: 18px;
    padding: 1.2rem;
    color: #64748b;
    background: #f8fafc;
}

.eq-collapsible-card {
    background: #fff;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 22px;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.06);
    overflow: hidden;
}

.eq-collapsible-summary {
    list-style: none;
    cursor: pointer;
    padding: 1.1rem 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    font-weight: 700;
    color: #0f172a;
    background: linear-gradient(135deg, rgba(67, 116, 255, 0.06), rgba(168, 85, 247, 0.08));
}

.eq-collapsible-summary::-webkit-details-marker {
    display: none;
}

.eq-collapsible-copy {
    color: #64748b;
    font-size: 0.92rem;
    font-weight: 500;
}

.eq-collapsible-icon {
    font-size: 1rem;
    transition: transform 0.2s ease;
}

details[open] .eq-collapsible-icon {
    transform: rotate(180deg);
}

.eq-collapsible-body {
    padding: 1.25rem;
}

@media (max-width: 767.98px) {
    .eq-cart-bar {
        width: calc(100vw - 1rem);
        bottom: 0.5rem;
        border-radius: 20px;
        padding: 1rem;
    }
}
</style>

<div class="eq-page-head">
    <h2>Top Eduquest Ace Exam that will help you build the right future for your Child</h2>
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
        <div class="eq-catalog-intro">
            <div class="eq-catalog-intro-grid">
                <div class="eq-catalog-card">
                    <h4>How tests help</h4>
                    <p>Live tests build exam discipline and measure your attribute-level readiness before the real attempt window opens.</p>
                    <ul class="eq-catalog-list">
                        <li>See skill growth through SIRA-based reporting.</li>
                        <li>Practice with timed conditions and clear instructions.</li>
                        <li>Track purchase, attempt, and report access in one place.</li>
                    </ul>
                </div>
                <div class="eq-catalog-card">
                    <h4>How practice papers help</h4>
                    <p>Practice papers give you focused revision material you can download, revisit, and use for self-paced prep.</p>
                    <ul class="eq-catalog-list">
                        <li>Reinforce concepts before taking the full test.</li>
                        <li>Download PDFs after purchase or instantly when free.</li>
                        <li>Prepare for class-specific and year-specific exam patterns.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="eq-cart-bar" id="test-cart-bar">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="mb-1 text-white">Bulk Purchase Cart</h5>
                    <div class="small text-white-50">Select multiple paid tests and practice papers, then pay once.</div>
                </div>
                <button type="button" class="btn btn-warning fw-semibold" id="bulk-buy-button">Buy Selected Items</button>
            </div>
            <div class="eq-cart-meta">
                <div class="eq-cart-stat">
                    <strong id="selected-count">0</strong>
                    <span>Selected items</span>
                </div>
                <div class="eq-cart-stat">
                    <strong id="selected-total">Rs 0</strong>
                    <span>Cart value</span>
                </div>
            </div>
            <div class="eq-selected-items" id="selected-items">
                <span class="eq-selected-pill">No paid items selected yet</span>
            </div>
        </div>
    <?php endif; ?>

    <div class="eq-page-head text-start">
        <h3>Paid Tests</h3>
        <p class="subtitle">Buy upcoming tests in advance. Once purchased, you can start them only during the live test window.</p>
    </div>
    <div class="row g-3">
        <?php foreach ($paidTestsList as $test): ?>
            <?php
                $startAt = !empty($test['start_at']) ? new DateTimeImmutable((string)$test['start_at'], new DateTimeZone('UTC')) : null;
                $endAt = !empty($test['end_at']) ? new DateTimeImmutable((string)$test['end_at'], new DateTimeZone('UTC')) : null;
                $statusLabel = 'Available';
                $statusClass = 'text-bg-success';
                $canAttempt = true;
                $canBuy = $startAt === null || $nowUtc < $startAt;
                $buyCountdown = tests_catalog_countdown($nowUtc, $startAt);
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
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between gap-2 align-items-start mb-2">
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($test['title']); ?></h5>
                            <div class="d-flex flex-column align-items-end gap-2">
                                <span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                                <span class="badge text-bg-warning text-dark"><?php echo htmlspecialchars(test_price_label($testPrice)); ?></span>
                            </div>
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
                            Start: <?php echo htmlspecialchars(tests_catalog_kolkata_label($startAt)); ?><br>
                            End: <?php echo htmlspecialchars(tests_catalog_kolkata_label($endAt)); ?>
                        </p>
                        <div class="eq-buy-window<?php echo $canBuy ? '' : ' is-closed'; ?>">
                            <?php if ($buyCountdown): ?>
                                <strong>Buy before test starts</strong>
                                <span class="js-buy-countdown" data-deadline="<?php echo htmlspecialchars($buyCountdown['iso']); ?>">Time left to buy: calculating...</span>
                                <span>Purchase closes on <?php echo htmlspecialchars($buyCountdown['label']); ?>.</span>
                            <?php elseif ($canBuy): ?>
                                <strong>Purchase window is open</strong>
                                <span>This test can be bought until the start time is set.</span>
                            <?php else: ?>
                                <strong>Purchase window closed</strong>
                                <span>Buying closed at the test start time.</span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <?php if ($authUser && $authUser['role'] === 'student'): ?>
                                <?php if (isset($attempted[(int)$test['id']])): ?>
                                    <a href="<?php echo htmlspecialchars(url_for('sira_report.php?attempt_id=' . (int)$attemptIds[(int)$test['id']])); ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        View SIRA Report
                                    </a>
                                <?php else: ?>
                                    <?php if (!$hasPaidTest && $canBuy): ?>
                                        <label class="eq-purchase-choice" for="buy-test-<?php echo (int)$test['id']; ?>">
                                            <input class="bulk-purchase-item" type="checkbox" value="test:<?php echo (int)$test['id']; ?>" id="buy-test-<?php echo (int)$test['id']; ?>" data-title="<?php echo htmlspecialchars($test['title']); ?>" data-amount="<?php echo (int)amount_in_paise($testPrice); ?>">
                                            <span class="eq-purchase-choice-head">
                                                <span class="eq-purchase-choice-title">Add test to cart</span>
                                                <span class="eq-purchase-choice-amount"><?php echo htmlspecialchars(test_price_label($testPrice)); ?></span>
                                            </span>
                                            <span class="eq-purchase-choice-text">Select this paid test to include it in your current checkout.</span>
                                        </label>
                                    <?php elseif (!$hasPaidTest): ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled>Buying closed at test start</button>
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
                                <?php if ($canBuy): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(url_for('login.php')); ?>">Login to Buy</a>
                                <?php else: ?>
                                    <span class="text-muted small">Buying closed at test start.</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$paidTestsList): ?>
            <div class="col-12"><div class="eq-empty-state">No paid tests are available right now.</div></div>
        <?php endif; ?>
    </div>

    <div class="eq-page-head text-start mt-5">
        <h3>Paid Practice Papers</h3>
        <p class="subtitle">Purchase downloadable preparation PDFs in advance and keep them accessible from this catalogue.</p>
    </div>
    <div class="row g-3">
        <?php foreach ($paidPracticePaperList as $paper): ?>
            <?php
                $paperPrice = (float)($paper['amount_inr'] ?? 0);
                $hasPaperAccess = !empty($paidPracticePapers[(int)$paper['id']]);
            ?>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between gap-2 align-items-start mb-2">
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($paper['name']); ?></h5>
                            <span class="badge text-bg-warning text-dark"><?php echo htmlspecialchars(test_price_label($paperPrice)); ?></span>
                        </div>
                        <p class="small text-muted mb-2">Mapped Test: <?php echo htmlspecialchars($paper['test_title']); ?></p>
                        <p class="card-text small text-muted flex-grow-1"><?php echo htmlspecialchars(text_preview(strip_tags((string)$paper['description']), 140, '...')); ?></p>
                        <p class="small mb-3">Class: <?php echo htmlspecialchars($paper['class_name']); ?> | Year: <?php echo htmlspecialchars($paper['paper_year']); ?></p>
                        <?php if ($authUser && $authUser['role'] === 'student'): ?>
                            <?php if ($hasPaperAccess): ?>
                                <a class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars(url_for('practice_paper_download.php?id=' . (int)$paper['id'])); ?>">Download PDF</a>
                            <?php else: ?>
                                <label class="eq-purchase-choice" for="buy-paper-<?php echo (int)$paper['id']; ?>">
                                    <input class="bulk-purchase-item" type="checkbox" value="practice_paper:<?php echo (int)$paper['id']; ?>" id="buy-paper-<?php echo (int)$paper['id']; ?>" data-title="<?php echo htmlspecialchars($paper['name']); ?>" data-amount="<?php echo (int)amount_in_paise($paperPrice); ?>">
                                    <span class="eq-purchase-choice-head">
                                        <span class="eq-purchase-choice-title">Add paper to cart</span>
                                        <span class="eq-purchase-choice-amount"><?php echo htmlspecialchars(test_price_label($paperPrice)); ?></span>
                                    </span>
                                    <span class="eq-purchase-choice-text">Select this paid practice paper to include it in your checkout.</span>
                                </label>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted small">Login as a student to download.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$paidPracticePaperList): ?>
            <div class="col-12"><div class="eq-empty-state">No paid practice papers are available right now.</div></div>
        <?php endif; ?>
    </div>

    <div class="mt-5">
        <details class="eq-collapsible-card">
            <summary class="eq-collapsible-summary">
                <span>
                    Free Tests
                    <span class="d-block eq-collapsible-copy">Collapsed by default. Expand to see tests that can be started for free.</span>
                </span>
                <span class="eq-collapsible-icon">&#9662;</span>
            </summary>
            <div class="eq-collapsible-body">
                <div class="row g-3">
                    <?php foreach ($freeTestsList as $test): ?>
                        <?php
                            $startAt = !empty($test['start_at']) ? new DateTimeImmutable((string)$test['start_at'], new DateTimeZone('UTC')) : null;
                            $endAt = !empty($test['end_at']) ? new DateTimeImmutable((string)$test['end_at'], new DateTimeZone('UTC')) : null;
                            $statusLabel = 'Available';
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
                        ?>
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between gap-2 align-items-start mb-2">
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($test['title']); ?></h5>
                                        <div class="d-flex flex-column align-items-end gap-2">
                                            <span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                                            <span class="badge text-bg-success">Free</span>
                                        </div>
                                    </div>
                                    <p class="card-text small text-muted flex-grow-1"><?php echo htmlspecialchars(text_preview(strip_tags((string)$test['description']), 140, '...')); ?></p>
                                    <p class="small mb-2">
                                        <?php if ($test['teacher_name']): ?>
                                            Teacher: <?php echo htmlspecialchars($test['teacher_name']); ?><br>
                                        <?php endif; ?>
                                        Marks: <?php echo (int)$test['total_marks']; ?> |
                                        Duration: <?php echo (int)$test['duration_minutes']; ?> min<br>
                                        Start: <?php echo htmlspecialchars(tests_catalog_kolkata_label($startAt)); ?><br>
                                        End: <?php echo htmlspecialchars(tests_catalog_kolkata_label($endAt)); ?>
                                    </p>
                                    <?php if ($authUser && $authUser['role'] === 'student'): ?>
                                        <?php if (isset($attempted[(int)$test['id']])): ?>
                                            <a href="<?php echo htmlspecialchars(url_for('sira_report.php?attempt_id=' . (int)$attemptIds[(int)$test['id']])); ?>"
                                               class="btn btn-sm btn-outline-primary">
                                                View SIRA Report
                                            </a>
                                        <?php elseif ($canAttempt): ?>
                                            <a class="btn btn-sm btn-success" href="<?php echo htmlspecialchars(url_for('test_attempt.php?id=' . (int)$test['id'])); ?>">Start Free Test</a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled><?php echo $statusLabel === 'Upcoming' ? 'Free test opens later' : 'Not available'; ?></button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">Login as a student to attempt.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$freeTestsList): ?>
                        <div class="col-12"><div class="eq-empty-state">No free tests are published yet.</div></div>
                    <?php endif; ?>
                </div>
            </div>
        </details>
    </div>

    <div class="mt-4">
        <details class="eq-collapsible-card">
            <summary class="eq-collapsible-summary">
                <span>
                    Free Practice Papers
                    <span class="d-block eq-collapsible-copy">Collapsed by default. Expand to see free downloadable revision papers.</span>
                </span>
                <span class="eq-collapsible-icon">&#9662;</span>
            </summary>
            <div class="eq-collapsible-body">
                <div class="row g-3">
                    <?php foreach ($freePracticePaperList as $paper): ?>
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between gap-2 align-items-start mb-2">
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($paper['name']); ?></h5>
                                        <span class="badge text-bg-success">Free</span>
                                    </div>
                                    <p class="small text-muted mb-2">Mapped Test: <?php echo htmlspecialchars($paper['test_title']); ?></p>
                                    <p class="card-text small text-muted flex-grow-1"><?php echo htmlspecialchars(text_preview(strip_tags((string)$paper['description']), 140, '...')); ?></p>
                                    <p class="small mb-3">Class: <?php echo htmlspecialchars($paper['class_name']); ?> | Year: <?php echo htmlspecialchars($paper['paper_year']); ?></p>
                                    <?php if ($authUser && $authUser['role'] === 'student'): ?>
                                        <a class="btn btn-sm btn-success" href="<?php echo htmlspecialchars(url_for('practice_paper_download.php?id=' . (int)$paper['id'])); ?>">Download Free Paper</a>
                                    <?php else: ?>
                                        <span class="text-muted small">Login as a student to download.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$freePracticePaperList): ?>
                        <div class="col-12"><div class="eq-empty-state">No free practice papers are published yet.</div></div>
                    <?php endif; ?>
                </div>
            </div>
        </details>
    </div>

    <?php if ($authUser && $authUser['role'] === 'student'): ?>
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <?php endif; ?>
    <script>
    (function () {
        const nodes = Array.from(document.querySelectorAll('.js-buy-countdown'));
        if (!nodes.length) {
            return;
        }

        function renderCountdown() {
            const now = Date.now();
            nodes.forEach(function (node) {
                const deadlineValue = node.getAttribute('data-deadline');
                const deadlineMs = deadlineValue ? Date.parse(deadlineValue) : NaN;
                if (!deadlineMs || Number.isNaN(deadlineMs)) {
                    node.textContent = 'Time left to buy: unavailable';
                    return;
                }

                let delta = Math.max(0, deadlineMs - now);
                if (delta <= 0) {
                    node.textContent = 'Time left to buy: closed';
                    return;
                }

                const totalSeconds = Math.floor(delta / 1000);
                const days = Math.floor(totalSeconds / 86400);
                const hours = Math.floor((totalSeconds % 86400) / 3600);
                const minutes = Math.floor((totalSeconds % 3600) / 60);
                const seconds = totalSeconds % 60;
                const parts = [];
                if (days > 0) {
                    parts.push(days + 'd');
                }
                parts.push(String(hours).padStart(2, '0') + 'h');
                parts.push(String(minutes).padStart(2, '0') + 'm');
                parts.push(String(seconds).padStart(2, '0') + 's');
                node.textContent = 'Time left to buy: ' + parts.join(' ');
            });
        }

        renderCountdown();
        window.setInterval(renderCountdown, 1000);
    })();
    </script>
    <?php if ($authUser && $authUser['role'] === 'student'): ?>
        <script>
        (function () {
            const cartBar = document.getElementById('test-cart-bar');
            const button = document.getElementById('bulk-buy-button');
            const csrfToken = document.getElementById('payment-csrf-token') ? document.getElementById('payment-csrf-token').value : '';
            const message = document.getElementById('payment-message');
            const countNode = document.getElementById('selected-count');
            const totalNode = document.getElementById('selected-total');
            const itemsNode = document.getElementById('selected-items');
            const storageKey = 'eduquestiq_bulk_purchase_cart_v1';
            if (!button || !message) return;

            function showMessage(type, text) {
                message.className = 'alert alert-' + type;
                message.textContent = text;
            }

            function formatInr(amountPaise) {
                return 'Rs ' + (amountPaise / 100).toLocaleString('en-IN', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 2
                });
            }

            function syncSelectedStyles() {
                document.querySelectorAll('.eq-purchase-choice').forEach(function (choice) {
                    const input = choice.querySelector('.bulk-purchase-item');
                    choice.classList.toggle('is-selected', !!(input && input.checked));
                });
            }

            function updateCartSummary() {
                if (!countNode || !totalNode || !itemsNode || !cartBar) return;
                const selectedInputs = Array.from(document.querySelectorAll('.bulk-purchase-item:checked'));
                const totalPaise = selectedInputs.reduce(function (sum, input) {
                    return sum + Number(input.dataset.amount || 0);
                }, 0);

                countNode.textContent = String(selectedInputs.length);
                totalNode.textContent = formatInr(totalPaise);
                itemsNode.innerHTML = '';
                if (!selectedInputs.length) {
                    cartBar.classList.remove('is-visible');
                    const pill = document.createElement('span');
                    pill.className = 'eq-selected-pill';
                    pill.textContent = 'No paid items selected yet';
                    itemsNode.appendChild(pill);
                    return;
                }

                cartBar.classList.add('is-visible');
                selectedInputs.forEach(function (input) {
                    const pill = document.createElement('span');
                    pill.className = 'eq-selected-pill';
                    pill.textContent = (input.dataset.title || 'Selected item') + ' · ' + formatInr(Number(input.dataset.amount || 0));
                    itemsNode.appendChild(pill);
                });
            }

            function saveSelection() {
                const selectedValues = Array.from(document.querySelectorAll('.bulk-purchase-item:checked')).map(function (input) {
                    return input.value;
                });
                try {
                    window.localStorage.setItem(storageKey, JSON.stringify(selectedValues));
                } catch (error) {
                }
            }

            function restoreSelection() {
                let selectedValues = [];
                try {
                    selectedValues = JSON.parse(window.localStorage.getItem(storageKey) || '[]');
                } catch (error) {
                    selectedValues = [];
                }
                if (!Array.isArray(selectedValues)) {
                    selectedValues = [];
                }

                document.querySelectorAll('.bulk-purchase-item').forEach(function (input) {
                    input.checked = selectedValues.indexOf(input.value) !== -1;
                });
            }

            function clearSelection() {
                try {
                    window.localStorage.removeItem(storageKey);
                } catch (error) {
                }
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

            document.querySelectorAll('.bulk-purchase-item').forEach(function (input) {
                input.addEventListener('change', function () {
                    syncSelectedStyles();
                    updateCartSummary();
                    saveSelection();
                });
            });

            if (window.location.search.indexOf('purchase=success') !== -1) {
                clearSelection();
            }

            restoreSelection();
            syncSelectedStyles();
            updateCartSummary();

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
                            clearSelection();
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
