<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_csrf.php';

$pdo = get_pdo();
$leadErrors = [];
$leadSuccess = null;
$leadForm = [
    'student_name' => '',
    'class_name' => '',
    'school_name' => '',
    'parent_email' => '',
    'parent_mobile' => '',
    'exam' => [],
];
$examOptions = [
    'ASI' => 'Ace STEM Intelligence (ASI)',
    'ALP' => 'Ace Language Proficiency (ALP)',
    'ALA' => 'Ace Logical Ability (ALA)',
    'ALSGA' => 'Ace Life Skills and General Awareness (ALSGA)',
    'AEDI' => 'Ace Emotional & Digital Intelligence (AEDI)',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'submit_sira_lead') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $leadErrors[] = 'Invalid form token. Please refresh and try again.';
    }

    $leadForm['student_name'] = trim((string)($_POST['student_name'] ?? ''));
    $leadForm['class_name'] = trim((string)($_POST['class_name'] ?? ''));
    $leadForm['school_name'] = trim((string)($_POST['school_name'] ?? ''));
    $leadForm['parent_email'] = trim((string)($_POST['parent_email'] ?? ''));
    $leadForm['parent_mobile'] = trim((string)($_POST['parent_mobile'] ?? ''));
    $rawExams = $_POST['exam'] ?? [];
    $leadForm['exam'] = is_array($rawExams) ? array_values($rawExams) : [];

    if ($leadForm['student_name'] === '') {
        $leadErrors[] = 'Student name is required.';
    }
    if ($leadForm['class_name'] === '') {
        $leadErrors[] = 'Class is required.';
    }
    if ($leadForm['school_name'] === '') {
        $leadErrors[] = 'School name is required.';
    }
    if (!filter_var($leadForm['parent_email'], FILTER_VALIDATE_EMAIL)) {
        $leadErrors[] = 'Enter a valid parent email address.';
    }
    if (!preg_match('/^\+?[0-9 ]{10,15}$/', $leadForm['parent_mobile'])) {
        $leadErrors[] = 'Enter a valid parent mobile number.';
    }

    $selectedExamCodes = array_values(array_intersect(array_keys($examOptions), $leadForm['exam']));
    if ($selectedExamCodes === []) {
        $leadErrors[] = 'Select at least one exam.';
    }

    if ($leadErrors === []) {
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS sira_leads (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    lead_uid VARCHAR(32) NOT NULL UNIQUE,
                    student_name VARCHAR(120) NOT NULL,
                    class_name VARCHAR(40) NOT NULL,
                    school_name VARCHAR(180) NOT NULL,
                    parent_email VARCHAR(160) NOT NULL,
                    parent_mobile VARCHAR(20) NOT NULL,
                    selected_exams JSON NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            $leadUid = 'SIRA' . gmdate('YmdHis') . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $pdo->prepare(
                'INSERT INTO sira_leads
                 (lead_uid, student_name, class_name, school_name, parent_email, parent_mobile, selected_exams, created_at)
                 VALUES (:lead_uid, :student_name, :class_name, :school_name, :parent_email, :parent_mobile, :selected_exams, UTC_TIMESTAMP())'
            );
            $stmt->execute([
                ':lead_uid' => $leadUid,
                ':student_name' => $leadForm['student_name'],
                ':class_name' => $leadForm['class_name'],
                ':school_name' => $leadForm['school_name'],
                ':parent_email' => $leadForm['parent_email'],
                ':parent_mobile' => $leadForm['parent_mobile'],
                ':selected_exams' => json_encode($selectedExamCodes, JSON_THROW_ON_ERROR),
            ]);

            $leadSuccess = 'Thanks! Your assessment request has been received. Lead ID: ' . $leadUid;
            $leadForm = [
                'student_name' => '',
                'class_name' => '',
                'school_name' => '',
                'parent_email' => '',
                'parent_mobile' => '',
                'exam' => [],
            ];
        } catch (Throwable $e) {
            $leadErrors[] = 'Unable to submit right now. Please try again in a moment.';
        }
    }
}

$stats = [
    'students' => 50000,
    'courses' => 500,
    'success_rate' => 95,
    'lessons' => 120000,
    'countries' => 50,
    'badges' => 100,
];

