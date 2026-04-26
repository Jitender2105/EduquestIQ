<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_sira.php';

$user = require_auth(['student']);
$attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
if ($attemptId <= 0) {
    header('Location: ' . url_for('tests.php'));
    exit;
}

$pdo = get_pdo();
$report = sira_build_test_report($pdo, $attemptId);
if (!$report) {
    header('Location: ' . url_for('tests.php'));
    exit;
}

if ((int)$report['attempt']['student_id'] !== (int)$user['sub']) {
    header('Location: ' . url_for('tests.php'));
    exit;
}

$statusCounts = $report['status_counts'];
$overallScore = (float)$report['overall_score'];
$overallBand = sira_band($overallScore);
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
    .eq-sira-panel {
        background: #fff;
        border: 1px solid rgba(47, 59, 120, 0.08);
        border-radius: 24px;
        box-shadow: 0 18px 40px rgba(37, 49, 104, 0.08);
        padding: 18px;
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
        .eq-sira-report-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 575px) {
        .eq-sira-report-grid {
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
                <div class="text-white-50 small">Attempted on <?php echo htmlspecialchars((string)$report['attempt']['attempt_date']); ?></div>
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
        <div class="eq-sira-metric"><strong><?php echo (int)$statusCounts['answered']; ?></strong><span>Answered</span></div>
        <div class="eq-sira-metric"><strong><?php echo (int)$statusCounts['marked_for_review']; ?></strong><span>Marked Review</span></div>
        <div class="eq-sira-metric"><strong><?php echo (int)$statusCounts['not_attempted']; ?></strong><span>Not Attempted</span></div>
    </section>

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
                                <div class="small text-muted"><?php echo htmlspecialchars(text_preview((string)$question['question_text'], 90, '...')); ?></div>
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
