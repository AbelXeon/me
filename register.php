<?php
// register.php
session_start();
require_once 'config/database.php';

$error = '';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// --- CSRF token setup ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Simple session-based rate limit: 5 attempts ---
if (!isset($_SESSION['register_attempts'])) {
    $_SESSION['register_attempts'] = 0;
}

$rate_limited = $_SESSION['register_attempts'] >= 5;

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($rate_limited) {
        $error = 'Too many attempts. Please try again later.';
    } elseif (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid form submission. Please refresh and try again.';
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
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            $error = 'Username must be 3-30 characters, letters/numbers/underscore only';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'Password must include at least one letter and one number';
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

                $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, username, email, password, account_status) VALUES (?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$first_name, $last_name, $username, $email, $hashed_password]);

                // Success — clear attempts, regenerate CSRF, redirect to login
                unset($_SESSION['register_attempts']);
                unset($_SESSION['csrf_token']);
                header('Location: login.php?registered=1');
                exit();
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
    <title>Create Account - Social Media Manager</title>
    <link rel="stylesheet" href="assets/css/register.css">
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

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" required
                        value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required
                        value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="At least 8 characters, letters + numbers" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn-register">Create Account</button>
        </form>
        <?php endif; ?>

        <div class="login-link">
            <p>Already have an account? <a href="login.php">Log in</a></p>
        </div>
    </div>
</body>

</html>