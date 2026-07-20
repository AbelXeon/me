<?php
// callback-tiktok.php
require_once 'includes/auth_check.php';
require_once 'config/database.php';
requireLogin();

$user_id = getCurrentUserId();
$conn = getDBConnection();

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

// 1. Verify the OAuth state to prevent CSRF attacks
if (empty($state) || $state !== ($_SESSION['oauth_state'] ?? '')) {
    header("Location: settings.php?error=invalid_state");
    exit();
}

// Clear the state so it can't be reused
unset($_SESSION['oauth_state']);

if (empty($code)) {
    header("Location: settings.php?error=no_code_provided");
    exit();
}

$clientKey = getenv('TIKTOK_CLIENT_KEY');
$clientSecret = getenv('TIKTOK_CLIENT_SECRET');
$redirectUri = getenv('TIKTOK_REDIRECT_URI');

// 2. Exchange the temporary authorization code for an Access Token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://open.tiktokapis.com/v2/oauth/token/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_key'    => $clientKey,
    'client_secret' => $clientSecret,
    'code'          => $code,
    'grant_type'    => 'authorization_code',
    'redirect_uri'  => $redirectUri
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    header("Location: settings.php?error=" . urlencode("Token Exchange CURL Error: " . $err));
    exit();
}

$tokenData = json_decode($response, true);

// Standard fields can occasionally be nested inside 'data' depending on API version
$accessToken  = $tokenData['access_token']  ?? ($tokenData['data']['access_token'] ?? null);
$refreshToken = $tokenData['refresh_token'] ?? ($tokenData['data']['refresh_token'] ?? null);
$expiresIn    = $tokenData['expires_in']    ?? ($tokenData['data']['expires_in'] ?? null);
$openId       = $tokenData['open_id']       ?? ($tokenData['data']['open_id'] ?? null);

if (!$accessToken) {
    $errMsg = $tokenData['error_description'] ?? ($tokenData['message'] ?? 'Unknown authentication error');
    header("Location: settings.php?error=" . urlencode($errMsg));
    exit();
}

// 3. Fetch the user's TikTok username so we can display it in Settings
$chUser = curl_init();
curl_setopt($chUser, CURLOPT_URL, "https://open.tiktokapis.com/v2/user/info/?fields=username");
curl_setopt($chUser, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chUser, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($chUser, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$accessToken}"
]);

$userResponse = curl_exec($chUser);
curl_close($chUser);

$userData = json_decode($userResponse, true);
$username = $userData['data']['user']['username'] ?? 'TikTok Account';

// Calculate token expiration date
$expiresAt = null;
if ($expiresIn) {
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
}

try {
    // 4. Save the credentials securely to social_accounts
    // Works perfectly on both SQLite and PostgreSQL using ON CONFLICT
    $stmt = $conn->prepare("
        INSERT INTO social_accounts (user_id, platform, account_name, access_token, refresh_token, platform_user_id, token_expires_at, status) 
        VALUES (?, 'tiktok', ?, ?, ?, ?, ?, 1)
        ON CONFLICT(user_id, platform) DO UPDATE SET 
        account_name = excluded.account_name,
        access_token = excluded.access_token,
        refresh_token = excluded.refresh_token,
        platform_user_id = excluded.platform_user_id,
        token_expires_at = excluded.token_expires_at,
        status = 1,
        connected_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$user_id, $username, $accessToken, $refreshToken, $openId, $expiresAt]);

    header("Location: settings.php?success=tiktok_connected");
    exit();

} catch (Exception $e) {
    header("Location: settings.php?error=" . urlencode("Database Error: " . $e->getMessage()));
    exit();
}