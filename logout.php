<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';

logout_user();

header('Location: index.php');
exit;

