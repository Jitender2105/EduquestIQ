<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';
require_once __DIR__ . '/includes_csrf.php';

$user = require_auth(['parent', 'school_admin']);
$pdo = get_pdo();

$errors = [];
$success = null;
$tableReady = false;

try {
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute(['parent_student_links']);
    $tableReady = (bool)$stmt->fetchColumn();
} catch (Throwable $e) {
    $tableReady = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableReady) {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Invalid CSRF token.';
    }

    $childEmail = trim((string)($_POST['child_email'] ?? ''));
    if ($childEmail === '' || !filter_var($childEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid child email is required.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$childEmail]);
        $child = $stmt->fetch();

        if (!$child || $child['role'] !== 'student') {
            $errors[] = 'No student account found with that email.';
        } else {
            if ($user['role'] === 'parent' && !empty($user['school_id'])) {
                $stmt = $pdo->prepare('SELECT school_id FROM users WHERE id = ?');
                $stmt->execute([(int)$child['id']]);
                $childSchoolId = $stmt->fetchColumn();
                if ((int)$childSchoolId !== (int)$user['school_id']) {
                    $errors[] = 'You can only link a student in your school.';
                }
            }

            $parentId = (int)$user['sub'];
            if ($user['role'] === 'school_admin') {
                $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
            }

            if (!$errors && $parentId <= 0) {
                $errors[] = 'Parent account is required.';
            } elseif (!$errors) {
                $stmt = $pdo->prepare(
                    'INSERT IGNORE INTO parent_student_links (parent_id, student_id) VALUES (?, ?)'
                );
                $stmt->execute([$parentId, (int)$child['id']]);
                $success = 'Parent-child link saved.';
            }
        }
    }
}

$parentOptions = [];
if ($user['role'] === 'school_admin') {
    $stmt = $pdo->query('SELECT id, name, email FROM users WHERE role = "parent" ORDER BY name ASC');
    $parentOptions = $stmt->fetchAll();
}

if ($tableReady && $user['role'] === 'parent') {
    $stmt = $pdo->prepare(
        'SELECT u.name, u.email, psl.created_at
         FROM parent_student_links psl
         JOIN users u ON u.id = psl.student_id
         WHERE psl.parent_id = ?
         ORDER BY psl.created_at DESC'
    );
    $stmt->execute([(int)$user['sub']]);
    $links = $stmt->fetchAll();
} elseif ($tableReady) {
    $stmt = $pdo->query(
        'SELECT p.name AS parent_name, p.email AS parent_email, s.name AS student_name, s.email AS student_email, psl.created_at
         FROM parent_student_links psl
         JOIN users p ON p.id = psl.parent_id
         JOIN users s ON s.id = psl.student_id
         ORDER BY psl.created_at DESC
         LIMIT 50'
    );
    $links = $stmt->fetchAll();
} else {
    $links = [];
    $errors[] = 'The parent_student_links table is missing. Re-import schema.sql or add the table from the latest schema.';
}

require_once __DIR__ . '/includes_header.php';
?>

<div class="eq-page-head d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h2 class="mb-0">Parent-Child Links</h2>
        <p class="subtitle">Link student accounts to parents for accurate child trends, attendance, and feedback on parent dashboards.</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-5">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Link a student</h5>
                <form method="post">
                    <?php echo csrf_field(); ?>
                    <?php if ($user['role'] === 'school_admin'): ?>
                        <div class="mb-3">
                            <label class="form-label">Parent</label>
                            <select name="parent_id" class="form-select" required>
                                <option value="">Select parent</option>
                                <?php foreach ($parentOptions as $parent): ?>
                                    <option value="<?php echo (int)$parent['id']; ?>">
                                        <?php echo htmlspecialchars($parent['name'] . ' (' . $parent['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Student Email</label>
                        <input type="email" name="child_email" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary" <?php echo $tableReady ? '' : 'disabled'; ?>>Save Link</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Existing Links</h5>
                <?php if (!$links): ?>
                    <p class="text-muted small mb-0">No links created yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <?php if ($user['role'] === 'school_admin'): ?>
                                        <th>Parent</th>
                                        <th>Student</th>
                                    <?php else: ?>
                                        <th>Student</th>
                                    <?php endif; ?>
                                    <th>Linked At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($links as $row): ?>
                                    <tr>
                                        <?php if ($user['role'] === 'school_admin'): ?>
                                            <td><?php echo htmlspecialchars($row['parent_name'] . ' (' . $row['parent_email'] . ')'); ?></td>
                                            <td><?php echo htmlspecialchars($row['student_name'] . ' (' . $row['student_email'] . ')'); ?></td>
                                        <?php else: ?>
                                            <td><?php echo htmlspecialchars($row['name'] . ' (' . $row['email'] . ')'); ?></td>
                                        <?php endif; ?>
                                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes_footer.php';
