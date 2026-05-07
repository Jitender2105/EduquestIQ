<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_csrf.php';
require_once __DIR__ . '/includes_payments.php';

$user = require_auth(['student']);

$testId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($testId <= 0) {
    header('Location: ' . url_for('tests.php'));
    exit;
}

$pdo = get_pdo();
$testHasActiveColumn = table_has_column($pdo, 'tests', 'is_active');
$testHasGradeColumn = table_has_column($pdo, 'tests', 'target_grade');
$studentGradeStmt = $pdo->prepare('SELECT grade FROM users WHERE id = ? LIMIT 1');
$studentGradeStmt->execute([(int)$user['sub']]);
$studentGrade = trim((string)$studentGradeStmt->fetchColumn());

$stmt = $pdo->prepare(
    'SELECT id, title, description, instruction, test_year, start_at, end_at, total_marks, duration_minutes, price_inr'
    . ($testHasGradeColumn ? ', target_grade' : '')
    . ($testHasActiveColumn ? ', is_active' : '') . '
     FROM tests
     WHERE id = ?'
);
$stmt->execute([$testId]);
$test = $stmt->fetch();

if (!$test) {
    header('Location: ' . url_for('tests.php'));
    exit;
}

if (($testHasActiveColumn && empty($test['is_active']))
    || ($testHasGradeColumn && $studentGrade !== '' && !empty($test['target_grade']) && (string)$test['target_grade'] !== $studentGrade)) {
    header('Location: ' . url_for('tests.php'));
    exit;
}

$testPrice = (float)($test['price_inr'] ?? 0);
if ($testPrice > 0 && !test_purchase_is_paid($pdo, $testId, (int)$user['sub'])) {
    header('Location: ' . url_for('test_purchase.php?id=' . $testId));
    exit;
}

$nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$startAt = !empty($test['start_at']) ? new DateTimeImmutable((string)$test['start_at'], new DateTimeZone('UTC')) : null;
$endAt = !empty($test['end_at']) ? new DateTimeImmutable((string)$test['end_at'], new DateTimeZone('UTC')) : null;
$isUpcoming = $startAt && $nowUtc < $startAt;
$isClosed = $endAt && $nowUtc > $endAt;
$secondsUntilStart = $isUpcoming ? max(0, $startAt->getTimestamp() - $nowUtc->getTimestamp()) : 0;
$secondsUntilEnd = $endAt ? max(0, $endAt->getTimestamp() - $nowUtc->getTimestamp()) : 0;

$stmt = $pdo->prepare(
    'SELECT tq.question_id, tq.marks, q.question_text, q.question_type
     FROM test_questions tq
     JOIN questions q ON tq.question_id = q.id
     WHERE tq.test_id = ?
     ORDER BY tq.id ASC'
);
$stmt->execute([$testId]);
$questions = $stmt->fetchAll();

if (!$questions) {
    echo '<div class="alert alert-warning">This test has no questions yet.</div>';
    require_once __DIR__ . '/includes_footer.php';
    exit;
}

