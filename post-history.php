<?php
// post-history.php
require_once 'includes/auth_check.php';
require_once 'config/database.php';
requireLogin();

$user_id = getCurrentUserId();
$conn = getDBConnection();

// Get optional filter from query string
$status_filter = $_GET['status'] ?? 'all';

// Handle Post Deletion
$action_msg = '';
$action_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $action_error = 'Invalid submission.';
    } else {
        $delete_post_id = intval($_POST['post_id'] ?? 0);
        
        // Verify post belongs to this user
        $stmt = $conn->prepare("SELECT p.id, m.path FROM posts p JOIN media_files m ON p.media_id = m.id WHERE p.id = ? AND p.user_id = ?");
        $stmt->execute([$delete_post_id, $user_id]);
        $post_to_delete = $stmt->fetch();

        if ($post_to_delete) {
            try {
                $conn->beginTransaction();

                // Delete post record (cascade deletes post_platforms)
                $stmtDel = $conn->prepare("DELETE FROM posts WHERE id = ?");
                $stmtDel->execute([$delete_post_id]);

                $conn->commit();
                $action_msg = "Post deleted successfully.";
            } catch (Exception $e) {
                if ($conn->inTransaction()) { $conn->rollBack(); }
                $action_error = "Failed to delete post: " . $e->getMessage();
            }
        } else {
            $action_error = "Post not found or permission denied.";
        }
    }
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Build query depending on status filter
$sql = "
    SELECT 
        p.id, 
        p.caption, 
        p.title, 
        p.media_type, 
        p.status, 
        p.scheduled_at, 
        p.published_at, 
        p.created_at,
        m.path as media_path,
        m.mime_type
    FROM posts p 
    JOIN media_files m ON p.media_id = m.id 
    WHERE p.user_id = ?
";

$params = [$user_id];

if (in_array($status_filter, ['posted', 'scheduled', 'draft', 'failed'])) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

// Get platform metadata
$platform_meta = [
    'facebook'  => ['icon' => '📘', 'label' => 'Facebook'],
    'telegram'  => ['icon' => '✈️', 'label' => 'Telegram'],
    'linkedin'  => ['icon' => '💼', 'label' => 'LinkedIn'],
    'tiktok'    => ['icon' => '🎵', 'label' => 'TikTok'],
    'instagram' => ['icon' => '📷', 'label' => 'Instagram'],
];

