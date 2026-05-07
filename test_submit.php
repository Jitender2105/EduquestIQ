<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';
require_once __DIR__ . '/includes_csrf.php';
require_once __DIR__ . '/includes_skills.php';
require_once __DIR__ . '/includes_sira.php';
require_once __DIR__ . '/includes_payments.php';

$user = require_auth(['student']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url_for('tests.php'));
    exit;
}

$csrf = $_POST['csrf_token'] ?? null;
if (!verify_csrf_token($csrf)) {
    http_response_code(400);
    echo 'Invalid CSRF token.';
    exit;
}

$testId = isset($_POST['test_id']) ? (int)$_POST['test_id'] : 0;
if ($testId <= 0) {
    header('Location: ' . url_for('tests.php'));
    exit;
}

$pdo = get_pdo();
$testHasActiveColumn = table_has_column($pdo, 'tests', 'is_active');
$testHasGradeColumn = table_has_column($pdo, 'tests', 'target_grade');
$studentGradeStmt = $pdo->prepare('SELECT grade FROM users WHERE id = ? LIMIT 1');
$studentGradeStmt->execute([(int)$user['sub']]);
$studentGrade = trim((string)$studentGradeStmt->fetchColumn());

try {
    $pdo->beginTransaction();

    // Ensure test exists
    $stmt = $pdo->prepare(
        'SELECT id, total_marks, start_at, end_at, price_inr'
        . ($testHasGradeColumn ? ', target_grade' : '')
        . ($testHasActiveColumn ? ', is_active' : '')
        . ' FROM tests WHERE id = ?'
    );
    $stmt->execute([$testId]);
    $test = $stmt->fetch();
    if (!$test) {
        $pdo->rollBack();
        header('Location: ' . url_for('tests.php'));
        exit;
    }
    if (($testHasActiveColumn && empty($test['is_active']))
        || ($testHasGradeColumn && $studentGrade !== '' && !empty($test['target_grade']) && (string)$test['target_grade'] !== $studentGrade)) {
        $pdo->rollBack();
        header('Location: ' . url_for('tests.php'));
        exit;
    }

    if ((float)($test['price_inr'] ?? 0) > 0 && !test_purchase_is_paid($pdo, $testId, (int)$user['sub'])) {
        $pdo->rollBack();
        header('Location: ' . url_for('test_purchase.php?id=' . $testId));
        exit;
    }

    $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    if (!empty($test['start_at'])) {
        $startAt = new DateTimeImmutable((string)$test['start_at'], new DateTimeZone('UTC'));
        if ($nowUtc < $startAt) {
            $pdo->rollBack();
            header('Location: ' . url_for('test_attempt.php?id=' . $testId));
            exit;
        }
    }
    if (!empty($test['end_at'])) {
        $endAt = new DateTimeImmutable((string)$test['end_at'], new DateTimeZone('UTC'));
        if ($nowUtc > $endAt) {
            $pdo->rollBack();
            header('Location: ' . url_for('tests.php'));
            exit;
        }
    }

    // Load questions in this test
    $stmt = $pdo->prepare(
        'SELECT tq.question_id, tq.marks, q.question_type
         FROM test_questions tq
         JOIN questions q ON tq.question_id = q.id
         WHERE tq.test_id = ?'
    );
    $stmt->execute([$testId]);
    $questions = $stmt->fetchAll();
    if (!$questions) {
        $pdo->rollBack();
        header('Location: ' . url_for('tests.php'));
        exit;
    }

    // Create attempt
    $stmt = $pdo->prepare(
        'INSERT INTO test_attempts (test_id, student_id, score, attempt_date)
         VALUES (?, ?, 0, NOW())'
    );
    $stmt->execute([$testId, (int)$user['sub']]);
    $attemptId = (int)$pdo->lastInsertId();

    $selectedMcq = $_POST['q'] ?? [];
    $subjectiveAnswers = $_POST['s'] ?? [];
    $visitedFlags = $_POST['visited'] ?? [];
    $reviewFlags = $_POST['review'] ?? [];
    $hasAnswerStatusColumn = table_has_column($pdo, 'test_answers', 'answer_status');

    $totalScore = 0.0;
    $totalPossible = 0.0;

    foreach ($questions as $q) {
        $qid = (int)$q['question_id'];
        $marks = (float)$q['marks'];
        $totalPossible += $marks;

        $selectedOptionId = null;
        $subjectiveAnswer = null;
        $answerStatus = 'not_attempted';
        $wasVisited = !empty($visitedFlags[$qid]);
        $isMarkedForReview = !empty($reviewFlags[$qid]);

        if ($q['question_type'] === 'mcq') {
            if (isset($selectedMcq[$qid]) && $selectedMcq[$qid] !== '') {
                $selectedOptionId = (int)$selectedMcq[$qid];
                $answerStatus = $isMarkedForReview ? 'marked_for_review' : 'answered';

                $stmt = $pdo->prepare(
                    'SELECT is_correct FROM question_options WHERE id = ? AND question_id = ?'
                );
                $stmt->execute([$selectedOptionId, $qid]);
                $opt = $stmt->fetch();
                if ($opt && (int)$opt['is_correct'] === 1) {
                    $totalScore += $marks;
                }
            } elseif ($wasVisited) {
                $answerStatus = $isMarkedForReview ? 'marked_for_review' : 'not_answered';
            }
        } else {
            if (isset($subjectiveAnswers[$qid])) {
                $subjectiveAnswer = trim((string)$subjectiveAnswers[$qid]);
                if ($subjectiveAnswer !== '') {
                    $answerStatus = $isMarkedForReview ? 'marked_for_review' : 'answered';
                } elseif ($wasVisited) {
                    $answerStatus = $isMarkedForReview ? 'marked_for_review' : 'not_answered';
                }
            }
        }

        if ($hasAnswerStatusColumn) {
            $stmt = $pdo->prepare(
                'INSERT INTO test_answers (attempt_id, question_id, selected_option_id, subjective_answer, answer_status)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $attemptId,
                $qid,
                $selectedOptionId ?: null,
                $subjectiveAnswer,
                $answerStatus,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO test_answers (attempt_id, question_id, selected_option_id, subjective_answer)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $attemptId,
                $qid,
                $selectedOptionId ?: null,
                $subjectiveAnswer,
            ]);
        }
    }

    // Convert to percentage if totalPossible > 0
    $scorePercent = $totalPossible > 0 ? ($totalScore / $totalPossible) * 100.0 : 0.0;

    $stmt = $pdo->prepare(
        'UPDATE test_attempts SET score = ? WHERE id = ?'
    );
    $stmt->execute([$scorePercent, $attemptId]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo 'Failed to submit test. Please try again.';
    exit;
}

// Update skill_progress for this attempt (separate from attempt transaction)
update_skill_progress_from_test($attemptId);

header('Location: ' . url_for('sira_report.php?attempt_id=' . $attemptId));
exit;
