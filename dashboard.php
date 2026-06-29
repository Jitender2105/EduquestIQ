<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes_auth.php';
require_once __DIR__ . '/includes_payments.php';
require_once __DIR__ . '/includes_articles.php';
$user = require_auth([]);

$learningHub = [
    'tests' => [],
    'materials' => [],
    'papers' => [],
    'videos' => [],
    'articles' => [],
    'counts' => [
        'tests' => 0,
        'materials' => 0,
        'papers' => 0,
        'videos' => 0,
        'articles' => 0,
    ],
];

function dashboard_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function dashboard_clean_excerpt(string $html, int $width = 140): string
{
    return text_preview(trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? ''), $width);
}

function dashboard_video_youtube_id(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    if (preg_match('~(?:youtube\.com/watch\?v=|youtube\.com/embed/|youtube\.com/shorts/|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $matches)) {
        return $matches[1];
    }

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host']) || !str_contains((string)$parts['host'], 'youtube.com') || empty($parts['query'])) {
        return null;
    }

    parse_str((string)$parts['query'], $query);
    return !empty($query['v']) && preg_match('/^[A-Za-z0-9_-]{11}$/', (string)$query['v']) ? (string)$query['v'] : null;
}

function dashboard_video_display_title(array $row, string $youtubeId): string
{
    $title = trim((string)($row['title'] ?? ''));
    if ($title !== '' && !preg_match('/^YouTube Lecture [A-Za-z0-9_-]{11}$/', $title)) {
        return $title;
    }

    foreach (['test_title', 'sub_attribute_name', 'attribute_name', 'course_title'] as $key) {
        $fallback = trim((string)($row[$key] ?? ''));
        if ($fallback !== '') {
            return $fallback . ' Video Lecture';
        }
    }

    return 'Premium Video Lesson';
}

function dashboard_load_learning_hub(array $user): array
{
    $hub = [
        'tests' => [],
        'materials' => [],
        'papers' => [],
        'videos' => [],
        'articles' => [],
        'counts' => [
            'tests' => 0,
            'materials' => 0,
            'papers' => 0,
            'videos' => 0,
            'articles' => 0,
        ],
    ];

    if (($user['role'] ?? '') !== 'student') {
        return $hub;
    }

    $pdo = get_pdo();
    $studentId = (int)$user['sub'];
    $studentGrade = '';
    try {
        $gradeStmt = $pdo->prepare('SELECT grade FROM users WHERE id = ? LIMIT 1');
        $gradeStmt->execute([$studentId]);
        $studentGrade = trim((string)$gradeStmt->fetchColumn());
    } catch (Throwable $e) {
        $studentGrade = '';
    }

    try {
        $testActive = table_has_column($pdo, 'tests', 'is_active');
        $testGrade = table_has_column($pdo, 'tests', 'target_grade');
        $testWhere = [];
        $testParams = [];
        if ($testActive) {
            $testWhere[] = 't.is_active = 1';
        }
        if ($testGrade && $studentGrade !== '') {
            $testWhere[] = '(t.target_grade IS NULL OR t.target_grade = "" OR t.target_grade = ?)';
            $testParams[] = $studentGrade;
        }

        $attemptRows = [];
        if (dashboard_table_exists($pdo, 'test_attempts')) {
            $attemptStmt = $pdo->prepare(
                'SELECT ta.test_id, ta.id, ta.score, ta.attempt_date
                 FROM test_attempts ta
                 INNER JOIN (
                    SELECT test_id, MAX(id) AS last_id
                    FROM test_attempts
                    WHERE student_id = ?
                    GROUP BY test_id
                 ) last_attempt ON last_attempt.last_id = ta.id'
            );
            $attemptStmt->execute([$studentId]);
            foreach ($attemptStmt->fetchAll() as $attemptRow) {
                $attemptRows[(int)$attemptRow['test_id']] = $attemptRow;
            }
        }

        $testSql = 'SELECT t.id, t.title, t.description, t.price_inr, t.total_marks, t.duration_minutes'
            . ($testGrade ? ', t.target_grade' : ', NULL AS target_grade')
            . ' FROM tests t'
            . ($testWhere ? ' WHERE ' . implode(' AND ', $testWhere) : '')
            . ' ORDER BY t.created_at DESC, t.id DESC LIMIT 24';
        $testStmt = $pdo->prepare($testSql);
        $testStmt->execute($testParams);
        foreach ($testStmt->fetchAll() as $test) {
            $price = (float)($test['price_inr'] ?? 0);
            $isAccessible = $price <= 0 || test_purchase_is_paid($pdo, (int)$test['id'], $studentId);
            if (!$isAccessible) {
                continue;
            }
            $attempt = $attemptRows[(int)$test['id']] ?? null;
            $hub['tests'][] = [
                'title' => (string)$test['title'],
                'meta' => trim(((int)($test['duration_minutes'] ?? 0) > 0 ? (int)$test['duration_minutes'] . ' min' : '') . (((int)($test['total_marks'] ?? 0) > 0) ? ' · ' . (int)$test['total_marks'] . ' marks' : ''), " \t\n\r\0\x0B·"),
                'excerpt' => dashboard_clean_excerpt((string)($test['description'] ?? ''), 110),
                'label' => $attempt ? 'Continue / retry test' : 'Start test',
                'url' => url_for('test_attempt.php?id=' . (int)$test['id']),
                'report_url' => $attempt ? url_for('sira_report.php?attempt_id=' . (int)$attempt['id']) : '',
                'badge' => $price > 0 ? 'Purchased' : 'Free',
            ];
            if (count($hub['tests']) >= 6) {
                break;
            }
        }
        $hub['counts']['tests'] = count($hub['tests']);
    } catch (Throwable $e) {
        $hub['tests'] = [];
    }

    try {
        ensure_study_material_tables($pdo);
        $materialSql = 'SELECT sm.id, sm.title, sm.description, sm.material_type, sm.access_type, sm.amount_inr, sm.grade, sm.chapter,
                               a.name AS attribute_name, sa.name AS sub_attribute_name
                        FROM study_materials sm
                        LEFT JOIN attributes a ON a.id = sm.attribute_id
                        LEFT JOIN sub_attributes sa ON sa.id = sm.sub_attribute_id
                        WHERE sm.is_active = 1 AND sm.status = "published"'
            . ($studentGrade !== '' ? ' AND (sm.grade IS NULL OR sm.grade = "" OR sm.grade = ?)' : '')
            . ' ORDER BY sm.updated_at DESC, sm.uploaded_at DESC, sm.id DESC LIMIT 24';
        $materialStmt = $pdo->prepare($materialSql);
        $materialStmt->execute($studentGrade !== '' ? [$studentGrade] : []);
        foreach ($materialStmt->fetchAll() as $material) {
            $isPaid = (string)($material['access_type'] ?? 'free') === 'paid' && (float)($material['amount_inr'] ?? 0) > 0;
            if ($isPaid && !study_material_purchase_is_paid($pdo, (int)$material['id'], $studentId)) {
                continue;
            }
            $tags = array_filter([
                (string)($material['grade'] ?? ''),
                (string)($material['attribute_name'] ?? ''),
                (string)($material['sub_attribute_name'] ?? ''),
                (string)($material['chapter'] ?? ''),
            ]);
            $hub['materials'][] = [
                'title' => (string)$material['title'],
                'meta' => implode(' · ', array_slice($tags, 0, 3)),
                'excerpt' => dashboard_clean_excerpt((string)($material['description'] ?? ''), 115),
                'label' => 'Open material',
                'url' => url_for('study_material_download.php?id=' . (int)$material['id']),
                'badge' => $isPaid ? 'Purchased' : 'Free',
                'type' => strtoupper((string)($material['material_type'] ?? 'PDF')),
            ];
            if (count($hub['materials']) >= 6) {
                break;
            }
        }
        $hub['counts']['materials'] = count($hub['materials']);
    } catch (Throwable $e) {
        $hub['materials'] = [];
    }

    try {
        ensure_practice_paper_tables($pdo);
        if (practice_paper_table_exists($pdo)) {
            $paperSql = 'SELECT pp.id, pp.name, pp.description, pp.class_name, pp.paper_year, pp.access_type, pp.amount_inr, t.title AS test_title
                         FROM practice_papers pp
                         JOIN tests t ON t.id = pp.test_id
                         WHERE pp.is_active = 1 AND pp.status = "published"'
                . ($studentGrade !== '' ? ' AND (pp.class_name IS NULL OR pp.class_name = "" OR pp.class_name = ?)' : '')
                . ' ORDER BY pp.updated_at DESC, pp.id DESC LIMIT 24';
            $paperStmt = $pdo->prepare($paperSql);
            $paperStmt->execute($studentGrade !== '' ? [$studentGrade] : []);
            foreach ($paperStmt->fetchAll() as $paper) {
                $isPaid = (string)($paper['access_type'] ?? 'free') === 'paid' && (float)($paper['amount_inr'] ?? 0) > 0;
                if ($isPaid && !practice_paper_purchase_is_paid($pdo, (int)$paper['id'], $studentId)) {
                    continue;
                }
                $hub['papers'][] = [
                    'title' => (string)$paper['name'],
                    'meta' => trim((string)($paper['class_name'] ?? '') . ' · ' . (string)($paper['paper_year'] ?? '') . ' · ' . (string)($paper['test_title'] ?? ''), " \t\n\r\0\x0B·"),
                    'excerpt' => dashboard_clean_excerpt((string)($paper['description'] ?? ''), 115),
                    'label' => 'Read paper',
                    'url' => url_for('practice_paper_download.php?id=' . (int)$paper['id']),
                    'badge' => $isPaid ? 'Purchased' : 'Free',
                ];
                if (count($hub['papers']) >= 6) {
                    break;
                }
            }
            $hub['counts']['papers'] = count($hub['papers']);
        }
    } catch (Throwable $e) {
        $hub['papers'] = [];
    }

    try {
        if (dashboard_table_exists($pdo, 'video_lectures')) {
            $hasContentMeta = dashboard_table_exists($pdo, 'content_metadata');
            $select = [
                'vl.id',
                'vl.title',
                'vl.video_url',
                'vl.duration',
                'c.title AS course_title',
                table_has_column($pdo, 'video_lectures', 'description') ? 'vl.description' : 'NULL AS description',
                table_has_column($pdo, 'video_lectures', 'is_featured') ? 'vl.is_featured' : '0 AS is_featured',
                table_has_column($pdo, 'video_lectures', 'test_id') ? 't.title AS test_title' : 'NULL AS test_title',
                table_has_column($pdo, 'video_lectures', 'attribute_id') ? 'a.name AS attribute_name' : 'NULL AS attribute_name',
                table_has_column($pdo, 'video_lectures', 'sub_attribute_id') ? 'sa.name AS sub_attribute_name' : 'NULL AS sub_attribute_name',
                $hasContentMeta ? 'COALESCE(cm.visibility, "public") AS visibility' : '"public" AS visibility',
            ];
            $videoSql = 'SELECT ' . implode(', ', $select) . ' FROM video_lectures vl LEFT JOIN courses c ON c.id = vl.course_id ';
            if (table_has_column($pdo, 'video_lectures', 'test_id')) {
                $videoSql .= 'LEFT JOIN tests t ON t.id = vl.test_id ';
            }
            if (table_has_column($pdo, 'video_lectures', 'attribute_id')) {
                $videoSql .= 'LEFT JOIN attributes a ON a.id = vl.attribute_id ';
            }
            if (table_has_column($pdo, 'video_lectures', 'sub_attribute_id')) {
                $videoSql .= 'LEFT JOIN sub_attributes sa ON sa.id = vl.sub_attribute_id ';
            }
            if ($hasContentMeta) {
                $videoSql .= 'LEFT JOIN content_metadata cm ON cm.entity_type = "video" AND cm.entity_id = vl.id ';
            }
            $videoSql .= table_has_column($pdo, 'video_lectures', 'is_active') ? 'WHERE vl.is_active = 1 ' : '';
            $videoSql .= 'ORDER BY CASE WHEN ' . ($hasContentMeta ? 'COALESCE(cm.visibility, "public")' : '"public"') . ' = "public" THEN 1 ELSE 0 END, is_featured DESC, vl.sequence_order ASC, vl.id DESC LIMIT 12';
            foreach ($pdo->query($videoSql)->fetchAll() as $videoRow) {
                $youtubeId = dashboard_video_youtube_id((string)($videoRow['video_url'] ?? ''));
                if ($youtubeId === null) {
                    continue;
                }
                $description = trim((string)($videoRow['description'] ?? ''));
                if (str_starts_with($description, 'Embedded lecture from YouTube:')) {
                    $description = '';
                }
                $hub['videos'][] = [
                    'title' => dashboard_video_display_title($videoRow, $youtubeId),
                    'meta' => trim((string)($videoRow['course_title'] ?? '') . ' · ' . ((int)($videoRow['duration'] ?? 0) > 0 ? (int)$videoRow['duration'] . ' min' : ''), " \t\n\r\0\x0B·"),
                    'excerpt' => dashboard_clean_excerpt($description, 115),
                    'youtube_id' => $youtubeId,
                    'embed_url' => 'https://www.youtube.com/embed/' . $youtubeId,
                    'thumbnail_url' => 'https://img.youtube.com/vi/' . $youtubeId . '/hqdefault.jpg',
                    'badge' => (string)($videoRow['visibility'] ?? 'public') === 'public' ? 'Video' : 'Premium',
                ];
                if (count($hub['videos']) >= 6) {
                    break;
                }
            }
            $hub['counts']['videos'] = count($hub['videos']);
        }
    } catch (Throwable $e) {
        $hub['videos'] = [];
    }

    try {
        if (article_table_exists($pdo, 'articles')) {
            $hasContentMeta = dashboard_table_exists($pdo, 'content_metadata');
            $articleSql = 'SELECT a.id, a.title, a.slug, a.content_html, a.article_type, a.image_path, a.created_at, '
                . ($hasContentMeta ? 'COALESCE(cm.visibility, "public") AS visibility' : '"public" AS visibility')
                . ' FROM articles a ';
            if ($hasContentMeta) {
                $articleSql .= 'LEFT JOIN content_metadata cm ON cm.entity_type = "article" AND cm.entity_id = a.id ';
            }
            $articleSql .= 'WHERE ' . article_active_clause($pdo, 'a')
                . ' ORDER BY CASE WHEN ' . ($hasContentMeta ? 'COALESCE(cm.visibility, "public")' : '"public"') . ' = "public" THEN 1 ELSE 0 END, a.created_at DESC, a.id DESC LIMIT 8';
            foreach ($pdo->query($articleSql)->fetchAll() as $articleRow) {
                $hub['articles'][] = [
                    'id' => (int)$articleRow['id'],
                    'title' => (string)$articleRow['title'],
                    'meta' => ucfirst((string)($articleRow['article_type'] ?? 'article')),
                    'excerpt' => dashboard_clean_excerpt((string)($articleRow['content_html'] ?? ''), 125),
                    'content_html' => (string)($articleRow['content_html'] ?? ''),
                    'image_url' => !empty($articleRow['image_path']) ? url_for((string)$articleRow['image_path']) : '',
                    'badge' => (string)($articleRow['visibility'] ?? 'public') === 'public' ? 'Article' : 'Premium',
                ];
                if (count($hub['articles']) >= 6) {
                    break;
                }
            }
            $hub['counts']['articles'] = count($hub['articles']);
        }
    } catch (Throwable $e) {
        $hub['articles'] = [];
    }

    return $hub;
}

