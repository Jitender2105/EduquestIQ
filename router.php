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

if ($uri === '/study-material') {
    require __DIR__ . '/materials.php';
    return true;
}

$seoTestAliases = [
    '/stem-test' => [
        'alias' => 'stem-test',
        'title' => 'STEM Test Preparation for Students | EduquestIQ',
        'description' => 'Practice STEM tests for mathematics, science, reasoning, and student readiness with EduquestIQ grade-wise assessments and SIRA reporting.',
    ],
    '/olympiad-exam' => [
        'alias' => 'olympiad-exam',
        'title' => 'Olympiad Exam Preparation & Practice Tests | EduquestIQ',
        'description' => 'Prepare for Olympiad-style exams with STEM practice tests, reasoning assessments, downloadable papers, and grade-wise exam readiness tools.',
    ],
    '/competitive-exam-grade-2' => [
        'alias' => 'competitive-exam-grade-2',
        'title' => 'Competitive Exam for Grade 2 | EduquestIQ',
        'description' => 'Grade 2 competitive exam preparation with STEM, reasoning, language, life skills, and Olympiad-style practice tests.',
    ],
    '/competitive-exam-grade-3' => [
        'alias' => 'competitive-exam-grade-3',
        'title' => 'Competitive Exam for Grade 3 | EduquestIQ',
        'description' => 'Grade 3 competitive exam and Olympiad preparation with STEM tests, practice papers, and skill readiness reports.',
    ],
    '/competitive-exam-grade-4' => [
        'alias' => 'competitive-exam-grade-4',
        'title' => 'Competitive Exam for Grade 4 | EduquestIQ',
        'description' => 'Grade 4 competitive exam preparation with STEM readiness tests, Olympiad practice, and SIRA performance insights.',
    ],
];

if (isset($seoTestAliases[$uri])) {
    $_GET['seo_alias'] = $seoTestAliases[$uri]['alias'];
    $GLOBALS['metaTitleOverride'] = $seoTestAliases[$uri]['title'];
    $GLOBALS['metaDescriptionOverride'] = $seoTestAliases[$uri]['description'];
    $GLOBALS['canonicalUrlOverride'] = 'https://eduquestiq.com' . $uri;
    require __DIR__ . '/tests.php';
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
