<?php
session_start();
require_once 'config/database.php';
require_once 'includes/mailer.php';

$error = '';
$show_modal = false;

// --- CSRF TOKEN SETUP ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- RATE LIMITER ---
if (!isset($_SESSION['register_attempts'])) {
    $_SESSION['register_attempts'] = 0;
}
$rate_limited = $_SESSION['register_attempts'] >= 5;

// HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // STEP 1: INITIAL REGISTRATION
    if (isset($_POST['register_step'])) {
        if ($rate_limited) {
            $error = 'Too many attempts. Please try again later.';
        } elseif (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $error = 'Invalid form submission. Refresh and try again.';
        } else {
            $_SESSION['register_attempts']++;

            $first_name = trim($_POST['first_name']);
            $last_name  = trim($_POST['last_name']);
            $username   = trim($_POST['username']);
            $email      = trim($_POST['email']);
            $password   = $_POST['password'];
            $confirm    = $_POST['confirm_password'];

            if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password)) {
                $error = 'Please fill in all required fields';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with(strtolower($email), '@gmail.com')) {
                $error = 'Please enter a valid @gmail.com address';
            } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
                $error = 'Username must be 3-30 characters, letters/numbers/underscore only';
            } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
                $error = 'Password must be 8+ characters and include a letter and a number';
            } elseif ($password !== $confirm) {
                $error = 'Passwords do not match';
            } else {
                $conn = getDBConnection();

                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);

                if ($stmt->fetch()) {
                    $error = 'Username or email already in use';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, username, email, password, account_status) VALUES (?, ?, ?, ?, ?, 'pending')");
                    $stmt->execute([$first_name, $last_name, $username, $email, $hashed_password]);

                    $userId = $conn->lastInsertId();
                    $code = rand(100000, 999999);
                    $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                    $stmt = $conn->prepare("INSERT INTO email_verification (user_id, email, code, purpose, expires_at) VALUES (?, ?, ?, 'email_verify', ?)");
                    $stmt->execute([$userId, $email, $code, $expires]);

                    sendCodeEmail($email, $first_name, $code, 'email_verify');

                    $_SESSION['temp_user_id'] = $userId;
                    $show_modal = true;
                }
            }
        }
    } 
    
    // STEP 2: CODE VERIFICATION
    elseif (isset($_POST['verify_step'])) {
        $entered_code = trim($_POST['verify_code']);
        $userId = $_SESSION['temp_user_id'] ?? null;

        if (!$userId) {
            $error = "Session expired. Please try registering again.";
        } else {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT id FROM email_verification WHERE user_id = ? AND code = ? AND is_used = 0 AND expires_at > CURRENT_TIMESTAMP");
            $stmt->execute([$userId, $entered_code]);
            
            if ($stmt->fetch()) {
                $conn->prepare("UPDATE email_verification SET is_used = 1, verified_at = CURRENT_TIMESTAMP WHERE user_id = ? AND code = ?")->execute([$userId, $entered_code]);
                $conn->prepare("UPDATE users SET account_status = 'active' WHERE id = ?")->execute([$userId]);
                
                unset($_SESSION['temp_user_id']);
                unset($_SESSION['register_attempts']);
                header('Location: login.php?registered=1');
                exit();
            } else {
                $error = "Invalid or expired verification code.";
                $show_modal = true;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - LEYKUN Social Media Management</title>
    <link rel="stylesheet" href="assets/css/register.css">
</head>
<body>

    <div class="register-shell">

        <!-- ===================== LEFT: ONBOARDING PANEL ===================== -->
        <div class="onboarding-panel">
            <div class="onboarding-glow onboarding-glow-1"></div>
            <div class="onboarding-glow onboarding-glow-2"></div>

            <div class="onboarding-inner">
                <div class="brand-block">
                    <div class="brand-mark">L</div>
                    <div>
                        <div class="brand-name">LEYKUN</div>
                        <div class="brand-tagline">Social Media Management</div>
                    </div>
                </div>

                <div class="onboarding-heading">
                    <h1>Get Started<br>with Us</h1>
                    <p>Complete these steps to launch your first post.</p>
                </div>

                <div class="onboarding-steps">
                    <div class="step-card active">
                        <span class="step-num">1</span>
                        <span class="step-label">Sign up your account</span>
                    </div>
                    <div class="step-card">
                        <span class="step-num">2</span>
                        <span class="step-label">Connect your platforms</span>
                    </div>
                    <div class="step-card">
                        <span class="step-num">3</span>
                        <span class="step-label">Create &amp; schedule posts</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== RIGHT: SIGNUP FORM ===================== -->
        <div class="register-panel">
            <div class="register-panel-inner">

                <div class="register-heading">
                    <h1>Sign Up Account</h1>
                    <p>Enter your details below to create your account.</p>
                </div>

                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (!$rate_limited): ?>
                <form method="POST" action="" class="register-form" id="registerForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="register_step" value="1">

                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" placeholder="e.g. John" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" placeholder="e.g. Francisco" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" placeholder="e.g. johnfrancisco" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Gmail Address</label>
                        <input type="email" name="email" placeholder="e.g. johnfrans@gmail.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <small>Must end in @gmail.com</small>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <div class="strength-meter" id="strengthMeter" hidden>
                            <div class="strength-bar"><span id="strengthFill"></span></div>
                            <span class="strength-label" id="strengthLabel"></span>
                        </div>
                        <small>Must be at least 8 characters, with a letter and a number.</small>
                    </div>

                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" id="confirmPassword" name="confirm_password" placeholder="Re-enter your password" required>
                        <span class="match-indicator" id="matchIndicator"></span>
                    </div>

                    <button type="submit" class="btn-register">Sign Up</button>
                </form>
                <?php else: ?>
                    <p class="error-message">Too many attempts. Locked.</p>
                <?php endif; ?>

                <div class="login-link">
                    <p>Already have an account? <a href="login.php">Log in</a></p>
                </div>

            </div>
        </div>

    </div>

    <!-- VERIFICATION MODAL -->
    <div id="verifyModal" class="modal-overlay" style="display: <?php echo $show_modal ? 'flex' : 'none'; ?>;">
        <div class="modal-card">
            <h2>Check Your Email</h2>
            <p>We've sent a 6-digit code to your Gmail. Please enter it below to verify your account.</p>
            
            <form method="POST">
                <input type="hidden" name="verify_step" value="1">
                <input type="text" name="verify_code" class="code-input" maxlength="6" placeholder="000000" required autofocus autocomplete="off">
                <button type="submit" class="btn-register btn-register-full">Verify &amp; Complete</button>
            </form>

            <!-- RESEND CODE SECTION WITH TIMER -->
            <div>
                <button type="button" id="resendBtn" class="resend-btn" onclick="resendCode()" disabled>Resend Code (60s)</button>
                <div id="resendMsg" class="resend-msg"></div>
            </div>
        </div>
    </div>

    <!-- ===== Password strength + confirm-match (client-side only; server still re-validates) ===== -->
    <script>
    (function() {
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirmPassword');
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
            if (!pwd) {
                meter.hidden = true;
                return;
            }
            meter.hidden = false;

            const score = scorePassword(pwd);
            let level, colorClass;
            if (score <= 1) {
                level = 'Very weak';
                colorClass = 'strength-weak';
            } else if (score === 2) {
                level = 'Weak';
                colorClass = 'strength-weak';
            } else if (score === 3) {
                level = 'Medium';
                colorClass = 'strength-medium';
            } else {
                level = 'Strong';
                colorClass = 'strength-strong';
            }

            const pct = Math.min(100, (score / 5) * 100);
            fill.style.width = pct + '%';
            fill.className = colorClass;
            label.textContent = level;
            label.className = 'strength-label ' + colorClass;

            updateMatch();
        }

        function updateMatch() {
            if (!confirmInput.value) {
                matchIndicator.textContent = '';
                matchIndicator.className = 'match-indicator';
                return;
            }
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

    <script>
        let timer = 60;
        let countdownInterval;

        function startTimer() {
            const resendBtn = document.getElementById('resendBtn');
            resendBtn.disabled = true;
            timer = 60;

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

        function resendCode() {
            const msgDiv = document.getElementById('resendMsg');
            msgDiv.style.color = "#333";
            msgDiv.innerText = "Sending new code...";

            fetch('auth_ajax.php?action=resend_registration_code')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    msgDiv.style.color = "green";
                    msgDiv.innerText = data.message;
                    startTimer(); // Restart 60s timer
                } else {
                    msgDiv.style.color = "red";
                    msgDiv.innerText = data.message;
                }
            })
            .catch(err => {
                msgDiv.style.color = "red";
                msgDiv.innerText = "Network error. Try again.";
            });
        }

        // Start timer automatically when modal opens
        <?php if ($show_modal): ?>
            window.onload = function() {
                startTimer();
            };
        <?php endif; ?>
    </script>
</body>
</html>