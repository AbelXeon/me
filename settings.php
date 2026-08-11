<?php
// settings.php
require_once 'includes/auth_check.php';
require_once 'config/database.php';
requireLogin();

$user_id = getCurrentUserId();
$conn = getDBConnection();
$error = '';
$success = '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Telegram manual connect/disconnect (the one platform without OAuth)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } elseif ($_POST['action'] === 'save_telegram') {
        $account_name = trim($_POST['account_name'] ?? '');
        $bot_token = trim($_POST['access_token'] ?? '');
        $channel_id = trim($_POST['platform_user_id'] ?? '');

        if (empty($account_name) || empty($bot_token) || empty($channel_id)) {
            $error = 'Please fill in all Telegram fields';
        } else {
            // Test the connection before saving
            $test_url = "https://api.telegram.org/bot{$bot_token}/getMe";
            $test_response = @file_get_contents($test_url);
            $test_data = $test_response ? json_decode($test_response, true) : null;

            if (!$test_data || !($test_data['ok'] ?? false)) {
                $error = 'Could not verify this bot token with Telegram. Double check it and try again.';
            } else {
                $stmt = $conn->prepare("SELECT id FROM social_accounts WHERE user_id = ? AND platform = 'telegram'");
                $stmt->execute([$user_id]);

                if ($stmt->fetch()) {
                    $stmt = $conn->prepare("UPDATE social_accounts SET account_name=?, access_token=?, platform_user_id=?, status=1, connected_at=CURRENT_TIMESTAMP WHERE user_id=? AND platform='telegram'");
                    $stmt->execute([$account_name, $bot_token, $channel_id, $user_id]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO social_accounts (user_id, platform, account_name, access_token, platform_user_id, status) VALUES (?, 'telegram', ?, ?, ?, 1)");
                    $stmt->execute([$user_id, $account_name, $bot_token, $channel_id]);
                }
                $success = 'Telegram connected successfully!';
            }
        }
    } elseif ($_POST['action'] === 'disconnect') {
        $platform = $_POST['platform'] ?? '';
        $stmt = $conn->prepare("DELETE FROM social_accounts WHERE user_id = ? AND platform = ?");
        $stmt->execute([$user_id, $platform]);
        $success = ucfirst($platform) . ' disconnected.';
    }
}

