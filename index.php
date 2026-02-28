<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';

$pdo = get_pdo();

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

        <div class="eq-hero-panel">
            <div class="eq-hero-panel-head">
                <span>EduquestIQ Dashboard</span>
                <span class="eq-panel-badge">◎</span>
            </div>
            <div class="eq-hero-panel-ring">75%<small>Complete</small></div>
            <div class="eq-hero-panel-grid">
                <div><small>Academic</small><strong>85%</strong></div>
                <div><small>Creative</small><strong>70%</strong></div>
                <div><small>Leadership</small><strong>60%</strong></div>
                <div><small>Technical</small><strong>90%</strong></div>
            </div>
            <div class="eq-hero-panel-activity">
                <h6>Recent Activity</h6>
                <p>Completed Math Challenge <span>2h ago</span></p>
                <p>Watched Creative Video <span>5h ago</span></p>
                <p>Earned Leadership Badge <span>1d ago</span></p>
            </div>
        </div>
    </div>
</section>

<section class="eq-home-section">
    <div class="eq-section-title">
        <h2>Holistic Skills Development</h2>
        <p>Our comprehensive platform covers four essential skill domains, ensuring holistic development for students aged 6-20.</p>
    </div>

    <div class="row g-3">
        <div class="col-md-6 col-xl-3">
            <article class="eq-skill-card academic">
                <div class="eq-skill-icon">📘</div>
                <h5>Academic Intelligence (AIQ)</h5>
                <p>Master core subjects with interactive lessons, practice tests, and personalized learning paths.</p>
                <ul>
                    <li>Mathematics</li>
                    <li>Science</li>
                    <li>English</li>
                    <li>General Knowledge</li>
                    <li>Computer Science + AI Basics</li>
                </ul>
            </article>
        </div>
        <div class="col-md-6 col-xl-3">
            <article class="eq-skill-card creative">
                <div class="eq-skill-icon">🎨</div>
                <h5>Logical & Analytical Reasoning (LAQ)</h5>
                <p>Strengthen your analytical skills through logical reasoning, structured thinking, and data-driven problem solving</p>
                <ul>
                    <li>Number patterns</li>
                    <li>Visual reasoning</li>
                    <li>Syllogisms</li>
                    <li>Cause & effect</li>
                    <li>Spatial reasoning</li>
                </ul>
            </article>
        </div>
        <div class="col-md-6 col-xl-3">
            <article class="eq-skill-card leadership">
                <div class="eq-skill-icon">🧩</div>
                <h5>Language Proficiency (LPI)</h5>
                <p>Build essential interpersonal qualities through teamwork, communication, and collaborative projects.</p>
                <ul>
                    <li>Vocabulary (synonyms, antonyms, word usage)</li>
                    <li>Phonetics (sound–letter mapping)</li>
                    <li>Homophones & homonyms</li>
                    <li>Word formation</li>
                    <li>Context-based word selection</li>
                </ul>
            </article>
        </div>
        <div class="col-md-6 col-xl-3">
            <article class="eq-skill-card technical">
                <div class="eq-skill-icon">⚡</div>
                <h5>Cognitive Ability & Learning Style (CAL)</h5>
                <p>Learn edge technical skills including coding, robotics, and digital literacy.</p>
                <ul>
                    <li>Memory (visual / auditory recall)</li>
                    <li>Attention span</li>
                    <li>Processing speed</li>
                    <li>Pattern recognition speed</li>
                </ul>
            </article>
        </div>
        <div class="col-md-6 col-xl-3">
            <article class="eq-skill-card leadership">
                <div class="eq-skill-icon">🧩</div>
                <h5>Emotional & Social Intelligence (ESQ)</h5>
                <p>Build essential interpersonal qualities through teamwork, communication, and collaborative projects.</p>
                <ul>
                    <li>Emotion recognition</li>
                    <li>Empathy</li>
                    <li>Decision-making in social situations</li>
                    <li>Conflict resolution </li>
                    <</ul>
            </article>
        </div>
        <div class="col-md-6 col-xl-3">
            <article class="eq-skill-card leadership">
                <div class="eq-skill-icon">🧩</div>
                <h5>21st Century Skills Index (21SI))</h5>
                <p>Build essential interpersonal qualities through teamwork, communication, and collaborative projects.</p>
                <ul>
                    <li>Critical thinking</li>
                    <li>Collaboration (scenario-based)</li>
                    <li>Communication choices</li>
                    <li>Digital awareness</li>
                
                </ul>
            </article>
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
