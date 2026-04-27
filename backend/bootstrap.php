<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes_auth.php';
require_once dirname(__DIR__) . '/includes_csrf.php';

function backend_user(): array
{
    static $user = null;
    if (is_array($user)) {
        return $user;
    }

    $user = require_auth(['teacher', 'school_admin']);
    return $user;
}

function backend_is_admin(array $user): bool
{
    return ($user['role'] ?? '') === 'school_admin';
}

function backend_require_admin(array $user): void
{
    if (!backend_is_admin($user)) {
        http_response_code(403);
        echo 'Forbidden. School admin only.';
        exit;
    }
}

function backend_schema_ready(PDO $pdo): array
{
    $required = [
        'schools',
        'subjects',
        'grade_levels',
        'academic_sessions',
        'course_categories',
        'tags',
        'course_taxonomy_map',
        'test_settings',
        'question_blueprint',
        'content_metadata',
    ];

    $missing = [];
    foreach ($required as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        if ((int)$stmt->fetchColumn() === 0) {
            $missing[] = $table;
        }
    }

    return ['ready' => !$missing, 'missing' => $missing];
}
