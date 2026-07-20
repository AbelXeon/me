<?php
// cron.php
require_once 'config/database.php';
require_once 'includes/socialMediaManager.php';

$conn = getDBConnection();
$manager = new SocialMediaManager($conn);

// Use PHP's date so it matches the timezone we set in database.php
$now = date('Y-m-d H:i:s');

echo "Checking for scheduled posts... (Current Time: $now)\n";

try {
    // Use the $now variable instead of CURRENT_TIMESTAMP
    $stmt = $conn->prepare("
        SELECT id FROM posts 
        WHERE status = 'scheduled' 
        AND scheduled_at <= ?
    ");
    $stmt->execute([$now]);
    $overduePosts = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($overduePosts)) {
        echo "No posts ready to be published yet.\n";
    } else {
        echo "Found " . count($overduePosts) . " posts to publish.\n";
        
        foreach ($overduePosts as $postId) {
            echo "Publishing Post ID: $postId... ";
            $manager->sendPost($postId);
            echo "Done.\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Finished loop.\n";