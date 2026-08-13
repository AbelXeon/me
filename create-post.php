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
        // Comments toggle: only meaningful for Instagram/TikTok, but harmless to
        // store for every post. Defaults to enabled (1) if the field is missing
        // or anything other than an explicit "0".
        $comments_enabled = (isset($_POST['comments_enabled']) && $_POST['comments_enabled'] === '0') ? 0 : 1;
        
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

                $stmt = $conn->prepare("INSERT INTO posts (user_id, caption, title, external_link, media_type, media_id, status, scheduled_at, comments_enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $caption, $title ?: null, $external_link, $primary_type, $primary_media_id, $post_status, $is_scheduled ? $scheduled_at : null, $comments_enabled]);
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
    'linkedin'  => ['icon' => 'https://img.icons8.com/color/48/linkedin.png', 'label' => 'LinkedIn'],
    'tiktok'    => ['icon' => 'https://cdn.simpleicons.org/tiktok/000000', 'label' => 'TikTok'],
];

$pageTitle = 'Create Post';
$activeNav = 'create-post';
$pageCss = 'assets/css/create-post.css';
$topbarTitle = 'Create Post';
$showBackBtn = true;
require_once 'includes/layout_header.php';
?>

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

                    <div class="compose-grid">

                        <div class="compose-main">
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
                        </div>

                        <div class="compose-side">
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

                            <div class="form-card" id="commentsCard" hidden>
                                <div class="form-group">
                                    <label class="switch-row" for="commentsToggle">
                                        <span class="switch-text">
                                            <strong>Allow comments</strong>
                                            <span>Applies to Instagram and TikTok</span>
                                        </span>
                                        <span class="switch">
                                            <input type="checkbox" id="commentsToggle" checked>
                                            <span class="switch-slider"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <input type="hidden" name="comments_enabled" id="commentsEnabledField" value="1">

                            <div class="form-card">
                                <div class="form-group">
                                    <label>When to publish</label>

                                    <div class="timing-toggle">
                                        <button type="button" class="timing-btn active" id="timingNowBtn">
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M13 2 3 14h7l-1 8 10-12h-7z"/>
                                            </svg>
                                            Post now
                                        </button>
                                        <button type="button" class="timing-btn" id="timingLaterBtn">
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="9"/>
                                                <path d="M12 7v5l3 3"/>
                                            </svg>
                                            Schedule for later
                                        </button>
                                    </div>

                                    <div class="scheduler-panel" id="schedulerPanel" hidden>
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
                                        </div>

                                        <div class="scheduler-summary" id="scheduledSummary">Pick a date and time above</div>
                                    </div>

                                    <input type="hidden" id="scheduled_at" name="scheduled_at" value="<?php echo htmlspecialchars($_POST['scheduled_at'] ?? ''); ?>">
                                </div>
                            </div>

                            <button type="submit" class="btn-primary">Save &amp; Publish</button>
                        </div>

                    </div>
                </form>
            <?php endif; ?>

    <!-- ===== Comments toggle (Instagram / TikTok only) ===== -->
    <script>
    (function() {
        const commentsCard = document.getElementById('commentsCard');
        const commentsToggle = document.getElementById('commentsToggle');
        const commentsEnabledField = document.getElementById('commentsEnabledField');
        const platformCheckboxes = document.querySelectorAll('input[name="platforms[]"]');
        if (!commentsCard || !commentsToggle || !commentsEnabledField) return;

        function relevantPlatformSelected() {
            return Array.from(platformCheckboxes).some(cb => cb.checked && (cb.value === 'instagram' || cb.value === 'tiktok'));
        }

        function syncCommentsCardVisibility() {
            commentsCard.hidden = !relevantPlatformSelected();
        }

        platformCheckboxes.forEach(cb => cb.addEventListener('change', syncCommentsCardVisibility));

        commentsToggle.addEventListener('change', () => {
            commentsEnabledField.value = commentsToggle.checked ? '1' : '0';
        });

        // Runs on load too, in case the form re-rendered after a validation
        // error with platforms already checked.
        syncCommentsCardVisibility();
    })();
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
        const hiddenField = document.getElementById('scheduled_at');
        const timingNowBtn = document.getElementById('timingNowBtn');
        const timingLaterBtn = document.getElementById('timingLaterBtn');
        const schedulerPanel = document.getElementById('schedulerPanel');
        if (!calendarDays) return;

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        let viewYear = today.getFullYear();
        let viewMonth = today.getMonth();
        let selectedDate = null;

        const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

        function pad(n) { return n.toString().padStart(2, '0'); }

        function nowTimeStr() {
            const n = new Date();
            return pad(n.getHours()) + ':' + pad(n.getMinutes());
        }

        function sameDay(a, b) {
            return a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
        }

        function applyTimeMinConstraint() {
            if (selectedDate && sameDay(selectedDate, today)) {
                timeInput.min = nowTimeStr();
            } else {
                timeInput.removeAttribute('min');
            }
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
                        applyTimeMinConstraint();
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
                if (sameDay(selectedDate, today) && timeInput.value < nowTimeStr()) {
                    hiddenField.value = '';
                    timeInput.value = '';
                    summary.textContent = "That time has already passed today — pick a later time";
                    summary.classList.remove('scheduler-summary-active');
                    summary.classList.add('scheduler-summary-warning');
                    return;
                }
                summary.classList.remove('scheduler-summary-warning');
                const value = selectedDate.getFullYear() + '-' + pad(selectedDate.getMonth() + 1) + '-' + pad(selectedDate.getDate()) + 'T' + timeInput.value;
                hiddenField.value = value;
                const niceDate = selectedDate.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
                summary.textContent = 'Scheduled for ' + niceDate + ' at ' + timeInput.value;
                summary.classList.add('scheduler-summary-active');
            } else if (selectedDate && !timeInput.value) {
                hiddenField.value = '';
                summary.textContent = 'Pick a time to finish scheduling';
                summary.classList.remove('scheduler-summary-active', 'scheduler-summary-warning');
            } else {
                hiddenField.value = '';
                summary.textContent = 'Pick a date and time above';
                summary.classList.remove('scheduler-summary-active', 'scheduler-summary-warning');
            }
        }

        function setTimingMode(mode) {
            if (mode === 'later') {
                timingLaterBtn.classList.add('active');
                timingNowBtn.classList.remove('active');
                schedulerPanel.hidden = false;
            } else {
                timingNowBtn.classList.add('active');
                timingLaterBtn.classList.remove('active');
                schedulerPanel.hidden = true;
                selectedDate = null;
                timeInput.value = '';
                timeInput.removeAttribute('min');
                hiddenField.value = '';
                renderCalendar();
                summary.textContent = 'Pick a date and time above';
                summary.classList.remove('scheduler-summary-active', 'scheduler-summary-warning');
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
        timingNowBtn.addEventListener('click', () => setTimingMode('now'));
        timingLaterBtn.addEventListener('click', () => setTimingMode('later'));

        renderCalendar();

        // If the form re-rendered after a validation error and a schedule was
        // already set, restore it instead of dropping back to "Post now".
        if (hiddenField.value) {
            const parts = hiddenField.value.split('T');
            const dateParts = (parts[0] || '').split('-').map(Number);
            if (dateParts.length === 3 && !isNaN(dateParts[0])) {
                const restored = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]);
                if (restored >= today) {
                    selectedDate = restored;
                    viewYear = restored.getFullYear();
                    viewMonth = restored.getMonth();
                    timeInput.value = parts[1] || '';
                    applyTimeMinConstraint();
                    renderCalendar();
                    setTimingMode('later');
                    updateScheduleValue();
                }
            }
        }
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

<?php require_once 'includes/layout_footer.php'; ?>