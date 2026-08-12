<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';
require_once 'includes/auth_check.php';

// If already logged in, go to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// A couple of cheap, standard response headers — doesn't touch your
// login logic, just tells the browser not to guess content types and
// not to let this page be framed by another site (clickjacking).
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// CSRF token for the login form itself (protects against login CSRF —
// someone tricking a logged-out visitor's browser into submitting a
// forged login request). Same pattern already used on your other pages.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid form submission. Please refresh the page and try again.';
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // Already safe: this is a parameterized query (PDO prepare + execute
        // with a bound placeholder), so the username value can never be
        // interpreted as SQL. That protection was already in place — I
        // haven't changed how this query is built.
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['account_status'] === 'pending') {
                $error = "Please verify your email before logging in.";
            } else {
                // Regenerate the session ID on privilege change (login) to
                // prevent session fixation attacks, then set the session
                // exactly as before.
                session_regenerate_id(true);

                // SETTING THE SESSION CORRECTLY TO MATCH YOUR AUTH_CHECK.PHP
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['logged_in'] = true; // THIS FIXES THE LOOP

                header('Location: dashboard.php');
                exit();
            }
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LEYKUN Social Media Management</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

    <div class="login-shell">

        <!-- ===================== LEFT: LOGIN FORM ===================== -->
        <div class="login-panel">
            <div class="login-panel-inner">

                <div class="brand-block">
                    <div class="brand-mark">L</div>
                    <div>
                        <div class="brand-name">LEYKUN</div>
                        <div class="brand-tagline">Social Media Management</div>
                    </div>
                </div>

                <div class="login-heading">
                    <h1>Login to your account</h1>
                    <p>Enter your credentials below to continue.</p>
                </div>

                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['registered'])): ?>
                    <div class="success-message">
                        Email verified! You can now login.
                    </div>
                <?php endif; ?>

                <form method="POST" class="login-form">
                    <input type="hidden" name="login_submit" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Your username" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Your password" required>
                    </div>

                    <button type="submit" class="btn-primary">Login</button>
                </form>

                <div class="signup-link">
                    <p><a href="javascript:void(0)" onclick="openForgotModal()">Forgot Password?</a></p>
                    <p>Don't have an account? <a href="register.php">Create one</a></p>
                </div>

            </div>
        </div>

        <!-- ===================== RIGHT: ANIMATED SHOWCASE ===================== -->
        <div class="showcase-panel">
            <div class="showcase-glow showcase-glow-1"></div>
            <div class="showcase-glow showcase-glow-2"></div>

            <div class="showcase-content">
                <span class="showcase-eyebrow">Great to see you again</span>
                <h2>Welcome<br>back</h2>
                <p>Log in to pick up right where you left off — schedule, publish, and track every platform from one place.</p>
            </div>

            <div class="showcase-mockup-wrap" aria-hidden="true">
                <div class="mockup-card">
                    <div class="mockup-topbar">
                        <span class="mockup-dot"></span>
                        <span class="mockup-dot"></span>
                        <span class="mockup-dot"></span>
                    </div>
                    <div class="mockup-body">
                        <div class="mockup-stat-row">
                            <div class="mockup-stat">
                                <span class="mockup-stat-label">Engagement this week</span>
                                <span class="mockup-stat-value">+24%</span>
                            </div>
                        </div>
                        <div class="mockup-bars">
                            <span style="--h:38%; animation-delay:0.05s;"></span>
                            <span style="--h:62%; animation-delay:0.12s;"></span>
                            <span style="--h:48%; animation-delay:0.19s;"></span>
                            <span style="--h:80%; animation-delay:0.26s;"></span>
                            <span style="--h:58%; animation-delay:0.33s;"></span>
                            <span style="--h:96%; animation-delay:0.40s;"></span>
                            <span style="--h:70%; animation-delay:0.47s;"></span>
                        </div>
                        <div class="mockup-chip-row">
                            <img src="https://cdn.simpleicons.org/facebook/1877F2" alt="">
                            <img src="https://cdn.simpleicons.org/instagram/E4405F" alt="">
                            <img src="https://cdn.simpleicons.org/telegram/26A5E4" alt="">
<img src="https://cdn.jsdelivr.net/npm/simple-icons@v13/icons/linkedin.svg" 
     alt="LinkedIn" 
     width="24" 
     height="24" 
     style="filter: invert(27%) sepia(89%) saturate(1844%) hue-rotate(178deg) brightness(91%) contrast(101%);">                        
         <img src="https://cdn.simpleicons.org/tiktok/000000" alt="">
                        </div>
                    </div>
                </div>

                <div class="float-chip float-chip-scheduled">
                    <span class="float-chip-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    Post scheduled
                </div>

                <div class="float-chip float-chip-platform">
                    <img src="https://cdn.simpleicons.org/tiktok/000000" alt="">
                    Connected
                </div>
            </div>
        </div>

    </div>

    <!-- FORGOT PASSWORD MODAL -->
    <div id="forgotModal" class="modal-overlay">
        <div class="modal-card">
            <div id="step1">
                <h2>Reset Password</h2>
                <p>Enter your Gmail address.</p>
                <input type="email" id="forgot_email" class="modal-input" placeholder="example@gmail.com">
                <button type="button" onclick="sendResetCode()" class="btn-login">Send Code</button>
                <button type="button" onclick="closeForgotModal()" class="btn-modal-cancel">Cancel</button>
            </div>
            <div id="step2" class="hidden">
                <h2>Enter Code</h2>
                <input type="text" id="reset_code" placeholder="6-digit code" class="modal-input modal-input-code">
                <input type="password" id="new_pass" placeholder="New Password" class="modal-input">
                <button type="button" onclick="verifyAndReset()" class="btn-login" style="margin-top:20px;">Update Password</button>
            </div>
        </div>
    </div>

    <script>
    function openForgotModal() { document.getElementById('forgotModal').style.display = 'flex'; }
    function closeForgotModal() { document.getElementById('forgotModal').style.display = 'none'; }

    function sendResetCode() {
        const email = document.getElementById('forgot_email').value;
        const btn = document.querySelector("#step1 .btn-login");
        
        if(!email) { alert("Please enter your email"); return; }
        
        btn.innerText = "Sending...";
        btn.disabled = true;

        fetch('auth_ajax.php?action=send_reset&email=' + encodeURIComponent(email))
        .then(response => {
            if (!response.ok) { throw new Error('Network response was not ok'); }
            return response.json();
        })
        .then(data => {
            btn.innerText = "Send Code";
            btn.disabled = false;
            if(data.success) {
                document.getElementById('step1').classList.add('hidden');
                document.getElementById('step2').classList.remove('hidden');
            } else { 
                alert(data.message); 
            }
        })
        .catch(error => {
            btn.innerText = "Send Code";
            btn.disabled = false;
            console.error('Error:', error);
            alert("Something went wrong. Check the browser console (F12) for details.");
        });
    }

    function verifyAndReset() {
        const email = document.getElementById('forgot_email').value;
        const code = document.getElementById('reset_code').value;
        const pass = document.getElementById('new_pass').value;
        
        if(!code || !pass) { alert("Please fill all fields"); return; }

        fetch('auth_ajax.php?action=complete_reset', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `email=${encodeURIComponent(email)}&code=${encodeURIComponent(code)}&password=${encodeURIComponent(pass)}`
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) { 
                alert('Password Updated Successfully!'); 
                window.location.href = 'login.php'; 
            } else { 
                alert(data.message); 
            }
        })
        .catch(err => alert("Error completing reset."));
    }
</script>
</body>
</html>