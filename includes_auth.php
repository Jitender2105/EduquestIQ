<?php
// EduquestIQ - Authentication, JWT, role checks, and rate limiting helpers

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Create a signed JWT (HS256).
 */
function jwt_encode(array $payload): string
{
    $header = ['typ' => 'JWT', 'alg' => 'HS256'];
    $segments = [];
    $segments[] = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
    $segments[] = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    $signing_input = implode('.', $segments);
    $signature = hash_hmac('sha256', $signing_input, JWT_SECRET, true);
    $segments[] = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    return implode('.', $segments);
}

/**
 * Decode and verify a JWT. Returns payload array or null.
 */
function jwt_decode(string $jwt): ?array
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }
    [$b64_header, $b64_payload, $b64_signature] = $parts;
    $header = json_decode(base64_decode(strtr($b64_header, '-_', '+/')), true);
    $payload = json_decode(base64_decode(strtr($b64_payload, '-_', '+/')), true);
    if (!is_array($header) || !is_array($payload)) {
        return null;
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        return null;
    }

    $signing_input = $b64_header . '.' . $b64_payload;
    $expected = hash_hmac('sha256', $signing_input, JWT_SECRET, true);
    $signature = base64_decode(strtr($b64_signature, '-_', '+/'));

    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $now = time();
    if (isset($payload['iss']) && $payload['iss'] !== JWT_ISSUER) {
        return null;
    }
    if (isset($payload['exp']) && $payload['exp'] < $now) {
        return null;
    }
    if (isset($payload['nbf']) && $payload['nbf'] > $now) {
        return null;
    }

    return $payload;
}

/**
 * Log user in: create JWT and set secure HttpOnly cookie.
 */
function login_user(array $user): void
{
    $now = time();
    $payload = [
        'sub'   => (int)$user['id'],
        'name'  => $user['name'],
        'role'  => $user['role'],
        'school_id' => $user['school_id'] ?? null,
        'iss'   => JWT_ISSUER,
        'iat'   => $now,
        'nbf'   => $now,
        'exp'   => $now + JWT_EXPIRY_SECONDS,
    ];
    $token = jwt_encode($payload);

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('auth_token', $token, [
        'expires'  => $payload['exp'],
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Destroy auth cookie.
 */
function logout_user(): void
{
    if (isset($_COOKIE['auth_token'])) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('auth_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE['auth_token']);
    }
}

/**
 * Get current authenticated user payload or null.
 */
function current_user(): ?array
{
    if (empty($_COOKIE['auth_token'])) {
        return null;
    }
    $payload = jwt_decode($_COOKIE['auth_token']);
    if (!$payload) {
        return null;
    }
    return $payload;
}

/**
 * Require login for a page.
 */
function require_auth(array $allowed_roles = []): array
{
    $user = current_user();
    if (!$user) {
        header('Location: ' . url_for('login.php'));
        exit;
    }
    if ($allowed_roles && !in_array($user['role'], $allowed_roles, true)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    return $user;
}

/**
 * Simple login rate limiting by email + IP.
 */
function is_login_rate_limited(string $email, string $ip): bool
{
    $pdo = get_pdo();

    // Limit: 5 attempts per 15 minutes per IP+email
    $stmt = $pdo->prepare('SELECT id, attempt_count, last_attempt_at FROM login_attempts WHERE email = ? AND ip_address = ?');
    $stmt->execute([$email, $ip]);
    $row = $stmt->fetch();

    $now = new DateTimeImmutable('now');
    $windowMinutes = 15;
    $maxAttempts = 5;

    if ($row) {
        $last = new DateTimeImmutable($row['last_attempt_at']);
        $diffMinutes = ($now->getTimestamp() - $last->getTimestamp()) / 60;
        if ($diffMinutes > $windowMinutes) {
            $stmt = $pdo->prepare('UPDATE login_attempts SET attempt_count = 0, last_attempt_at = NOW() WHERE id = ?');
            $stmt->execute([$row['id']]);
            return false;
        }
        return (int)$row['attempt_count'] >= $maxAttempts;
    }

    return false;
}

function record_login_attempt(string $email, string $ip, bool $success): void
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, attempt_count FROM login_attempts WHERE email = ? AND ip_address = ?');
    $stmt->execute([$email, $ip]);
    $row = $stmt->fetch();

    if ($success) {
        if ($row) {
            $stmt = $pdo->prepare('UPDATE login_attempts SET attempt_count = 0, last_attempt_at = NOW() WHERE id = ?');
            $stmt->execute([$row['id']]);
        }
        return;
    }

    if ($row) {
        $stmt = $pdo->prepare('UPDATE login_attempts SET attempt_count = attempt_count + 1, last_attempt_at = NOW() WHERE id = ?');
        $stmt->execute([$row['id']]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO login_attempts (email, ip_address, attempt_count, last_attempt_at) VALUES (?, ?, 1, NOW())');
        $stmt->execute([$email, $ip]);
    }
}