// Get all connected accounts for this user
$stmt = $conn->prepare("SELECT * FROM social_accounts WHERE user_id = ? AND status = 1");
$stmt->execute([$user_id]);
$accounts = [];
while ($row = $stmt->fetch()) {
    $accounts[$row['platform']] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Settings - Social Media Manager</title>
    <link rel="stylesheet" href="assets/css/settings.css">
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
                    <a href="settings.php" class="nav-item active">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>
                        </svg>
                        Platform Settings
                    </a>
                </li>
                <li>
                    <a href="post-history.php" class="nav-item">
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
                        <h1>Platform Settings</h1>
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

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Facebook -->
            <div class="platform-card">
                <div class="platform-header">
                    <div class="platform-info">
                        <div class="platform-icon facebook-icon">
                            <img src="https://cdn.simpleicons.org/facebook/1877F2" alt="Facebook">
                            <img src="https://cdn.simpleicons.org/instagram/E4405F" alt="Instagram" class="platform-icon-secondary">
                        </div>
                        <div>
                            <div class="platform-name">Facebook &amp; Instagram</div>
                            <div class="platform-status <?php echo isset($accounts['facebook']) ? 'status-connected' : 'status-disconnected'; ?>">
                                <?php if (isset($accounts['facebook'])): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 5-5"/></svg>
                                    Connected as <?php echo htmlspecialchars($accounts['facebook']['account_name']); ?>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5l5 5"/><path d="M14.5 9.5l-5 5"/></svg>
                                    Not Connected
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if (isset($accounts['facebook'])): ?>
                        <form method="POST" action="" onsubmit="return confirm('Disconnect Facebook?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="disconnect">
                            <input type="hidden" name="platform" value="facebook">
                            <button type="submit" class="btn btn-danger">Disconnect</button>
                        </form>
                    <?php else: ?>
                        <!-- FIXED: Added platform=facebook parameter below -->
                        <a href="connect-platforms.php?platform=facebook" class="btn btn-primary">Connect</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Telegram (manual, no OAuth exists for it) -->
            <div class="platform-card">
                <div class="platform-header" onclick="togglePlatform('telegram')">
                    <div class="platform-info">
                        <div class="platform-icon telegram-icon">
                            <img src="https://cdn.simpleicons.org/telegram/26A5E4" alt="Telegram">
                        </div>
                        <div>
                            <div class="platform-name">Telegram</div>
                            <div class="platform-status <?php echo isset($accounts['telegram']) ? 'status-connected' : 'status-disconnected'; ?>">
                                <?php if (isset($accounts['telegram'])): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 5-5"/></svg>
                                    Connected as <?php echo htmlspecialchars($accounts['telegram']['account_name']); ?>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5l5 5"/><path d="M14.5 9.5l-5 5"/></svg>
                                    Not Connected
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <span class="chevron">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </span>
                </div>
                <div class="platform-body" id="telegram-body" data-connected="<?php echo isset($accounts['telegram']) ? '1' : '0'; ?>">
                    <div class="setup-instructions">
                        <h4>How to connect Telegram:</h4>
                        <ol>
                            <li>Open Telegram, search <strong>@BotFather</strong>, send <code>/newbot</code></li>
                            <li>Copy the Bot Token it gives you</li>
                            <li>Create/open your channel &rarr; Administrators &rarr; add your bot as Admin</li>
                            <li>Forward any message from your channel to <strong>@userinfobot</strong> to get your Channel ID</li>
                        </ol>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="save_telegram">

                        <div class="form-group">
                            <label>Channel Name</label>
                            <input type="text" name="account_name" placeholder="My Telegram Channel"
                                value="<?php echo htmlspecialchars($accounts['telegram']['account_name'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Bot Token</label>
                            <input type="text" name="access_token" placeholder="123456789:ABCdef..."
                                value="<?php echo htmlspecialchars($accounts['telegram']['access_token'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Channel ID</label>
                            <input type="text" name="platform_user_id" placeholder="-100123456789"
                                value="<?php echo htmlspecialchars($accounts['telegram']['platform_user_id'] ?? ''); ?>" required>
                        </div>

                        <button type="submit" class="btn-primary">Save Telegram</button>
                    </form>

                    <?php if (isset($accounts['telegram'])): ?>
                        <form method="POST" action="" onsubmit="return confirm('Disconnect Telegram?');" style="margin-top: 10px;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="disconnect">
                            <input type="hidden" name="platform" value="telegram">
                            <button type="submit" class="btn btn-danger">Disconnect</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- LinkedIn -->
            <div class="platform-card">
                <div class="platform-header">
                    <div class="platform-info">
                        <div class="platform-icon linkedin-icon">
                            <img src="https://cdn.simpleicons.org/linkedin/0A66C2" alt="LinkedIn">
                        </div>
                        <div>
                            <div class="platform-name">LinkedIn</div>
                            <div class="platform-status <?php echo isset($accounts['linkedin']) ? 'status-connected' : 'status-disconnected'; ?>">
                                <?php if (isset($accounts['linkedin'])): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 5-5"/></svg>
                                    Connected as <?php echo htmlspecialchars($accounts['linkedin']['account_name']); ?>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5l5 5"/><path d="M14.5 9.5l-5 5"/></svg>
                                    Not Connected
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if (isset($accounts['linkedin'])): ?>
                        <form method="POST" action="" onsubmit="return confirm('Disconnect LinkedIn?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="disconnect">
                            <input type="hidden" name="platform" value="linkedin">
                            <button type="submit" class="btn btn-danger">Disconnect</button>
                        </form>
                    <?php else: ?>
                        <!-- FIXED: Added platform=linkedin parameter below -->
                        <a href="connect-platforms.php?platform=linkedin" class="btn btn-primary">Connect</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TikTok -->
            <div class="platform-card">
                <div class="platform-header">
                    <div class="platform-info">
                        <div class="platform-icon tiktok-icon">
                            <img src="https://cdn.simpleicons.org/tiktok/000000" alt="TikTok">
                        </div>
                        <div>
                            <div class="platform-name">TikTok</div>
                            <div class="platform-status <?php echo isset($accounts['tiktok']) ? 'status-connected' : 'status-disconnected'; ?>">
                                <?php if (isset($accounts['tiktok'])): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 5-5"/></svg>
                                    Connected as <?php echo htmlspecialchars($accounts['tiktok']['account_name']); ?>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5l5 5"/><path d="M14.5 9.5l-5 5"/></svg>
                                    Not Connected
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if (isset($accounts['tiktok'])): ?>
                        <form method="POST" action="" onsubmit="return confirm('Disconnect TikTok?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="disconnect">
                            <input type="hidden" name="platform" value="tiktok">
                            <button type="submit" class="btn btn-danger">Disconnect</button>
                        </form>
                    <?php else: ?>
                        <!-- FIXED: Added platform=tiktok parameter below -->
                        <a href="connect-platforms.php?platform=tiktok" class="btn btn-primary">Connect</a>
                    <?php endif; ?>
                </div>
            </div>

        </main>
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

    <script src="assets/js/settings.js"></script>

</body>

</html>