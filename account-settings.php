<?php
// account-settings.php
require_once 'includes/auth_check.php';
require_once 'config/database.php';
require_once 'includes/mailer.php';
requireLogin();

$user_id = getCurrentUserId();
$conn = getDBConnection();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$nameError = ''; $nameSuccess = '';
$usernameError = ''; $usernameSuccess = '';
$passwordError = ''; $passwordSuccess = '';
$activeTab = 'profile';

// Load current user record
$stmt = $conn->prepare("SELECT id, first_name, last_name, username, email, account_status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: logout.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $formAction = $_POST['form_action'] ?? '';
    $csrfOk = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);

    if ($formAction === 'update_name') {
        $activeTab = 'profile';

        if (!$csrfOk) {
            $nameError = 'Invalid form submission. Please refresh and try again.';
        } else {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name  = trim($_POST['last_name'] ?? '');

            if (empty($first_name) || empty($last_name)) {
                $nameError = 'Both first and last name are required.';
            } else {
                $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $user_id]);

                $user['first_name'] = $first_name;
                $user['last_name'] = $last_name;
                $nameSuccess = 'Your name has been updated.';
            }
        }

    } elseif ($formAction === 'update_username') {
        $activeTab = 'account';

        if (!$csrfOk) {
            $usernameError = 'Invalid form submission. Please refresh and try again.';
        } else {
            $username = trim($_POST['username'] ?? '');

            if (empty($username)) {
                $usernameError = 'Username is required.';
            } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
                $usernameError = 'Username must be 3-30 characters, letters/numbers/underscore only.';
            } else {
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$username, $user_id]);

                if ($stmt->fetch()) {
                    $usernameError = 'That username is already taken.';
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$username, $user_id]);

                    $_SESSION['username'] = $username;
                    $user['username'] = $username;
                    $usernameSuccess = 'Your username has been updated.';
                }
            }
        }

    } elseif ($formAction === 'update_password') {
        $activeTab = 'security';

        if (!$csrfOk) {
            $passwordError = 'Invalid form submission. Please refresh and try again.';
        } else {
            $current_password = $_POST['current_password'] ?? '';
            $new_password     = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $row = $stmt->fetch();

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $passwordError = 'Please fill in all password fields.';
            } elseif (!$row || !password_verify($current_password, $row['password'])) {
                $passwordError = 'Your current password is incorrect.';
            } elseif (strlen($new_password) < 8 || !preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
                $passwordError = 'New password must be 8+ characters and include a letter and a number.';
            } elseif ($new_password !== $confirm_password) {
                $passwordError = 'New passwords do not match.';
            } elseif (password_verify($new_password, $row['password'])) {
                $passwordError = 'New password must be different from your current password.';
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$hashed, $user_id]);
                $passwordSuccess = 'Your password has been changed.';
            }
        }
    }
}

// Whether each card's edit form should render already-open (e.g. after an error, so the person doesn't lose context)
$nameEditOpen = (bool) $nameError;
$usernameEditOpen = (bool) $usernameError;
$passwordEditOpen = (bool) $passwordError;

$statusLabels = [
    'active'    => ['label' => 'Active',    'class' => 'status-active'],
    'pending'   => ['label' => 'Pending',   'class' => 'status-pending'],
    'suspended' => ['label' => 'Suspended', 'class' => 'status-suspended'],
    'deleted'   => ['label' => 'Deleted',   'class' => 'status-suspended'],
];
$statusInfo = $statusLabels[$user['account_status']] ?? ['label' => ucfirst($user['account_status']), 'class' => 'status-pending'];

