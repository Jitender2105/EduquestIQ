<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';
require_once __DIR__ . '/includes_sira.php';

$user = require_auth(['school_admin', 'content_admin', 'super_admin']);
$pdo = get_pdo();

$studentId = (int)($_GET['student_id'] ?? 0);
$student = null;
$errors = [];

if ($studentId <= 0) {
    $errors[] = 'Student id is required.';
} else {
    $stmt = $pdo->prepare('SELECT id, name, email, grade, school_id, role FROM users WHERE id = ? AND role = "student" LIMIT 1');
    $stmt->execute([$studentId]);
    $student = $stmt->fetch() ?: null;

    if (!$student) {
        $errors[] = 'Student not found.';
    } elseif ($user['role'] === 'school_admin' && (int)($student['school_id'] ?? 0) !== (int)($user['school_id'] ?? 0)) {
        $errors[] = 'You can only view students in your school.';
        $student = null;
    }
}

$attempts = [];
$attributeRows = [];
$latestReport = null;
$avgScore = 0.0;
$attemptCount = 0;
$schoolName = '';

if ($student) {
    if (!empty($student['school_id'])) {
        $stmt = $pdo->prepare('SELECT name FROM schools WHERE id = ?');
        $stmt->execute([(int)$student['school_id']]);
        $schoolName = (string)($stmt->fetchColumn() ?: '');
    }

    $stmt = $pdo->prepare(
        'SELECT ta.id, t.title, ta.score, ta.attempt_date
         FROM test_attempts ta
         JOIN tests t ON t.id = ta.test_id
         WHERE ta.student_id = ?
         ORDER BY ta.attempt_date DESC'
    );
    $stmt->execute([$studentId]);
    $attempts = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT COALESCE(AVG(score),0), COUNT(*) FROM test_attempts WHERE student_id = ?');
    $stmt->execute([$studentId]);
    $vals = $stmt->fetch(PDO::FETCH_NUM);
    if ($vals) {
        $avgScore = (float)$vals[0];
        $attemptCount = (int)$vals[1];
    }

    $stmt = $pdo->prepare(
        'SELECT a.name, AVG(sp.score) AS score
         FROM skill_progress sp
         JOIN attributes a ON a.id = sp.attribute_id
         WHERE sp.student_id = ?
         GROUP BY a.id, a.name
         ORDER BY a.name'
    );
    $stmt->execute([$studentId]);
    $attributeRows = $stmt->fetchAll();

    if (!empty($attempts)) {
        $latestReport = sira_build_test_report($pdo, (int)$attempts[0]['id']);
    }
}

require_once __DIR__ . '/includes_header.php';
?>
<div class="eq-page-head d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h2 class="mb-0">Student Report</h2>
        <div class="subtitle">Detailed test and skill report for school monitoring.</div>
    </div>
    <a href="<?php echo htmlspecialchars(url_for('dashboard.php')); ?>" class="btn btn-outline-secondary btn-sm">Back to dashboard</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php elseif ($student): ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card card-dashboard h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h4 class="mb-1"><?php echo htmlspecialchars($student['name']); ?></h4>
                            <div class="text-muted"><?php echo htmlspecialchars($student['email']); ?> · Grade <?php echo htmlspecialchars((string)($student['grade'] ?? '')); ?></div>
                        </div>
                        <div class="text-end">
                            <div class="small text-muted">Average score</div>
                            <div class="h3 mb-0"><?php echo number_format($avgScore, 1); ?></div>
                        </div>
                    </div>
                    <hr>
                    <p class="mb-0"><?php echo htmlspecialchars(sira_overall_message($avgScore, (string)$student['name'])); ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card card-dashboard h-100">
                <div class="card-body">
                    <h6 class="card-title mb-3">Quick Stats</h6>
                    <ul class="list-unstyled small mb-0">
                        <li class="mb-2"><strong>Tests attempted:</strong> <?php echo (int)$attemptCount; ?></li>
                        <li class="mb-2"><strong>Attributes tracked:</strong> <?php echo count($attributeRows); ?></li>
                        <li class="mb-2"><strong>School:</strong> <?php echo htmlspecialchars($schoolName !== '' ? $schoolName : 'Not linked'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <?php if ($latestReport): ?>
        <div class="card card-dashboard mt-3">
            <div class="card-body">
                <h5 class="card-title">Latest SIRA summary</h5>
                <div class="small text-muted mb-2">
                    <?php echo htmlspecialchars($latestReport['attempt']['test_title']); ?> · <?php echo htmlspecialchars((string)$latestReport['attempt']['attempt_date']); ?>
                </div>
                <p class="mb-0"><?php echo htmlspecialchars($latestReport['overall_message']); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="card card-dashboard h-100">
                <div class="card-body">
                    <h5 class="card-title">Attribute scores</h5>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($attributeRows as $row): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($row['name']); ?></span>
                                <span class="badge text-bg-primary rounded-pill"><?php echo number_format((float)$row['score'], 1); ?></span>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!$attributeRows): ?>
                            <li class="list-group-item">No skill data yet.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-dashboard h-100">
                <div class="card-body">
                    <h5 class="card-title">Test history</h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Test</th>
                                    <th>Score</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attempts as $attempt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($attempt['title']); ?></td>
                                        <td><?php echo number_format((float)$attempt['score'], 1); ?></td>
                                        <td><?php echo htmlspecialchars((string)$attempt['attempt_date']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$attempts): ?>
                                    <tr><td colspan="3" class="text-muted">No test attempts yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes_footer.php';