try {
    $students = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    $courses = (int)$pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
    $stats['students'] = max($stats['students'], $students);
    $stats['courses'] = max($stats['courses'], $courses);
} catch (Throwable $e) {
    // Keep curated defaults if DB is empty/unavailable for optional homepage metrics.
}

$testimonials = [
    [
        'name' => 'Sarah Johnson',
        'grade' => 'Grade 12 Student',
        'city' => 'New York, USA',
        'text' => 'The balanced approach to academics and creativity helped me maintain excellent grades while exploring design.',
        'tags' => ['Academic Excellence', 'Creative Design', 'Leadership'],
    ],
    [
        'name' => 'Arjun Patel',
        'grade' => 'Grade 10 Student',
        'city' => 'Mumbai, India',
        'text' => 'Project-based programming and robotics kept me motivated. The progress tracking helped me stay consistent.',
        'tags' => ['Technical Skills', 'Programming', 'Problem Solving'],
    ],
    [
        'name' => 'Emma Chen',
        'grade' => 'Grade 8 Student',
        'city' => 'Toronto, Canada',
        'text' => 'Courses are engaging and community learning helped me collaborate with students from different regions.',
        'tags' => ['Creative Writing', 'Communication', 'Teamwork'],
    ],
    [
        'name' => 'Michael Rodriguez',
        'grade' => 'Grade 11 Student',
        'city' => 'Madrid, Spain',
        'text' => 'I now feel confident leading projects and communicating clearly with peers in collaborative assignments.',
        'tags' => ['Leadership', 'Communication', 'Academic Excellence'],
    ],
    [
        'name' => 'Priya Sharma',
        'grade' => 'Grade 9 Student',
        'city' => 'Delhi, India',
        'text' => 'Personalized learning paths adapt to my pace and interests, especially for creative and math-focused learning.',
        'tags' => ['Creative Arts', 'Mathematics', 'Innovation'],
    ],
    [
        'name' => 'David Kim',
        'grade' => 'Grade 7 Student',
        'city' => 'Seoul, South Korea',
        'text' => 'Quick reels and micro lessons give me short boosts of motivation and practical digital skills every day.',
        'tags' => ['Technical Skills', 'Digital Literacy', 'Leadership'],
    ],
];
?>

