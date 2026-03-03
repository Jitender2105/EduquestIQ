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
            $stmt = $pdo->prepare('UPDATE users SET status = ?, school_id = ? WHERE id = ?');
            $schoolId = (int)($_POST['school_id'] ?? 0);
            $stmt->execute([(string)$_POST['status'], $schoolId > 0 ? $schoolId : null, (int)$_POST['user_id']]);
            $success = 'User updated.';
        } catch (Throwable $e) {
            $errors[] = 'Update failed: ' . $e->getMessage();
        }
    }
}

$schools = $pdo->query('SELECT id, name FROM schools ORDER BY name')->fetchAll();
$users = $pdo->query('SELECT u.id, u.name, u.email, u.role, u.status, u.school_id, u.age, u.grade, u.role_profile, s.name AS school_name FROM users u LEFT JOIN schools s ON s.id=u.school_id ORDER BY u.id DESC LIMIT 300')->fetchAll();

require_once dirname(__DIR__) . '/includes_header.php';
?>
<div class="eq-page-head"><h2>Users Backend</h2><p class="subtitle">Role governance, school assignment, and profile-level inspection.</p></div>
<?php require __DIR__ . '/nav.php'; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="row g-3">
<div class="col-md-4"><form method="post" class="card p-3"><?php echo csrf_field(); ?><h6>Update User</h6><select class="form-select mb-2" name="user_id" required><?php foreach ($users as $u): ?><option value="<?php echo (int)$u['id']; ?>">#<?php echo (int)$u['id']; ?> <?php echo htmlspecialchars($u['name'].' ('.$u['role'].')'); ?></option><?php endforeach; ?></select><select class="form-select mb-2" name="status"><option value="active">active</option><option value="inactive">inactive</option></select><select class="form-select mb-2" name="school_id"><option value="">clear school</option><?php foreach ($schools as $s): ?><option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?></select><button class="btn btn-primary btn-sm">Update</button></form></div>
<div class="col-md-8"><div class="card p-3"><h6>Recent Users</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>ID</th><th>User</th><th>Role</th><th>School</th><th>Status</th><th>Profile</th></tr></thead><tbody><?php foreach ($users as $u): ?><tr><td><?php echo (int)$u['id']; ?></td><td><?php echo htmlspecialchars($u['name'].'<'.$u['email'].'>'); ?></td><td><?php echo htmlspecialchars($u['role']); ?></td><td><?php echo htmlspecialchars((string)$u['school_name']); ?></td><td><?php echo htmlspecialchars($u['status']); ?></td><td><code><?php echo htmlspecialchars(text_preview((string)($u['role_profile'] ?? ''), 70, '...')); ?></code></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div>
<?php require_once dirname(__DIR__) . '/includes_footer.php';
