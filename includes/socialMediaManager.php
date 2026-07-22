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
        $err = curl_error($ch);
        curl_close($ch);

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

        // Dynamically get your verified domain
        $redirectUri = getenv('FB_REDIRECT_URI') ?: '';
        $parsedUrl = parse_url($redirectUri);
        $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] : 'https';
        $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : 'me-wpv3.onrender.com';

        // CASE 1: SINGLE MEDIA POST
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

        // CASE 2: MULTI-PHOTO ALBUM POST
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

        // CASE 1: SINGLE MEDIA POST
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

            // WAIT FOR PROCESSING TO PREVENT "MEDIA ID NOT AVAILABLE"
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

        // CASE 2: MULTI-PHOTO CAROUSEL POST
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

            // Wait for all item containers to be finished
            sleep(5);

            // Step B: Create main carousel container
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

            // Wait for the main container to process
            sleep(5);

            // Step C: Publish the main carousel
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
     * Publishes a text and media directly to a personal LinkedIn Profile feed
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

        // Prepare standard LinkedIn request payload
        $payload = [
            'author' => $urnOwner,
            'commentary' => $finalCaption,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => []
            ],
            'lifecycleState' => 'PUBLISHED'
        ];

        // --- FIXED: CORRECTED THE LONG CLASS-NAME TYPO HERE ---
        if (!empty($mediaItems) && $mediaItems[0]['type'] === 'image') {
            $mediaPath = __DIR__ . '/../' . $mediaItems[0]['path'];
            
            if (file_exists($mediaPath)) {
                try {
                    // Step A: Register the image upload [1]
                    $chRegister = curl_init();
                    curl_setopt($chRegister, CURLOPT_URL, "https://api.linkedin.com/v2/assets?action=registerUpload");
                    curl_setopt($chRegister, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chRegister, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($chRegister, CURLOPT_POST, 1);
                    curl_setopt($chRegister, CURLOPT_POSTFIELDS, json_encode([
                        'registerUploadRequest' => [
                            'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                            'owner' => $urnOwner,
                            'serviceRelationships' => [
                                [
                                    'relationshipType' => 'OWNER',
                                    'identifier' => 'urn:li:userGeneratedContent'
                                ]
                            ]
                        ]
                    ]));
                    curl_setopt($chRegister, CURLOPT_HTTPHEADER, [
                        "Authorization: Bearer {$accessToken}",
                        "Content-Type: application/json"
                    ]);
                    $registerResponse = curl_exec($chRegister);
                    curl_close($chRegister);

                    $registerResult = json_decode($registerResponse, true);
                    
                    // FIXED: Changed MediaUploadMechanism to MediaUploadHttpRequest
                    $uploadUrl = $registerResult['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'] ?? null;
                    $assetUrn = $registerResult['value']['asset'] ?? null;

                    if ($uploadUrl && $assetUrn) {
                        // Step B: Upload the raw binary file data to the provided uploadUrl [1]
                        $fileBinary = file_get_contents($mediaPath);
                        
                        $chPut = curl_init();
                        curl_setopt($chPut, CURLOPT_URL, $uploadUrl);
                        curl_setopt($chPut, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($chPut, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($chPut, CURLOPT_CUSTOMREQUEST, "PUT");
                        curl_setopt($chPut, CURLOPT_POSTFIELDS, $fileBinary);
                        curl_setopt($chPut, CURLOPT_HTTPHEADER, [
                            "Content-Type: image/jpeg" // FIXED: Removed the Authorization header for S3 uploads [1.2.5]
                        ]);
                        curl_exec($chPut);
                        curl_close($chPut);

                        // Step C: Link the successfully uploaded asset URN directly to your post payload! [1]
                        $payload['content'] = [
                            'media' => [
                                'title' => !empty($post['title']) ? $post['title'] : 'Shared Image',
                                'id'    => $assetUrn
                            ]
                        ];
                    }
                } catch (Exception $e) {
                    // Fallback silently to Article Share if binary upload fails [1]
                }
            }
        }

        // Fallback for videos: Share as an Article Link [1]
        if (empty($payload['content']) && !empty($mediaItems)) {
            $redirectUri = getenv('LINKEDIN_REDIRECT_URI') ?: '';
            $parsedUrl = parse_url($redirectUri);
            $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] : 'https';
            $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : 'me-wpv3.onrender.com';
            $absoluteMediaUrl = $scheme . '://' . $host . '/' . $mediaItems[0]['path'];

            $payload['content'] = [
                'article' => [
                    'source'      => $absoluteMediaUrl,
                    'title'       => !empty($post['title']) ? $post['title'] : 'Shared Media',
                    'description' => 'Shared content via Social Media Manager.'
                ]
            ];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.linkedin.com/v2/posts");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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