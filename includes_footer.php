<?php
// EduquestIQ - Shared HTML footer
?>
    </div>
</main>
<footer class="border-top py-3 mt-4 bg-white">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <img src="<?php echo htmlspecialchars(url_for('assets/img/eduquestiq-logo-icon.png')); ?>" alt="EduquestIQ" style="width:28px;height:28px;border-radius:8px;object-fit:cover;">
            <div class="text-muted small">&copy; <?php echo date('Y'); ?> EduquestIQ · Skills-first learning platform</div>
        </div>
        <div>
            <a href="<?php echo htmlspecialchars(url_for('privacy.php')); ?>" class="me-3 small text-decoration-none">Privacy Policy</a>
            <a href="<?php echo htmlspecialchars(url_for('terms.php')); ?>" class="small text-decoration-none">Terms &amp; Conditions</a>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
