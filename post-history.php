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

// ---------- Pagination (10 at a time, buffer-loaded on scroll) ----------
$limit = 10;
$offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;
$is_ajax = (($_GET['ajax'] ?? '') === '1');

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

// Fetch one extra row past the limit so we know whether there's more to buffer-load
$sql .= " ORDER BY p.created_at DESC LIMIT " . intval($limit + 1) . " OFFSET " . intval($offset);

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

$has_more = count($posts) > $limit;
if ($has_more) {
    $posts = array_slice($posts, 0, $limit);
}

// Get platform metadata (real brand icons instead of emoji)
$platform_meta = [
    'facebook'  => ['icon' => 'https://cdn.simpleicons.org/facebook/1877F2', 'label' => 'Facebook'],
    'telegram'  => ['icon' => 'https://cdn.simpleicons.org/telegram/26A5E4', 'label' => 'Telegram'],
    'linkedin'  => ['icon' => 'https://cdn.simpleicons.org/linkedin/0A66C2', 'label' => 'LinkedIn'],
    'tiktok'    => ['icon' => 'https://cdn.simpleicons.org/tiktok/000000', 'label' => 'TikTok'],
    'instagram' => ['icon' => 'https://cdn.simpleicons.org/instagram/E4405F', 'label' => 'Instagram'],
];

// Helper to fetch platforms for each post
function getPostPlatforms($conn, $post_id) {
    $stmt = $conn->prepare("SELECT platform, platform_post_id, status, error_message, posted_at FROM post_platforms WHERE post_id = ?");
    $stmt->execute([$post_id]);
    return $stmt->fetchAll();
}

// Helper to fetch any additional media for a multi-image/video post
function getPostExtraMedia($conn, $post_id) {
    $stmt = $conn->prepare("SELECT mf.path, mf.type FROM post_extra_media pem JOIN media_files mf ON pem.media_id = mf.id WHERE pem.post_id = ? ORDER BY pem.id ASC");
    $stmt->execute([$post_id]);
    return $stmt->fetchAll();
}

// Renders a single post card. Shared by the full-page render and the AJAX
// buffered-load fragment so both stay in sync automatically.
function renderPostCard($conn, $post, $platform_meta) {
    $post_platforms = getPostPlatforms($conn, $post['id']);
    $extra_media = getPostExtraMedia($conn, $post['id']);

    $media_items = [['path' => $post['media_path'], 'type' => $post['media_type']]];
    foreach ($extra_media as $em) {
        $media_items[] = ['path' => $em['path'], 'type' => $em['type']];
    }
    $media_json = htmlspecialchars(json_encode($media_items), ENT_QUOTES);
    $extra_count = count($media_items) - 1;

    $status = $post['status'];
    $status_class = match ($status) {
        'posted' => 'badge-posted',
        'scheduled' => 'badge-scheduled',
        'failed' => 'badge-failed',
        default => 'badge-draft'
    };
    ?>
    <div class="post-card">
        <div class="media-preview" data-media="<?php echo $media_json; ?>" role="button" tabindex="0" aria-label="View post media">
            <?php if ($post['media_type'] === 'video'): ?>
                <video src="<?php echo htmlspecialchars($post['media_path']); ?>" muted playsinline></video>
                <span class="media-play-icon">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                </span>
            <?php else: ?>
                <img src="<?php echo htmlspecialchars($post['media_path']); ?>" alt="Post media" loading="lazy">
            <?php endif; ?>
            <?php if ($extra_count > 0): ?>
                <span class="media-count-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="14" height="14" rx="2"/><path d="M7 7V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-2"/></svg>
                    +<?php echo (int)$extra_count; ?>
                </span>
            <?php endif; ?>
            <span class="media-expand-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
            </span>
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
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                        Created: <?php echo date('M d, Y H:i', strtotime($post['created_at'])); ?>
                    </span>
                    <?php if ($post['scheduled_at']): ?>
                        <span>
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                            Scheduled for: <?php echo date('M d, Y H:i', strtotime($post['scheduled_at'])); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($post['published_at']): ?>
                        <span>
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.5-2 5-2 5s3.5-.5 5-2c.8-.8.8-2.2 0-3s-2.2-.8-3 0z"/><path d="M12 15l-3-3a22 22 0 0 1 8-8c2-1 5.5-1.5 5.5-1.5s-.5 3.5-1.5 5.5a22 22 0 0 1-8 8z"/></svg>
                            Published: <?php echo date('M d, Y H:i', strtotime($post['published_at'])); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="platform-list">
                    <strong class="platform-list-label">Target Platforms:</strong>
                    <?php foreach ($post_platforms as $pp): ?>
                        <?php 
                            $p_code = $pp['platform'];
                            $p_icon = $platform_meta[$p_code]['icon'] ?? null;
                            $p_label = $platform_meta[$p_code]['label'] ?? ucfirst($p_code);
                            $p_status = $pp['status'];
                            $chip_class = ($p_status === 'posted') ? 'chip-posted' : (($p_status === 'failed') ? 'chip-failed' : '');
                        ?>
                        <div>
                            <span class="platform-chip <?php echo $chip_class; ?>">
                                <?php if ($p_icon): ?>
                                    <img src="<?php echo htmlspecialchars($p_icon); ?>" alt="<?php echo htmlspecialchars($p_label); ?>">
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M8.5 14.5 3 20"/><path d="M15.5 14.5 21 20"/><path d="M12 3v8"/></svg>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($p_label); ?></span>
                                <span class="chip-substatus">(<?php echo ucfirst($p_status); ?>)</span>
                            </span>
                            <?php if ($p_status === 'failed' && !empty($pp['error_message'])): ?>
                                <span class="error-tooltip">
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/></svg>
                                    <?php echo htmlspecialchars($pp['error_message']); ?>
                                </span>
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
                    <button type="submit" class="btn-delete">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php
}

