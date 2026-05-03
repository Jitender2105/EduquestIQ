<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?
         LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function sira_band(float $score): array
{
    if ($score >= 85) {
        return ['label' => 'Excellent', 'color' => 'success', 'message' => 'This is a strong and consistent performance.'];
    }
    if ($score >= 70) {
        return ['label' => 'Strong', 'color' => 'primary', 'message' => 'You are showing reliable understanding and good control.'];
    }
    if ($score >= 50) {
        return ['label' => 'Developing', 'color' => 'warning', 'message' => 'You are on the right path and regular practice will lift this area quickly.'];
    }

    return ['label' => 'Foundation', 'color' => 'danger', 'message' => 'This area needs focused support and guided practice.'];
}

function sira_attribute_message(string $attributeName, float $score, string $studentName = ''): string
{
    $band = sira_band($score);
    $namePrefix = $studentName !== '' ? $studentName . ', ' : '';

    switch ($band['label']) {
        case 'Excellent':
            return $namePrefix . 'you are demonstrating excellent strength in ' . $attributeName . '. Keep stretching with advanced practice and challenge tasks.';
        case 'Strong':
            return $namePrefix . 'you are performing strongly in ' . $attributeName . '. A little more consistency and depth will push this into excellence.';
        case 'Developing':
            return $namePrefix . $attributeName . ' is developing well. Focused revision, short practice cycles, and feedback will help you grow steadily.';
        default:
            return $namePrefix . $attributeName . ' needs a stronger base. Start with the core concepts and build confidence step by step.';
    }
}

function sira_overall_message(float $score, string $studentName = ''): string
{
    $band = sira_band($score);
    $namePrefix = $studentName !== '' ? $studentName . ', ' : '';

    switch ($band['label']) {
        case 'Excellent':
            return $namePrefix . 'your overall SIRA profile is excellent. You are showing balanced performance across key skills and are ready for advanced learning paths.';
        case 'Strong':
            return $namePrefix . 'your overall performance is strong. Keep practising consistently to turn this into a high-confidence profile.';
        case 'Developing':
            return $namePrefix . 'you have a solid foundation. With structured practice and regular revision, your overall performance can improve noticeably.';
        default:
            return $namePrefix . 'your report shows that the right support can make a big difference. A focused learning plan will help you improve steadily.';
    }
}

function sira_rank_block(array $scores, float $currentScore): array
{
    $participants = count($scores);
    if ($participants === 0) {
        return [
            'participants' => 0,
            'rank' => null,
            'percentile' => null,
            'average' => null,
        ];
    }

    $higher = 0;
    $sum = 0.0;
    foreach ($scores as $score) {
        $value = (float)$score;
        $sum += $value;
        if ($value > $currentScore) {
            $higher++;
        }
    }

    $rank = $higher + 1;
    $percentile = $participants > 1
        ? (($participants - $rank) / ($participants - 1)) * 100.0
        : 100.0;

    return [
        'participants' => $participants,
        'rank' => $rank,
        'percentile' => $percentile,
        'average' => $sum / $participants,
        'top_score' => max(array_map('floatval', $scores)),
        'lowest_score' => min(array_map('floatval', $scores)),
    ];
}

function sira_empty_rank_block(): array
{
    return [
        'participants' => 0,
        'rank' => null,
        'percentile' => null,
        'average' => null,
        'top_score' => null,
        'lowest_score' => null,
    ];
}

