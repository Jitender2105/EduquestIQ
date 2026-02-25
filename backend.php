<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';

require_auth(['teacher', 'school_admin']);
header('Location: ' . url_for('manage_lms.php'));
exit;