// ---------- AJAX buffered-load branch: return only the next batch of cards ----------
if ($is_ajax) {
    header('X-Has-More: ' . ($has_more ? '1' : '0'));
    header('Content-Type: text/html; charset=utf-8');
    foreach ($posts as $post) {
        renderPostCard($conn, $post, $platform_meta);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post History - Social Media Manager</title>
    <link rel="stylesheet" href="assets/css/post-history.css">
</head>

<body>

    <div class="app-shell">

        <!-- ===================== SIDEBAR OVERLAY (mobile) ===================== -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

        <!-- ===================== SIDEBAR ===================== -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div class="brand-mark">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2a10 10 0 1 0 10 10"/>
                        <path d="M12 6a6 6 0 1 0 6 6"/>
                        <circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/>
                    </svg>
                </div>
                <span>Social Manager</span>
                <button type="button" class="sidebar-close" onclick="closeSidebar()" aria-label="Close menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18"/>
                        <path d="M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="sidebar-section-label">Menu</div>
            <ul class="nav-list">
                <li>
                    <a href="dashboard.php" class="nav-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="9" rx="1.5"/>
                            <rect x="14" y="3" width="7" height="5" rx="1.5"/>
                            <rect x="14" y="12" width="7" height="9" rx="1.5"/>
                            <rect x="3" y="16" width="7" height="5" rx="1.5"/>
                        </svg>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="create-post.php" class="nav-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9"/>
                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                        </svg>
                        Create Post
                    </a>
                </li>
                <li>
                    <a href="settings.php" class="nav-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>
                        </svg>
                        Platform Settings
                    </a>
                </li>
                <li>
                    <a href="post-history.php" class="nav-item active">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18"/>
                            <rect x="7" y="12" width="3" height="6" rx="0.5"/>
                            <rect x="12.5" y="8" width="3" height="10" rx="0.5"/>
                            <rect x="18" y="5" width="3" height="13" rx="0.5"/>
                        </svg>
                        Post History
                    </a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <a href="logout.php" class="btn-logout">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <path d="M16 17l5-5-5-5"/>
                        <path d="M21 12H9"/>
                    </svg>
                    Logout
                </a>
            </div>
        </aside>

        <!-- ===================== MAIN CONTENT ===================== -->
        <main class="main">

            <div class="topbar">
                <div class="topbar-left">
                    <button type="button" class="hamburger-btn" onclick="openSidebar()" aria-label="Open menu">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"/>
                            <path d="M3 12h18"/>
                            <path d="M3 18h18"/>
                        </svg>
                    </button>
                    <div>
                        <h1>Post History</h1>
                        <a href="dashboard.php" class="back-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 12H5"/>
                                <path d="M12 19l-7-7 7-7"/>
                            </svg>
                            Back to Dashboard
                        </a>
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
                    <div class="empty-state-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/></svg>
                    </div>
                    <h3>No posts found</h3>
                    <p>You haven't created any posts matching this status yet.</p>
                    <a href="create-post.php" class="btn-create">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        Create a New Post
                    </a>
                </div>
            <?php else: ?>
                <div id="postsContainer">
                    <?php foreach ($posts as $post): ?>
                        <?php renderPostCard($conn, $post, $platform_meta); ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($has_more): ?>
                    <div id="scrollSentinel"></div>
                    <div id="loadingIndicator" class="loading-indicator" hidden>
                        <span class="spinner"></span> Loading more posts&hellip;
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </main>
    </div>

    <!-- ===================== LIGHTBOX ===================== -->
    <div class="lightbox-overlay" id="lightboxOverlay">
        <button type="button" class="lightbox-close" id="lightboxClose" aria-label="Close">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>
        </button>
        <button type="button" class="lightbox-nav lightbox-prev" id="lightboxPrev" aria-label="Previous">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <div class="lightbox-stage" id="lightboxStage"></div>
        <button type="button" class="lightbox-nav lightbox-next" id="lightboxNext" aria-label="Next">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </button>
        <div class="lightbox-dots" id="lightboxDots"></div>
    </div>

    <!-- ===== Sidebar toggle (mobile) ===== -->
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

    <!-- ===== Lightbox gallery ===== -->
    <script>
    (function() {
        let media = [];
        let index = 0;

        const overlay = document.getElementById('lightboxOverlay');
        const stage = document.getElementById('lightboxStage');
        const dotsWrap = document.getElementById('lightboxDots');
        const prevBtn = document.getElementById('lightboxPrev');
        const nextBtn = document.getElementById('lightboxNext');
        const closeBtn = document.getElementById('lightboxClose');
        const postsContainer = document.getElementById('postsContainer');

        function renderStage() {
            stage.innerHTML = '';
            const item = media[index];
            if (!item) return;

            if (item.type === 'video') {
                const v = document.createElement('video');
                v.src = item.path;
                v.controls = true;
                v.autoplay = true;
                stage.appendChild(v);
            } else {
                const img = document.createElement('img');
                img.src = item.path;
                img.alt = 'Post media';
                stage.appendChild(img);
            }

            const multi = media.length > 1;
            prevBtn.style.display = multi ? 'flex' : 'none';
            nextBtn.style.display = multi ? 'flex' : 'none';
            renderDots();
        }

        function renderDots() {
            dotsWrap.innerHTML = '';
            if (media.length <= 1) return;
            media.forEach((_, i) => {
                const dot = document.createElement('span');
                dot.className = 'lightbox-dot' + (i === index ? ' active' : '');
                dot.addEventListener('click', () => { index = i; renderStage(); });
                dotsWrap.appendChild(dot);
            });
        }

        function open(mediaItems, startIndex) {
            media = mediaItems;
            index = startIndex || 0;
            renderStage();
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function close() {
            overlay.classList.remove('open');
            stage.innerHTML = '';
            document.body.style.overflow = '';
        }

        prevBtn.addEventListener('click', () => { index = (index - 1 + media.length) % media.length; renderStage(); });
        nextBtn.addEventListener('click', () => { index = (index + 1) % media.length; renderStage(); });
        closeBtn.addEventListener('click', close);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
        document.addEventListener('keydown', (e) => {
            if (!overlay.classList.contains('open')) return;
            if (e.key === 'Escape') close();
            if (e.key === 'ArrowLeft') prevBtn.click();
            if (e.key === 'ArrowRight') nextBtn.click();
        });

        function bindPreviewClicks() {
            if (!postsContainer) return;
            postsContainer.addEventListener('click', (e) => {
                const preview = e.target.closest('.media-preview');
                if (!preview) return;
                const raw = preview.getAttribute('data-media');
                if (!raw) return;
                try {
                    open(JSON.parse(raw), 0);
                } catch (err) { /* ignore malformed data */ }
            });
            postsContainer.addEventListener('keydown', (e) => {
                const preview = e.target.closest('.media-preview');
                if (!preview) return;
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    preview.click();
                }
            });
        }

        bindPreviewClicks();
        window.__lightboxBindPreviewClicks = bindPreviewClicks;
    })();
    </script>

    <!-- ===== Buffered infinite scroll (10 at a time) ===== -->
    <?php if (!empty($posts)): ?>
    <script>
    (function() {
        let currentOffset = <?php echo (int) count($posts); ?>;
        let hasMore = <?php echo $has_more ? 'true' : 'false'; ?>;
        let isLoading = false;
        const currentStatus = "<?php echo htmlspecialchars($status_filter, ENT_QUOTES); ?>";

        const postsContainer = document.getElementById('postsContainer');
        const sentinel = document.getElementById('scrollSentinel');
        const loadingIndicator = document.getElementById('loadingIndicator');

        if (!sentinel || !postsContainer) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting && hasMore && !isLoading) {
                    loadMore();
                }
            });
        }, { rootMargin: '300px' });

        observer.observe(sentinel);

        function loadMore() {
            isLoading = true;
            if (loadingIndicator) loadingIndicator.hidden = false;

            fetch('post-history.php?status=' + encodeURIComponent(currentStatus) + '&offset=' + currentOffset + '&ajax=1', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then((res) => {
                hasMore = res.headers.get('X-Has-More') === '1';
                return res.text();
            })
            .then((html) => {
                const temp = document.createElement('div');
                temp.innerHTML = html;
                const newCards = temp.querySelectorAll('.post-card');
                newCards.forEach((card) => postsContainer.appendChild(card));
                currentOffset += newCards.length;
                isLoading = false;
                if (loadingIndicator) loadingIndicator.hidden = true;

                if (!hasMore) {
                    observer.disconnect();
                    sentinel.remove();
                }
            })
            .catch(() => {
                isLoading = false;
                if (loadingIndicator) loadingIndicator.hidden = true;
            });
        }
    })();
    </script>
    <?php endif; ?>
</body>

</html>