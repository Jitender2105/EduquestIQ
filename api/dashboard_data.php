<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes_auth.php';
require_once __DIR__ . '/../includes_sira.php';

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

function average_from_rows(array $rows, string $key): float
{
    if (!$rows) {
        return 0.0;
    }

    $values = array_map(static fn (array $row): float => (float)($row[$key] ?? 0), $rows);
    $values = array_filter($values, static fn (float $value): bool => $value > 0 || $value === 0.0);
    if (!$values) {
        return 0.0;
    }

    return array_sum($values) / count($values);
}

function suggestion_paragraphs_for_scores(array $rows, string $subject, string $studentName = ''): array
{
    $paragraphs = [];
    foreach (array_slice($rows, 0, 3) as $row) {
        $label = (string)($row['name'] ?? $row['attribute_name'] ?? $row['sub_name'] ?? $subject);
        $paragraphs[] = sira_attribute_message($label, (float)($row['score'] ?? 0), $studentName);
    }
    if (!$paragraphs) {
        $paragraphs[] = $subject . ' insights will appear once enough learning data is available.';
    }
    return $paragraphs;
}

switch ($user['role']) {
    case 'student':
        $studentId = (int)$user['sub'];
        $studentInfoStmt = $pdo->prepare(
            'SELECT u.id, u.name, u.grade, u.school_id, s.name AS school_name, s.state AS school_state
             FROM users u
             LEFT JOIN schools s ON s.id = u.school_id
             WHERE u.id = ?'
        );
        $studentInfoStmt->execute([$studentId]);
        $studentInfo = $studentInfoStmt->fetch() ?: ['name' => $user['name'], 'grade' => null, 'school_id' => null, 'school_name' => null, 'school_state' => null];
        $studentGrade = trim((string)($studentInfo['grade'] ?? ''));
        $studentSchoolId = (int)($studentInfo['school_id'] ?? 0);
        $studentSchoolState = trim((string)($studentInfo['school_state'] ?? ''));

        $stmt = $pdo->prepare(
            'SELECT t.title, ta.score, ta.attempt_date
             FROM test_attempts ta
             JOIN tests t ON t.id = ta.test_id
             WHERE ta.student_id = ?
             ORDER BY ta.attempt_date DESC
             LIMIT 5'
        );
        $stmt->execute([$studentId]);
        $recentTests = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT a.name AS attribute_name, COALESCE(AVG(sp.score), 0) AS score
             FROM attributes a
             LEFT JOIN skill_progress sp ON sp.attribute_id = a.id AND sp.student_id = ?
             GROUP BY a.id, a.name
             ORDER BY a.name'
        );
        $stmt->execute([$studentId]);
        $attributeRows = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT sa.name AS sub_name, COALESCE(sp.score, 0) AS score, a.name AS attribute_name
             FROM sub_attributes sa
             JOIN attributes a ON a.id = sa.attribute_id
             LEFT JOIN skill_progress sp ON sp.sub_attribute_id = sa.id AND sp.student_id = ?
             ORDER BY a.name, sa.name'
        );
        $stmt->execute([$studentId]);
        $subAttributeRows = $stmt->fetchAll();

        $avgTestScore = 0.0;
        if ($recentTests) {
            $avgTestScore = array_sum(array_map(static fn (array $row): float => (float)$row['score'], $recentTests)) / count($recentTests);
        } else {
            $stmt = $pdo->prepare('SELECT COALESCE(AVG(score), 0) FROM test_attempts WHERE student_id = ?');
            $stmt->execute([$studentId]);
            $avgTestScore = (float)$stmt->fetchColumn();
        }

        $avgAttributeScore = average_from_rows($attributeRows, 'score');
        $avgSubAttributeScore = average_from_rows($subAttributeRows, 'score');

        $sameSchoolClassAvg = 0.0;
        $sameStateClassAvg = 0.0;
        $sameClassPeers = [];
        $sameStatePeers = [];
        if ($studentGrade !== '') {
            $peerStmt = $pdo->prepare(
                'SELECT u.name, COALESCE(AVG(ta.score), 0) AS score
                 FROM users u
                 LEFT JOIN test_attempts ta ON ta.student_id = u.id
                 WHERE u.role = "student" AND u.school_id = ? AND u.grade = ? AND u.id <> ?
                 GROUP BY u.id, u.name
                 ORDER BY score DESC, u.name ASC
                 LIMIT 10'
            );
            $peerStmt->execute([$studentSchoolId, $studentGrade, $studentId]);
            $sameClassPeers = $peerStmt->fetchAll();

            $sameClassStmt = $pdo->prepare(
                'SELECT COALESCE(AVG(ta.score), 0)
                 FROM users u
                 LEFT JOIN test_attempts ta ON ta.student_id = u.id
                 WHERE u.role = "student" AND u.school_id = ? AND u.grade = ?'
            );
            $sameClassStmt->execute([$studentSchoolId, $studentGrade]);
            $sameSchoolClassAvg = (float)$sameClassStmt->fetchColumn();

            if ($studentSchoolState !== '') {
                $stateStmt = $pdo->prepare(
                    'SELECT u.name, s.name AS school_name, COALESCE(AVG(ta.score), 0) AS score
                     FROM users u
                     LEFT JOIN schools s ON s.id = u.school_id
                     LEFT JOIN test_attempts ta ON ta.student_id = u.id
                     WHERE u.role = "student" AND u.grade = ? AND s.state = ? AND (u.school_id IS NULL OR u.school_id <> ?)
                     GROUP BY u.id, u.name, s.name
                     ORDER BY score DESC, u.name ASC
                     LIMIT 10'
                );
                $stateStmt->execute([$studentGrade, $studentSchoolState, $studentSchoolId]);
                $sameStatePeers = $stateStmt->fetchAll();

                $sameStateStmt = $pdo->prepare(
                    'SELECT COALESCE(AVG(ta.score), 0)
                     FROM users u
                     LEFT JOIN schools s ON s.id = u.school_id
                     LEFT JOIN test_attempts ta ON ta.student_id = u.id
                     WHERE u.role = "student" AND u.grade = ? AND s.state = ? AND (u.school_id IS NULL OR u.school_id <> ?)'
                );
                $sameStateStmt->execute([$studentGrade, $studentSchoolState, $studentSchoolId]);
                $sameStateClassAvg = (float)$sameStateStmt->fetchColumn();
            }
        }

        $comparisonMetrics = [
            ['label' => 'My average', 'value' => number_format($avgTestScore, 1)],
            ['label' => 'Same school / same class', 'value' => number_format($sameSchoolClassAvg, 1)],
            ['label' => 'Same state / same class', 'value' => number_format($sameStateClassAvg, 1)],
        ];

        $response = base_response();
        $response['primaryChartTitle'] = 'Test wise score - last 5 tests';
        $response['secondaryChartTitle'] = 'Attribute and class comparison';
        $response['primaryChart'] = [
            'type' => 'line',
            'data' => [
                'labels' => array_map(static fn (array $row): string => (string)$row['title'], array_reverse($recentTests)),
                'datasets' => [[
                    'label' => 'Test score',
                    'data' => array_map(static fn (array $row): float => (float)$row['score'], array_reverse($recentTests)),
                    'borderColor' => 'rgba(67, 116, 255, 1)',
                    'backgroundColor' => 'rgba(67, 116, 255, 0.18)',
                    'tension' => 0.35,
                    'fill' => true,
                ]],
            ],
        ];
        $response['secondaryChart'] = [
            'type' => 'bar',
            'data' => [
                'labels' => ['My Avg', 'Same School / Class', 'Same State / Class'],
                'datasets' => [[
                    'label' => 'Score comparison',
                    'data' => [$avgTestScore, $sameSchoolClassAvg, $sameStateClassAvg],
                    'backgroundColor' => ['rgba(67,116,255,0.65)', 'rgba(76,175,80,0.65)', 'rgba(255,193,7,0.65)'],
                ]],
            ],
        ];
        $response['highlights'] = [
            'Current class: ' . ($studentGrade !== '' ? $studentGrade : 'Unassigned'),
            'Recent tests tracked: ' . count($recentTests),
            'Average attribute score: ' . number_format($avgAttributeScore, 1),
            'Average sub-attribute score: ' . number_format($avgSubAttributeScore, 1),
        ];
        $response['recentAchievements'] = load_recent_achievements($pdo, $studentId);
        $response['communityFeed'] = load_community($pdo);
        $response['metrics'] = [
            ['label' => 'Last 5 Avg', 'value' => number_format($avgTestScore, 1)],
            ['label' => 'Attribute Avg', 'value' => number_format($avgAttributeScore, 1)],
            ['label' => 'Sub-Attr Avg', 'value' => number_format($avgSubAttributeScore, 1)],
            ['label' => 'Tests', 'value' => count($recentTests)],
        ];
        $response['widgets'] = [
            [
                'title' => 'Last 5 test scores',
                'type' => 'list',
                'emptyText' => 'No test attempts yet.',
                'items' => array_map(static function (array $row): array {
                    return [
                        'primary' => $row['title'],
                        'secondary' => number_format((float)$row['score'], 1) . ' · ' . $row['attempt_date'],
                    ];
                }, array_reverse($recentTests)),
            ],
            [
                'title' => 'Attribute wise score',
                'type' => 'list',
                'emptyText' => 'No skill progress yet.',
                'items' => array_map(static function (array $row): array {
                    return [
                        'primary' => $row['attribute_name'],
                        'secondary' => number_format((float)$row['score'], 1),
                    ];
                }, $attributeRows),
            ],
            [
                'title' => 'Comparison summary',
                'type' => 'table',
                'headers' => ['Scope', 'Average score'],
                'rows' => array_map(static function (array $row): array {
                    return [$row['label'], $row['value']];
                }, $comparisonMetrics),
            ],
        ];
        $response['roleSections'] = [
            [
                'title' => 'Attribute suggestions',
                'paragraphs' => suggestion_paragraphs_for_scores($attributeRows, 'Attribute', (string)$studentInfo['name']),
            ],
            [
                'title' => 'Overall suggestions',
                'paragraphs' => [
                    sira_overall_message($avgTestScore, (string)$studentInfo['name']),
                    'Your testing pattern shows ' . count($recentTests) . ' recent checkpoints. Keep a steady weekly revision loop and focus on the lowest scoring attribute first.',
                ],
            ],
            [
                'title' => 'Peer comparison',
                'items' => [
                    'Same school / same class average: ' . number_format($sameSchoolClassAvg, 1),
                    'Same state / same class average: ' . number_format($sameStateClassAvg, 1),
                    'Top same-class peers tracked: ' . count($sameClassPeers),
                    'Same-state peers tracked: ' . count($sameStatePeers),
                ],
            ],
        ];
        $response['comparisonRows'] = [
            'sameClassPeers' => $sameClassPeers,
            'sameStatePeers' => $sameStatePeers,
        ];
        $response['hideSections'] = ['community-panel', 'achievements-panel'];

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
        $studentRows = [];
        $comparisonRows = [];
        $selectedGrade = trim((string)($_GET['grade'] ?? ''));
        $gradeOptions = [];
        $totalStudents = 0;
        $avgScore = 0.0;
        $attemptCount = 0;
        $overallSira = 0.0;
        $schoolState = '';

        if ($schoolId > 0) {
            $stmt = $pdo->prepare('SELECT name, state FROM schools WHERE id = ?');
            $stmt->execute([$schoolId]);
            $school = $stmt->fetch() ?: ['name' => '', 'state' => ''];
            $schoolName = (string)($school['name'] ?? '');
            $schoolState = (string)($school['state'] ?? '');

            $gradeStmt = $pdo->prepare(
                'SELECT DISTINCT COALESCE(NULLIF(TRIM(grade), ""), "Unassigned") AS grade_label
                 FROM users
                 WHERE role = "student" AND school_id = ?
                 ORDER BY grade_label ASC'
            );
            $gradeStmt->execute([$schoolId]);
            $gradeOptions = array_map(static fn (array $row): string => (string)$row['grade_label'], $gradeStmt->fetchAll());

            $gradeRowsStmt = $pdo->prepare(
                'SELECT base.grade_label,
                        COUNT(DISTINCT base.student_id) AS student_count,
                        COUNT(DISTINCT base.attempt_id) AS test_attempted_count,
                        COALESCE(AVG(base.student_sira), 0) AS sira_rating
                 FROM (
                    SELECT u.id AS student_id,
                           COALESCE(NULLIF(TRIM(u.grade), ""), "Unassigned") AS grade_label,
                           ta.id AS attempt_id,
                           COALESCE(skill.avg_score, 0) AS student_sira
                    FROM users u
                    LEFT JOIN test_attempts ta ON ta.student_id = u.id
                    LEFT JOIN (
                        SELECT student_id, AVG(score) AS avg_score
                        FROM skill_progress
                        GROUP BY student_id
                    ) skill ON skill.student_id = u.id
                    WHERE u.role = "student" AND u.school_id = ?
                 ) base
                 GROUP BY base.grade_label
                 ORDER BY base.grade_label ASC'
            );
            $gradeRowsStmt->execute([$schoolId]);
            $gradeRows = $gradeRowsStmt->fetchAll();

            $statsStmt = $pdo->prepare(
                'SELECT COALESCE(AVG(scores.avg_score), 0) AS avg_score,
                        COUNT(DISTINCT ta.id) AS attempt_count,
                        COUNT(DISTINCT u.id) AS student_count,
                        COALESCE(AVG(scores.avg_score), 0) AS overall_sira
                 FROM users u
                 LEFT JOIN test_attempts ta ON ta.student_id = u.id
                 LEFT JOIN (
                    SELECT student_id, AVG(score) AS avg_score
                    FROM skill_progress
                    GROUP BY student_id
                 ) scores ON scores.student_id = u.id
                 WHERE u.school_id = ? AND u.role = "student"'
            );
            $statsStmt->execute([$schoolId]);
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $avgScore = (float)($stats['avg_score'] ?? 0);
            $attemptCount = (int)($stats['attempt_count'] ?? 0);
            $totalStudents = (int)($stats['student_count'] ?? 0);
            $overallSira = (float)($stats['overall_sira'] ?? 0);

            $studentStmt = $pdo->prepare(
                'SELECT u.id, u.name, COALESCE(NULLIF(TRIM(u.grade), ""), "Unassigned") AS grade_label,
                        COALESCE(skill.avg_score, 0) AS sira_score,
                        COUNT(DISTINCT ta.id) AS attempts
                 FROM users u
                 LEFT JOIN test_attempts ta ON ta.student_id = u.id
                 LEFT JOIN (
                    SELECT student_id, AVG(score) AS avg_score
                    FROM skill_progress
                    GROUP BY student_id
                 ) skill ON skill.student_id = u.id
                 WHERE u.role = "student" AND u.school_id = ?'
                    . ($selectedGrade !== '' ? ' AND COALESCE(NULLIF(TRIM(u.grade), ""), "Unassigned") = ?' : '') .
                '
                 GROUP BY u.id, u.name, grade_label, skill.avg_score
                 ORDER BY grade_label ASC, sira_score DESC, u.name ASC'
            );
            $params = [$schoolId];
            if ($selectedGrade !== '') {
                $params[] = $selectedGrade;
            }
            $studentStmt->execute($params);
            $students = $studentStmt->fetchAll();

            $attemptsStmt = $pdo->prepare(
                'WITH ranked_attempts AS (
                    SELECT ta.student_id, ta.score, ta.attempt_date, t.title,
                           ROW_NUMBER() OVER (PARTITION BY ta.student_id ORDER BY ta.attempt_date DESC) AS rn
                    FROM test_attempts ta
                    JOIN tests t ON t.id = ta.test_id
                    JOIN users u ON u.id = ta.student_id
                    WHERE u.role = "student" AND u.school_id = ?'
                    . ($selectedGrade !== '' ? ' AND COALESCE(NULLIF(TRIM(u.grade), ""), "Unassigned") = ?' : '') .
                ')
                 SELECT * FROM ranked_attempts WHERE rn <= 5 ORDER BY student_id ASC, rn ASC'
            );
            $attemptParams = [$schoolId];
            if ($selectedGrade !== '') {
                $attemptParams[] = $selectedGrade;
            }
            $attemptsStmt->execute($attemptParams);
            $attemptsByStudent = [];
            foreach ($attemptsStmt->fetchAll() as $row) {
                $attemptsByStudent[(int)$row['student_id']][] = $row;
            }

            foreach ($students as &$student) {
                $student['recent_attempts'] = $attemptsByStudent[(int)$student['id']] ?? [];
            }
            unset($student);

            if ($schoolState !== '') {
                $comparisonStmt = $pdo->prepare(
                    'SELECT s.id, s.name, s.city, s.state,
                            COUNT(DISTINCT u.id) AS student_count,
                            COUNT(DISTINCT ta.id) AS test_attempted_count,
                            COALESCE(AVG(scores.avg_score), 0) AS sira_rating
                     FROM schools s
                     LEFT JOIN users u ON u.school_id = s.id AND u.role = "student"
                     LEFT JOIN test_attempts ta ON ta.student_id = u.id
                     LEFT JOIN (
                        SELECT student_id, AVG(score) AS avg_score
                        FROM skill_progress
                        GROUP BY student_id
                     ) scores ON scores.student_id = u.id
                     WHERE s.state = ?
                     GROUP BY s.id, s.name, s.city, s.state
                     ORDER BY sira_rating DESC, student_count DESC
                     LIMIT 8'
                );
                $comparisonStmt->execute([$schoolState]);
                $comparisonRows = $comparisonStmt->fetchAll();
            }

            $studentsForSelected = array_values(array_filter($students, static function (array $row) use ($selectedGrade): bool {
                return $selectedGrade === '' || (string)$row['grade_label'] === $selectedGrade;
            }));
        }

        $response = base_response();
        $response['primaryChartTitle'] = 'Class wise number of student and test attempted';
        $response['secondaryChartTitle'] = 'Class wise SIRA rating';
        $response['primaryChart'] = [
            'type' => 'bar',
            'data' => [
                'labels' => array_map(static fn (array $row): string => (string)$row['grade_label'], $gradeRows),
                'datasets' => [
                    [
                        'label' => 'Students',
                        'data' => array_map(static fn (array $row): float => (float)$row['student_count'], $gradeRows),
                        'backgroundColor' => 'rgba(76, 175, 80, 0.65)',
                    ],
                    [
                        'label' => 'Tests attempted',
                        'data' => array_map(static fn (array $row): float => (float)$row['test_attempted_count'], $gradeRows),
                        'backgroundColor' => 'rgba(33, 150, 243, 0.55)',
                    ],
                ],
            ],
        ];
        $response['secondaryChart'] = [
            'type' => 'bar',
            'data' => [
                'labels' => array_map(static fn (array $row): string => (string)$row['grade_label'], $gradeRows),
                'datasets' => [[
                    'label' => 'SIRA rating',
                    'data' => array_map(static fn (array $row): float => (float)$row['sira_rating'], $gradeRows),
                    'backgroundColor' => 'rgba(33, 150, 243, 0.65)',
                ]],
            ],
        ];
        $response['highlights'] = [
            $schoolId > 0 ? ('School dashboard: ' . ($schoolName !== '' ? $schoolName : ('#' . $schoolId))) : 'No school linked to this account.',
            'Students in school: ' . $totalStudents,
            'Average test score: ' . number_format($avgScore, 1),
            'Overall SIRA rating: ' . number_format($overallSira, 1),
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
                'title' => 'Class wise SIRA rating',
                'type' => 'list',
                'emptyText' => 'No class data available.',
                'items' => array_map(static function (array $row): array {
                    return [
                        'primary' => (string)$row['grade_label'],
                        'secondary' => number_format((float)$row['sira_rating'], 1) . ' SIRA · ' . (int)$row['student_count'] . ' students · ' . (int)$row['test_attempted_count'] . ' tests',
                    ];
                }, $gradeRows),
            ],
            [
                'title' => 'Student wise SIRA rating',
                'type' => 'studentCards',
                'emptyText' => 'No students found.',
                'items' => array_map(static function (array $row): array {
                    return [
                        'primary' => $row['name'],
                        'secondary' => 'Grade ' . ($row['grade_label'] ?: 'Unassigned') . ' · SIRA ' . number_format((float)$row['sira_score'], 1) . ' · ' . (int)$row['attempts'] . ' tests',
                        'link' => url_for('student_report.php?student_id=' . (int)$row['id']),
                        'link_label' => 'Open report',
                        'scores' => array_map(static function (array $attempt): string {
                            return number_format((float)$attempt['score'], 1) . ' · ' . (string)$attempt['title'];
                        }, $row['recent_attempts'] ?? []),
                    ];
                }, $studentsForSelected),
            ],
            [
                'title' => 'Comparison with same-state schools',
                'type' => 'table',
                'headers' => ['School', 'Students', 'Tests', 'SIRA'],
                'rows' => array_map(static function (array $row): array {
                    return [
                        trim((string)$row['name'] . (!empty($row['city']) ? ', ' . $row['city'] : '')),
                        (string)$row['student_count'],
                        (string)$row['test_attempted_count'],
                        number_format((float)$row['sira_rating'], 1),
                    ];
                }, $comparisonRows),
            ],
        ];
        $response['filters'] = [
            'grades' => $gradeOptions,
            'selectedGrade' => $selectedGrade,
        ];
        $response['roleSections'] = [
            [
                'title' => 'Class wise suggestion',
                'paragraphs' => [
                    'Across your school, the strongest gains usually come from focusing on the grade with the lowest SIRA and test participation first. A targeted class plan, a weekly revision rhythm, and a short remedial practice set can lift the full cohort without increasing friction.',
                    'Use the student list to spot the classes where last-five-test momentum is falling. Those groups should receive the next intervention: revision, teacher feedback, and a short follow-up assessment so the improvement loop is visible quickly.',
                ],
            ],
            [
                'title' => 'Overall school suggestion',
                'paragraphs' => [
                    'Your school dashboard is strongest when class-wise performance, student-wise progress, and the state comparison are reviewed together. The objective is not just a higher score but a more balanced skill profile across academic, creative, leadership, and technical domains.',
                    'A good next step is to assign one class-level focus and one school-wide focus each week. That keeps improvement measurable while also giving teachers, parents, and leaders a shared action plan.',
                ],
            ],
        ];
        $response['comparisonRows'] = $comparisonRows;
        $response['hideSections'] = ['community-panel', 'achievements-panel'];

        json_result($response);
        break;
}
