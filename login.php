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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Social Manager</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <style>
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); backdrop-filter: blur(10px);
            justify-content: center; align-items: center; z-index: 9999;
        }
        .modal-card { background: white; padding: 40px; border-radius: 20px; width: 90%; max-width: 400px; text-align:center; }
        .hidden { display: none; }
        .btn-login { cursor: pointer; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>📱 Login</h1>
            <p>Welcome back! Please login.</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <div class="error-message" style="background:#d4edda; color:#155724; border-color:#c3e6cb;">
                Email verified! You can now login.
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="login_submit" value="1">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="btn-login">Login</button>
        </form>
        
        <div class="signup-link">
            <p><a href="javascript:void(0)" onclick="openForgotModal()">Forgot Password?</a></p>
            <p>Don't have an account? <a href="register.php">Create one</a></p>
        </div>
    </div>

    <!-- FORGOT PASSWORD MODAL -->
    <div id="forgotModal" class="modal-overlay">
        <div class="modal-card">
            <div id="step1">
                <h2>Reset Password</h2>
                <p>Enter your Gmail address.</p>
                <input type="email" id="forgot_email" style="width:100%; padding:12px; margin:20px 0; border:1px solid #ddd; border-radius:5px;" placeholder="example@gmail.com">
                <button type="button" onclick="sendResetCode()" class="btn-login">Send Code</button>
                <button type="button" onclick="closeForgotModal()" style="background:none; border:none; margin-top:15px; color:#666; cursor:pointer;">Cancel</button>
            </div>
            <div id="step2" class="hidden">
                <h2>Enter Code</h2>
                <input type="text" id="reset_code" placeholder="6-digit code" style="width:100%; padding:12px; margin-top:20px; text-align:center; font-size:20px; border:1px solid #ddd;">
                <input type="password" id="new_pass" placeholder="New Password" style="width:100%; padding:12px; margin-top:10px; border:1px solid #ddd;">
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