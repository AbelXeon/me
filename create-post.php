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
    'facebook'  => ['icon' => 'https://cdn.simpleicons.org/facebook/1877F2', 'label' => 'Facebook'],
    'instagram' => ['icon' => 'https://cdn.simpleicons.org/instagram/E4405F', 'label' => 'Instagram'],
    'telegram'  => ['icon' => 'https://cdn.simpleicons.org/telegram/26A5E4', 'label' => 'Telegram'],
    'linkedin'  => ['icon' => 'https://cdn.simpleicons.org/linkedin/0A66C2', 'label' => 'LinkedIn'],
    'tiktok'    => ['icon' => 'https://cdn.simpleicons.org/tiktok/000000', 'label' => 'TikTok'],
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

    <div class="app-shell">

        <!-- ===================== SIDEBAR OVERLAY (mobile) ===================== -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

        <!-- ===================== SIDEBAR ===================== -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div class="brand-mark">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2a10 10 0 1 0 10 10"/>
                        <path d="M12 6a6 6 0 1 0 6 6"/>
                        <circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/>
                    </svg>
                </div>
                <span>Social Manager</span>
                <button type="button" class="sidebar-close" onclick="closeSidebar()" aria-label="Close menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18"/>
                        <path d="M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="sidebar-section-label">Menu</div>
            <ul class="nav-list">
                <li>
                    <a href="dashboard.php" class="nav-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="9" rx="1.5"/>
                            <rect x="14" y="3" width="7" height="5" rx="1.5"/>
                            <rect x="14" y="12" width="7" height="9" rx="1.5"/>
                            <rect x="3" y="16" width="7" height="5" rx="1.5"/>
                        </svg>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="create-post.php" class="nav-item active">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9"/>
                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                        </svg>
                        Create Post
                    </a>
                </li>
                <li>
                    <a href="settings.php" class="nav-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>
                        </svg>
                        Platform Settings
                    </a>
                </li>
                <li>
                    <a href="post-history.php" class="nav-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18"/>
                            <rect x="7" y="12" width="3" height="6" rx="0.5"/>
                            <rect x="12.5" y="8" width="3" height="10" rx="0.5"/>
                            <rect x="18" y="5" width="3" height="13" rx="0.5"/>
                        </svg>
                        Post History
                    </a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <a href="logout.php" class="btn-logout">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <path d="M16 17l5-5-5-5"/>
                        <path d="M21 12H9"/>
                    </svg>
                    Logout
                </a>
            </div>
        </aside>

        <!-- ===================== MAIN CONTENT ===================== -->
        <main class="main">

            <div class="topbar">
                <div class="topbar-left">
                    <button type="button" class="hamburger-btn" onclick="openSidebar()" aria-label="Open menu">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"/>
                            <path d="M3 12h18"/>
                            <path d="M3 18h18"/>
                        </svg>
                    </button>
                    <div>
                        <h1>Create Post</h1>
                        <a href="dashboard.php" class="back-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 12H5"/>
                                <path d="M12 19l-7-7 7-7"/>
                            </svg>
                            Back to Dashboard
                        </a>
                    </div>
                </div>

                <div class="user-info">
                    <span class="welcome">Welcome, <strong><?php echo htmlspecialchars(getCurrentUsername()); ?></strong></span>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (empty($connected)): ?>
                <div class="alert alert-warning">
                    You haven't connected any platforms yet. <a href="settings.php">Go connect one first &rarr;</a>
                </div>
            <?php else: ?>
                <!-- Added id='postForm' here so JS can target it -->
                <form id="postForm" method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="form-card">
                        <div class="form-group">
                            <label for="caption">Caption</label>
                            <textarea id="caption" name="caption" rows="5" required placeholder="Write something worth posting..."><?php echo htmlspecialchars($_POST['caption'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="external_link">Links (Optional)</label>
                            <textarea id="external_link" name="external_link" rows="2" placeholder="Paste links here (one per line)"><?php echo htmlspecialchars($_POST['external_link'] ?? ''); ?></textarea>
                            <small>Links will be appended to the end of the post.</small>
                        </div>
                    </div>

                    <div class="form-card">
                        <div class="form-group">
                            <label for="mediaInput">Media</label>

                            <!-- Added id='mediaInput' here -->
                            <input type="file" id="mediaInput" name="media[]" accept="image/*,video/*" multiple required class="file-input-hidden">

                            <div class="dropzone" id="dropzone" tabindex="0" role="button" aria-label="Select media files">
                                <div class="dropzone-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 16V4"/>
                                        <path d="M7 9l5-5 5 5"/>
                                        <path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>
                                    </svg>
                                </div>
                                <p class="dropzone-title">Click to upload or drag and drop</p>
                                <p class="dropzone-hint">Images or videos, multiple files supported</p>
                            </div>

                            <div class="media-preview-grid" id="mediaPreviewGrid"></div>
                        </div>
                    </div>

                    <div class="form-card">
                        <div class="form-group">
                            <label>Post to</label>
                            <div class="platform-checkboxes">
                                <?php foreach ($connected as $platform => $account_name): ?>
                                    <?php if (isset($platform_meta[$platform])): ?>
                                        <label class="platform-checkbox">
                                            <input type="checkbox" name="platforms[]" value="<?php echo htmlspecialchars($platform); ?>">
                                            <span class="platform-checkbox-inner">
                                                <img src="<?php echo htmlspecialchars($platform_meta[$platform]['icon']); ?>" alt="<?php echo htmlspecialchars($platform_meta[$platform]['label']); ?>" class="platform-icon">
                                                <span class="platform-text">
                                                    <span class="platform-name"><?php echo htmlspecialchars($platform_meta[$platform]['label']); ?></span>
                                                    <span class="platform-account"><?php echo htmlspecialchars($account_name); ?></span>
                                                </span>
                                                <span class="platform-check">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M20 6 9 17l-5-5"/>
                                                    </svg>
                                                </span>
                                            </span>
                                        </label>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-card">
                        <div class="form-group">
                            <label>Schedule for later (optional)</label>

                            <div class="scheduler">
                                <div class="calendar" id="calendar">
                                    <div class="calendar-header">
                                        <button type="button" id="prevMonth" class="cal-nav" aria-label="Previous month">
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M15 18l-6-6 6-6"/>
                                            </svg>
                                        </button>
                                        <span id="calendarMonthLabel" class="cal-month-label"></span>
                                        <button type="button" id="nextMonth" class="cal-nav" aria-label="Next month">
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M9 18l6-6-6-6"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="cal-weekdays">
                                        <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                                    </div>
                                    <div class="cal-days" id="calendarDays"></div>
                                </div>

                                <div class="scheduler-time">
                                    <label for="scheduleTime">Time</label>
                                    <input type="time" id="scheduleTime">
                                    <div class="scheduler-summary" id="scheduledSummary">No date selected — post publishes immediately</div>
                                    <button type="button" id="clearSchedule" class="btn-clear-schedule">Clear schedule</button>
                                </div>
                            </div>

                            <input type="hidden" id="scheduled_at" name="scheduled_at" value="<?php echo htmlspecialchars($_POST['scheduled_at'] ?? ''); ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">Save &amp; Publish</button>
                </form>
            <?php endif; ?>

        </main>
    </div>

    <!-- ===== Sidebar toggle (mobile) ===== -->
    <script>
        function openSidebar() {
            document.getElementById('sidebar').classList.add('open');
            document.getElementById('sidebarOverlay').classList.add('open');
        }
        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebarOverlay').classList.remove('open');
        }
    </script>

    <!-- ===== Media dropzone + preview ===== -->
    <script>
    (function() {
        const mediaInput = document.getElementById('mediaInput');
        const dropzone = document.getElementById('dropzone');
        const previewGrid = document.getElementById('mediaPreviewGrid');
        if (!mediaInput || !dropzone) return;

        dropzone.addEventListener('click', () => mediaInput.click());
        dropzone.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                mediaInput.click();
            }
        });

        ['dragover', 'dragenter'].forEach(evt => {
            dropzone.addEventListener(evt, (e) => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
        });
        ['dragleave', 'dragend'].forEach(evt => {
            dropzone.addEventListener(evt, () => dropzone.classList.remove('dragover'));
        });
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                mediaInput.files = e.dataTransfer.files;
                renderPreviews();
            }
        });

        mediaInput.addEventListener('change', renderPreviews);

        function renderPreviews() {
            previewGrid.innerHTML = '';
            const files = Array.from(mediaInput.files);

            files.forEach((file, index) => {
                const card = document.createElement('div');
                card.className = 'media-preview-card';

                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    img.onload = () => URL.revokeObjectURL(img.src);
                    card.appendChild(img);
                } else if (file.type.startsWith('video/')) {
                    const video = document.createElement('video');
                    video.src = URL.createObjectURL(file);
                    video.muted = true;
                    card.appendChild(video);
                    const badge = document.createElement('span');
                    badge.className = 'media-preview-badge';
                    badge.textContent = 'Video';
                    card.appendChild(badge);
                }

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'media-preview-remove';
                removeBtn.setAttribute('aria-label', 'Remove file');
                removeBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>';
                removeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const dt = new DataTransfer();
                    files.forEach((f, i) => { if (i !== index) dt.items.add(f); });
                    mediaInput.files = dt.files;
                    renderPreviews();
                });
                card.appendChild(removeBtn);

                previewGrid.appendChild(card);
            });
        }
    })();
    </script>

    <!-- ===== Custom schedule calendar ===== -->
    <script>
    (function() {
        const calendarDays = document.getElementById('calendarDays');
        const monthLabel = document.getElementById('calendarMonthLabel');
        const prevBtn = document.getElementById('prevMonth');
        const nextBtn = document.getElementById('nextMonth');
        const timeInput = document.getElementById('scheduleTime');
        const summary = document.getElementById('scheduledSummary');
        const clearBtn = document.getElementById('clearSchedule');
        const hiddenField = document.getElementById('scheduled_at');
        if (!calendarDays) return;

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        let viewYear = today.getFullYear();
        let viewMonth = today.getMonth();
        let selectedDate = null;

        const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

        function pad(n) { return n.toString().padStart(2, '0'); }

        function sameDay(a, b) {
            return a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
        }

        function renderCalendar() {
            calendarDays.innerHTML = '';
            monthLabel.textContent = monthNames[viewMonth] + ' ' + viewYear;

            const firstOfMonth = new Date(viewYear, viewMonth, 1);
            const startOffset = firstOfMonth.getDay();
            const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

            for (let i = 0; i < startOffset; i++) {
                const spacer = document.createElement('span');
                spacer.className = 'cal-day cal-day-empty';
                calendarDays.appendChild(spacer);
            }

            for (let d = 1; d <= daysInMonth; d++) {
                const cellDate = new Date(viewYear, viewMonth, d);
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'cal-day';
                btn.textContent = d;

                const isPast = cellDate < today;
                const isToday = sameDay(cellDate, today);
                const isSelected = sameDay(cellDate, selectedDate);

                if (isPast) {
                    btn.classList.add('cal-day-disabled');
                    btn.disabled = true;
                    btn.setAttribute('aria-disabled', 'true');
                } else {
                    btn.addEventListener('click', () => {
                        selectedDate = cellDate;
                        renderCalendar();
                        updateScheduleValue();
                    });
                }

                if (isToday) btn.classList.add('cal-day-today');
                if (isSelected) btn.classList.add('cal-day-selected');

                calendarDays.appendChild(btn);
            }
        }

        function updateScheduleValue() {
            if (selectedDate && timeInput.value) {
                const value = selectedDate.getFullYear() + '-' + pad(selectedDate.getMonth() + 1) + '-' + pad(selectedDate.getDate()) + 'T' + timeInput.value;
                hiddenField.value = value;
                const niceDate = selectedDate.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
                summary.textContent = 'Scheduled for ' + niceDate + ' at ' + timeInput.value;
                summary.classList.add('scheduler-summary-active');
            } else if (selectedDate && !timeInput.value) {
                hiddenField.value = '';
                summary.textContent = 'Pick a time to finish scheduling';
                summary.classList.remove('scheduler-summary-active');
            } else {
                hiddenField.value = '';
                summary.textContent = 'No date selected — post publishes immediately';
                summary.classList.remove('scheduler-summary-active');
            }
        }

        prevBtn.addEventListener('click', () => {
            viewMonth--;
            if (viewMonth < 0) { viewMonth = 11; viewYear--; }
            renderCalendar();
        });
        nextBtn.addEventListener('click', () => {
            viewMonth++;
            if (viewMonth > 11) { viewMonth = 0; viewYear++; }
            renderCalendar();
        });
        timeInput.addEventListener('change', updateScheduleValue);
        clearBtn.addEventListener('click', () => {
            selectedDate = null;
            timeInput.value = '';
            renderCalendar();
            updateScheduleValue();
        });

        renderCalendar();
    })();
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