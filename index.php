<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_csrf.php';
require_once __DIR__ . '/includes_payments.php';

$pdo = get_pdo();
$leadErrors = [];
$leadSuccess = null;
$leadForm = [
    'student_name' => '',
    'class_name' => '',
    'school_name' => '',
    'parent_email' => '',
    'parent_mobile' => '',
    'exam' => [],
];
$examOptions = [
    'ASI' => 'Ace STEM Intelligence (ASI)',
    'ALP' => 'Ace Language Proficiency (ALP)',
    'ALA' => 'Ace Logical Ability (ALA)',
    'ALSGA' => 'Ace Life Skills and General Awareness (ALSGA)',
    'AEDI' => 'Ace Emotional & Digital Intelligence (AEDI)',
];

function home_tests_kolkata_label(?DateTimeImmutable $value): string
{
    if (!$value) {
        return 'Not set';
    }

    return $value->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y, h:i A');
}

function home_tests_countdown(DateTimeImmutable $nowUtc, ?DateTimeImmutable $deadlineUtc): ?array
{
    if (!$deadlineUtc || $deadlineUtc <= $nowUtc) {
        return null;
    }

    return [
        'iso' => $deadlineUtc->format(DateTimeInterface::ATOM),
        'label' => home_tests_kolkata_label($deadlineUtc),
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'submit_sira_lead') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $leadErrors[] = 'Invalid form token. Please refresh and try again.';
    }

    $leadForm['student_name'] = trim((string)($_POST['student_name'] ?? ''));
    $leadForm['class_name'] = trim((string)($_POST['class_name'] ?? ''));
    $leadForm['school_name'] = trim((string)($_POST['school_name'] ?? ''));
    $leadForm['parent_email'] = trim((string)($_POST['parent_email'] ?? ''));
    $leadForm['parent_mobile'] = trim((string)($_POST['parent_mobile'] ?? ''));
    $rawExams = $_POST['exam'] ?? [];
    $leadForm['exam'] = is_array($rawExams) ? array_values($rawExams) : [];

    if ($leadForm['student_name'] === '') {
        $leadErrors[] = 'Student name is required.';
    }
    if ($leadForm['class_name'] === '') {
        $leadErrors[] = 'Class is required.';
    }
    if ($leadForm['school_name'] === '') {
        $leadErrors[] = 'School name is required.';
    }
    if (!filter_var($leadForm['parent_email'], FILTER_VALIDATE_EMAIL)) {
        $leadErrors[] = 'Enter a valid parent email address.';
    }
    if (!preg_match('/^\+?[0-9 ]{10,15}$/', $leadForm['parent_mobile'])) {
        $leadErrors[] = 'Enter a valid parent mobile number.';
    }

    $selectedExamCodes = array_values(array_intersect(array_keys($examOptions), $leadForm['exam']));
    if ($selectedExamCodes === []) {
        $leadErrors[] = 'Select at least one exam.';
    }

    if ($leadErrors === []) {
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS sira_leads (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    lead_uid VARCHAR(32) NOT NULL UNIQUE,
                    student_name VARCHAR(120) NOT NULL,
                    class_name VARCHAR(40) NOT NULL,
                    school_name VARCHAR(180) NOT NULL,
                    parent_email VARCHAR(160) NOT NULL,
                    parent_mobile VARCHAR(20) NOT NULL,
                    selected_exams JSON NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            $leadUid = 'SIRA' . gmdate('YmdHis') . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $pdo->prepare(
                'INSERT INTO sira_leads
                 (lead_uid, student_name, class_name, school_name, parent_email, parent_mobile, selected_exams, created_at)
                 VALUES (:lead_uid, :student_name, :class_name, :school_name, :parent_email, :parent_mobile, :selected_exams, UTC_TIMESTAMP())'
            );
            $stmt->execute([
                ':lead_uid' => $leadUid,
                ':student_name' => $leadForm['student_name'],
                ':class_name' => $leadForm['class_name'],
                ':school_name' => $leadForm['school_name'],
                ':parent_email' => $leadForm['parent_email'],
                ':parent_mobile' => $leadForm['parent_mobile'],
                ':selected_exams' => json_encode($selectedExamCodes, JSON_THROW_ON_ERROR),
            ]);

            $leadSuccess = 'Thanks! Your assessment request has been received. Lead ID: ' . $leadUid;
            $leadForm = [
                'student_name' => '',
                'class_name' => '',
                'school_name' => '',
                'parent_email' => '',
                'parent_mobile' => '',
                'exam' => [],
            ];
        } catch (Throwable $e) {
            $leadErrors[] = 'Unable to submit right now. Please try again in a moment.';
        }
    }
}

$stats = [
    'students' => 50000,
    'courses' => 500,
    'success_rate' => 95,
    'lessons' => 120000,
    'countries' => 50,
    'badges' => 100,
];

try {
    $students = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    $courses = (int)$pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
    $stats['students'] = max($stats['students'], $students);
    $stats['courses'] = max($stats['courses'], $courses);
} catch (Throwable $e) {
    // Keep curated defaults if DB is empty/unavailable for optional homepage metrics.
}

