<?php
// EduquestIQ - Shared HTML header (include at top of pages)

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes_auth.php';

$authUser = current_user();
$currentPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$pageMeta = [
    'index.php' => ['EduquestIQ | Skills-First Learning Platform', 'Master academics, creativity, leadership, and technical skills with dynamic courses, tests, and progress dashboards.'],
    'about.php' => ['About EduquestIQ | What We Do', 'Learn how EduquestIQ helps students, parents, teachers, and schools drive measurable learning outcomes.'],
    'courses.php' => ['Courses | EduquestIQ', 'Browse skill-mapped courses, enroll, and continue learning through videos, tests, and study resources.'],
    'course.php' => ['Course Details | EduquestIQ', 'View course curriculum, lectures, materials, and progress in one place.'],
    'tests.php' => ['Tests & Assessments | EduquestIQ', 'Attempt mapped assessments that update attribute and sub-attribute level skill scores.'],
    'test_attempt.php' => ['Attempt Test | EduquestIQ', 'Complete MCQ and subjective assessments with live scoring and skill impact.'],
    'learning_paths.php' => ['Learning Paths | EduquestIQ', 'Follow structured course journeys or learn self-paced with saved progress.'],
    'community.php' => ['Community Learning | EduquestIQ', 'Collaborate with peers through posts, comments, and likes in the EduquestIQ community.'],
    'articles.php' => ['Articles | EduquestIQ', 'Explore learning insights, study tips, and curated education resources.'],
    'video_lectures.php' => ['Video Lectures | EduquestIQ', 'Access course-linked video lessons with sequence and duration tracking.'],
    'materials.php' => ['Study Materials | EduquestIQ', 'Find PDFs, DOCs, PPTs, and links organized by course and skill domain.'],
    'dashboard.php' => ['Dashboard | EduquestIQ', 'Track progress, skills, achievements, and performance through dynamic role-based dashboards.'],
    'backend.php' => ['Backend Management | EduquestIQ', 'Manage LMS entities including questions, attributes, courses, and content modules.'],
    'manage_lms.php' => ['LMS Admin Panel | EduquestIQ', 'Configure tests, questions, mappings, courses, and learning content from one panel.'],
    'parent_children.php' => ['Parent Links | EduquestIQ', 'Connect child accounts and monitor learning trends, performance, and feedback.'],
    'teacher_feedback.php' => ['Teacher Feedback | EduquestIQ', 'Review and manage feedback interactions between teachers, students, and parents.'],
    'login.php' => ['Login | EduquestIQ', 'Sign in to access your EduquestIQ dashboard and learning modules.'],
    'register.php' => ['Register | EduquestIQ', 'Create your EduquestIQ account as student, parent, teacher, or school admin.'],
    'privacy.php' => ['Privacy Policy | EduquestIQ', 'Read how EduquestIQ handles, secures, and processes user data.'],
    'terms.php' => ['Terms & Conditions | EduquestIQ', 'Review the terms governing use of the EduquestIQ platform.'],
    'material_upload.php' => ['Upload Material | EduquestIQ', 'Upload validated study resources linked to courses and learners.'],
];
$metaTitle = $pageMeta[$currentPage][0] ?? 'EduquestIQ | Learning Platform';
$metaDescription = $pageMeta[$currentPage][1] ?? 'EduquestIQ is a skills-first LMS for students, parents, teachers, and school administrators.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($metaTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(url_for('assets/img/favicon.png')); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(url_for('assets/img/favicon.png')); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --eq-bg: #f3f6ff;
            --eq-surface: #ffffff;
            --eq-text: #15182b;
            --eq-muted: #727a96;
            --eq-line: rgba(39, 53, 110, 0.08);
            --eq-blue: #4374ff;
            --eq-indigo: #5d47ff;
            --eq-magenta: #b335ff;
            --eq-gradient: linear-gradient(135deg, #3e6fff 0%, #6f3bff 48%, #be34ff 100%);
            --eq-shadow: 0 18px 44px rgba(47, 59, 120, 0.14);
            --eq-radius: 20px;
        }

        html, body { background: var(--eq-bg); }
        body {
            color: var(--eq-text);
            font-family: 'Manrope', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, h3, h4, h5, h6, .navbar-brand {
            font-family: 'Outfit', sans-serif;
            letter-spacing: -0.02em;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.92) !important;
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--eq-line);
        }

        .navbar-brand {
            font-weight: 800;
            color: #2e3f95 !important;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .eq-brand-logo {
            display: block;
            height: 32px;
            max-width: 210px;
            width: auto;
            object-fit: contain;
            filter: drop-shadow(0 4px 8px rgba(40, 54, 122, 0.12));
        }
        .eq-brand-mark {
            display: none;
            width: 112px;
            height: 30px;
            object-fit: contain;
            border-radius: 0;
            box-shadow: none;
        }
        .eq-brand-text {
            display: none;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            color: #2e3f95;
        }

        .nav-link {
            color: #5f6786 !important;
            font-weight: 600;
            font-size: 0.92rem;
        }

        .nav-link:hover,
        .nav-link:focus {
            color: #2f3c8f !important;
        }

        .btn {
            border-radius: 12px;
            font-weight: 700;
            border-width: 1px;
        }

        .btn-primary {
            background: var(--eq-gradient);
            border: none;
            box-shadow: 0 10px 20px rgba(102, 59, 255, 0.24);
        }

        .btn-primary:hover,
        .btn-primary:focus {
            filter: brightness(1.03);
        }

        .btn-outline-primary {
            border-color: rgba(79, 92, 180, 0.24);
            color: #3b4bb5;
            background: #fff;
        }

        .btn-outline-primary:hover,
        .btn-outline-primary:focus {
            background: rgba(67, 116, 255, 0.07);
            color: #3142a7;
            border-color: rgba(79, 92, 180, 0.3);
        }

        .btn-outline-secondary {
            border-color: rgba(58, 74, 141, 0.16);
            color: #59627f;
        }

        .hero {
            position: relative;
            overflow: hidden;
            border-radius: 0 0 28px 28px;
            padding: 72px 0 68px;
            background: radial-gradient(circle at 15% 20%, rgba(255,255,255,0.14), rgba(255,255,255,0) 40%),
                        radial-gradient(circle at 80% 10%, rgba(255,255,255,0.12), rgba(255,255,255,0) 35%),
                        var(--eq-gradient);
            box-shadow: inset 0 -1px 0 rgba(255,255,255,0.12);
        }

        .hero::before,
        .hero::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            filter: blur(24px);
            opacity: 0.35;
            pointer-events: none;
        }

        .hero::before {
            width: 260px;
            height: 260px;
            background: #fff;
            top: -70px;
            left: -60px;
        }

        .hero::after {
            width: 320px;
            height: 320px;
            background: #ff8af6;
            right: -90px;
            bottom: -120px;
        }

        .eq-card,
        .card,
        .card-dashboard {
            border: 1px solid var(--eq-line);
            border-radius: var(--eq-radius);
            background: var(--eq-surface);
            box-shadow: var(--eq-shadow);
        }

        .card-dashboard {
            box-shadow: 0 10px 24px rgba(21, 32, 88, 0.08);
        }

        .eq-soft-card {
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.18);
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(8px);
            color: #fff;
        }

        .eq-section {
            padding: 56px 0;
        }
        .eq-page-shell {
            padding-top: 6px;
        }
        .eq-page-head {
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(67,116,255,0.12), rgba(179,53,255,0.08));
            border: 1px solid rgba(77, 95, 173, 0.12);
            padding: 18px 20px;
            margin-bottom: 18px;
        }
        .eq-page-head h1,
        .eq-page-head h2 {
            margin: 0 0 4px;
            font-weight: 700;
            font-size: clamp(1.25rem, 2vw, 1.75rem);
        }
        .eq-page-head p,
        .eq-page-head .subtitle {
            margin: 0;
            color: var(--eq-muted);
            font-size: 0.92rem;
        }

        .eq-section-title {
            text-align: center;
            margin-bottom: 28px;
        }

        .eq-section-title h2 {
            font-size: clamp(1.3rem, 3vw, 2rem);
            font-weight: 700;
            margin-bottom: 8px;
        }

        .eq-section-title p {
            color: var(--eq-muted);
            margin: 0 auto;
            max-width: 680px;
            font-size: 0.95rem;
        }

        .eq-muted { color: var(--eq-muted) !important; }
        .eq-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.22);
            color: #fff;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 6px 12px;
        }
        .eq-chip::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #ffd45f;
            box-shadow: 0 0 0 3px rgba(255,212,95,0.18);
        }

        .eq-metric {
            border-radius: 14px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            color: #fff;
            padding: 10px 12px;
        }
        .eq-metric strong { display: block; font-size: 1rem; font-family: 'Outfit', sans-serif; }
        .eq-metric span { font-size: 0.75rem; opacity: 0.8; }

        .eq-feature-card {
            height: 100%;
            border-radius: 18px;
            border: 1px solid var(--eq-line);
            background: #fff;
            padding: 20px;
            box-shadow: 0 8px 24px rgba(34, 51, 109, 0.05);
        }

        .eq-feature-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .eq-glow-band {
            padding: 44px 0;
            background: var(--eq-gradient);
            color: #fff;
            border-radius: 28px 28px 0 0;
            margin-top: 36px;
            box-shadow: 0 -14px 30px rgba(87, 64, 255, 0.15);
        }

        .eq-list-clean { list-style: none; padding: 0; margin: 0; }
        .eq-list-clean li { margin-bottom: 8px; color: var(--eq-muted); font-size: 0.88rem; }

        .eq-mini-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .eq-dashboard-mock {
            border-radius: 18px;
            background: linear-gradient(180deg, rgba(255,255,255,0.12), rgba(255,255,255,0.05));
            border: 1px solid rgba(255,255,255,0.18);
            padding: 16px;
            color: #fff;
            box-shadow: 0 20px 40px rgba(31, 10, 94, 0.2);
        }

        .eq-ring {
            width: 82px;
            height: 82px;
            border-radius: 999px;
            margin: 8px auto 12px;
            background:
                radial-gradient(circle closest-side, rgba(102,58,255,0.45) 72%, transparent 73% 100%),
                conic-gradient(#ffd45f 0 270deg, rgba(255,255,255,0.18) 270deg 360deg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1rem;
        }

        .eq-subtle {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 10px;
        }

        .eq-hero-copy h1 {
            color: #fff;
            font-size: clamp(2rem, 4vw, 3.15rem);
            line-height: 1.04;
            font-weight: 800;
            margin-top: 12px;
            margin-bottom: 14px;
        }
        .eq-hero-copy h1 .accent { color: #ffd45f; }
        .eq-hero-copy p {
            color: rgba(255,255,255,0.82);
            max-width: 540px;
            font-size: 0.98rem;
            line-height: 1.6;
            margin-bottom: 22px;
        }

        .eq-hero-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
        .eq-hero-actions .btn { min-width: 145px; }
        .eq-hero-actions .btn-outline-light {
            color: #fff;
            border-color: rgba(255,255,255,0.28);
            background: rgba(255,255,255,0.06);
        }
        .eq-hero-actions .btn-outline-light:hover { background: rgba(255,255,255,0.14); }

        .eq-trust-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            max-width: 480px;
        }

        .alert, .list-group-item, .form-control, .form-select, .input-group-text {
            border-radius: 12px !important;
            border-color: rgba(52, 67, 126, 0.12);
        }
        .table {
            --bs-table-bg: transparent;
            --bs-table-striped-bg: rgba(67,116,255,0.03);
            --bs-table-hover-bg: rgba(67,116,255,0.04);
            --bs-table-border-color: rgba(52, 67, 126, 0.08);
        }
        .table thead th {
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            color: #4f5674;
            border-bottom-width: 1px;
        }
        .accordion-item {
            border: 1px solid rgba(52, 67, 126, 0.1);
            border-radius: 16px !important;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(21, 32, 88, 0.04);
        }
        .accordion-button {
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            background: #fff;
        }
        .accordion-button:not(.collapsed) {
            background: linear-gradient(135deg, rgba(67,116,255,0.08), rgba(179,53,255,0.05));
            color: #2b377e;
            box-shadow: none;
        }
        .accordion-button:focus {
            box-shadow: 0 0 0 .2rem rgba(67,116,255,0.1);
        }
        .badge {
            border-radius: 10px;
            font-weight: 700;
        }
        .list-group-item {
            background: transparent;
        }

        .form-control:focus, .form-select:focus {
            border-color: rgba(67, 116, 255, 0.42);
            box-shadow: 0 0 0 .2rem rgba(67, 116, 255, 0.12);
        }

        footer {
            background: rgba(255,255,255,0.92) !important;
            border-top: 1px solid var(--eq-line) !important;
        }

        .eq-home-hero {
            margin-top: 8px;
            border-radius: 0;
            background: radial-gradient(circle at 18% 20%, rgba(255,255,255,0.14), transparent 35%),
                        radial-gradient(circle at 82% 6%, rgba(255,255,255,0.16), transparent 32%),
                        linear-gradient(135deg, #356eff 0%, #6a40ff 48%, #bd2ee5 100%);
            padding: 74px 4vw;
            color: #fff;
        }

        .eq-home-hero-grid {
            max-width: 1120px;
            margin: 0 auto;
            display: grid;
            gap: 40px;
            grid-template-columns: 1.05fr 1fr;
            align-items: center;
        }
        .eq-home-hero h1 {
            font-size: clamp(2rem, 4.2vw, 4rem);
            line-height: 1.04;
            margin: 16px 0 14px;
            color: #fff;
        }
        .eq-home-hero h1 .accent { color: #ffcc3e; }
        .eq-home-hero p {
            margin: 0;
            max-width: 560px;
            color: rgba(255,255,255,0.84);
            font-size: 1.02rem;
            line-height: 1.6;
        }
        .eq-home-hero-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 22px;
        }
        .eq-home-hero-actions .btn {
            min-width: 162px;
            border-radius: 10px;
            font-weight: 700;
            border: 1px solid rgba(255,255,255,0.26);
        }
        .eq-home-hero-actions .btn-light {
            color: #2a3e96;
            background: #fff;
            border-color: #fff;
        }
        .eq-home-hero-actions .btn-outline-light {
            background: rgba(255,255,255,0.08);
        }
        .eq-home-hero-metrics {
            margin-top: 18px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            max-width: 460px;
        }
        .eq-home-hero-metrics div {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.14);
            border-radius: 12px;
            padding: 10px 12px;
        }
        .eq-home-hero-metrics strong {
            display: block;
            font-family: 'Outfit', sans-serif;
            font-size: 1.2rem;
            line-height: 1.1;
        }
        .eq-home-hero-metrics span {
            display: block;
            color: rgba(255,255,255,0.77);
            font-size: 0.78rem;
        }
        .eq-hero-panel {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.17);
            border-radius: 18px;
            box-shadow: 0 24px 45px rgba(39, 18, 109, 0.2);
            padding: 16px;
        }
        .eq-hero-panel-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            font-size: 0.83rem;
            color: rgba(255,255,255,0.85);
            font-weight: 600;
        }
        .eq-panel-badge {
            width: 32px;
            height: 32px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #ffcf3f;
            color: #6b4d00;
            font-size: 1rem;
        }
        .eq-hero-panel-ring {
            margin: 8px auto 16px;
            width: 92px;
            height: 92px;
            border-radius: 999px;
            background:
                radial-gradient(circle closest-side, rgba(93,61,255,0.48) 69%, transparent 70% 100%),
                conic-gradient(#ffc83c 0 270deg, rgba(255,255,255,0.16) 270deg 360deg);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            line-height: 1;
        }
        .eq-hero-panel-ring small {
            margin-top: 4px;
            font-size: 0.62rem;
            color: rgba(255,255,255,0.8);
        }
        .eq-hero-panel-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }
        .eq-hero-panel-grid div {
            border-radius: 12px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 10px;
            text-align: center;
        }
        .eq-hero-panel-grid small {
            display: block;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.72);
        }
        .eq-hero-panel-grid strong {
            font-size: 0.95rem;
            color: #fff;
        }
        .eq-hero-panel-activity {
            border-radius: 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 10px;
        }
        .eq-hero-panel-activity h6 {
            color: rgba(255,255,255,0.84);
            margin: 0 0 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .eq-hero-panel-activity p {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin: 0 0 5px;
            font-size: 0.72rem;
            color: rgba(255,255,255,0.75);
        }
        .eq-home-section {
            max-width: 1120px;
            margin: 0 auto;
            padding: 54px 14px;
        }
        .eq-skill-card {
            height: 100%;
            border-radius: 14px;
            border: 1px solid rgba(79, 96, 168, 0.12);
            padding: 16px;
            background: #fff;
        }
        .eq-skill-card .eq-skill-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            font-size: 1rem;
        }
        .eq-skill-card h5 {
            font-size: 1.22rem;
            margin-bottom: 8px;
        }
        .eq-skill-card p {
            color: #70779a;
            font-size: 0.88rem;
            min-height: 64px;
        }
        .eq-skill-card ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .eq-skill-card li {
            font-size: 0.8rem;
            color: #5e678d;
            margin-bottom: 5px;
            position: relative;
            padding-left: 14px;
        }
        .eq-skill-card li::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 999px;
            position: absolute;
            top: 6px;
            left: 0;
            background: #5a89ff;
        }
        .eq-skill-card.academic { background: #eef4ff; }
        .eq-skill-card.academic .eq-skill-icon { background: #3f78ff; color: #fff; }
        .eq-skill-card.creative { background: #f5ecff; }
        .eq-skill-card.creative .eq-skill-icon { background: #a05eff; color: #fff; }
        .eq-skill-card.leadership { background: #eaf8ec; }
        .eq-skill-card.leadership .eq-skill-icon { background: #69ca83; color: #fff; }
        .eq-skill-card.technical { background: #fff2e7; }
        .eq-skill-card.technical .eq-skill-icon { background: #f1a673; color: #fff; }
        .eq-home-platform .eq-platform-card {
            border: 1px solid rgba(68, 86, 161, 0.11);
            border-radius: 12px;
            background: #fff;
            padding: 14px;
            height: 100%;
        }
        .eq-platform-card h6 {
            font-size: 1rem;
            margin-bottom: 7px;
        }
        .eq-platform-card p {
            color: #7c84a4;
            font-size: 0.83rem;
            min-height: 50px;
        }
        .eq-platform-card a {
            text-decoration: none;
            color: #4267e8;
            font-size: 0.82rem;
            font-weight: 700;
        }
        .eq-home-gradient-zone {
            background: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.12), transparent 35%),
                        linear-gradient(135deg, #346dfd 0%, #6f40ff 48%, #bd2fe5 100%);
        }
        .eq-section-title.light h2,
        .eq-section-title.light p {
            color: #fff;
        }
        .eq-section-title.light p {
            color: rgba(255,255,255,0.8);
        }
        .eq-stat-grid,
        .eq-recognition-grid {
            display: grid;
            gap: 14px;
        }
        .eq-stat-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .eq-recognition-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top: 14px; }
        .eq-glass-card {
            border: 1px solid rgba(255,255,255,0.18);
            background: rgba(255,255,255,0.09);
            color: #fff;
            border-radius: 14px;
            padding: 16px;
            text-align: center;
        }
        .eq-glass-card strong {
            display: block;
            font-size: 2rem;
            font-family: 'Outfit', sans-serif;
            line-height: 1.05;
        }
        .eq-glass-card span {
            display: block;
            font-weight: 700;
            margin-top: 4px;
        }
        .eq-glass-card small {
            display: block;
            margin-top: 3px;
            color: rgba(255,255,255,0.78);
        }
        .eq-gradient-cta {
            margin: 28px auto 0;
            max-width: 640px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.18);
            background: rgba(255,255,255,0.11);
            padding: 20px;
            text-align: center;
            color: #fff;
        }
        .eq-gradient-cta h3 {
            margin-bottom: 6px;
            color: #fff;
        }
        .eq-gradient-cta p {
            color: rgba(255,255,255,0.83);
            margin-bottom: 14px;
        }
        .eq-gradient-cta .btn { margin: 2px; }
        .eq-testimonial-card {
            border: 1px solid rgba(67, 84, 149, 0.13);
            background: #fff;
            border-radius: 14px;
            height: 100%;
            padding: 16px;
            box-shadow: 0 10px 20px rgba(26, 39, 88, 0.06);
        }
        .eq-stars {
            letter-spacing: 2px;
            color: #f4c34d;
            margin-bottom: 8px;
        }
        .eq-testimonial-card p {
            margin-bottom: 10px;
            color: #5f6788;
            font-size: 0.9rem;
            min-height: 86px;
        }
        .eq-tag-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 12px;
        }
        .eq-tag-row span {
            font-size: 0.72rem;
            border-radius: 999px;
            background: #edf2ff;
            color: #4d68aa;
            padding: 4px 8px;
        }
        .eq-person-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .eq-avatar {
            width: 33px;
            height: 33px;
            border-radius: 999px;
            background: linear-gradient(135deg, #3f73ff, #6f41ff);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
        }
        .eq-person-row strong {
            display: block;
            font-size: 0.88rem;
            line-height: 1.15;
        }
        .eq-person-row small {
            display: block;
            color: #8088a8;
            font-size: 0.72rem;
        }
        .eq-review-cta {
            margin: 26px auto 0;
            max-width: 460px;
            border: 1px solid rgba(67, 84, 149, 0.12);
            border-radius: 12px;
            background: #fff;
            text-align: center;
            padding: 18px;
        }
        .eq-review-cta .btn { margin: 3px; }
        .eq-last-gradient .eq-home-section {
            padding-bottom: 40px;
        }
        .eq-gradient-checks {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            color: rgba(255,255,255,0.86);
            font-size: 0.85rem;
        }
        .eq-gradient-checks li {
            position: relative;
            padding-left: 18px;
        }
        .eq-gradient-checks li::before {
            content: "✓";
            position: absolute;
            left: 0;
            top: -1px;
            color: #73e18e;
            font-weight: 700;
        }
        .eq-progress-panel {
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.16);
            background: rgba(255,255,255,0.1);
            padding: 34px 20px;
        }
        .eq-progress-track {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: rgba(255,255,255,0.22);
            overflow: hidden;
        }
        .eq-progress-track span {
            display: block;
            width: 75%;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #ffcb42, #ff9c3d);
        }
        footer.eq-site-footer {
            background: #0f1a3a !important;
            border-top: 1px solid rgba(255,255,255,0.05) !important;
            padding: 44px 0 20px;
            color: #fff;
        }
        .eq-site-footer .eq-brand-logo {
            filter: none;
            max-width: 180px;
            height: auto;
        }
        .eq-site-footer h6 {
            margin-bottom: 10px;
            color: #fff;
            font-size: 0.92rem;
        }
        .eq-site-footer a {
            display: block;
            color: rgba(255,255,255,0.72);
            text-decoration: none;
            margin-bottom: 6px;
            font-size: 0.84rem;
        }
        .eq-site-footer a:hover { color: #fff; }
        .eq-footer-meta {
            color: rgba(255,255,255,0.68);
            font-size: 0.8rem;
            margin-bottom: 5px;
        }
        .eq-socials {
            display: flex;
            gap: 8px;
            margin-top: 5px;
        }
        .eq-socials span {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.1);
            font-size: 0.75rem;
            color: #fff;
        }
        .eq-footer-feature-row {
            margin-top: 24px;
            border-top: 1px solid rgba(255,255,255,0.08);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            padding: 14px 0;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            color: rgba(255,255,255,0.86);
            font-size: 0.84rem;
        }
        .eq-footer-bottom {
            padding-top: 12px;
            font-size: 0.74rem;
            color: rgba(255,255,255,0.6);
            display: flex;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }
        .eq-fallback {
            margin-top: 8px;
        }
        .eq-fallback-hero {
            border-radius: 16px;
            padding: 20px;
            color: #fff;
            background: radial-gradient(circle at 20% 10%, rgba(255,255,255,0.14), transparent 38%),
                        linear-gradient(135deg, #346dfd 0%, #6f40ff 48%, #bd2ee5 100%);
            border: 1px solid rgba(255,255,255,0.16);
            box-shadow: 0 18px 34px rgba(56, 42, 150, 0.2);
        }
        .eq-fallback-eyebrow {
            display: inline-block;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.1);
            padding: 5px 10px;
            font-size: 0.74rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .eq-fallback-hero h3 {
            margin: 0 0 8px;
            color: #fff;
            font-size: clamp(1.3rem, 2.5vw, 1.85rem);
        }
        .eq-fallback-hero p {
            margin: 0;
            max-width: 760px;
            color: rgba(255,255,255,0.84);
        }
        .eq-fallback-points {
            margin-top: 12px;
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .eq-fallback-points div {
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.16);
            background: rgba(255,255,255,0.08);
            padding: 8px 10px;
            font-size: 0.82rem;
            color: rgba(255,255,255,0.87);
        }
        .eq-fallback-actions {
            margin-top: 14px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .eq-fallback-grid {
            margin-top: 12px;
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .eq-fallback-card {
            border-radius: 14px;
            border: 1px solid rgba(62, 81, 154, 0.12);
            background: #fff;
            padding: 14px;
        }
        .eq-fallback-card h6 {
            margin: 0 0 4px;
            font-size: 1rem;
        }
        .eq-fallback-meta {
            color: #6d7598;
            font-size: 0.77rem;
            margin-bottom: 7px;
        }
        .eq-fallback-card p {
            margin: 0 0 7px;
            color: #616a8f;
            font-size: 0.86rem;
        }
        .eq-fallback-card a {
            text-decoration: none;
            font-weight: 700;
            font-size: 0.82rem;
        }

        @media (max-width: 991px) {
            .hero {
                border-radius: 0 0 18px 18px;
                padding: 52px 0 46px;
            }
            .eq-trust-strip {
                grid-template-columns: 1fr;
                max-width: 100%;
            }
            .eq-dashboard-mock {
                margin-top: 10px;
            }
            .eq-brand-logo {
                height: 28px;
                max-width: 150px;
            }
            .eq-home-hero {
                padding: 50px 16px;
            }
            .eq-home-hero-grid {
                grid-template-columns: 1fr;
            }
            .eq-stat-grid,
            .eq-recognition-grid,
            .eq-footer-feature-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .eq-gradient-checks {
                grid-template-columns: 1fr;
            }
            .eq-fallback-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 480px) {
            .eq-brand-logo {
                display: block;
                height: 26px;
                max-width: 150px;
            }
            .eq-brand-mark {
                display: none;
            }
            .eq-brand-text {
                display: none;
                font-size: 1rem;
            }
            .eq-home-hero-metrics,
            .eq-stat-grid,
            .eq-recognition-grid,
            .eq-footer-feature-row {
                grid-template-columns: 1fr;
            }
            .eq-fallback-points,
            .eq-fallback-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            * { scroll-behavior: auto !important; }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="<?php echo htmlspecialchars(url_for('index.php')); ?>">
            <img src="<?php echo htmlspecialchars(url_for('assets/img/eduquestiq-logo-wide.png')); ?>" alt="EduquestIQ" class="eq-brand-logo">
            <img src="<?php echo htmlspecialchars(url_for('assets/img/eduquestiq-logo-icon.png')); ?>" alt="EduquestIQ" class="eq-brand-mark">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(url_for('index.php')); ?>">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(url_for('courses.php')); ?>">Courses</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(url_for('tests.php')); ?>">Tests</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(url_for('learning_paths.php')); ?>">Learning Paths</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(url_for('community.php')); ?>">Community</a></li>
                <?php if ($authUser && in_array($authUser['role'], ['teacher', 'school_admin'], true)): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(url_for('backend.php')); ?>">Backend</a></li>
                <?php endif; ?>
                <?php if ($authUser && in_array($authUser['role'], ['parent', 'school_admin'], true)): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(url_for('parent_children.php')); ?>">Parent Links</a></li>
                <?php endif; ?>
                <?php if ($authUser && in_array($authUser['role'], ['teacher', 'parent', 'school_admin'], true)): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(url_for('teacher_feedback.php')); ?>">Feedback</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(url_for('articles.php')); ?>">Articles</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(url_for('video_lectures.php')); ?>">Video Lectures</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(url_for('materials.php')); ?>">Study Materials</a></li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if ($authUser): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(url_for('dashboard.php')); ?>">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(url_for('logout.php')); ?>">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(url_for('login.php')); ?>">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(url_for('register.php')); ?>">Register</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(url_for('about.php')); ?>">About</a></li>
            </ul>
        </div>
    </div>
</nav>
<main class="py-4">
    <div class="container eq-page-shell">
