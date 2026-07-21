<?php
// callback-facebook.php
require_once 'includes/auth_check.php';
require_once 'config/database.php';
requireLogin();

$user_id = getCurrentUserId();
$conn = getDBConnection();

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

// 1. CSRF Verification
if (empty($state) || $state !== ($_SESSION['oauth_state'] ?? '')) {
    header("Location: settings.php?error=invalid_state");
    exit();
}
unset($_SESSION['oauth_state']);

if (empty($code)) {
    header("Location: settings.php?error=no_code_provided");
    exit();
}

$appId = getenv('FB_APP_ID');
$appSecret = getenv('FB_APP_SECRET');
$redirectUri = getenv('FB_REDIRECT_URI');

// 2. Exchange code for Short-Lived User Access Token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://graph.facebook.com/v18.0/oauth/access_token?" . http_build_query([
    'client_id'     => $appId,
    'redirect_uri'  => $redirectUri,
    'client_secret' => $appSecret,
    'code'          => $code
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($response, true);
$userAccessToken = $tokenData['access_token'] ?? null;

if (!$userAccessToken) {
    header("Location: settings.php?error=" . urlencode($tokenData['error']['message'] ?? 'User token exchange failed.'));
    exit();
}

// 3. Exchange Short-Lived User Token for Long-Lived User Token (60 days)
$chLong = curl_init();
curl_setopt($chLong, CURLOPT_URL, "https://graph.facebook.com/v18.0/oauth/access_token?" . http_build_query([
    'grant_type'        => 'fb_exchange_token',
    'client_id'         => $appId,
    'client_secret'     => $appSecret,
    'fb_exchange_token' => $userAccessToken
]));
curl_setopt($chLong, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chLong, CURLOPT_SSL_VERIFYPEER, false);
$longResponse = curl_exec($chLong);
curl_close($chLong);

$longTokenData = json_decode($longResponse, true);
$longLivedUserToken = $longTokenData['access_token'] ?? null;

if (!$longLivedUserToken) {
    header("Location: settings.php?error=long_lived_token_failed");
    exit();
}

// 4. Fetch the Facebook Pages owned by this user
$chPages = curl_init();
curl_setopt($chPages, CURLOPT_URL, "https://graph.facebook.com/v18.0/me/accounts?access_token=" . urlencode($longLivedUserToken));
curl_setopt($chPages, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chPages, CURLOPT_SSL_VERIFYPEER, false);
$pagesResponse = curl_exec($chPages);
curl_close($chPages);

$pagesData = json_decode($pagesResponse, true);
$pages = $pagesData['data'] ?? [];

if (empty($pages)) {
    header("Location: settings.php?error=" . urlencode("No Facebook Pages found. You must create a Facebook Page first."));
    exit();
}

// For this simple test setup, we will connect the FIRST page found
$targetPage = $pages[0];
$pageAccessToken = $targetPage['access_token']; // Page specific token that never expires!
$pageId = $targetPage['id'];
$pageName = $targetPage['name'];

try {
    // 5. Save the Page Access Token to social_accounts
    $stmt = $conn->prepare("
        INSERT INTO social_accounts (user_id, platform, account_name, access_token, platform_user_id, status) 
        VALUES (?, 'facebook', ?, ?, ?, 1)
        ON CONFLICT(user_id, platform) DO UPDATE SET 
        account_name = excluded.account_name,
        access_token = excluded.access_token,
        platform_user_id = excluded.platform_user_id,
        status = 1,
        connected_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$user_id, $pageName, $pageAccessToken, $pageId]);

    header("Location: settings.php?success=facebook_connected");
    exit();

} catch (Exception $e) {
    header("Location: settings.php?error=" . urlencode("Database Error: " . $e->getMessage()));
    exit();
}