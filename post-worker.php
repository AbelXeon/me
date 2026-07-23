<?php
// post-worker.php
//
// Internal endpoint used ONLY for parallel background posting -- it is triggered
// by SocialMediaManager::sendPost() firing a non-blocking request to itself, one
// per selected platform, so all platforms process at the same time instead of
// one after another. This is NOT meant to be visited directly by users or
// browsers -- it's protected by an HMAC token so outsiders can't trigger posts.
//
// Set INTERNAL_WORKER_SECRET in your environment (Render env vars) to a long
// random string. If it's not set, a weak fallback is used -- fine for local
// testing, but you should set a real one before relying on this in production.

require_once 'config/database.php';
require_once 'includes/socialMediaManager.php';

if (!isset($_ENV['TELEGRAM_BOT_TOKEN'])) {
    require_once 'includes/env.php';
}

$postId   = $_GET['post_id']  ?? null;
$platform = $_GET['platform'] ?? null;
$token    = $_GET['token']    ?? '';

$secret = getenv('INTERNAL_WORKER_SECRET') ?: 'change-me-internal-secret';
$expectedToken = $secret; // sendPost() currently passes the raw secret as the token

if (!$postId || !$platform || !hash_equals($expectedToken, (string)$token)) {
    http_response_code(403);
    exit('Forbidden');
}

// The caller (dispatchAsync in SocialMediaManager) closes its socket immediately
// and doesn't read any response -- but if this script IS ever hit by something
// that waits (browser, curl -v, etc.), finish the HTTP response fast and keep
// working in the background so the actual posting isn't cut short by a client
// timeout.
ignore_user_abort(true);
set_time_limit(0);
if (function_exists('fastcgi_finish_request')) {
    header('Content-Type: text/plain');
    echo 'OK';
    fastcgi_finish_request();
}

$conn = getDBConnection();
$manager = new SocialMediaManager($conn);
$manager->processPlatform((int)$postId, $platform);