<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
?>

<style>
    .eq-about-hero {
        background: radial-gradient(circle at 20% 10%, rgba(255,255,255,0.15), transparent 35%),
                    linear-gradient(135deg, #356eff 0%, #6a40ff 48%, #bd2ee5 100%);
        border-radius: 20px;
        color: #fff;
        padding: 42px 30px;
        margin-bottom: 24px;
        box-shadow: 0 20px 42px rgba(43, 28, 124, 0.2);
    }
    .eq-about-hero h1 {
        margin: 0 0 10px;
        font-size: clamp(1.9rem, 4vw, 3rem);
    }
    .eq-about-hero p {
        margin: 0;
        max-width: 760px;
        color: rgba(255,255,255,0.85);
    }
    .eq-about-chip-row {
        margin-top: 18px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .eq-about-chip {
        border: 1px solid rgba(255,255,255,0.2);
        background: rgba(255,255,255,0.1);
        border-radius: 999px;
        padding: 7px 12px;
        font-size: 0.82rem;
        font-weight: 700;
    }
    .eq-about-block {
        border: 1px solid rgba(65, 83, 154, 0.12);
        border-radius: 18px;
        background: #fff;
        padding: 22px;
        height: 100%;
        box-shadow: 0 10px 24px rgba(34, 51, 109, 0.05);
    }
    .eq-about-block h3,
    .eq-about-block h4 {
        margin-bottom: 10px;
    }
    .eq-about-muted {
        color: #6f7898;
        margin: 0;
    }
    .eq-what-grid {
        display: grid;
        gap: 14px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .eq-what-card {
        border: 1px solid rgba(65, 83, 154, 0.12);
        border-radius: 14px;
        background: #fff;
        padding: 16px;
    }
    .eq-what-card strong {
        display: block;
        margin-bottom: 6px;
        font-family: 'Outfit', sans-serif;
        font-size: 1.02rem;
    }
    .eq-what-card p {
        margin: 0;
        color: #6f7898;
        font-size: 0.9rem;
    }
    .eq-flow {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 10px;
    }
    .eq-flow li {
        border-radius: 12px;
        border: 1px solid rgba(65, 83, 154, 0.12);
        background: #f9fbff;
        padding: 12px;
        display: flex;
        gap: 10px;
        align-items: flex-start;
    }
    .eq-flow span {
        width: 24px;
        height: 24px;
        border-radius: 999px;
        background: linear-gradient(135deg, #3f73ff, #6f41ff);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.74rem;
        font-weight: 700;
        flex: 0 0 24px;
        margin-top: 1px;
    }
    .eq-outcomes {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .eq-outcome {
        border-radius: 14px;
        border: 1px solid rgba(65, 83, 154, 0.12);
        background: #fff;
        padding: 16px;
        text-align: center;
    }
    .eq-outcome strong {
        display: block;
        font-family: 'Outfit', sans-serif;
        font-size: 1.7rem;
        line-height: 1.05;
        color: #3349a8;
    }
    .eq-outcome span {
        display: block;
        margin-top: 4px;
        color: #5e678d;
        font-size: 0.9rem;
        font-weight: 600;
    }
    .eq-about-cta {
        margin-top: 18px;
        border-radius: 18px;
        padding: 20px;
        text-align: center;
        border: 1px solid rgba(86, 71, 191, 0.15);
        background: linear-gradient(135deg, rgba(62,111,255,0.12), rgba(179,53,255,0.1));
    }
    @media (max-width: 992px) {
        .eq-what-grid { grid-template-columns: 1fr 1fr; }
        .eq-outcomes { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 600px) {
        .eq-about-hero { padding: 30px 18px; }
        .eq-what-grid,
        .eq-outcomes { grid-template-columns: 1fr; }
    }
</style>

<section class="eq-about-hero">
    <h1>What We Do at EduquestIQ</h1>
    <p>
        We help schools, teachers, parents, and students run one integrated learning system where every test,
        course, video, and activity contributes to measurable skill growth.
    </p>
    <div class="eq-about-chip-row">
        <div class="eq-about-chip">Skills-first LMS</div>
        <div class="eq-about-chip">Dynamic dashboards</div>
        <div class="eq-about-chip">Hostinger-ready architecture</div>
        <div class="eq-about-chip">PHP + MySQL only</div>
    </div>
</section>

<section class="eq-section pt-1">
    <div class="row g-3">
        <div class="col-lg-6">
            <article class="eq-about-block">
                <h3>Our Mission</h3>
                <p class="eq-about-muted">
                    Replace fragmented tools with a single production LMS where learning outcomes are transparent,
                    trackable, and actionable for every role in the ecosystem.
                </p>
            </article>
        </div>
        <div class="col-lg-6">
            <article class="eq-about-block">
                <h3>Who We Serve</h3>
                <p class="eq-about-muted">
                    Students tracking growth, parents monitoring progress, teachers managing curriculum,
                    and school admins supervising performance and engagement metrics.
                </p>
            </article>
        </div>
    </div>
</section>

<section class="eq-section pt-1">
    <div class="eq-section-title text-start mb-3">
        <h2>Core Platform Capabilities</h2>
        <p>Each module is built to map learning activity directly to skill development and decision-ready insights.</p>
    </div>

    <div class="eq-what-grid">
        <article class="eq-what-card"><strong>Assessment Intelligence</strong><p>Questions are mapped to attributes and sub-attributes with weights for precise skill-level impact.</p></article>
        <article class="eq-what-card"><strong>Course Delivery</strong><p>Courses combine lectures, materials, and tests with structured or flexible learning path sequencing.</p></article>
        <article class="eq-what-card"><strong>Progress Engine</strong><p>Skill scores auto-update from test performance, completion events, and mapped learning activity.</p></article>
        <article class="eq-what-card"><strong>Achievement System</strong><p>Gamified milestones recognize consistency, completion, performance, and community participation.</p></article>
        <article class="eq-what-card"><strong>Community Learning</strong><p>Students collaborate through posts, comments, and likes to support peer-driven development.</p></article>
        <article class="eq-what-card"><strong>Role Dashboards</strong><p>Students, parents, teachers, and admins each get dynamic charts and metrics relevant to their goals.</p></article>
    </div>
</section>

<section class="eq-section pt-1">
    <div class="row g-3">
        <div class="col-lg-7">
            <article class="eq-about-block">
                <h4>How EduquestIQ Works</h4>
                <ul class="eq-flow">
                    <li><span>1</span><div><strong>Define skill framework</strong><br><small class="text-muted">Create attributes and sub-attributes for academic, creative, leadership, and technical domains.</small></div></li>
                    <li><span>2</span><div><strong>Map learning content</strong><br><small class="text-muted">Connect questions, tests, courses, videos, and materials to that framework.</small></div></li>
                    <li><span>3</span><div><strong>Capture learner activity</strong><br><small class="text-muted">Track enrollments, attempts, completions, achievements, and community interactions.</small></div></li>
                    <li><span>4</span><div><strong>Generate live insights</strong><br><small class="text-muted">Render role-based dashboards with progress trends, rankings, and outcome metrics.</small></div></li>
                </ul>
            </article>
        </div>
        <div class="col-lg-5">
            <article class="eq-about-block">
                <h4>Production-Ready by Design</h4>
                <p class="eq-about-muted mb-3">
                    Built for shared hosting realities while preserving modern product behavior.
                </p>
                <ul class="eq-flow">
                    <li><span>✓</span><div><small class="text-muted">No Node.js, no build process, no framework lock-in.</small></div></li>
                    <li><span>✓</span><div><small class="text-muted">Secure auth with JWT, role middleware, CSRF, and prepared statements.</small></div></li>
                    <li><span>✓</span><div><small class="text-muted">Hostinger-compatible deployment with SQL schema and Git-based release flow.</small></div></li>
                </ul>
            </article>
        </div>
    </div>
</section>

<section class="eq-section pt-1 pb-2">
    <div class="eq-section-title text-start mb-3">
        <h2>Impact We Focus On</h2>
        <p>We optimize for measurable outcomes, not vanity metrics.</p>
    </div>
    <div class="eq-outcomes">
        <div class="eq-outcome"><strong>95%</strong><span>Skill growth visibility</span></div>
        <div class="eq-outcome"><strong>500+</strong><span>Course capacity model</span></div>
        <div class="eq-outcome"><strong>50K+</strong><span>Learner-ready scale</span></div>
        <div class="eq-outcome"><strong>24/7</strong><span>Always-accessible learning</span></div>
    </div>

    <div class="eq-about-cta">
        <h4 class="mb-2">See EduquestIQ in action</h4>
        <p class="eq-about-muted mb-3">Explore the platform flows for courses, tests, learning paths, and dashboards.</p>
        <a href="<?php echo htmlspecialchars(url_for('courses.php')); ?>" class="btn btn-primary btn-sm px-4">Explore Courses</a>
        <a href="<?php echo htmlspecialchars(url_for('dashboard.php')); ?>" class="btn btn-outline-primary btn-sm px-4">Open Dashboard</a>
    </div>
</section>

<?php
require_once __DIR__ . '/includes_footer.php';
