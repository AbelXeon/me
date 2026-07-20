<?php
// terms.php
require_once 'config/database.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - Social Media Manager</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; padding: 40px 20px; background: #f4f7f6; }
        .container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h1 { color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; }
        p { margin-bottom: 15px; }
        .back-link { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #4a90e2; font-weight: bold; }
        .footer { margin-top: 40px; font-size: 0.9em; color: #888; text-align: center; }
    </style>
</head>
<body>
    <a href="register.php" class="back-link">← Back to Registration</a>
    <div class="container">
        <h1>Terms of Service</h1>
        <p>Last Updated: <?php echo date('F j, Y'); ?></p>

        <h2>1. Acceptance of Terms</h2>
        <p>By creating an account and using this Social Media Manager platform, you agree to be bound by these Terms of Service and all applicable laws and regulations.</p>

        <h2>2. User Responsibilities</h2>
        <p>You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. You must provide your own Telegram Bot tokens and Channel IDs as required by the platform settings.</p>

        <h2>3. Content & Conduct</h2>
        <p>You retain all rights to the media (images and videos) and captions you upload. However, you agree not to use the service to post:</p>
        <ul>
            <li>Illegal or prohibited content.</li>
            <li>Spam, automated unwanted messages, or phishing links.</li>
            <li>Content that violates the Terms of Service of the social platforms you connect (e.g., Telegram, Facebook).</li>
        </ul>

        <h2>4. Platform Access</h2>
        <p>This tool uses third-party APIs (like Telegram). We are not responsible for any changes, downtime, or account suspensions imposed by these third-party platforms.</p>

        <h2>5. Limitation of Liability</h2>
        <p>The service is provided "as is." We do not guarantee that your posts will always be published at the exact scheduled time due to potential server or API delays. We shall not be liable for any data loss or account issues resulting from the use of this tool.</p>

        <h2>6. Termination</h2>
        <p>We reserve the right to suspend or terminate your account if you are found to be in violation of these terms.</p>
    </div>
    <div class="footer">
        &copy; <?php echo date('Y'); ?> Social Media Manager. All rights reserved.
    </div>
</body>
</html>