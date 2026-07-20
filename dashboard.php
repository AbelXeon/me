<?php
// dashboard.php
require_once 'includes/auth_check.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Social Media Manager</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>

<body>
    <div class="header">
        <h1>📱 Social Media Manager</h1>
        <div class="user-info">
            <span>Welcome, <strong><?php echo htmlspecialchars(getCurrentUsername()); ?></strong></span>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="welcome-card">
            <h2>Welcome to Your Social Media Dashboard!</h2>
            <p>Manage all your social media accounts from one place</p>
        </div>

        <div class="nav-cards">
            <a href="create-post.php" class="nav-card">
                <div class="icon">✍️</div>
                <h3>Create Post</h3>
                <p>Create and publish posts to multiple platforms</p>
            </a>

            <a href="settings.php" class="nav-card">
                <div class="icon">⚙️</div>
                <h3>Platform Settings</h3>
                <p>Connect your social media accounts</p>
            </a>

            <a href="post-history.php" class="nav-card">
                <div class="icon">📊</div>
                <h3>Post History</h3>
                <p>View all your published and scheduled posts</p>
            </a>
        </div>
    </div>
</body>

</html>