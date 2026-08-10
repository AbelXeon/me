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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if ($user['account_status'] === 'pending') {
            $error = "Please verify your email before logging in.";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['logged_in'] = true;

            header('Location: dashboard.php');
            exit();
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Social Manager</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="split-screen">
        <!-- LEFT SIDE: FORM -->
        <div class="left-side">
            <div class="form-wrapper">
                <div class="logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2a10 10 0 1 0 10 10"/><path d="M12 6a6 6 0 1 0 6 6"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/>
                    </svg>
                    <span>Social Manager</span>
                </div>

                <div class="header-text">
                    <h1>Welcome Back</h1>
                    <p>Enter your credentials to manage your platforms.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['registered'])): ?>
                    <div class="alert alert-success">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        Email verified! You can now login.
                    </div>
                <?php endif; ?>

                <form method="POST" class="login-form">
                    <input type="hidden" name="login_submit" value="1">
                    <div class="input-group">
                        <label>Username</label>
                        <div class="input-icon-wrapper">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <input type="text" name="username" placeholder="Enter your username" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <div class="label-row">
                            <label>Password</label>
                            <a href="javascript:void(0)" onclick="openForgotModal()" class="forgot-link">Forgot?</a>
                        </div>
                        <div class="input-icon-wrapper">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" name="password" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">Sign In</button>
                </form>

                <div class="footer-link">
                    Don't have an account? <a href="register.php">Create one</a>
                </div>
            </div>
        </div>

        <!-- RIGHT SIDE: VISUAL -->
        <div class="right-side">
            <div class="visual-content">
                <h2>The smartest way to manage your social presence.</h2>
                <p>One dashboard for all your platforms. Schedule, analyze, and grow your audience without the stress.</p>
                
                <div class="mini-dashboard-preview">
                    <div class="preview-line" style="width: 80%"></div>
                    <div class="preview-line" style="width: 60%"></div>
                    <div class="preview-line" style="width: 90%"></div>
                    <div class="preview-dots">
                        <div class="dot"></div><div class="dot"></div><div class="dot"></div>
                    </div>
                </div>
            </div>
            <div class="circles">
                <div class="circle c1"></div>
                <div class="circle c2"></div>
            </div>
        </div>
    </div>

    <!-- FORGOT PASSWORD MODAL -->
    <div id="forgotModal" class="modal-overlay">
        <div class="modal-card">
            <button class="modal-close" onclick="closeForgotModal()">&times;</button>
            <div id="step1">
                <h3>Reset Password</h3>
                <p>We will send a 6-digit verification code to your Gmail address.</p>
                <div class="input-group" style="margin-top: 20px;">
                    <input type="email" id="forgot_email" placeholder="example@gmail.com">
                </div>
                <button type="button" onclick="sendResetCode()" class="btn-primary">Send Reset Code</button>
            </div>
            <div id="step2" class="hidden">
                <h3>Verification</h3>
                <p>Enter the code sent to your email.</p>
                <div class="input-group" style="margin-top: 20px;">
                    <input type="text" id="reset_code" placeholder="000000" maxlength="6" style="text-align:center; letter-spacing: 4px; font-weight: bold;">
                </div>
                <div class="input-group">
                    <input type="password" id="new_pass" placeholder="New Password">
                </div>
                <button type="button" onclick="verifyAndReset()" class="btn-primary">Update Password</button>
            </div>
        </div>
    </div>

    <script>
    function openForgotModal() { document.getElementById('forgotModal').style.display = 'flex'; }
    function closeForgotModal() { document.getElementById('forgotModal').style.display = 'none'; }

    function sendResetCode() {
        const email = document.getElementById('forgot_email').value;
        const btn = document.querySelector("#step1 .btn-primary");
        if(!email) { alert("Please enter your email"); return; }
        btn.innerText = "Sending...";
        btn.disabled = true;

        fetch('auth_ajax.php?action=send_reset&email=' + encodeURIComponent(email))
        .then(response => response.json())
        .then(data => {
            btn.innerText = "Send Reset Code";
            btn.disabled = false;
            if(data.success) {
                document.getElementById('step1').classList.add('hidden');
                document.getElementById('step2').classList.remove('hidden');
            } else { alert(data.message); }
        })
        .catch(error => {
            btn.innerText = "Send Reset Code";
            btn.disabled = false;
            alert("Connection error.");
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
                alert('Success!'); window.location.href = 'login.php'; 
            } else { alert(data.message); }
        });
    }
    </script>
</body>
</html>