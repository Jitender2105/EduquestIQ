<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes_articles.php';

header('Content-Type: application/xml; charset=UTF-8');

function sitemap_origin(): string
{
    if (defined('BASE_URL') && trim((string)BASE_URL) !== '') {
        return rtrim((string)BASE_URL, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'eduquestiq.com');
    return $scheme . '://' . $host;
}

function sitemap_url(string $path): string
{
    return sitemap_origin() . '/' . ltrim($path, '/');
}

function sitemap_lastmod(?string $value = null): string
{
    try {
        $date = $value ? new DateTimeImmutable($value) : new DateTimeImmutable('now');
    } catch (Throwable $e) {
        $date = new DateTimeImmutable('now');
    }

    return $date->format('Y-m-d');
}

$urls = [
    ['loc' => sitemap_url('/'), 'changefreq' => 'weekly', 'priority' => '1.0'],
    ['loc' => sitemap_url('/tests'), 'changefreq' => 'daily', 'priority' => '0.95'],
    ['loc' => sitemap_url('/stem-test'), 'changefreq' => 'weekly', 'priority' => '0.90'],
    ['loc' => sitemap_url('/olympiad-exam'), 'changefreq' => 'weekly', 'priority' => '0.90'],
    ['loc' => sitemap_url('/competitive-exam-grade-2'), 'changefreq' => 'weekly', 'priority' => '0.88'],
    ['loc' => sitemap_url('/competitive-exam-grade-3'), 'changefreq' => 'weekly', 'priority' => '0.88'],
    ['loc' => sitemap_url('/competitive-exam-grade-4'), 'changefreq' => 'weekly', 'priority' => '0.88'],
    ['loc' => sitemap_url('/articles'), 'changefreq' => 'weekly', 'priority' => '0.85'],
    ['loc' => sitemap_url('/courses'), 'changefreq' => 'weekly', 'priority' => '0.75'],
    ['loc' => sitemap_url('/video_lectures'), 'changefreq' => 'weekly', 'priority' => '0.70'],
    ['loc' => sitemap_url('/study-material'), 'changefreq' => 'weekly', 'priority' => '0.78'],
    ['loc' => sitemap_url('/about'), 'changefreq' => 'monthly', 'priority' => '0.60'],
];

try {
    $pdo = get_pdo();
    if (article_table_exists($pdo, 'articles')) {
        $visibility = article_active_clause($pdo, 'a');
        $stmt = $pdo->query(
            'SELECT a.slug, a.created_at
             FROM articles a
             WHERE ' . $visibility . '
             ORDER BY a.created_at DESC
             LIMIT 1000'
        );
        foreach ($stmt->fetchAll() as $article) {
            $slug = trim((string)($article['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $urls[] = [
                'loc' => sitemap_url('/articles/' . rawurlencode($slug)),
                'lastmod' => sitemap_lastmod((string)($article['created_at'] ?? '')),
                'changefreq' => 'monthly',
                'priority' => '0.70',
            ];
        }
    }
} catch (Throwable $e) {
    // Keep the static sitemap valid even if the database is unavailable.
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
    <url>
        <loc><?php echo htmlspecialchars($url['loc'], ENT_XML1); ?></loc>
        <lastmod><?php echo htmlspecialchars($url['lastmod'] ?? sitemap_lastmod(), ENT_XML1); ?></lastmod>
        <changefreq><?php echo htmlspecialchars($url['changefreq'], ENT_XML1); ?></changefreq>
        <priority><?php echo htmlspecialchars($url['priority'], ENT_XML1); ?></priority>
    </url>
<?php endforeach; ?>
</urlset>
