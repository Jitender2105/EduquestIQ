<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';
require_once __DIR__ . '/includes_csrf.php';
require_once __DIR__ . '/includes_skills.php';

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

try {
    $pdo->beginTransaction();

    // Ensure test exists
    $stmt = $pdo->prepare(
        'SELECT id, total_marks FROM tests WHERE id = ?'
    );
    $stmt->execute([$testId]);
    $test = $stmt->fetch();
    if (!$test) {
        $pdo->rollBack();
        header('Location: ' . url_for('tests.php'));
        exit;
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

    $totalScore = 0.0;
    $totalPossible = 0.0;

    foreach ($questions as $q) {
        $qid = (int)$q['question_id'];
        $marks = (float)$q['marks'];
        $totalPossible += $marks;

        $selectedOptionId = null;
        $subjectiveAnswer = null;

        if ($q['question_type'] === 'mcq') {
            if (isset($selectedMcq[$qid]) && $selectedMcq[$qid] !== '') {
                $selectedOptionId = (int)$selectedMcq[$qid];

                $stmt = $pdo->prepare(
                    'SELECT is_correct FROM question_options WHERE id = ? AND question_id = ?'
                );
                $stmt->execute([$selectedOptionId, $qid]);
                $opt = $stmt->fetch();
                if ($opt && (int)$opt['is_correct'] === 1) {
                    $totalScore += $marks;
                }
            }
        } else {
            if (isset($subjectiveAnswers[$qid])) {
                $subjectiveAnswer = trim((string)$subjectiveAnswers[$qid]);
            }
        }

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

header('Location: ' . url_for('tests.php'));
exit;
