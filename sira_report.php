<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_sira.php';

$user = require_auth(['student']);
$attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
$pdo = get_pdo();
$report = $attemptId > 0 ? sira_build_test_report($pdo, $attemptId) : null;
$reportError = null;

if ($attemptId <= 0) {
    $reportError = 'No attempt was selected.';
} elseif (!$report) {
    $reportError = 'We could not find a detailed SIRA report for this attempt yet.';
} elseif ((int)$report['attempt']['student_id'] !== (int)$user['sub']) {
    $reportError = 'You can view only your own SIRA reports.';
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
?>

<style>
    .eq-sira-report-shell {
        display: grid;
        gap: 18px;
    }
    .eq-sira-report-hero {
        background: linear-gradient(135deg, #335cff 0%, #7a35ff 48%, #b931ff 100%);
        color: #fff;
        border-radius: 26px;
        padding: 24px;
        box-shadow: 0 24px 48px rgba(75, 58, 255, 0.22);
    }
    .eq-sira-report-hero h2 {
        margin-bottom: 6px;
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
        border-radius: 18px;
        padding: 16px;
        border: 1px solid rgba(47, 59, 120, 0.08);
        box-shadow: 0 14px 28px rgba(37, 49, 104, 0.08);
    }
    .eq-sira-metric strong {
        display: block;
        font-size: 1.7rem;
        line-height: 1;
        margin-bottom: 4px;
    }
    .eq-sira-table td,
    .eq-sira-table th {
        vertical-align: middle;
    }
    .eq-sira-panel {
        background: #fff;
        border: 1px solid rgba(47, 59, 120, 0.08);
        border-radius: 24px;
        box-shadow: 0 18px 40px rgba(37, 49, 104, 0.08);
        padding: 18px;
    }
    .eq-sira-compare-card {
        border-radius: 18px;
        background: #f8faff;
        border: 1px solid rgba(47, 59, 120, 0.08);
        padding: 16px;
        height: 100%;
    }
    .eq-sira-compare-card strong {
        display: block;
        font-size: 1.35rem;
        margin-bottom: 2px;
    }
    .eq-sira-insight-card {
        border-radius: 16px;
        border: 1px solid rgba(47, 59, 120, 0.08);
        background: #fbfcff;
        padding: 14px;
        height: 100%;
    }
    .eq-sira-insight-card strong {
        display: block;
        font-size: 1.2rem;
    }
    .eq-sira-attribute-card {
        background: #f9fbff;
        border: 1px solid rgba(47, 59, 120, 0.08);
        border-radius: 18px;
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
    @media (max-width: 991px) {
        .eq-sira-report-grid,
        .eq-sira-report-grid.six {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 575px) {
        .eq-sira-report-grid,
        .eq-sira-report-grid.six {
            grid-template-columns: 1fr;
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
                <div class="badge text-bg-light text-dark mb-2">SIRA Assessment Report</div>
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
            <div class="text-end">
                <div class="display-6 fw-bold"><?php echo number_format($overallScore, 1); ?>%</div>
                <div class="small text-white-50"><?php echo htmlspecialchars($overallBand['label']); ?> overall</div>
            </div>
        </div>
        <p class="mt-3 mb-0"><?php echo htmlspecialchars($report['overall_message']); ?></p>
    </section>

    <section class="eq-sira-report-grid">
        <div class="eq-sira-metric"><strong><?php echo number_format((float)$overallScore, 1); ?>%</strong><span>Overall Score</span></div>
        <div class="eq-sira-metric"><strong><?php echo number_format((float)$report['earned_marks_total'], 1); ?> / <?php echo number_format((float)$report['total_possible_marks'], 1); ?></strong><span>Total Marks</span></div>
        <div class="eq-sira-metric"><strong><?php echo (int)$report['response_count']; ?> / <?php echo (int)$report['total_questions']; ?></strong><span>Answered Questions</span></div>
        <div class="eq-sira-metric"><strong>#<?php echo (int)($comparison['overall']['rank'] ?? 0); ?></strong><span>Overall Rank</span></div>
    </section>

    <section class="eq-sira-report-grid">
        <div class="eq-sira-metric"><strong><?php echo (int)$report['attempted_count']; ?></strong><span>Visited / Attempted</span></div>
        <div class="eq-sira-metric"><strong><?php echo (int)$report['correct_count']; ?></strong><span>Correct</span></div>
        <div class="eq-sira-metric"><strong><?php echo (int)$report['incorrect_count']; ?></strong><span>Incorrect</span></div>
        <div class="eq-sira-metric"><strong><?php echo (int)$statusCounts['not_attempted']; ?></strong><span>Not Attempted</span></div>
    </section>

    <section class="eq-sira-report-grid">
        <div class="eq-sira-metric"><strong><?php echo $accuracy !== null ? number_format((float)$accuracy, 1) . '%' : 'N/A'; ?></strong><span>Objective Accuracy</span></div>
        <div class="eq-sira-metric"><strong><?php echo number_format($completion, 1); ?>%</strong><span>Completion Rate</span></div>
        <div class="eq-sira-metric"><strong><?php echo (int)$report['unanswered_count']; ?></strong><span>Not Answered</span></div>
        <div class="eq-sira-metric"><strong><?php echo (int)$statusCounts['marked_for_review']; ?></strong><span>Marked Review</span></div>
    </section>

    <section class="eq-sira-panel">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">Peer comparison</h5>
            <span class="badge text-bg-primary">Same test context</span>
        </div>
        <div class="row g-3">
            <?php foreach (['overall', 'class', 'grade', 'school', 'state_grade', 'state'] as $scope): ?>
                <?php $block = $comparison[$scope]; ?>
                <div class="col-md-6 col-xl-3">
                    <div class="eq-sira-compare-card">
                        <div class="small text-muted mb-2"><?php echo htmlspecialchars($block['label']); ?> comparison</div>
                        <strong>
                            <?php if ($block['rank'] !== null): ?>
                                Rank <?php echo (int)$block['rank']; ?> / <?php echo (int)$block['participants']; ?>
                            <?php else: ?>
                                Not available
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
        <section class="eq-sira-panel">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="mb-0">SIRA performance indicators</h5>
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

    <div class="row g-3">
        <div class="col-lg-7">
            <section class="eq-sira-panel h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Attribute scores</h5>
                    <span class="badge text-bg-primary">SIRA</span>
                </div>
                <canvas id="attributeChart" height="220"></canvas>
            </section>
        </div>
        <div class="col-lg-5">
            <section class="eq-sira-panel h-100">
                <h5 class="mb-3">Question status summary</h5>
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
        </div>
    </div>

    <section class="eq-sira-panel">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">Question performance</h5>
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

    <section class="eq-sira-panel">
        <h5 class="mb-3">Detailed attribute report</h5>
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
    const ctx = document.getElementById('attributeChart').getContext('2d');
    new Chart(ctx, {
        type: 'radar',
        data: {
            labels: <?php echo json_encode($report['chart_labels'], JSON_UNESCAPED_UNICODE); ?>,
            datasets: [{
                label: 'Attribute score',
                data: <?php echo json_encode($report['chart_scores'], JSON_UNESCAPED_UNICODE); ?>,
                backgroundColor: 'rgba(67, 116, 255, 0.18)',
                borderColor: 'rgba(67, 116, 255, 1)',
                borderWidth: 2,
                pointBackgroundColor: 'rgba(67, 116, 255, 1)',
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                r: {
                    beginAtZero: true,
                    suggestedMax: 100,
                    ticks: { stepSize: 20 }
                }
            }
        }
    });
})();
</script>

<?php
require_once __DIR__ . '/includes_footer.php';
