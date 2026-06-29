<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';
$user = require_auth(['student', 'school_admin', 'content_admin', 'super_admin']);
require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_sira.php';
$attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
$pdo = get_pdo();
$report = $attemptId > 0 ? sira_build_test_report($pdo, $attemptId) : null;
$reportError = null;

if ($attemptId <= 0) {
    $reportError = 'No attempt was selected.';
} elseif (!$report) {
    $reportError = 'We could not find a detailed SIRA report for this attempt yet.';
} else {
    $userRole = (string)($user['role'] ?? '');
    $canViewReport = false;
    if ($userRole === 'student') {
        $canViewReport = (int)$report['attempt']['student_id'] === (int)$user['sub'];
    } elseif (in_array($userRole, ['content_admin', 'super_admin'], true)) {
        $canViewReport = true;
    } elseif ($userRole === 'school_admin') {
        $canViewReport = (int)($report['attempt']['student_school_id'] ?? 0) > 0
            && (int)($report['attempt']['student_school_id'] ?? 0) === (int)($user['school_id'] ?? 0);
    }
    if (!$canViewReport) {
        $reportError = 'You do not have access to this SIRA report.';
    }
}

if ($reportError !== null) {
    ?>
    <div class="eq-page-head">
        <h2>SIRA Report</h2>
        <p class="subtitle">Detailed test reports open here after each submission.</p>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="alert alert-warning mb-0">
                <?php echo htmlspecialchars($reportError); ?>
                <div class="mt-2">
                    <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(url_for('tests.php')); ?>">Back to tests</a>
                </div>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/includes_footer.php';
    return;
}

$statusCounts = $report['status_counts'];
$overallScore = (float)$report['overall_score'];
$overallBand = sira_band($overallScore);
$comparison = $report['comparison'];
$accuracy = $report['accuracy_percent'];
$completion = (float)$report['completion_percent'];

function sira_report_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function sira_rank_medal(?int $rank): string
{
    if ($rank === null || $rank <= 0) {
        return '🎖️';
    }
    if ($rank === 1) {
        return '🥇';
    }
    if ($rank === 2) {
        return '🥈';
    }
    if ($rank === 3) {
        return '🥉';
    }
    return '🏅';
}

$overallRank = $comparison['overall']['rank'] !== null ? (int)$comparison['overall']['rank'] : null;
$certificateUrl = url_for('sira_certificate.php?attempt_id=' . (int)$attemptId);
?>

