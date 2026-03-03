<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_csrf.php';

$errors = [];
$success = false;
$pdo = get_pdo();
$schoolsTableReady = false;
$schools = [];

try {
    $schoolsTableReady = (bool)$pdo->query("SHOW TABLES LIKE 'schools'")->fetchColumn();
    if ($schoolsTableReady) {
        $schools = $pdo->query(
            'SELECT id, name, city, state
             FROM schools
             WHERE status = "active"
             ORDER BY name ASC'
        )->fetchAll();
    }
} catch (Throwable $e) {
    $schoolsTableReady = false;
    $schools = [];
}

$name = '';
$email = '';
$role = 'student';
$age = null;
$grade = '';
$schoolId = 0;
$agreeTerms = 0;

$parentPhone = '';
$parentRelation = '';
$childName = '';
$childGrade = '';

$teacherEmployeeId = '';
$teacherSubjects = '';
$teacherGradeLevels = '';
$teacherExperience = null;

$adminDesignation = '';
$adminWorkEmail = '';
$adminWorkPhone = '';
$adminIdCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $role = (string)($_POST['role'] ?? 'student');
    $ageRaw = trim((string)($_POST['age'] ?? ''));
    $age = $ageRaw === '' ? null : (int)$ageRaw;
    $grade = trim((string)($_POST['grade'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');
    $schoolId = isset($_POST['school_id']) && $_POST['school_id'] !== '' ? (int)$_POST['school_id'] : 0;
    $agreeTerms = isset($_POST['agree_terms']) ? 1 : 0;
    $csrf = $_POST['csrf_token'] ?? null;

    $parentPhone = trim((string)($_POST['parent_phone'] ?? ''));
    $parentRelation = trim((string)($_POST['parent_relation'] ?? ''));
    $childName = trim((string)($_POST['child_name'] ?? ''));
    $childGrade = trim((string)($_POST['child_grade'] ?? ''));

    $teacherEmployeeId = trim((string)($_POST['teacher_employee_id'] ?? ''));
    $teacherSubjects = trim((string)($_POST['teacher_subjects'] ?? ''));
    $teacherGradeLevels = trim((string)($_POST['teacher_grade_levels'] ?? ''));
    $teacherExperienceRaw = trim((string)($_POST['teacher_experience_years'] ?? ''));
    $teacherExperience = $teacherExperienceRaw === '' ? null : (int)$teacherExperienceRaw;

    $adminDesignation = trim((string)($_POST['admin_designation'] ?? ''));
    $adminWorkEmail = trim((string)($_POST['admin_work_email'] ?? ''));
    $adminWorkPhone = trim((string)($_POST['admin_work_phone'] ?? ''));
    $adminIdCode = trim((string)($_POST['admin_id_code'] ?? ''));

    $validGrades = ['Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
    $validRoles = ['student', 'parent', 'teacher', 'school_admin'];
    $validRelations = ['father', 'mother', 'guardian', 'other'];

    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Invalid CSRF token. Please refresh and try again.';
    }
    if (!$schoolsTableReady) {
        $errors[] = 'Registration setup is incomplete. Please run the latest database migration to create schools table.';
    }
    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (!in_array($role, $validRoles, true)) {
        $errors[] = 'Invalid role selected.';
    }
    if ($schoolId <= 0) {
        $errors[] = 'Please select a school from the list.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if ($agreeTerms !== 1) {
        $errors[] = 'You must agree to the Terms of Service and Privacy Policy.';
    }

    if ($role === 'student') {
        if ($age === null || $age < 4 || $age > 100) {
            $errors[] = 'Age must be a number between 4 and 100.';
        }
        if (!in_array($grade, $validGrades, true)) {
            $errors[] = 'Please select a valid grade.';
        }
    }

    if ($role === 'parent') {
        if ($parentPhone === '' || !preg_match('/^[0-9+\-\s()]{8,20}$/', $parentPhone)) {
            $errors[] = 'Enter a valid parent phone number.';
        }
        if (!in_array($parentRelation, $validRelations, true)) {
            $errors[] = 'Please select parent relation.';
        }
        if ($childName === '') {
            $errors[] = 'Child full name is required for parent registration.';
        }
        if (!in_array($childGrade, $validGrades, true)) {
            $errors[] = 'Please select child grade.';
        }
    }

    if ($role === 'teacher') {
        if ($teacherEmployeeId === '') {
            $errors[] = 'Teacher ID / Employee ID is required.';
        }
        if ($teacherSubjects === '') {
            $errors[] = 'Subject specialization is required.';
        }
        if ($teacherGradeLevels === '') {
            $errors[] = 'Grades taught is required.';
        }
        if ($teacherExperience === null || $teacherExperience < 0 || $teacherExperience > 60) {
            $errors[] = 'Enter valid teaching experience in years.';
        }
    }

    if ($role === 'school_admin') {
        if ($adminDesignation === '') {
            $errors[] = 'Designation is required for school admin.';
        }
        if (!filter_var($adminWorkEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid official work email is required.';
        }
        if ($adminWorkPhone === '' || !preg_match('/^[0-9+\-\s()]{8,20}$/', $adminWorkPhone)) {
            $errors[] = 'Enter a valid official work phone number.';
        }
        if ($adminIdCode === '') {
            $errors[] = 'Admin ID / Employee code is required.';
        }
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists.';
            } else {
                $stmt = $pdo->prepare('SELECT id FROM schools WHERE id = ? AND status = "active" LIMIT 1');
                $stmt->execute([$schoolId]);
                if (!$stmt->fetch()) {
                    $errors[] = 'Selected school was not found. Please choose from the list.';
                    throw new RuntimeException('School not found');
                }

                $roleProfile = null;
                if ($role === 'parent') {
                    $roleProfile = [
                        'parent_phone' => $parentPhone,
                        'relation_to_student' => $parentRelation,
                        'child_name' => $childName,
                        'child_grade' => $childGrade,
                    ];
                } elseif ($role === 'teacher') {
                    $roleProfile = [
                        'teacher_employee_id' => $teacherEmployeeId,
                        'teacher_subjects' => $teacherSubjects,
                        'teacher_grade_levels' => $teacherGradeLevels,
                        'teacher_experience_years' => $teacherExperience,
                    ];
                } elseif ($role === 'school_admin') {
                    $roleProfile = [
                        'admin_designation' => $adminDesignation,
                        'admin_work_email' => $adminWorkEmail,
                        'admin_work_phone' => $adminWorkPhone,
                        'admin_id_code' => $adminIdCode,
                    ];
                }

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    'INSERT INTO users (name, email, password, role, school_id, age, grade, role_profile, terms_accepted, terms_accepted_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())'
                );
                $stmt->execute([
                    $name,
                    $email,
                    $hash,
                    $role,
                    $schoolId,
                    $role === 'student' ? $age : null,
                    $role === 'student' ? $grade : null,
                    $roleProfile ? json_encode($roleProfile, JSON_UNESCAPED_UNICODE) : null,
                ]);
                $success = true;
            }
        } catch (Throwable $e) {
            if (!$errors) {
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <div class="eq-page-head">
            <h2>Create Your EduquestIQ Account</h2>
            <p class="subtitle">Role-based onboarding for students, parents, teachers, and school admins.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                Registration successful. You can now <a href="<?php echo htmlspecialchars(url_for('login.php')); ?>">log in</a>.
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$schoolsTableReady): ?>
            <div class="alert alert-warning">
                School master table is missing. Run migration file
                <code>migrations/2026-02-28_registration_school_upgrade.sql</code>
                and refresh this page.
            </div>
        <?php endif; ?>

        <form method="post" class="card p-3" id="register-form">
            <?php echo csrf_field(); ?>

            <div class="mb-3">
                <label class="form-label">Register as</label>
                <select name="role" id="role" class="form-select" required>
                    <option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="parent" <?php echo $role === 'parent' ? 'selected' : ''; ?>>Parent</option>
                    <option value="teacher" <?php echo $role === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                    <option value="school_admin" <?php echo $role === 'school_admin' ? 'selected' : ''; ?>>School Admin</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Enter your full name</label>
                <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($name); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Enter your email</label>
                <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($email); ?>">
            </div>

            <div class="mb-3" id="student-age-wrap">
                <label class="form-label">Age</label>
                <input type="number" name="age" id="age" class="form-control" min="4" max="100" value="<?php echo $age !== null ? (int)$age : ''; ?>">
            </div>

            <div class="mb-3" id="student-grade-wrap">
                <label class="form-label">Grade</label>
                <select name="grade" id="grade" class="form-select">
                    <option value="">Select grade</option>
                    <?php
                    $gradeOptions = ['Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
                    foreach ($gradeOptions as $gradeOption):
                    ?>
                        <option value="<?php echo htmlspecialchars($gradeOption); ?>" <?php echo $grade === $gradeOption ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($gradeOption); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="parent-fields" class="border rounded p-3 mb-3" style="display:none;">
                <h6>Parent Details</h6>
                <div class="mb-2">
                    <label class="form-label">Parent phone number</label>
                    <input type="text" name="parent_phone" id="parent_phone" class="form-control" value="<?php echo htmlspecialchars($parentPhone); ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label">Relation to student</label>
                    <select name="parent_relation" id="parent_relation" class="form-select">
                        <option value="">Select relation</option>
                        <option value="father" <?php echo $parentRelation === 'father' ? 'selected' : ''; ?>>Father</option>
                        <option value="mother" <?php echo $parentRelation === 'mother' ? 'selected' : ''; ?>>Mother</option>
                        <option value="guardian" <?php echo $parentRelation === 'guardian' ? 'selected' : ''; ?>>Guardian</option>
                        <option value="other" <?php echo $parentRelation === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Child full name</label>
                    <input type="text" name="child_name" id="child_name" class="form-control" value="<?php echo htmlspecialchars($childName); ?>">
                </div>
                <div>
                    <label class="form-label">Child grade</label>
                    <select name="child_grade" id="child_grade" class="form-select">
                        <option value="">Select grade</option>
                        <?php foreach ($gradeOptions as $gradeOption): ?>
                            <option value="<?php echo htmlspecialchars($gradeOption); ?>" <?php echo $childGrade === $gradeOption ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($gradeOption); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="teacher-fields" class="border rounded p-3 mb-3" style="display:none;">
                <h6>Teacher Details</h6>
                <div class="mb-2">
                    <label class="form-label">Teacher ID / Employee ID</label>
                    <input type="text" name="teacher_employee_id" id="teacher_employee_id" class="form-control" value="<?php echo htmlspecialchars($teacherEmployeeId); ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label">Subject specialization</label>
                    <input type="text" name="teacher_subjects" id="teacher_subjects" class="form-control" placeholder="e.g., Mathematics, Physics" value="<?php echo htmlspecialchars($teacherSubjects); ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label">Grades taught</label>
                    <input type="text" name="teacher_grade_levels" id="teacher_grade_levels" class="form-control" placeholder="e.g., Grade 8-10" value="<?php echo htmlspecialchars($teacherGradeLevels); ?>">
                </div>
                <div>
                    <label class="form-label">Teaching experience (years)</label>
                    <input type="number" min="0" max="60" name="teacher_experience_years" id="teacher_experience_years" class="form-control" value="<?php echo $teacherExperience !== null ? (int)$teacherExperience : ''; ?>">
                </div>
            </div>

            <div id="admin-fields" class="border rounded p-3 mb-3" style="display:none;">
                <h6>School Admin Details</h6>
                <div class="mb-2">
                    <label class="form-label">Designation</label>
                    <input type="text" name="admin_designation" id="admin_designation" class="form-control" placeholder="e.g., Principal / Coordinator" value="<?php echo htmlspecialchars($adminDesignation); ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label">Official work email</label>
                    <input type="email" name="admin_work_email" id="admin_work_email" class="form-control" value="<?php echo htmlspecialchars($adminWorkEmail); ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label">Official work phone</label>
                    <input type="text" name="admin_work_phone" id="admin_work_phone" class="form-control" value="<?php echo htmlspecialchars($adminWorkPhone); ?>">
                </div>
                <div>
                    <label class="form-label">Admin ID / Employee code</label>
                    <input type="text" name="admin_id_code" id="admin_id_code" class="form-control" value="<?php echo htmlspecialchars($adminIdCode); ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">School Name</label>
                <input
                    type="search"
                    name="school_name"
                    id="school_name"
                    class="form-control"
                    list="school_options"
                    placeholder="Search and select your school"
                    required
                    <?php echo !$schoolsTableReady ? 'disabled' : ''; ?>
                    value="<?php
                        if (!empty($schoolId)) {
                            foreach ($schools as $s) {
                                if ((int)$s['id'] === (int)$schoolId) {
                                    echo htmlspecialchars($s['name'] . (!empty($s['city']) ? ' - ' . $s['city'] : ''));
                                    break;
                                }
                            }
                        }
                    ?>"
                >
                <input type="hidden" name="school_id" id="school_id" value="<?php echo (int)$schoolId; ?>">
                <datalist id="school_options">
                    <?php foreach ($schools as $school): ?>
                        <option value="<?php echo htmlspecialchars($school['name'] . (!empty($school['city']) ? ' - ' . $school['city'] : '')); ?>" data-id="<?php echo (int)$school['id']; ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <div class="form-text">School list is managed from backend by school admins.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="form-text mb-1">Create a password</div>
                <input type="password" name="password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="password_confirm" class="form-control" required>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" value="1" name="agree_terms" id="agree_terms" <?php echo $agreeTerms ? 'checked' : ''; ?> required>
                <label class="form-check-label" for="agree_terms">
                    I agree to the <a href="<?php echo htmlspecialchars(url_for('terms.php')); ?>">Terms of Service</a> and
                    <a href="<?php echo htmlspecialchars(url_for('privacy.php')); ?>">Privacy Policy</a>
                </label>
            </div>

            <button type="submit" class="btn btn-primary w-100">Register</button>
        </form>
    </div>
</div>

<script>
    (function () {
        const roleSelect = document.getElementById('role');
        const studentAgeWrap = document.getElementById('student-age-wrap');
        const studentGradeWrap = document.getElementById('student-grade-wrap');
        const ageInput = document.getElementById('age');
        const gradeInput = document.getElementById('grade');

        const parentFields = document.getElementById('parent-fields');
        const teacherFields = document.getElementById('teacher-fields');
        const adminFields = document.getElementById('admin-fields');

        const parentRequired = ['parent_phone', 'parent_relation', 'child_name', 'child_grade'];
        const teacherRequired = ['teacher_employee_id', 'teacher_subjects', 'teacher_grade_levels', 'teacher_experience_years'];
        const adminRequired = ['admin_designation', 'admin_work_email', 'admin_work_phone', 'admin_id_code'];

        const schoolNameInput = document.getElementById('school_name');
        const schoolIdInput = document.getElementById('school_id');
        const form = document.getElementById('register-form');
        const options = Array.from(document.querySelectorAll('#school_options option'));

        function setRequired(ids, required) {
            ids.forEach(function (id) {
                const el = document.getElementById(id);
                if (el) {
                    el.required = required;
                }
            });
        }

        function toggleRoleFields() {
            const role = roleSelect.value;
            const isStudent = role === 'student';
            const isParent = role === 'parent';
            const isTeacher = role === 'teacher';
            const isAdmin = role === 'school_admin';

            studentAgeWrap.style.display = isStudent ? '' : 'none';
            studentGradeWrap.style.display = isStudent ? '' : 'none';
            ageInput.required = isStudent;
            gradeInput.required = isStudent;

            parentFields.style.display = isParent ? '' : 'none';
            teacherFields.style.display = isTeacher ? '' : 'none';
            adminFields.style.display = isAdmin ? '' : 'none';

            setRequired(parentRequired, isParent);
            setRequired(teacherRequired, isTeacher);
            setRequired(adminRequired, isAdmin);
        }

        function syncSchoolId() {
            if (!schoolNameInput || !schoolIdInput) {
                return;
            }
            const value = schoolNameInput.value.trim();
            const match = options.find((opt) => opt.value === value);
            schoolIdInput.value = match ? (match.dataset.id || '') : '';
        }

        roleSelect.addEventListener('change', toggleRoleFields);
        toggleRoleFields();

        if (schoolNameInput && schoolIdInput) {
            schoolNameInput.addEventListener('input', syncSchoolId);
            schoolNameInput.addEventListener('change', syncSchoolId);
            schoolNameInput.addEventListener('blur', syncSchoolId);
            form.addEventListener('submit', syncSchoolId);
        }
    })();
</script>

<?php
require_once __DIR__ . '/includes_footer.php';