function sira_build_test_report(PDO $pdo, int $attemptId): ?array
{
    $hasAnswerStatus = table_has_column($pdo, 'test_answers', 'answer_status');

    $stmt = $pdo->prepare(
        'SELECT ta.id AS attempt_id,
                ta.test_id,
                ta.student_id,
                ta.score AS attempt_score,
                ta.attempt_date,
                t.title AS test_title,
                t.description AS test_description,
                t.total_marks,
                t.duration_minutes,
                u.name AS student_name,
                u.email AS student_email,
                u.grade AS student_grade,
                u.school_id AS student_school_id,
                s.name AS student_school_name,
                s.state AS student_school_state
         FROM test_attempts ta
         JOIN tests t ON t.id = ta.test_id
         JOIN users u ON u.id = ta.student_id
         LEFT JOIN schools s ON s.id = u.school_id
         WHERE ta.id = ?'
    );
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch();
    if (!$attempt) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT tq.id AS test_question_id,
                tq.question_id,
                tq.marks,
                q.question_text,
                q.question_type,
                q.difficulty
         FROM test_questions tq
         JOIN questions q ON q.id = tq.question_id
         WHERE tq.test_id = ?
         ORDER BY tq.id ASC'
    );
    $stmt->execute([(int)$attempt['test_id']]);
    $questions = $stmt->fetchAll();

    if (!$questions) {
        $emptyComparison = [
            'overall' => sira_empty_rank_block() + ['label' => 'Overall'],
            'class' => sira_empty_rank_block() + ['label' => 'Same School + Class'],
            'grade' => sira_empty_rank_block() + ['label' => 'Same Class'],
            'school' => sira_empty_rank_block() + ['label' => 'School'],
            'state' => sira_empty_rank_block() + ['label' => 'State'],
            'state_grade' => sira_empty_rank_block() + ['label' => 'State + Class'],
        ];
        return [
            'attempt' => $attempt,
            'question_rows' => [],
            'attribute_rows' => [],
            'status_counts' => ['answered' => 0, 'not_answered' => 0, 'not_attempted' => 0, 'marked_for_review' => 0],
            'overall_score' => (float)$attempt['attempt_score'],
            'overall_message' => sira_overall_message((float)$attempt['attempt_score'], (string)$attempt['student_name']),
            'chart_labels' => [],
            'chart_scores' => [],
            'total_questions' => 0,
            'attempted_count' => 0,
            'response_count' => 0,
            'correct_count' => 0,
            'incorrect_count' => 0,
            'pending_subjective_count' => 0,
            'earned_marks_total' => 0.0,
            'objective_possible_marks' => 0.0,
            'total_possible_marks' => 0.0,
            'accuracy_percent' => null,
            'completion_percent' => 0.0,
            'unanswered_count' => 0,
            'comparison' => $emptyComparison,
            'insights' => [],
        ];
    }

    $questionIds = array_map(static fn(array $row): int => (int)$row['question_id'], $questions);
    $in = implode(',', array_fill(0, count($questionIds), '?'));
    $stmt = $pdo->prepare(
        'SELECT ta.question_id,
                ta.selected_option_id,
                ta.subjective_answer' . ($hasAnswerStatus ? ', ta.answer_status' : '') . ',
                q.question_type,
                qo.option_text AS selected_option_text,
                qo.is_correct AS selected_is_correct
         FROM test_answers ta
         JOIN questions q ON q.id = ta.question_id
         LEFT JOIN question_options qo ON qo.id = ta.selected_option_id
         WHERE ta.attempt_id = ?
         ORDER BY ta.id ASC'
    );
    $stmt->execute([$attemptId]);
    $answers = [];
    foreach ($stmt->fetchAll() as $row) {
        $answers[(int)$row['question_id']] = $row;
    }

    $correctOptionStmt = $pdo->prepare(
        'SELECT question_id, option_text
         FROM question_options
         WHERE question_id IN (' . $in . ') AND is_correct = 1
         ORDER BY id ASC'
    );
    $correctOptionStmt->execute($questionIds);
    $correctOptionsByQuestion = [];
    foreach ($correctOptionStmt->fetchAll() as $row) {
        $qid = (int)$row['question_id'];
        if (!isset($correctOptionsByQuestion[$qid])) {
            $correctOptionsByQuestion[$qid] = (string)$row['option_text'];
        }
    }

    $mappingStmt = $pdo->prepare(
        'SELECT qam.question_id,
                qam.attribute_id,
                qam.sub_attribute_id,
                qam.weight,
                a.name AS attribute_name,
                sa.name AS sub_attribute_name
         FROM question_attribute_mapping qam
         JOIN attributes a ON a.id = qam.attribute_id
         JOIN sub_attributes sa ON sa.id = qam.sub_attribute_id
         WHERE qam.question_id IN (' . $in . ')'
    );
    $mappingStmt->execute($questionIds);
    $mappingRows = $mappingStmt->fetchAll();

    $mappingsByQuestion = [];
    foreach ($mappingRows as $row) {
        $qid = (int)$row['question_id'];
        $mappingsByQuestion[$qid][] = $row;
    }

    $attributeAgg = [];
    $questionRows = [];
    $statusCounts = [
        'answered' => 0,
        'not_answered' => 0,
        'not_attempted' => 0,
        'marked_for_review' => 0,
    ];
    $correctCount = 0;
    $incorrectCount = 0;
    $pendingSubjectiveCount = 0;
    $earnedMarksTotal = 0.0;
    $objectivePossibleMarks = 0.0;
    $totalPossibleMarks = 0.0;
    $responseCount = 0;
    $unansweredCount = 0;

    foreach ($questions as $idx => $question) {
        $qid = (int)$question['question_id'];
        $answer = $answers[$qid] ?? null;
        $status = 'not_attempted';
        if ($answer && $hasAnswerStatus && !empty($answer['answer_status'])) {
            $status = (string)$answer['answer_status'];
        } elseif ($answer) {
            $selected = trim((string)($answer['selected_option_id'] ?? ''));
            $subjective = trim((string)($answer['subjective_answer'] ?? ''));
            if ($selected !== '' || $subjective !== '') {
                $status = 'answered';
            }
        }

        if (!isset($statusCounts[$status])) {
            $status = 'not_attempted';
        }
        $statusCounts[$status]++;

        $isCorrect = false;
        $selectedOptionText = null;
        $subjectiveAnswer = $answer['subjective_answer'] ?? null;
        $hasResponse = false;
        if ($answer && $question['question_type'] === 'mcq' && !empty($answer['selected_option_id'])) {
            $isCorrect = !empty($answer['selected_is_correct']) && (int)$answer['selected_is_correct'] === 1;
            $selectedOptionText = $answer['selected_option_text'] ?? null;
            $hasResponse = true;
        } elseif ($question['question_type'] === 'subjective' && trim((string)$subjectiveAnswer) !== '') {
            $hasResponse = true;
        }

        if ($hasResponse) {
            $responseCount++;
        }
        if ($status === 'not_answered' || ($status === 'marked_for_review' && !$hasResponse)) {
            $unansweredCount++;
        }

        $earnedMarks = null;
        $maxMarks = (float)$question['marks'];
        $totalPossibleMarks += $maxMarks;
        if ($question['question_type'] === 'mcq') {
            $objectivePossibleMarks += $maxMarks;
            $earnedMarks = $isCorrect ? $maxMarks : 0.0;
            $earnedMarksTotal += $earnedMarks;
            if ($status === 'answered' || $status === 'marked_for_review') {
                if ($isCorrect) {
                    $correctCount++;
                } else {
                    $incorrectCount++;
                }
            }
        } elseif (trim((string)$subjectiveAnswer) !== '') {
            $pendingSubjectiveCount++;
        }

        if (!isset($mappingsByQuestion[$qid])) {
            $mappingsByQuestion[$qid] = [];
        }

        foreach ($mappingsByQuestion[$qid] as $mapping) {
            $attributeId = (int)$mapping['attribute_id'];
            $subAttributeId = (int)$mapping['sub_attribute_id'];
            $weight = (float)$mapping['weight'];
            if ($weight <= 0) {
                continue;
            }

            if (!isset($attributeAgg[$attributeId])) {
                $attributeAgg[$attributeId] = [
                    'attribute_name' => (string)$mapping['attribute_name'],
                    'possible' => 0.0,
                    'earned' => 0.0,
                    'pending_review' => 0,
                    'subs' => [],
                ];
            }

            if (!isset($attributeAgg[$attributeId]['subs'][$subAttributeId])) {
                $attributeAgg[$attributeId]['subs'][$subAttributeId] = [
                    'name' => (string)$mapping['sub_attribute_name'],
                    'possible' => 0.0,
                    'earned' => 0.0,
                    'pending_review' => 0,
                ];
            }

            if ($question['question_type'] === 'mcq') {
                $attributeAgg[$attributeId]['possible'] += $weight;
                $attributeAgg[$attributeId]['subs'][$subAttributeId]['possible'] += $weight;
                if ($isCorrect) {
                    $attributeAgg[$attributeId]['earned'] += $weight;
                    $attributeAgg[$attributeId]['subs'][$subAttributeId]['earned'] += $weight;
                }
            } elseif (trim((string)$subjectiveAnswer) !== '') {
                $attributeAgg[$attributeId]['pending_review']++;
                $attributeAgg[$attributeId]['subs'][$subAttributeId]['pending_review']++;
            }
        }

        $questionRows[] = [
            'number' => $idx + 1,
            'question_text' => (string)$question['question_text'],
            'question_type' => (string)$question['question_type'],
            'difficulty' => (string)($question['difficulty'] ?? ''),
            'status' => $status,
            'marks' => (float)$question['marks'],
            'earned_marks' => $earnedMarks,
            'is_correct' => $question['question_type'] === 'mcq' ? $isCorrect : null,
            'selected_option_text' => $selectedOptionText,
            'correct_option_text' => $correctOptionsByQuestion[$qid] ?? null,
            'subjective_answer' => $subjectiveAnswer,
            'has_response' => $hasResponse,
        ];
    }

    $attributeRows = [];
    foreach ($attributeAgg as $attr) {
        $score = $attr['possible'] > 0 ? ($attr['earned'] / $attr['possible']) * 100.0 : 0.0;
        $band = sira_band($score);
        $attributeRows[] = [
            'name' => $attr['attribute_name'],
            'score' => $score,
            'label' => $band['label'],
            'message' => sira_attribute_message($attr['attribute_name'], $score, (string)$attempt['student_name']),
            'pending_review' => $attr['pending_review'],
            'subs' => array_map(static function (array $sub): array {
                $subScore = $sub['possible'] > 0 ? ($sub['earned'] / $sub['possible']) * 100.0 : 0.0;
                return [
                    'name' => $sub['name'],
                    'score' => $subScore,
                    'pending_review' => $sub['pending_review'],
                ];
            }, $attr['subs']),
        ];
    }

    usort($attributeRows, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
    $chartLabels = [];
    $chartScores = [];
    foreach ($attributeRows as $attrRow) {
        $chartLabels[] = $attrRow['name'];
        $chartScores[] = round((float)$attrRow['score'], 1);
    }

    $comparisonStmt = $pdo->prepare(
        'SELECT ta.score, u.grade, u.school_id, s.state
         FROM test_attempts ta
         JOIN users u ON u.id = ta.student_id
         LEFT JOIN schools s ON s.id = u.school_id
         WHERE ta.test_id = ?'
    );
    $comparisonStmt->execute([(int)$attempt['test_id']]);
    $allScores = [];
    $classScores = [];
    $gradeScores = [];
    $schoolScores = [];
    $stateScores = [];
    $stateGradeScores = [];
    foreach ($comparisonStmt->fetchAll() as $row) {
        $score = (float)$row['score'];
        $allScores[] = $score;

        if ((string)($row['grade'] ?? '') !== '' && (string)($row['grade'] ?? '') === (string)($attempt['student_grade'] ?? '')
            && (int)($row['school_id'] ?? 0) === (int)($attempt['student_school_id'] ?? 0)) {
            $classScores[] = $score;
        }
        if ((string)($row['grade'] ?? '') !== '' && (string)($row['grade'] ?? '') === (string)($attempt['student_grade'] ?? '')) {
            $gradeScores[] = $score;
        }
        if ((int)($row['school_id'] ?? 0) > 0 && (int)($row['school_id'] ?? 0) === (int)($attempt['student_school_id'] ?? 0)) {
            $schoolScores[] = $score;
        }
        if ((string)($row['state'] ?? '') !== '' && (string)($row['state'] ?? '') === (string)($attempt['student_school_state'] ?? '')) {
            $stateScores[] = $score;
        }
        if ((string)($row['state'] ?? '') !== '' && (string)($row['state'] ?? '') === (string)($attempt['student_school_state'] ?? '')
            && (string)($row['grade'] ?? '') !== '' && (string)($row['grade'] ?? '') === (string)($attempt['student_grade'] ?? '')) {
            $stateGradeScores[] = $score;
        }
    }

    $comparison = [
        'overall' => sira_rank_block($allScores, (float)$attempt['attempt_score']),
        'class' => sira_rank_block($classScores, (float)$attempt['attempt_score']),
        'grade' => sira_rank_block($gradeScores, (float)$attempt['attempt_score']),
        'school' => sira_rank_block($schoolScores, (float)$attempt['attempt_score']),
        'state' => sira_rank_block($stateScores, (float)$attempt['attempt_score']),
        'state_grade' => sira_rank_block($stateGradeScores, (float)$attempt['attempt_score']),
    ];

    $comparison['class']['label'] = 'Same School + Class';
    $comparison['grade']['label'] = 'Same Class';
    $comparison['school']['label'] = 'School';
    $comparison['state']['label'] = 'State';
    $comparison['state_grade']['label'] = 'State + Class';
    $comparison['overall']['label'] = 'Overall';

    $attemptedCount = count($questionRows) - (int)$statusCounts['not_attempted'];
    $accuracyPercent = ($correctCount + $incorrectCount) > 0
        ? ($correctCount / ($correctCount + $incorrectCount)) * 100.0
        : null;
    $completionPercent = count($questionRows) > 0 ? ($responseCount / count($questionRows)) * 100.0 : 0.0;
    $topScore = $comparison['overall']['top_score'] ?? null;
    $scoreGapToTop = $topScore !== null ? max(0.0, (float)$topScore - (float)$attempt['attempt_score']) : null;

    $insights = [];
    if ($accuracyPercent !== null) {
        $insights[] = [
            'label' => 'Accuracy',
            'value' => number_format($accuracyPercent, 1) . '%',
            'text' => $accuracyPercent >= 80
                ? 'Strong precision on attempted objective questions.'
                : 'Review incorrect MCQs first; that is the fastest score recovery area.',
        ];
    }
    $insights[] = [
        'label' => 'Completion',
        'value' => number_format($completionPercent, 1) . '%',
        'text' => $completionPercent >= 90
            ? 'Most questions received a response.'
            : 'Unattempted questions are reducing the score ceiling.',
    ];
    if ($scoreGapToTop !== null) {
        $insights[] = [
            'label' => 'Gap to top score',
            'value' => number_format($scoreGapToTop, 1) . '%',
            'text' => $scoreGapToTop <= 10
                ? 'Close to the current top performance band for this test.'
                : 'There is clear room to improve through targeted revision.',
        ];
    }
    if ($pendingSubjectiveCount > 0) {
        $insights[] = [
            'label' => 'Pending review',
            'value' => (string)$pendingSubjectiveCount,
            'text' => 'Subjective answers can change the final interpretation after teacher review.',
        ];
    }

    return [
        'attempt' => $attempt,
        'question_rows' => $questionRows,
        'attribute_rows' => $attributeRows,
        'status_counts' => $statusCounts,
        'overall_score' => (float)$attempt['attempt_score'],
        'overall_message' => sira_overall_message((float)$attempt['attempt_score'], (string)$attempt['student_name']),
        'chart_labels' => $chartLabels,
        'chart_scores' => $chartScores,
        'total_questions' => count($questionRows),
        'attempted_count' => $attemptedCount,
        'response_count' => $responseCount,
        'correct_count' => $correctCount,
        'incorrect_count' => $incorrectCount,
        'pending_subjective_count' => $pendingSubjectiveCount,
        'earned_marks_total' => $earnedMarksTotal,
        'objective_possible_marks' => $objectivePossibleMarks,
        'total_possible_marks' => $totalPossibleMarks,
        'accuracy_percent' => $accuracyPercent,
        'completion_percent' => $completionPercent,
        'unanswered_count' => $unansweredCount,
        'comparison' => $comparison,
        'insights' => $insights,
    ];
}
