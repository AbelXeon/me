<?php
// callback-linkedin.php
require_once 'includes/auth_check.php';
require_once 'config/database.php';
requireLogin();

$user_id = getCurrentUserId();
$conn = getDBConnection();

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

// 1. Verify OAuth State
if (empty($state) || $state !== ($_SESSION['oauth_state'] ?? '')) {
    header("Location: settings.php?error=invalid_state");
    exit();
}
unset($_SESSION['oauth_state']);

if (empty($code)) {
    header("Location: settings.php?error=no_code_provided");
    exit();
}

$clientId = getenv('LINKEDIN_CLIENT_ID');
$clientSecret = getenv('LINKEDIN_CLIENT_SECRET');
$redirectUri = getenv('LINKEDIN_REDIRECT_URI');

// 2. Exchange Authorization Code for Access Token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.linkedin.com/oauth/v2/accessToken");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'redirect_uri'  => $redirectUri,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    header("Location: settings.php?error=" . urlencode("LinkedIn Token exchange CURL Error: " . $err));
    exit();
}

$tokenData = json_decode($response, true);
$accessToken = $tokenData['access_token'] ?? null;
$expiresIn = $tokenData['expires_in'] ?? null;

if (!$accessToken) {
    $errorMsg = $tokenData['error_description'] ?? 'Token exchange failed';
    header("Location: settings.php?error=" . urlencode($errorMsg));
    exit();
}

// 3. Fetch user's LinkedIn profile using standard OpenID Connect
$chUser = curl_init();
curl_setopt($chUser, CURLOPT_URL, "https://api.linkedin.com/v2/userinfo");
curl_setopt($chUser, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chUser, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($chUser, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$accessToken}"
]);

$userResponse = curl_exec($chUser);
curl_close($chUser);

$userData = json_decode($userResponse, true);

$name = $userData['name'] ?? 'LinkedIn User';
$sub = $userData['sub'] ?? null; // OpenID Unique Identifier (Person ID)

if (!$sub) {
    header("Location: settings.php?error=failed_to_fetch_profile");
    exit();
}

// Convert OpenID identifier into the required LinkedIn Person URN format
$platform_user_id = 'urn:li:person:' . $sub;

$expiresAt = null;
if ($expiresIn) {
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
}

try {
    // 4. Save the LinkedIn credentials
    $stmt = $conn->prepare("
        INSERT INTO social_accounts (user_id, platform, account_name, access_token, platform_user_id, token_expires_at, status) 
        VALUES (?, 'linkedin', ?, ?, ?, ?, 1)
        ON CONFLICT(user_id, platform) DO UPDATE SET 
        account_name = excluded.account_name,
        access_token = excluded.access_token,
        platform_user_id = excluded.platform_user_id,
        token_expires_at = excluded.token_expires_at,
        status = 1,
        connected_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$user_id, $name, $accessToken, $platform_user_id, $expiresAt]);

    header("Location: settings.php?success=linkedin_connected");
    exit();

} catch (Exception $e) {
    header("Location: settings.php?error=" . urlencode("Database Error: " . $e->getMessage()));
    exit();
}