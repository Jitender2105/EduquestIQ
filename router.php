<?php
declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = __DIR__ . $uri;

if ($uri !== '/' && str_ends_with($uri, '.php')) {
    $target = rtrim(substr($uri, 0, -4), '/');
    if ($target === '') {
        $target = '/';
    }
    $query = $_SERVER['QUERY_STRING'] ?? '';
    if ($query !== '') {
        $target .= '?' . $query;
    }
    header('Location: ' . $target, true, 301);
    exit;
}

if ($uri === '/') {
    require __DIR__ . '/index.php';
    return true;
}

if (preg_match('#^/articles/([^/]+)/?$#', $uri, $matches)) {
    $_GET['slug'] = $matches[1];
    require __DIR__ . '/article.php';
    return true;
}

if (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
    return false;
}

$clean = ltrim($uri, '/');
$candidate = __DIR__ . '/' . $clean . '.php';
if (is_file($candidate)) {
    require $candidate;
    return true;
}

http_response_code(404);
echo '404 Not Found';
return true;
