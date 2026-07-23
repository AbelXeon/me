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
            } elseif ($platform === 'linkedin') {
                $success = $this->postToLinkedIn($post, $platform_post_id, $error_message);
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
     * Helper to fetch all media files associated with a post (Primary + Extras)
     */
    private function getPostMediaItems($post) {
        $stmtMedia = $this->db->prepare("
            SELECT path, type FROM media_files WHERE id = ?
            UNION ALL
            SELECT m.path, m.type FROM media_files m 
            JOIN post_extra_media pem ON m.id = pem.media_id 
            WHERE pem.post_id = ?
        ");
        $stmtMedia->execute([$post['media_id'], $post['id']]);
        return $stmtMedia->fetchAll();
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

        $finalCaption = $post['caption'];
        if (!empty($post['external_link'])) {
            $finalCaption .= "\n\n" . $post['external_link'];
        }

        $mediaItems = $this->getPostMediaItems($post);
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
                'privacy_level' => 'SELF_ONLY',
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
        curl_close($ch);
        $result = json_decode($response, true);

        if (isset($result['error']) && $result['error']['code'] === 'ok') {
            $platform_post_id = $result['data']['publish_id'] ?? null;
            return true;
        }

        $error_message = $result['error']['message'] ?? 'TikTok API Error';
        return false;
    }

    /**
     * Publishes text and media (single or album) directly to Facebook Page Feed
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
            $finalCaption .= "\n\n" . $post['external_link'];
        }

        $mediaItems = $this->getPostMediaItems($post);

        $redirectUri = getenv('FB_REDIRECT_URI') ?: '';
        $parsedUrl = parse_url($redirectUri);
        $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] : 'https';
        $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : 'me-wpv3.onrender.com';

        if (count($mediaItems) === 1) {
            $absoluteMediaUrl = $scheme . '://' . $host . '/' . $mediaItems[0]['path'];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POST, 1);

            if ($mediaItems[0]['type'] === 'video') {
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
            $result = json_decode($response, true);
            curl_close($ch);

            if (isset($result['id']) || isset($result['post_id'])) {
                $platform_post_id = $result['post_id'] ?? ($result['id'] ?? null);
                return true;
            }

            $error_message = $result['error']['message'] ?? 'Facebook API Error';
            return false;
        }

        try {
            $attachedMediaIds = [];
            foreach ($mediaItems as $item) {
                if ($item['type'] === 'video') continue;

                $absoluteMediaUrl = $scheme . '://' . $host . '/' . $item['path'];
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://graph.facebook.com/v18.0/{$pageId}/photos");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, [
                    'url'          => $absoluteMediaUrl,
                    'published'    => 'false',
                    'access_token' => $pageAccessToken
                ]);
                $response = curl_exec($ch);
                curl_close($ch);

                $res = json_decode($response, true);
                if (isset($res['id'])) {
                    $attachedMediaIds[] = ['media_fbid' => $res['id']];
                }
            }

            if (empty($attachedMediaIds)) {
                throw new Exception("Failed to prepare any media files for the Facebook Album.");
            }

            $chPublish = curl_init();
            curl_setopt($chPublish, CURLOPT_URL, "https://graph.facebook.com/v18.0/{$pageId}/feed");
            curl_setopt($chPublish, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chPublish, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($chPublish, CURLOPT_POST, 1);
            curl_setopt($chPublish, CURLOPT_POSTFIELDS, [
                'message'        => $finalCaption,
                'attached_media' => json_encode($attachedMediaIds),
                'access_token'   => $pageAccessToken
            ]);
            $publishResponse = curl_exec($chPublish);
            curl_close($chPublish);

            $publishResult = json_decode($publishResponse, true);
            if (isset($publishResult['id'])) {
                $platform_post_id = $publishResult['id'];
                return true;
            }

            $error_message = $publishResult['error']['message'] ?? 'Failed to publish Facebook Album.';
            return false;

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            return false;
        }
    }

    /**
     * Publishes images/videos (single or carousel) to linked Instagram Business Accounts
     */
    private function postToInstagram($post, &$platform_post_id, &$error_message) {
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

        $mediaItems = $this->getPostMediaItems($post);

        $redirectUri = getenv('FB_REDIRECT_URI') ?: '';
        $parsedUrl = parse_url($redirectUri);
        $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] : 'https';
        $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : 'me-wpv3.onrender.com';

        if (count($mediaItems) === 1) {
            $absoluteMediaUrl = $scheme . '://' . $host . '/' . $mediaItems[0]['path'];
            $is_video = ($mediaItems[0]['type'] === 'video');

            $chContainer = curl_init();
            curl_setopt($chContainer, CURLOPT_URL, "https://graph.facebook.com/v18.0/{$instagramId}/media");
            curl_setopt($chContainer, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chContainer, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($chContainer, CURLOPT_POST, 1);

            if ($is_video) {
                curl_setopt($chContainer, CURLOPT_POSTFIELDS, [
                    'media_type'   => 'REELS',
                    'video_url'    => $absoluteMediaUrl,
                    'caption'      => $finalCaption,
                    'access_token' => $pageAccessToken
                ]);
            } else {
                curl_setopt($chContainer, CURLOPT_POSTFIELDS, [
                    'image_url'    => $absoluteMediaUrl,
                    'caption'      => $finalCaption,
                    'access_token' => $pageAccessToken
                ]);
            }

            $containerResponse = curl_exec($chContainer);
            $containerResult = json_decode($containerResponse, true);
            curl_close($chContainer);

            $creationId = $containerResult['id'] ?? null;
            if (!$creationId) {
                $error_message = "IG Container Error: " . ($containerResult['error']['message'] ?? 'Unknown');
                return false;
            }

            $isFinished = false;
            $retries = 15; 
            while ($retries > 0) {
                $chStatus = curl_init();
                curl_setopt($chStatus, CURLOPT_URL, "https://graph.facebook.com/v18.0/{$creationId}?fields=status_code&access_token=" . urlencode($pageAccessToken));
                curl_setopt($chStatus, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chStatus, CURLOPT_SSL_VERIFYPEER, false);
                $statusResponse = curl_exec($chStatus);
                curl_close($chStatus);

                $statusResult = json_decode($statusResponse, true);
                $statusCode = $statusResult['status_code'] ?? 'IN_PROGRESS';

                if ($statusCode === 'FINISHED') {
                    $isFinished = true;
                    break;
                } elseif ($statusCode === 'ERROR') {
                    $error_message = "Instagram Media Processing Error: " . ($statusResult['error_message'] ?? 'Unknown.');
                    return false;
                }
                sleep(3); 
                $retries--;
            }

            if (!$isFinished) {
                $error_message = "Instagram timed out waiting for media to process.";
                return false;
            }

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
            $publishResult = json_decode($publishResponse, true);
            curl_close($chPublish);

            if (isset($publishResult['id'])) {
                $platform_post_id = $publishResult['id'];
                return true;
            }

            $error_message = "IG Publish Error: " . ($publishResult['error']['message'] ?? 'Unknown');
            return false;
        }

        try {
            $carouselItemIds = [];
            foreach ($mediaItems as $item) {
                if ($item['type'] === 'video') continue;

                $absoluteMediaUrl = $scheme . '://' . $host . '/' . $item['path'];
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://graph.facebook.com/v18.0/{$instagramId}/media");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, [
                    'image_url'        => $absoluteMediaUrl,
                    'is_carousel_item' => 'true',
                    'access_token'     => $pageAccessToken
                ]);
                $response = curl_exec($ch);
                curl_close($ch);

                $res = json_decode($response, true);
                if (isset($res['id'])) {
                    $carouselItemIds[] = $res['id'];
                }
            }

            if (count($carouselItemIds) < 2) {
                throw new Exception("Instagram Carousel requires at least 2 images.");
            }

            sleep(5);

            $chMain = curl_init();
            curl_setopt($chMain, CURLOPT_URL, "https://graph.facebook.com/v18.0/{$instagramId}/media");
            curl_setopt($chMain, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chMain, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($chMain, CURLOPT_POST, 1);
            curl_setopt($chMain, CURLOPT_POSTFIELDS, [
                'media_type'   => 'CAROUSEL',
                'children'     => json_encode($carouselItemIds),
                'caption'      => $finalCaption,
                'access_token' => $pageAccessToken
            ]);
            $mainResponse = curl_exec($chMain);
            curl_close($chMain);

            $mainResult = json_decode($mainResponse, true);
            $creationId = $mainResult['id'] ?? null;

            if (!$creationId) {
                throw new Exception("Main IG Carousel Error: " . ($mainResult['error']['message'] ?? 'Unknown'));
            }

            sleep(5);

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
            $publishResult = json_decode($publishResponse, true);
            curl_close($chPublish);

            if (isset($publishResult['id'])) {
                $platform_post_id = $publishResult['id'];
                return true;
            }

            $error_message = "IG Carousel Publish Error: " . ($publishResult['error']['message'] ?? 'Unknown');
            return false;

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            return false;
        }
    }

    /**
     * Publishes text, multiple images, or a native video to a LinkedIn Profile
     */
    private function postToLinkedIn($post, &$platform_post_id, &$error_message) {
        $stmt = $this->db->prepare("SELECT access_token, platform_user_id FROM social_accounts WHERE user_id = ? AND platform = 'linkedin' AND status = 1");
        $stmt->execute([$post['user_id']]);
        $account = $stmt->fetch();

        if (!$account) {
            $error_message = "LinkedIn account not connected.";
            return false;
        }

        $accessToken = $account['access_token'];
        $urnOwner = $account['platform_user_id']; 

        $finalCaption = $post['caption'];
        if (!empty($post['external_link'])) {
            $finalCaption .= "\n\n" . $post['external_link'];
        }

        $mediaItems = $this->getPostMediaItems($post);
        $uploadedAssets = [];
        $isVideoPost = false;
        $hasVideoItem = false;
        $videoDebugReason = null; // captures WHY video processing stopped, for real debugging

        // LinkedIn API version used for the versioned /rest/ endpoints (video upload).
        // Bump roughly yearly -- LinkedIn supports each version for about 12 months.
        $linkedinVersion = '202606';

        // 1. Process all media attachments
        foreach ($mediaItems as $item) {
            $mediaPath = realpath(__DIR__ . '/../' . $item['path']);
            if (!$mediaPath || !file_exists($mediaPath)) continue;

            $fileBinary = file_get_contents($mediaPath);
            $fileSize = filesize($mediaPath);
            $isImage = ($item['type'] === 'image');

            try {
                if ($isImage) {
                    // --- IMAGE UPLOAD (unchanged, already working) ---
                    $ch = curl_init("https://api.linkedin.com/v2/images?action=initializeUpload");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['initializeUploadRequest' => ['owner' => $urnOwner]]));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$accessToken}", "Content-Type: application/json"]);
                    $resp = json_decode(curl_exec($ch), true);
                    curl_close($ch);

                    $uploadUrl = $resp['value']['uploadUrl'] ?? null;
                    $assetUrn = $resp['value']['image'] ?? null;
                    $contentType = "image/jpeg";

                    if ($uploadUrl && $assetUrn) {
                        $chPut = curl_init($uploadUrl);
                        curl_setopt($chPut, CURLOPT_CUSTOMREQUEST, "PUT");
                        curl_setopt($chPut, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($chPut, CURLOPT_POSTFIELDS, $fileBinary);
                        curl_setopt($chPut, CURLOPT_HTTPHEADER, ["Content-Type: {$contentType}"]);
                        curl_exec($chPut);
                        curl_close($chPut);

                        $uploadedAssets[] = $assetUrn;
                    }
                } else {
                    // --- VIDEO UPLOAD (fixed) ---
                    // Videos MUST go through the versioned /rest/videos endpoint --
                    // /v2/videos does not exist, which is why this silently failed before.
                    // Videos also use chunked/multipart upload + a required finalize step,
                    // unlike the single-shot image upload above.
                    $hasVideoItem = true;
                    $chInit = curl_init("https://api.linkedin.com/rest/videos?action=initializeUpload");
                    curl_setopt($chInit, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chInit, CURLOPT_POST, 1);
                    curl_setopt($chInit, CURLOPT_POSTFIELDS, json_encode([
                        'initializeUploadRequest' => [
                            'owner' => $urnOwner,
                            'fileSizeBytes' => $fileSize,
                            'uploadCaptions' => false,
                            'uploadThumbnail' => false
                        ]
                    ]));
                    curl_setopt($chInit, CURLOPT_HTTPHEADER, [
                        "Authorization: Bearer {$accessToken}",
                        "Content-Type: application/json",
                        "LinkedIn-Version: {$linkedinVersion}",
                        "X-Restli-Protocol-Version: 2.0.0"
                    ]);
                    $initResponse = curl_exec($chInit);
                    $initHttpCode = curl_getinfo($chInit, CURLINFO_HTTP_CODE);
                    curl_close($chInit);

                    $initResult = json_decode($initResponse, true);
                    $uploadInstructions = $initResult['value']['uploadInstructions'] ?? [];
                    $videoUrn = $initResult['value']['video'] ?? null;
                    $uploadToken = $initResult['value']['uploadToken'] ?? '';

                    if (empty($uploadInstructions) || !$videoUrn) {
                        // Initialization failed -- capture LinkedIn's actual response so
                        // we know why (bad scope, app not approved, invalid owner urn, etc.)
                        $apiError = $initResult['message'] ?? json_encode($initResult);
                        $videoDebugReason = "Video init failed (HTTP {$initHttpCode}): " . $apiError;
                        continue;
                    }

                    // Upload each chunk and collect its ETag (required for finalize)
                    $uploadedPartIds = [];
                    $uploadOk = true;

                    foreach ($uploadInstructions as $instruction) {
                        $chunkUploadUrl = $instruction['uploadUrl'] ?? null;
                        $firstByte = $instruction['firstByte'] ?? 0;
                        $lastByte = $instruction['lastByte'] ?? ($fileSize - 1);
                        $chunkLength = ($lastByte - $firstByte) + 1;
                        $chunkData = substr($fileBinary, $firstByte, $chunkLength);

                        if (!$chunkUploadUrl) {
                            $uploadOk = false;
                            $videoDebugReason = "Video chunk upload aborted: missing uploadUrl in LinkedIn's initializeUpload response.";
                            break;
                        }

                        $responseHeaders = [];
                        $chPut = curl_init($chunkUploadUrl);
                        curl_setopt($chPut, CURLOPT_CUSTOMREQUEST, "PUT");
                        curl_setopt($chPut, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($chPut, CURLOPT_POSTFIELDS, $chunkData);
                        // NOTE: without an explicit Content-Type, curl defaults PUT bodies to
                        // application/x-www-form-urlencoded, which LinkedIn's pre-signed upload
                        // URL will often reject with a 400. Set it explicitly like the (working)
                        // image upload above does.
                        curl_setopt($chPut, CURLOPT_HTTPHEADER, [
                            "Content-Type: video/mp4",
                            "Content-Length: " . strlen($chunkData)
                        ]);
                        curl_setopt($chPut, CURLOPT_HEADERFUNCTION, function($curl, $headerLine) use (&$responseHeaders) {
                            $parts = explode(':', $headerLine, 2);
                            if (count($parts) === 2) {
                                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                            }
                            return strlen($headerLine);
                        });
                        $putResponseBody = curl_exec($chPut);
                        $putHttpCode = curl_getinfo($chPut, CURLINFO_HTTP_CODE);
                        curl_close($chPut);

                        if ($putHttpCode < 200 || $putHttpCode >= 300) {
                            $uploadOk = false;
                            // Include LinkedIn's actual response body so we know the real
                            // reason (signature mismatch, size mismatch, expired URL, etc.)
                            // instead of just the bare status code.
                            $bodySnippet = $putResponseBody ? substr($putResponseBody, 0, 300) : '(empty response body)';
                            $videoDebugReason = "Video chunk PUT failed with HTTP {$putHttpCode}: {$bodySnippet}";
                            break;
                        }

                        // ETag is required to finalize the upload
                        $etag = $responseHeaders['etag'] ?? null;
                        if ($etag) {
                            $uploadedPartIds[] = trim($etag, '"');
                        }
                    }

                    if (!$uploadOk) {
                        continue; // skip this media item, falls through to fallback below
                    }

                    // Finalize the video upload
                    $chFinalize = curl_init("https://api.linkedin.com/rest/videos?action=finalizeUpload");
                    curl_setopt($chFinalize, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chFinalize, CURLOPT_POST, 1);
                    curl_setopt($chFinalize, CURLOPT_POSTFIELDS, json_encode([
                        'finalizeUploadRequest' => [
                            'video' => $videoUrn,
                            'uploadToken' => $uploadToken,
                            'uploadedPartIds' => $uploadedPartIds
                        ]
                    ]));
                    curl_setopt($chFinalize, CURLOPT_HTTPHEADER, [
                        "Authorization: Bearer {$accessToken}",
                        "Content-Type: application/json",
                        "LinkedIn-Version: {$linkedinVersion}",
                        "X-Restli-Protocol-Version: 2.0.0"
                    ]);
                    $finalizeResponse = curl_exec($chFinalize);
                    $finalizeHttpCode = curl_getinfo($chFinalize, CURLINFO_HTTP_CODE);
                    curl_close($chFinalize);

                    if ($finalizeHttpCode < 200 || $finalizeHttpCode >= 300) {
                        $videoDebugReason = "Video finalizeUpload failed (HTTP {$finalizeHttpCode}): " . $finalizeResponse;
                        continue;
                    }

                    // Poll processing status until AVAILABLE before referencing it in a post
                    $isReady = false;
                    $retries = 20;
                    while ($retries > 0) {
                        $chStatus = curl_init("https://api.linkedin.com/rest/videos/" . urlencode($videoUrn));
                        curl_setopt($chStatus, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($chStatus, CURLOPT_HTTPHEADER, [
                            "Authorization: Bearer {$accessToken}",
                            "LinkedIn-Version: {$linkedinVersion}",
                            "X-Restli-Protocol-Version: 2.0.0"
                        ]);
                        $statusResponse = curl_exec($chStatus);
                        curl_close($chStatus);

                        $statusResult = json_decode($statusResponse, true);
                        $status = $statusResult['status'] ?? 'PROCESSING';

                        if ($status === 'AVAILABLE') {
                            $isReady = true;
                            break;
                        } elseif ($status === 'FAILED') {
                            break;
                        }

                        sleep(3);
                        $retries--;
                    }

                    if ($isReady) {
                        $uploadedAssets[] = $videoUrn;
                        $isVideoPost = true;
                    } else {
                        $videoDebugReason = "Video never reached AVAILABLE status (last status: {$status}). It may need more processing time, or the upload failed server-side.";
                    }
                }
            } catch (Exception $e) {
                $videoDebugReason = "Exception during video processing: " . $e->getMessage();
                continue;
            }
            if ($isVideoPost) break; // LinkedIn supports only one video per post
        }

        // If the post included a video but it never successfully attached, fail here
        // with the real reason instead of silently publishing caption-only text and
        // reporting "posted" as if the video worked.
        if ($hasVideoItem && !$isVideoPost) {
            $error_message = $videoDebugReason ?? "LinkedIn video upload failed for an unknown reason.";
            return false;
        }

        $payload = [
            'author' => $urnOwner,
            'commentary' => $finalCaption,
            'visibility' => 'PUBLIC',
            'distribution' => ['feedDistribution' => 'MAIN_FEED'],
            'lifecycleState' => 'PUBLISHED'
        ];

        if (!empty($uploadedAssets)) {
            if ($isVideoPost) {
                $payload['content'] = ['media' => ['id' => $uploadedAssets[0]]];
            } else {
                $imagesArray = [];
                foreach ($uploadedAssets as $urn) {
                    $imagesArray[] = ['id' => $urn];
                }
                $payload['content'] = ['multiImage' => ['images' => $imagesArray]];
            }
        } else if (!empty($post['external_link'])) {
            $payload['content'] = [
                'article' => [
                    'source' => $post['external_link'],
                    'title' => $post['title'] ?: 'Shared Link'
                ]
            ];
        }

        $ch = curl_init("https://api.linkedin.com/v2/posts");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json",
            "X-Restli-Protocol-Version: 2.0.0"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 201) {
            $platform_post_id = 'urn:li:share:' . time(); 
            return true;
        }

        $result = json_decode($response, true);
        $error_message = $result['message'] ?? 'LinkedIn Error (HTTP ' . $httpCode . ')';
        return false;
    }
}