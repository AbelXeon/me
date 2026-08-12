<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/auth_check.php';
requireLogin();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$pageCss = 'assets/css/dashboard.css';
$topbarTitle = 'Dashboard Overview';
$showBackBtn = false;
require_once 'includes/layout_header.php';
?>

            <!-- Overview: coming soon -->
            <div class="overview-panel">
                <h2>Coming soon</h2>
            </div>

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