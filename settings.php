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
    <div class="header">
        <div class="header-left">
            <h1>⚙️ Platform Settings</h1>
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        <div class="user-info">
            <span>👤 <?php echo htmlspecialchars(getCurrentUsername()); ?></span>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
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
                    <div class="platform-icon facebook-icon">📘</div>
                    <div>
                        <div class="platform-name">Facebook & Instagram</div>
                        <div class="platform-status <?php echo isset($accounts['facebook']) ? 'status-connected' : 'status-disconnected'; ?>">
                            <?php echo isset($accounts['facebook']) ? '✅ Connected as ' . htmlspecialchars($accounts['facebook']['account_name']) : '❌ Not Connected'; ?>
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
                    <div class="platform-icon telegram-icon">✈️</div>
                    <div>
                        <div class="platform-name">Telegram</div>
                        <div class="platform-status <?php echo isset($accounts['telegram']) ? 'status-connected' : 'status-disconnected'; ?>">
                            <?php echo isset($accounts['telegram']) ? '✅ Connected as ' . htmlspecialchars($accounts['telegram']['account_name']) : '❌ Not Connected'; ?>
                        </div>
                    </div>
                </div>
                <span class="chevron">▼</span>
            </div>
            <div class="platform-body" id="telegram-body" data-connected="<?php echo isset($accounts['telegram']) ? '1' : '0'; ?>">
                <div class="setup-instructions">
                    <h4>How to connect Telegram:</h4>
                    <ol>
                        <li>Open Telegram, search <strong>@BotFather</strong>, send <code>/newbot</code></li>
                        <li>Copy the Bot Token it gives you</li>
                        <li>Create/open your channel → Administrators → add your bot as Admin</li>
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
                    <div class="platform-icon linkedin-icon">💼</div>
                    <div>
                        <div class="platform-name">LinkedIn</div>
                        <div class="platform-status <?php echo isset($accounts['linkedin']) ? 'status-connected' : 'status-disconnected'; ?>">
                            <?php echo isset($accounts['linkedin']) ? '✅ Connected as ' . htmlspecialchars($accounts['linkedin']['account_name']) : '❌ Not Connected'; ?>
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
                    <div class="platform-icon tiktok-icon">🎵</div>
                    <div>
                        <div class="platform-name">TikTok</div>
                        <div class="platform-status <?php echo isset($accounts['tiktok']) ? 'status-connected' : 'status-disconnected'; ?>">
                            <?php echo isset($accounts['tiktok']) ? '✅ Connected as ' . htmlspecialchars($accounts['tiktok']['account_name']) : '❌ Not Connected'; ?>
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
    </div>
<script src="assets/js/settings.js"></script>
    
</body>

</html>