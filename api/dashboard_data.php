<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes_auth.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = get_pdo();

function json_result(array $data): void
{
    echo json_encode($data);
    exit;
}

function table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    $cache[$table] = (bool)$stmt->fetchColumn();
    return $cache[$table];
}

function load_community(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT cp.content, u.name
         FROM community_posts cp
         JOIN users u ON cp.user_id = u.id
         ORDER BY cp.created_at DESC
         LIMIT 5'
    );

    $feed = [];
    foreach ($stmt->fetchAll() as $row) {
        $feed[] = [
            'user' => $row['name'],
            'content' => $row['content'],
        ];
    }
    return $feed;
}

function load_recent_achievements(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT a.title, a.description
         FROM user_achievements ua
         JOIN achievements a ON ua.achievement_id = a.id
         WHERE ua.user_id = ?
         ORDER BY ua.awarded_at DESC
         LIMIT 5'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function base_response(): array
{
    return [
        'primaryChartTitle' => 'Overview',
        'secondaryChartTitle' => 'Progress',
        'primaryChart' => ['type' => 'bar', 'data' => ['labels' => [], 'datasets' => []]],
        'secondaryChart' => ['type' => 'bar', 'data' => ['labels' => [], 'datasets' => []]],
        'highlights' => [],
        'recentAchievements' => [],
        'communityFeed' => [],
        'metrics' => [],
        'widgets' => [],
    ];
}

switch ($user['role']) {
    case 'student':
        $studentId = (int)$user['sub'];

        $stmt = $pdo->prepare(
            'SELECT sa.name AS sub_name, sp.score
             FROM skill_progress sp
             JOIN sub_attributes sa ON sp.sub_attribute_id = sa.id
             WHERE sp.student_id = ?
             ORDER BY sa.name'
        );
        $stmt->execute([$studentId]);
        $skills = $stmt->fetchAll();
        $skillLabels = [];
        $skillScores = [];
        foreach ($skills as $row) {
            $skillLabels[] = $row['sub_name'];
            $skillScores[] = (float)$row['score'];
        }

        $stmt = $pdo->prepare(
            'SELECT c.id, c.title, AVG(p.completion_percentage) AS completion
             FROM progress p
             JOIN courses c ON p.course_id = c.id
             WHERE p.student_id = ?
             GROUP BY c.id, c.title
             ORDER BY c.title'
        );
        $stmt->execute([$studentId]);
        $progressRows = $stmt->fetchAll();
        $progressLabels = [];
        $progressValues = [];
        foreach ($progressRows as $row) {
            $progressLabels[] = $row['title'];
            $progressValues[] = (float)$row['completion'];
        }

        $stmt = $pdo->prepare(
            'SELECT c.id, c.title, ce.enrolled_at
             FROM course_enrollments ce
             JOIN courses c ON ce.course_id = c.id
             WHERE ce.student_id = ?
             ORDER BY ce.enrolled_at DESC
             LIMIT 5'
        );
        $stmt->execute([$studentId]);
        $activeCourses = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT t.id, t.title, t.duration_minutes
             FROM tests t
             WHERE EXISTS (SELECT 1 FROM test_questions tq WHERE tq.test_id = t.id)
               AND NOT EXISTS (
                   SELECT 1 FROM test_attempts ta WHERE ta.test_id = t.id AND ta.student_id = ?
               )
             ORDER BY t.created_at DESC
             LIMIT 5'
        );
        $stmt->execute([$studentId]);
        $upcomingTests = $stmt->fetchAll();

        $avgSkill = $skillScores ? array_sum($skillScores) / count($skillScores) : 0.0;
        $avgCourseProgress = $progressValues ? array_sum($progressValues) / count($progressValues) : 0.0;
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_achievements WHERE user_id = ?');
        $stmt->execute([$studentId]);
        $achievementCount = (int)$stmt->fetchColumn();

        $response = base_response();
        $response['primaryChartTitle'] = 'Skill radar chart';
        $response['secondaryChartTitle'] = 'Progress chart';
        $response['primaryChart'] = [
            'type' => 'radar',
            'data' => [
                'labels' => $skillLabels,
                'datasets' => [[
                    'label' => 'Skill score',
                    'data' => $skillScores,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.18)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'borderWidth' => 2,
                ]],
            ],
        ];
        $response['secondaryChart'] = [
            'type' => 'bar',
            'data' => [
                'labels' => $progressLabels,
                'datasets' => [[
                    'label' => 'Completion %',
                    'data' => $progressValues,
                    'backgroundColor' => 'rgba(75, 192, 192, 0.55)',
                ]],
            ],
        ];
        $response['highlights'] = $activeCourses || $upcomingTests
            ? [
                'Active courses: ' . count($activeCourses),
                'Upcoming tests: ' . count($upcomingTests),
            ]
            : ['Start by enrolling in a course.'];
        $response['recentAchievements'] = load_recent_achievements($pdo, $studentId);
        $response['communityFeed'] = load_community($pdo);
        $response['metrics'] = [
            ['label' => 'Active Courses', 'value' => count($activeCourses)],
            ['label' => 'Upcoming Tests', 'value' => count($upcomingTests)],
            ['label' => 'Avg Skill Score', 'value' => number_format($avgSkill, 1)],
            ['label' => 'Avg Progress %', 'value' => number_format($avgCourseProgress, 1)],
        ];
        $response['widgets'] = [
            [
                'title' => 'Active courses',
                'type' => 'list',
                'emptyText' => 'No enrolled courses yet.',
                'items' => array_map(static function (array $row): array {
                    return ['primary' => $row['title'], 'secondary' => 'Enrolled: ' . $row['enrolled_at']];
                }, $activeCourses),
            ],
            [
                'title' => 'Upcoming tests',
                'type' => 'list',
                'emptyText' => 'No pending tests.',
                'items' => array_map(static function (array $row): array {
                    return ['primary' => $row['title'], 'secondary' => ((int)$row['duration_minutes']) . ' min'];
                }, $upcomingTests),
            ],
            [
                'title' => 'Recent achievements',
                'type' => 'list',
                'emptyText' => 'No achievements yet.',
                'items' => array_map(static function (array $row): array {
                    return ['primary' => $row['title'], 'secondary' => $row['description']];
                }, $response['recentAchievements']),
            ],
        ];

        json_result($response);
        break;

    case 'parent':
        $parentId = (int)$user['sub'];
        $child = null;

        if (table_exists($pdo, 'parent_student_links')) {
            $stmt = $pdo->prepare(
                'SELECT u.id, u.name, u.grade
                 FROM parent_student_links psl
                 JOIN users u ON u.id = psl.student_id
                 WHERE psl.parent_id = ?
                 ORDER BY psl.id ASC
                 LIMIT 1'
            );
            $stmt->execute([$parentId]);
            $child = $stmt->fetch() ?: null;
        }

        $childId = $child ? (int)$child['id'] : 0;
        $attrLabels = [];
        $attrScores = [];
        $attemptRows = [];
        $avgScore = 0.0;
        $attemptCount = 0;

        if ($childId > 0) {
            $stmt = $pdo->prepare(
                'SELECT a.name, AVG(sp.score) AS score
                 FROM skill_progress sp
                 JOIN attributes a ON a.id = sp.attribute_id
                 WHERE sp.student_id = ?
                 GROUP BY a.id, a.name
                 ORDER BY a.name'
            );
            $stmt->execute([$childId]);
            foreach ($stmt->fetchAll() as $row) {
                $attrLabels[] = $row['name'];
                $attrScores[] = (float)$row['score'];
            }

            $stmt = $pdo->prepare('SELECT COALESCE(AVG(score),0), COUNT(*) FROM test_attempts WHERE student_id = ?');
            $stmt->execute([$childId]);
            $vals = $stmt->fetch(PDO::FETCH_NUM);
            if ($vals) {
                $avgScore = (float)$vals[0];
                $attemptCount = (int)$vals[1];
            }

            $stmt = $pdo->prepare(
                'SELECT ta.id, t.title, ta.score, ta.attempt_date
                 FROM test_attempts ta
                 JOIN tests t ON t.id = ta.test_id
                 WHERE ta.student_id = ?
                 ORDER BY ta.attempt_date DESC
                 LIMIT 10'
            );
            $stmt->execute([$childId]);
            $attemptRows = $stmt->fetchAll();
        }

        $response = base_response();
        $response['primaryChartTitle'] = 'Child skill trend graph';
        $response['secondaryChartTitle'] = 'Test performance';
        $response['primaryChart'] = [
            'type' => 'bar',
            'data' => [
                'labels' => $attrLabels,
                'datasets' => [[
                    'label' => 'Attribute score',
                    'data' => $attrScores,
                    'backgroundColor' => 'rgba(153, 102, 255, 0.55)',
                ]],
            ],
        ];
        $response['secondaryChart'] = [
            'type' => 'bar',
            'data' => [
                'labels' => array_map(static fn (array $row): string => (string)$row['title'], $attemptRows),
                'datasets' => [[
                    'label' => 'Test score',
                    'data' => array_map(static fn (array $row): float => (float)$row['score'], $attemptRows),
                    'backgroundColor' => 'rgba(33, 150, 243, 0.65)',
                ]],
            ],
        ];
        $response['highlights'] = [
            $child ? ('Child: ' . $child['name']) : 'No child linked yet.',
            'Average test score: ' . number_format($avgScore, 1),
            'Tests attempted: ' . $attemptCount,
            'Attribute coverage: ' . count($attrLabels) . ' areas',
        ];
        $response['recentAchievements'] = [];
        $response['communityFeed'] = [];
        $response['metrics'] = [
            ['label' => 'Child', 'value' => $child ? $child['name'] : 'Not linked'],
            ['label' => 'Avg Test Score', 'value' => number_format($avgScore, 1)],
            ['label' => 'Tests Attempted', 'value' => $attemptCount],
            ['label' => 'Attributes', 'value' => count($attrLabels)],
        ];
        $progressItems = [];
        foreach ($attrLabels as $index => $label) {
            $progressItems[] = [
                'primary' => $label,
                'secondary' => number_format((float)($attrScores[$index] ?? 0), 1),
            ];
        }
        $response['widgets'] = [
            [
                'title' => 'Progress summary',
                'type' => 'list',
                'emptyText' => 'No progress data.',
                'items' => $progressItems,
            ],
            [
                'title' => 'Recent tests',
                'type' => 'list',
                'emptyText' => 'No test attempts yet.',
                'items' => array_map(static function (array $row): array {
                    return [
                        'primary' => $row['title'],
                        'secondary' => number_format((float)$row['score'], 1) . ' · ' . $row['attempt_date'],
                    ];
                }, $attemptRows),
            ],
        ];
        $response['hideSections'] = ['community-panel', 'achievements-panel'];

        json_result($response);
        break;

    case 'teacher':
        $teacherId = (int)$user['sub'];

        $stmt = $pdo->prepare(
            'SELECT t.id, t.title, AVG(ta.score) AS avg_score, COUNT(ta.id) AS attempts
             FROM tests t
             LEFT JOIN test_attempts ta ON ta.test_id = t.id
             WHERE t.created_by = ?
             GROUP BY t.id, t.title, t.created_at
             ORDER BY t.created_at DESC
             LIMIT 10'
        );
        $stmt->execute([$teacherId]);
        $testRows = $stmt->fetchAll();
        $testLabels = [];
        $testAverages = [];
        foreach ($testRows as $row) {
            $testLabels[] = $row['title'];
            $testAverages[] = (float)$row['avg_score'];
        }

        $stmt = $pdo->prepare(
            'SELECT c.id, c.title, AVG(p.completion_percentage) AS completion
             FROM courses c
             LEFT JOIN progress p ON p.course_id = c.id
             WHERE c.teacher_id = ?
             GROUP BY c.id, c.title
             ORDER BY c.title'
        );
        $stmt->execute([$teacherId]);
        $courseRows = $stmt->fetchAll();
        $courseLabels = [];
        $courseCompletion = [];
        foreach ($courseRows as $row) {
            $courseLabels[] = $row['title'];
            $courseCompletion[] = (float)$row['completion'];
        }

        $stmt = $pdo->prepare(
            'SELECT u.name, AVG(ta.score) AS avg_score, COUNT(ta.id) AS attempts
             FROM tests t
             JOIN test_attempts ta ON ta.test_id = t.id
             JOIN users u ON u.id = ta.student_id
             WHERE t.created_by = ?
             GROUP BY ta.student_id, u.name
             ORDER BY avg_score DESC, attempts DESC, u.name ASC
             LIMIT 10'
        );
        $stmt->execute([$teacherId]);
        $rankingRows = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE teacher_id = ?');
        $stmt->execute([$teacherId]);
        $teacherCourseCount = (int)$stmt->fetchColumn();

        $response = base_response();
        $response['primaryChartTitle'] = 'Class performance chart';
        $response['secondaryChartTitle'] = 'Course completion stats';
        $response['primaryChart'] = [
            'type' => 'bar',
            'data' => [
                'labels' => $testLabels,
                'datasets' => [[
                    'label' => 'Test analytics (avg score)',
                    'data' => $testAverages,
                    'backgroundColor' => 'rgba(255, 159, 64, 0.65)',
                ]],
            ],
        ];
        $response['secondaryChart'] = [
            'type' => 'bar',
            'data' => [
                'labels' => $courseLabels,
                'datasets' => [[
                    'label' => 'Completion %',
                    'data' => $courseCompletion,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.65)',
                ]],
            ],
        ];
        $response['highlights'] = [
            'Class performance chart and test analytics loaded.',
            'Course completion stats available for ' . count($courseRows) . ' courses.',
            'Student ranking list includes top performers.',
        ];
        $response['recentAchievements'] = load_recent_achievements($pdo, $teacherId);
        $response['communityFeed'] = load_community($pdo);
        $response['metrics'] = [
            ['label' => 'Courses', 'value' => $teacherCourseCount],
            ['label' => 'Tests', 'value' => count($testRows)],
            ['label' => 'Ranked Students', 'value' => count($rankingRows)],
            ['label' => 'Community Posts', 'value' => (int)$pdo->query('SELECT COUNT(*) FROM community_posts')->fetchColumn()],
        ];
        $response['widgets'] = [
            [
                'title' => 'Student ranking',
                'type' => 'list',
                'emptyText' => 'No student test attempts yet.',
                'items' => array_map(static function (array $row): array {
                    return [
                        'primary' => $row['name'],
                        'secondary' => 'Avg ' . number_format((float)$row['avg_score'], 1) . ' (' . (int)$row['attempts'] . ' attempts)',
                    ];
                }, $rankingRows),
            ],
            [
                'title' => 'Test analytics',
                'type' => 'list',
                'emptyText' => 'No tests created yet.',
                'items' => array_map(static function (array $row): array {
                    return [
                        'primary' => $row['title'],
                        'secondary' => 'Avg ' . number_format((float)$row['avg_score'], 1) . ', attempts ' . (int)$row['attempts'],
                    ];
                }, $testRows),
            ],
        ];

        json_result($response);
        break;

    case 'content_admin':
    case 'super_admin':
        $totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
        $courseCount = (int)$pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
        $testCount = (int)$pdo->query('SELECT COUNT(*) FROM tests')->fetchColumn();
        $attemptCount = (int)$pdo->query('SELECT COUNT(*) FROM test_attempts')->fetchColumn();
        $studentCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();

        $stmt = $pdo->query(
            'SELECT a.name, AVG(sp.score) AS avg_score
             FROM attributes a
             LEFT JOIN skill_progress sp ON sp.attribute_id = a.id
             GROUP BY a.id, a.name
             ORDER BY a.name'
        );
        $skillRows = $stmt->fetchAll();
        $skillLabels = [];
        $skillValues = [];
        foreach ($skillRows as $row) {
            $skillLabels[] = $row['name'];
            $skillValues[] = (float)$row['avg_score'];
        }

        $response = base_response();
        $response['primaryChartTitle'] = 'Skill distribution';
        $response['secondaryChartTitle'] = 'Platform activity';
        $response['primaryChart'] = [
            'type' => 'radar',
            'data' => [
                'labels' => $skillLabels,
                'datasets' => [[
                    'label' => 'Avg skill score',
                    'data' => $skillValues,
                    'backgroundColor' => 'rgba(0, 150, 136, 0.2)',
                    'borderColor' => 'rgba(0, 150, 136, 1)',
                    'borderWidth' => 2,
                ]],
            ],
        ];
        $response['secondaryChart'] = [
            'type' => 'bar',
            'data' => [
                'labels' => ['Students', 'Courses', 'Tests', 'Attempts'],
                'datasets' => [[
                    'label' => 'Counts',
                    'data' => [$studentCount, $courseCount, $testCount, $attemptCount],
                    'backgroundColor' => [
                        'rgba(33,150,243,0.65)',
                        'rgba(76,175,80,0.65)',
                        'rgba(255,193,7,0.65)',
                        'rgba(156,39,176,0.65)',
                    ],
                ]],
            ],
        ];
        $response['highlights'] = [
            'Total users: ' . $totalUsers,
            'Active users: ' . $activeUsers,
            'Course stats: ' . $courseCount . ' courses / ' . $testCount . ' tests',
            'Attempt volume: ' . $attemptCount . ' attempts',
        ];
        $response['recentAchievements'] = [];
        $response['communityFeed'] = load_community($pdo);
        $response['metrics'] = [
            ['label' => 'Total Users', 'value' => $totalUsers],
            ['label' => 'Active Users', 'value' => $activeUsers],
            ['label' => 'Courses', 'value' => $courseCount],
            ['label' => 'Tests', 'value' => $testCount],
        ];
        $response['widgets'] = [
            [
                'title' => 'Platform activity',
                'type' => 'list',
                'items' => [
                    ['primary' => 'Students', 'secondary' => (string)$studentCount],
                    ['primary' => 'Course records', 'secondary' => (string)$courseCount],
                    ['primary' => 'Test records', 'secondary' => (string)$testCount],
                    ['primary' => 'Attempts', 'secondary' => (string)$attemptCount],
                ],
                'emptyText' => 'No platform data available.',
            ],
        ];

        json_result($response);
        break;

    case 'school_admin':
    default:
        $schoolId = (int)($user['school_id'] ?? 0);
        $schoolName = '';
        $students = [];
        $gradeRows = [];
        $attemptRows = [];
        $totalStudents = 0;
        $avgScore = 0.0;
        $attemptCount = 0;

        if ($schoolId > 0) {
            $stmt = $pdo->prepare('SELECT name FROM schools WHERE id = ?');
            $stmt->execute([$schoolId]);
            $schoolName = (string)($stmt->fetchColumn() ?: '');

            $stmt = $pdo->prepare(
                'SELECT id, name, grade
                 FROM users
                 WHERE role = "student" AND school_id = ?
                 ORDER BY grade ASC, name ASC'
            );
            $stmt->execute([$schoolId]);
            $students = $stmt->fetchAll();
            $totalStudents = count($students);

            $stmt = $pdo->prepare(
                'SELECT COALESCE(u.grade, "Unassigned") AS grade_label,
                        COALESCE(AVG(ta.score), 0) AS avg_score,
                        COUNT(DISTINCT u.id) AS student_count
                 FROM users u
                 LEFT JOIN test_attempts ta ON ta.student_id = u.id
                 WHERE u.role = "student" AND u.school_id = ?
                 GROUP BY COALESCE(u.grade, "Unassigned")
                 ORDER BY grade_label ASC'
            );
            $stmt->execute([$schoolId]);
            $gradeRows = $stmt->fetchAll();

            $stmt = $pdo->prepare('SELECT COALESCE(AVG(score),0), COUNT(*) FROM test_attempts ta JOIN users u ON u.id = ta.student_id WHERE u.school_id = ? AND u.role = "student"');
            $stmt->execute([$schoolId]);
            $vals = $stmt->fetch(PDO::FETCH_NUM);
            if ($vals) {
                $avgScore = (float)$vals[0];
                $attemptCount = (int)$vals[1];
            }

            $stmt = $pdo->prepare(
                'SELECT u.id, u.name, u.grade, COALESCE(AVG(ta.score), 0) AS avg_score, COUNT(ta.id) AS attempts
                 FROM users u
                 LEFT JOIN test_attempts ta ON ta.student_id = u.id
                 WHERE u.role = "student" AND u.school_id = ?
                 GROUP BY u.id, u.name, u.grade
                 ORDER BY avg_score DESC, attempts DESC, u.name ASC
                 LIMIT 15'
            );
            $stmt->execute([$schoolId]);
            $attemptRows = $stmt->fetchAll();
        }

        $response = base_response();
        $response['primaryChartTitle'] = 'Class-wise progress';
        $response['secondaryChartTitle'] = 'Student performance';
        $response['primaryChart'] = [
            'type' => 'bar',
            'data' => [
                'labels' => array_map(static fn (array $row): string => (string)$row['grade_label'], $gradeRows),
                'datasets' => [[
                    'label' => 'Average score',
                    'data' => array_map(static fn (array $row): float => (float)$row['avg_score'], $gradeRows),
                    'backgroundColor' => 'rgba(76, 175, 80, 0.65)',
                ]],
            ],
        ];
        $response['secondaryChart'] = [
            'type' => 'bar',
            'data' => [
                'labels' => array_map(static fn (array $row): string => (string)$row['name'], $attemptRows),
                'datasets' => [[
                    'label' => 'Avg score',
                    'data' => array_map(static fn (array $row): float => (float)$row['avg_score'], $attemptRows),
                    'backgroundColor' => 'rgba(33, 150, 243, 0.65)',
                ]],
            ],
        ];
        $response['highlights'] = [
            $schoolId > 0 ? ('School dashboard: ' . ($schoolName !== '' ? $schoolName : ('#' . $schoolId))) : 'No school linked to this account.',
            'Students in school: ' . $totalStudents,
            'Average test score: ' . number_format($avgScore, 1),
            'Tests attempted: ' . $attemptCount,
        ];
        $response['recentAchievements'] = [];
        $response['communityFeed'] = [];
        $response['metrics'] = [
            ['label' => 'Students', 'value' => $totalStudents],
            ['label' => 'Avg Test Score', 'value' => number_format($avgScore, 1)],
            ['label' => 'Tests Attempted', 'value' => $attemptCount],
            ['label' => 'Grades', 'value' => count($gradeRows)],
        ];
        $response['widgets'] = [
            [
                'title' => 'Class progress',
                'type' => 'list',
                'emptyText' => 'No class data available.',
                'items' => array_map(static function (array $row): array {
                    return [
                        'primary' => (string)$row['grade_label'],
                        'secondary' => number_format((float)$row['avg_score'], 1) . ' avg · ' . (int)$row['student_count'] . ' students',
                    ];
                }, $gradeRows),
            ],
            [
                'title' => 'Student reports',
                'type' => 'list',
                'emptyText' => 'No students found.',
                'items' => array_map(static function (array $row): array {
                    return [
                        'primary' => $row['name'],
                        'secondary' => 'Grade ' . ($row['grade'] ?: 'Unassigned') . ' · Avg ' . number_format((float)$row['avg_score'], 1) . ' · ' . (int)$row['attempts'] . ' tests',
                        'link' => url_for('student_report.php?student_id=' . (int)$row['id']),
                        'link_label' => 'Open report',
                    ];
                }, $attemptRows),
            ],
        ];
        $response['hideSections'] = ['community-panel', 'achievements-panel'];

        json_result($response);
        break;
}
