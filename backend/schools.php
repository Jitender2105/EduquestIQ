<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
$user = backend_user();
backend_require_admin($user);
$pdo = get_pdo();

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $city = trim((string)($_POST['city'] ?? ''));
        $state = trim((string)($_POST['state'] ?? ''));
        $status = (string)($_POST['status'] ?? 'active');
        if ($name === '') {
            $errors[] = 'School name is required.';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors[] = 'Invalid status.';
        }
        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO schools (name, city, state, status) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $city !== '' ? $city : null, $state !== '' ? $state : null, $status]);
            $success = 'School added.';
        }
    }
}

$schools = $pdo->query('SELECT id, name, city, state, status, created_at FROM schools ORDER BY id DESC LIMIT 300')->fetchAll();

require_once dirname(__DIR__) . '/includes_header.php';
?>
<div class="eq-page-head"><h2>Schools</h2><p class="subtitle">School master for registration, users, and reporting scope.</p></div>
<?php require __DIR__ . '/nav.php'; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <form method="post" class="card p-3">
            <?php echo csrf_field(); ?>
            <h6>Create School</h6>
            <input class="form-control mb-2" name="name" placeholder="School name" required>
            <input class="form-control mb-2" name="city" placeholder="City">
            <input class="form-control mb-2" name="state" placeholder="State">
            <select class="form-select mb-2" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>
            <button class="btn btn-primary btn-sm">Save</button>
        </form>
    </div>
    <div class="col-lg-8">
        <div class="card p-3">
            <h6>Schools List</h6>
            <div class="table-responsive"><table class="table table-sm"><thead><tr><th>ID</th><th>Name</th><th>City</th><th>State</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($schools as $s): ?>
                <tr><td><?php echo (int)$s['id']; ?></td><td><?php echo htmlspecialchars($s['name']); ?></td><td><?php echo htmlspecialchars((string)$s['city']); ?></td><td><?php echo htmlspecialchars((string)$s['state']); ?></td><td><?php echo htmlspecialchars($s['status']); ?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/includes_footer.php';
