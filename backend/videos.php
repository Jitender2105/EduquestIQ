<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = backend_user();
$pdo = get_pdo();

$hasVideoTestColumn = table_has_column($pdo, 'video_lectures', 'test_id');
$hasVideoAttributeColumn = table_has_column($pdo, 'video_lectures', 'attribute_id');
$hasVideoSubAttributeColumn = table_has_column($pdo, 'video_lectures', 'sub_attribute_id');
$hasVideoDescriptionColumn = table_has_column($pdo, 'video_lectures', 'description');
$hasVideoActiveColumn = table_has_column($pdo, 'video_lectures', 'is_active');

function backend_video_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function backend_video_extract_youtube_id(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    if (preg_match('~(?:youtube\.com/watch\?v=|youtube\.com/embed/|youtube\.com/shorts/|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $matches)) {
        return $matches[1];
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return null;
    }

    if (!empty($parts['host']) && str_contains((string)$parts['host'], 'youtube.com') && !empty($parts['query'])) {
        parse_str((string)$parts['query'], $query);
        if (!empty($query['v']) && preg_match('/^[A-Za-z0-9_-]{11}$/', (string)$query['v'])) {
            return (string)$query['v'];
        }
    }

    return null;
}

$errors = [];
$success = null;
$migrationNotice = null;

if (!$hasVideoTestColumn || !$hasVideoAttributeColumn || !$hasVideoSubAttributeColumn || !$hasVideoDescriptionColumn) {
    $migrationNotice = 'Run migration migrations/2026-05-12_video_lecture_mapping_upgrade.sql to enable full video mapping fields.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        try {
            backend_require_admin($user);

            $title = trim((string)($_POST['title'] ?? ''));
            $videoUrl = trim((string)($_POST['video_url'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $duration = max(1, (int)($_POST['duration'] ?? 0));
            $sequenceOrder = max(1, (int)($_POST['sequence_order'] ?? 1));
            $courseId = max(1, (int)($_POST['course_id'] ?? 0));
            $testId = max(0, (int)($_POST['test_id'] ?? 0));
            $attributeId = max(0, (int)($_POST['attribute_id'] ?? 0));
            $subAttributeId = max(0, (int)($_POST['sub_attribute_id'] ?? 0));
            $isActive = (string)($_POST['is_active'] ?? '1') === '1' ? 1 : 0;

            if ($title === '') {
                $errors[] = 'Video title is required.';
            }
            if ($courseId <= 0) {
                $errors[] = 'Course is required.';
            }
            if (backend_video_extract_youtube_id($videoUrl) === null) {
                $errors[] = 'Enter a valid YouTube URL.';
            }

            if ($errors === []) {
                $columns = ['course_id', 'title'];
                $placeholders = ['?', '?'];
                $values = [$courseId, $title];

                if ($hasVideoDescriptionColumn) {
                    $columns[] = 'description';
                    $placeholders[] = '?';
                    $values[] = $description !== '' ? $description : null;
                }
                if ($hasVideoTestColumn) {
                    $columns[] = 'test_id';
                    $placeholders[] = '?';
                    $values[] = $testId > 0 ? $testId : null;
                }
                if ($hasVideoAttributeColumn) {
                    $columns[] = 'attribute_id';
                    $placeholders[] = '?';
                    $values[] = $attributeId > 0 ? $attributeId : null;
                }
                if ($hasVideoSubAttributeColumn) {
                    $columns[] = 'sub_attribute_id';
                    $placeholders[] = '?';
                    $values[] = $subAttributeId > 0 ? $subAttributeId : null;
                }

                $columns = array_merge($columns, ['video_url', 'duration', 'sequence_order']);
                $placeholders = array_merge($placeholders, ['?', '?', '?']);
                $values = array_merge($values, [$videoUrl, $duration, $sequenceOrder]);

                if ($hasVideoActiveColumn) {
                    $columns[] = 'is_active';
                    $placeholders[] = '?';
                    $values[] = $isActive;
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO video_lectures (' . implode(', ', $columns) . ')
                     VALUES (' . implode(', ', $placeholders) . ')'
                );
                $stmt->execute($values);
                $videoId = (int)$pdo->lastInsertId();

                if (backend_video_table_exists($pdo, 'content_metadata')) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO content_metadata
                         (entity_type, entity_id, language, visibility, version_label, license_type, tags_json)
                         VALUES ("video", ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $videoId,
                        trim((string)($_POST['language'] ?? '')) ?: 'en',
                        (string)($_POST['visibility'] ?? 'public'),
                        trim((string)($_POST['version_label'] ?? '')) ?: null,
                        trim((string)($_POST['license_type'] ?? '')) ?: null,
                        trim((string)($_POST['tags_json'] ?? '')) ?: null,
                    ]);
                }

                $success = 'Video tutorial saved.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

$courses = $pdo->query('SELECT id, title FROM courses ORDER BY title ASC')->fetchAll();
$tests = $pdo->query('SELECT id, title FROM tests ORDER BY title ASC')->fetchAll();
$attributes = $pdo->query('SELECT id, name FROM attributes ORDER BY name ASC')->fetchAll();
$subAttributes = $pdo->query('SELECT id, attribute_id, name FROM sub_attributes ORDER BY attribute_id ASC, name ASC')->fetchAll();

$videoSelect = ['vl.id', 'c.title AS course_title', 'vl.title', 'vl.duration', 'vl.sequence_order', 'vl.video_url'];
if ($hasVideoTestColumn) {
    $videoSelect[] = 't.title AS test_title';
}
if ($hasVideoAttributeColumn) {
    $videoSelect[] = 'a.name AS attribute_name';
}
if ($hasVideoSubAttributeColumn) {
    $videoSelect[] = 'sa.name AS sub_attribute_name';
}

$videosQuery = 'SELECT ' . implode(', ', $videoSelect) . '
    FROM video_lectures vl
    JOIN courses c ON c.id = vl.course_id ';
if ($hasVideoTestColumn) {
    $videosQuery .= 'LEFT JOIN tests t ON t.id = vl.test_id ';
}
if ($hasVideoAttributeColumn) {
    $videosQuery .= 'LEFT JOIN attributes a ON a.id = vl.attribute_id ';
}
if ($hasVideoSubAttributeColumn) {
    $videosQuery .= 'LEFT JOIN sub_attributes sa ON sa.id = vl.sub_attribute_id ';
}
$videosQuery .= 'ORDER BY vl.id DESC LIMIT 150';
$videos = $pdo->query($videosQuery)->fetchAll();

require_once dirname(__DIR__) . '/includes_header.php';
?>
<div class="eq-page-head">
    <h2>Video Tutorials Backend</h2>
    <p class="subtitle">Map YouTube lectures to tests and skill areas so the frontend can group them like a learning video hub.</p>
</div>
<?php require __DIR__ . '/nav.php'; ?>
<?php require __DIR__ . '/richtext.php'; ?>

<?php if ($migrationNotice): ?>
    <div class="alert alert-warning"><?php echo htmlspecialchars($migrationNotice); ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <form method="post" class="card p-3 shadow-sm">
            <?php echo csrf_field(); ?>
            <h5 class="mb-3">Add YouTube Video Lecture</h5>

            <label class="form-label">Course</label>
            <select class="form-select mb-3" name="course_id" required>
                <option value="">Select course</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo (int)$course['id']; ?>"><?php echo htmlspecialchars((string)$course['title']); ?></option>
                <?php endforeach; ?>
            </select>

            <label class="form-label">Map to test</label>
            <select class="form-select mb-3" name="test_id">
                <option value="">Optional test mapping</option>
                <?php foreach ($tests as $test): ?>
                    <option value="<?php echo (int)$test['id']; ?>"><?php echo htmlspecialchars((string)$test['title']); ?></option>
                <?php endforeach; ?>
            </select>

            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label">Attribute</label>
                    <select class="form-select mb-3" name="attribute_id" id="video-attribute-select">
                        <option value="">Optional attribute</option>
                        <?php foreach ($attributes as $attribute): ?>
                            <option value="<?php echo (int)$attribute['id']; ?>"><?php echo htmlspecialchars((string)$attribute['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sub-attribute</label>
                    <select class="form-select mb-3" name="sub_attribute_id" id="video-sub-attribute-select">
                        <option value="">Optional sub-attribute</option>
                        <?php foreach ($subAttributes as $subAttribute): ?>
                            <option value="<?php echo (int)$subAttribute['id']; ?>" data-attribute-id="<?php echo (int)$subAttribute['attribute_id']; ?>">
                                <?php echo htmlspecialchars((string)$subAttribute['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label class="form-label">Video title</label>
            <input class="form-control mb-3" name="title" required>

            <label class="form-label">Description</label>
            <textarea class="form-control mb-3 eq-richtext" data-richtext name="description" rows="4"></textarea>

            <label class="form-label">YouTube URL</label>
            <input class="form-control mb-3" name="video_url" placeholder="https://www.youtube.com/watch?v=..." required>

            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Duration (min)</label>
                    <input class="form-control" type="number" name="duration" min="1" value="10">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sequence</label>
                    <input class="form-control" type="number" name="sequence_order" min="1" value="1">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Language</label>
                    <input class="form-control" name="language" value="en">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Visibility</label>
                    <select class="form-select" name="visibility">
                        <option value="public">public</option>
                        <option value="enrolled_only">enrolled_only</option>
                        <option value="private">private</option>
                    </select>
                </div>
            </div>

            <label class="form-label">Version label</label>
            <input class="form-control mb-3" name="version_label" placeholder="v1">

            <label class="form-label">License type</label>
            <input class="form-control mb-3" name="license_type" placeholder="Standard educational license">

            <label class="form-label">Tags JSON</label>
            <textarea class="form-control mb-3" name="tags_json" rows="2" placeholder='["youtube","algebra","test-prep"]'></textarea>

            <button class="btn btn-primary">Save Video Lecture</button>
        </form>
    </div>

    <div class="col-lg-7">
        <div class="card p-3 shadow-sm">
            <h5 class="mb-3">Recent Video Lectures</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Mapped To</th>
                            <th>Meta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($videos as $video): ?>
                            <?php
                                $mappedTo = [];
                                if (!empty($video['test_title'])) {
                                    $mappedTo[] = 'Test: ' . (string)$video['test_title'];
                                }
                                if (!empty($video['sub_attribute_name'])) {
                                    $mappedTo[] = 'Skill: ' . (string)$video['sub_attribute_name'];
                                } elseif (!empty($video['attribute_name'])) {
                                    $mappedTo[] = 'Attribute: ' . (string)$video['attribute_name'];
                                }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars((string)$video['title']); ?></strong>
                                    <div class="small text-muted"><?php echo htmlspecialchars((string)$video['course_title']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($mappedTo ? implode(' · ', $mappedTo) : 'General library'); ?></td>
                                <td class="small text-muted">
                                    <?php echo (int)($video['duration'] ?? 0); ?> min
                                    · #<?php echo (int)($video['sequence_order'] ?? 0); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$videos): ?>
                            <tr><td colspan="3" class="text-muted">No videos added yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const attributeSelect = document.getElementById('video-attribute-select');
    const subAttributeSelect = document.getElementById('video-sub-attribute-select');
    if (!attributeSelect || !subAttributeSelect) {
        return;
    }

    const options = Array.from(subAttributeSelect.querySelectorAll('option[data-attribute-id]'));
    function syncSubAttributes() {
        const attributeId = attributeSelect.value;
        options.forEach(function (option) {
            option.hidden = attributeId !== '' && option.getAttribute('data-attribute-id') !== attributeId;
        });
        if (subAttributeSelect.selectedOptions[0] && subAttributeSelect.selectedOptions[0].hidden) {
            subAttributeSelect.value = '';
        }
    }

    attributeSelect.addEventListener('change', syncSubAttributes);
    syncSubAttributes();
})();
</script>

<?php require_once dirname(__DIR__) . '/includes_footer.php';