$learningHub = dashboard_load_learning_hub($user);

require_once __DIR__ . '/includes_header.php';
?>

<style>
    .eq-dashboard-shell {
        --dash-accent: #4374ff;
        --dash-accent-2: #ffb23f;
        --dash-accent-3: #13b8a6;
        --dash-accent-4: #ff5d8f;
        --dash-ink: #121731;
        --dash-soft: #667089;
        --dash-panel: #ffffff;
        --dash-line: rgba(30, 43, 92, 0.1);
        --dash-shadow: 0 18px 46px rgba(37, 48, 99, 0.12);
        color: var(--dash-ink);
        position: relative;
    }

    .eq-dashboard-shell * {
        letter-spacing: 0;
    }

    .eq-dashboard-hero {
        position: relative;
        overflow: hidden;
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,0.32);
        background:
            radial-gradient(circle at 8% 18%, rgba(255, 210, 97, 0.24), transparent 24%),
            radial-gradient(circle at 56% 0%, rgba(40, 192, 169, 0.24), transparent 28%),
            linear-gradient(135deg, rgba(18, 23, 49, 0.96), rgba(43, 71, 152, 0.93)),
            url('<?php echo htmlspecialchars(url_for('assets/img/sira-assessment-visual.png')); ?>') right center / 430px auto no-repeat;
        box-shadow: var(--dash-shadow);
        min-height: 300px;
        padding: 32px;
    }

    .eq-dashboard-hero::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, rgba(18,23,49,0.92) 0%, rgba(18,23,49,0.78) 52%, rgba(18,23,49,0.24) 100%);
        pointer-events: none;
    }

    .eq-dashboard-hero::before {
        content: "";
        position: absolute;
        inset: 16px;
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,0.08);
        pointer-events: none;
    }

    .eq-dashboard-hero > * {
        position: relative;
        z-index: 1;
    }

    .eq-dash-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.18);
        color: rgba(255,255,255,0.9);
        font-size: 0.78rem;
        font-weight: 800;
        padding: 7px 12px;
        text-transform: uppercase;
    }

    .eq-dash-eyebrow span {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--dash-accent-2);
        box-shadow: 0 0 0 4px rgba(255,178,63,0.2);
    }

    .eq-dashboard-title {
        color: #fff;
        font-size: clamp(2.2rem, 4.4vw, 4rem);
        font-weight: 800;
        line-height: 1.02;
        max-width: 760px;
        margin: 18px 0 10px;
    }

    .eq-dashboard-subtitle {
        color: rgba(255,255,255,0.76);
        max-width: 650px;
        font-size: 1.04rem;
        line-height: 1.65;
        margin: 0;
    }

    .eq-section-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 14px;
        margin: 26px 0 14px;
    }

    .eq-section-head h2 {
        margin: 0;
        color: var(--dash-ink);
        font-size: clamp(1.2rem, 2vw, 1.62rem);
        font-weight: 800;
    }

    .eq-section-head p {
        margin: 4px 0 0;
        color: var(--dash-soft);
        font-size: 0.92rem;
        line-height: 1.45;
    }

    .eq-section-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        background: #fff;
        border: 1px solid rgba(30,43,92,0.08);
        color: var(--dash-accent);
        font-size: 0.78rem;
        font-weight: 800;
        padding: 8px 12px;
        white-space: nowrap;
        box-shadow: 0 8px 18px rgba(37, 48, 99, 0.07);
    }

    .eq-section-badge::before {
        content: "";
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--dash-accent-2);
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--dash-accent-2) 20%, transparent);
    }

    .eq-dash-celebration {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin: -26px 18px 24px;
        position: relative;
        z-index: 3;
    }

    .eq-kudo-card {
        border-radius: 8px;
        background: rgba(255,255,255,0.94);
        border: 1px solid rgba(30,43,92,0.08);
        box-shadow: 0 16px 34px rgba(37, 48, 99, 0.12);
        padding: 14px;
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: 10px;
        align-items: center;
    }

    .eq-kudo-icon,
    .eq-dash-metric-icon,
    .eq-insight-icon {
        font-family: 'Outfit', sans-serif;
    }

    .eq-kudo-icon {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, var(--dash-accent-2), var(--dash-accent-4));
        color: #fff;
        font-size: 1.25rem;
        box-shadow: 0 10px 20px color-mix(in srgb, var(--dash-accent-4) 24%, transparent);
    }

    .eq-kudo-card strong {
        display: block;
        color: var(--dash-ink);
        font-size: 0.96rem;
        line-height: 1.15;
    }

    .eq-kudo-card span {
        display: block;
        color: var(--dash-soft);
        font-size: 0.78rem;
        font-weight: 700;
        margin-top: 3px;
    }

    .eq-dashboard-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 24px;
    }

    .eq-dashboard-actions .form-select,
    .eq-dashboard-actions .btn {
        border-radius: 8px;
        min-height: 40px;
    }

    .eq-dash-hero-strip {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-top: 22px;
        max-width: 690px;
    }

    .eq-dash-hero-stat {
        border-radius: 8px;
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.16);
        padding: 12px;
        color: #fff;
        min-width: 0;
    }

    .eq-dash-hero-stat strong {
        display: block;
        font-family: 'Outfit', sans-serif;
        font-size: 1.25rem;
        line-height: 1.1;
        overflow-wrap: anywhere;
    }

    .eq-dash-hero-stat span {
        display: block;
        color: rgba(255,255,255,0.7);
        font-size: 0.78rem;
        font-weight: 700;
        margin-top: 5px;
    }

    .eq-dash-panel,
    .eq-dash-metric {
        border-radius: 8px;
        border: 1px solid var(--dash-line);
        background: var(--dash-panel);
        box-shadow: 0 14px 34px rgba(40, 52, 102, 0.09);
    }

    .eq-dash-metric {
        height: 100%;
        padding: 18px;
        position: relative;
        overflow: hidden;
        min-height: 150px;
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }

    .eq-dash-metric:hover,
    .eq-dash-panel:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 42px rgba(40, 52, 102, 0.13);
    }

    .eq-dash-metric::after {
        content: "";
        position: absolute;
        width: 84px;
        height: 84px;
        right: -28px;
        top: -28px;
        border-radius: 50%;
        background: color-mix(in srgb, var(--dash-accent) 18%, transparent);
    }

    .eq-dash-metric::before {
        content: "";
        position: absolute;
        inset: auto 18px 14px 18px;
        height: 5px;
        border-radius: 999px;
        background: linear-gradient(90deg, var(--dash-accent), var(--dash-accent-2), var(--dash-accent-3));
        opacity: 0.74;
    }

    .eq-dash-metric-icon {
        width: 44px;
        height: 44px;
        border-radius: 8px;
        display: inline-grid;
        place-items: center;
        background: linear-gradient(135deg, var(--dash-accent), var(--dash-accent-3));
        color: #fff;
        font-weight: 800;
        font-size: 1.2rem;
        margin-bottom: 14px;
        box-shadow: 0 10px 22px color-mix(in srgb, var(--dash-accent) 24%, transparent);
    }

    .eq-dash-metric-label {
        color: var(--dash-soft);
        font-size: 0.84rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .eq-dash-metric-value {
        color: var(--dash-ink);
        font-family: 'Outfit', sans-serif;
        font-size: clamp(1.6rem, 2.7vw, 2.35rem);
        font-weight: 800;
        line-height: 1.05;
        margin-top: 5px;
        overflow-wrap: anywhere;
    }

    .eq-insight-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
        margin-bottom: 24px;
    }

    .eq-insight-card {
        border-radius: 8px;
        border: 1px solid var(--dash-line);
        background: linear-gradient(180deg, #fff, #f8faff);
        box-shadow: 0 14px 34px rgba(40, 52, 102, 0.09);
        padding: 18px;
        min-height: 250px;
        position: relative;
        overflow: hidden;
    }

    .eq-insight-card::after {
        content: "";
        position: absolute;
        right: -44px;
        top: -44px;
        width: 118px;
        height: 118px;
        border-radius: 50%;
        background: color-mix(in srgb, var(--dash-accent) 12%, transparent);
    }

    .eq-insight-head {
        position: relative;
        z-index: 1;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 12px;
    }

    .eq-insight-head h4 {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
    }

    .eq-insight-head p {
        margin: 4px 0 0;
        color: var(--dash-soft);
        font-size: 0.82rem;
    }

    .eq-insight-icon {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: grid;
        place-items: center;
        background: color-mix(in srgb, var(--dash-accent) 14%, #fff);
        color: var(--dash-accent);
        font-size: 1.15rem;
        flex: 0 0 auto;
    }

    .eq-mini-chart {
        height: 150px;
        position: relative;
        z-index: 1;
    }

    .eq-mini-chart canvas {
        width: 100% !important;
        max-height: 150px;
    }

    .eq-dash-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.55fr) minmax(300px, 0.75fr);
        gap: 18px;
    }

    .eq-dash-panel {
        padding: 22px;
        height: 100%;
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }

    .eq-dash-panel-title {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 16px;
    }

    .eq-dash-panel-title h3,
    .eq-dash-panel-title h4 {
        margin: 0;
        font-size: 1.22rem;
        font-weight: 800;
    }

    .eq-dash-panel-title p {
        margin: 5px 0 0;
        color: var(--dash-soft);
        font-size: 0.86rem;
    }

    .eq-dash-pill {
        border-radius: 999px;
        background: color-mix(in srgb, var(--dash-accent) 11%, #fff);
        color: color-mix(in srgb, var(--dash-accent) 70%, #111);
        border: 1px solid color-mix(in srgb, var(--dash-accent) 20%, transparent);
        font-size: 0.76rem;
        font-weight: 800;
        padding: 6px 10px;
        white-space: nowrap;
    }

    .eq-chart-frame {
        min-height: 360px;
        position: relative;
    }

    .eq-chart-frame canvas {
        width: 100% !important;
        max-height: 365px;
    }

    .eq-ring-wrap {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: 16px;
        align-items: center;
        padding: 16px;
        border-radius: 8px;
        background: linear-gradient(135deg, color-mix(in srgb, var(--dash-accent) 11%, #fff), #fff);
        border: 1px solid color-mix(in srgb, var(--dash-accent) 16%, transparent);
        margin-bottom: 16px;
    }

    .eq-score-ring {
        width: 104px;
        height: 104px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: conic-gradient(var(--dash-accent) var(--ring-value, 0%), #e8edf8 0);
        flex: 0 0 auto;
    }

    .eq-score-ring span {
        width: 78px;
        height: 78px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: #fff;
        color: var(--dash-ink);
        font-family: 'Outfit', sans-serif;
        font-size: 1.42rem;
        font-weight: 800;
    }

    .eq-focus-list,
    .eq-clean-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .eq-focus-list li,
    .eq-clean-list li,
    .eq-feed-item {
        border-radius: 8px;
        background: linear-gradient(135deg, #f8faff, #fff);
        border: 1px solid rgba(32, 49, 109, 0.07);
        padding: 13px 14px;
        color: #333b52;
        font-size: 0.94rem;
        line-height: 1.5;
    }

    .eq-focus-list li + li,
    .eq-clean-list li + li,
    .eq-feed-item + .eq-feed-item {
        margin-top: 9px;
    }

    .eq-feed-user {
        display: block;
        color: var(--dash-ink);
        font-weight: 800;
        margin-bottom: 3px;
    }

    .eq-widget-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .eq-data-list {
        display: grid;
        gap: 9px;
    }

    .eq-data-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 12px;
        align-items: center;
        border-radius: 8px;
        background: linear-gradient(135deg, #f8faff, #fff);
        border: 1px solid rgba(32,49,109,0.07);
        padding: 14px;
    }

    .eq-data-row strong {
        display: block;
        font-size: 0.98rem;
        color: var(--dash-ink);
        overflow-wrap: anywhere;
    }

    .eq-data-row span {
        color: var(--dash-soft);
        font-size: 0.84rem;
        line-height: 1.45;
    }

    .eq-data-value {
        border-radius: 999px;
        background: #fff;
        border: 1px solid rgba(32,49,109,0.08);
        color: var(--dash-accent);
        font-weight: 800;
        padding: 6px 9px;
        max-width: 180px;
        overflow-wrap: anywhere;
        text-align: right;
    }

    .eq-student-tile {
        border-radius: 8px;
        background: linear-gradient(135deg, #f8faff, #fff);
        border: 1px solid rgba(32,49,109,0.08);
        padding: 14px;
    }

    .eq-student-tile + .eq-student-tile {
        margin-top: 10px;
    }

    .eq-score-chips {
        display: flex;
        gap: 7px;
        flex-wrap: wrap;
        margin-top: 10px;
    }

    .eq-score-chip {
        border-radius: 999px;
        background: #fff;
        border: 1px solid rgba(32,49,109,0.08);
        color: #4a536d;
        font-size: 0.76rem;
        font-weight: 800;
        padding: 6px 9px;
    }

    .eq-section-text {
        color: var(--dash-soft);
        font-size: 0.92rem;
        line-height: 1.65;
        margin-bottom: 12px;
    }

    .eq-empty {
        color: var(--dash-soft);
        border-radius: 8px;
        background: #f8faff;
        border: 1px dashed rgba(32,49,109,0.16);
        padding: 14px;
        font-size: 0.9rem;
    }

    .eq-dash-table {
        margin: 0;
        font-size: 0.9rem;
    }

    .eq-dash-table th {
        color: var(--dash-soft);
        font-size: 0.78rem;
        text-transform: uppercase;
        border-bottom-color: rgba(32,49,109,0.08);
    }

    .eq-dash-table td {
        border-bottom-color: rgba(32,49,109,0.06);
    }

    .eq-learning-hub {
        border-radius: 8px;
        border: 1px solid rgba(32,49,109,0.08);
        background:
            linear-gradient(135deg, rgba(67,116,255,0.08), rgba(19,184,166,0.06)),
            #fff;
        box-shadow: var(--dash-shadow);
        padding: 18px;
        margin-bottom: 24px;
    }

    .eq-learning-top {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 16px;
        align-items: start;
        margin-bottom: 16px;
    }

    .eq-learning-top h2 {
        margin: 0;
        font-size: clamp(1.25rem, 2vw, 1.75rem);
        font-weight: 800;
    }

    .eq-learning-top p {
        margin: 5px 0 0;
        color: var(--dash-soft);
        max-width: 760px;
        line-height: 1.55;
    }

    .eq-learning-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .eq-learning-actions .btn,
    .eq-learning-card .btn {
        border-radius: 8px;
        font-weight: 800;
    }

    .eq-learning-tabs {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 8px;
        margin-bottom: 14px;
    }

    .eq-learning-tab {
        border: 1px solid rgba(32,49,109,0.1);
        background: rgba(255,255,255,0.78);
        color: var(--dash-ink);
        border-radius: 8px;
        min-height: 42px;
        padding: 8px 12px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 800;
        white-space: nowrap;
    }

    .eq-learning-tab.active {
        background: var(--dash-ink);
        border-color: var(--dash-ink);
        color: #fff;
        box-shadow: 0 10px 24px rgba(18,23,49,0.16);
    }

    .eq-learning-statline {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 14px;
    }

    .eq-learning-stat {
        border-radius: 8px;
        border: 1px solid rgba(32,49,109,0.08);
        background: #fff;
        padding: 12px;
    }

    .eq-learning-stat strong {
        display: block;
        font-size: 1.5rem;
        line-height: 1;
    }

    .eq-learning-stat span {
        display: block;
        color: var(--dash-soft);
        font-size: 0.78rem;
        font-weight: 800;
        margin-top: 5px;
    }

    .eq-learning-panel {
        display: none;
    }

    .eq-learning-panel.active {
        display: block;
    }

    .eq-learning-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .eq-learning-card {
        border-radius: 8px;
        border: 1px solid rgba(32,49,109,0.08);
        background: rgba(255,255,255,0.96);
        padding: 14px;
        min-height: 218px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        box-shadow: 0 12px 26px rgba(37, 48, 99, 0.08);
    }

    .eq-learning-card-media {
        aspect-ratio: 16 / 9;
        border-radius: 8px;
        overflow: hidden;
        background: #eef3ff;
        margin: -4px -4px 2px;
    }

    .eq-learning-card-media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .eq-learning-card-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
    }

    .eq-learning-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: grid;
        place-items: center;
        background: color-mix(in srgb, var(--dash-accent) 12%, #fff);
        color: var(--dash-accent);
        font-size: 1.2rem;
        flex: 0 0 auto;
    }

    .eq-learning-badge {
        border-radius: 999px;
        background: #f6f8ff;
        border: 1px solid rgba(32,49,109,0.08);
        color: var(--dash-accent);
        font-size: 0.72rem;
        font-weight: 900;
        padding: 5px 8px;
        white-space: nowrap;
    }

    .eq-learning-card h3 {
        font-size: 1rem;
        margin: 0;
        font-weight: 850;
        line-height: 1.3;
    }

    .eq-learning-card .meta {
        color: var(--dash-soft);
        font-size: 0.78rem;
        font-weight: 800;
        min-height: 18px;
    }

    .eq-learning-card p {
        color: var(--dash-soft);
        font-size: 0.86rem;
        line-height: 1.45;
        margin: 0;
    }

    .eq-learning-card-footer {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
        margin-top: auto;
    }

    .eq-learning-empty {
        border-radius: 8px;
        border: 1px dashed rgba(32,49,109,0.18);
        background: rgba(255,255,255,0.72);
        color: var(--dash-soft);
        padding: 18px;
        display: grid;
        gap: 8px;
    }

    .eq-learning-reader {
        max-height: 68vh;
        overflow: auto;
        padding-right: 4px;
    }

    .eq-learning-reader img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
    }

    .eq-dashboard-shell[data-dashboard-role="parent"] {
        --dash-accent: #e85b6b;
        --dash-accent-2: #ffd166;
        --dash-accent-3: #3fc1c9;
        --dash-accent-4: #8b5cf6;
    }

    .eq-dashboard-shell[data-dashboard-role="teacher"] {
        --dash-accent: #f59f22;
        --dash-accent-2: #4374ff;
        --dash-accent-3: #23b26d;
        --dash-accent-4: #ef476f;
    }

    .eq-dashboard-shell[data-dashboard-role="school_admin"] {
        --dash-accent: #0b8f78;
        --dash-accent-2: #ffbf3f;
        --dash-accent-3: #4374ff;
        --dash-accent-4: #8b5cf6;
    }

    .eq-dashboard-shell[data-dashboard-role="content_admin"],
    .eq-dashboard-shell[data-dashboard-role="super_admin"] {
        --dash-accent: #6946e8;
        --dash-accent-2: #28c0a9;
        --dash-accent-3: #f5a623;
        --dash-accent-4: #ff5d8f;
    }

    @media (max-width: 991.98px) {
        .eq-dashboard-hero {
            background:
                linear-gradient(135deg, rgba(18, 23, 49, 0.96), rgba(43, 71, 152, 0.94)),
                url('<?php echo htmlspecialchars(url_for('assets/img/sira-assessment-visual.png')); ?>') right bottom / 280px auto no-repeat;
            padding: 22px;
        }

        .eq-dash-grid,
        .eq-widget-grid,
        .eq-insight-grid,
        .eq-learning-grid {
            grid-template-columns: 1fr;
        }

        .eq-learning-top {
            grid-template-columns: 1fr;
        }

        .eq-learning-actions {
            justify-content: flex-start;
        }

        .eq-dash-celebration {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-left: 0;
            margin-right: 0;
        }

        .eq-learning-statline {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575.98px) {
        .eq-dashboard-hero {
            padding: 18px;
        }

        .eq-dash-hero-strip {
            grid-template-columns: 1fr;
        }

        .eq-section-head {
            display: block;
        }

        .eq-section-badge {
            margin-top: 10px;
        }

        .eq-dash-celebration {
            grid-template-columns: 1fr;
            margin-top: 14px;
        }

        .eq-ring-wrap {
            grid-template-columns: 1fr;
            text-align: center;
        }

        .eq-score-ring {
            margin: 0 auto;
        }

        .eq-data-row {
            grid-template-columns: 1fr;
        }

        .eq-data-value {
            text-align: left;
            max-width: 100%;
        }

        .eq-learning-hub {
            padding: 14px;
        }

        .eq-learning-statline {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="eq-dashboard-shell" data-dashboard-role="<?php echo htmlspecialchars((string)$user['role']); ?>">
    <section class="eq-dashboard-hero mb-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <div class="eq-dash-eyebrow"><span></span><em id="persona-kicker" class="fst-normal">Dashboard</em></div>
                <h1 class="eq-dashboard-title" id="persona-title">Loading your dashboard</h1>
                <p class="eq-dashboard-subtitle" id="persona-copy">Preparing fresh learning insights for your role.</p>
            </div>
            <a href="<?php echo htmlspecialchars(url_for('logout.php')); ?>" class="btn btn-light btn-sm">Logout</a>
        </div>

        <div class="eq-dashboard-actions">
            <span class="eq-dash-pill">Hi, <?php echo htmlspecialchars((string)$user['name']); ?></span>
            <div id="role-controls"></div>
        </div>

        <div class="eq-dash-hero-strip" id="hero-stats"></div>
    </section>

    <div class="eq-section-head">
        <div>
            <h2>Celebration Board</h2>
            <p>Quick wins, signals, and encouragement pulled from the latest dashboard data.</p>
        </div>
        <span class="eq-section-badge">Kudos</span>
    </div>
    <section class="eq-dash-celebration" id="kudo-strip"></section>

    <?php
    $learningPanels = [
        'tests' => ['label' => 'Tests', 'icon' => '📝', 'empty' => 'No unlocked tests yet. Use the test catalogue to buy or subscribe free tests, then attempt them here.'],
        'materials' => ['label' => 'Study Material', 'icon' => '📚', 'empty' => 'No unlocked study material yet. Use the study material catalogue to get free or paid material first.'],
        'papers' => ['label' => 'Practice Papers', 'icon' => '📄', 'empty' => 'No unlocked practice papers yet. Subscribe or purchase papers from the catalogue first.'],
        'videos' => ['label' => 'Premium Videos', 'icon' => '▶', 'empty' => 'No dashboard videos are available yet. Premium lessons added by the backend will appear here.'],
        'articles' => ['label' => 'Premium Articles', 'icon' => '✦', 'empty' => 'No dashboard articles are available yet. Premium reading added by the backend will appear here.'],
    ];
    ?>
    <section class="eq-learning-hub" id="learning-hub">
        <div class="eq-learning-top">
            <div>
                <h2>Learning Hub</h2>
                <p>Open every unlocked learning item from one dashboard: tests, study material, practice papers, premium articles, and premium videos. Catalogue pages stay focused on purchase and free subscription.</p>
            </div>
            <div class="eq-learning-actions">
                <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars(url_for('tests.php')); ?>">Get Tests</a>
                <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars(url_for('study-material.php')); ?>">Get Study Material</a>
            </div>
        </div>

        <?php if (($user['role'] ?? '') === 'student'): ?>
            <div class="eq-learning-statline">
                <?php foreach ($learningPanels as $panelKey => $panel): ?>
                    <div class="eq-learning-stat">
                        <strong><?php echo (int)($learningHub['counts'][$panelKey] ?? 0); ?></strong>
                        <span><?php echo htmlspecialchars($panel['label']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="eq-learning-tabs" role="tablist" aria-label="Learning Hub">
                <?php $firstLearningTab = true; ?>
                <?php foreach ($learningPanels as $panelKey => $panel): ?>
                    <button class="eq-learning-tab<?php echo $firstLearningTab ? ' active' : ''; ?>" type="button" data-learning-tab="<?php echo htmlspecialchars($panelKey); ?>">
                        <span><?php echo htmlspecialchars($panel['icon']); ?></span>
                        <span><?php echo htmlspecialchars($panel['label']); ?></span>
                        <small><?php echo (int)($learningHub['counts'][$panelKey] ?? 0); ?></small>
                    </button>
                    <?php $firstLearningTab = false; ?>
                <?php endforeach; ?>
            </div>

            <?php $firstLearningPanel = true; ?>
            <?php foreach ($learningPanels as $panelKey => $panel): ?>
                <div class="eq-learning-panel<?php echo $firstLearningPanel ? ' active' : ''; ?>" data-learning-panel="<?php echo htmlspecialchars($panelKey); ?>">
                    <?php if (!empty($learningHub[$panelKey])): ?>
                        <div class="eq-learning-grid">
                            <?php foreach ($learningHub[$panelKey] as $item): ?>
                                <article class="eq-learning-card">
                                    <?php if ($panelKey === 'videos'): ?>
                                        <div class="eq-learning-card-media">
                                            <img src="<?php echo htmlspecialchars((string)$item['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars((string)$item['title']); ?>">
                                        </div>
                                    <?php elseif ($panelKey === 'articles' && !empty($item['image_url'])): ?>
                                        <div class="eq-learning-card-media">
                                            <img src="<?php echo htmlspecialchars((string)$item['image_url']); ?>" alt="<?php echo htmlspecialchars((string)$item['title']); ?>">
                                        </div>
                                    <?php endif; ?>

                                    <div class="eq-learning-card-head">
                                        <div class="eq-learning-icon"><?php echo htmlspecialchars($panel['icon']); ?></div>
                                        <span class="eq-learning-badge"><?php echo htmlspecialchars((string)($item['badge'] ?? $panel['label'])); ?></span>
                                    </div>
                                    <div>
                                        <h3><?php echo htmlspecialchars((string)$item['title']); ?></h3>
                                        <div class="meta"><?php echo htmlspecialchars((string)($item['meta'] ?? '')); ?></div>
                                    </div>
                                    <p><?php echo htmlspecialchars((string)($item['excerpt'] ?? 'Ready to open from your dashboard.')); ?></p>
                                    <div class="eq-learning-card-footer">
                                        <?php if ($panelKey === 'videos'): ?>
                                            <button
                                                class="btn btn-primary btn-sm"
                                                type="button"
                                                data-learning-video
                                                data-title="<?php echo htmlspecialchars((string)$item['title']); ?>"
                                                data-embed="<?php echo htmlspecialchars((string)$item['embed_url']); ?>"
                                            >Watch here</button>
                                        <?php elseif ($panelKey === 'articles'): ?>
                                            <button
                                                class="btn btn-primary btn-sm"
                                                type="button"
                                                data-learning-article
                                                data-title="<?php echo htmlspecialchars((string)$item['title']); ?>"
                                                data-template="learning-article-<?php echo (int)$item['id']; ?>"
                                            >Read here</button>
                                            <div class="d-none" id="learning-article-<?php echo (int)$item['id']; ?>"><?php echo (string)$item['content_html']; ?></div>
                                        <?php else: ?>
                                            <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars((string)$item['url']); ?>"><?php echo htmlspecialchars((string)$item['label']); ?></a>
                                            <?php if (!empty($item['report_url'])): ?>
                                                <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars((string)$item['report_url']); ?>">View report</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="eq-learning-empty">
                            <strong><?php echo htmlspecialchars($panel['label']); ?></strong>
                            <span><?php echo htmlspecialchars($panel['empty']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php $firstLearningPanel = false; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="eq-learning-empty">
                <strong>Student reading opens from student dashboard</strong>
                <span>Teachers, parents, and admins keep their analytics here. Students will see their unlocked material, papers, tests, premium videos, and premium articles in this Learning Hub.</span>
            </div>
        <?php endif; ?>
    </section>

    <div class="eq-section-head">
        <div>
            <h2>Today’s Snapshot</h2>
            <p>The core numbers that matter most for this role, shown as clear action tiles.</p>
        </div>
        <span class="eq-section-badge">Live metrics</span>
    </div>
    <div class="row g-3 mb-4" id="metric-cards"></div>

    <div class="eq-section-head">
        <div>
            <h2>Visual Pulse</h2>
            <p>Mini graphs for momentum, distribution, and comparison before the detailed charts.</p>
        </div>
        <span class="eq-section-badge">Mini charts</span>
    </div>
    <section class="eq-insight-grid" id="insight-charts">
        <div class="eq-insight-card">
            <div class="eq-insight-head">
                <div>
                    <h4>Momentum Trail</h4>
                    <p>How the main signals are moving together</p>
                </div>
                <span class="eq-insight-icon">↗</span>
            </div>
            <div class="eq-mini-chart"><canvas id="sparkChart" height="130"></canvas></div>
        </div>
        <div class="eq-insight-card">
            <div class="eq-insight-head">
                <div>
                    <h4>Focus Wheel</h4>
                    <p>Distribution of the most important metrics</p>
                </div>
                <span class="eq-insight-icon">◐</span>
            </div>
            <div class="eq-mini-chart"><canvas id="mixChart" height="130"></canvas></div>
        </div>
        <div class="eq-insight-card">
            <div class="eq-insight-head">
                <div>
                    <h4>Action Ladder</h4>
                    <p>Fast comparison for the next priority</p>
                </div>
                <span class="eq-insight-icon">▦</span>
            </div>
            <div class="eq-mini-chart"><canvas id="barMiniChart" height="130"></canvas></div>
        </div>
    </section>

    <div class="eq-section-head">
        <div>
            <h2>Performance Studio</h2>
            <p>The main performance chart and a readable focus summary side by side.</p>
        </div>
        <span class="eq-section-badge">Deep view</span>
    </div>
    <div class="eq-dash-grid mb-4">
        <section class="eq-dash-panel">
            <div class="eq-dash-panel-title">
                <div>
                    <h3 id="primary-chart-title">Loading...</h3>
                    <p id="primary-chart-subtitle">Performance view</p>
                </div>
                <span class="eq-dash-pill" id="primary-chart-badge">Live</span>
            </div>
            <div class="eq-chart-frame">
                <canvas id="primaryChart" height="150"></canvas>
            </div>
        </section>

        <aside class="eq-dash-panel">
            <div class="eq-dash-panel-title">
                <div>
                    <h4 id="focus-title">Focus score</h4>
                    <p id="focus-subtitle">Role based summary</p>
                </div>
            </div>
            <div class="eq-ring-wrap">
                <div class="eq-score-ring" id="focus-ring"><span id="focus-score">0</span></div>
                <div>
                    <strong id="focus-label">Current snapshot</strong>
                    <p class="eq-section-text mb-0" id="focus-copy">Insights will appear once data loads.</p>
                </div>
            </div>
            <ul class="eq-focus-list" id="highlights-list"></ul>
        </aside>
    </div>

    <div class="eq-section-head">
        <div>
            <h2>Comparison Room</h2>
            <p>Secondary graph and community activity, shown only where the role has those signals.</p>
        </div>
        <span class="eq-section-badge">Context</span>
    </div>
    <div class="eq-dash-grid mb-4">
        <section class="eq-dash-panel" id="secondary-chart-panel">
            <div class="eq-dash-panel-title">
                <div>
                    <h4 id="secondary-chart-title">Progress</h4>
                    <p id="secondary-chart-subtitle">Comparison and movement</p>
                </div>
                <span class="eq-dash-pill">Compare</span>
            </div>
            <div class="eq-chart-frame">
                <canvas id="secondaryChart" height="140"></canvas>
            </div>
        </section>

        <aside class="eq-dash-panel" id="community-panel">
            <div class="eq-dash-panel-title">
                <div>
                    <h4>Community Sparks</h4>
                    <p>Recent learning conversations and shared moments</p>
                </div>
            </div>
            <div id="community-feed"></div>
        </aside>
    </div>

    <div class="eq-section-head">
        <div>
            <h2>Role Workbench</h2>
            <p>Lists, rankings, reports, and tables tailored to the signed-in persona.</p>
        </div>
        <span class="eq-section-badge">Data cards</span>
    </div>
    <div class="eq-widget-grid mb-4" id="dynamic-widgets"></div>

    <div class="eq-section-head">
        <div>
            <h2>Next Best Actions</h2>
            <p>Practical recommendations generated from the dashboard’s learning signals.</p>
        </div>
        <span class="eq-section-badge">Action plan</span>
    </div>
    <div class="eq-widget-grid mb-4" id="role-sections"></div>

    <div class="eq-section-head" id="achievements-heading">
        <div>
            <h2>Milestone Wall</h2>
            <p>Recent achievements, recognition, and progress moments.</p>
        </div>
        <span class="eq-section-badge">Rewards</span>
    </div>
    <section class="eq-dash-panel" id="achievements-panel">
        <div class="eq-dash-panel-title">
            <div>
                <h4>Badge Shelf</h4>
                <p>Recognition, progress moments, and celebration history</p>
            </div>
        </div>
        <ul class="eq-clean-list" id="achievements-list"></ul>
    </section>
</div>

<div class="modal fade" id="learningArticleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="learningArticleTitle">Article</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="eq-learning-reader" id="learningArticleBody"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="learningVideoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="learningVideoTitle">Video Lesson</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="ratio ratio-16x9">
                    <iframe id="learningVideoFrame" src="" title="Premium video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const tabButtons = document.querySelectorAll('[data-learning-tab]');
        const panels = document.querySelectorAll('[data-learning-panel]');
        tabButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const target = button.getAttribute('data-learning-tab');
                tabButtons.forEach(function (item) {
                    item.classList.toggle('active', item === button);
                });
                panels.forEach(function (panel) {
                    panel.classList.toggle('active', panel.getAttribute('data-learning-panel') === target);
                });
            });
        });

        const articleModalEl = document.getElementById('learningArticleModal');
        const articleTitle = document.getElementById('learningArticleTitle');
        const articleBody = document.getElementById('learningArticleBody');
        const articleModal = articleModalEl && window.bootstrap ? new bootstrap.Modal(articleModalEl) : null;
        document.querySelectorAll('[data-learning-article]').forEach(function (button) {
            button.addEventListener('click', function () {
                const template = document.getElementById(button.getAttribute('data-template') || '');
                articleTitle.textContent = button.getAttribute('data-title') || 'Article';
                articleBody.innerHTML = template ? template.innerHTML : '';
                if (articleModal) {
                    articleModal.show();
                }
            });
        });

        const videoModalEl = document.getElementById('learningVideoModal');
        const videoTitle = document.getElementById('learningVideoTitle');
        const videoFrame = document.getElementById('learningVideoFrame');
        const videoModal = videoModalEl && window.bootstrap ? new bootstrap.Modal(videoModalEl) : null;
        document.querySelectorAll('[data-learning-video]').forEach(function (button) {
            button.addEventListener('click', function () {
                videoTitle.textContent = button.getAttribute('data-title') || 'Video Lesson';
                videoFrame.src = (button.getAttribute('data-embed') || '') + '?autoplay=1&rel=0';
                if (videoModal) {
                    videoModal.show();
                }
            });
        });
        if (videoModalEl) {
            videoModalEl.addEventListener('hidden.bs.modal', function () {
                videoFrame.src = '';
            });
        }
    })();

    (function () {
        const dashboardUser = <?php echo json_encode([
            'name' => (string)$user['name'],
            'role' => (string)$user['role'],
        ], JSON_UNESCAPED_SLASHES); ?>;
        const apiUrl = <?php echo json_encode(url_for('api/dashboard_data.php') . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')); ?>;
        const ctxPrimary = document.getElementById('primaryChart').getContext('2d');
        const ctxSecondary = document.getElementById('secondaryChart').getContext('2d');
        const ctxSpark = document.getElementById('sparkChart').getContext('2d');
        const ctxMix = document.getElementById('mixChart').getContext('2d');
        const ctxBarMini = document.getElementById('barMiniChart').getContext('2d');
        let primaryChart, secondaryChart, sparkChart, mixChart, barMiniChart;

        const personaMap = {
            student: {
                kicker: 'Student cockpit',
                title: 'Your learning progress, made visual',
                copy: 'Track recent tests, attribute strength, peer comparison, and the next skill to sharpen.',
                focusTitle: 'Learning momentum',
                focusSubtitle: 'Your current progress signal',
                chartSubtitles: ['Latest test movement', 'Skill and peer comparison'],
                kudos: ['🎯 Practice streak', '🌟 Skill spotlight', '🏆 Next milestone', '✨ Smart revision'],
                palette: ['#4374ff', '#13b8a6', '#ffb23f', '#e85b6b']
            },
            parent: {
                kicker: 'Parent view',
                title: 'A clearer window into your child’s growth',
                copy: 'See test patterns, skill coverage, and progress snapshots without digging through reports.',
                focusTitle: 'Child progress',
                focusSubtitle: 'Family learning snapshot',
                chartSubtitles: ['Skill strength by attribute', 'Recent test performance'],
                kudos: ['💛 Family support', '⭐ Growth moment', '🎁 Weekly reward', '📌 Parent focus'],
                palette: ['#e85b6b', '#3fc1c9', '#ffd166', '#6c63ff']
            },
            teacher: {
                kicker: 'Teacher desk',
                title: 'Class performance you can act on',
                copy: 'Spot test averages, course completion, top learners, and where the next intervention belongs.',
                focusTitle: 'Class pulse',
                focusSubtitle: 'Teaching action summary',
                chartSubtitles: ['Assessment averages', 'Course completion'],
                kudos: ['👏 Class kudos', '📚 Lesson pulse', '🚀 Improvement lift', '✅ Feedback loop'],
                palette: ['#f59f22', '#4374ff', '#23b26d', '#ef476f']
            },
            school_admin: {
                kicker: 'School command center',
                title: 'School-wide learning health at a glance',
                copy: 'Compare grades, students, tests attempted, and SIRA movement across the full school.',
                focusTitle: 'School SIRA pulse',
                focusSubtitle: 'Institution progress summary',
                chartSubtitles: ['Class participation and attempts', 'SIRA by class'],
                kudos: ['🏫 School pulse', '🥇 Grade wins', '📈 Growth lane', '🎉 Cohort moment'],
                palette: ['#0b8f78', '#4374ff', '#ffbf3f', '#8b5cf6']
            },
            content_admin: {
                kicker: 'Content operations',
                title: 'Platform content and skill signals together',
                copy: 'Monitor content records, test volume, users, attempts, and average skill distribution.',
                focusTitle: 'Platform health',
                focusSubtitle: 'Operations snapshot',
                chartSubtitles: ['Skill distribution', 'Platform activity'],
                kudos: ['🧩 Content lift', '📊 Skill signal', '🛠 Action queue', '✨ Quality moment'],
                palette: ['#6946e8', '#28c0a9', '#f5a623', '#ef476f']
            },
            super_admin: {
                kicker: 'Admin cockpit',
                title: 'EduquestIQ growth and activity center',
                copy: 'Watch users, active accounts, tests, courses, attempts, and skill distribution from one place.',
                focusTitle: 'Platform health',
                focusSubtitle: 'Operations snapshot',
                chartSubtitles: ['Skill distribution', 'Platform activity'],
                kudos: ['🚀 Platform lift', '📈 Growth spark', '🏆 Top signal', '🎉 Celebrate progress'],
                palette: ['#6946e8', '#28c0a9', '#f5a623', '#ef476f']
            }
        };

        const persona = personaMap[dashboardUser.role] || personaMap.school_admin;
        Chart.defaults.font.family = "'Manrope', sans-serif";
        Chart.defaults.color = '#667089';

        function esc(text) {
            return String(text == null ? '' : text);
        }

        function htmlEscape(text) {
            return String(text == null ? '' : text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function textInitial(label) {
            const value = esc(label).trim();
            return value ? value.charAt(0).toUpperCase() : 'E';
        }

        function metricIcon(label, index) {
            const text = esc(label).toLowerCase();
            if (/student|child|learner/.test(text)) return '🎓';
            if (/user|active/.test(text)) return '👥';
            if (/score|avg|sira|rating|progress|attribute/.test(text)) return '📈';
            if (/test|attempt|exam/.test(text)) return '📝';
            if (/course|lesson|content/.test(text)) return '📚';
            if (/grade|class/.test(text)) return '🏫';
            return ['✨', '🎯', '🏆', '🚀'][index % 4] || textInitial(label);
        }

        function metricNumber(value) {
            const match = esc(value).replace(/,/g, '').match(/-?\d+(\.\d+)?/);
            return match ? Number(match[0]) : 0;
        }

        function pickFocusMetric(metrics) {
            const preferred = (metrics || []).find(function (metric) {
                return /score|avg|sira|rating|progress|attribute/i.test(esc(metric.label));
            });
            return preferred || (metrics || [])[0] || { label: 'Progress', value: 0 };
        }

        function clampPercent(value) {
            return Math.max(0, Math.min(100, Number.isFinite(value) ? value : 0));
        }

        function miniChartConfig(type, labels, values, palette) {
            const config = {
                type,
                data: {
                    labels,
                    datasets: [{
                        label: 'Dashboard signal',
                        data: values,
                        borderColor: palette[0],
                        backgroundColor: type === 'doughnut'
                            ? palette.map(function (color) { return color + 'd9'; })
                            : palette[0] + '26',
                        borderWidth: type === 'line' ? 3 : 0,
                        tension: 0.4,
                        fill: type === 'line',
                        borderRadius: type === 'bar' ? 8 : 0,
                        maxBarThickness: 28,
                        pointRadius: type === 'line' ? 4 : 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: type === 'doughnut', position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                        tooltip: {
                            backgroundColor: '#121731',
                            titleColor: '#fff',
                            bodyColor: '#eef2ff',
                            cornerRadius: 8,
                            padding: 10
                        }
                    },
                    scales: {
                        x: { display: type !== 'doughnut', grid: { display: false } },
                        y: { display: type !== 'doughnut', beginAtZero: true, grid: { color: 'rgba(32,49,109,0.07)' } }
                    },
                    cutout: type === 'doughnut' ? '68%' : undefined
                }
            };
            if (type === 'doughnut') {
                delete config.options.scales;
            }
            return config;
        }

        function makeHeroStats(metrics) {
            const wrap = document.getElementById('hero-stats');
            const items = (metrics || []).slice(0, 3);
            wrap.innerHTML = '';
            if (!items.length) {
                items.push({ label: 'Status', value: 'Ready' });
            }
            items.forEach(function (metric) {
                const node = document.createElement('div');
                node.className = 'eq-dash-hero-stat';
                node.innerHTML = '<strong>' + htmlEscape(metric.value) + '</strong><span>' + htmlEscape(metric.label) + '</span>';
                wrap.appendChild(node);
            });
        }

        function renderKudos(data) {
            const strip = document.getElementById('kudo-strip');
            const metrics = data.metrics || [];
            const highlights = data.highlights || [];
            const focusMetric = pickFocusMetric(metrics);
            const labels = persona.kudos || ['✨ Great work', '🎯 Focus', '🏆 Milestone', '📈 Growth'];
            strip.innerHTML = labels.map(function (label, index) {
                const metric = metrics[index % Math.max(metrics.length, 1)] || focusMetric;
                const highlight = highlights[index % Math.max(highlights.length, 1)] || 'Keep the learning loop moving.';
                const parts = label.split(' ');
                const icon = parts.shift() || '✨';
                const title = parts.join(' ') || 'Kudo';
                const text = index === 0 ? esc(metric.label) + ': ' + esc(metric.value) : highlight;
                return [
                    '<article class="eq-kudo-card">',
                    '<div class="eq-kudo-icon">' + htmlEscape(icon) + '</div>',
                    '<div><strong>' + htmlEscape(title) + '</strong><span>' + htmlEscape(text) + '</span></div>',
                    '</article>'
                ].join('');
            }).join('');
        }

        function chartConfig(rawConfig, palette) {
            const config = JSON.parse(JSON.stringify(rawConfig || { type: 'bar', data: { labels: [], datasets: [] } }));
            const datasets = ((config.data || {}).datasets || []);
            datasets.forEach(function (dataset, index) {
                const color = palette[index % palette.length];
                if (!dataset.borderColor) dataset.borderColor = color;
                if (!dataset.backgroundColor) dataset.backgroundColor = color + 'b3';
                if (Array.isArray(dataset.backgroundColor)) return;
                if (config.type === 'line') {
                    dataset.backgroundColor = color + '24';
                    dataset.borderWidth = dataset.borderWidth || 3;
                    dataset.pointRadius = dataset.pointRadius || 4;
                    dataset.pointHoverRadius = 6;
                    dataset.tension = dataset.tension == null ? 0.38 : dataset.tension;
                    dataset.fill = dataset.fill == null ? true : dataset.fill;
                }
                if (config.type === 'bar') {
                    dataset.borderRadius = dataset.borderRadius || 8;
                    dataset.maxBarThickness = dataset.maxBarThickness || 42;
                }
            });
            config.options = Object.assign({
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, boxWidth: 8, boxHeight: 8, padding: 18 }
                    },
                    tooltip: {
                        backgroundColor: '#121731',
                        titleColor: '#fff',
                        bodyColor: '#e9ecf5',
                        cornerRadius: 8,
                        padding: 12
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true } },
                    y: { beginAtZero: true, grid: { color: 'rgba(32,49,109,0.08)' } }
                }
            }, config.options || {});
            if (config.type === 'radar' || config.type === 'doughnut' || config.type === 'pie') {
                delete config.options.scales;
            }
            return config;
        }

        function scoreChips(scores) {
            if (!scores || !scores.length) {
                return '<div class="eq-empty">No recent tests yet.</div>';
            }
            return '<div class="eq-score-chips">' + scores.map(function (score) {
                return '<span class="eq-score-chip">' + htmlEscape(score) + '</span>';
            }).join('') + '</div>';
        }

        function renderControls(data) {
            const controls = document.getElementById('role-controls');
            controls.innerHTML = '';
            if (!(data.filters && Array.isArray(data.filters.grades) && data.filters.grades.length > 0)) {
                return;
            }

            const select = document.createElement('select');
            select.className = 'form-select form-select-sm';
            select.setAttribute('aria-label', 'Filter class');
            select.style.minWidth = '190px';
            const allOption = document.createElement('option');
            allOption.value = '';
            allOption.textContent = 'All classes';
            select.appendChild(allOption);
            data.filters.grades.forEach(function (grade) {
                const option = document.createElement('option');
                option.value = grade;
                option.textContent = grade;
                option.selected = data.filters.selectedGrade && data.filters.selectedGrade === grade;
                select.appendChild(option);
            });
            select.addEventListener('change', function () {
                const next = new URL(window.location.href);
                if (select.value) {
                    next.searchParams.set('grade', select.value);
                } else {
                    next.searchParams.delete('grade');
                }
                window.location.href = next.pathname + (next.search ? next.search : '');
            });
            controls.appendChild(select);
        }

        function renderMetrics(metricsData) {
            const metrics = document.getElementById('metric-cards');
            metrics.innerHTML = '';
            (metricsData || []).forEach(function (m, index) {
                const col = document.createElement('div');
                col.className = 'col-6 col-xl-3';
                col.innerHTML = [
                    '<div class="eq-dash-metric">',
                    '<div class="eq-dash-metric-icon">' + htmlEscape(metricIcon(m.label, index)) + '</div>',
                    '<div class="eq-dash-metric-label">' + htmlEscape(m.label || '') + '</div>',
                    '<div class="eq-dash-metric-value">' + htmlEscape(m.value || 0) + '</div>',
                    '</div>'
                ].join('');
                metrics.appendChild(col);
            });
        }

        function renderMiniCharts(data) {
            const metrics = data.metrics || [];
            const labels = metrics.map(function (metric) { return esc(metric.label || 'Signal'); }).slice(0, 6);
            const values = metrics.map(function (metric) { return Math.max(metricNumber(metric.value), 0); }).slice(0, 6);
            while (labels.length < 3) {
                labels.push(['Momentum', 'Focus', 'Action'][labels.length]);
                values.push([35, 62, 84][values.length] || 40);
            }
            const sparkValues = values.map(function (value, index) {
                return Math.max(4, value + (index * 7) + 8);
            });
            if (sparkChart) sparkChart.destroy();
            if (mixChart) mixChart.destroy();
            if (barMiniChart) barMiniChart.destroy();
            sparkChart = new Chart(ctxSpark, miniChartConfig('line', labels, sparkValues, persona.palette));
            mixChart = new Chart(ctxMix, miniChartConfig('doughnut', labels.slice(0, 4), values.slice(0, 4), persona.palette));
            barMiniChart = new Chart(ctxBarMini, miniChartConfig('bar', labels, values, persona.palette.slice().reverse()));
        }

        function renderHighlights(data) {
            const focusMetric = pickFocusMetric(data.metrics || []);
            const focusValue = clampPercent(metricNumber(focusMetric.value));
            document.getElementById('focus-title').textContent = persona.focusTitle;
            document.getElementById('focus-subtitle').textContent = persona.focusSubtitle;
            document.getElementById('focus-ring').style.setProperty('--ring-value', focusValue + '%');
            document.getElementById('focus-score').textContent = Math.round(focusValue);
            document.getElementById('focus-label').textContent = esc(focusMetric.label || 'Current snapshot');
            document.getElementById('focus-copy').textContent = 'Based on the latest dashboard data available for ' + dashboardUser.name + '.';

            const highlightsList = document.getElementById('highlights-list');
            highlightsList.innerHTML = '';
            (data.highlights || []).forEach(function (item) {
                const li = document.createElement('li');
                li.textContent = item;
                highlightsList.appendChild(li);
            });
            if (!highlightsList.children.length) {
                const li = document.createElement('li');
                li.textContent = 'Fresh insights will appear after activity starts.';
                highlightsList.appendChild(li);
            }
        }

        function renderAchievements(items) {
            const list = document.getElementById('achievements-list');
            list.innerHTML = '';
            if (!(items || []).length) {
                const li = document.createElement('li');
                li.textContent = 'No achievements yet.';
                list.appendChild(li);
                return;
            }
            items.forEach(function (a) {
                const li = document.createElement('li');
                li.textContent = esc(a.title) + (a.description ? ' - ' + esc(a.description) : '');
                list.appendChild(li);
            });
        }

        function renderFeed(items) {
            const feed = document.getElementById('community-feed');
            feed.innerHTML = '';
            if (!(items || []).length) {
                feed.innerHTML = '<div class="eq-empty">No posts yet.</div>';
                return;
            }
            items.forEach(function (post) {
                const item = document.createElement('div');
                item.className = 'eq-feed-item';
                item.innerHTML = '<span class="eq-feed-user">' + htmlEscape(post.user) + '</span>' + htmlEscape(post.content);
                feed.appendChild(item);
            });
        }

        function listWidget(w, body) {
            const list = document.createElement('div');
            list.className = 'eq-data-list';
            (w.items || []).forEach(function (item) {
                const row = document.createElement('div');
                row.className = 'eq-data-row';
                if (typeof item === 'string') {
                    row.innerHTML = '<strong>' + htmlEscape(item) + '</strong>';
                } else {
                    const title = item.link
                        ? '<a href="' + htmlEscape(item.link) + '" class="text-decoration-none fw-bold">' + htmlEscape(item.primary || item.title || '') + '</a>'
                        : '<strong>' + htmlEscape(item.primary || item.title || '') + '</strong>';
                    row.innerHTML = '<div>' + title + (item.secondary ? '<span>' + htmlEscape(item.secondary) + '</span>' : '') + '</div>' +
                        (item.secondary ? '<div class="eq-data-value">' + htmlEscape(item.secondary) + '</div>' : '');
                }
                list.appendChild(row);
            });
            if (!(w.items || []).length) {
                list.innerHTML = '<div class="eq-empty">' + htmlEscape(w.emptyText || 'No data') + '</div>';
            }
            body.appendChild(list);
        }

        function tableWidget(w, body) {
            const tableWrap = document.createElement('div');
            tableWrap.className = 'table-responsive';
            const table = document.createElement('table');
            table.className = 'table eq-dash-table align-middle';
            const thead = document.createElement('thead');
            const headRow = document.createElement('tr');
            (w.headers || []).forEach(function (heading) {
                const th = document.createElement('th');
                th.scope = 'col';
                th.textContent = heading;
                headRow.appendChild(th);
            });
            thead.appendChild(headRow);
            const tbody = document.createElement('tbody');
            (w.rows || []).forEach(function (row) {
                const tr = document.createElement('tr');
                row.forEach(function (cell) {
                    const td = document.createElement('td');
                    td.textContent = cell;
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
            if (!(w.rows || []).length) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = (w.headers || []).length || 1;
                td.className = 'text-muted';
                td.textContent = esc(w.emptyText || 'No data');
                tr.appendChild(td);
                tbody.appendChild(tr);
            }
            table.appendChild(thead);
            table.appendChild(tbody);
            tableWrap.appendChild(table);
            body.appendChild(tableWrap);
        }

        function studentCardsWidget(w, body) {
            const wrap = document.createElement('div');
            (w.items || []).forEach(function (item) {
                const tile = document.createElement('div');
                tile.className = 'eq-student-tile';
                const action = item.link ? '<a href="' + htmlEscape(item.link) + '" class="btn btn-sm btn-outline-primary">' + htmlEscape(item.link_label || 'Open') + '</a>' : '';
                tile.innerHTML = [
                    '<div class="d-flex justify-content-between align-items-start gap-2">',
                    '<div><strong>' + htmlEscape(item.primary || item.title || '') + '</strong>',
                    '<div class="text-muted small">' + htmlEscape(item.secondary || '') + '</div></div>',
                    action,
                    '</div>',
                    scoreChips(item.scores || [])
                ].join('');
                wrap.appendChild(tile);
            });
            if (!(w.items || []).length) {
                wrap.innerHTML = '<div class="eq-empty">' + htmlEscape(w.emptyText || 'No data') + '</div>';
            }
            body.appendChild(wrap);
        }

        function renderWidgets(data) {
            const widgetsWrap = document.getElementById('dynamic-widgets');
            widgetsWrap.innerHTML = '';
            (data.widgets || []).forEach(function (w) {
                const panel = document.createElement('section');
                panel.className = 'eq-dash-panel';
                const body = document.createElement('div');
                const subtitle = w.type === 'table'
                    ? 'Structured comparison table'
                    : (w.type === 'studentCards' ? 'Learner cards with recent signals' : 'Role-specific records and links');
                body.innerHTML = '<div class="eq-dash-panel-title"><div><h4>' + htmlEscape(w.title || 'Workbench card') + '</h4><p>' + htmlEscape(subtitle) + '</p></div><span class="eq-dash-pill">Open</span></div>';

                if (w.type === 'list') {
                    listWidget(w, body);
                } else if (w.type === 'table') {
                    tableWidget(w, body);
                } else if (w.type === 'studentCards') {
                    studentCardsWidget(w, body);
                } else if (w.type === 'text') {
                    const p = document.createElement('p');
                    p.className = 'eq-section-text mb-0';
                    p.textContent = esc(w.content || '');
                    body.appendChild(p);
                }
                panel.appendChild(body);
                widgetsWrap.appendChild(panel);
            });
        }

        function renderRoleSections(data) {
            const sections = document.getElementById('role-sections');
            sections.innerHTML = '';
            (data.roleSections || []).forEach(function (section) {
                const panel = document.createElement('section');
                panel.className = 'eq-dash-panel';
                const body = document.createElement('div');
                body.innerHTML = '<div class="eq-dash-panel-title"><div><h4>' + htmlEscape(section.title || 'Action card') + '</h4><p>Clear next step from the learning signals</p></div><span class="eq-dash-pill">Do next</span></div>';

                (section.paragraphs || []).forEach(function (para) {
                    const p = document.createElement('p');
                    p.className = 'eq-section-text';
                    p.textContent = esc(para);
                    body.appendChild(p);
                });

                if (section.items && section.items.length) {
                    const ul = document.createElement('ul');
                    ul.className = 'eq-clean-list';
                    section.items.forEach(function (item) {
                        const li = document.createElement('li');
                        li.textContent = esc(item);
                        ul.appendChild(li);
                    });
                    body.appendChild(ul);
                }
                panel.appendChild(body);
                sections.appendChild(panel);
            });
        }

        function renderCharts(data) {
            ['community-panel', 'achievements-panel', 'achievements-heading'].forEach(function (id) {
                const panel = document.getElementById(id);
                if (panel) panel.classList.remove('d-none');
            });
            (data.hideSections || []).forEach(function (id) {
                const panel = document.getElementById(id);
                if (panel) panel.classList.add('d-none');
                if (id === 'achievements-panel') {
                    const heading = document.getElementById('achievements-heading');
                    if (heading) heading.classList.add('d-none');
                }
            });

            document.getElementById('persona-kicker').textContent = persona.kicker;
            document.getElementById('persona-title').textContent = persona.title;
            document.getElementById('persona-copy').textContent = persona.copy;
            document.getElementById('primary-chart-title').textContent = data.primaryChartTitle || 'Overview';
            document.getElementById('secondary-chart-title').textContent = data.secondaryChartTitle || 'Progress';
            document.getElementById('primary-chart-subtitle').textContent = persona.chartSubtitles[0];
            document.getElementById('secondary-chart-subtitle').textContent = persona.chartSubtitles[1];
            document.getElementById('primary-chart-badge').textContent = dashboardUser.role.replace(/_/g, ' ');

            renderControls(data);
            renderKudos(data);
            renderMetrics(data.metrics || []);
            makeHeroStats(data.metrics || []);
            renderHighlights(data);
            renderAchievements(data.recentAchievements || []);
            renderFeed(data.communityFeed || []);
            renderWidgets(data);
            renderRoleSections(data);
            renderMiniCharts(data);

            if (primaryChart) primaryChart.destroy();
            if (secondaryChart) secondaryChart.destroy();
            primaryChart = new Chart(ctxPrimary, chartConfig(data.primaryChart, persona.palette));
            secondaryChart = new Chart(ctxSecondary, chartConfig(data.secondaryChart, persona.palette.slice().reverse()));
        }

        $.getJSON(apiUrl)
            .done(function (response) {
                renderCharts(response);
            })
            .fail(function () {
                document.getElementById('persona-title').textContent = 'Dashboard data could not load';
                document.getElementById('persona-copy').textContent = 'Please refresh the page or sign in again.';
            });
    })();
</script>

<?php
require_once __DIR__ . '/includes_footer.php';
