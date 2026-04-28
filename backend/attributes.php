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
            if ($action === 'attribute') {
                backend_require_admin($user);
                $stmt = $pdo->prepare('INSERT INTO attributes (name, description) VALUES (?, ?)');
                $stmt->execute([trim((string)$_POST['name']), trim((string)$_POST['description']) ?: null]);
                $success = 'Attribute added.';
            } elseif ($action === 'sub') {
                backend_require_admin($user);
                $stmt = $pdo->prepare('INSERT INTO sub_attributes (attribute_id, name, description) VALUES (?, ?, ?)');
                $stmt->execute([(int)$_POST['attribute_id'], trim((string)$_POST['name']), trim((string)$_POST['description']) ?: null]);
                $success = 'Sub-attribute added.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

$attributes = $pdo->query('SELECT id, name, description FROM attributes ORDER BY id DESC LIMIT 150')->fetchAll();
$subs = $pdo->query('SELECT sa.id, sa.name, a.name AS attribute_name FROM sub_attributes sa JOIN attributes a ON a.id = sa.attribute_id ORDER BY sa.id DESC LIMIT 300')->fetchAll();

require_once dirname(__DIR__) . '/includes_header.php';
?>
<div class="eq-page-head"><h2>Attributes & Sub-Attributes</h2><p class="subtitle">Core skill measurement framework.</p></div>
<?php require __DIR__ . '/nav.php'; ?>
<?php require __DIR__ . '/richtext.php'; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="row g-3">
<div class="col-md-6"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="attribute"><h6>Create Attribute</h6><input class="form-control mb-2" name="name" placeholder="Academic" required><textarea class="form-control mb-2 eq-richtext" data-richtext name="description" rows="2" placeholder="Description"></textarea><button class="btn btn-primary btn-sm" <?php echo backend_is_admin($user) ? '' : 'disabled'; ?>>Add</button></form></div>
<div class="col-md-6"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="sub"><h6>Create Sub-Attribute</h6><select class="form-select mb-2" name="attribute_id" required><?php foreach ($attributes as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option><?php endforeach; ?></select><input class="form-control mb-2" name="name" placeholder="Mathematics" required><textarea class="form-control mb-2 eq-richtext" data-richtext name="description" rows="2"></textarea><button class="btn btn-primary btn-sm" <?php echo backend_is_admin($user) ? '' : 'disabled'; ?>>Add</button></form></div>
</div>
<div class="row g-3 mt-1">
<div class="col-md-6"><div class="card p-3"><h6>Attributes</h6><ul class="small mb-0"><?php foreach (array_slice($attributes,0,40) as $a): ?><li>#<?php echo (int)$a['id']; ?> <?php echo htmlspecialchars($a['name']); ?></li><?php endforeach; ?></ul></div></div>
<div class="col-md-6"><div class="card p-3"><h6>Sub-Attributes</h6><ul class="small mb-0"><?php foreach (array_slice($subs,0,80) as $s): ?><li><?php echo htmlspecialchars($s['attribute_name'].' -> '.$s['name']); ?></li><?php endforeach; ?></ul></div></div>
</div>
<?php require_once dirname(__DIR__) . '/includes_footer.php';
