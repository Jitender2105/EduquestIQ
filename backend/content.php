<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
$user = backend_user();
$pdo = get_pdo();

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'video') {
                $stmt = $pdo->prepare('INSERT INTO video_lectures (course_id, title, video_url, duration, sequence_order) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([(int)$_POST['course_id'], trim((string)$_POST['title']), trim((string)$_POST['video_url']) ?: null, (int)$_POST['duration'], (int)$_POST['sequence_order']]);
                $videoId = (int)$pdo->lastInsertId();
                $stmt = $pdo->prepare('INSERT INTO content_metadata (entity_type, entity_id, language, visibility, version_label, license_type, tags_json) VALUES ("video", ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$videoId, trim((string)$_POST['language']) ?: 'en', (string)$_POST['visibility'], trim((string)$_POST['version_label']) ?: null, trim((string)$_POST['license_type']) ?: null, trim((string)$_POST['tags_json']) ?: null]);
                $success = 'Video + metadata added.';
            } elseif ($action === 'material') {
                $stmt = $pdo->prepare('INSERT INTO study_materials (course_id, title, file_path, material_type, uploaded_at) VALUES (?, ?, ?, ?, NOW())');
                $stmt->execute([(int)$_POST['course_id'], trim((string)$_POST['title']), trim((string)$_POST['file_path']), (string)$_POST['material_type']]);
                $materialId = (int)$pdo->lastInsertId();
                $type = (string)$_POST['material_type'] === 'link' ? 'article' : 'material';
                $stmt = $pdo->prepare('INSERT INTO content_metadata (entity_type, entity_id, language, visibility, version_label, license_type, tags_json) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$type, $materialId, trim((string)$_POST['language']) ?: 'en', (string)$_POST['visibility'], trim((string)$_POST['version_label']) ?: null, trim((string)$_POST['license_type']) ?: null, trim((string)$_POST['tags_json']) ?: null]);
                $success = 'Material/article + metadata added.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

$courses = $pdo->query('SELECT id, title FROM courses ORDER BY id DESC LIMIT 200')->fetchAll();
$videos = $pdo->query('SELECT vl.id, c.title AS course_title, vl.title FROM video_lectures vl JOIN courses c ON c.id=vl.course_id ORDER BY vl.id DESC LIMIT 60')->fetchAll();
$materials = $pdo->query('SELECT sm.id, c.title AS course_title, sm.title, sm.material_type FROM study_materials sm JOIN courses c ON c.id=sm.course_id ORDER BY sm.id DESC LIMIT 60')->fetchAll();
$meta = $pdo->query('SELECT entity_type, entity_id, visibility, language, updated_at FROM content_metadata ORDER BY updated_at DESC LIMIT 80')->fetchAll();

require_once dirname(__DIR__) . '/includes_header.php';
?>
<div class="eq-page-head"><h2>Content Backend</h2><p class="subtitle">Video tutorials, study materials, and article resources with content metadata.</p></div>
<?php require __DIR__ . '/nav.php'; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="row g-3">
<div class="col-md-6"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="video"><h6>Add Video Tutorial</h6><select class="form-select mb-2" name="course_id" required><?php foreach ($courses as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option><?php endforeach; ?></select><input class="form-control mb-2" name="title" placeholder="Video title" required><input class="form-control mb-2" name="video_url" placeholder="https://..."><div class="row g-2 mb-2"><div class="col"><input class="form-control" type="number" name="duration" value="10" min="1"></div><div class="col"><input class="form-control" type="number" name="sequence_order" value="1" min="1"></div></div><div class="row g-2 mb-2"><div class="col"><input class="form-control" name="language" value="en"></div><div class="col"><select class="form-select" name="visibility"><option value="public">public</option><option value="enrolled_only">enrolled_only</option><option value="private">private</option></select></div></div><input class="form-control mb-2" name="version_label" placeholder="v1"><input class="form-control mb-2" name="license_type" placeholder="CC-BY"><input class="form-control mb-2" name="tags_json" placeholder='["algebra","intro"]'><button class="btn btn-primary btn-sm">Save Video</button></form></div>
<div class="col-md-6"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="material"><h6>Add Study Material / Article</h6><select class="form-select mb-2" name="course_id" required><?php foreach ($courses as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option><?php endforeach; ?></select><input class="form-control mb-2" name="title" placeholder="Resource title" required><input class="form-control mb-2" name="file_path" placeholder="URL or path" required><select class="form-select mb-2" name="material_type"><option value="pdf">pdf</option><option value="doc">doc</option><option value="ppt">ppt</option><option value="link">link(article)</option></select><div class="row g-2 mb-2"><div class="col"><input class="form-control" name="language" value="en"></div><div class="col"><select class="form-select" name="visibility"><option value="public">public</option><option value="enrolled_only">enrolled_only</option><option value="private">private</option></select></div></div><input class="form-control mb-2" name="version_label" placeholder="2026.1"><input class="form-control mb-2" name="license_type" placeholder="All rights reserved"><input class="form-control mb-2" name="tags_json" placeholder='["worksheet","grade8"]'><button class="btn btn-primary btn-sm">Save Resource</button></form></div>
</div>
<div class="row g-3 mt-1"><div class="col-md-4"><div class="card p-3"><h6>Recent Videos</h6><ul class="small mb-0"><?php foreach ($videos as $v): ?><li>#<?php echo (int)$v['id']; ?> <?php echo htmlspecialchars($v['course_title'].' -> '.$v['title']); ?></li><?php endforeach; ?></ul></div></div><div class="col-md-4"><div class="card p-3"><h6>Recent Materials</h6><ul class="small mb-0"><?php foreach ($materials as $m): ?><li>#<?php echo (int)$m['id']; ?> <?php echo htmlspecialchars($m['title'].' ['.$m['material_type'].']'); ?></li><?php endforeach; ?></ul></div></div><div class="col-md-4"><div class="card p-3"><h6>Recent Metadata</h6><ul class="small mb-0"><?php foreach ($meta as $m): ?><li><?php echo htmlspecialchars($m['entity_type'].'#'.$m['entity_id'].' '.$m['visibility'].' '.$m['language']); ?></li><?php endforeach; ?></ul></div></div></div>
<?php require_once dirname(__DIR__) . '/includes_footer.php';
