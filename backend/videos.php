<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = backend_user();
$pdo = get_pdo();

$hasVideoTestColumn = table_has_column($pdo, 'video_lectures', 'test_id');
$hasVideoAttributeColumn = table_has_column($pdo, 'video_lectures', 'attribute_id');
$hasVideoSubAttributeColumn = table_has_column($pdo, 'video_lectures', 'sub_attribute_id');
$hasVideoActiveColumn = table_has_column($pdo, 'video_lectures', 'is_active');
$hasVideoFeaturedColumn = table_has_column($pdo, 'video_lectures', 'is_featured');
$hasVideoDescriptionColumn = table_has_column($pdo, 'video_lectures', 'description');
$hasVideoCourseColumn = table_has_column($pdo, 'video_lectures', 'course_id');

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

function backend_video_default_form(): array
{
    return [
        'edit_id' => '',
        'test_id' => '',
        'attribute_id' => '',
        'sub_attribute_id' => '',
        'video_url' => '',
        'is_active' => '1',
        'is_featured' => '0',
    ];
}

function backend_video_form_from_source(array $source): array
{
    $form = backend_video_default_form();
    $form['edit_id'] = trim((string)($source['edit_id'] ?? ''));
    $form['test_id'] = trim((string)($source['test_id'] ?? ''));
    $form['attribute_id'] = trim((string)($source['attribute_id'] ?? ''));
    $form['sub_attribute_id'] = trim((string)($source['sub_attribute_id'] ?? ''));
    $form['video_url'] = trim((string)($source['video_url'] ?? ''));
    $form['is_active'] = (string)($source['is_active'] ?? '1');
    $form['is_featured'] = !empty($source['is_featured']) ? '1' : '0';
    return $form;
}

$migrationMessages = [];
if (!$hasVideoTestColumn || !$hasVideoAttributeColumn || !$hasVideoSubAttributeColumn) {
    $migrationMessages[] = 'Run migration migrations/2026-05-12_video_lecture_mapping_upgrade.sql for test and skill mappings.';
}
if (!$hasVideoFeaturedColumn) {
    $migrationMessages[] = 'Run migration migrations/2026-05-12_video_lecture_backend_featured.sql for the featured flag.';
}

$errors = [];
$success = null;
$form = backend_video_default_form();

