<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';
require_once __DIR__ . '/includes_csrf.php';
require_once __DIR__ . '/includes_achievements.php';

$pdo = get_pdo();
$authUser = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($csrf)) {
        http_response_code(400);
        echo 'Invalid CSRF token.';
        exit;
    }

    if (!$authUser) {
        http_response_code(403);
        echo 'You must be logged in to interact with the community.';
        exit;
    }

    $action = $_POST['action'] ?? '';
    $userId = (int)$authUser['sub'];

    if ($action === 'post') {
        $content = trim((string)($_POST['content'] ?? ''));
        if ($content !== '') {
            $stmt = $pdo->prepare(
                'INSERT INTO community_posts (user_id, content, created_at) VALUES (?, ?, NOW())'
            );
            $stmt->execute([$userId, $content]);
            evaluate_achievements_for_user($userId);
        }
        header('Location: ' . url_for('community.php'));
        exit;
    }

    if ($action === 'comment') {
        $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        $comment = trim((string)($_POST['comment'] ?? ''));
        if ($postId > 0 && $comment !== '') {
            $stmt = $pdo->prepare(
                'INSERT INTO post_comments (post_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())'
            );
            $stmt->execute([$postId, $userId, $comment]);
            evaluate_achievements_for_user($userId);
        }
        header('Location: ' . url_for('community.php'));
        exit;
    }

    if ($action === 'like') {
        $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        if ($postId > 0) {
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO post_likes (post_id, user_id) VALUES (?, ?)'
            );
            $stmt->execute([$postId, $userId]);
            evaluate_achievements_for_user($userId);
        }
        header('Location: ' . url_for('community.php'));
        exit;
    }
}

require_once __DIR__ . '/includes_header.php';

// Load posts
$stmt = $pdo->query(
    'SELECT cp.id, cp.content, cp.created_at,
            u.name AS user_name,
            (SELECT COUNT(*) FROM post_comments WHERE post_id = cp.id) AS comments_count,
            (SELECT COUNT(*) FROM post_likes WHERE post_id = cp.id) AS likes_count
     FROM community_posts cp
     JOIN users u ON cp.user_id = u.id
     ORDER BY cp.created_at DESC
     LIMIT 20'
);
$posts = $stmt->fetchAll();

// Load comments for these posts
$commentsByPost = [];
if ($posts) {
    $postIds = array_column($posts, 'id');
    $in = implode(',', array_fill(0, count($postIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT pc.post_id, pc.comment, pc.created_at, u.name AS user_name
         FROM post_comments pc
         JOIN users u ON pc.user_id = u.id
         WHERE pc.post_id IN ($in)
         ORDER BY pc.created_at ASC"
    );
    $stmt->execute($postIds);
    foreach ($stmt->fetchAll() as $row) {
        $pid = (int)$row['post_id'];
        if (!isset($commentsByPost[$pid])) {
            $commentsByPost[$pid] = [];
        }
        $commentsByPost[$pid][] = $row;
    }
}
?>

<div class="row">
    <div class="col-md-8">
        <div class="eq-page-head">
            <h2>Community Learning</h2>
            <p class="subtitle">Share questions, ideas, and learning moments with peers. Community activity can also unlock achievements.</p>
        </div>

        <?php if ($authUser): ?>
            <form method="post" class="card mb-3 p-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="post">
                <div class="mb-2">
                    <textarea name="content" rows="3" class="form-control" placeholder="Start a discussion..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Post</button>
            </form>
        <?php else: ?>
            <div class="alert alert-info">
                Please <a href="<?php echo htmlspecialchars(url_for('login.php')); ?>">log in</a> to post or comment.
            </div>
        <?php endif; ?>

        <?php if (!$posts): ?>
            <p class="text-muted">No posts yet. Be the first to start a conversation!</p>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong><?php echo htmlspecialchars($post['user_name']); ?></strong>
                                <span class="text-muted small">
                                    &middot; <?php echo htmlspecialchars($post['created_at']); ?>
                                </span>
                            </div>
                        </div>
                        <p class="mt-2 mb-2"><?php echo nl2br(htmlspecialchars((string)$post['content'])); ?></p>
                        <div class="d-flex align-items-center mb-2">
                            <span class="small text-muted me-2">
                                <?php echo (int)$post['likes_count']; ?> likes ·
                                <?php echo (int)$post['comments_count']; ?> comments
                            </span>
                            <?php if ($authUser): ?>
                                <form method="post" class="d-inline ms-2">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="like">
                                    <input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>">
                                    <button type="submit" class="btn btn-link btn-sm p-0">Like</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($commentsByPost[(int)$post['id']] ?? [])): ?>
                            <div class="mb-2">
                                <?php foreach ($commentsByPost[(int)$post['id']] as $comment): ?>
                                    <div class="border rounded-3 p-2 mb-1 bg-light">
                                        <strong class="small"><?php echo htmlspecialchars($comment['user_name']); ?></strong>
                                        <span class="text-muted small">
                                            &middot; <?php echo htmlspecialchars($comment['created_at']); ?>
                                        </span>
                                        <div class="small mt-1">
                                            <?php echo nl2br(htmlspecialchars((string)$comment['comment'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($authUser): ?>
                            <form method="post" class="mt-2">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="comment">
                                <input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>">
                                <div class="input-group input-group-sm">
                                    <input type="text" name="comment" class="form-control" placeholder="Write a comment...">
                                    <button type="submit" class="btn btn-outline-secondary">Comment</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/includes_footer.php';
