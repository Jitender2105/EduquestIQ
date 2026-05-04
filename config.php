<?php
// EduquestIQ - Global configuration and database connection

declare(strict_types=1);

// Configure secure PHP session (used for CSRF + some server-side state)
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

date_default_timezone_set('UTC');

function load_env_file(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $values = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }

        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        $values[$key] = $value;
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }

    return $values;
}

// Default configuration (safe to commit). Override in config.local.php for local/production secrets.
$config = [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'EduquestIQ',
    'db_user' => 'your_db_user',
    'db_pass' => 'your_db_password',
    'jwt_secret' => 'change_this_to_a_long_random_secret_string',
    'jwt_issuer' => 'eduquestiq',
    'jwt_expiry_seconds' => 60 * 60 * 24 * 7, // 7 days
    'razorpay_key_id' => '',
    'razorpay_key_secret' => '',
    'razorpay_webhook_secret' => '',
    'payment_support_email' => 'jitender@eduquestiq.com',
    'base_url' => '',
];

$localConfigFile = __DIR__ . '/config.local.php';
if (is_file($localConfigFile)) {
    $local = require $localConfigFile;
    if (is_array($local)) {
        $config = array_replace($config, $local);
    }
}

$env = load_env_file(__DIR__ . '/.env');
$envMap = [
    'RAZORPAY_KEY_ID' => 'razorpay_key_id',
    'RAZORPAY_KEY_SECRET' => 'razorpay_key_secret',
    'RAZORPAY_WEBHOOK_SECRET' => 'razorpay_webhook_secret',
    'PAYMENT_SUPPORT_EMAIL' => 'payment_support_email',
    'BASE_URL' => 'base_url',
];
foreach ($envMap as $envKey => $configKey) {
    $value = getenv($envKey);
    if ($value !== false && trim((string)$value) !== '') {
        $config[$configKey] = (string)$value;
    } elseif (isset($env[$envKey]) && trim((string)$env[$envKey]) !== '') {
        $config[$configKey] = (string)$env[$envKey];
    }
}

define('DB_HOST', (string)$config['db_host']);
define('DB_PORT', (int)$config['db_port']);
define('DB_NAME', (string)$config['db_name']);
define('DB_USER', (string)$config['db_user']);
define('DB_PASS', (string)$config['db_pass']);

define('JWT_SECRET', (string)$config['jwt_secret']);
define('JWT_ISSUER', (string)$config['jwt_issuer']);
define('JWT_EXPIRY_SECONDS', (int)$config['jwt_expiry_seconds']);
define('BASE_URL', (string)$config['base_url']);

/**
 * Get a shared PDO connection.
 */
function get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    return $pdo;
}

/**
 * Helper to build absolute URL paths.
 */
function url_for(string $path = ''): string
{
    $base = rtrim(BASE_URL, '/');
    $path = ltrim($path, '/');

    $query = '';
    if (str_contains($path, '?')) {
        [$path, $query] = explode('?', $path, 2);
    }

    if ($path === '' || $path === 'index.php') {
        $normalized = '/';
    } else {
        if (str_ends_with($path, '.php')) {
            $path = substr($path, 0, -4);
        }
        $normalized = '/' . ltrim($path, '/');
    }

    if ($query !== '') {
        $normalized .= '?' . $query;
    }

    return $base . $normalized;
}

/**
 * Safe string preview helper that works with and without mbstring.
 */
function text_preview(string $text, int $width = 140, string $trimMarker = '...'): string
{
    if ($width <= 0) {
        return '';
    }

    if (function_exists('mb_strimwidth')) {
        return (string)mb_strimwidth($text, 0, $width, $trimMarker, 'UTF-8');
    }

    if (strlen($text) <= $width) {
        return $text;
    }

    $trimWidth = max(0, $width - strlen($trimMarker));
    return substr($text, 0, $trimWidth) . $trimMarker;
}
