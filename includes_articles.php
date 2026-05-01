<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes_files.php';

function article_slugify(string $title): string
{
    $title = trim($title);
    if ($title === '') {
        return 'article';
    }

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
    if (!is_string($ascii) || $ascii === '') {
        $ascii = $title;
    }

    $slug = strtolower($ascii);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'article';
}

function article_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function article_upload_image(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('Invalid image size. Maximum is 5 MB.');
    }

    $originalName = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Invalid image extension.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $tmp);
            if (is_string($detected)) {
                $mime = $detected;
            }
            finfo_close($finfo);
        }
    }

    $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if ($mime !== '' && !in_array($mime, $allowedMime, true)) {
        throw new RuntimeException('Invalid image file type.');
    }

    $dir = ensure_upload_dir('uploads/articles');
    $basename = bin2hex(random_bytes(12)) . '.' . $ext;
    $target = $dir . '/' . $basename;

    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('Failed to save uploaded image.');
    }

    return 'uploads/articles/' . $basename;
}

function article_excerpt(string $html, int $width = 180): string
{
    return text_preview(trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? ''), $width);
}

function article_unique_slug(PDO $pdo, string $title, ?int $ignoreId = null): string
{
    $base = article_slugify($title);
    $slug = $base;
    $suffix = 2;

    while (true) {
        if (article_slug_exists($pdo, $slug, $ignoreId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
            continue;
        }
        return $slug;
    }
}

function article_slug_exists(PDO $pdo, string $slug, ?int $ignoreId = null): bool
{
    if (!article_table_exists($pdo, 'articles')) {
        return false;
    }

    if ($ignoreId !== null) {
        $stmt = $pdo->prepare('SELECT id FROM articles WHERE slug = ? AND id <> ? LIMIT 1');
        $stmt->execute([$slug, $ignoreId]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM articles WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
    }

    return (bool)$stmt->fetchColumn();
}

