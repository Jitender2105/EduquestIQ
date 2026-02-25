<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_csrf.php';

$user = require_auth(['student']);

$testId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($testId <= 0) {
    header('Location: ' . url_for('tests.php'));
    exit;
}

$pdo = get_pdo();

// Load test and questions
$stmt = $pdo->prepare(
    'SELECT id, title, description, total_marks, duration_minutes
     FROM tests
     WHERE id = ?'
);
$stmt->execute([$testId]);
$test = $stmt->fetch();

if (!$test) {
    header('Location: ' . url_for('tests.php'));
    exit;
}

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

// Load options for all MCQ questions
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

<div class="mb-3">
    <a href="<?php echo htmlspecialchars(url_for('tests.php')); ?>" class="btn btn-link">&larr; Back to tests</a>
</div>

<div class="eq-page-head">
    <h2><?php echo htmlspecialchars($test['title']); ?></h2>
    <p class="subtitle">
        Marks: <?php echo (int)$test['total_marks']; ?> |
        Duration: <?php echo (int)$test['duration_minutes']; ?> minutes
    </p>
</div>
<p><?php echo nl2br(htmlspecialchars((string)$test['description'])); ?></p>

<form method="post" action="<?php echo htmlspecialchars(url_for('test_submit.php')); ?>">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="test_id" value="<?php echo (int)$testId; ?>">

    <?php foreach ($questions as $idx => $q): ?>
        <?php $qid = (int)$q['question_id']; ?>
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-title">
                    Q<?php echo $idx + 1; ?>.
                    <?php echo nl2br(htmlspecialchars((string)$q['question_text'])); ?>
                    <span class="badge text-bg-light ms-2"><?php echo (int)$q['marks']; ?> marks</span>
                </h6>
                <?php if ($q['question_type'] === 'mcq'): ?>
                    <?php if (!empty($optionsByQuestion[$qid])): ?>
                        <?php foreach ($optionsByQuestion[$qid] as $opt): ?>
                            <div class="form-check">
                                <input class="form-check-input"
                                       type="radio"
                                       name="q[<?php echo $qid; ?>]"
                                       id="q<?php echo $qid; ?>_opt<?php echo (int)$opt['id']; ?>"
                                       value="<?php echo (int)$opt['id']; ?>">
                                <label class="form-check-label" for="q<?php echo $qid; ?>_opt<?php echo (int)$opt['id']; ?>">
                                    <?php echo htmlspecialchars($opt['option_text']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted small">No options defined for this question.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <textarea name="s[<?php echo $qid; ?>]" rows="4" class="form-control"></textarea>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-primary">Submit test</button>
</form>

<?php
require_once __DIR__ . '/includes_footer.php';