if (isset($_GET['edit'])) {
    $editId = max(0, (int)$_GET['edit']);
    if ($editId > 0) {
        $selectParts = ['id', 'video_url'];
        if ($hasVideoTestColumn) {
            $selectParts[] = 'test_id';
        }
        if ($hasVideoAttributeColumn) {
            $selectParts[] = 'attribute_id';
        }
        if ($hasVideoSubAttributeColumn) {
            $selectParts[] = 'sub_attribute_id';
        }
        if ($hasVideoActiveColumn) {
            $selectParts[] = 'is_active';
        }
        if ($hasVideoFeaturedColumn) {
            $selectParts[] = 'is_featured';
        }
        $stmt = $pdo->prepare('SELECT ' . implode(', ', $selectParts) . ' FROM video_lectures WHERE id = ? LIMIT 1');
        $stmt->execute([$editId]);
        $existing = $stmt->fetch();
        if ($existing) {
            $form = [
                'edit_id' => (string)$existing['id'],
                'test_id' => (string)($existing['test_id'] ?? ''),
                'attribute_id' => (string)($existing['attribute_id'] ?? ''),
                'sub_attribute_id' => (string)($existing['sub_attribute_id'] ?? ''),
                'video_url' => (string)($existing['video_url'] ?? ''),
                'is_active' => !isset($existing['is_active']) || (int)$existing['is_active'] === 1 ? '1' : '0',
                'is_featured' => !empty($existing['is_featured']) ? '1' : '0',
            ];
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        try {
            backend_require_admin($user);
            $form = backend_video_form_from_source($_POST);

            $editId = max(0, (int)$form['edit_id']);
            $testId = max(0, (int)$form['test_id']);
            $attributeId = max(0, (int)$form['attribute_id']);
            $subAttributeId = max(0, (int)$form['sub_attribute_id']);
            $videoUrl = $form['video_url'];
            $youtubeId = backend_video_extract_youtube_id($videoUrl);
            $isActive = $form['is_active'] === '0' ? 0 : 1;
            $isFeatured = $form['is_featured'] === '1' ? 1 : 0;

            if ($youtubeId === null) {
                $errors[] = 'Enter a valid YouTube URL.';
            }
            if ($testId <= 0 && $attributeId <= 0 && $subAttributeId <= 0) {
                $errors[] = 'Select at least one mapping: test, attribute, or sub-attribute.';
            }

            if ($errors === []) {
                $title = 'YouTube Lecture ' . $youtubeId;
                $description = $hasVideoDescriptionColumn ? ('Embedded lecture from YouTube: ' . $videoUrl) : null;
                $sequenceOrder = 1;
                $duration = 10;

                $defaultCourseId = null;
                if ($hasVideoCourseColumn) {
                    $defaultCourseId = $pdo->query('SELECT id FROM courses ORDER BY id ASC LIMIT 1')->fetchColumn();
                    if ($defaultCourseId === false) {
                        $defaultCourseId = null;
                    }
                }

                if ($editId > 0) {
                    $updates = ['title = ?', 'video_url = ?', 'duration = ?', 'sequence_order = ?'];
                    $values = [$title, $videoUrl, $duration, $sequenceOrder];

                    if ($hasVideoDescriptionColumn) {
                        $updates[] = 'description = ?';
                        $values[] = $description;
                    }
                    if ($hasVideoCourseColumn && $defaultCourseId !== null) {
                        $updates[] = 'course_id = ?';
                        $values[] = (int)$defaultCourseId;
                    }
                    if ($hasVideoTestColumn) {
                        $updates[] = 'test_id = ?';
                        $values[] = $testId > 0 ? $testId : null;
                    }
                    if ($hasVideoAttributeColumn) {
                        $updates[] = 'attribute_id = ?';
                        $values[] = $attributeId > 0 ? $attributeId : null;
                    }
                    if ($hasVideoSubAttributeColumn) {
                        $updates[] = 'sub_attribute_id = ?';
                        $values[] = $subAttributeId > 0 ? $subAttributeId : null;
                    }
                    if ($hasVideoActiveColumn) {
                        $updates[] = 'is_active = ?';
                        $values[] = $isActive;
                    }
                    if ($hasVideoFeaturedColumn) {
                        $updates[] = 'is_featured = ?';
                        $values[] = $isFeatured;
                    }

                    $values[] = $editId;
                    $stmt = $pdo->prepare('UPDATE video_lectures SET ' . implode(', ', $updates) . ' WHERE id = ?');
                    $stmt->execute($values);
                    $success = 'Video updated.';
                } else {
                    $columns = ['title', 'video_url', 'duration', 'sequence_order'];
                    $placeholders = ['?', '?', '?', '?'];
                    $values = [$title, $videoUrl, $duration, $sequenceOrder];

                    if ($hasVideoCourseColumn) {
                        $columns[] = 'course_id';
                        $placeholders[] = '?';
                        $values[] = $defaultCourseId;
                    }
                    if ($hasVideoDescriptionColumn) {
                        $columns[] = 'description';
                        $placeholders[] = '?';
                        $values[] = $description;
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
                    if ($hasVideoActiveColumn) {
                        $columns[] = 'is_active';
                        $placeholders[] = '?';
                        $values[] = $isActive;
                    }
                    if ($hasVideoFeaturedColumn) {
                        $columns[] = 'is_featured';
                        $placeholders[] = '?';
                        $values[] = $isFeatured;
                    }

                    $stmt = $pdo->prepare(
                        'INSERT INTO video_lectures (' . implode(', ', $columns) . ')
                         VALUES (' . implode(', ', $placeholders) . ')'
                    );
                    $stmt->execute($values);
                    $success = 'Video added.';
                }

                $form = backend_video_default_form();
            }
        } catch (Throwable $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

$tests = $pdo->query('SELECT id, title FROM tests ORDER BY title ASC')->fetchAll();
$attributes = $pdo->query('SELECT id, name FROM attributes ORDER BY name ASC')->fetchAll();
$subAttributes = $pdo->query('SELECT id, attribute_id, name FROM sub_attributes ORDER BY attribute_id ASC, name ASC')->fetchAll();

$videoSelect = ['vl.id', 'vl.title', 'vl.video_url'];
if ($hasVideoTestColumn) {
    $videoSelect[] = 't.title AS test_title';
}
if ($hasVideoAttributeColumn) {
    $videoSelect[] = 'a.name AS attribute_name';
}
if ($hasVideoSubAttributeColumn) {
    $videoSelect[] = 'sa.name AS sub_attribute_name';
}
if ($hasVideoActiveColumn) {
    $videoSelect[] = 'vl.is_active';
}
if ($hasVideoFeaturedColumn) {
    $videoSelect[] = 'vl.is_featured';
}

$videosQuery = 'SELECT ' . implode(', ', $videoSelect) . '
    FROM video_lectures vl ';
if ($hasVideoTestColumn) {
    $videosQuery .= 'LEFT JOIN tests t ON t.id = vl.test_id ';
}
if ($hasVideoAttributeColumn) {
    $videosQuery .= 'LEFT JOIN attributes a ON a.id = vl.attribute_id ';
}
if ($hasVideoSubAttributeColumn) {
    $videosQuery .= 'LEFT JOIN sub_attributes sa ON sa.id = vl.sub_attribute_id ';
}
$videosQuery .= 'ORDER BY ';
if ($hasVideoFeaturedColumn) {
    $videosQuery .= 'vl.is_featured DESC, ';
}
$videosQuery .= 'vl.id DESC LIMIT 150';
$videos = $pdo->query($videosQuery)->fetchAll();

require_once dirname(__DIR__) . '/includes_header.php';
?>
<div class="eq-page-head">
    <h2>Video Tutorials Backend</h2>
    <p class="subtitle">Manage mapped YouTube videos with a minimal backend: mapping, URL, visibility, featured placement, and edit support.</p>
</div>
<?php require __DIR__ . '/nav.php'; ?>

<?php foreach ($migrationMessages as $message): ?>
    <div class="alert alert-warning"><?php echo htmlspecialchars($message); ?></div>
<?php endforeach; ?>
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
            <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($form['edit_id']); ?>">
            <h5 class="mb-3"><?php echo $form['edit_id'] !== '' ? 'Edit Video' : 'Add Video'; ?></h5>

            <label class="form-label">Select Test</label>
            <select class="form-select mb-3" name="test_id">
                <option value="">Select test</option>
                <?php foreach ($tests as $test): ?>
                    <option value="<?php echo (int)$test['id']; ?>"<?php echo $form['test_id'] === (string)$test['id'] ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars((string)$test['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label">Select Attribute</label>
                    <select class="form-select mb-3" name="attribute_id" id="video-attribute-select">
                        <option value="">Select attribute</option>
                        <?php foreach ($attributes as $attribute): ?>
                            <option value="<?php echo (int)$attribute['id']; ?>"<?php echo $form['attribute_id'] === (string)$attribute['id'] ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars((string)$attribute['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Select Sub-attribute</label>
                    <select class="form-select mb-3" name="sub_attribute_id" id="video-sub-attribute-select">
                        <option value="">Select sub-attribute</option>
                        <?php foreach ($subAttributes as $subAttribute): ?>
                            <option value="<?php echo (int)$subAttribute['id']; ?>" data-attribute-id="<?php echo (int)$subAttribute['attribute_id']; ?>"<?php echo $form['sub_attribute_id'] === (string)$subAttribute['id'] ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars((string)$subAttribute['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label class="form-label">Paste Video URL</label>
            <input class="form-control mb-3" name="video_url" placeholder="https://www.youtube.com/watch?v=..." value="<?php echo htmlspecialchars($form['video_url']); ?>" required>

            <label class="form-label">Status</label>
            <select class="form-select mb-3" name="is_active">
                <option value="1"<?php echo $form['is_active'] === '1' ? ' selected' : ''; ?>>Active</option>
                <option value="0"<?php echo $form['is_active'] === '0' ? ' selected' : ''; ?>>Inactive</option>
            </select>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="video-featured" <?php echo $form['is_featured'] === '1' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="video-featured">Featured (show on top)</label>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary"><?php echo $form['edit_id'] !== '' ? 'Update Video' : 'Add Video'; ?></button>
                <?php if ($form['edit_id'] !== ''): ?>
                    <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url_for('backend/videos.php')); ?>">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="col-lg-7">
        <div class="card p-3 shadow-sm">
            <h5 class="mb-3">Video Library</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Video</th>
                            <th>Mapping</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($videos as $video): ?>
                            <?php
                                $mapping = [];
                                if (!empty($video['test_title'])) {
                                    $mapping[] = 'Test: ' . (string)$video['test_title'];
                                }
                                if (!empty($video['sub_attribute_name'])) {
                                    $mapping[] = 'Sub-attribute: ' . (string)$video['sub_attribute_name'];
                                } elseif (!empty($video['attribute_name'])) {
                                    $mapping[] = 'Attribute: ' . (string)$video['attribute_name'];
                                }
                                $statusParts = [];
                                $statusParts[] = !isset($video['is_active']) || (int)$video['is_active'] === 1 ? 'Active' : 'Inactive';
                                if (!empty($video['is_featured'])) {
                                    $statusParts[] = 'Featured';
                                }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars((string)$video['title']); ?></strong>
                                    <div class="small text-muted"><?php echo htmlspecialchars((string)$video['video_url']); ?></div>
                                </td>
                                <td class="small text-muted"><?php echo htmlspecialchars($mapping ? implode(' · ', $mapping) : 'General library'); ?></td>
                                <td class="small"><?php echo htmlspecialchars(implode(' · ', $statusParts)); ?></td>
                                <td class="text-end">
                                    <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars(url_for('backend/videos.php?edit=' . (int)$video['id'])); ?>">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$videos): ?>
                            <tr><td colspan="4" class="text-muted">No videos added yet.</td></tr>
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
        const selected = subAttributeSelect.selectedOptions[0];
        if (selected && selected.hidden) {
            subAttributeSelect.value = '';
        }
    }

    attributeSelect.addEventListener('change', syncSubAttributes);
    syncSubAttributes();
})();
</script>

<?php require_once dirname(__DIR__) . '/includes_footer.php';