$optionsByQuestion = [];
$questionIds = array_column($questions, 'question_id');
if ($questionIds) {
    $in = implode(',', array_fill(0, count($questionIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, question_id, option_text
         FROM question_options
         WHERE question_id IN ($in)
         ORDER BY id ASC"
    );
    $stmt->execute($questionIds);
    foreach ($stmt->fetchAll() as $opt) {
        $qid = (int)$opt['question_id'];
        if (!isset($optionsByQuestion[$qid])) {
            $optionsByQuestion[$qid] = [];
        }
        $optionsByQuestion[$qid][] = $opt;
    }
}
?>

<style>
    .eq-attempt-shell {
        display: grid;
        grid-template-columns: 320px minmax(0, 1fr);
        gap: 18px;
        align-items: start;
    }
    .eq-attempt-sidebar,
    .eq-attempt-main,
    .eq-backend-panel {
        background: #fff;
        border: 1px solid rgba(47, 59, 120, 0.08);
        border-radius: 22px;
        box-shadow: 0 18px 40px rgba(37, 49, 104, 0.08);
    }
    .eq-attempt-sidebar {
        padding: 18px;
        position: sticky;
        top: 92px;
    }
    .eq-attempt-main {
        padding: 18px;
    }
    .eq-attempt-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        align-items: center;
        padding-bottom: 14px;
        border-bottom: 1px solid rgba(47, 59, 120, 0.08);
        margin-bottom: 16px;
    }
    .eq-attempt-head h2 {
        margin-bottom: 4px;
        font-size: 1.4rem;
    }
    .eq-attempt-meta {
        color: #6f7690;
        font-size: 0.9rem;
    }
    .eq-time-box {
        min-width: 168px;
        padding: 14px 16px;
        border-radius: 16px;
        background: linear-gradient(135deg, #1f4eff, #7b37ff);
        color: #fff;
        box-shadow: 0 12px 26px rgba(73, 59, 255, 0.25);
    }
    .eq-time-box small {
        display: block;
        opacity: 0.8;
    }
    .eq-time-box strong {
        font-size: 1.4rem;
        letter-spacing: 0.02em;
    }
    .eq-status-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 14px;
    }
    .eq-status-pill {
        border-radius: 14px;
        padding: 10px 12px;
        color: #fff;
        font-weight: 700;
        font-size: 0.84rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .eq-status-pill span {
        font-size: 1.05rem;
    }
    .eq-status-answered { background: linear-gradient(135deg, #2ebd6b, #1f8f53); }
    .eq-status-not_answered { background: linear-gradient(135deg, #ff6b6b, #e44949); }
    .eq-status-not_attempted { background: linear-gradient(135deg, #4c7dff, #325dde); }
    .eq-status-marked_for_review { background: linear-gradient(135deg, #f5a524, #e07a12); }
    .eq-question-nav {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 14px;
    }
    .eq-qnav-btn {
        border: 0;
        border-radius: 11px;
        min-height: 36px;
        font-size: 0.84rem;
        font-weight: 700;
        color: #fff;
        background: #4c7dff;
        box-shadow: 0 8px 16px rgba(76, 125, 255, 0.18);
    }
    .eq-qnav-btn.is-active {
        outline: 3px solid rgba(67, 116, 255, 0.24);
        transform: translateY(-1px);
    }
    .eq-qnav-btn.status-not_attempted { background: #4c7dff; }
    .eq-qnav-btn.status-not_answered { background: #ff6b6b; }
    .eq-qnav-btn.status-answered { background: #2ebd6b; }
    .eq-qnav-btn.status-marked_for_review { background: #f5a524; }
    .eq-question-card {
        display: none;
        background: #f9fbff;
        border: 1px solid rgba(47, 59, 120, 0.08);
        border-radius: 20px;
        padding: 18px;
    }
    .eq-question-card.is-active {
        display: block;
    }
    .eq-question-top {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 16px;
    }
    .eq-question-top h5 {
        margin-bottom: 8px;
        line-height: 1.45;
    }
    .eq-question-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .eq-question-body .form-check {
        background: #fff;
        border: 1px solid rgba(47, 59, 120, 0.08);
        border-radius: 14px;
        padding: 10px 12px 10px 36px;
        margin-bottom: 10px;
    }
    .eq-question-body .form-check:hover {
        border-color: rgba(67, 116, 255, 0.28);
        box-shadow: 0 6px 18px rgba(67, 116, 255, 0.08);
    }
    .eq-question-body textarea.form-control {
        min-height: 180px;
        border-radius: 16px;
    }
    .eq-attempt-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 16px;
        justify-content: space-between;
    }
    .eq-attempt-actions .left,
    .eq-attempt-actions .right {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .eq-attempt-note {
        font-size: 0.9rem;
        color: #6e7487;
        margin-top: 12px;
    }
    .eq-legend {
        display: grid;
        gap: 8px;
        margin-top: 14px;
    }
    .eq-legend-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 12px;
        border-radius: 14px;
        background: #f8faff;
        border: 1px solid rgba(47, 59, 120, 0.08);
        font-weight: 700;
        color: #29324e;
    }
    .eq-legend-item small {
        font-weight: 700;
        color: #6e7487;
    }
    @media (max-width: 991px) {
        .eq-attempt-shell {
            grid-template-columns: 1fr;
        }
        .eq-attempt-sidebar {
            position: static;
        }
        .eq-question-nav {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }
    @media (max-width: 575px) {
        .eq-question-nav {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
</style>

<div class="mb-3">
    <a href="<?php echo htmlspecialchars(url_for('tests.php')); ?>" class="btn btn-link">&larr; Back to tests</a>
</div>

<div class="eq-backend-panel mb-3 d-none" id="attempt-summary-panel">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="badge text-bg-primary mb-2">SIRA Test</div>
                <h2 class="mb-1"><?php echo htmlspecialchars($test['title']); ?></h2>
                <div class="eq-attempt-meta">
                    Year: <?php echo htmlspecialchars((string)($test['test_year'] ?? '')); ?> |
                    Marks: <?php echo (int)$test['total_marks']; ?> |
                    Duration: <?php echo (int)$test['duration_minutes']; ?> minutes
                </div>
            </div>
            <div class="text-end">
                <?php if ($isUpcoming): ?>
                    <div class="eq-time-box">
                        <small>Starts in</small>
                        <strong id="pre-start-countdown"></strong>
                    </div>
                <?php elseif ($isClosed): ?>
                    <div class="eq-time-box">
                        <small>Test closed</small>
                        <strong>Ended</strong>
                    </div>
                <?php else: ?>
                    <div class="eq-time-box">
                        <small>Time Left</small>
                        <strong id="time-left"><?php echo (int)$test['duration_minutes']; ?>:00</strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="eq-backend-panel mb-3" id="instruction-screen">
    <div class="card-body">
        <h4 class="mb-3">Test Instructions</h4>
        <div class="mb-3"><?php echo (string)($test['instruction'] ?? ''); ?></div>
        <?php if ($isUpcoming): ?>
            <div class="alert alert-info mb-3">This test has not started yet. You can begin after the scheduled start time.</div>
            <div class="mb-3">
                <strong>Start countdown:</strong>
                <span id="start-countdown"></span>
            </div>
            <button type="button" class="btn btn-primary" id="btn-start-test" disabled>Proceed</button>
        <?php elseif ($isClosed): ?>
            <div class="alert alert-danger mb-0">This test has already ended and can no longer be attempted.</div>
        <?php else: ?>
            <div class="alert alert-primary mb-3">Read the instructions carefully. The test questions will appear only after you click Proceed.</div>
            <button type="button" class="btn btn-primary" id="btn-start-test">Proceed</button>
        <?php endif; ?>
    </div>
</div>

<div class="eq-attempt-shell d-none" id="sira-attempt-app">
    <aside class="eq-attempt-sidebar">
        <div class="eq-page-head mb-3">
            <h2 class="mb-1"><?php echo htmlspecialchars($test['title']); ?></h2>
            <div class="eq-attempt-meta">SIRA • <?php echo (int)$test['total_marks']; ?> marks</div>
        </div>

        <div class="eq-status-grid">
            <div class="eq-status-pill eq-status-answered"><span id="count-answered">0</span> Answered</div>
            <div class="eq-status-pill eq-status-not_answered"><span id="count-not-answered">0</span> Not Answered</div>
            <div class="eq-status-pill eq-status-marked_for_review"><span id="count-review">0</span> Review</div>
            <div class="eq-status-pill eq-status-not_attempted"><span id="count-not-attempted"><?php echo count($questions); ?></span> Not Attempted</div>
        </div>

        <div class="eq-question-nav" id="question-nav">
            <?php foreach ($questions as $idx => $q): ?>
                <button type="button"
                        class="eq-qnav-btn status-not_attempted <?php echo $idx === 0 ? 'is-active' : ''; ?>"
                        data-jump-index="<?php echo (int)$idx; ?>">
                    <?php echo (int)$idx + 1; ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="eq-legend">
            <div class="eq-legend-item"><span>Answered</span><small>Green</small></div>
            <div class="eq-legend-item"><span>Not Answered</span><small>Red</small></div>
            <div class="eq-legend-item"><span>Review</span><small>Orange</small></div>
            <div class="eq-legend-item"><span>Not Attempted</span><small>Blue</small></div>
        </div>
    </aside>

    <main class="eq-attempt-main">
        <div class="eq-attempt-head">
            <div>
                <h2><?php echo htmlspecialchars($test['title']); ?></h2>
                <div class="eq-attempt-meta">
                    <?php echo (string)($test['description'] ?? ''); ?>
                </div>
            </div>
            <div class="text-end">
                <div class="eq-attempt-meta">Question <span id="current-question-number">1</span> of <?php echo count($questions); ?></div>
                <div class="eq-attempt-meta">Duration: <?php echo (int)$test['duration_minutes']; ?> minutes</div>
            </div>
        </div>

        <form method="post" action="<?php echo htmlspecialchars(url_for('test_submit.php')); ?>" id="sira-attempt-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="test_id" value="<?php echo (int)$testId; ?>">

            <div class="eq-question-stage">
                <?php foreach ($questions as $idx => $q): ?>
                    <?php $qid = (int)$q['question_id']; ?>
                    <article class="eq-question-card <?php echo $idx === 0 ? 'is-active' : ''; ?>"
                             data-question-card
                             data-question-id="<?php echo $qid; ?>"
                             data-question-index="<?php echo (int)$idx; ?>">
                        <input type="hidden" name="visited[<?php echo $qid; ?>]" value="0" data-visited-field>
                        <input type="hidden" name="review[<?php echo $qid; ?>]" value="0" data-review-field>
                        <div class="eq-question-top">
                            <div>
                                <div class="eq-question-badges mb-2">
                                    <span class="badge text-bg-light">Q<?php echo $idx + 1; ?></span>
                                    <span class="badge text-bg-primary"><?php echo (int)$q['marks']; ?> marks</span>
                                    <span class="badge text-bg-secondary"><?php echo htmlspecialchars(strtoupper((string)$q['question_type'])); ?></span>
                                </div>
                                <h5><?php echo (string)$q['question_text']; ?></h5>
                            </div>
                            <span class="badge text-bg-info">SIRA</span>
                        </div>

                        <div class="eq-question-body">
                            <?php if ($q['question_type'] === 'mcq'): ?>
                                <?php if (!empty($optionsByQuestion[$qid])): ?>
                                    <?php foreach ($optionsByQuestion[$qid] as $opt): ?>
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   type="radio"
                                                   name="q[<?php echo $qid; ?>]"
                                                   id="q<?php echo $qid; ?>_opt<?php echo (int)$opt['id']; ?>"
                                                   value="<?php echo (int)$opt['id']; ?>"
                                                   data-answer-input>
                                            <label class="form-check-label" for="q<?php echo $qid; ?>_opt<?php echo (int)$opt['id']; ?>">
                                                <?php echo (string)$opt['option_text']; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted small">No options defined for this question.</p>
                                <?php endif; ?>
                            <?php else: ?>
                                <textarea name="s[<?php echo $qid; ?>]" rows="6" class="form-control" data-answer-input placeholder="Type your answer here..."></textarea>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="eq-attempt-actions">
                <div class="left">
                    <button type="button" class="btn btn-outline-secondary" id="btn-prev">Previous</button>
                    <button type="button" class="btn btn-outline-primary" id="btn-clear">Clear Answer</button>
                    <button type="button" class="btn btn-outline-warning" id="btn-review">Select and mark for review</button>
                </div>
                <div class="right">
                    <button type="button" class="btn btn-primary" id="btn-next">Next</button>
                    <button type="submit" class="btn btn-success" id="btn-finish">Finish</button>
                </div>
            </div>

            <div class="eq-attempt-note">* Use the question palette to jump between questions. Unvisited questions remain not attempted until opened.</div>
        </form>
    </main>
</div>

<script>
(function () {
    const form = document.getElementById('sira-attempt-form');
    const app = document.getElementById('sira-attempt-app');
    const summaryPanel = document.getElementById('attempt-summary-panel');
    const instructionScreen = document.getElementById('instruction-screen');
    const startButton = document.getElementById('btn-start-test');
    const cards = Array.from(document.querySelectorAll('[data-question-card]'));
    const navButtons = Array.from(document.querySelectorAll('#question-nav [data-jump-index]'));
    const answeredEl = document.getElementById('count-answered');
    const notAnsweredEl = document.getElementById('count-not-answered');
    const reviewEl = document.getElementById('count-review');
    const notAttemptedEl = document.getElementById('count-not-attempted');
    const currentQuestionEl = document.getElementById('current-question-number');
    const timeLeftEl = document.getElementById('time-left');
    const startCountdownEl = document.getElementById('start-countdown');
    const preStartCountdownEl = document.getElementById('pre-start-countdown');
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    const btnClear = document.getElementById('btn-clear');
    const btnReview = document.getElementById('btn-review');
    const btnFinish = document.getElementById('btn-finish');
    const durationSeconds = <?php echo (int)$test['duration_minutes'] * 60; ?>;
    const startDelaySeconds = <?php echo (int)$secondsUntilStart; ?>;
    const endEpochMs = <?php echo $endAt ? ((int)$endAt->getTimestamp() * 1000) : 0; ?>;
    let currentIndex = 0;
    let timerId = null;
    let startTimerId = null;
    let startedAt = null;
    let attemptLimitSeconds = durationSeconds;

    function getCard(index) {
        return cards[index];
    }

    function setVisited(index) {
        const input = getCard(index).querySelector('[data-visited-field]');
        if (input) input.value = '1';
    }

    function setReview(index, value) {
        const input = getCard(index).querySelector('[data-review-field]');
        if (input) input.value = value ? '1' : '0';
    }

    function hasAnswer(index) {
        const card = getCard(index);
        const radio = card.querySelector('input[type="radio"]:checked');
        if (radio) return true;
        const textarea = card.querySelector('textarea');
        return !!(textarea && textarea.value.trim() !== '');
    }

    function isVisited(index) {
        const input = getCard(index).querySelector('[data-visited-field]');
        return !!(input && input.value === '1');
    }

    function isMarkedForReview(index) {
        const input = getCard(index).querySelector('[data-review-field]');
        return !!(input && input.value === '1');
    }

    function getStatus(index) {
        if (isMarkedForReview(index)) {
            return 'marked_for_review';
        }
        if (hasAnswer(index)) {
            return 'answered';
        }
        return isVisited(index) ? 'not_answered' : 'not_attempted';
    }

    function updateNavButton(index) {
        const status = getStatus(index);
        const btn = navButtons[index];
        btn.className = 'eq-qnav-btn status-' + status + (index === currentIndex ? ' is-active' : '');
    }

    function renderCounts() {
        const counts = { answered: 0, not_answered: 0, not_attempted: 0, marked_for_review: 0 };
        cards.forEach(function (_, index) {
            counts[getStatus(index)]++;
            updateNavButton(index);
        });
        answeredEl.textContent = String(counts.answered);
        notAnsweredEl.textContent = String(counts.not_answered);
        reviewEl.textContent = String(counts.marked_for_review);
        notAttemptedEl.textContent = String(counts.not_attempted);
    }

    function showQuestion(index) {
        if (index < 0 || index >= cards.length) {
            return;
        }
        cards.forEach(function (card, idx) {
            card.classList.toggle('is-active', idx === index);
        });
        currentIndex = index;
        currentQuestionEl.textContent = String(index + 1);
        renderCounts();
    }

    function clearCurrentAnswer() {
        const card = getCard(currentIndex);
        card.querySelectorAll('input[type="radio"]').forEach(function (input) {
            input.checked = false;
        });
        const textarea = card.querySelector('textarea');
        if (textarea) textarea.value = '';
        setReview(currentIndex, false);
        setVisited(currentIndex);
        renderCounts();
    }

    function markForReview() {
        setVisited(currentIndex);
        setReview(currentIndex, true);
        renderCounts();
    }

    function nextQuestion() {
        setVisited(currentIndex);
        if (currentIndex < cards.length - 1) {
            showQuestion(currentIndex + 1);
        } else {
            renderCounts();
        }
    }

    function prevQuestion() {
        setVisited(currentIndex);
        if (currentIndex > 0) {
            showQuestion(currentIndex - 1);
        }
    }

    function finishTest(forceSubmit) {
        setVisited(currentIndex);
        renderCounts();
        const notAnswered = Number(notAnsweredEl.textContent || '0');
        const notAttempted = Number(notAttemptedEl.textContent || '0');
        if (!forceSubmit && (notAnswered + notAttempted) > 0) {
            const proceed = window.confirm('Some questions are still unanswered or not attempted. Submit the test now?');
            if (!proceed) {
                return;
            }
        }
        form.submit();
    }

    function updateTimer() {
        const elapsed = Math.floor((Date.now() - startedAt) / 1000);
        const remaining = Math.max(attemptLimitSeconds - elapsed, 0);
        const mins = Math.floor(remaining / 60);
        const secs = remaining % 60;
        if (timeLeftEl) {
            timeLeftEl.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        }
        if (remaining <= 0) {
            clearInterval(timerId);
            finishTest(true);
        }
    }

    function formatDuration(totalSeconds) {
        const days = Math.floor(totalSeconds / 86400);
        const hours = Math.floor((totalSeconds % 86400) / 3600);
        const mins = Math.floor((totalSeconds % 3600) / 60);
        const secs = totalSeconds % 60;
        return days + 'd ' + hours + 'h ' + mins + 'm ' + secs + 's';
    }

    function updateStartCountdown() {
        if (!startCountdownEl && !preStartCountdownEl) {
            return;
        }
        if (startDelaySeconds <= 0) {
            if (startCountdownEl) startCountdownEl.textContent = '0d 0h 0m 0s';
            if (preStartCountdownEl) preStartCountdownEl.textContent = 'Ready';
            if (startButton) startButton.disabled = false;
            clearInterval(startTimerId);
            return;
        }

        const elapsed = Math.floor((Date.now() - pageLoadedAt) / 1000);
        const remaining = Math.max(startDelaySeconds - elapsed, 0);
        const label = formatDuration(remaining);
        if (startCountdownEl) startCountdownEl.textContent = label;
        if (preStartCountdownEl) preStartCountdownEl.textContent = label;
        if (remaining <= 0) {
            if (startButton) startButton.disabled = false;
            clearInterval(startTimerId);
        }
    }

    function startAttempt() {
        const now = Date.now();
        const remainingToEnd = endEpochMs > 0 ? Math.max(0, Math.floor((endEpochMs - now) / 1000)) : durationSeconds;
        attemptLimitSeconds = Math.min(durationSeconds, remainingToEnd || durationSeconds);
        startedAt = Date.now();
        instructionScreen.classList.add('d-none');
        if (summaryPanel) summaryPanel.classList.remove('d-none');
        app.classList.remove('d-none');
        setVisited(0);
        showQuestion(0);
        updateTimer();
        timerId = window.setInterval(updateTimer, 1000);
    }

    const pageLoadedAt = Date.now();
    if (startButton) {
        if (startDelaySeconds > 0) {
            startButton.disabled = true;
            updateStartCountdown();
            startTimerId = window.setInterval(updateStartCountdown, 1000);
        } else {
            startButton.disabled = false;
        }
        startButton.addEventListener('click', startAttempt);
    }

    navButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setVisited(currentIndex);
            showQuestion(Number(button.dataset.jumpIndex));
        });
    });

    document.querySelectorAll('[data-answer-input]').forEach(function (input) {
        input.addEventListener('change', function () {
            setVisited(currentIndex);
            renderCounts();
        });
        input.addEventListener('input', function () {
            setVisited(currentIndex);
            renderCounts();
        });
    });

    btnPrev.addEventListener('click', prevQuestion);
    btnNext.addEventListener('click', nextQuestion);
    btnClear.addEventListener('click', clearCurrentAnswer);
    btnReview.addEventListener('click', markForReview);
    btnFinish.addEventListener('click', function (event) {
        event.preventDefault();
        finishTest(false);
    });

    renderCounts();
})();
</script>

<?php
require_once __DIR__ . '/includes_footer.php';
