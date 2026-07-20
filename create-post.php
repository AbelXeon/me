<?php
// create-post.php
require_once 'includes/auth_check.php';
require_once 'config/database.php';
requireLogin();

$user_id = getCurrentUserId();
$conn = getDBConnection();
$error = '';
$success = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$stmt = $conn->prepare("SELECT platform, account_name FROM social_accounts WHERE user_id = ? AND status = 1");
$stmt->execute([$user_id]);
$connected = [];
while ($row = $stmt->fetch()) {
    $connected[$row['platform']] = $row['account_name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid form submission.';
    } else {
        $caption = trim($_POST['caption'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $external_link = trim($_POST['external_link'] ?? '');
        $platforms = $_POST['platforms'] ?? [];
        $scheduled_at = trim($_POST['scheduled_at'] ?? '');
        
        if (!empty($scheduled_at)) {
            $scheduled_at = str_replace('T', ' ', $scheduled_at) . ':00';
        }

        if (empty($caption)) {
            $error = 'Caption cannot be empty';
        } elseif (empty($platforms)) {
            $error = 'Select at least one platform to post to';
        } elseif (empty($_FILES['media']['name'][0])) {
            $error = 'Please select at least one image or video to upload';
        } else {
            try {
                $conn->beginTransaction();

                $uploaded_media_ids = [];
                $files = $_FILES['media'];
                $total_files = count($files['name']);
                $allowed_image_mimes = ['image/jpeg', 'image/png', 'image/webp'];
                $allowed_video_mimes = ['video/mp4', 'video/quicktime'];

                for ($i = 0; $i < $total_files; $i++) {
                    if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $detected_mime = finfo_file($finfo, $files['tmp_name'][$i]);
                    finfo_close($finfo);

                    $media_type = null;
                    if (in_array($detected_mime, $allowed_image_mimes)) $media_type = 'image';
                    elseif (in_array($detected_mime, $allowed_video_mimes)) $media_type = 'video';

                    if (!$media_type) throw new Exception("File " . ($i+1) . " is not supported.");

                    $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                    $new_filename = 'post_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $upload_dir = __DIR__ . '/uploads/posts/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                    if (move_uploaded_file($files['tmp_name'][$i], $upload_dir . $new_filename)) {
                        $relative_path = 'uploads/posts/' . $new_filename;
                        $stmt = $conn->prepare("INSERT INTO media_files (path, type, size, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$relative_path, $media_type, $files['size'][$i], $detected_mime, $user_id]);
                        $uploaded_media_ids[] = $conn->lastInsertId();
                    }
                }

                // DEFENSIVE CHECK: Make sure at least one file was successfully uploaded
                if (empty($uploaded_media_ids)) {
                    throw new Exception("Failed to upload files. Please make sure the server has permission to write to the uploads directory.");
                }

                $primary_media_id = $uploaded_media_ids[0];
                $stmtMedia = $conn->prepare("SELECT type FROM media_files WHERE id = ?");
                $stmtMedia->execute([$primary_media_id]);
                $primary_type = $stmtMedia->fetchColumn();

                $is_scheduled = !empty($scheduled_at);
                $post_status = $is_scheduled ? 'scheduled' : 'draft';

                $stmt = $conn->prepare("INSERT INTO posts (user_id, caption, title, external_link, media_type, media_id, status, scheduled_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $caption, $title ?: null, $external_link, $primary_type, $primary_media_id, $post_status, $is_scheduled ? $scheduled_at : null]);
                $post_id = $conn->lastInsertId();

                if (count($uploaded_media_ids) > 1) {
                    $stmtExtra = $conn->prepare("INSERT INTO post_extra_media (post_id, media_id) VALUES (?, ?)");
                    for ($i = 1; $i < count($uploaded_media_ids); $i++) {
                        $stmtExtra->execute([$post_id, $uploaded_media_ids[$i]]);
                    }
                }

                foreach ($platforms as $platform) {
                    $stmt = $conn->prepare("INSERT INTO post_platforms (post_id, platform, status) VALUES (?, ?, 'pending')");
                    $stmt->execute([$post_id, $platform]);
                }

                $conn->commit();

                if (!$is_scheduled) {
                    require_once 'includes/socialMediaManager.php';
                    $manager = new SocialMediaManager($conn);
                    $manager->sendPost($post_id);
                    $success = 'Post successfully created and published!';
                } else {
                    $success = 'Post scheduled successfully!';
                }

            } catch (Exception $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

$platform_meta = [
    'facebook' => ['icon' => '📘', 'label' => 'Facebook'],
    'telegram' => ['icon' => '✈️', 'label' => 'Telegram'],
    'linkedin' => ['icon' => '💼', 'label' => 'LinkedIn'],
    'tiktok'   => ['icon' => '🎵', 'label' => 'TikTok'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Post - Social Media Manager</title>
    <link rel="stylesheet" href="assets/css/create-post.css">
</head>
<body>
    <div class="header">
        <div class="header-left">
            <h1>✍️ Create Post</h1>
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        <div class="user-info">
            <span>👤 <?php echo htmlspecialchars(getCurrentUsername()); ?></span>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($error) echo "<div class='alert alert-error'>".htmlspecialchars($error)."</div>"; ?>
        <?php if ($success) echo "<div class='alert alert-success'>".htmlspecialchars($success)."</div>"; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="form-group">
                <label for="title">Internal Title (Optional)</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="caption">Caption</label>
                <textarea id="caption" name="caption" rows="5" required><?php echo htmlspecialchars($_POST['caption'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="external_link">Links (Optional)</label>
                <textarea id="external_link" name="external_link" rows="2" placeholder="Paste links here (one per line)"><?php echo htmlspecialchars($_POST['external_link'] ?? ''); ?></textarea>
                <small>Links will be appended to the end of the post.</small>
            </div>

            <div class="form-group">
                <label for="media">Media (Select one or multiple)</label>
                <input type="file" id="media" name="media[]" accept="image/*,video/*" multiple required>
            </div>

            <div class="form-group">
                <label>Post to</label>
                <div class="platform-checkboxes">
                    <?php foreach ($connected as $platform => $account_name): ?>
                        <label class="platform-checkbox">
                            <input type="checkbox" name="platforms[]" value="<?php echo htmlspecialchars($platform); ?>">
                            <span><?php echo $platform_meta[$platform]['icon']; ?> <?php echo htmlspecialchars($platform_meta[$platform]['label']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="scheduled_at">Schedule for later</label>
                <input type="datetime-local" id="scheduled_at" name="scheduled_at">
            </div>

            <button type="submit" class="btn-primary">Save & Publish</button>
        </form>
    </div>
</body>
</html>