$testimonials = [
    [
        'name' => 'Sarah Johnson',
        'grade' => 'Grade 12 Student',
        'city' => 'New York, USA',
        'text' => 'The balanced approach to academics and creativity helped me maintain excellent grades while exploring design.',
        'tags' => ['Academic Excellence', 'Creative Design', 'Leadership'],
    ],
    [
        'name' => 'Arjun Patel',
        'grade' => 'Grade 10 Student',
        'city' => 'Mumbai, India',
        'text' => 'Project-based programming and robotics kept me motivated. The progress tracking helped me stay consistent.',
        'tags' => ['Technical Skills', 'Programming', 'Problem Solving'],
    ],
    [
        'name' => 'Emma Chen',
        'grade' => 'Grade 8 Student',
        'city' => 'Toronto, Canada',
        'text' => 'Courses are engaging and community learning helped me collaborate with students from different regions.',
        'tags' => ['Creative Writing', 'Communication', 'Teamwork'],
    ],
    [
        'name' => 'Michael Rodriguez',
        'grade' => 'Grade 11 Student',
        'city' => 'Madrid, Spain',
        'text' => 'I now feel confident leading projects and communicating clearly with peers in collaborative assignments.',
        'tags' => ['Leadership', 'Communication', 'Academic Excellence'],
    ],
    [
        'name' => 'Priya Sharma',
        'grade' => 'Grade 9 Student',
        'city' => 'Delhi, India',
        'text' => 'Personalized learning paths adapt to my pace and interests, especially for creative and math-focused learning.',
        'tags' => ['Creative Arts', 'Mathematics', 'Innovation'],
    ],
    [
        'name' => 'David Kim',
        'grade' => 'Grade 7 Student',
        'city' => 'Seoul, South Korea',
        'text' => 'Quick reels and micro lessons give me short boosts of motivation and practical digital skills every day.',
        'tags' => ['Technical Skills', 'Digital Literacy', 'Leadership'],
    ],
];

$featuredTests = [];
$featuredPracticePapers = [];
$featuredPaidTestsPurchased = [];
$featuredPaidPapersPurchased = [];
$homeNowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$homeStudentGrade = '';
$testsHaveActiveColumn = table_has_column($pdo, 'tests', 'is_active');
$testsHaveGradeColumn = table_has_column($pdo, 'tests', 'target_grade');
$papersHaveActiveColumn = practice_paper_table_exists($pdo) && table_has_column($pdo, 'practice_papers', 'is_active');

if ($authUser && ($authUser['role'] ?? '') === 'student') {
    $gradeStmt = $pdo->prepare('SELECT grade FROM users WHERE id = ? LIMIT 1');
    $gradeStmt->execute([(int)$authUser['sub']]);
    $homeStudentGrade = trim((string)$gradeStmt->fetchColumn());

    $purchaseStmt = $pdo->prepare(
        'SELECT test_id
         FROM test_purchases
         WHERE student_id = ? AND payment_status = "paid"'
    );
    $purchaseStmt->execute([(int)$authUser['sub']]);
    foreach ($purchaseStmt->fetchAll() as $row) {
        $featuredPaidTestsPurchased[(int)$row['test_id']] = true;
    }

    if (practice_paper_purchase_table_exists($pdo)) {
        $paperPurchaseStmt = $pdo->prepare(
            'SELECT practice_paper_id
             FROM practice_paper_purchases
             WHERE student_id = ? AND payment_status = "paid"'
        );
        $paperPurchaseStmt->execute([(int)$authUser['sub']]);
        foreach ($paperPurchaseStmt->fetchAll() as $row) {
            $featuredPaidPapersPurchased[(int)$row['practice_paper_id']] = true;
        }
    }
}

try {
    $testWhere = ['COALESCE(t.price_inr, 0) > 0'];
    if ($testsHaveActiveColumn) {
        $testWhere[] = 't.is_active = 1';
    }
    if ($authUser && ($authUser['role'] ?? '') === 'student' && $testsHaveGradeColumn && $homeStudentGrade !== '') {
        $testWhere[] = "(t.target_grade = " . $pdo->quote($homeStudentGrade) . " OR t.target_grade IS NULL OR t.target_grade = '')";
    }
    $featuredTests = $pdo->query(
        'SELECT t.id, t.title, t.description, t.start_at, t.end_at, t.total_marks, t.duration_minutes, t.price_inr, t.created_at,
                u.name AS teacher_name
         FROM tests t
         LEFT JOIN users u ON u.id = t.created_by
         WHERE ' . implode(' AND ', $testWhere) . '
         ORDER BY t.created_at DESC
         LIMIT 8'
    )->fetchAll();

    if (practice_paper_table_exists($pdo)) {
        $paperWhere = ["pp.access_type = 'paid'", 'COALESCE(pp.amount_inr, 0) > 0'];
        $paperWhere[] = $papersHaveActiveColumn ? 'pp.is_active = 1' : 'pp.status = "published"';
        if ($testsHaveActiveColumn) {
            $paperWhere[] = 't.is_active = 1';
        }
        $featuredPracticePapers = $pdo->query(
            'SELECT pp.*, t.title AS test_title
             FROM practice_papers pp
             JOIN tests t ON t.id = pp.test_id
             WHERE ' . implode(' AND ', $paperWhere) . '
             ORDER BY pp.created_at DESC, pp.id DESC
             LIMIT 8'
        )->fetchAll();
    }
} catch (Throwable $e) {
    $featuredTests = [];
    $featuredPracticePapers = [];
}

$hasFeaturedTests = $featuredTests !== [];
$hasFeaturedPracticePapers = $featuredPracticePapers !== [];
$hasFeaturedAssessments = $hasFeaturedTests || $hasFeaturedPracticePapers;
?>

