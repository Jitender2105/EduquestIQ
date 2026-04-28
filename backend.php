<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';

require_auth(['content_admin', 'super_admin']);
header('Location: ' . url_for('backend/index.php'));
exit;
