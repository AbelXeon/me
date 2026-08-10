<?php
// post-history.php
require_once 'includes/auth_check.php';
require_once 'config/database.php';
requireLogin();

$user_id = getCurrentUserId();
$conn = getDBConnection();

$status_filter = $_GET['status'] ?? 'all';

$action_msg = '';
$action_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $action_error = 'Invalid submission.';
    } else {
        $delete_post_id = intval($_POST['post_id'] ?? 0);
        $stmt = $conn->prepare("SELECT p.id, m.path FROM posts p JOIN media_files m ON p.media_id = m.id WHERE p.id = ? AND p.user_id = ?");
        $stmt->execute([$delete_post_id, $user_id]);
        $post_to_delete = $stmt->fetch();

        if ($post_to_delete) {
            try {
                $conn->beginTransaction();
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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

$platform_meta = [
    'facebook'  => ['icon' => 'https://cdn.simpleicons.org/facebook/1877F2', 'label' => 'Facebook'],
    'instagram' => ['icon' => 'https://cdn.simpleicons.org/instagram/E4405F', 'label' => 'Instagram'],
    'telegram'  => ['icon' => 'https://cdn.simpleicons.org/telegram/26A5E4', 'label' => 'Telegram'],
    'linkedin'  => ['icon' => 'https://cdn.simpleicons.org/linkedin/0A66C2', 'label' => 'LinkedIn'],
    'tiktok'    => ['icon' => 'https://cdn.simpleicons.org/tiktok/000000', 'label' => 'TikTok'],
];

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
    <link rel="stylesheet" href="assets/css/create-post.css"> <!-- Reusing your existing CSS variables -->
    <style>
        /* Specific enhancements for Post History */
        .filter-nav {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            overflow-x: auto;
            padding-bottom: 8px;
        }
        .filter-link {
            padding: 8px 16px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 100px;
            font-size: 13px;
            font-weight: 600;
            color: var(--slate);
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        .filter-link:hover { border-color: var(--accent); color: var(--accent); }
        .filter-link.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        .post-card-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .history-card {
            display: flex;
            gap: 24px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-card);
            transition: transform 0.2s ease;
        }
        .history-media {
            width: 180px;
            height: 180px;
            flex-shrink: 0;
            border-radius: var(--radius-md);
            overflow: hidden;
            background: var(--ink);
            position: relative;
        }
        .history-media img, .history-media video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .history-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .status-badge {
            display: inline-flex;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
        }
        .status-posted { background: var(--success-soft); color: var(--success); }
        .status-scheduled { background: var(--warning-soft); color: var(--warning); }
        .status-failed { background: var(--danger-soft); color: var(--danger); }
        .status-draft { background: var(--bg); color: var(--slate); }

        .history-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .history-caption {
            font-size: 14px;
            color: var(--ink-soft);
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 16px;
        }
        .platform-mini-list {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid var(--line);
        }
        .platform-status-chip {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 500;
            color: var(--ink-soft);
        }
        .platform-status-chip img {
            width: 16px;
            height: 16px;
        }
        .platform-status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--line);
        }
        .empty-state svg {
            width: 48px;
            height: 48px;
            color: var(--slate);
            margin-bottom: 16px;
            opacity: 0.5;
        }

        @media (max-width: 650px) {
            .history-card { flex-direction: column; }
            .history-media { width: 100%; height: 200px; }
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

        <!-- SIDEBAR -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div class="brand-mark">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2a10 10 0 1 0 10 10"/><path d="M12 6a6 6 0 1 0 6 6"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/>
                    </svg>
                </div>
                <span>Social Manager</span>
                <button type="button" class="sidebar-close" onclick="closeSidebar()">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="sidebar-section-label">Menu</div>
            <ul class="nav-list">
                <li><a href="dashboard.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
                    Dashboard</a></li>
                <li><a href="create-post.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    Create Post</a></li>
                <li><a href="post-history.php" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6" rx="0.5"/><rect x="12.5" y="8" width="3" height="10" rx="0.5"/><rect x="18" y="5" width="3" height="13" rx="0.5"/></svg>
                    Post History</a></li>
            </ul>
            <div class="sidebar-footer">
                <a href="logout.php" class="btn-logout">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                    Logout</a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main">
            <div class="topbar">
                <div class="topbar-left">
                    <button type="button" class="hamburger-btn" onclick="openSidebar()">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/></svg>
                    </button>
                    <div>
                        <h1>Post History</h1>
                        <span class="back-btn">Review your social activity</span>
                    </div>
                </div>
                <div class="user-info">
                    <span class="welcome">Welcome, <strong><?php echo htmlspecialchars(getCurrentUsername()); ?></strong></span>
                </div>
            </div>

            <?php if ($action_msg): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($action_msg); ?></div>
            <?php endif; ?>
            <?php if ($action_error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($action_error); ?></div>
            <?php endif; ?>

            <div class="filter-nav">
                <a href="?status=all" class="filter-link <?php echo $status_filter === 'all' ? 'active' : ''; ?>">All Posts</a>
                <a href="?status=posted" class="filter-link <?php echo $status_filter === 'posted' ? 'active' : ''; ?>">Published</a>
                <a href="?status=scheduled" class="filter-link <?php echo $status_filter === 'scheduled' ? 'active' : ''; ?>">Scheduled</a>
                <a href="?status=draft" class="filter-link <?php echo $status_filter === 'draft' ? 'active' : ''; ?>">Drafts</a>
                <a href="?status=failed" class="filter-link <?php echo $status_filter === 'failed' ? 'active' : ''; ?>">Failed</a>
            </div>

            <?php if (empty($posts)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <h3>No posts found</h3>
                    <p>Try changing your filter or create something new.</p>
                    <br>
                    <a href="create-post.php" class="btn-primary" style="padding: 10px 24px; text-decoration:none;">Create New Post</a>
                </div>
            <?php else: ?>
                <div class="post-card-grid">
                    <?php foreach ($posts as $post): ?>
                        <?php 
                            $post_platforms = getPostPlatforms($conn, $post['id']); 
                            $status = $post['status'];
                        ?>
                        <div class="history-card">
                            <div class="history-media">
                                <?php if ($post['media_type'] === 'video'): ?>
                                    <video src="<?php echo htmlspecialchars($post['media_path']); ?>"></video>
                                    <span class="media-preview-badge">Video</span>
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($post['media_path']); ?>" alt="Media">
                                <?php endif; ?>
                            </div>

                            <div class="history-content">
                                <div class="status-badge status-<?php echo $status; ?>">
                                    <?php echo htmlspecialchars($status); ?>
                                </div>
                                
                                <div class="history-title"><?php echo htmlspecialchars($post['title'] ?: 'Untitled Post'); ?></div>
                                <div class="history-caption"><?php echo htmlspecialchars($post['caption']); ?></div>
                                
                                <div class="post-meta" style="margin-bottom:0;">
                                    <span style="display:flex; align-items:center; gap:4px;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                        <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                                    </span>
                                </div>

                                <div class="platform-mini-list">
                                    <?php foreach ($post_platforms as $pp): ?>
                                        <?php 
                                            $p_code = $pp['platform'];
                                            $p_icon = $platform_meta[$p_code]['icon'] ?? '';
                                            $p_status = $pp['status'];
                                            $dot_color = ($p_status === 'posted') ? 'var(--success)' : (($p_status === 'failed') ? 'var(--danger)' : 'var(--slate)');
                                        ?>
                                        <div class="platform-status-chip">
                                            <img src="<?php echo $p_icon; ?>" alt="">
                                            <span class="platform-status-dot" style="background:<?php echo $dot_color; ?>"></span>
                                            <?php if($p_status === 'failed'): ?>
                                                <span style="color:var(--danger); font-size:10px;">Error</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>

                                    <div style="margin-left: auto;">
                                        <form method="POST" action="" onsubmit="return confirm('Delete this post record?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                            <button type="submit" class="btn-clear-schedule">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function openSidebar() {
            document.getElementById('sidebar').classList.add('open');
            document.getElementById('sidebarOverlay').classList.add('open');
        }
        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebarOverlay').classList.remove('open');
        }
    </script>
</body>
</html>