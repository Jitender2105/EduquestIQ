<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
$user = backend_user();
$pdo = get_pdo();
backend_require_admin($user);

$errors = [];
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO achievements (title, description, icon, criteria_type, criteria_value) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([trim((string)$_POST['title']), trim((string)$_POST['description']) ?: null, trim((string)$_POST['icon']) ?: null, (string)$_POST['criteria_type'], (int)$_POST['criteria_value']]);
            $success = 'Achievement created.';
        } catch (Throwable $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

$rows = $pdo->query('SELECT id, title, criteria_type, criteria_value FROM achievements ORDER BY id DESC LIMIT 150')->fetchAll();

require_once dirname(__DIR__) . '/includes_header.php';
?>
<div class="eq-page-head"><h2>Achievements Backend</h2><p class="subtitle">Configure gamification and recognition criteria.</p></div>
<?php require __DIR__ . '/nav.php'; ?>
<?php require __DIR__ . '/richtext.php'; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="row g-3">
<div class="col-md-5"><form method="post" class="card p-3"><?php echo csrf_field(); ?><h6>Create Achievement</h6><input class="form-control mb-2" name="title" required><textarea class="form-control mb-2 eq-richtext" data-richtext name="description" rows="2"></textarea><input class="form-control mb-2" name="icon" placeholder="trophy"><select class="form-select mb-2" name="criteria_type"><option value="score">score</option><option value="course_completion">course_completion</option><option value="activity">activity</option></select><input class="form-control mb-2" name="criteria_value" type="number" min="1" value="1"><button class="btn btn-primary btn-sm">Create</button></form></div>
<div class="col-md-7"><div class="card p-3"><h6>Achievements</h6><ul class="small mb-0"><?php foreach ($rows as $r): ?><li>#<?php echo (int)$r['id']; ?> <?php echo htmlspecialchars($r['title'].' ['.$r['criteria_type'].' >= '.$r['criteria_value'].']'); ?></li><?php endforeach; ?></ul></div></div>
</div>
<?php require_once dirname(__DIR__) . '/includes_footer.php';
