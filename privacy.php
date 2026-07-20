<?php
// privacy.php
require_once 'config/database.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Social Media Manager</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; padding: 40px 20px; background: #f4f7f6; }
        .container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h1 { color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; }
        .back-link { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #4a90e2; font-weight: bold; }
        .footer { margin-top: 40px; font-size: 0.9em; color: #888; text-align: center; }
    </style>
</head>
<body>
    <a href="register.php" class="back-link">← Back to Registration</a>
    <div class="container">
        <h1>Privacy Policy</h1>
        <p>Last Updated: <?php echo date('F j, Y'); ?></p>

        <h2>1. Information We Collect</h2>
        <p>We collect information you provide directly to us when you create an account, such as your name, email address, and profile picture.</p>

        <h2>2. Social Media Data</h2>
        <p>To provide our services, we store your social media connection details, including Telegram Bot Tokens and Channel IDs. This information is used strictly to publish content on your behalf at your request.</p>

        <h2>3. Media Storage</h2>
        <p>Images and videos you upload for posting are stored on our server in the <code>uploads/posts/</code> directory. These files are kept until you delete the associated post or your account.</p>

        <h2>4. Data Usage</h2>
        <p>We use your data to:</p>
        <ul>
            <li>Maintain your user account and profile.</li>
            <li>Schedule and publish posts to your connected platforms.</li>
            <li>Provide technical support and improve the service.</li>
        </ul>

        <h2>5. Security</h2>
        <p>We implement security measures including database encryption keys and password hashing to protect your information. However, please remember that no method of electronic storage is 100% secure.</p>

        <h2>6. Third-Party Sharing</h2>
        <p>We do not sell your data. Your content is only shared with the third-party platforms you explicitly choose to connect (e.g., sending data to Telegram's API to fulfill a post request).</p>

        <h2>7. Your Rights</h2>
        <p>You can view, edit, or delete your account information at any time through the Settings page. Upon account deletion, your profile data and uploaded media will be removed from our active database.</p>
    </div>
    <div class="footer">
        &copy; <?php echo date('Y'); ?> Social Media Manager. All rights reserved.
    </div>
</body>
</html>