<?php
// EduquestIQ - Shared HTML footer
?>
    </div>
</main>
<?php if (!empty($eqCustomHomeFooter)): ?>
<footer class="eq-site-footer mt-0">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <a class="navbar-brand text-white mb-3" href="<?php echo htmlspecialchars(url_for('index.php')); ?>">
                    <img src="<?php echo htmlspecialchars(url_for('assets/img/eduquestiq-logo-wide.png')); ?>" alt="EduquestIQ" class="eq-brand-logo">
                </a>
                <p class="small text-white-50 mb-3">Empowering students aged 6-20 with comprehensive skill development across academic, creative, leadership, and technical domains.</p>
                <div class="eq-footer-meta">contact@eduquestiq.com</div>
                <div class="eq-footer-meta">+1 (555) 123-4567</div>
                <div class="eq-footer-meta">Global Online Platform</div>
            </div>
            <div class="col-6 col-lg-2">
                <h6>Platform</h6>
                <a href="<?php echo htmlspecialchars(url_for('dashboard.php')); ?>">Dashboard</a>
                <a href="<?php echo htmlspecialchars(url_for('courses.php')); ?>">Courses</a>
                <a href="<?php echo htmlspecialchars(url_for('video_lectures.php')); ?>">Videos</a>
                <a href="<?php echo htmlspecialchars(url_for('materials.php')); ?>">Resources</a>
                <a href="<?php echo htmlspecialchars(url_for('tests.php')); ?>">Leaderboard</a>
            </div>
            <div class="col-6 col-lg-2">
                <h6>Support</h6>
                <a href="<?php echo htmlspecialchars(url_for('about.php')); ?>">Help Center</a>
                <a href="<?php echo htmlspecialchars(url_for('about.php')); ?>">Contact Us</a>
                <a href="<?php echo htmlspecialchars(url_for('terms.php')); ?>">FAQ</a>
                <a href="<?php echo htmlspecialchars(url_for('community.php')); ?>">Community</a>
                <a href="<?php echo htmlspecialchars(url_for('about.php')); ?>">Bug Report</a>
            </div>
            <div class="col-6 col-lg-2">
                <h6>Legal</h6>
                <a href="<?php echo htmlspecialchars(url_for('privacy.php')); ?>">Privacy Policy</a>
                <a href="<?php echo htmlspecialchars(url_for('terms.php')); ?>">Terms of Service</a>
                <a href="<?php echo htmlspecialchars(url_for('privacy.php')); ?>">Cookie Policy</a>
                <a href="<?php echo htmlspecialchars(url_for('terms.php')); ?>">Refund Policy</a>
            </div>
            <div class="col-6 col-lg-2">
                <h6>Follow Us</h6>
                <div class="eq-socials">
                    <span>f</span><span>x</span><span>ig</span><span>in</span>
                </div>
            </div>
        </div>
        <div class="eq-footer-feature-row">
            <div>Academic Excellence</div>
            <div>Creative Skills</div>
            <div>Leadership Development</div>
            <div>Technical Mastery</div>
        </div>
        <div class="eq-footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> EduquestIQ. All rights reserved.</span>
            <span>Made with care for students worldwide</span>
            <span>All systems operational</span>
        </div>
    </div>
</footer>
<?php else: ?>
<footer class="border-top py-3 mt-4 bg-white">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <img src="<?php echo htmlspecialchars(url_for('assets/img/eduquestiq-logo-wide.png')); ?>" alt="EduquestIQ" style="width:102px;height:28px;object-fit:contain;">
            <div class="text-muted small">&copy; <?php echo date('Y'); ?> EduquestIQ · Skills-first learning platform</div>
        </div>
        <div>
            <a href="<?php echo htmlspecialchars(url_for('privacy.php')); ?>" class="me-3 small text-decoration-none">Privacy Policy</a>
            <a href="<?php echo htmlspecialchars(url_for('terms.php')); ?>" class="small text-decoration-none">Terms &amp; Conditions</a>
        </div>
    </div>
</footer>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
