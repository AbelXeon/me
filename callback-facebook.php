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
curl_setopt($ch, CURLOPT_URL, "https://graph.facebook.com/oauth/access_token?" . http_build_query([
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
    echo "<h2>❌ Short-Lived Token Exchange Failed</h2>";
    echo "<pre>" . htmlspecialchars(json_encode($tokenData, JSON_PRETTY_PRINT)) . "</pre>";
    exit();
}

// 3. Exchange Short-Lived User Token for a Long-Lived User Token (60 days)
$chLong = curl_init();
curl_setopt($chLong, CURLOPT_URL, "https://graph.facebook.com/oauth/access_token?" . http_build_query([
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
    echo "<h2>❌ Long-Lived Token Exchange Failed</h2>";
    echo "<pre>" . htmlspecialchars(json_encode($longTokenData, JSON_PRETTY_PRINT)) . "</pre>";
    exit();
}

// 4. Fetch the Facebook Pages owned by this user (Removed strict v18.0 path for compatibility)
$chPages = curl_init();
curl_setopt($chPages, CURLOPT_URL, "https://graph.facebook.com/me/accounts?access_token=" . urlencode($longLivedUserToken));
curl_setopt($chPages, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chPages, CURLOPT_SSL_VERIFYPEER, false);
$pagesResponse = curl_exec($chPages);
curl_close($chPages);

$pagesData = json_decode($pagesResponse, true);
$pages = $pagesData['data'] ?? [];

// DEBUG CHECK: If pages is empty, output raw API results instead of redirecting
if (empty($pages)) {
    echo "<h2>🔍 No Facebook Pages Found (Debug Mode)</h2>";
    echo "<p>Authentication succeeded, but the Page list request returned empty or threw an error.</p>";
    echo "<h3>Facebook Pages API Response:</h3>";
    echo "<pre>" . htmlspecialchars(json_encode($pagesData, JSON_PRETTY_PRINT)) . "</pre>";
    echo "<h3>Token Exchange Response:</h3>";
    echo "<pre>" . htmlspecialchars(json_encode($longTokenData, JSON_PRETTY_PRINT)) . "</pre>";
    echo "<br><br><a href='settings.php'>Go back to Settings</a>";
    exit();
}

// Automatically connect the first Page found
$targetPage = $pages[0];
$pageAccessToken = $targetPage['access_token']; 
$pageId = $targetPage['id'];
$pageName = $targetPage['name'];

// 5. Query for Linked Instagram Account
$instagramBusinessId = null;
$instagramUsername = null;

$chIg = curl_init();
curl_setopt($chIg, CURLOPT_URL, "https://graph.facebook.com/" . urlencode($pageId) . "?fields=instagram_business_account&access_token=" . urlencode($pageAccessToken));
curl_setopt($chIg, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chIg, CURLOPT_SSL_VERIFYPEER, false);
$igResponse = curl_exec($chIg);
curl_close($chIg);

$igData = json_decode($igResponse, true);

if (isset($igData['instagram_business_account']['id'])) {
    $instagramBusinessId = $igData['instagram_business_account']['id'];

    $chIgUser = curl_init();
    curl_setopt($chIgUser, CURLOPT_URL, "https://graph.facebook.com/" . urlencode($instagramBusinessId) . "?fields=username&access_token=" . urlencode($pageAccessToken));
    curl_setopt($chIgUser, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chIgUser, CURLOPT_SSL_VERIFYPEER, false);
    $igUserResponse = curl_exec($chIgUser);
    curl_close($chIgUser);

    $igUserData = json_decode($igUserResponse, true);
    $instagramUsername = $igUserData['username'] ?? null;
}

try {
    $conn->beginTransaction();

    // 6. Save the Facebook Page Token
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

    // 7. Save Instagram if linked
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

    $msg = ($instagramBusinessId && $instagramUsername) ? "facebook_and_instagram_connected" : "facebook_connected";
    header("Location: settings.php?success=" . $msg);
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    header("Location: settings.php?error=" . urlencode("Database Error: " . $e->getMessage()));
    exit();
}