<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
?>

<section class="hero">
    <div class="row align-items-center g-4 position-relative">
        <div class="col-lg-6">
            <div class="eq-hero-copy">
                <div class="eq-chip">Trusted by modern education teams</div>
                <h1>
                    Master Skills Beyond
                    <span class="accent">Traditional Learning</span>
                </h1>
                <p>
                    Join the EduquestIQ platform where academic excellence meets creative innovation,
                    leadership growth, and technical mastery. Built for students, parents, teachers, and schools.
                </p>

                <div class="eq-hero-actions">
                    <a href="<?php echo htmlspecialchars(url_for('register.php')); ?>" class="btn btn-primary btn-lg">
                        Start Your Journey
                    </a>
                    <a href="<?php echo htmlspecialchars(url_for('login.php')); ?>" class="btn btn-outline-light btn-lg">
                        Watch Demo
                    </a>
                </div>

                <div class="eq-trust-strip">
                    <div class="eq-metric">
                        <strong>50K+</strong>
                        <span>Active learners</span>
                    </div>
                    <div class="eq-metric">
                        <strong>500+</strong>
                        <span>Schools & teachers</span>
                    </div>
                    <div class="eq-metric">
                        <strong>95%</strong>
                        <span>Skill improvement rate</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="eq-dashboard-mock mx-lg-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="small fw-semibold opacity-75">EduquestIQ Dashboard</div>
                    <div class="rounded-circle bg-warning" style="width: 22px; height: 22px;"></div>
                </div>

                <div class="text-center">
                    <div class="eq-ring">75%</div>
                </div>

                <div class="eq-mini-grid mb-3">
                    <div class="eq-subtle text-center">
                        <div class="small opacity-75">Academic</div>
                        <div class="fw-bold">88%</div>
                    </div>
                    <div class="eq-subtle text-center">
                        <div class="small opacity-75">Creative</div>
                        <div class="fw-bold">76%</div>
                    </div>
                    <div class="eq-subtle text-center">
                        <div class="small opacity-75">Leadership</div>
                        <div class="fw-bold">72%</div>
                    </div>
                    <div class="eq-subtle text-center">
                        <div class="small opacity-75">Technical</div>
                        <div class="fw-bold">84%</div>
                    </div>
                </div>

                <div class="eq-subtle">
                    <div class="small fw-semibold mb-2 opacity-75">Recent Activity</div>
                    <div class="d-flex justify-content-between small mb-1"><span>Assessment Skill Update</span><span class="opacity-75">+8%</span></div>
                    <div class="d-flex justify-content-between small mb-1"><span>Course Completion Score</span><span class="opacity-75">+12%</span></div>
                    <div class="d-flex justify-content-between small"><span>Community Learning Streak</span><span class="opacity-75">+5</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="eq-section">
    <div class="eq-section-title">
        <h2>Holistic Skills Development</h2>
        <p>
            Our comprehensive platform tracks essential skill domains across academics, creativity,
            leadership, and technical growth for learners aged 6-20.
        </p>
    </div>

    <div class="row g-3">
        <div class="col-md-6 col-xl-3">
            <div class="eq-feature-card" style="background: linear-gradient(180deg,#f4f8ff,#ffffff);">
                <div class="eq-feature-icon" style="background:#e7efff;color:#3866ff;">A</div>
                <h5 class="mb-2">Academic Excellence</h5>
                <p class="small eq-muted">Measure core subject mastery through mapped tests, assignments, and learning paths.</p>
                <ul class="eq-list-clean">
                    <li>Mathematics</li>
                    <li>Science</li>
                    <li>Language</li>
                    <li>Social Studies</li>
                </ul>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="eq-feature-card" style="background: linear-gradient(180deg,#faf4ff,#ffffff);">
                <div class="eq-feature-icon" style="background:#f0e2ff;color:#9148ff;">C</div>
                <h5 class="mb-2">Creative Development</h5>
                <p class="small eq-muted">Unlock artistic potential with project-based learning and skill tracking.</p>
                <ul class="eq-list-clean">
                    <li>Visual Arts</li>
                    <li>Music Theory</li>
                    <li>Storytelling</li>
                    <li>Innovation</li>
                </ul>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="eq-feature-card" style="background: linear-gradient(180deg,#f2fff6,#ffffff);">
                <div class="eq-feature-icon" style="background:#dff8e6;color:#28a65b;">L</div>
                <h5 class="mb-2">Leadership Skills</h5>
                <p class="small eq-muted">Build teamwork, communication, and confidence through community learning.</p>
                <ul class="eq-list-clean">
                    <li>Communication</li>
                    <li>Teamwork</li>
                    <li>Problem Solving</li>
                    <li>Initiative</li>
                </ul>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="eq-feature-card" style="background: linear-gradient(180deg,#fff8f1,#ffffff);">
                <div class="eq-feature-icon" style="background:#ffe9d3;color:#ff8a2d;">T</div>
                <h5 class="mb-2">Technical Mastery</h5>
                <p class="small eq-muted">Track coding, digital fluency, and tool usage through practical tasks.</p>
                <ul class="eq-list-clean">
                    <li>Programming</li>
                    <li>Robotics</li>
                    <li>Digital Skills</li>
                    <li>Data Analysis</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="eq-section pt-2">
    <div class="eq-section-title">
        <h2>Powerful Learning Platform</h2>
        <p>Everything students, teachers, parents, and admins need in one integrated system.</p>
    </div>

    <div class="row g-3">
        <?php
        $items = [
            ['title' => 'Video Lectures', 'desc' => 'Structured lessons with progress marking and course-level tracking.', 'icon' => 'VL', 'link' => 'video_lectures.php'],
            ['title' => 'Study Materials', 'desc' => 'PDFs, docs, presentations, and links validated and organized by course.', 'icon' => 'SM', 'link' => 'materials.php'],
            ['title' => 'Progress Tracking', 'desc' => 'Track completion across videos/materials and update skill scores automatically.', 'icon' => 'PT', 'link' => 'dashboard.php'],
            ['title' => 'Achievement System', 'desc' => 'Unlock badges for scores, completion milestones, and community activity.', 'icon' => 'AS', 'link' => 'dashboard.php'],
            ['title' => 'Flexible Learning', 'desc' => 'Follow guided paths or learn self-paced and resume anytime.', 'icon' => 'FL', 'link' => 'learning_paths.php'],
            ['title' => 'Community Learning', 'desc' => 'Peer discussions, comments, likes, and collaborative growth.', 'icon' => 'CL', 'link' => 'community.php'],
        ];
        foreach ($items as $item):
        ?>
            <div class="col-md-6 col-xl-4">
                <div class="eq-feature-card">
                    <div class="eq-feature-icon" style="background:#edf2ff;color:#4a5fff;"><?php echo htmlspecialchars($item['icon']); ?></div>
                    <h6 class="mb-2"><?php echo htmlspecialchars($item['title']); ?></h6>
                    <p class="small eq-muted mb-3"><?php echo htmlspecialchars($item['desc']); ?></p>
                    <a class="small fw-semibold text-decoration-none" href="<?php echo htmlspecialchars(url_for($item['link'])); ?>">
                        Learn more
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="eq-section pt-2">
    <div class="eq-section-title">
        <h2>Role-Based Dashboards That Adapt in Real Time</h2>
        <p>
            Every user sees a purpose-built dashboard powered by AJAX, Chart.js, and role-based access control.
        </p>
    </div>

    <div class="row g-3">
        <div class="col-md-6 col-xl-3">
            <div class="eq-feature-card">
                <div class="eq-feature-icon" style="background:#e8efff;color:#3e6fff;">S</div>
                <h6>Student Dashboard</h6>
                <p class="small eq-muted mb-2">Skill radar chart, progress chart, active courses, upcoming tests, achievements, and community feed.</p>
                <a href="<?php echo htmlspecialchars(url_for('dashboard.php')); ?>" class="small fw-semibold text-decoration-none">Open dashboard</a>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="eq-feature-card">
                <div class="eq-feature-icon" style="background:#f1e8ff;color:#8d46ff;">P</div>
                <h6>Parent Dashboard</h6>
                <p class="small eq-muted mb-2">Child skill trend, performance summary, attendance, teacher feedback, and achievement visibility.</p>
                <a href="<?php echo htmlspecialchars(url_for('parent_children.php')); ?>" class="small fw-semibold text-decoration-none">Link child account</a>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="eq-feature-card">
                <div class="eq-feature-icon" style="background:#e7fbef;color:#20a45c;">T</div>
                <h6>Teacher Dashboard</h6>
                <p class="small eq-muted mb-2">Class performance analytics, course completion stats, test analysis, and student ranking insights.</p>
                <a href="<?php echo htmlspecialchars(url_for('manage_lms.php')); ?>" class="small fw-semibold text-decoration-none">Manage LMS data</a>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="eq-feature-card">
                <div class="eq-feature-icon" style="background:#fff0df;color:#ff8a2d;">A</div>
                <h6>Admin Dashboard</h6>
                <p class="small eq-muted mb-2">User metrics, course stats, engagement signals, and skill distribution across the platform.</p>
                <a href="<?php echo htmlspecialchars(url_for('manage_lms.php')); ?>" class="small fw-semibold text-decoration-none">Admin controls</a>
            </div>
        </div>
    </div>
