<?php
// index.php
session_start();

// If the user is already logged in, redirect them to the dashboard directly
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Postbridge - Bridge Your Social Media</title>
    <link rel="stylesheet" href="assets/css/index.css">
</head>
<body>

    <div class="hero-section">
        <div class="welcome-card">
            <div class="logo-icon">🌉</div>
            <h1>Postbridge</h1>
            <p>The ultimate bridge between your content and your audience. Schedule and publish to all your platforms at once.</p>
            
            <div class="btn-group">
                <a href="login.php" class="btn btn-primary">Login to Your Account</a>
                <a href="register.php" class="btn btn-secondary">Create a New Account</a>
            </div>
        </div>
    </div>

    <div class="info-section">
        <h2>How it Works</h2>
        <div class="steps-container">
            <div class="step">
                <div class="step-icon">🔌</div>
                <h3>1. Connect</h3>
                <p>Link your Facebook, Instagram, Telegram, and more. <a href="how-to-connect.php">Learn how to connect &rarr;</a></p>
            </div>
            <div class="step">
                <div class="step-icon">✍️</div>
                <h3>2. Create</h3>
                <p>Craft your post, upload images or videos, and write your captions in one simple editor.</p>
            </div>
            <div class="step">
                <div class="step-icon">🚀</div>
                <h3>3. Bridge</h3>
                <p>Click publish to send your post to all platforms instantly, or schedule it for the perfect time.</p>
            </div>
        </div>
    </div>

    <footer>
        <p>
            &copy; <?php echo date('Y'); ?> Postbridge. All rights reserved.
            <br>
            <a href="terms.php">Terms of Service</a> | <a href="privacy.php">Privacy Policy</a> | <a href="how-to-connect.php">Connection Guide</a>
        </p>
    </footer>

</body>
</html>