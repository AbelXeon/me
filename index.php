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
    <title>Social Media Manager</title>
        <link rel="stylesheet" href="assets/css/index.css">

</head>
<body>

    <div class="hero-container">
        <div class="welcome-card">
            <div class="logo-icon">📱</div>
            <h1>Social Manager</h1>
            <p>A simple way to schedule, coordinate, and automatically publish posts across your connected platforms.</p>
            
            <div class="btn-group">
                <a href="login.php" class="btn btn-primary">Login to Your Account</a>
                <a href="register.php" class="btn btn-secondary">Create a New Account</a>
            </div>
        </div>
    </div>

    <footer>
        <p>
            &copy; <?php echo date('Y'); ?> Social Media Manager. All rights reserved.
            <br>
            <a href="terms.php">Terms of Service</a> | <a href="privacy.php">Privacy Policy</a>
        </p>
    </footer>

</body>
</html>