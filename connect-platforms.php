<?php
// connect-platforms.php
require_once 'includes/auth_check.php';
require_once 'config/database.php';
requireLogin();

if (empty($_SESSION['oauth_state'])) {
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
}

$platform = $_GET['platform'] ?? '';

if ($platform === 'tiktok') {
    $clientKey = getenv('TIKTOK_CLIENT_KEY');
    $redirectUri = getenv('TIKTOK_REDIRECT_URI');
    $state = $_SESSION['oauth_state'];
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

} elseif ($platform === 'facebook') {
    $appId = getenv('FB_APP_ID');
    $redirectUri = getenv('FB_REDIRECT_URI');
    $state = $_SESSION['oauth_state'];
    
    // Standard Business Scopes (Pages + Instagram)
    $scopes = [
        'public_profile',
        'pages_show_list',
        'pages_manage_posts',
        'pages_read_engagement',
        'instagram_basic',
        'instagram_content_publish'
    ];

    $authUrl = "https://www.facebook.com/v18.0/dialog/oauth/?" . http_build_query([
        'client_id'     => $appId,
        'redirect_uri'  => $redirectUri,
        'scope'         => implode(',', $scopes),
        'state'         => $state,
        'response_type' => 'code',
        'auth_type'     => 'rerequest'
    ]);

    header("Location: " . $authUrl);
    exit();

} else {
    header("Location: settings.php?error=unsupported_platform");
    exit();
}