<style>
    .eq-home-lead-card {
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.26);
        border-radius: 18px;
        padding: 18px;
        box-shadow: 0 18px 36px rgba(29, 14, 102, 0.18);
    }
    .eq-home-lead-card h3 {
        color: #fff;
        font-size: 1.2rem;
        margin-bottom: 4px;
    }
    .eq-home-lead-card p {
        color: rgba(255, 255, 255, 0.82);
        font-size: 0.84rem;
        margin-bottom: 12px;
    }
    .eq-home-lead-card .form-label {
        color: rgba(255, 255, 255, 0.92);
        font-size: 0.78rem;
        margin-bottom: 4px;
        font-weight: 700;
    }
    .eq-home-lead-card .form-control,
    .eq-home-lead-card .form-select {
        background: rgba(255, 255, 255, 0.96);
        border-color: rgba(255, 255, 255, 0.9);
    }
    .eq-home-lead-card .btn {
        width: 100%;
    }
    .eq-home-multiselect {
        min-height: 112px;
    }
    .eq-sira-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        align-items: stretch;
    }
    .eq-sira-copy {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .eq-sira-copy article {
        border: 1px solid rgba(79, 96, 168, 0.12);
        border-radius: 14px;
        background: #fff;
        padding: 14px;
    }
    .eq-sira-visual-wrap {
        border-radius: 16px;
        background: #fff;
        border: 1px solid rgba(79, 96, 168, 0.12);
        overflow: hidden;
        box-shadow: 0 12px 26px rgba(30, 45, 102, 0.08);
        height: 100%;
    }
    .eq-sira-visual-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    @media (max-width: 991px) {
        .eq-sira-grid {
            grid-template-columns: 1fr;
        }
        .eq-sira-copy {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="eq-home-hero">
    <div class="eq-home-hero-grid">
        <div>
            <div class="eq-chip">Trusted by 10,000+ students worldwide</div>
            <h1>
                Master Skills Beyond
                <span class="accent">Traditional Learning</span>
            </h1>
            <p>
                Join the EduquestIQ platform where academic excellence meets creative innovation,
                leadership development, and technical mastery. Designed for students aged 6-20.
            </p>
            <div class="eq-home-hero-actions">
                <a href="<?php echo htmlspecialchars(url_for('register.php')); ?>" class="btn btn-light btn-lg">Start Your Journey</a>
                <a href="<?php echo htmlspecialchars(url_for('video_lectures.php')); ?>" class="btn btn-outline-light btn-lg">Watch Demo</a>
            </div>
            <div class="eq-home-hero-metrics">
                <div><strong><?php echo number_format($stats['students']); ?>+</strong><span>Students</span></div>
                <div><strong><?php echo number_format($stats['courses']); ?>+</strong><span>Courses</span></div>
                <div><strong><?php echo (int)$stats['success_rate']; ?>%</strong><span>Success Rate</span></div>
            </div>
        </div>
        <div class="eq-home-lead-card">
            <h3>Book Your SIRA Assessment</h3>
            <p>Fill this form and our team will connect with you quickly.</p>
            <?php if ($leadSuccess !== null): ?>
                <div class="alert alert-success py-2 px-3 small mb-3"><?php echo htmlspecialchars($leadSuccess); ?></div>
            <?php endif; ?>
            <?php if ($leadErrors !== []): ?>
                <div class="alert alert-danger py-2 px-3 small mb-3">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($leadErrors as $leadError): ?>
                            <li><?php echo htmlspecialchars($leadError); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="submit_sira_lead">
                <div class="mb-2">
                    <label class="form-label" for="lead-student-name">Student Name</label>
                    <input class="form-control form-control-sm" id="lead-student-name" name="student_name" maxlength="120" required value="<?php echo htmlspecialchars($leadForm['student_name']); ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="lead-class-name">Class</label>
                    <input class="form-control form-control-sm" id="lead-class-name" name="class_name" maxlength="40" required value="<?php echo htmlspecialchars($leadForm['class_name']); ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="lead-school-name">School Name</label>
                    <input class="form-control form-control-sm" id="lead-school-name" name="school_name" maxlength="180" required value="<?php echo htmlspecialchars($leadForm['school_name']); ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="lead-parent-email">Parent Email</label>
                    <input class="form-control form-control-sm" id="lead-parent-email" name="parent_email" type="email" maxlength="160" required value="<?php echo htmlspecialchars($leadForm['parent_email']); ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="lead-parent-mobile">Parent Mobile Number</label>
                    <input class="form-control form-control-sm" id="lead-parent-mobile" name="parent_mobile" maxlength="20" required value="<?php echo htmlspecialchars($leadForm['parent_mobile']); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="lead-exam">Exam (Multi Select)</label>
                    <select class="form-select form-select-sm eq-home-multiselect" id="lead-exam" name="exam[]" multiple required>
                        <?php foreach ($examOptions as $code => $label): ?>
                            <option value="<?php echo htmlspecialchars($code); ?>" <?php echo in_array($code, $leadForm['exam'], true) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-light btn-sm" type="submit">Submit Lead</button>
            </form>
        </div>
    </div>
</section>

<section class="eq-home-section">
    <div class="eq-section-title">
        <h2>Student Intelligence & Readiness Assessment (SIRA) 
        </h2>
        <p>Our comprehensive platform covers four essential skill domains, ensuring holistic development for students aged 6-20.</p>
    </div>

    <div class="eq-sira-grid">
        <div class="eq-sira-copy">
            <article class="eq-skill-card academic">
                <div class="eq-skill-icon">📘</div>
                <h5>Ace STEM Intelligence (ASI)</h5>
                <p>Strengthen analytical and problem-solving abilities through conceptual understanding.</p>
                <ul>
                    <li>Mathematics</li>
                    <li>Science (EVS)</li>
                </ul>
            </article>
            <article class="eq-skill-card creative">
                <div class="eq-skill-icon">📝</div>
                <h5>Ace Language Proficiency (ALP)</h5>
                <p>Empower confident expression through a rich blend of language skills.</p>
                <ul>
                    <li>English language skills</li>
                    <li>Vocabulary and comprehension</li>
                </ul>
            </article>
            <article class="eq-skill-card leadership">
                <div class="eq-skill-icon">🧠</div>
                <h5>Ace Logical Ability (ALA)</h5>
                <p>Enhances critical thinking and reasoning through structured problem-solving.</p>
                <ul>
                    <li>Logical reasoning</li>
                    <li>Analytical problem solving</li>
                </ul>
            </article>
            <article class="eq-skill-card technical">
                <div class="eq-skill-icon">🌍</div>
                <h5>Ace Life Skills and General Awareness (ALSGA)</h5>
                <p>Builds practical life-readiness with broad world awareness and knowledge.</p>
                <ul>
                    <li>Life skills</li>
                    <li>General knowledge and awareness</li>
                </ul>
            </article>
            <article class="eq-skill-card creative" style="grid-column: 1 / -1;">
                <div class="eq-skill-icon">💡</div>
                <h5>Ace Emotional & Digital Intelligence (AEDI)</h5>
                <p>Nurtures emotional awareness and safe, responsible engagement with the digital world and AI.</p>
                <ul>
                    <li>Emotional intelligence</li>
                    <li>Digital safety awareness</li>
                    <li>AI awareness</li>
                </ul>
            </article>
        </div>
        <div class="eq-sira-visual-wrap">
            <img src="<?php echo htmlspecialchars(url_for('assets/img/sira-assessment-visual.png')); ?>" alt="Student progress report preview for SIRA assessment">
        </div>
    </div>
</section>

<section class="eq-home-section eq-home-platform">
    <div class="eq-section-title">
        <h2>Powerful Learning Platform</h2>
        <p>Everything you need to succeed, all in one place.</p>
    </div>

    <div class="row g-3">
        <div class="col-md-6 col-xl-4"><div class="eq-platform-card"><h6>Video Lectures</h6><p>High-quality video content with interactive elements and progress tracking.</p><a href="<?php echo htmlspecialchars(url_for('video_lectures.php')); ?>">500+ Videos</a></div></div>
        <div class="col-md-6 col-xl-4"><div class="eq-platform-card"><h6>Study Materials</h6><p>Comprehensive resources including PDFs, guides, and reference documents.</p><a href="<?php echo htmlspecialchars(url_for('materials.php')); ?>">1000+ Resources</a></div></div>
        <div class="col-md-6 col-xl-4"><div class="eq-platform-card"><h6>Progress Tracking</h6><p>Real-time analytics and personalized insights for continuous improvement.</p><a href="<?php echo htmlspecialchars(url_for('dashboard.php')); ?>">95% Success Rate</a></div></div>
        <div class="col-md-6 col-xl-4"><div class="eq-platform-card"><h6>Achievement System</h6><p>Gamified learning with badges, certificates, and recognition programs.</p><a href="<?php echo htmlspecialchars(url_for('dashboard.php')); ?>">100+ Badges</a></div></div>
        <div class="col-md-6 col-xl-4"><div class="eq-platform-card"><h6>Flexible Learning</h6><p>Learn at your own pace with 24/7 access to all platform features.</p><a href="<?php echo htmlspecialchars(url_for('learning_paths.php')); ?>">24/7 Access</a></div></div>
        <div class="col-md-6 col-xl-4"><div class="eq-platform-card"><h6>Community Learning</h6><p>Connect with peers, ask questions, and share growth milestones.</p><a href="<?php echo htmlspecialchars(url_for('community.php')); ?>">Active Community</a></div></div>
    </div>
</section>

<section class="eq-home-gradient-zone">
    <div class="eq-home-section">
        <div class="eq-section-title light">
            <h2>Trusted by Students Worldwide</h2>
            <p>Join thousands of students who are already developing their skills and achieving their goals.</p>
        </div>

        <div class="eq-stat-grid">
            <div class="eq-glass-card"><strong><?php echo number_format($stats['students']); ?>+</strong><span>Active Students</span><small>Students from 50+ countries</small></div>
            <div class="eq-glass-card"><strong><?php echo number_format($stats['courses']); ?>+</strong><span>Courses Available</span><small>Across all skill domains</small></div>
            <div class="eq-glass-card"><strong><?php echo (int)$stats['success_rate']; ?>%</strong><span>Success Rate</span><small>Measured performance growth</small></div>
            <div class="eq-glass-card"><strong><?php echo number_format($stats['lessons']); ?>+</strong><span>Lessons Completed</span><small>Learning hours logged</small></div>
            <div class="eq-glass-card"><strong><?php echo (int)$stats['countries']; ?>+</strong><span>Countries</span><small>Global reach and impact</small></div>
            <div class="eq-glass-card"><strong>24/7</strong><span>Access</span><small>Learn anytime, anywhere</small></div>
        </div>

        <div class="eq-section-title light mt-5">
            <h2>Recognized Excellence</h2>
            <p>Our commitment to quality education has earned us recognition from leading organizations.</p>
        </div>

        <div class="eq-recognition-grid">
            <div class="eq-glass-card"><strong>2023</strong><span>Excellence in Education</span><small>Recognized for innovative learning approaches</small></div>
            <div class="eq-glass-card"><strong><?php echo (int)$stats['success_rate']; ?>%</strong><span>Skill Improvement</span><small>Students show measurable growth</small></div>
            <div class="eq-glass-card"><strong>50+</strong><span>Countries Reached</span><small>Global impact across regions</small></div>
        </div>

        <div class="eq-gradient-cta">
            <h3>Ready to Start Your Journey?</h3>
            <p>Join thousands of students who are already developing their skills and achieving their goals.</p>
            <div>
                <a href="<?php echo htmlspecialchars(url_for('register.php')); ?>" class="btn btn-light btn-sm px-4">Get Started Free</a>
                <a href="<?php echo htmlspecialchars(url_for('courses.php')); ?>" class="btn btn-outline-light btn-sm px-4">Learn More</a>
            </div>
        </div>
    </div>
</section>

<section class="eq-home-section">
    <div class="eq-section-title">
        <h2>What Students Say About EduquestIQ</h2>
        <p>Hear from students around the world who have transformed their learning journey with EduquestIQ.</p>
    </div>

    <div class="row g-3">
        <?php foreach ($testimonials as $i => $review): ?>
            <div class="col-md-6 col-xl-4">
                <article class="eq-testimonial-card">
                    <div class="eq-stars">★★★★★</div>
                    <p><?php echo htmlspecialchars($review['text']); ?></p>
                    <div class="eq-tag-row">
                        <?php foreach ($review['tags'] as $tag): ?>
                            <span><?php echo htmlspecialchars($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="eq-person-row">
                        <div class="eq-avatar"><?php echo htmlspecialchars(substr($review['name'], 0, 1)); ?></div>
                        <div>
                            <strong><?php echo htmlspecialchars($review['name']); ?></strong>
                            <small><?php echo htmlspecialchars($review['grade']); ?></small>
                            <small><?php echo htmlspecialchars($review['city']); ?></small>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="eq-review-cta">
        <a href="<?php echo htmlspecialchars(url_for('register.php')); ?>" class="btn btn-primary btn-sm px-4">Start Free Trial</a>
        <a href="<?php echo htmlspecialchars(url_for('community.php')); ?>" class="btn btn-outline-secondary btn-sm px-4">View All Reviews</a>
    </div>
</section>

<section class="eq-home-gradient-zone eq-last-gradient">
    <div class="eq-home-section">
        <div class="row g-4 align-items-center">
            <div class="col-lg-6 text-white">
                <p class="mb-3">Transform your learning experience with our comprehensive platform designed for students aged 6-20. Develop academic, creative, leadership, and technical skills all in one place.</p>
                <ul class="eq-gradient-checks">
                    <li>Access to 500+ courses across all domains</li>
                    <li>Personalized learning paths and progress tracking</li>
                    <li>Interactive video lectures and study materials</li>
                    <li>Community features and peer collaboration</li>
                </ul>
            </div>
            <div class="col-lg-6">
                <div class="eq-progress-panel">
                    <div class="d-flex justify-content-between small text-white-50 mb-2">
                        <span>Learning Progress</span>
                        <span>75% Complete</span>
                    </div>
                    <div class="eq-progress-track"><span></span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$eqCustomHomeFooter = true;
require_once __DIR__ . '/includes_footer.php';
