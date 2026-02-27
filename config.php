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
    'base_url' => '',
];

$localConfigFile = __DIR__ . '/config.local.php';
if (is_file($localConfigFile)) {
    $local = require $localConfigFile;
    if (is_array($local)) {
        $config = array_replace($config, $local);
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
    $path = '/' . ltrim($path, '/');
    return $base . $path;
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
