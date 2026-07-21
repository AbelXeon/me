<?php
// callback-facebook.php
require_once 'includes/auth_check.php';
require_once 'config/database.php';
requireLogin();

$user_id = getCurrentUserId();
$conn = getDBConnection();

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

// 1. Verify OAuth State to prevent CSRF attacks
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

// 2. Exchange the temporary code for a Short-Lived User Access Token
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
    $errorMsg = $tokenData['error']['message'] ?? 'User token exchange failed.';
    header("Location: settings.php?error=" . urlencode($errorMsg));
    exit();
}

// 3. Exchange Short-Lived User Token for a Long-Lived User Token (60 days)
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

// Automatically connect the first Page found
$targetPage = $pages[0];
$pageAccessToken = $targetPage['access_token']; // Page specific token that never expires!
$pageId = $targetPage['id'];
$pageName = $targetPage['name'];

// --- NEW INSTAGRAM DETECTION CODE ---
$instagramBusinessId = null;
$instagramUsername = null;

// Query the Facebook Page to see if it has a linked Instagram Business Account
$chIg = curl_init();
curl_setopt($chIg, CURLOPT_URL, "https://graph.facebook.com/v18.0/" . urlencode($pageId) . "?fields=instagram_business_account&access_token=" . urlencode($pageAccessToken));
curl_setopt($chIg, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chIg, CURLOPT_SSL_VERIFYPEER, false);
$igResponse = curl_exec($chIg);
curl_close($chIg);

$igData = json_decode($igResponse, true);

if (isset($igData['instagram_business_account']['id'])) {
    $instagramBusinessId = $igData['instagram_business_account']['id'];

    // Fetch the Instagram Username
    $chIgUser = curl_init();
    curl_setopt($chIgUser, CURLOPT_URL, "https://graph.facebook.com/v18.0/" . urlencode($instagramBusinessId) . "?fields=username&access_token=" . urlencode($pageAccessToken));
    curl_setopt($chIgUser, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chIgUser, CURLOPT_SSL_VERIFYPEER, false);
    $igUserResponse = curl_exec($chIgUser);
    curl_close($chIgUser);

    $igUserData = json_decode($igUserResponse, true);
    $instagramUsername = $igUserData['username'] ?? null;
}

try {
    $conn->beginTransaction();

    // 5. Save the Facebook Page Token
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

    // 6. Save Instagram if linked
    if ($instagramBusinessId && $instagramUsername) {
        $stmtIg = $conn->prepare("
            INSERT INTO social_accounts (user_id, platform, account_name, access_token, platform_user_id, status) 
            VALUES (?, 'instagram', ?, ?, ?, 1)
            ON CONFLICT(user_id, platform) DO UPDATE SET 
            account_name = excluded.account_name,
            access_token = excluded.access_token,
            platform_user_id = excluded.platform_user_id,
            status = 1,
            connected_at = CURRENT_TIMESTAMP
        ");
        $stmtIg->execute([$user_id, $instagramUsername, $pageAccessToken, $instagramBusinessId]);
    }

    $conn->commit();

    // Redirect back to settings page with success message
    $msg = ($instagramBusinessId && $instagramUsername) ? "facebook_and_instagram_connected" : "facebook_connected";
    header("Location: settings.php?success=" . $msg);
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    header("Location: settings.php?error=" . urlencode("Database Error: " . $e->getMessage()));
    exit();
}