// Helper to fetch platforms for each post
function getPostPlatforms($conn, $post_id) {
    $stmt = $conn->prepare("SELECT platform, platform_post_id, status, error_message, posted_at FROM post_platforms WHERE post_id = ?");
    $stmt->execute([$post_id]);
    return $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post History - Social Media Manager</title>
    <link rel="stylesheet" href="assets/css/post-history.css">
    <style>
        /* Fallback Styles if post-history.css does not exist */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f4f6f9; color: #333; padding-bottom: 40px; }
        .header { background: #ffffff; border-bottom: 1px solid #e0e0e0; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .header-left { display: flex; align-items: center; gap: 20px; }
        .header h1 { font-size: 20px; color: #111; }
        .back-btn { text-decoration: none; color: #0066cc; font-size: 14px; font-weight: 500; }
        .user-info { display: flex; align-items: center; gap: 15px; font-size: 14px; }
        .btn-logout { background: #ff4d4d; color: #fff; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; }
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #e6f4ea; color: #137333; border: 1px solid #ceead6; }
        .alert-error { background: #fce8e6; color: #c5221f; border: 1px solid #fad2cf; }
        
        .filter-bar { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-btn { padding: 8px 16px; border-radius: 20px; text-decoration: none; background: #e4e6eb; color: #4b4f56; font-size: 13px; font-weight: 600; }
        .filter-btn.active { background: #0066cc; color: #ffffff; }

        .post-card { background: #fff; border-radius: 8px; border: 1px solid #e0e0e0; margin-bottom: 20px; padding: 20px; display: flex; gap: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .media-preview { width: 140px; height: 140px; border-radius: 6px; overflow: hidden; background: #000; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
        .media-preview img, .media-preview video { width: 100%; height: 100%; object-fit: cover; }
        .post-content { flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .post-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .post-title { font-weight: 700; font-size: 16px; color: #111; margin-bottom: 4px; }
        .post-caption { font-size: 14px; color: #444; line-height: 1.4; white-space: pre-wrap; margin-bottom: 12px; }
        
        .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-posted { background: #e6f4ea; color: #137333; }
        .badge-scheduled { background: #feefe3; color: #b06000; }
        .badge-draft { background: #f1f3f4; color: #5f6368; }
        .badge-failed { background: #fce8e6; color: #c5221f; }

        .post-meta { font-size: 12px; color: #777; margin-bottom: 12px; display: flex; gap: 15px; flex-wrap: wrap; }
        .platform-list { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .platform-chip { font-size: 12px; padding: 4px 10px; border-radius: 15px; border: 1px solid #ddd; background: #fafafa; display: flex; align-items: center; gap: 6px; }
        .platform-chip.chip-posted { border-color: #ceead6; background: #f6fbf7; }
        .platform-chip.chip-failed { border-color: #fad2cf; background: #fff8f8; }
        
        .post-actions { display: flex; justify-content: flex-end; margin-top: 10px; }
        .btn-delete { background: none; border: none; color: #dc3545; font-size: 13px; cursor: pointer; text-decoration: underline; padding: 0; }
        .btn-delete:hover { color: #a71d2a; }
        
        .empty-state { text-align: center; padding: 50px 20px; background: #fff; border-radius: 8px; border: 1px solid #e0e0e0; }
        .empty-state h3 { font-size: 18px; color: #333; margin-bottom: 8px; }
        .empty-state p { font-size: 14px; color: #666; margin-bottom: 15px; }
        .btn-create { display: inline-block; background: #0066cc; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 4px; font-size: 14px; }
        
        .error-tooltip { color: #c5221f; font-size: 11px; margin-top: 4px; display: block; }
    </style>
</head>

<body>
    <div class="header">
        <div class="header-left">
            <h1>📊 Post History</h1>
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        <div class="user-info">
            <span>👤 <?php echo htmlspecialchars(getCurrentUsername()); ?></span>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($action_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($action_msg); ?></div>
        <?php endif; ?>

        <?php if ($action_error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($action_error); ?></div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <a href="post-history.php?status=all" class="filter-btn <?php echo $status_filter === 'all' ? 'active' : ''; ?>">All Posts</a>
            <a href="post-history.php?status=posted" class="filter-btn <?php echo $status_filter === 'posted' ? 'active' : ''; ?>">Posted</a>
            <a href="post-history.php?status=scheduled" class="filter-btn <?php echo $status_filter === 'scheduled' ? 'active' : ''; ?>">Scheduled</a>
            <a href="post-history.php?status=draft" class="filter-btn <?php echo $status_filter === 'draft' ? 'active' : ''; ?>">Drafts</a>
            <a href="post-history.php?status=failed" class="filter-btn <?php echo $status_filter === 'failed' ? 'active' : ''; ?>">Failed</a>
        </div>

        <?php if (empty($posts)): ?>
            <div class="empty-state">
                <h3>No posts found</h3>
                <p>You haven't created any posts matching this status yet.</p>
                <a href="create-post.php" class="btn-create">✍️ Create a New Post</a>
            </div>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <?php 
                    $post_platforms = getPostPlatforms($conn, $post['id']); 
                    $status = $post['status'];
                    $status_class = match ($status) {
                        'posted' => 'badge-posted',
                        'scheduled' => 'badge-scheduled',
                        'failed' => 'badge-failed',
                        default => 'badge-draft'
                    };
                ?>
                <div class="post-card">
                    <div class="media-preview">
                        <?php if ($post['media_type'] === 'video'): ?>
                            <video src="<?php echo htmlspecialchars($post['media_path']); ?>" controls></video>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($post['media_path']); ?>" alt="Post Media">
                        <?php endif; ?>
                    </div>

                    <div class="post-content">
                        <div>
                            <div class="post-header">
                                <div class="post-title">
                                    <?php echo htmlspecialchars($post['title'] ?: 'Untitled Post'); ?>
                                </div>
                                <span class="badge <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars(ucfirst($status)); ?>
                                </span>
                            </div>

                            <div class="post-caption">
                                <?php echo htmlspecialchars($post['caption']); ?>
                            </div>

                            <div class="post-meta">
                                <span>📅 Created: <?php echo date('M d, Y H:i', strtotime($post['created_at'])); ?></span>
                                <?php if ($post['scheduled_at']): ?>
                                    <span>⏰ Scheduled for: <?php echo date('M d, Y H:i', strtotime($post['scheduled_at'])); ?></span>
                                <?php endif; ?>
                                <?php if ($post['published_at']): ?>
                                    <span>🚀 Published: <?php echo date('M d, Y H:i', strtotime($post['published_at'])); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="platform-list">
                                <strong style="font-size: 12px; color: #555;">Target Platforms:</strong>
                                <?php foreach ($post_platforms as $pp): ?>
                                    <?php 
                                        $p_code = $pp['platform'];
                                        $p_icon = $platform_meta[$p_code]['icon'] ?? '🔗';
                                        $p_label = $platform_meta[$p_code]['label'] ?? ucfirst($p_code);
                                        $p_status = $pp['status'];
                                        $chip_class = ($p_status === 'posted') ? 'chip-posted' : (($p_status === 'failed') ? 'chip-failed' : '');
                                    ?>
                                    <div>
                                        <span class="platform-chip <?php echo $chip_class; ?>">
                                            <span><?php echo $p_icon; ?></span>
                                            <span><?php echo htmlspecialchars($p_label); ?></span>
                                            <span style="font-size: 10px; opacity: 0.8;">(<?php echo ucfirst($p_status); ?>)</span>
                                        </span>
                                        <?php if ($p_status === 'failed' && !empty($pp['error_message'])): ?>
                                            <span class="error-tooltip">❌ <?php echo htmlspecialchars($pp['error_message']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="post-actions">
                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this post record?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                <button type="submit" class="btn-delete">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>

</html>