<style>
    .eq-sira-report-shell {
        display: grid;
        gap: 24px;
        --sira-accent: #6946e8;
        --sira-accent-2: #28c0a9;
        --sira-accent-3: #f5a623;
        --sira-accent-4: #ff5d8f;
        --sira-ink: #121731;
        --sira-soft: #667089;
        --sira-line: rgba(30, 43, 92, 0.1);
        color: var(--sira-ink);
        min-width: 0;
    }
    .eq-sira-report-shell > *,
    .eq-sira-report-hero .d-flex > *,
    .eq-sira-kudo > *,
    .eq-sira-panel,
    .eq-sira-metric {
        min-width: 0;
    }
    .eq-sira-report-shell h2,
    .eq-sira-report-shell h3,
    .eq-sira-report-shell h4,
    .eq-sira-report-shell h5,
    .eq-sira-report-shell p,
    .eq-sira-report-shell span,
    .eq-sira-report-shell strong,
    .eq-sira-report-shell td,
    .eq-sira-report-shell th {
        overflow-wrap: anywhere;
    }

    .eq-sira-report-hero {
        position: relative;
        overflow: hidden;
        background:
            radial-gradient(circle at 8% 18%, rgba(255, 210, 97, 0.24), transparent 24%),
            radial-gradient(circle at 58% 0%, rgba(40, 192, 169, 0.22), transparent 28%),
            linear-gradient(135deg, rgba(18, 23, 49, 0.96), rgba(43, 71, 152, 0.93)),
            url('<?php echo htmlspecialchars(url_for('assets/img/sira-assessment-visual.png')); ?>') right center / 430px auto no-repeat;
        color: #fff;
        border-radius: 8px;
        padding: 32px;
        min-height: 310px;
        box-shadow: 0 24px 48px rgba(37, 48, 99, 0.16);
        border: 1px solid rgba(255,255,255,0.2);
    }
    .eq-sira-report-hero::before {
        content: "";
        position: absolute;
        inset: 16px;
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,0.08);
        pointer-events: none;
    }
    .eq-sira-report-hero::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, rgba(18,23,49,0.93), rgba(18,23,49,0.78) 55%, rgba(18,23,49,0.28));
        pointer-events: none;
    }
    .eq-sira-report-hero > * {
        position: relative;
        z-index: 1;
    }
    .eq-sira-report-hero h2 {
        font-size: clamp(2rem, 4vw, 3.6rem);
        font-weight: 800;
        line-height: 1.05;
        margin-bottom: 6px;
    }

    .eq-sira-hero-score {
        width: 144px;
        height: 144px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: conic-gradient(var(--sira-accent-2) <?php echo max(0, min(100, $overallScore)); ?>%, rgba(255,255,255,0.18) 0);
        box-shadow: 0 16px 30px rgba(0,0,0,0.16);
    }
    .eq-sira-hero-score span {
        width: 108px;
        height: 108px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: rgba(255,255,255,0.96);
        color: var(--sira-ink);
        font-family: 'Outfit', sans-serif;
        font-size: 1.8rem;
        font-weight: 800;
    }

    .eq-sira-section-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 14px;
    }
    .eq-sira-section-head h3 {
        margin: 0;
        color: var(--sira-ink);
        font-size: clamp(1.2rem, 2vw, 1.6rem);
        font-weight: 800;
    }
    .eq-sira-section-head p {
        margin: 4px 0 0;
        color: var(--sira-soft);
        font-size: 0.92rem;
        line-height: 1.45;
    }
    .eq-sira-section-badge,
    .eq-sira-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        background: #fff;
        border: 1px solid rgba(30,43,92,0.08);
        color: var(--sira-accent);
        font-size: 0.78rem;
        font-weight: 800;
        padding: 8px 12px;
        white-space: nowrap;
        box-shadow: 0 8px 18px rgba(37, 48, 99, 0.07);
    }
    .eq-sira-section-badge::before {
        content: "";
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--sira-accent-3);
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--sira-accent-3) 20%, transparent);
    }

    .eq-sira-kudo-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-top: -44px;
        padding: 0 18px;
        position: relative;
        z-index: 2;
    }
    .eq-sira-kudo {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: 10px;
        align-items: center;
        border-radius: 8px;
        background: rgba(255,255,255,0.96);
        border: 1px solid rgba(30,43,92,0.08);
        box-shadow: 0 16px 34px rgba(37, 48, 99, 0.12);
        padding: 14px;
    }
    .eq-sira-kudo-icon,
    .eq-sira-metric-icon,
    .eq-sira-insight-icon {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, var(--sira-accent), var(--sira-accent-4));
        color: #fff;
        font-size: 1.2rem;
        box-shadow: 0 10px 20px color-mix(in srgb, var(--sira-accent) 22%, transparent);
    }
    .eq-sira-kudo strong {
        display: block;
        font-size: 0.96rem;
        line-height: 1.15;
    }
    .eq-sira-kudo span {
        display: block;
        color: var(--sira-soft);
        font-size: 0.78rem;
        font-weight: 700;
        margin-top: 3px;
    }

    .eq-sira-report-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }
    .eq-sira-report-grid.six {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .eq-sira-metric {
        background: #fff;
        border-radius: 8px;
        padding: 18px;
        border: 1px solid var(--sira-line);
        box-shadow: 0 14px 28px rgba(37, 49, 104, 0.08);
        min-height: 148px;
        position: relative;
        overflow: hidden;
    }
    .eq-sira-metric::after {
        content: "";
        position: absolute;
        right: -34px;
        top: -34px;
        width: 92px;
        height: 92px;
        border-radius: 50%;
        background: color-mix(in srgb, var(--sira-accent) 12%, transparent);
    }
    .eq-sira-metric::before {
        content: "";
        position: absolute;
        inset: auto 18px 14px 18px;
        height: 5px;
        border-radius: 999px;
        background: linear-gradient(90deg, var(--sira-accent), var(--sira-accent-2), var(--sira-accent-3));
        opacity: 0.75;
    }
    .eq-sira-metric strong {
        display: block;
        font-family: 'Outfit', sans-serif;
        font-size: clamp(1.45rem, 2.7vw, 2.2rem);
        line-height: 1;
        margin-bottom: 4px;
        position: relative;
        z-index: 1;
    }
    .eq-sira-metric span {
        color: var(--sira-soft);
        font-size: 0.82rem;
        font-weight: 800;
        text-transform: uppercase;
        position: relative;
        z-index: 1;
    }
    .eq-sira-table td,
    .eq-sira-table th {
        vertical-align: middle;
    }
    .eq-sira-panel {
        background: #fff;
        border: 1px solid var(--sira-line);
        border-radius: 8px;
        box-shadow: 0 18px 40px rgba(37, 49, 104, 0.08);
        padding: 22px;
    }
    .eq-sira-compare-card {
        border-radius: 8px;
        background: linear-gradient(135deg, #f8faff, #fff);
        border: 1px solid var(--sira-line);
        padding: 16px;
        height: 100%;
    }
    .eq-sira-compare-card strong {
        display: block;
        font-size: 1.35rem;
        margin-bottom: 2px;
    }
    .eq-sira-insight-card {
        border-radius: 8px;
        border: 1px solid var(--sira-line);
        background: linear-gradient(135deg, #fbfcff, #fff);
        padding: 16px;
        height: 100%;
    }
    .eq-sira-insight-card strong {
        display: block;
        font-size: 1.2rem;
    }
    .eq-sira-attribute-card {
        background: linear-gradient(135deg, #f9fbff, #fff);
        border: 1px solid var(--sira-line);
        border-radius: 8px;
        padding: 16px;
        height: 100%;
    }
    .eq-sira-attribute-card .progress {
        height: 10px;
        border-radius: 999px;
    }
    .eq-sira-attribute-card .progress-bar {
        border-radius: 999px;
    }
    .eq-question-status {
        font-weight: 700;
    }
    .status-not_attempted { color: #325dde; }
    .status-not_answered { color: #e44949; }
    .status-answered { color: #1f8f53; }
    .status-marked_for_review { color: #e07a12; }
    .eq-question-list li + li {
        margin-top: 10px;
    }
    .eq-sira-chart-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 18px;
    }
    .eq-sira-mini-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }
    .eq-sira-chart-box {
        min-height: 320px;
    }
    .eq-sira-chart-box canvas {
        width: 100% !important;
        max-height: 340px;
    }
    .eq-sira-status-chip {
        display: inline-flex;
        border-radius: 999px;
        padding: 6px 10px;
        background: #f8faff;
        border: 1px solid rgba(30,43,92,0.08);
        font-weight: 800;
        font-size: 0.76rem;
    }
    .eq-rank-medal {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: linear-gradient(135deg, #fff8dc, #ffe29a);
        border: 1px solid rgba(161, 119, 19, 0.18);
        box-shadow: 0 10px 18px rgba(122, 86, 11, 0.12);
        font-size: 1.35rem;
        margin-right: 8px;
        vertical-align: middle;
    }
    .eq-certificate-card {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 18px;
        align-items: center;
        border-radius: 8px;
        border: 1px solid rgba(30,43,92,0.1);
        background:
            radial-gradient(circle at 5% 15%, rgba(245,166,35,0.18), transparent 24%),
            linear-gradient(135deg, #fff, #f8faff);
        padding: 22px;
        box-shadow: 0 18px 40px rgba(37, 49, 104, 0.08);
    }
    .eq-certificate-seal {
        width: 112px;
        height: 112px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: conic-gradient(#ffd66b, #f5a623, #fff0b8, #ffd66b);
        box-shadow: 0 16px 28px rgba(126, 83, 10, 0.18);
        color: #4f3500;
        font-size: 2rem;
        font-weight: 800;
        text-align: center;
    }
    @media (max-width: 991px) {
        .eq-sira-report-grid,
        .eq-sira-report-grid.six {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .eq-sira-kudo-grid,
        .eq-sira-mini-grid,
        .eq-sira-chart-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 575px) {
        .eq-sira-report-grid,
        .eq-sira-report-grid.six,
        .eq-sira-kudo-grid,
        .eq-sira-mini-grid,
        .eq-sira-chart-grid {
            grid-template-columns: 1fr;
        }
        .eq-sira-report-hero {
            padding: 18px;
        }
        .eq-sira-kudo-grid {
            margin-top: 0;
            padding: 0;
        }
        .eq-sira-section-head {
            display: block;
        }
        .eq-sira-section-badge {
            margin-top: 10px;
        }
        .eq-certificate-card {
            grid-template-columns: 1fr;
        }
        .eq-certificate-seal {
            width: 94px;
            height: 94px;
        }
    }
</style>

<div class="mb-3">
    <a href="<?php echo htmlspecialchars(url_for('tests.php')); ?>" class="btn btn-link">&larr; Back to tests</a>
</div>

<div class="eq-sira-report-shell">
    <section class="eq-sira-report-hero">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="eq-sira-pill mb-3">SIRA Assessment Report</div>
                <h2><?php echo htmlspecialchars($report['attempt']['student_name']); ?></h2>
                <p class="mb-1"><?php echo htmlspecialchars($report['attempt']['test_title']); ?></p>
                <div class="text-white-50 small">
                    Attempted on <?php echo htmlspecialchars((string)$report['attempt']['attempt_date']); ?>
                    <?php if (!empty($report['attempt']['student_grade'])): ?>
                        · Grade <?php echo htmlspecialchars((string)$report['attempt']['student_grade']); ?>
                    <?php endif; ?>
                    <?php if (!empty($report['attempt']['student_school_name'])): ?>
                        · <?php echo htmlspecialchars((string)$report['attempt']['student_school_name']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-center">
                <div class="eq-sira-hero-score"><span><?php echo number_format($overallScore, 1); ?>%</span></div>
                <div class="small text-white-50 mt-2"><?php echo htmlspecialchars($overallBand['label']); ?> overall</div>
            </div>
        </div>
        <p class="mt-3 mb-0"><?php echo htmlspecialchars($report['overall_message']); ?></p>
    </section>

    <section class="eq-sira-kudo-grid">
        <article class="eq-sira-kudo">
            <div class="eq-sira-kudo-icon">🎯</div>
            <div><strong>Score Moment</strong><span><?php echo number_format($overallScore, 1); ?>% overall performance</span></div>
        </article>
        <article class="eq-sira-kudo">
            <div class="eq-sira-kudo-icon">🏆</div>
            <div><strong>Rank Signal</strong><span><?php echo sira_rank_medal($overallRank); ?> #<?php echo $overallRank !== null ? (int)$overallRank : 0; ?> in this test context</span></div>
        </article>
        <article class="eq-sira-kudo">
            <div class="eq-sira-kudo-icon">✅</div>
            <div><strong>Accuracy Check</strong><span><?php echo $accuracy !== null ? number_format((float)$accuracy, 1) . '% objective accuracy' : 'Accuracy will unlock after review'; ?></span></div>
        </article>
        <article class="eq-sira-kudo">
            <div class="eq-sira-kudo-icon">🚀</div>
            <div><strong>Next Push</strong><span><?php echo number_format($completion, 1); ?>% completion rhythm</span></div>
        </article>
    </section>

    <div class="eq-sira-section-head">
        <div>
            <h3>Achievement Certificate</h3>
            <p>A printable certificate for this SIRA attempt with score, rank medal, grade, and test name.</p>
        </div>
        <span class="eq-sira-section-badge">Certificate</span>
    </div>
    <section class="eq-certificate-card">
        <div>
            <div class="eq-sira-pill mb-3">Certificate Ready</div>
            <h3 class="mb-2">Certificate of SIRA Achievement</h3>
            <p class="mb-2 text-muted">
                Awarded to <strong><?php echo htmlspecialchars((string)$report['attempt']['student_name']); ?></strong>
                for completing <strong><?php echo htmlspecialchars((string)$report['attempt']['test_title']); ?></strong>
                with <?php echo number_format($overallScore, 1); ?>% overall score.
            </p>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="eq-sira-status-chip"><?php echo sira_rank_medal($overallRank); ?> Rank <?php echo $overallRank !== null ? (int)$overallRank : 'N/A'; ?></span>
                <span class="eq-sira-status-chip"><?php echo htmlspecialchars($overallBand['label']); ?> Band</span>
                <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($certificateUrl); ?>">Open Certificate</a>
            </div>
        </div>
        <div class="eq-certificate-seal"><?php echo sira_rank_medal($overallRank); ?></div>
    </section>

    <div class="eq-sira-section-head">
        <div>
            <h3>Attempt Snapshot</h3>
            <p>Clear score, marks, answers, rank, and participation numbers from this test attempt.</p>
        </div>
        <span class="eq-sira-section-badge">Live result</span>
    </div>
    <section class="eq-sira-report-grid">
        <div class="eq-sira-metric"><strong><?php echo number_format((float)$overallScore, 1); ?>%</strong><span>Overall Score</span></div>
        <div class="eq-sira-metric"><strong><?php echo number_format((float)$report['earned_marks_total'], 1); ?> / <?php echo number_format((float)$report['total_possible_marks'], 1); ?></strong><span>Total Marks</span></div>
        <div class="eq-sira-metric"><strong><?php echo (int)$report['response_count']; ?> / <?php echo (int)$report['total_questions']; ?></strong><span>Answered Questions</span></div>
        <div class="eq-sira-metric"><strong><span class="eq-rank-medal"><?php echo sira_rank_medal($overallRank); ?></span>#<?php echo $overallRank !== null ? (int)$overallRank : 0; ?></strong><span>Overall Rank</span></div>
    </section>

    <div class="eq-sira-section-head">
        <div>
            <h3>Answer Quality</h3>
            <p>Visited, correct, incorrect, unanswered, and review status in readable tiles.</p>
        </div>
        <span class="eq-sira-section-badge">Response health</span>
    </div>
    <section class="eq-sira-report-grid">
        <div class="eq-sira-metric"><strong><?php echo (int)$report['attempted_count']; ?></strong><span>Visited / Attempted</span></div>
        <div class="eq-sira-metric"><strong><?php echo (int)$report['correct_count']; ?></strong><span>Correct</span></div>
        <div class="eq-sira-metric"><strong><?php echo (int)$report['incorrect_count']; ?></strong><span>Incorrect</span></div>
        <div class="eq-sira-metric"><strong><?php echo (int)$statusCounts['not_attempted']; ?></strong><span>Not Attempted</span></div>
    </section>

    <div class="eq-sira-section-head">
        <div>
            <h3>Completion Compass</h3>
            <p>Accuracy and completion indicators that show how strong the attempt pattern was.</p>
        </div>
        <span class="eq-sira-section-badge">Attempt pattern</span>
    </div>
    <section class="eq-sira-report-grid">
        <div class="eq-sira-metric"><strong><?php echo $accuracy !== null ? number_format((float)$accuracy, 1) . '%' : 'N/A'; ?></strong><span>Objective Accuracy</span></div>
        <div class="eq-sira-metric"><strong><?php echo number_format($completion, 1); ?>%</strong><span>Completion Rate</span></div>
        <div class="eq-sira-metric"><strong><?php echo (int)$report['unanswered_count']; ?></strong><span>Not Answered</span></div>
        <div class="eq-sira-metric"><strong><?php echo (int)$statusCounts['marked_for_review']; ?></strong><span>Marked Review</span></div>
    </section>

    <div class="eq-sira-section-head">
        <div>
            <h3>Peer Arena</h3>
            <p>Compare this attempt against overall, class, grade, school, state-grade, and state contexts.</p>
        </div>
        <span class="eq-sira-section-badge">Rank map</span>
    </div>
    <section class="eq-sira-panel">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">Comparison Cards</h5>
            <span class="eq-sira-status-chip">Same test context</span>
        </div>
        <div class="row g-3">
            <?php foreach (['overall', 'class', 'grade', 'school', 'state_grade', 'state'] as $scope): ?>
                <?php $block = $comparison[$scope]; ?>
                <div class="col-md-6 col-xl-3">
                    <div class="eq-sira-compare-card">
                        <div class="small text-muted mb-2"><?php echo htmlspecialchars($block['label']); ?> comparison</div>
                        <strong>
                            <?php if ($block['rank'] !== null): ?>
                                <span class="eq-rank-medal"><?php echo sira_rank_medal((int)$block['rank']); ?></span>Rank <?php echo (int)$block['rank']; ?> / <?php echo (int)$block['participants']; ?>
                            <?php else: ?>
                                <span class="eq-rank-medal"><?php echo sira_rank_medal(null); ?></span>Not available
                            <?php endif; ?>
                        </strong>
                        <div class="small text-muted">
                            Average:
                            <?php echo $block['average'] !== null ? number_format((float)$block['average'], 1) . '%' : 'N/A'; ?>
                        </div>
                        <div class="small text-muted">
                            Percentile:
                            <?php echo $block['percentile'] !== null ? number_format((float)$block['percentile'], 1) . 'th' : 'N/A'; ?>
                        </div>
                        <div class="small text-muted">
                            Top score:
                            <?php echo $block['top_score'] !== null ? number_format((float)$block['top_score'], 1) . '%' : 'N/A'; ?>
                        </div>
                        <div class="small text-muted">
                            Participants: <?php echo (int)$block['participants']; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if (!empty($report['insights'])): ?>
        <div class="eq-sira-section-head">
            <div>
                <h3>Performance Signals</h3>
                <p>SIRA indicators that summarize the attempt beyond the raw score.</p>
            </div>
            <span class="eq-sira-section-badge">Insights</span>
        </div>
        <section class="eq-sira-panel">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="mb-0">Signal Cards</h5>
                <span class="small text-muted">Based on this test attempt and peer context</span>
            </div>
            <div class="row g-3">
                <?php foreach ($report['insights'] as $insight): ?>
                    <div class="col-md-6 col-xl-3">
                        <div class="eq-sira-insight-card">
                            <div class="small text-muted"><?php echo htmlspecialchars((string)$insight['label']); ?></div>
                            <strong><?php echo htmlspecialchars((string)$insight['value']); ?></strong>
                            <div class="small text-muted"><?php echo htmlspecialchars((string)$insight['text']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="eq-sira-section-head">
        <div>
            <h3>Visual Score Studio</h3>
            <p>Attribute radar, question status distribution, and peer comparison in chart form.</p>
        </div>
        <span class="eq-sira-section-badge">Charts</span>
    </div>
    <section class="eq-sira-chart-grid">
        <div class="eq-sira-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Attribute Skill Map</h5>
                <span class="eq-sira-status-chip">SIRA Radar</span>
            </div>
            <div class="eq-sira-chart-box">
                <canvas id="attributeChart" height="220"></canvas>
            </div>
        </div>
        <div class="eq-sira-mini-grid">
            <section class="eq-sira-panel h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Question Status Wheel</h5>
                    <span class="eq-sira-status-chip">Status</span>
                </div>
                <div class="eq-sira-chart-box">
                    <canvas id="statusChart" height="220"></canvas>
                </div>
            </section>
            <section class="eq-sira-panel h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Peer Score Bars</h5>
                    <span class="eq-sira-status-chip">Compare</span>
                </div>
                <div class="eq-sira-chart-box">
                    <canvas id="comparisonChart" height="220"></canvas>
                </div>
            </section>
        </div>
    </section>

    <div class="eq-sira-section-head">
        <div>
            <h3>Question Journey</h3>
            <p>A scannable summary of question status before the detailed table.</p>
        </div>
        <span class="eq-sira-section-badge">Question map</span>
    </div>
    <section class="eq-sira-panel">
        <h5 class="mb-3">Status Timeline</h5>
        <ul class="list-unstyled eq-question-list mb-0">
            <?php foreach ($report['question_rows'] as $question): ?>
                <li class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="fw-bold">Q<?php echo (int)$question['number']; ?></div>
                        <div class="small text-muted"><?php echo htmlspecialchars(text_preview(strip_tags((string)$question['question_text']), 90, '...')); ?></div>
                    </div>
                    <div class="text-end">
                        <div class="eq-question-status status-<?php echo htmlspecialchars((string)$question['status']); ?>">
                            <?php echo htmlspecialchars(str_replace('_', ' ', (string)$question['status'])); ?>
                        </div>
                        <div class="small text-muted">
                            <?php
                                if ($question['earned_marks'] === null) {
                                    echo 'Pending review';
                                } else {
                                    echo number_format((float)$question['earned_marks'], 1) . ' / ' . number_format((float)$question['marks'], 1);
                                }
                            ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <div class="eq-sira-section-head">
        <div>
            <h3>Answer Review Table</h3>
            <p>Question-by-question status, result, selected response, correct answer, and marks.</p>
        </div>
        <span class="eq-sira-section-badge">Detailed review</span>
    </div>
    <section class="eq-sira-panel">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">Question Performance</h5>
            <span class="small text-muted">Includes question status, result, selected response, and marks earned.</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle eq-sira-table">
                <thead>
                    <tr>
                        <th>Q#</th>
                        <th>Question</th>
                        <th>Status</th>
                        <th>Result</th>
                        <th>Selected / Response</th>
                        <th>Correct Answer</th>
                        <th>Marks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['question_rows'] as $question): ?>
                        <tr>
                            <td class="fw-bold"><?php echo (int)$question['number']; ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars(text_preview(strip_tags((string)$question['question_text']), 96, '...')); ?></div>
                                <div class="small text-muted text-capitalize"><?php echo htmlspecialchars((string)$question['question_type']); ?></div>
                            </td>
                            <td><span class="eq-question-status status-<?php echo htmlspecialchars((string)$question['status']); ?>"><?php echo htmlspecialchars(sira_report_label((string)$question['status'])); ?></span></td>
                            <td>
                                <?php if ($question['question_type'] === 'mcq'): ?>
                                    <?php echo $question['is_correct'] ? '<span class="badge text-bg-success">Correct</span>' : (($question['status'] === 'not_attempted' || $question['status'] === 'not_answered') ? '<span class="badge text-bg-secondary">Not solved</span>' : '<span class="badge text-bg-danger">Incorrect</span>'); ?>
                                <?php else: ?>
                                    <?php echo trim((string)$question['subjective_answer']) !== '' ? '<span class="badge text-bg-warning text-dark">Pending review</span>' : '<span class="badge text-bg-secondary">Not answered</span>'; ?>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?php
                                if ($question['question_type'] === 'mcq') {
                                    echo htmlspecialchars((string)($question['selected_option_text'] ?? 'No option selected'));
                                } else {
                                    echo htmlspecialchars(text_preview(trim((string)$question['subjective_answer']), 90, '...') ?: 'No response submitted');
                                }
                                ?>
                            </td>
                            <td class="small text-muted">
                                <?php
                                if ($question['question_type'] === 'mcq') {
                                    echo htmlspecialchars((string)($question['correct_option_text'] ?? 'Not configured'));
                                } else {
                                    echo 'Teacher reviewed';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if ($question['earned_marks'] === null) {
                                    echo 'Pending / ' . number_format((float)$question['marks'], 1);
                                } else {
                                    echo number_format((float)$question['earned_marks'], 1) . ' / ' . number_format((float)$question['marks'], 1);
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="eq-sira-section-head">
        <div>
            <h3>Skill Action Plan</h3>
            <p>Attribute-wise SIRA report with progress bars, feedback, and sub-attribute signals.</p>
        </div>
        <span class="eq-sira-section-badge">Next steps</span>
    </div>
    <section class="eq-sira-panel">
        <h5 class="mb-3">Detailed Attribute Report</h5>
        <div class="row g-3">
            <?php foreach ($report['attribute_rows'] as $attr): ?>
                <?php
                    $attrScore = (float)$attr['score'];
                    $width = max(0, min(100, $attrScore));
                ?>
                <div class="col-md-6 col-xl-4">
                    <article class="eq-sira-attribute-card">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($attr['name']); ?></h6>
                                <div class="small text-muted"><?php echo htmlspecialchars($attr['label']); ?></div>
                            </div>
                            <div class="fw-bold"><?php echo number_format($attrScore, 1); ?>%</div>
                        </div>
                        <div class="progress mb-3">
                            <div class="progress-bar" style="width: <?php echo $width; ?>%"></div>
                        </div>
                        <p class="small mb-2"><?php echo htmlspecialchars($attr['message']); ?></p>
                        <?php if (!empty($attr['pending_review'])): ?>
                            <div class="badge text-bg-warning text-dark mb-2"><?php echo (int)$attr['pending_review']; ?> pending subjective review</div>
                        <?php endif; ?>
                        <?php if (!empty($attr['subs'])): ?>
                            <div class="small text-muted mb-1">Sub-attributes</div>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($attr['subs'] as $sub): ?>
                                    <span class="badge text-bg-light text-dark border">
                                        <?php echo htmlspecialchars($sub['name']); ?>: <?php echo number_format((float)$sub['score'], 1); ?>%
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<script>
(function () {
    Chart.defaults.font.family = "'Manrope', sans-serif";
    Chart.defaults.color = '#667089';

    const palette = ['#6946e8', '#28c0a9', '#f5a623', '#ff5d8f', '#4374ff', '#e85b6b'];
    const chartTooltip = {
        backgroundColor: '#121731',
        titleColor: '#fff',
        bodyColor: '#eef2ff',
        cornerRadius: 8,
        padding: 12
    };

    const ctx = document.getElementById('attributeChart').getContext('2d');
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const comparisonCtx = document.getElementById('comparisonChart').getContext('2d');

    new Chart(ctx, {
        type: 'radar',
        data: {
            labels: <?php echo json_encode($report['chart_labels'], JSON_UNESCAPED_UNICODE); ?>,
            datasets: [{
                label: 'Attribute score',
                data: <?php echo json_encode($report['chart_scores'], JSON_UNESCAPED_UNICODE); ?>,
                backgroundColor: 'rgba(105, 70, 232, 0.18)',
                borderColor: '#6946e8',
                borderWidth: 2,
                pointBackgroundColor: '#6946e8',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: chartTooltip
            },
            scales: {
                r: {
                    beginAtZero: true,
                    suggestedMax: 100,
                    ticks: { stepSize: 20 },
                    grid: { color: 'rgba(32,49,109,0.08)' },
                    angleLines: { color: 'rgba(32,49,109,0.08)' }
                }
            }
        }
    });

    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_map('sira_report_label', array_keys($statusCounts)), JSON_UNESCAPED_UNICODE); ?>,
            datasets: [{
                data: <?php echo json_encode(array_values($statusCounts), JSON_UNESCAPED_UNICODE); ?>,
                backgroundColor: palette,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                tooltip: chartTooltip
            }
        }
    });

    new Chart(comparisonCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_map(static fn (array $block): string => (string)$block['label'], array_values($comparison)), JSON_UNESCAPED_UNICODE); ?>,
            datasets: [{
                label: 'Average score',
                data: <?php echo json_encode(array_map(static fn (array $block): float => $block['average'] !== null ? (float)$block['average'] : 0.0, array_values($comparison)), JSON_UNESCAPED_UNICODE); ?>,
                backgroundColor: palette.map(function (color) { return color + 'cc'; }),
                borderRadius: 8,
                maxBarThickness: 34
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: chartTooltip
            },
            scales: {
                x: { grid: { display: false }, ticks: { maxRotation: 30, minRotation: 0 } },
                y: { beginAtZero: true, suggestedMax: 100, grid: { color: 'rgba(32,49,109,0.08)' } }
            }
        }
    });
})();
</script>

<?php
require_once __DIR__ . '/includes_footer.php';
