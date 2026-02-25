<?php
// EduquestIQ - Shared HTML header (include at top of pages)

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes_auth.php';

$authUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EduquestIQ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
            width: 28px;
            height: 28px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 6px 14px rgba(59, 73, 153, 0.18);
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
        }

        @media (max-width: 480px) {
            .eq-brand-logo {
                display: none;
            }
            .eq-brand-mark {
                display: block;
            }
            .eq-brand-text {
                display: inline;
                font-size: 1rem;
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
            <span class="eq-brand-text">EduquestIQ</span>
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