</section>

<section class="eq-section pt-2">
    <div class="eq-section-title">
        <h2>Why EduquestIQ Stands Out</h2>
        <p>
            Built for production on shared hosting while still delivering dynamic, modern LMS experiences.
        </p>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="eq-feature-card">
                <h6 class="mb-2">100% Hostinger Compatible</h6>
                <p class="small eq-muted mb-0">PHP 8.1+, MySQL 8+, Apache `.htaccess`, no Node.js, no build process, and direct `public_html` deployment.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="eq-feature-card">
                <h6 class="mb-2">Secure by Default</h6>
                <p class="small eq-muted mb-0">JWT auth, prepared statements, CSRF protection, rate-limited login, role validation, and file upload checks.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="eq-feature-card">
                <h6 class="mb-2">Data-Driven Skill Growth</h6>
                <p class="small eq-muted mb-0">Every test question can impact multiple sub-attributes using weighted mappings for meaningful progress analytics.</p>
            </div>
        </div>
    </div>
</section>

<section class="eq-glow-band">
    <div class="text-center">
        <h3 class="mb-2">Trusted by Students Worldwide</h3>
        <p class="mb-4" style="color: rgba(255,255,255,0.82);">
            Dynamic dashboards, mapped assessments, and role-based insights designed for real learning outcomes.
        </p>
        <div class="row g-3 justify-content-center mb-4">
            <div class="col-md-3 col-6">
                <div class="eq-soft-card p-3">
                    <div class="small opacity-75">Skill Mappings</div>
                    <div class="fs-5 fw-bold">Multi-Attribute</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="eq-soft-card p-3">
                    <div class="small opacity-75">Dashboard Updates</div>
                    <div class="fs-5 fw-bold">AJAX + Live Data</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="eq-soft-card p-3">
                    <div class="small opacity-75">Learning Paths</div>
                    <div class="fs-5 fw-bold">Structured + Flexible</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="eq-soft-card p-3">
                    <div class="small opacity-75">Community Learning</div>
                    <div class="fs-5 fw-bold">Posts & Feedback</div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <a href="<?php echo htmlspecialchars(url_for('register.php')); ?>" class="btn btn-light btn-sm px-4">Create Account</a>
            <a href="<?php echo htmlspecialchars(url_for('courses.php')); ?>" class="btn btn-outline-light btn-sm px-4">Explore Courses</a>
            <a href="<?php echo htmlspecialchars(url_for('dashboard.php')); ?>" class="btn btn-outline-light btn-sm px-4">Open Dashboard</a>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes_footer.php';
