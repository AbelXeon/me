<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/auth_check.php';
require_once 'config/database.php';
requireLogin();

$user_id = getCurrentUserId();
$conn = getDBConnection();

// ---------- Post counts by status ----------
$statusCounts = ['draft' => 0, 'scheduled' => 0, 'posted' => 0, 'failed' => 0];
$stmt = $conn->prepare("SELECT status, COUNT(*) as cnt FROM posts WHERE user_id = ? GROUP BY status");
$stmt->execute([$user_id]);
while ($row = $stmt->fetch()) {
    if (isset($statusCounts[$row['status']])) {
        $statusCounts[$row['status']] = (int) $row['cnt'];
    }
}
$totalPosts = array_sum($statusCounts);

// ---------- Connected platforms ----------
$connectedPlatforms = [];
$stmt = $conn->prepare("SELECT platform FROM social_accounts WHERE user_id = ? AND status = 1");
$stmt->execute([$user_id]);
while ($row = $stmt->fetch()) {
    $connectedPlatforms[] = $row['platform'];
}
$connectedCount = count($connectedPlatforms);

// ---------- Posting activity, last 14 days ----------
$activityDays = [];
for ($i = 13; $i >= 0; $i--) {
    $activityDays[date('Y-m-d', strtotime("-{$i} days"))] = 0;
}
$cutoff = date('Y-m-d 00:00:00', strtotime('-13 days'));
$stmt = $conn->prepare("SELECT created_at FROM posts WHERE user_id = ? AND created_at >= ?");
$stmt->execute([$user_id, $cutoff]);
while ($row = $stmt->fetch()) {
    $day = substr($row['created_at'], 0, 10);
    if (isset($activityDays[$day])) {
        $activityDays[$day]++;
    }
}
$maxDayCount = max(1, max($activityDays));

// ---------- Posts sent per platform ----------
$platformCounts = ['facebook' => 0, 'instagram' => 0, 'telegram' => 0, 'linkedin' => 0, 'tiktok' => 0];
$stmt = $conn->prepare("SELECT pp.platform, COUNT(*) as cnt FROM post_platforms pp JOIN posts p ON pp.post_id = p.id WHERE p.user_id = ? GROUP BY pp.platform");
$stmt->execute([$user_id]);
while ($row = $stmt->fetch()) {
    if (isset($platformCounts[$row['platform']])) {
        $platformCounts[$row['platform']] = (int) $row['cnt'];
    }
}
$maxPlatformCount = max(1, max($platformCounts));

