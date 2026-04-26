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
                u.email AS student_email
         FROM test_attempts ta
         JOIN tests t ON t.id = ta.test_id
         JOIN users u ON u.id = ta.student_id
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
        if ($answer && $question['question_type'] === 'mcq' && !empty($answer['selected_option_id'])) {
            $isCorrect = !empty($answer['selected_is_correct']) && (int)$answer['selected_is_correct'] === 1;
            $selectedOptionText = $answer['selected_option_text'] ?? null;
        }

        $earnedMarks = null;
        $maxMarks = (float)$question['marks'];
        if ($question['question_type'] === 'mcq') {
            $earnedMarks = $isCorrect ? $maxMarks : 0.0;
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
            'selected_option_text' => $selectedOptionText,
            'subjective_answer' => $subjectiveAnswer,
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
    ];
}
