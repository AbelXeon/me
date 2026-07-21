<?php
// check-tiktok-status.php
require_once 'config/database.php';

$conn = getDBConnection();

try {
    // Get the last TikTok post_platforms record
    $stmt = $conn->prepare("
        SELECT platform_post_id, post_id, error_message 
        FROM post_platforms 
        WHERE platform = 'tiktok' 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute();
    $record = $stmt->fetch();

    if (!$record || !$record['platform_post_id']) {
        die("No recent TikTok post with a publish_id found in the database.");
    }

    $publishId = $record['platform_post_id'];
    echo "Checking status for Publish ID: <strong>" . htmlspecialchars($publishId) . "</strong><br><br>";

    // Get TikTok Access Token for this post
    $stmtUser = $conn->prepare("
        SELECT access_token FROM social_accounts sa 
        JOIN posts p ON sa.user_id = p.user_id 
        WHERE p.id = ? AND sa.platform = 'tiktok'
    ");
    $stmtUser->execute([$record['post_id']]);
    $accessToken = $stmtUser->fetchColumn();

    if (!$accessToken) {
        die("Could not find access token for this post.");
    }

    // Call TikTok /status/fetch/ API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://open.tiktokapis.com/v2/post/publish/status/fetch/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'publish_id' => $publishId
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$accessToken}",
        "Content-Type: application/json; charset=UTF-8"
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        die("CURL Error: " . $err);
    }

    $result = json_decode($response, true);
    
    echo "<h3>TikTok Status Response:</h3>";
    echo "<pre>" . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)) . "</pre>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}