// ---------- Recent activity (last 5 posts) ----------
$stmt = $conn->prepare("
    SELECT p.id, p.caption, p.title, p.status, p.media_type, p.created_at, m.path as media_path
    FROM posts p
    JOIN media_files m ON p.media_id = m.id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recentPosts = $stmt->fetchAll();

function getDashboardPostPlatforms($conn, $post_id) {
    $stmt = $conn->prepare("SELECT platform, status FROM post_platforms WHERE post_id = ?");
    $stmt->execute([$post_id]);
    return $stmt->fetchAll();
}

$platform_meta = [
    'facebook'  => ['icon' => 'https://cdn.simpleicons.org/facebook/1877F2', 'label' => 'Facebook'],
    'instagram' => ['icon' => 'https://cdn.simpleicons.org/instagram/E4405F', 'label' => 'Instagram'],
    'telegram'  => ['icon' => 'https://cdn.simpleicons.org/telegram/26A5E4', 'label' => 'Telegram'],
    'linkedin'  => ['icon' => 'https://img.icons8.com/color/48/linkedin.png', 'label' => 'LinkedIn'],
    'tiktok'    => ['icon' => 'https://cdn.simpleicons.org/tiktok/000000', 'label' => 'TikTok'],
];

$statusBadgeClass = [
    'posted' => 'badge-posted',
    'scheduled' => 'badge-scheduled',
    'failed' => 'badge-failed',
    'draft' => 'badge-draft',
];

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$pageCss = 'assets/css/dashboard.css';
$topbarTitle = 'Dashboard Overview';
$showBackBtn = false;
require_once 'includes/layout_header.php';
?>

            <!-- ===== Stat cards ===== -->
            <div class="stat-grid">
                <div class="stat-card">
                    <span class="stat-label">Total Posts</span>
                    <span class="stat-value"><?php echo $totalPosts; ?></span>
                    <span class="stat-sub"><?php echo $statusCounts['draft']; ?> draft<?php echo $statusCounts['draft'] === 1 ? '' : 's'; ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Published</span>
                    <span class="stat-value stat-value-success"><?php echo $statusCounts['posted']; ?></span>
                    <span class="stat-sub">Sent to at least one platform</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Scheduled</span>
                    <span class="stat-value stat-value-warning"><?php echo $statusCounts['scheduled']; ?></span>
                    <span class="stat-sub">Waiting to publish</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Connected Platforms</span>
                    <span class="stat-value"><?php echo $connectedCount; ?><span class="stat-value-of">/5</span></span>
                    <span class="stat-sub"><a href="settings.php">Manage connections &rarr;</a></span>
                </div>
            </div>

            <!-- ===== Chart + platform breakdown ===== -->
            <div class="dash-grid">
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h3>Posting activity</h3>
                        <span class="dash-card-hint">Last 14 days</span>
                    </div>

                    <?php if ($totalPosts === 0): ?>
                        <div class="dash-empty">No posts yet — your activity will show up here once you create one.</div>
                    <?php else: ?>
                        <div class="activity-chart">
                            <?php foreach ($activityDays as $day => $count): ?>
                                <?php
                                    $heightPct = max(4, round(($count / $maxDayCount) * 100));
                                    $label = date('M j', strtotime($day));
                                ?>
                                <div class="activity-bar-wrap" title="<?php echo htmlspecialchars($label . ': ' . $count . ' post' . ($count === 1 ? '' : 's')); ?>">
                                    <div class="activity-bar" style="height: <?php echo $heightPct; ?>%;"><span class="activity-bar-count"><?php echo $count; ?></span></div>
                                    <span class="activity-bar-label"><?php echo date('j', strtotime($day)); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dash-card">
                    <div class="dash-card-header">
                        <h3>By platform</h3>
                        <span class="dash-card-hint">All-time</span>
                    </div>

                    <div class="platform-breakdown">
                        <?php foreach ($platform_meta as $code => $meta): ?>
                            <?php $count = $platformCounts[$code]; $pct = max(3, round(($count / $maxPlatformCount) * 100)); ?>
                            <div class="pb-row">
                                <img src="<?php echo htmlspecialchars($meta['icon']); ?>" alt="<?php echo htmlspecialchars($meta['label']); ?>" class="pb-icon">
                                <span class="pb-label"><?php echo htmlspecialchars($meta['label']); ?></span>
                                <div class="pb-track"><div class="pb-fill" style="width: <?php echo $pct; ?>%;"></div></div>
                                <span class="pb-count"><?php echo $count; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- ===== Recent activity ===== -->
            <div class="dash-card">
                <div class="dash-card-header">
                    <h3>Recent activity</h3>
                    <a href="post-history.php" class="dash-card-link">View all &rarr;</a>
                </div>

                <?php if (empty($recentPosts)): ?>
                    <div class="dash-empty">Nothing here yet. <a href="create-post.php">Create your first post &rarr;</a></div>
                <?php else: ?>
                    <div class="recent-list">
                        <?php foreach ($recentPosts as $post): ?>
                            <?php $postPlatforms = getDashboardPostPlatforms($conn, $post['id']); ?>
                            <div class="recent-row">
                                <div class="recent-thumb">
                                    <?php if ($post['media_type'] === 'video'): ?>
                                        <video src="<?php echo htmlspecialchars($post['media_path']); ?>" muted></video>
                                    <?php else: ?>
                                        <img src="<?php echo htmlspecialchars($post['media_path']); ?>" alt="" loading="lazy">
                                    <?php endif; ?>
                                </div>
                                <div class="recent-body">
                                    <div class="recent-top">
                                        <span class="recent-title"><?php echo htmlspecialchars($post['title'] ?: 'Untitled Post'); ?></span>
                                        <span class="badge <?php echo $statusBadgeClass[$post['status']] ?? 'badge-draft'; ?>"><?php echo htmlspecialchars(ucfirst($post['status'])); ?></span>
                                    </div>
                                    <p class="recent-caption"><?php echo htmlspecialchars(mb_strimwidth($post['caption'], 0, 110, '...')); ?></p>
                                    <div class="recent-meta">
                                        <span class="recent-date"><?php echo date('M j, Y \a\t H:i', strtotime($post['created_at'])); ?></span>
                                        <span class="recent-platforms">
                                            <?php foreach ($postPlatforms as $pp): ?>
                                                <?php if (isset($platform_meta[$pp['platform']])): ?>
                                                    <img src="<?php echo htmlspecialchars($platform_meta[$pp['platform']]['icon']); ?>" alt="<?php echo htmlspecialchars($platform_meta[$pp['platform']]['label']); ?>" title="<?php echo htmlspecialchars($platform_meta[$pp['platform']]['label'] . ' — ' . ucfirst($pp['status'])); ?>">
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ===== Quick actions ===== -->
            <div class="nav-cards">
                <a href="create-post.php" class="nav-card">
                    <div class="icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9"/>
                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                        </svg>
                    </div>
                    <h3>Create Post</h3>
                    <p>Create and publish posts to multiple platforms</p>
                </a>
                <a href="settings.php" class="nav-card">
                    <div class="icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>
                        </svg>
                    </div>
                    <h3>Platform Settings</h3>
                    <p>Connect your social media accounts</p>
                </a>
                <a href="post-history.php" class="nav-card">
                    <div class="icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18"/>
                            <rect x="7" y="12" width="3" height="6" rx="0.5"/>
                            <rect x="12.5" y="8" width="3" height="10" rx="0.5"/>
                            <rect x="18" y="5" width="3" height="13" rx="0.5"/>
                        </svg>
                    </div>
                    <h3>Post History</h3>
                    <p>View all your published and scheduled posts</p>
                </a>
            </div>

<?php require_once 'includes/layout_footer.php'; ?>