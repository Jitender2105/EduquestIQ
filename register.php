<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_csrf.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $schoolId = isset($_POST['school_id']) && $_POST['school_id'] !== '' ? (int)$_POST['school_id'] : null;
    $csrf = $_POST['csrf_token'] ?? null;

    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Invalid CSRF token. Please refresh and try again.';
    }

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (!in_array($role, ['student', 'parent', 'teacher', 'school_admin'], true)) {
        $errors[] = 'Invalid role selected.';
    }
    if ($schoolId !== null && $schoolId <= 0) {
        $errors[] = 'School ID must be a positive number.';
    }

    if (!$errors) {
        try {
            $pdo = get_pdo();

            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, school_id) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$name, $email, $hash, $role, $schoolId]);
                $success = true;
            }
        } catch (Throwable $e) {
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="eq-page-head">
            <h2>Create Your EduquestIQ Account</h2>
            <p class="subtitle">Join as a student, parent, teacher, or school admin and start with role-based insights immediately.</p>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success">
                Registration successful. You can now <a href="<?php echo htmlspecialchars(url_for('login.php')); ?>">log in</a>.
            </div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post" class="card p-3">
            <?php echo csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" required value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select" required>
                    <option value="student" <?php echo (isset($role) && $role === 'student') ? 'selected' : ''; ?>>Student</option>
                    <option value="parent" <?php echo (isset($role) && $role === 'parent') ? 'selected' : ''; ?>>Parent</option>
                    <option value="teacher" <?php echo (isset($role) && $role === 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                    <option value="school_admin" <?php echo (isset($role) && $role === 'school_admin') ? 'selected' : ''; ?>>School Admin</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">School ID (optional)</label>
                <input type="number" min="1" name="school_id" class="form-control" value="<?php echo isset($schoolId) && $schoolId !== null ? (int)$schoolId : ''; ?>">
                <div class="form-text">Use the same School ID for related students/parents/teachers to group dashboard data.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="password_confirm" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Register</button>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/includes_footer.php';
