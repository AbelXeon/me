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
    public function sendPost($postId) {
        // Updated query to include external_link
        $stmt = $this->db->prepare("
            SELECT p.*, m.path as media_path, m.type as media_file_type 
            FROM posts p 
            JOIN media_files m ON p.media_id = m.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();

        if (!$post) return false;

        $stmt = $this->db->prepare("SELECT platform FROM post_platforms WHERE post_id = ? AND status = 'pending'");
        $stmt->execute([$postId]);
        $platforms = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($platforms as $platform) {
            $success = false;
            $platform_post_id = null;
            $error_message = null;

            if ($platform === 'telegram') {
                $success = $this->postToTelegram($post, $platform_post_id, $error_message);
            } 

            $status = $success ? 'posted' : 'failed';
            $stmtUpdate = $this->db->prepare("
                UPDATE post_platforms 
                SET status = ?, platform_post_id = ?, posted_at = CURRENT_TIMESTAMP, error_message = ? 
                WHERE post_id = ? AND platform = ?
            ");
            $stmtUpdate->execute([$status, $platform_post_id, $error_message, $postId, $platform]);
        }

        // Final status updates
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

    private function postToTelegram($post, &$platform_post_id, &$error_message) {
        $stmt = $this->db->prepare("SELECT access_token, platform_user_id FROM social_accounts WHERE user_id = ? AND platform = 'telegram' AND status = 1");
        $stmt->execute([$post['user_id']]);
        $account = $stmt->fetch();

        if (!$account) {
            $error_message = "Telegram account not connected.";
            return false;
        }

        // Prepare the caption with links appended at the end
        $finalCaption = $post['caption'];
        if (!empty($post['external_link'])) {
            $finalCaption .= "\n\n🔗 Links:\n" . $post['external_link'];
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
                    'caption' => ($index === 0) ? $finalCaption : '' // Only first image gets the caption
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
            // Check if result is a list (Album) or a single object (Photo/Video)
            if (isset($result['result'][0]['message_id'])) {
                // It's an album, take the ID of the first message
                $platform_post_id = $result['result'][0]['message_id'];
            } else {
                // It's a single post
                $platform_post_id = $result['result']['message_id'] ?? null;
            }
            return true;
        }

        $error_message = $result['description'] ?? 'Telegram Error';
        return false;
    }

  
}