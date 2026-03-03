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
        try {
            $postId = (int)($_POST['post_id'] ?? 0);
            if ($postId > 0) {
                $stmt = $pdo->prepare('DELETE FROM community_posts WHERE id = ?');
                $stmt->execute([$postId]);
                $success = 'Post deleted.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Delete failed: ' . $e->getMessage();
        }
    }
}

$posts = $pdo->query('SELECT cp.id, cp.content, cp.created_at, u.name AS user_name FROM community_posts cp JOIN users u ON u.id=cp.user_id ORDER BY cp.id DESC LIMIT 150')->fetchAll();

require_once dirname(__DIR__) . '/includes_header.php';
?>
<div class="eq-page-head"><h2>Community Moderation</h2><p class="subtitle">Review and moderate community posts from a dedicated backend page.</p></div>
<?php require __DIR__ . '/nav.php'; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card p-3"><h6>Recent Posts</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>ID</th><th>User</th><th>Content</th><th>Action</th></tr></thead><tbody><?php foreach ($posts as $p): ?><tr><td><?php echo (int)$p['id']; ?></td><td><?php echo htmlspecialchars($p['user_name']); ?></td><td><?php echo htmlspecialchars(text_preview((string)$p['content'], 160, '...')); ?></td><td><form method="post" class="m-0"><?php echo csrf_field(); ?><input type="hidden" name="post_id" value="<?php echo (int)$p['id']; ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php require_once dirname(__DIR__) . '/includes_footer.php';
