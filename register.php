<?php
session_start();
require_once 'config/database.php';
require_once 'includes/mailer.php';

$error = '';
$show_modal = false;

// --- YOUR ORIGINAL CSRF TOKEN SETUP ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- YOUR ORIGINAL RATE LIMITER ---
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

            // VALIDATION (Including your original checks + Gmail check)
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

                // Check if username or email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);

                if ($stmt->fetch()) {
                    $error = 'Username or email already in use';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert user as 'pending'
                    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, username, email, password, account_status) VALUES (?, ?, ?, ?, ?, 'pending')");
                    $stmt->execute([$first_name, $last_name, $username, $email, $hashed_password]);

                    $userId = $conn->lastInsertId();
                    $code = rand(100000, 999999);
                    $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                    // Save code to your email_verification table
                    $stmt = $conn->prepare("INSERT INTO email_verification (user_id, email, code, purpose, expires_at) VALUES (?, ?, ?, 'email_verify', ?)");
                    $stmt->execute([$userId, $email, $code, $expires]);

                    // Send the email via Brevo
                    sendCodeEmail($email, $first_name, $code, 'email_verify');

                    $_SESSION['temp_user_id'] = $userId;
                    $show_modal = true; // This triggers the blur/popup
                }
            }
        }
    } 
    
    // STEP 2: CODE VERIFICATION (From the Modal)
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
                // Update code to used
                $conn->prepare("UPDATE email_verification SET is_used = 1, verified_at = CURRENT_TIMESTAMP WHERE user_id = ? AND code = ?")->execute([$userId, $entered_code]);
                // Set user to active
                $conn->prepare("UPDATE users SET account_status = 'active' WHERE id = ?")->execute([$userId]);
                
                unset($_SESSION['temp_user_id']);
                unset($_SESSION['register_attempts']);
                header('Location: login.php?registered=1');
                exit();
            } else {
                $error = "Invalid or expired verification code.";
                $show_modal = true; // Keep the modal open if they fail
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
    <title>Create Account - Social Manager</title>
    <link rel="stylesheet" href="assets/css/register.css">
    <style>
        /* MODAL & BLUR STYLING */
        .modal-overlay {
            display: <?php echo $show_modal ? 'flex' : 'none'; ?>;
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px); /* This creates the blur background */
            -webkit-backdrop-filter: blur(10px);
            justify-content: center; align-items: center; z-index: 9999;
        }
        .modal-card {
            background: white; padding: 40px; border-radius: 20px; text-align: center;
            box-shadow: 0 15px 35px rgba(0,0,0,0.5); width: 90%; max-width: 400px;
        }
        .code-input {
            font-size: 32px; letter-spacing: 10px; text-align: center;
            width: 100%; margin: 25px 0; border: 2px solid #007bff; border-radius: 10px;
            padding: 10px;
        }
    </style>
</head>
<body>

    <div class="register-container">
        <div class="register-header">
            <h1>📱 Create Account</h1>
            <p>Sign up to manage your social media</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!$rate_limited): ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="register_step" value="1">

            <div class="form-row">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>

            <div class="form-group">
                <label>Gmail Address</label>
                <input type="email" name="email" placeholder="must end in @gmail.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="8+ chars, letters + numbers" required>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn-register">Create Account</button>
        </form>
        <?php else: ?>
            <p class="error-message">Too many attempts. Locked.</p>
        <?php endif; ?>

        <div class="login-link">
            <p>Already have an account? <a href="login.php">Log in</a></p>
        </div>
    </div>

    <!-- VERIFICATION MODAL (The Popup) -->
    <div id="verifyModal" class="modal-overlay">
        <div class="modal-card">
            <h2>Check Your Email</h2>
            <p>We've sent a 6-digit code to your Gmail. Please enter it below to verify your account.</p>
            <form method="POST">
                <input type="hidden" name="verify_step" value="1">
                <input type="text" name="verify_code" class="code-input" maxlength="6" placeholder="000000" required autofocus>
                <button type="submit" class="btn-register" style="width: 100%;">Verify & Complete</button>
            </form>
        </div>
    </div>

</body>
</html>