<style>
    .eq-home-lead-card {
        position: relative;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.28);
        border-radius: 24px;
        padding: 22px;
        box-shadow: 0 22px 48px rgba(29, 14, 102, 0.22);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
    }
    .eq-home-lead-card h3 {
        color: #fff;
        font-size: 1.25rem;
        margin-bottom: 6px;
        letter-spacing: -0.02em;
    }
    .eq-home-lead-card p {
        color: rgba(255, 255, 255, 0.82);
        font-size: 0.88rem;
        margin-bottom: 16px;
        line-height: 1.5;
    }
    .eq-home-lead-card .form-label {
        color: rgba(255, 255, 255, 0.92);
        font-size: 0.77rem;
        margin-bottom: 5px;
        font-weight: 700;
        letter-spacing: 0.02em;
    }
    .eq-home-lead-card .form-control,
    .eq-home-lead-card .form-select {
        background: rgba(255, 255, 255, 0.97);
        border-color: rgba(255, 255, 255, 0.88);
        border-radius: 12px;
        box-shadow: none;
        min-height: 42px;
        font-size: 0.92rem;
    }
    .eq-home-lead-card .form-control:focus,
    .eq-home-lead-card .form-select:focus {
        border-color: rgba(99, 102, 241, 0.7);
        box-shadow: 0 0 0 0.18rem rgba(99, 102, 241, 0.14);
    }
    .eq-home-lead-card .select2-container {
        width: 100% !important;
    }
    .eq-home-lead-card .select2-container--default .select2-selection--multiple {
        min-height: 42px;
        border: 0;
        border-radius: 12px;
        padding: 4px 6px;
        background: rgba(255, 255, 255, 0.97);
    }
    .eq-home-lead-card .select2-container--default.select2-container--focus .select2-selection--multiple {
        box-shadow: 0 0 0 0.18rem rgba(99, 102, 241, 0.14);
    }
    .eq-home-lead-card .select2-container--default .select2-selection--multiple .select2-selection__choice {
        border: 0;
        border-radius: 999px;
        background: #e8efff;
        color: #24306b;
        font-weight: 700;
        padding: 3px 8px;
    }
    .eq-home-lead-card .btn {
        width: 100%;
        border-radius: 12px;
        min-height: 44px;
        font-weight: 700;
        letter-spacing: 0.01em;
    }
    .eq-home-lead-card .btn-light {
        background: linear-gradient(135deg, #ffffff, #f4f7ff);
        border: 0;
        color: #24306b;
        box-shadow: 0 10px 22px rgba(255, 255, 255, 0.18);
    }
    .eq-sira-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        align-items: stretch;
    }
    .eq-sira-copy {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .eq-sira-copy article {
        border: 1px solid rgba(79, 96, 168, 0.12);
        border-radius: 14px;
        background: #fff;
        padding: 14px;
    }
    .eq-sira-visual-wrap {
        border-radius: 16px;
        background: linear-gradient(180deg, #ffffff 0%, #f7f9ff 100%);
        border: 1px solid rgba(79, 96, 168, 0.12);
        padding: 16px;
        overflow: hidden;
        box-shadow: 0 12px 26px rgba(30, 45, 102, 0.08);
        height: 100%;
    }
    .eq-sira-visual-wrap img {
        width: 100%;
        height: auto;
        object-fit: contain;
        display: block;
        border-radius: 12px;
    }
    @media (max-width: 991px) {
        .eq-sira-grid {
            grid-template-columns: 1fr;
        }
        .eq-sira-copy {
            grid-template-columns: 1fr;
        }
    }
    @media (min-width: 992px) {
        .eq-home-lead-card form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .eq-home-lead-card .mb-3,
        .eq-home-lead-card .eq-full-span {
            grid-column: 1 / -1;
        }
        .eq-home-lead-card .mb-2,
        .eq-home-lead-card .mb-3 {
            margin-bottom: 0 !important;
        }
        .eq-home-lead-card .btn {
            grid-column: 1 / -1;
            margin-top: 2px;
        }
    }
    .eq-featured-home-section {
        max-width: 1120px;
        margin: 0 auto;
        padding: 54px 14px 24px;
    }
    .eq-featured-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
        align-items: start;
    }
    .eq-featured-grid.eq-single {
        grid-template-columns: minmax(0, 1fr);
        max-width: 760px;
    }
    .eq-featured-panel {
        background: #fff;
        border: 1px solid rgba(67, 84, 149, 0.12);
        border-radius: 22px;
        box-shadow: 0 16px 32px rgba(23, 35, 84, 0.08);
        overflow: hidden;
    }
    .eq-featured-head {
        padding: 18px 18px 0;
    }
    .eq-featured-head h3 {
        margin: 0 0 6px;
        font-size: 1.18rem;
    }
    .eq-featured-head p {
        margin: 0;
        color: #6b738f;
        font-size: 0.88rem;
    }
    .eq-featured-list {
        max-height: 620px;
        overflow-y: auto;
        padding: 14px 18px 18px;
        display: grid;
        gap: 12px;
    }
    .eq-featured-card {
        border: 1px solid rgba(67, 84, 149, 0.1);
        border-radius: 16px;
        padding: 14px;
        background: linear-gradient(180deg, #fff, #f9fbff);
    }
    .eq-featured-card h4 {
        font-size: 1rem;
        margin: 0 0 6px;
    }
    .eq-featured-card p {
        color: #6f7794;
        font-size: 0.84rem;
        margin-bottom: 8px;
    }
    .eq-featured-meta {
        color: #5c6482;
        font-size: 0.78rem;
        margin-bottom: 10px;
    }
    .eq-featured-meta strong {
        color: #273264;
    }
    .eq-featured-card .badge {
        border-radius: 999px;
    }
    .eq-featured-buy-window {
        margin: 0.65rem 0 0.85rem;
        padding: 0.7rem 0.85rem;
        border-radius: 16px;
        background: linear-gradient(135deg, rgba(67, 116, 255, 0.08), rgba(168, 85, 247, 0.08));
        border: 1px solid rgba(67, 116, 255, 0.14);
    }
    .eq-featured-buy-window strong {
        display: block;
        color: #2b3c91;
        font-size: 0.85rem;
    }
    .eq-featured-buy-window span {
        display: block;
        color: #5e6785;
        font-size: 0.8rem;
        margin-top: 0.16rem;
    }
    .eq-featured-buy-window .js-buy-countdown {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin-top: 0.45rem;
        padding: 0.42rem 0.72rem;
        border-radius: 999px;
        background: linear-gradient(135deg, #1d4ed8, #7c3aed);
        color: #fff;
        font-size: 0.84rem;
        font-weight: 700;
        letter-spacing: 0.01em;
        box-shadow: 0 10px 24px rgba(59, 130, 246, 0.22);
    }
    .eq-featured-buy-window.is-closed {
        background: rgba(148, 163, 184, 0.08);
        border-color: rgba(148, 163, 184, 0.24);
    }
    .eq-featured-buy-window.is-closed strong {
        color: #475569;
    }
    .eq-featured-cart {
        display: none;
        position: fixed;
        left: 50%;
        bottom: 1rem;
        transform: translateX(-50%);
        width: min(1120px, calc(100vw - 1.5rem));
        z-index: 1040;
        background: linear-gradient(135deg, #101937, #1b2552);
        color: #fff;
        border-radius: 24px;
        padding: 18px;
        box-shadow: 0 18px 36px rgba(16, 25, 55, 0.24);
    }
    .eq-featured-cart.is-visible {
        display: block;
    }
    .eq-featured-cart h4 {
        margin: 0 0 4px;
        color: #fff;
    }
    .eq-featured-cart p {
        margin: 0;
        color: rgba(255, 255, 255, 0.72);
        font-size: 0.85rem;
    }
    .eq-featured-cart-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 12px 20px;
        margin-top: 14px;
    }
    .eq-featured-cart-stats strong {
        display: block;
        font-size: 1.15rem;
        color: #fff;
    }
    .eq-featured-cart-stats span {
        display: block;
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.76rem;
    }
    .eq-featured-cart-items {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 14px;
    }
    .eq-featured-cart-pill {
        border-radius: 999px;
        padding: 6px 10px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.14);
        color: #fff;
        font-size: 0.79rem;
    }
    .eq-featured-cart-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 16px;
    }
    .eq-featured-cart-actions .btn {
        min-width: 150px;
    }
    .eq-featured-add-btn.is-added {
        background: #142458;
        border-color: #142458;
        color: #fff;
    }
    @media (max-width: 991px) {
        .eq-featured-grid {
            grid-template-columns: 1fr;
        }
        .eq-featured-list {
            max-height: 520px;
        }
        .eq-featured-cart {
            width: calc(100vw - 1rem);
            bottom: 0.5rem;
            border-radius: 20px;
            padding: 1rem;
        }
    }
</style>

<section class="eq-home-hero">
    <div class="eq-home-hero-grid">
        <div>
            <div class="eq-chip">Trusted by 10,000+ students worldwide</div>
            <h1>
                STEM Test, Olympiad and
                <span class="accent">Competitive Exam Prep</span>
            </h1>
            <p>
                Join EduquestIQ for grade-wise STEM tests, Olympiad practice, competitive exam preparation,
                SIRA skill reports, and holistic readiness for students aged 6-20.
            </p>
            <div class="eq-home-hero-actions">
                <a href="<?php echo htmlspecialchars(url_for('register.php')); ?>" class="btn btn-light btn-lg">Start Your Journey</a>
                <a href="<?php echo htmlspecialchars(url_for('video_lectures.php')); ?>" class="btn btn-outline-light btn-lg">Watch Demo</a>
            </div>
            <div class="eq-home-hero-metrics">
                <div><strong><?php echo number_format($stats['students']); ?>+</strong><span>Students</span></div>
                <div><strong><?php echo number_format($stats['courses']); ?>+</strong><span>Courses</span></div>
                <div><strong><?php echo (int)$stats['success_rate']; ?>%</strong><span>Success Rate</span></div>
            </div>
        </div>
        <div class="eq-home-lead-card">
            <h3>Book Your SIRA Assessment</h3>
            <p>Fill this form and our team will connect with you quickly.</p>
            <?php if ($leadSuccess !== null): ?>
                <div class="alert alert-success py-2 px-3 small mb-3"><?php echo htmlspecialchars($leadSuccess); ?></div>
            <?php endif; ?>
            <?php if ($leadErrors !== []): ?>
                <div class="alert alert-danger py-2 px-3 small mb-3">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($leadErrors as $leadError): ?>
                            <li><?php echo htmlspecialchars($leadError); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="submit_sira_lead">
                <div class="mb-2">
                    <label class="form-label" for="lead-student-name">Student Name</label>
                    <input class="form-control form-control-sm" id="lead-student-name" name="student_name" maxlength="120" required value="<?php echo htmlspecialchars($leadForm['student_name']); ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="lead-class-name">Class</label>
                    <input class="form-control form-control-sm" id="lead-class-name" name="class_name" maxlength="40" required value="<?php echo htmlspecialchars($leadForm['class_name']); ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="lead-school-name">School Name</label>
                    <input class="form-control form-control-sm" id="lead-school-name" name="school_name" maxlength="180" required value="<?php echo htmlspecialchars($leadForm['school_name']); ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="lead-parent-email">Parent Email</label>
                    <input class="form-control form-control-sm" id="lead-parent-email" name="parent_email" type="email" maxlength="160" required value="<?php echo htmlspecialchars($leadForm['parent_email']); ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="lead-parent-mobile">Parent Mobile Number</label>
                    <input class="form-control form-control-sm" id="lead-parent-mobile" name="parent_mobile" maxlength="20" required value="<?php echo htmlspecialchars($leadForm['parent_mobile']); ?>">
                </div>
                <div class="mb-3 eq-full-span">
                    <label class="form-label" for="lead-exam">Exam</label>
                    <select class="form-select form-select-sm eq-home-select2" id="lead-exam" name="exam[]" multiple required>
                        <?php foreach ($examOptions as $code => $label): ?>
                            <option value="<?php echo htmlspecialchars($code); ?>" <?php echo in_array($code, $leadForm['exam'], true) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="text-white-50 small mt-1">Search and choose one or more exams.</div>
                </div>
                <button class="btn btn-light btn-sm" type="submit">Start Learning</button>
            </form>
        </div>
    </div>
</section>

<section class="eq-home-section">
    <div class="eq-section-title">
        <h2>Student Intelligence & Readiness Assessment (SIRA) 
        </h2>
        <p>Our comprehensive platform covers four essential skill domains, ensuring holistic development for students aged 6-20.</p>
    </div>

    <div class="eq-sira-grid">
        <div class="eq-sira-copy">
            <article class="eq-skill-card academic">
                <div class="eq-skill-icon">📘</div>
                <h5>Ace STEM Intelligence (ASI)</h5>
                <p>Strengthen analytical and problem-solving abilities through conceptual understanding.</p>
                <ul>
                    <li>Mathematics</li>
                    <li>Science (EVS)</li>
                </ul>
            </article>
            <article class="eq-skill-card creative">
                <div class="eq-skill-icon">📝</div>
                <h5>Ace Language Proficiency (ALP)</h5>
                <p>Empower confident expression through a rich blend of language skills.</p>
                <ul>
                    <li>English language skills</li>
                    <li>Vocabulary and comprehension</li>
                </ul>
            </article>
            <article class="eq-skill-card leadership">
                <div class="eq-skill-icon">🧠</div>
                <h5>Ace Logical Ability (ALA)</h5>
                <p>Enhances critical thinking and reasoning through structured problem-solving.</p>
                <ul>
                    <li>Logical reasoning</li>
                    <li>Analytical problem solving</li>
                </ul>
            </article>
            <article class="eq-skill-card technical">
                <div class="eq-skill-icon">🌍</div>
                <h5>Ace Life Skills and General Awareness (ALSGA)</h5>
                <p>Builds practical life-readiness with broad world awareness and knowledge.</p>
                <ul>
                    <li>Life skills</li>
                    <li>General knowledge and awareness</li>
                </ul>
            </article>
            <article class="eq-skill-card creative" style="grid-column: 1 / -1;">
                <div class="eq-skill-icon">💡</div>
                <h5>Ace Emotional & Digital Intelligence (AEDI)</h5>
                <p>Nurtures emotional awareness and safe, responsible engagement with the digital world and AI.</p>
                <ul>
                    <li>Emotional intelligence</li>
                    <li>Digital safety awareness</li>
                    <li>AI awareness</li>
                </ul>
            </article>
        </div>
        <div class="eq-sira-visual-wrap">
            <img src="<?php echo htmlspecialchars(url_for('assets/img/sira-assessment-visual.png')); ?>" alt="Student progress report preview for SIRA assessment">
        </div>
    </div>
</section>

<section class="eq-home-section eq-home-platform">
    <div class="eq-section-title">
        <h2>Powerful Learning Platform</h2>
        <p>Everything you need to succeed, all in one place.</p>
    </div>

    <div class="row g-3">
        <div class="col-md-6 col-xl-4"><div class="eq-platform-card"><h6>Video Lectures</h6><p>High-quality video content with interactive elements and progress tracking.</p><a href="<?php echo htmlspecialchars(url_for('video_lectures.php')); ?>">500+ Videos</a></div></div>
        <div class="col-md-6 col-xl-4"><div class="eq-platform-card"><h6>Study Materials</h6><p>Class-wise free and premium PDFs, guides, and reference documents.</p><a href="<?php echo htmlspecialchars(url_for('study-material')); ?>">Open Library</a></div></div>
        <div class="col-md-6 col-xl-4"><div class="eq-platform-card"><h6>Progress Tracking</h6><p>Real-time analytics and personalized insights for continuous improvement.</p><a href="<?php echo htmlspecialchars(url_for('dashboard.php')); ?>">95% Success Rate</a></div></div>
        <div class="col-md-6 col-xl-4"><div class="eq-platform-card"><h6>Achievement System</h6><p>Gamified learning with badges, certificates, and recognition programs.</p><a href="<?php echo htmlspecialchars(url_for('dashboard.php')); ?>">100+ Badges</a></div></div>
        <div class="col-md-6 col-xl-4"><div class="eq-platform-card"><h6>Flexible Learning</h6><p>Learn at your own pace with 24/7 access to all platform features.</p><a href="<?php echo htmlspecialchars(url_for('learning_paths.php')); ?>">24/7 Access</a></div></div>
        <div class="col-md-6 col-xl-4"><div class="eq-platform-card"><h6>Community Learning</h6><p>Connect with peers, ask questions, and share growth milestones.</p><a href="<?php echo htmlspecialchars(url_for('community.php')); ?>">Active Community</a></div></div>
    </div>
</section>

<?php if ($hasFeaturedAssessments): ?>
<section class="eq-featured-home-section">
    <div class="eq-section-title">
        <h2>Featured Assessments</h2>
        <p>Explore high-demand tests and practice papers from the homepage, add them to cart, and continue to the full catalogue anytime.</p>
    </div>

    <?php if (!empty($_GET['purchase']) && $_GET['purchase'] === 'success'): ?>
        <div class="alert alert-success">Purchase completed. Your featured items now reflect the latest access status.</div>
    <?php endif; ?>

    <div id="home-featured-message" class="alert d-none" role="alert"></div>

    <div class="eq-featured-grid<?php echo ($hasFeaturedTests xor $hasFeaturedPracticePapers) ? ' eq-single' : ''; ?>">
        <?php if ($hasFeaturedTests): ?>
        <article class="eq-featured-panel">
            <div class="eq-featured-head d-flex justify-content-between align-items-start gap-3">
                <div>
                    <h3>Featured Tests</h3>
                    <p>Timed assessments you can secure now and start from the full tests page.</p>
                </div>
                <a href="<?php echo htmlspecialchars(url_for('tests.php')); ?>" class="btn btn-outline-primary btn-sm">View More</a>
            </div>
            <div class="eq-featured-list">
                <?php foreach ($featuredTests as $test): ?>
                    <?php
                        $startAt = !empty($test['start_at']) ? new DateTimeImmutable((string)$test['start_at'], new DateTimeZone('UTC')) : null;
                        $endAt = !empty($test['end_at']) ? new DateTimeImmutable((string)$test['end_at'], new DateTimeZone('UTC')) : null;
                        $statusLabel = 'Available';
                        $statusClass = 'text-bg-success';
                        $canAttempt = true;
                        $canBuy = $startAt === null || $homeNowUtc < $startAt;
                        $buyCountdown = home_tests_countdown($homeNowUtc, $startAt);
                        if ($startAt && $homeNowUtc < $startAt) {
                            $statusLabel = 'Upcoming';
                            $statusClass = 'text-bg-warning';
                            $canAttempt = false;
                        }
                        if ($endAt && $homeNowUtc > $endAt) {
                            $statusLabel = 'Closed';
                            $statusClass = 'text-bg-secondary';
                            $canAttempt = false;
                        }
                        $testPrice = (float)($test['price_inr'] ?? 0);
                        $isPurchased = !empty($featuredPaidTestsPurchased[(int)$test['id']]);
                    ?>
                    <article class="eq-featured-card">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <h4><?php echo htmlspecialchars($test['title']); ?></h4>
                            <div class="d-flex flex-column align-items-end gap-2">
                                <span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                                <span class="badge text-bg-primary">Featured</span>
                            </div>
                        </div>
                        <p><?php echo htmlspecialchars(text_preview(strip_tags((string)$test['description']), 120, '...')); ?></p>
                        <div class="eq-featured-meta">
                            <strong><?php echo htmlspecialchars(test_price_label($testPrice)); ?></strong>
                            · <?php echo (int)$test['duration_minutes']; ?> min
                            · <?php echo (int)$test['total_marks']; ?> marks
                        </div>
                        <div class="eq-featured-buy-window<?php echo $canBuy ? '' : ' is-closed'; ?>">
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
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <?php if ($authUser && ($authUser['role'] ?? '') === 'student'): ?>
                                <?php if ($isPurchased): ?>
                                    <?php if ($canAttempt): ?>
                                        <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(url_for('test_attempt.php?id=' . (int)$test['id'])); ?>">Start Test</a>
                                    <?php else: ?>
                                        <button class="btn btn-outline-secondary btn-sm" disabled>Purchased - starts later</button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($canBuy): ?>
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm eq-featured-add-btn"
                                            data-item-value="test:<?php echo (int)$test['id']; ?>"
                                            data-item-title="<?php echo htmlspecialchars($test['title']); ?>"
                                            data-item-amount="<?php echo (int)amount_in_paise($testPrice); ?>"
                                        >
                                            Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-outline-secondary btn-sm" disabled>Buying closed</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($canBuy): ?>
                                    <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars(url_for('login.php')); ?>">Login to Buy</a>
                                <?php else: ?>
                                    <button class="btn btn-outline-secondary btn-sm" disabled>Buying closed</button>
                                <?php endif; ?>
                            <?php endif; ?>
                            <a href="<?php echo htmlspecialchars(url_for('tests.php')); ?>" class="small text-decoration-none fw-semibold">See details</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </article>
        <?php endif; ?>

        <?php if ($hasFeaturedPracticePapers): ?>
        <article class="eq-featured-panel">
            <div class="eq-featured-head d-flex justify-content-between align-items-start gap-3">
                <div>
                    <h3>Featured Practice Papers</h3>
                    <p>Downloadable revision papers linked to high-interest assessments and exam years.</p>
                </div>
                <a href="<?php echo htmlspecialchars(url_for('tests.php')); ?>" class="btn btn-outline-primary btn-sm">View More</a>
            </div>
            <div class="eq-featured-list">
                <?php foreach ($featuredPracticePapers as $paper): ?>
                    <?php
                        $paperPrice = (float)($paper['amount_inr'] ?? 0);
                        $hasPaperAccess = !empty($featuredPaidPapersPurchased[(int)$paper['id']]);
                    ?>
                    <article class="eq-featured-card">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <h4><?php echo htmlspecialchars($paper['name']); ?></h4>
                            <span class="badge text-bg-primary">Featured</span>
                        </div>
                        <p><?php echo htmlspecialchars(text_preview(strip_tags((string)$paper['description']), 120, '...')); ?></p>
                        <div class="eq-featured-meta">
                            <strong><?php echo htmlspecialchars(test_price_label($paperPrice)); ?></strong>
                            · <?php echo htmlspecialchars((string)$paper['class_name']); ?>
                            · <?php echo htmlspecialchars((string)$paper['paper_year']); ?>
                        </div>
                        <div class="small text-muted mb-2">Mapped Test: <?php echo htmlspecialchars((string)$paper['test_title']); ?></div>
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <?php if ($authUser && ($authUser['role'] ?? '') === 'student'): ?>
                                <?php if ($hasPaperAccess): ?>
                                    <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(url_for('practice_paper_download.php?id=' . (int)$paper['id'])); ?>">Download Paper</a>
                                <?php else: ?>
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary btn-sm eq-featured-add-btn"
                                        data-item-value="practice_paper:<?php echo (int)$paper['id']; ?>"
                                        data-item-title="<?php echo htmlspecialchars($paper['name']); ?>"
                                        data-item-amount="<?php echo (int)amount_in_paise($paperPrice); ?>"
                                    >
                                        Add to Cart
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars(url_for('login.php')); ?>">Login to Buy</a>
                            <?php endif; ?>
                            <a href="<?php echo htmlspecialchars(url_for('tests.php')); ?>" class="small text-decoration-none fw-semibold">See details</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </article>
        <?php endif; ?>
    </div>

    <?php if ($authUser && ($authUser['role'] ?? '') === 'student'): ?>
        <input type="hidden" id="home-payment-csrf-token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        <section class="eq-featured-cart" id="home-featured-cart">
            <h4>Featured Cart</h4>
            <p>Your cart appears only after you add a featured item from the homepage.</p>
            <div class="eq-featured-cart-stats">
                <div><strong id="home-featured-count">0</strong><span>Selected items</span></div>
                <div><strong id="home-featured-total">Rs 0</strong><span>Total cart value</span></div>
            </div>
            <div class="eq-featured-cart-items" id="home-featured-items"></div>
            <div class="eq-featured-cart-actions">
                <button type="button" class="btn btn-warning fw-semibold" id="home-featured-buy">Buy Featured Items</button>
                <a href="<?php echo htmlspecialchars(url_for('tests.php')); ?>" class="btn btn-outline-light">View Full Test Page</a>
            </div>
        </section>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="eq-home-gradient-zone">
    <div class="eq-home-section">
        <div class="eq-section-title light">
            <h2>Trusted by Students Worldwide</h2>
            <p>Join thousands of students who are already developing their skills and achieving their goals.</p>
        </div>

        <div class="eq-stat-grid">
            <div class="eq-glass-card"><strong><?php echo number_format($stats['students']); ?>+</strong><span>Active Students</span><small>Students from 50+ countries</small></div>
            <div class="eq-glass-card"><strong><?php echo number_format($stats['courses']); ?>+</strong><span>Courses Available</span><small>Across all skill domains</small></div>
            <div class="eq-glass-card"><strong><?php echo (int)$stats['success_rate']; ?>%</strong><span>Success Rate</span><small>Measured performance growth</small></div>
            <div class="eq-glass-card"><strong><?php echo number_format($stats['lessons']); ?>+</strong><span>Lessons Completed</span><small>Learning hours logged</small></div>
            <div class="eq-glass-card"><strong><?php echo (int)$stats['countries']; ?>+</strong><span>Countries</span><small>Global reach and impact</small></div>
            <div class="eq-glass-card"><strong>24/7</strong><span>Access</span><small>Learn anytime, anywhere</small></div>
        </div>

        <div class="eq-section-title light mt-5">
            <h2>Recognized Excellence</h2>
            <p>Our commitment to quality education has earned us recognition from leading organizations.</p>
        </div>

        <div class="eq-recognition-grid">
            <div class="eq-glass-card"><strong>2023</strong><span>Excellence in Education</span><small>Recognized for innovative learning approaches</small></div>
            <div class="eq-glass-card"><strong><?php echo (int)$stats['success_rate']; ?>%</strong><span>Skill Improvement</span><small>Students show measurable growth</small></div>
            <div class="eq-glass-card"><strong>50+</strong><span>Countries Reached</span><small>Global impact across regions</small></div>
        </div>

        <div class="eq-gradient-cta">
            <h3>Ready to Start Your Journey?</h3>
            <p>Join thousands of students who are already developing their skills and achieving their goals.</p>
            <div>
                <a href="<?php echo htmlspecialchars(url_for('register.php')); ?>" class="btn btn-light btn-sm px-4">Get Started Free</a>
                <a href="<?php echo htmlspecialchars(url_for('courses.php')); ?>" class="btn btn-outline-light btn-sm px-4">Learn More</a>
            </div>
        </div>
    </div>
</section>

<section class="eq-home-section">
    <div class="eq-section-title">
        <h2>What Students Say About EduquestIQ</h2>
        <p>Hear from students around the world who have transformed their learning journey with EduquestIQ.</p>
    </div>

    <div class="row g-3">
        <?php foreach ($testimonials as $i => $review): ?>
            <div class="col-md-6 col-xl-4">
                <article class="eq-testimonial-card">
                    <div class="eq-stars">★★★★★</div>
                    <p><?php echo htmlspecialchars($review['text']); ?></p>
                    <div class="eq-tag-row">
                        <?php foreach ($review['tags'] as $tag): ?>
                            <span><?php echo htmlspecialchars($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="eq-person-row">
                        <div class="eq-avatar"><?php echo htmlspecialchars(substr($review['name'], 0, 1)); ?></div>
                        <div>
                            <strong><?php echo htmlspecialchars($review['name']); ?></strong>
                            <small><?php echo htmlspecialchars($review['grade']); ?></small>
                            <small><?php echo htmlspecialchars($review['city']); ?></small>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="eq-review-cta">
        <a href="<?php echo htmlspecialchars(url_for('register.php')); ?>" class="btn btn-primary btn-sm px-4">Start Free Trial</a>
        <a href="<?php echo htmlspecialchars(url_for('community.php')); ?>" class="btn btn-outline-secondary btn-sm px-4">View All Reviews</a>
    </div>
</section>

<section class="eq-home-gradient-zone eq-last-gradient">
    <div class="eq-home-section">
        <div class="row g-4 align-items-center">
            <div class="col-lg-6 text-white">
                <p class="mb-3">Transform your learning experience with our comprehensive platform designed for students aged 6-20. Develop academic, creative, leadership, and technical skills all in one place.</p>
                <ul class="eq-gradient-checks">
                    <li>Access to 500+ courses across all domains</li>
                    <li>Personalized learning paths and progress tracking</li>
                    <li>Interactive video lectures and study materials</li>
                    <li>Community features and peer collaboration</li>
                </ul>
            </div>
            <div class="col-lg-6">
                <div class="eq-progress-panel">
                    <div class="d-flex justify-content-between small text-white-50 mb-2">
                        <span>Learning Progress</span>
                        <span>75% Complete</span>
                    </div>
                    <div class="eq-progress-track"><span></span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$eqCustomHomeFooter = true;
?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<?php if ($authUser && ($authUser['role'] ?? '') === 'student'): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<?php endif; ?>
<script>
if (window.jQuery && jQuery.fn.select2) {
    jQuery(function ($) {
        $('#lead-exam').select2({
            placeholder: 'Search and select exams',
            width: '100%',
            closeOnSelect: false
        });
    });
}

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

            const delta = Math.max(0, deadlineMs - now);
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

<?php if ($authUser && ($authUser['role'] ?? '') === 'student'): ?>
(function () {
    const storageKey = 'eduquestiq_home_featured_cart_v1';
    const cart = document.getElementById('home-featured-cart');
    const countNode = document.getElementById('home-featured-count');
    const totalNode = document.getElementById('home-featured-total');
    const itemsNode = document.getElementById('home-featured-items');
    const buyButton = document.getElementById('home-featured-buy');
    const message = document.getElementById('home-featured-message');
    const csrfToken = document.getElementById('home-payment-csrf-token') ? document.getElementById('home-payment-csrf-token').value : '';
    const addButtons = Array.from(document.querySelectorAll('.eq-featured-add-btn'));

    if (!cart || !countNode || !totalNode || !itemsNode || !buyButton || !message) {
        return;
    }

    function showMessage(type, text) {
        message.className = 'alert alert-' + type;
        message.textContent = text;
        message.classList.remove('d-none');
    }

    function formatInr(amountPaise) {
        return 'Rs ' + (amountPaise / 100).toLocaleString('en-IN', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });
    }

    function loadSelection() {
        try {
            const parsed = JSON.parse(window.localStorage.getItem(storageKey) || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function saveSelection(values) {
        window.localStorage.setItem(storageKey, JSON.stringify(values));
    }

    function renderCart() {
        const selectedValues = loadSelection();
        const selectedButtons = addButtons.filter(function (button) {
            return selectedValues.indexOf(button.dataset.itemValue || '') !== -1;
        });
        const totalPaise = selectedButtons.reduce(function (sum, button) {
            return sum + Number(button.dataset.itemAmount || 0);
        }, 0);

        addButtons.forEach(function (button) {
            const active = selectedValues.indexOf(button.dataset.itemValue || '') !== -1;
            button.classList.toggle('is-added', active);
            button.textContent = active ? 'Added to Cart' : 'Add to Cart';
        });

        if (!selectedButtons.length) {
            cart.classList.remove('is-visible');
            countNode.textContent = '0';
            totalNode.textContent = 'Rs 0';
            itemsNode.innerHTML = '';
            return;
        }

        cart.classList.add('is-visible');
        countNode.textContent = String(selectedButtons.length);
        totalNode.textContent = formatInr(totalPaise);
        itemsNode.innerHTML = '';
        selectedButtons.forEach(function (button) {
            const pill = document.createElement('span');
            pill.className = 'eq-featured-cart-pill';
            pill.textContent = (button.dataset.itemTitle || 'Featured item') + ' · ' + formatInr(Number(button.dataset.itemAmount || 0));
            itemsNode.appendChild(pill);
        });
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

    addButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const value = button.dataset.itemValue || '';
            if (!value) return;

            const selectedValues = loadSelection();
            const index = selectedValues.indexOf(value);
            if (index === -1) {
                selectedValues.push(value);
            } else {
                selectedValues.splice(index, 1);
            }
            saveSelection(selectedValues);
            renderCart();
        });
    });

    if (window.location.search.indexOf('purchase=success') !== -1) {
        saveSelection([]);
    }

    buyButton.addEventListener('click', async function () {
        const selectedValues = loadSelection();
        const selected = selectedValues.map(function (value) {
            const parts = value.split(':');
            return {type: parts[0], id: Number(parts[1])};
        }).filter(function (item) {
            return item.type && item.id > 0;
        });

        if (!selected.length) {
            cart.classList.remove('is-visible');
            return;
        }

        buyButton.disabled = true;
        showMessage('info', 'Preparing secure checkout...');
        let order;
        try {
            order = await postJson(<?php echo json_encode(url_for('api/create-order.php')); ?>, {
                amount: 0,
                currency: <?php echo json_encode(payment_gateway_currency()); ?>,
                receipt: 'home-featured',
                items: selected
            });
        } catch (error) {
            showMessage('danger', error.message);
            buyButton.disabled = false;
            return;
        }

        const rzp = new Razorpay({
            key: order.key_id,
            amount: order.amount,
            currency: order.currency,
            name: 'EduquestIQ',
            description: 'EduquestIQ featured purchase',
            order_id: order.order_id,
            callback_url: <?php echo json_encode(url_for('razorpay_return.php?source=home')); ?>,
            redirect: true,
            prefill: {
                name: <?php echo json_encode((string)$authUser['name']); ?>,
                email: <?php echo json_encode((string)$authUser['email']); ?>
            },
            handler: async function (response) {
                try {
                    await postJson(<?php echo json_encode(url_for('api/verify-payment.php')); ?>, {
                        razorpay_order_id: response.razorpay_order_id || '',
                        razorpay_payment_id: response.razorpay_payment_id || '',
                        razorpay_signature: response.razorpay_signature || ''
                    });
                    saveSelection([]);
                    showMessage('success', 'Payment verified. Refreshing featured items...');
                    window.location.href = <?php echo json_encode(url_for('index.php?purchase=success')); ?>;
                } catch (error) {
                    showMessage('danger', error.message);
                    buyButton.disabled = false;
                }
            },
            theme: {color: '#4374ff'},
            modal: {
                ondismiss: function () {
                    showMessage('warning', 'Payment was cancelled. You can continue from the featured cart anytime.');
                    buyButton.disabled = false;
                }
            }
        });
        rzp.on('payment.failed', function (response) {
            const reason = response && response.error && response.error.description ? response.error.description : 'Payment failed. Please try again.';
            showMessage('danger', reason);
            buyButton.disabled = false;
        });
        rzp.open();
    });

    renderCart();
})();
<?php endif; ?>
</script>
<?php
require_once __DIR__ . '/includes_footer.php';