$pageTitle = 'Account Settings';
$activeNav = 'account-settings';
$pageCss = 'assets/css/account-settings.css';
$topbarTitle = 'Account Settings';
require_once 'includes/layout_header.php';
?>

    <div class="aset-shell">

        <!-- ===================== SECTION TABS ===================== -->
        <nav class="aset-tabs" id="asetTabs">
            <button type="button" class="aset-tab" data-tab="profile">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                Profile
            </button>
            <button type="button" class="aset-tab" data-tab="account">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18"/></svg>
                Account
            </button>
            <button type="button" class="aset-tab" data-tab="security">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                Security
            </button>
        </nav>

        <!-- ===================== PANELS ===================== -->
        <div class="aset-panels">

            <!-- ---------- PROFILE PANEL ---------- -->
            <section class="aset-panel" data-panel="profile">

                <!-- NAME (view/edit toggle) -->
                <div class="form-card" id="nameCard">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Name</h2>
                            <p class="card-subtitle">This is how your name appears across LEYKUN.</p>
                        </div>
                        <button type="button" class="edit-btn" data-target="nameCard" <?php echo $nameEditOpen ? 'hidden' : ''; ?>>
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                            Edit
                        </button>
                    </div>

                    <?php if ($nameError): ?><div class="alert alert-error"><?php echo htmlspecialchars($nameError); ?></div><?php endif; ?>
                    <?php if ($nameSuccess): ?><div class="alert alert-success"><?php echo htmlspecialchars($nameSuccess); ?></div><?php endif; ?>

                    <div class="field-view" <?php echo $nameEditOpen ? 'hidden' : ''; ?>>
                        <div class="field-row">
                            <span class="field-label">First Name</span>
                            <span class="field-value"><?php echo htmlspecialchars($user['first_name']); ?></span>
                        </div>
                        <div class="field-row">
                            <span class="field-label">Last Name</span>
                            <span class="field-value"><?php echo htmlspecialchars($user['last_name']); ?></span>
                        </div>
                    </div>

                    <form method="POST" action="" class="field-edit" <?php echo $nameEditOpen ? '' : 'hidden'; ?>>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="form_action" value="update_name">

                        <div class="form-row-flex">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($user['first_name']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($user['last_name']); ?>">
                            </div>
                        </div>

                        <div class="edit-actions">
                            <button type="submit" class="btn-primary">Save Name</button>
                            <button type="button" class="btn-secondary cancel-edit" data-target="nameCard">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- EMAIL (display only) -->
                <div class="form-card">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Email</h2>
                            <p class="card-subtitle">Contact support if you need to change this.</p>
                        </div>
                    </div>
                    <div class="field-view">
                        <div class="field-row">
                            <span class="field-label">Email</span>
                            <span class="field-value"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ---------- ACCOUNT PANEL ---------- -->
            <section class="aset-panel" data-panel="account">

                <!-- USERNAME (view/edit toggle) -->
                <div class="form-card" id="usernameCard">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Username</h2>
                            <p class="card-subtitle">Used to sign in to your account.</p>
                        </div>
                        <button type="button" class="edit-btn" data-target="usernameCard" <?php echo $usernameEditOpen ? 'hidden' : ''; ?>>
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                            Edit
                        </button>
                    </div>

                    <?php if ($usernameError): ?><div class="alert alert-error"><?php echo htmlspecialchars($usernameError); ?></div><?php endif; ?>
                    <?php if ($usernameSuccess): ?><div class="alert alert-success"><?php echo htmlspecialchars($usernameSuccess); ?></div><?php endif; ?>

                    <div class="field-view" <?php echo $usernameEditOpen ? 'hidden' : ''; ?>>
                        <div class="field-row">
                            <span class="field-label">Username</span>
                            <span class="field-value">@<?php echo htmlspecialchars($user['username']); ?></span>
                        </div>
                    </div>

                    <form method="POST" action="" class="field-edit" <?php echo $usernameEditOpen ? '' : 'hidden'; ?>>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="form_action" value="update_username">

                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($user['username']); ?>">
                            <small>3-30 characters — letters, numbers, and underscores only.</small>
                        </div>

                        <div class="edit-actions">
                            <button type="submit" class="btn-primary">Save Username</button>
                            <button type="button" class="btn-secondary cancel-edit" data-target="usernameCard">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- EMAIL (display only) -->
                <div class="form-card">
                    <div class="card-header">
                        <div><h2 class="card-title">Email</h2></div>
                    </div>
                    <div class="field-view">
                        <div class="field-row">
                            <span class="field-label">Email</span>
                            <span class="field-value"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- ACCOUNT STATUS (display only) -->
                <div class="form-card">
                    <div class="card-header">
                        <div><h2 class="card-title">Account Status</h2></div>
                    </div>
                    <span class="status-badge <?php echo $statusInfo['class']; ?>"><?php echo htmlspecialchars($statusInfo['label']); ?></span>
                </div>
            </section>

            <!-- ---------- SECURITY PANEL ---------- -->
            <section class="aset-panel" data-panel="security">

                <div class="form-card" id="passwordCard">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Password</h2>
                            <p class="card-subtitle">Change your password. You'll need your current one.</p>
                        </div>
                        <button type="button" class="edit-btn" data-target="passwordCard" <?php echo $passwordEditOpen ? 'hidden' : ''; ?>>
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                            Change Password
                        </button>
                    </div>

                    <?php if ($passwordError): ?><div class="alert alert-error"><?php echo htmlspecialchars($passwordError); ?></div><?php endif; ?>
                    <?php if ($passwordSuccess): ?><div class="alert alert-success"><?php echo htmlspecialchars($passwordSuccess); ?></div><?php endif; ?>

                    <div class="field-view" <?php echo $passwordEditOpen ? 'hidden' : ''; ?>>
                        <div class="field-row">
                            <span class="field-label">Password</span>
                            <span class="field-value field-value-dots">••••••••••••</span>
                        </div>
                    </div>

                    <form method="POST" action="" class="field-edit" id="passwordForm" <?php echo $passwordEditOpen ? '' : 'hidden'; ?>>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="form_action" value="update_password">

                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required autocomplete="new-password">
                            <div class="strength-meter" id="strengthMeter" hidden>
                                <div class="strength-bar"><span id="strengthFill"></span></div>
                                <span class="strength-label" id="strengthLabel"></span>
                            </div>
                            <small>Must be at least 8 characters, with a letter and a number.</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                            <span class="match-indicator" id="matchIndicator"></span>
                        </div>

                        <div class="edit-actions edit-actions-wrap">
                            <button type="submit" class="btn-primary">Update Password</button>
                            <button type="button" class="btn-secondary cancel-edit" data-target="passwordCard">Cancel</button>
                            <button type="button" class="forgot-link" id="openForgotModal">Forgot your current password?</button>
                        </div>
                    </form>
                </div>
            </section>

        </div>
    </div>

    <!-- ===================== FORGOT PASSWORD MODAL ===================== -->
    <div id="forgotModal" class="aset-modal-overlay" style="display:none;">
        <div class="aset-modal-card">
            <button type="button" class="aset-modal-close" id="closeForgotModal" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>
            </button>

            <div id="forgotStepEmail">
                <h2>Reset your password</h2>
                <p>Enter the email on your account and we'll send you a 6-digit code.</p>

                <div class="form-group">
                    <label for="forgotEmail">Email</label>
                    <input type="email" id="forgotEmail" placeholder="you@example.com" autocomplete="email">
                </div>

                <div id="forgotEmailMsg" class="aset-modal-msg"></div>
                <button type="button" class="btn-primary btn-inline" id="sendCodeBtn">Send Code</button>
            </div>

            <div id="forgotStepReset" style="display:none;">
                <h2>Check your email</h2>
                <p>Enter the code we sent, then choose a new password.</p>

                <div class="form-group">
                    <label for="forgotCode">6-digit code</label>
                    <input type="text" id="forgotCode" class="code-input" maxlength="6" placeholder="000000" autocomplete="one-time-code">
                </div>

                <div class="form-group">
                    <label for="forgotNewPassword">New Password</label>
                    <input type="password" id="forgotNewPassword" autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label for="forgotConfirmPassword">Confirm New Password</label>
                    <input type="password" id="forgotConfirmPassword" autocomplete="new-password">
                </div>

                <div id="forgotResetMsg" class="aset-modal-msg"></div>
                <button type="button" class="btn-primary btn-inline" id="resetPasswordBtn">Reset Password</button>

                <div class="resend-row">
                    <button type="button" id="resendResetBtn" class="resend-btn" disabled>Resend Code (60s)</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Tab switching ===== -->
    <script>
    (function() {
        const tabs = document.querySelectorAll('.aset-tab');
        const panels = document.querySelectorAll('.aset-panel');
        const initialTab = <?php echo json_encode($activeTab); ?>;

        function activate(tabName) {
            tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === tabName));
            panels.forEach(p => p.classList.toggle('active', p.dataset.panel === tabName));
            if (history.replaceState) history.replaceState(null, '', '#' + tabName);
        }

        tabs.forEach(tab => tab.addEventListener('click', () => activate(tab.dataset.tab)));

        const hashTab = window.location.hash.replace('#', '');
        const validTabs = ['profile', 'account', 'security'];
        const startTab = validTabs.includes(initialTab) ? initialTab : (validTabs.includes(hashTab) ? hashTab : 'profile');
        activate(startTab);
    })();
    </script>

    <!-- ===== View / Edit toggle for each card ===== -->
    <script>
    (function() {
        function openEdit(id) {
            const card = document.getElementById(id);
            if (!card) return;
            card.querySelector('.field-view')?.setAttribute('hidden', '');
            card.querySelector('.field-edit')?.removeAttribute('hidden');
            card.querySelector('.edit-btn')?.setAttribute('hidden', '');
        }
        function closeEdit(id) {
            const card = document.getElementById(id);
            if (!card) return;
            card.querySelector('.field-view')?.removeAttribute('hidden');
            card.querySelector('.field-edit')?.setAttribute('hidden', '');
            card.querySelector('.edit-btn')?.removeAttribute('hidden');
        }

        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', () => openEdit(btn.dataset.target));
        });
        document.querySelectorAll('.cancel-edit').forEach(btn => {
            btn.addEventListener('click', () => closeEdit(btn.dataset.target));
        });
    })();
    </script>

    <!-- ===== Password strength + confirm-match ===== -->
    <script>
    (function() {
        const passwordInput = document.getElementById('new_password');
        const confirmInput = document.getElementById('confirm_password');
        const meter = document.getElementById('strengthMeter');
        const fill = document.getElementById('strengthFill');
        const label = document.getElementById('strengthLabel');
        const matchIndicator = document.getElementById('matchIndicator');

        function scorePassword(pwd) {
            let score = 0;
            if (pwd.length >= 8) score++;
            if (pwd.length >= 12) score++;
            if (/[a-z]/.test(pwd) && /[A-Z]/.test(pwd)) score++;
            if (/[0-9]/.test(pwd)) score++;
            if (/[^A-Za-z0-9]/.test(pwd)) score++;
            return score;
        }

        function updateStrength() {
            const pwd = passwordInput.value;
            if (!pwd) { meter.hidden = true; return; }
            meter.hidden = false;

            const score = scorePassword(pwd);
            let level, colorClass;
            if (score <= 1) { level = 'Very weak'; colorClass = 'strength-weak'; }
            else if (score === 2) { level = 'Weak'; colorClass = 'strength-weak'; }
            else if (score === 3) { level = 'Medium'; colorClass = 'strength-medium'; }
            else { level = 'Strong'; colorClass = 'strength-strong'; }

            fill.style.width = Math.min(100, (score / 5) * 100) + '%';
            fill.className = colorClass;
            label.textContent = level;
            label.className = 'strength-label ' + colorClass;
            updateMatch();
        }

        function updateMatch() {
            if (!confirmInput.value) { matchIndicator.textContent = ''; matchIndicator.className = 'match-indicator'; return; }
            if (confirmInput.value === passwordInput.value) {
                matchIndicator.textContent = 'Passwords match';
                matchIndicator.className = 'match-indicator match-ok';
            } else {
                matchIndicator.textContent = "Passwords don't match";
                matchIndicator.className = 'match-indicator match-bad';
            }
        }

        if (passwordInput && confirmInput) {
            passwordInput.addEventListener('input', updateStrength);
            confirmInput.addEventListener('input', updateMatch);
        }
    })();
    </script>

    <!-- ===== Forgot password modal logic ===== -->
    <script>
    (function() {
        const CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token']); ?>;
        const AJAX_URL = 'account-settings-ajax.php';

        const modal = document.getElementById('forgotModal');
        const openBtn = document.getElementById('openForgotModal');
        const closeBtn = document.getElementById('closeForgotModal');

        const stepEmail = document.getElementById('forgotStepEmail');
        const stepReset = document.getElementById('forgotStepReset');

        const emailInput = document.getElementById('forgotEmail');
        const emailMsg = document.getElementById('forgotEmailMsg');
        const sendCodeBtn = document.getElementById('sendCodeBtn');

        const codeInput = document.getElementById('forgotCode');
        const newPwInput = document.getElementById('forgotNewPassword');
        const confirmPwInput = document.getElementById('forgotConfirmPassword');
        const resetMsg = document.getElementById('forgotResetMsg');
        const resetBtn = document.getElementById('resetPasswordBtn');
        const resendBtn = document.getElementById('resendResetBtn');

        let currentEmail = '';
        let timer = 60;
        let countdownInterval;

        function openModal() {
            modal.style.display = 'flex';
            requestAnimationFrame(() => modal.classList.add('aset-show'));
            resetModalState();
        }

        function closeModal() {
            modal.classList.remove('aset-show');
            setTimeout(() => { modal.style.display = 'none'; }, 200);
            clearInterval(countdownInterval);
        }

        function resetModalState() {
            stepEmail.style.display = 'block';
            stepReset.style.display = 'none';
            emailInput.value = '';
            codeInput.value = '';
            newPwInput.value = '';
            confirmPwInput.value = '';
            emailMsg.textContent = '';
            resetMsg.textContent = '';
        }

        function startTimer() {
            resendBtn.disabled = true;
            timer = 60;
            resendBtn.innerText = `Resend Code (${timer}s)`;
            clearInterval(countdownInterval);
            countdownInterval = setInterval(() => {
                timer--;
                resendBtn.innerText = `Resend Code (${timer}s)`;
                if (timer <= 0) {
                    clearInterval(countdownInterval);
                    resendBtn.innerText = "Resend Code";
                    resendBtn.disabled = false;
                }
            }, 1000);
        }

        function setMsg(el, text, isError) {
            el.textContent = text;
            el.className = 'aset-modal-msg ' + (isError ? 'aset-msg-error' : 'aset-msg-success');
        }

        async function sendCode(isResend) {
            const email = (isResend ? currentEmail : emailInput.value).trim();
            const msgEl = isResend ? resetMsg : emailMsg;

            if (!email) {
                setMsg(emailMsg, 'Please enter your email.', true);
                return;
            }

            const btn = isResend ? resendBtn : sendCodeBtn;
            btn.disabled = true;
            setMsg(msgEl, isResend ? 'Sending new code…' : 'Sending code…', false);

            try {
                const res = await fetch(AJAX_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'send_reset_code', email, csrf_token: CSRF_TOKEN })
                });
                const data = await res.json();

                if (data.success) {
                    currentEmail = email;
                    setMsg(msgEl, data.message, false);
                    if (!isResend) {
                        stepEmail.style.display = 'none';
                        stepReset.style.display = 'block';
                    }
                    startTimer();
                } else {
                    setMsg(msgEl, data.message, true);
                    if (!isResend) btn.disabled = false;
                }
            } catch (err) {
                setMsg(msgEl, 'Network error. Please try again.', true);
                btn.disabled = false;
            }
        }

        async function resetPassword() {
            const code = codeInput.value.trim();
            const newPw = newPwInput.value;
            const confirmPw = confirmPwInput.value;

            if (!code || !newPw || !confirmPw) {
                setMsg(resetMsg, 'Please fill in all fields.', true);
                return;
            }
            if (newPw !== confirmPw) {
                setMsg(resetMsg, 'Passwords do not match.', true);
                return;
            }

            resetBtn.disabled = true;
            setMsg(resetMsg, 'Resetting your password…', false);

            try {
                const res = await fetch(AJAX_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'reset_password',
                        email: currentEmail,
                        code,
                        new_password: newPw,
                        confirm_password: confirmPw,
                        csrf_token: CSRF_TOKEN
                    })
                });
                const data = await res.json();

                if (data.success) {
                    setMsg(resetMsg, data.message + ' Redirecting…', false);
                    setTimeout(() => { window.location.href = 'account-settings.php#security'; }, 1500);
                } else {
                    setMsg(resetMsg, data.message, true);
                    resetBtn.disabled = false;
                }
            } catch (err) {
                setMsg(resetMsg, 'Network error. Please try again.', true);
                resetBtn.disabled = false;
            }
        }

        openBtn.addEventListener('click', openModal);
        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

        sendCodeBtn.addEventListener('click', () => sendCode(false));
        resendBtn.addEventListener('click', () => sendCode(true));
        resetBtn.addEventListener('click', resetPassword);
    })();
    </script>

<?php require_once 'includes/layout_footer.php'; ?>