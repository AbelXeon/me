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

// Get this user's connected platforms
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

        // Per-platform comment toggle. Unchecked checkboxes are simply absent from
        // $_POST, so absence = disabled. Only meaningful for instagram/tiktok --
        // stored for every platform row regardless, other platforms just ignore it.
        $instagram_comments_enabled = isset($_POST['instagram_comments']) ? 1 : 0;
        $tiktok_comments_enabled    = isset($_POST['tiktok_comments']) ? 1 : 0;

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
                    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                        $error_codes = [
                            UPLOAD_ERR_INI_SIZE   => "The file is too large.",
                            UPLOAD_ERR_FORM_SIZE  => "The file exceeds the form limit.",
                            UPLOAD_ERR_PARTIAL    => "The file was only partially uploaded.",
                            UPLOAD_ERR_NO_FILE    => "No file was uploaded.",
                            UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
                            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
                            UPLOAD_ERR_EXTENSION  => "A PHP extension stopped the upload."
                        ];
                        $err_msg = $error_codes[$files['error'][$i]] ?? "Unknown upload error: " . $files['error'][$i];
                        throw new Exception("File " . ($i+1) . " upload failed: " . $err_msg);
                    }

                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $detected_mime = finfo_file($finfo, $files['tmp_name'][$i]);
                    finfo_close($finfo);

                    $media_type = null;
                    if (in_array($detected_mime, $allowed_image_mimes)) $media_type = 'image';
                    elseif (in_array($detected_mime, $allowed_video_mimes)) $media_type = 'video';

                    if (!$media_type) throw new Exception("File " . ($i+1) . " is not supported.");

                    $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                    
                    // If image was compressed client-side, force save as jpg [1.1.2]
                    if ($media_type === 'image') {
                        $ext = 'jpg';
                        $detected_mime = 'image/jpeg';
                    }

                    $new_filename = 'post_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $upload_dir = __DIR__ . '/uploads/posts/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                    if (move_uploaded_file($files['tmp_name'][$i], $upload_dir . $new_filename)) {
                        $relative_path = 'uploads/posts/' . $new_filename;
                        chmod($upload_dir . $new_filename, 0644); 
                        
                        $stmt = $conn->prepare("INSERT INTO media_files (path, type, size, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$relative_path, $media_type, $files['size'][$i], $detected_mime, $user_id]);
                        $uploaded_media_ids[] = $conn->lastInsertId();
                    } else {
                        throw new Exception("Failed to move the uploaded file " . ($i+1) . ".");
                    }
                }

                if (empty($uploaded_media_ids)) {
                    throw new Exception("No files were successfully processed.");
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
                    // comments_enabled only actually matters for instagram/tiktok in
                    // SocialMediaManager -- other platforms store it but ignore it.
                    $commentsEnabled = 1;
                    if ($platform === 'instagram') $commentsEnabled = $instagram_comments_enabled;
                    if ($platform === 'tiktok') $commentsEnabled = $tiktok_comments_enabled;

                    $stmt = $conn->prepare("INSERT INTO post_platforms (post_id, platform, status, comments_enabled) VALUES (?, ?, 'pending', ?)");
                    $stmt->execute([$post_id, $platform, $commentsEnabled]);
                }

                $conn->commit();

                if (!$is_scheduled) {
                    require_once 'includes/socialMediaManager.php';
                    $manager = new SocialMediaManager($conn);
                    $manager->sendPost($post_id);
                    $success = 'Post created! Publishing to each platform now -- check Post History for live status.';
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
    'facebook'  => ['icon' => '📘', 'label' => 'Facebook'],
    'instagram' => ['icon' => '📸', 'label' => 'Instagram'],
    'telegram'  => ['icon' => '✈️', 'label' => 'Telegram'],
    'linkedin'  => ['icon' => '💼', 'label' => 'LinkedIn'],
    'tiktok'    => ['icon' => '🎵', 'label' => 'TikTok'],
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

        <?php if (empty($connected)): ?>
            <div class="alert alert-warning">
                You haven't connected any platforms yet. <a href="settings.php">Go connect one first →</a>
            </div>
        <?php else: ?>
            <!-- Added id='postForm' here so JS can target it -->
            <form id="postForm" method="POST" action="" enctype="multipart/form-data">
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
                    <!-- Added id='mediaInput' here -->
                    <input type="file" id="mediaInput" name="media[]" accept="image/*,video/*" multiple required>
                </div>

                <div class="form-group">
                    <label>Post to</label>
                    <div class="platform-checkboxes">
                        <?php foreach ($connected as $platform => $account_name): ?>
                            <?php if (isset($platform_meta[$platform])): ?>
                                <label class="platform-checkbox">
                                    <input type="checkbox" name="platforms[]" value="<?php echo htmlspecialchars($platform); ?>" data-platform="<?php echo htmlspecialchars($platform); ?>" class="platform-toggle-checkbox">
                                    <span><?php echo $platform_meta[$platform]['icon']; ?> <?php echo htmlspecialchars($platform_meta[$platform]['label']); ?> (<?php echo htmlspecialchars($account_name); ?>)</span>
                                </label>

                                <?php if ($platform === 'instagram'): ?>
                                    <label class="platform-comment-toggle" id="instagram-comment-toggle" style="display:none; margin-left:28px; font-size:0.9em;">
                                        <input type="checkbox" name="instagram_comments" checked>
                                        Allow comments on Instagram
                                    </label>
                                <?php endif; ?>

                                <?php if ($platform === 'tiktok'): ?>
                                    <label class="platform-comment-toggle" id="tiktok-comment-toggle" style="display:none; margin-left:28px; font-size:0.9em;">
                                        <input type="checkbox" name="tiktok_comments" checked>
                                        Allow comments on TikTok
                                    </label>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="scheduled_at">Schedule for later</label>
                    <input type="datetime-local" id="scheduled_at" name="scheduled_at">
                </div>

                <button type="submit" class="btn-primary">Save & Publish</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Show/hide the comment toggle for a platform only when that platform is checked -->
    <script>
    document.querySelectorAll('.platform-toggle-checkbox').forEach(function(cb) {
        cb.addEventListener('change', function() {
            var toggleRow = document.getElementById(this.dataset.platform + '-comment-toggle');
            if (toggleRow) {
                toggleRow.style.display = this.checked ? 'block' : 'none';
            }
        });
    });
    </script>

    <!-- --- CLIENT SIDE IMAGE COMPRESSION SCRIPT (NO EXTERNAL LIBRARIES!) --- -->
    <script>
    document.getElementById('postForm').addEventListener('submit', async function(e) {
        const fileInput = document.getElementById('mediaInput');
        if (!fileInput.files.length) return;

        e.preventDefault(); // Stop form submission temporarily to compress [1.1.2]
        
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerText = "Compressing & Uploading...";

        const dataTransfer = new DataArrayItemsCollector();

        for (let i = 0; i < fileInput.files.length; i++) {
            const file = fileInput.files[i];
            
            // Only compress if the file is an image [1.1.2]
            if (file.type.startsWith('image/')) {
                try {
                    const compressedImage = await compressImage(file, 1024, 0.7); // Resizes to max 1024px width, 70% quality [1.1.2]
                    dataTransfer.add(compressedImage);
                } catch (err) {
                    dataTransfer.add(file); // Fallback to original if compression fails [1.1.2]
                }
            } else {
                dataTransfer.add(file); // Keep videos completely untouched [1.1.2]
            }
        }

        fileInput.files = dataTransfer.files; // Replace original files with compressed files [1.1.2]
        this.submit(); // Submit the form now [1.1.2]
    });

    // Helper class to override the FileList array in the file input [1.1.2]
    class DataArrayItemsCollector {
        constructor() {
            this.dt = new DataTransfer();
        }
        add(file) {
            this.dt.items.add(file);
        }
        get files() {
            return this.dt.files;
        }
    }

    // Native HTML5 Canvas Image Compression [1.1.2]
    function compressImage(file, maxDimension, quality) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = function(event) {
                const img = new Image();
                img.src = event.target.result;
                img.onload = function() {
                    let width = img.width;
                    let height = img.height;

                    // Calculate new dimensions keeping the aspect ratio [1.1.2]
                    if (width > height) {
                        if (width > maxDimension) {
                            height *= maxDimension / width;
                            width = maxDimension;
                        }
                    } else {
                        if (height > maxDimension) {
                            width *= maxDimension / height;
                            height = maxDimension;
                        }
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    // Convert canvas to a fresh compressed Blob/File [1.1.2]
                    canvas.toBlob((blob) => {
                        if (!blob) {
                            reject(new Error("Canvas conversion failed"));
                            return;
                        }
                        const compressedFile = new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".jpg", {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        });
                        resolve(compressedFile);
                    }, 'image/jpeg', quality);
                };
                img.onerror = () => reject(new Error("Image load error"));
            };
            reader.onerror = () => reject(new Error("File read error"));
        });
    }
    </script>
</body>
</html>