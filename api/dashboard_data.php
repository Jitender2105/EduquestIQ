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
            [
                'title' => 'Learning mode',
                'type' => 'text',
                'content' => 'You can follow structured learning paths or continue self-paced and resume anytime. Achievements unlocked: ' . $achievementCount,
            ],
        ];

        json_result($response);
        break;

    case 'parent':
        $parentId = (int)$user['sub'];
        $child = null;

        if (table_exists($pdo, 'parent_student_links')) {
            $stmt = $pdo->prepare(
                'SELECT u.id, u.name
                 FROM parent_student_links psl
                 JOIN users u ON u.id = psl.student_id
                 WHERE psl.parent_id = ?
                 ORDER BY psl.id ASC
                 LIMIT 1'
            );
            $stmt->execute([$parentId]);
            $child = $stmt->fetch() ?: null;
        }

        if (!$child) {
            $stmt = $pdo->prepare(
                'SELECT id, name
                 FROM users
                 WHERE role = "student" AND school_id = ?
                 ORDER BY id ASC
                 LIMIT 1'
            );
            $stmt->execute([$user['school_id'] ?? null]);
            $child = $stmt->fetch() ?: null;
        }

        $childId = $child ? (int)$child['id'] : 0;

        $attrLabels = [];
        $attrScores = [];
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
        }

        $avgScore = 0.0;
        $attemptCount = 0;
        if ($childId > 0) {
            $stmt = $pdo->prepare('SELECT COALESCE(AVG(score),0), COUNT(*) FROM test_attempts WHERE student_id = ?');
            $stmt->execute([$childId]);
            $vals = $stmt->fetch(PDO::FETCH_NUM);
            if ($vals) {
                $avgScore = (float)$vals[0];
                $attemptCount = (int)$vals[1];
            }
        }

        $attendancePresent = 0;
        $attendanceAbsent = 0;
        $attendanceLate = 0;
        if ($childId > 0) {
            $stmt = $pdo->prepare(
                'SELECT
                    COALESCE(SUM(status = "present"), 0) AS present_days,
                    COALESCE(SUM(status = "absent"), 0) AS absent_days,
                    COALESCE(SUM(status = "late"), 0) AS late_days
                 FROM attendance
                 WHERE student_id = ?'
            );
            $stmt->execute([$childId]);
            $row = $stmt->fetch();
            if ($row) {
                $attendancePresent = (int)$row['present_days'];
                $attendanceAbsent = (int)$row['absent_days'];
                $attendanceLate = (int)$row['late_days'];
            }
        }

        $feedbackItems = [];
        if ($childId > 0 && table_exists($pdo, 'teacher_feedback')) {
            $stmt = $pdo->prepare(
                'SELECT tf.feedback_text, tf.created_at, u.name AS teacher_name
                 FROM teacher_feedback tf
                 JOIN users u ON u.id = tf.teacher_id
                 WHERE tf.student_id = ?
                 ORDER BY tf.created_at DESC
                 LIMIT 5'
            );
            $stmt->execute([$childId]);
            foreach ($stmt->fetchAll() as $row) {
                $feedbackItems[] = [
                    'primary' => $row['teacher_name'],
                    'secondary' => $row['feedback_text'] . ' (' . $row['created_at'] . ')',
                ];
            }
        }

        $response = base_response();
        $response['primaryChartTitle'] = 'Child skill trend graph';
        $response['secondaryChartTitle'] = 'Attendance summary';
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
            'type' => 'doughnut',
            'data' => [
                'labels' => ['Present', 'Absent', 'Late'],
                'datasets' => [[
                    'data' => [$attendancePresent, $attendanceAbsent, $attendanceLate],
                    'backgroundColor' => ['#4caf50', '#f44336', '#ff9800'],
                ]],
            ],
        ];
        $response['highlights'] = [
            $child ? ('Child: ' . $child['name']) : 'No child linked yet.',
            'Performance summary: Avg test score ' . number_format($avgScore, 1),
            'Attendance summary: Present ' . $attendancePresent . ', Absent ' . $attendanceAbsent . ', Late ' . $attendanceLate,
            'Teacher feedback entries: ' . count($feedbackItems),
        ];
        $response['recentAchievements'] = $childId ? load_recent_achievements($pdo, $childId) : [];
        $response['communityFeed'] = load_community($pdo);
        $response['metrics'] = [
            ['label' => 'Child', 'value' => $child ? $child['name'] : 'Not linked'],
            ['label' => 'Avg Test Score', 'value' => number_format($avgScore, 1)],
            ['label' => 'Tests Attempted', 'value' => $attemptCount],
            ['label' => 'Attendance Days', 'value' => $attendancePresent + $attendanceAbsent + $attendanceLate],
        ];
        $response['widgets'] = [
            [
                'title' => 'Performance summary',
                'type' => 'list',
                'emptyText' => 'No performance data.',
                'items' => $childId ? [
                    ['primary' => 'Average test score', 'secondary' => number_format($avgScore, 1)],
                    ['primary' => 'Test attempts', 'secondary' => (string)$attemptCount],
                ] : [],
            ],
            [
                'title' => 'Attendance summary',
                'type' => 'list',
                'emptyText' => 'No attendance records.',
                'items' => $childId ? [
                    ['primary' => 'Present', 'secondary' => (string)$attendancePresent],
                    ['primary' => 'Absent', 'secondary' => (string)$attendanceAbsent],
                    ['primary' => 'Late', 'secondary' => (string)$attendanceLate],
                ] : [],
            ],
            [
                'title' => 'Teacher feedback',
                'type' => 'list',
                'emptyText' => 'No teacher feedback yet.',
                'items' => $feedbackItems,
            ],
        ];

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

    case 'school_admin':
    default:
        $totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
        $courseCount = (int)$pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
        $enrollmentCount = (int)$pdo->query('SELECT COUNT(*) FROM course_enrollments')->fetchColumn();

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

        $posts = (int)$pdo->query('SELECT COUNT(*) FROM community_posts')->fetchColumn();
        $comments = (int)$pdo->query('SELECT COUNT(*) FROM post_comments')->fetchColumn();
        $likes = (int)$pdo->query('SELECT COUNT(*) FROM post_likes')->fetchColumn();
        $attempts = (int)$pdo->query('SELECT COUNT(*) FROM test_attempts')->fetchColumn();
        $progressUpdates = (int)$pdo->query('SELECT COUNT(*) FROM progress')->fetchColumn();

        $response = base_response();
        $response['primaryChartTitle'] = 'Skill distribution';
        $response['secondaryChartTitle'] = 'Engagement metrics';
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
                'labels' => ['Posts', 'Comments', 'Likes', 'Test attempts', 'Progress updates'],
                'datasets' => [[
                    'label' => 'Engagement',
                    'data' => [$posts, $comments, $likes, $attempts, $progressUpdates],
                    'backgroundColor' => [
                        'rgba(33,150,243,0.65)',
                        'rgba(76,175,80,0.65)',
                        'rgba(255,193,7,0.65)',
                        'rgba(156,39,176,0.65)',
                        'rgba(255,87,34,0.65)',
                    ],
                ]],
            ],
        ];
        $response['highlights'] = [
            'Total users: ' . $totalUsers,
            'Active users: ' . $activeUsers,
            'Course stats: ' . $courseCount . ' courses / ' . $enrollmentCount . ' enrollments',
            'Engagement metrics loaded for community, tests, and progress',
        ];
        $response['recentAchievements'] = load_recent_achievements($pdo, (int)$user['sub']);
        $response['communityFeed'] = load_community($pdo);
        $response['metrics'] = [
            ['label' => 'Total Users', 'value' => $totalUsers],
            ['label' => 'Active Users', 'value' => $activeUsers],
            ['label' => 'Courses', 'value' => $courseCount],
            ['label' => 'Enrollments', 'value' => $enrollmentCount],
        ];
        $response['widgets'] = [
            [
                'title' => 'Course stats',
                'type' => 'list',
                'items' => [
                    ['primary' => 'Courses', 'secondary' => (string)$courseCount],
                    ['primary' => 'Enrollments', 'secondary' => (string)$enrollmentCount],
                    ['primary' => 'Tests attempted', 'secondary' => (string)$attempts],
                ],
                'emptyText' => 'No course stats available.',
            ],
            [
                'title' => 'Engagement metrics',
                'type' => 'list',
                'items' => [
                    ['primary' => 'Community posts', 'secondary' => (string)$posts],
                    ['primary' => 'Comments', 'secondary' => (string)$comments],
                    ['primary' => 'Likes', 'secondary' => (string)$likes],
                    ['primary' => 'Progress updates', 'secondary' => (string)$progressUpdates],
                ],
                'emptyText' => 'No engagement data.',
            ],
        ];

        json_result($response);
        break;
}
