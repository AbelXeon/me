<?php
session_start();
require_once 'config/database.php';

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
    <title>Login</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <style>
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); backdrop-filter: blur(10px);
            justify-content: center; align-items: center; z-index: 9999;
        }
        .modal-card { background: white; padding: 40px; border-radius: 20px; width: 90%; max-width: 400px; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>📱 Login</h1>
        <?php if ($error): ?><div class="error-message"><?php echo $error; ?></div><?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="login_submit" value="1">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" class="btn-login">Login</button>
        </form>
        <p><a href="javascript:void(0)" onclick="openForgotModal()">Forgot Password?</a></p>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotModal" class="modal-overlay">
        <div class="modal-card">
            <div id="step1">
                <h2>Reset Password</h2>
                <p>Enter your registered Gmail.</p>
                <input type="email" id="forgot_email" style="width:100%; padding:10px; margin:20px 0;" placeholder="example@gmail.com">
                <button type="button" onclick="sendResetCode()" class="btn-login" style="width:100%">Send Reset Code</button>
                <button type="button" onclick="closeForgotModal()" style="background:none; border:none; margin-top:10px; cursor:pointer;">Cancel</button>
            </div>
            
            <div id="step2" class="hidden">
                <h2>Enter Code</h2>
                <input type="text" id="reset_code" placeholder="6-digit code" style="width:100%; padding:10px; margin-top:20px; text-align:center; font-size:20px;">
                <input type="password" id="new_pass" placeholder="New Password" style="width:100%; padding:10px; margin-top:10px;">
                <button type="button" onclick="verifyAndReset()" class="btn-login" style="width:100%; margin-top:20px;">Update Password</button>
            </div>
        </div>
    </div>

    <script>
        function openForgotModal() { document.getElementById('forgotModal').style.display = 'flex'; }
        function closeForgotModal() { document.getElementById('forgotModal').style.display = 'none'; }

        function sendResetCode() {
            const email = document.getElementById('forgot_email').value;
            fetch('auth_ajax.php?action=send_reset&email=' + email)
            .then(r => r.json()).then(data => {
                if(data.success) {
                    document.getElementById('step1').classList.add('hidden');
                    document.getElementById('step2').classList.remove('hidden');
                } else { alert(data.message); }
            });
        }

        function verifyAndReset() {
            const email = document.getElementById('forgot_email').value;
            const code = document.getElementById('reset_code').value;
            const pass = document.getElementById('new_pass').value;

            fetch('auth_ajax.php?action=complete_reset', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `email=${email}&code=${code}&password=${pass}`
            })
            .then(r => r.json()).then(data => {
                if(data.success) { alert('Password Updated!'); location.reload(); }
                else { alert(data.message); }
            });
        }
    </script>
</body>
</html>