<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
?>

<div class="eq-page-head"><h2>Privacy Policy</h2><p class="subtitle">How EduquestIQ stores and protects account and learning data.</p></div>
<p class="text-muted">
    EduquestIQ stores only the data required to operate the learning platform: your account details, course
    enrollment data, test attempts, progress records, and activity within the community modules.
    Passwords are hashed using PHP's <code>password_hash()</code> and are never stored in plain text.
</p>
<p class="text-muted">
    Instance owners (schools or administrators) are responsible for configuring HTTPS and data retention policies
    on their hosting environment. This sample implementation is intended as a starting point and should be reviewed
    for your specific legal and compliance requirements.
</p>

<?php
require_once __DIR__ . '/includes_footer.php';
