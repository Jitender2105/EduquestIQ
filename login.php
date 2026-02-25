<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';
require_once __DIR__ . '/includes_csrf.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? null;

    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Invalid CSRF token. Please refresh and try again.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!$errors && is_login_rate_limited($email, $ip)) {
        $errors[] = 'Too many attempts. Please wait a few minutes before trying again.';
    }

    if (!$errors) {
        try {
            $pdo = get_pdo();
            $stmt = $pdo->prepare('SELECT id, name, email, password, role, status, school_id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                record_login_attempt($email, $ip, false);
                $errors[] = 'Invalid email or password.';
            } elseif ($user['status'] !== 'active') {
                $errors[] = 'Your account is not active.';
            } else {
                record_login_attempt($email, $ip, true);
                login_user($user);
                header('Location: ' . url_for('dashboard.php'));
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = 'Login failed. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes_header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="eq-page-head">
            <h2>Welcome Back</h2>
            <p class="subtitle">Sign in to access your skill dashboard, courses, tests, and community learning feed.</p>
        </div>
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
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/includes_footer.php';
