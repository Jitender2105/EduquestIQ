<?php
// EduquestIQ - Skill progress helpers

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes_achievements.php';

/**
 * Update skill_progress rows based on a completed test attempt.
 * Uses question_attribute_mapping weights and correctness.
 */
function update_skill_progress_from_test(int $attemptId): void
{
    $pdo = get_pdo();

    // Fetch attempt details
    $stmt = $pdo->prepare('SELECT student_id, test_id FROM test_attempts WHERE id = ?');
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch();
    if (!$attempt) {
        return;
    }
    $studentId = (int)$attempt['student_id'];

    // For each answered question, join its attribute mappings and option correctness
    $stmt = $pdo->prepare(
        'SELECT 
            ta.question_id,
            qo.is_correct,
            qam.attribute_id,
            qam.sub_attribute_id,
            qam.weight
         FROM test_answers ta
         JOIN question_attribute_mapping qam ON ta.question_id = qam.question_id
         LEFT JOIN question_options qo ON ta.selected_option_id = qo.id
         WHERE ta.attempt_id = ?'
    );
    $stmt->execute([$attemptId]);
    $rows = $stmt->fetchAll();

    if (!$rows) {
        return;
    }

    // Aggregate weighted correctness per attribute/sub-attribute
    $totals = []; // [attr][sub] = ['w_total' => ..., 'w_correct' => ...]
    foreach ($rows as $row) {
        $attr = (int)$row['attribute_id'];
        $sub = (int)$row['sub_attribute_id'];
        $weight = (float)$row['weight'];
        if ($weight <= 0) {
            continue;
        }

        if (!isset($totals[$attr])) {
            $totals[$attr] = [];
        }
        if (!isset($totals[$attr][$sub])) {
            $totals[$attr][$sub] = ['w_total' => 0.0, 'w_correct' => 0.0];
        }

        $totals[$attr][$sub]['w_total'] += $weight;
        if (!is_null($row['is_correct']) && (int)$row['is_correct'] === 1) {
            $totals[$attr][$sub]['w_correct'] += $weight;
        }
    }

    foreach ($totals as $attrId => $subs) {
        foreach ($subs as $subId => $agg) {
            if ($agg['w_total'] <= 0) {
                continue;
            }
            $percent = ($agg['w_correct'] / $agg['w_total']) * 100.0;

            // Insert or update moving average score
            $stmt = $pdo->prepare(
                'INSERT INTO skill_progress (student_id, attribute_id, sub_attribute_id, score, updated_at)
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE score = (score + VALUES(score)) / 2, updated_at = NOW()'
            );
            $stmt->execute([$studentId, $attrId, $subId, $percent]);
        }
    }

    // Recalculate achievements after skill update
    evaluate_achievements_for_user($studentId);
}

/**
 * Update skill_progress based on overall course completion percentage.
 * Writes to the "Overall" sub-attribute for the course's primary attribute.
 */
function update_skill_progress_from_course_completion(int $studentId, int $courseId): void
{
    $pdo = get_pdo();

    // Get course attribute
    $stmt = $pdo->prepare('SELECT attribute_id FROM courses WHERE id = ?');
    $stmt->execute([$courseId]);
    $attrId = (int)$stmt->fetchColumn();
    if (!$attrId) {
        return;
    }

    // Find or assume "Overall" sub-attribute for this attribute
    $stmt = $pdo->prepare(
        'SELECT id FROM sub_attributes WHERE attribute_id = ? AND name = "Overall" LIMIT 1'
    );
    $stmt->execute([$attrId]);
    $subId = (int)$stmt->fetchColumn();
    if (!$subId) {
        return;
    }

    // Calculate overall course completion for this student
    $stmt = $pdo->prepare(
        'SELECT COALESCE(AVG(completion_percentage), 0) 
         FROM progress 
         WHERE student_id = ? AND course_id = ?'
    );
    $stmt->execute([$studentId, $courseId]);
    $completion = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'INSERT INTO skill_progress (student_id, attribute_id, sub_attribute_id, score, updated_at)
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE score = GREATEST(score, VALUES(score)), updated_at = NOW()'
    );
    $stmt->execute([$studentId, $attrId, $subId, $completion]);

    evaluate_achievements_for_user($studentId);
}

