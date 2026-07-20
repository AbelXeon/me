<?php
// connect-platforms.php
require_once 'includes/auth_check.php';
require_once 'config/database.php';
requireLogin();

// Generate a random state token to prevent CSRF attacks
if (empty($_SESSION['oauth_state'])) {
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
}

$platform = $_GET['platform'] ?? 'tiktok';

if ($platform === 'tiktok') {
    $clientKey = getenv('TIKTOK_CLIENT_KEY');
    $redirectUri = getenv('TIKTOK_REDIRECT_URI');
    $state = $_SESSION['oauth_state'];
    
    // The specific scopes you requested in your TikTok developer portal
    $scopes = "user.info.basic,video.upload,video.publish";

    $authUrl = "https://www.tiktok.com/v2/auth/authorize/?" . http_build_query([
        'client_key'    => $clientKey,
        'scope'         => $scopes,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'state'         => $state
    ]);

    header("Location: " . $authUrl);
    exit();
} else {
    // If you add Facebook or LinkedIn later, their redirects will go here
    header("Location: settings.php?error=unsupported_platform");
    exit();
}