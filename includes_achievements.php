<?php
// EduquestIQ - Achievement evaluation helpers

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function evaluate_achievements_for_user(int $userId): void
{
    $pdo = get_pdo();

    // Aggregate metrics used by criteria
    $stmt = $pdo->prepare('SELECT COALESCE(AVG(score), 0) AS avg_score FROM test_attempts WHERE student_id = ?');
    $stmt->execute([$userId]);
    $avgScore = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT course_id) 
         FROM progress 
         WHERE student_id = ? AND completion_percentage >= 100'
    );
    $stmt->execute([$userId]);
    $completedCourses = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT 
            (SELECT COUNT(*) FROM community_posts WHERE user_id = ?) +
            (SELECT COUNT(*) FROM post_comments WHERE user_id = ?) +
            (SELECT COUNT(*) FROM post_likes WHERE user_id = ?) AS activity_count'
    );
    $stmt->execute([$userId, $userId, $userId]);
    $activityCount = (int)$stmt->fetchColumn();

    // Evaluate against all defined achievements
    $achievements = $pdo->query('SELECT id, criteria_type, criteria_value FROM achievements')->fetchAll();

    foreach ($achievements as $ach) {
        $meets = false;
        switch ($ach['criteria_type']) {
            case 'score':
                $meets = $avgScore >= (int)$ach['criteria_value'];
                break;
            case 'course_completion':
                $meets = $completedCourses >= (int)$ach['criteria_value'];
                break;
            case 'activity':
                $meets = $activityCount >= (int)$ach['criteria_value'];
                break;
        }

        if (!$meets) {
            continue;
        }

        // Avoid duplicates
        $stmt = $pdo->prepare(
            'SELECT 1 FROM user_achievements WHERE user_id = ? AND achievement_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $ach['id']]);
        if ($stmt->fetch()) {
            continue;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO user_achievements (user_id, achievement_id, awarded_at) VALUES (?, ?, NOW())'
        );
        $stmt->execute([$userId, $ach['id']]);
    }
}

