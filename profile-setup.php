<?php
// profile-setup.php
require_once 'includes/auth_check.php';
require_once 'config/database.php';
requireLogin();

$error = '';
$user_id = getCurrentUserId();
$conn = getDBConnection();

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } else {
        $address  = trim($_POST['address']);
        $phone_no = trim($_POST['phone_no']);
        $profile_image_path = $user['profile_image']; // keep existing if no new upload

        // Handle profile image upload if one was provided
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_image'];
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($detected_mime, $allowed_mimes)) {
                $error = 'Profile image must be JPG, PNG, or WEBP';
            } elseif ($file['size'] > $max_size) {
                $error = 'Profile image must be under 5MB';
            } else {
                $ext = match ($detected_mime) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                };
                $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                $upload_dir = __DIR__ . '/uploads/profiles/';

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_filename)) {
                    $profile_image_path = 'uploads/profiles/' . $new_filename;
                } else {
                    $error = 'Failed to save profile image';
                }
            }
        }

        if (!$error) {
            $stmt = $conn->prepare("UPDATE users SET address = ?, phone_no = ?, profile_image = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$address, $phone_no, $profile_image_path, $user_id]);

            header('Location: dashboard.php');
            exit();
        }
    }
}

$conn = null;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile - Social Media Manager</title>
    <link rel="stylesheet" href="assets/css/profile-setup.css">

</head>

<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>👋 Welcome, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
            <p>Let's finish setting up your profile</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="section-title">Your Info</div>

            <div class="avatar-upload">
                <div class="avatar-preview">
                    <?php if ($user['profile_image']): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile">
                    <?php else: ?>
                        👤
                    <?php endif; ?>
                </div>
                <div style="flex: 1;">
                    <label for="profile_image">Profile Picture</label>
                    <input type="file" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/webp">
                </div>
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" placeholder="City, Country"
                    value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="phone_no">Phone Number</label>
                <input type="text" id="phone_no" name="phone_no" placeholder="+251 900 000 000"
                    value="<?php echo htmlspecialchars($user['phone_no'] ?? ''); ?>">
            </div>

            <div class="section-title">Connect Your Platforms</div>
            <p style="font-size: 13px; color: #888; margin-bottom: 15px;">
                Optional — you can also do this later from Settings.
            </p>

            <div class="platform-list">
                <div class="platform-row">
                    <div class="platform-row-info">
                        <span class="platform-row-icon">📘</span>
                        <span class="platform-row-name">Facebook & Instagram</span>
                    </div>
                    <a href="connect-platforms.php" class="btn-connect-small">Connect</a>
                </div>

                <div class="platform-row">
                    <div class="platform-row-info">
                        <span class="platform-row-icon">✈️</span>
                        <span class="platform-row-name">Telegram</span>
                    </div>
                    <a href="connect-platforms.php" class="btn-connect-small">Connect</a>
                </div>

                <div class="platform-row">
                    <div class="platform-row-info">
                        <span class="platform-row-icon">💼</span>
                        <span class="platform-row-name">LinkedIn</span>
                    </div>
                    <a href="connect-platforms.php" class="btn-connect-small">Connect</a>
                </div>

                <div class="platform-row">
                    <div class="platform-row-info">
                        <span class="platform-row-icon">🎵</span>
                        <span class="platform-row-name">TikTok</span>
                    </div>
                    <a href="connect-platforms.php" class="btn-connect-small">Connect</a>
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn-primary">Save & Continue</button>
                <a href="dashboard.php" class="btn-skip">Skip for now</a>
            </div>
        </form>
    </div>
</body>

</html>