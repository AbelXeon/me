<?php
// includes/socialMediaManager.php

class SocialMediaManager {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
        
        // If running from cron.php, $_ENV might be empty, so we load it
        if (!isset($_ENV['TELEGRAM_BOT_TOKEN'])) {
            require_once __DIR__ . '/env.php'; 
        }
    }

    /**
     * Main function to process a post for all selected platforms
     */
    public function sendPost($postId) {
        // 1. Fetch post details
        $stmt = $this->db->prepare("
            SELECT p.*, m.path as media_path, m.type as media_file_type 
            FROM posts p 
            JOIN media_files m ON p.media_id = m.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();

        if (!$post) return false;

        // 2. Fetch platforms for this post that are 'pending'
        $stmt = $this->db->prepare("SELECT platform FROM post_platforms WHERE post_id = ? AND status = 'pending'");
        $stmt->execute([$postId]);
        $platforms = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($platforms as $platform) {
            $success = false;
            $platform_post_id = null;
            $error_message = null;

            if ($platform === 'telegram') {
                $success = $this->postToTelegram($post, $platform_post_id, $error_message);
            } elseif ($platform === 'tiktok') {
                $success = $this->postToTikTok($post, $platform_post_id, $error_message);
            } elseif ($platform === 'facebook') {
                $success = $this->postToFacebook($post, $platform_post_id, $error_message);
            } elseif ($platform === 'instagram') {
                $success = $this->postToInstagram($post, $platform_post_id, $error_message);
            }

            // Update status in database
            $status = $success ? 'posted' : 'failed';
            $stmtUpdate = $this->db->prepare("
                UPDATE post_platforms 
                SET status = ?, platform_post_id = ?, posted_at = CURRENT_TIMESTAMP, error_message = ? 
                WHERE post_id = ? AND platform = ?
            ");
            $stmtUpdate->execute([$status, $platform_post_id, $error_message, $postId, $platform]);
        }

        // Update main post status if all platforms are processed
        $stmtCheck = $this->db->prepare("SELECT COUNT(*) FROM post_platforms WHERE post_id = ? AND status = 'pending'");
        $stmtCheck->execute([$postId]);
        if ($stmtCheck->fetchColumn() == 0) {
            $stmtSuccessCheck = $this->db->prepare("SELECT COUNT(*) FROM post_platforms WHERE post_id = ? AND status = 'posted'");
            $stmtSuccessCheck->execute([$postId]);
            $finalStatus = ($stmtSuccessCheck->fetchColumn() > 0) ? 'posted' : 'failed';

            $stmtMain = $this->db->prepare("UPDATE posts SET status = ?, published_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtMain->execute([$finalStatus, $postId]);
        }
    }

    /**
     * Publishes text and media to Telegram Channels
     */
    private function postToTelegram($post, &$platform_post_id, &$error_message) {
        $stmt = $this->db->prepare("SELECT access_token, platform_user_id FROM social_accounts WHERE user_id = ? AND platform = 'telegram' AND status = 1");
        $stmt->execute([$post['user_id']]);
        $account = $stmt->fetch();

        if (!$account) {
            $error_message = "Telegram account not connected.";
            return false;
        }

        // Clean link format (no emojis or "Links:" prefix)
        $finalCaption = $post['caption'];
        if (!empty($post['external_link'])) {
            $finalCaption .= "\n\n" . $post['external_link'];
        }

        $stmtMedia = $this->db->prepare("
            SELECT path, type FROM media_files WHERE id = ?
            UNION ALL
            SELECT m.path, m.type FROM media_files m 
            JOIN post_extra_media pem ON m.id = pem.media_id 
            WHERE pem.post_id = ?
        ");
        $stmtMedia->execute([$post['media_id'], $post['id']]);
        $mediaItems = $stmtMedia->fetchAll();

        $botToken = $account['access_token'];
        $chatId = $account['platform_user_id'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, 1);

        if (count($mediaItems) === 1) {
            $method = ($mediaItems[0]['type'] === 'video') ? 'sendVideo' : 'sendPhoto';
            $mediaField = ($mediaItems[0]['type'] === 'video') ? 'video' : 'photo';
            
            curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot{$botToken}/{$method}");
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'chat_id' => $chatId,
                'caption' => $finalCaption,
                $mediaField => new CURLFile(realpath(__DIR__ . '/../' . $mediaItems[0]['path']))
            ]);
        } else {
            curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot{$botToken}/sendMediaGroup");
            $mediaGroup = [];
            $postData = ['chat_id' => $chatId];

            foreach ($mediaItems as $index => $item) {
                $fileKey = "file_" . $index;
                $mediaGroup[] = [
                    'type' => ($item['type'] === 'video') ? 'video' : 'photo',
                    'media' => "attach://" . $fileKey,
                    'caption' => ($index === 0) ? $finalCaption : ''
                ];
                $postData[$fileKey] = new CURLFile(realpath(__DIR__ . '/../' . $item['path']));
            }
            $postData['media'] = json_encode($mediaGroup);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }

        $response = curl_exec($ch);
        $result = json_decode($response, true);
        curl_close($ch);

        if (isset($result['ok']) && $result['ok']) {
            if (isset($result['result'][0]['message_id'])) {
                $platform_post_id = $result['result'][0]['message_id'];
            } else {
                $platform_post_id = $result['result']['message_id'] ?? null;
            }
            return true;
        }

        $error_message = $result['description'] ?? 'Telegram Error';
        return false;
    }

    /**
     * Publishes a video to TikTok's Content Posting API v2
     */
    private function postToTikTok($post, &$platform_post_id, &$error_message) {
        $stmt = $this->db->prepare("SELECT access_token FROM social_accounts WHERE user_id = ? AND platform = 'tiktok' AND status = 1");
        $stmt->execute([$post['user_id']]);
        $account = $stmt->fetch();

        if (!$account) {
            $error_message = "TikTok account not connected.";
            return false;
        }

        if ($post['media_file_type'] !== 'video') {
            $error_message = "TikTok only supports video uploads.";
            return false;
        }

        $finalCaption = $post['caption'];
        if (!empty($post['external_link'])) {
            $finalCaption .= "\n\n" . $post['external_link'];
        }

        $redirectUri = getenv('TIKTOK_REDIRECT_URI') ?: '';
        $parsedUrl = parse_url($redirectUri);
        $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] : 'https';
        $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : 'me-wpv3.onrender.com';
        $absoluteVideoUrl = $scheme . '://' . $host . '/' . $post['media_path'];

        $accessToken = $account['access_token'];

        $payload = [
            'post_info' => [
                'title' => $finalCaption,
                'privacy_level' => 'SELF_ONLY', // Required for sandbox testing
                'disable_duet' => false,
                'disable_stitch' => false,
                'disable_comment' => false
            ],
            'source_info' => [
                'source' => 'PULL_FROM_URL',
                'video_url' => $absoluteVideoUrl
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://open.tiktokapis.com/v2/post/publish/video/init/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json; charset=UTF-8"
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $error_message = "CURL Error: " . $err;
            return false;
        }

        $result = json_decode($response, true);
        
        if (isset($result['error']) && $result['error']['code'] === 'ok') {
            $platform_post_id = $result['data']['publish_id'] ?? null;
            return true;
        }

        $error_message = $result['error']['message'] ?? 'TikTok API Error';
        return false;
    }

    /**
     * Publishes text and media directly to Facebook Page Feed
     */
    private function postToFacebook($post, &$platform_post_id, &$error_message) {
        $stmt = $this->db->prepare("SELECT access_token, platform_user_id FROM social_accounts WHERE user_id = ? AND platform = 'facebook' AND status = 1");
        $stmt->execute([$post['user_id']]);
        $account = $stmt->fetch();

        if (!$account) {
            $error_message = "Facebook account not connected.";
            return false;
        }

        $pageAccessToken = $account['access_token'];
        $pageId = $account['platform_user_id'];

        $finalCaption = $post['caption'];
        if (!empty($post['external_link'])) {
            $finalCaption .= "\n\n" . $post['external_link']; // Fixed: Removed "🔗 Links:"
        }

        $redirectUri = getenv('FB_REDIRECT_URI') ?: '';
        $parsedUrl = parse_url($redirectUri);
        $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] : 'https';
        $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : 'me-wpv3.onrender.com';
        $absoluteMediaUrl = $scheme . '://' . $host . '/' . $post['media_path'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, 1);

        if ($post['media_file_type'] === 'video') {
            curl_setopt($ch, CURLOPT_URL, "https://graph.facebook.com/v18.0/{$pageId}/videos");
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'file_url'     => $absoluteMediaUrl,
                'description'  => $finalCaption,
                'access_token' => $pageAccessToken
            ]);
        } else {
            curl_setopt($ch, CURLOPT_URL, "https://graph.facebook.com/v18.0/{$pageId}/photos");
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'url'          => $absoluteMediaUrl,
                'message'      => $finalCaption,
                'access_token' => $pageAccessToken
            ]);
        }

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $error_message = "CURL Error: " . $err;
            return false;
        }

        $result = json_decode($response, true);

        if (isset($result['id']) || isset($result['post_id'])) {
            $platform_post_id = $result['post_id'] ?? ($result['id'] ?? null);
            return true;
        }

        $error_message = $result['error']['message'] ?? 'Facebook API Error';
        return false;
    }

    /**
     * Publishes images/videos directly to linked Instagram Business Accounts
     */
    private function postToInstagram($post, &$platform_post_id, &$error_message) {
        // Instagram uses your Facebook Page Access Token to publish content!
        $stmt = $this->db->prepare("SELECT access_token, platform_user_id FROM social_accounts WHERE user_id = ? AND platform = 'instagram' AND status = 1");
        $stmt->execute([$post['user_id']]);
        $account = $stmt->fetch();

        if (!$account) {
            $error_message = "Instagram account not connected.";
            return false;
        }

        $pageAccessToken = $account['access_token'];
        $instagramId = $account['platform_user_id'];

        $finalCaption = $post['caption'];
        if (!empty($post['external_link'])) {
            $finalCaption .= "\n\n" . $post['external_link'];
        }

        // Dynamically get your verified domain
        $redirectUri = getenv('FB_REDIRECT_URI') ?: '';
        $parsedUrl = parse_url($redirectUri);
        $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] : 'https';
        $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : 'me-wpv3.onrender.com';
        $absoluteMediaUrl = $scheme . '://' . $host . '/' . $post['media_path'];

        $is_video = ($post['media_file_type'] === 'video');

        // STEP 1: Create the media container on Instagram
        $chContainer = curl_init();
        curl_setopt($chContainer, CURLOPT_URL, "https://graph.facebook.com/v18.0/{$instagramId}/media");
        curl_setopt($chContainer, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chContainer, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($chContainer, CURLOPT_POST, 1);

        if ($is_video) {
            // Instagram Reels container
            curl_setopt($chContainer, CURLOPT_POSTFIELDS, [
                'media_type'   => 'REELS',
                'video_url'    => $absoluteMediaUrl,
                'caption'      => $finalCaption,
                'access_token' => $pageAccessToken
            ]);
        } else {
            // Instagram Image container
            curl_setopt($chContainer, CURLOPT_POSTFIELDS, [
                'image_url'    => $absoluteMediaUrl,
                'caption'      => $finalCaption,
                'access_token' => $pageAccessToken
            ]);
        }

        $containerResponse = curl_exec($chContainer);
        curl_close($chContainer);

        $containerResult = json_decode($containerResponse, true);
        $creationId = $containerResult['id'] ?? null;

        if (!$creationId) {
            $error_message = "Instagram Container Error: " . ($containerResult['error']['message'] ?? 'Unknown error');
            return false;
        }

        // STEP 2: If video (Reels), wait 10 seconds for Instagram's video processors to download and approve the file
        if ($is_video) {
            sleep(10);
        }

        // STEP 3: Publish the container
        $chPublish = curl_init();
        curl_setopt($chPublish, CURLOPT_URL, "https://graph.facebook.com/v18.0/{$instagramId}/media_publish");
        curl_setopt($chPublish, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chPublish, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($chPublish, CURLOPT_POST, 1);
        curl_setopt($chPublish, CURLOPT_POSTFIELDS, [
            'creation_id'  => $creationId,
            'access_token' => $pageAccessToken
        ]);

        $publishResponse = curl_exec($chPublish);
        curl_close($chPublish);

        $publishResult = json_decode($publishResponse, true);

        if (isset($publishResult['id'])) {
            $platform_post_id = $publishResult['id'];
            return true;
        }

        $error_message = "Instagram Publish Error: " . ($publishResult['error']['message'] ?? 'Unknown error');
        return false;
    }
}