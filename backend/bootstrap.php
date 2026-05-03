<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes_auth.php';
require_once dirname(__DIR__) . '/includes_csrf.php';

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: 0');
}

function backend_user(): array
{
    static $user = null;
    if (is_array($user)) {
        return $user;
    }

    $user = require_auth(['content_admin', 'super_admin']);
    return $user;
}

function backend_is_admin(array $user): bool
{
    return in_array(($user['role'] ?? ''), ['content_admin', 'super_admin'], true);
}

function backend_is_super_admin(array $user): bool
{
    return ($user['role'] ?? '') === 'super_admin';
}

function backend_can_edit(array $user): bool
{
    return backend_is_super_admin($user);
}

function backend_is_read_only(array $user): bool
{
    return backend_is_admin($user) && !backend_can_edit($user);
}

function backend_require_admin(array $user): void
{
    if (!backend_is_admin($user)) {
        http_response_code(403);
        echo 'Forbidden. Content admin only.';
        exit;
    }

    if (backend_is_super_admin($user)) {
        return;
    }

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        http_response_code(403);
        echo 'Forbidden. Super admin only for changes.';
        exit;
    }
}

function backend_readonly_notice(array $user, string $scope = 'this backend module'): void
{
    if (!backend_is_read_only($user)) {
        return;
    }
    echo '<div class="alert alert-warning">Read-only access enabled. Only super admins can make changes in ' . htmlspecialchars($scope) . '.</div>';
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
        'articles',
        'article_faqs',
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

$GLOBALS['backendReadOnly'] = backend_is_read_only(backend_user());
