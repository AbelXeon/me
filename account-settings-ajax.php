<?php
// account-settings-ajax.php
// Handles the "Forgot your current password?" modal on account-settings.php.
// Kept as its own file so it doesn't touch the existing auth_ajax.php used
// during registration.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/mailer.php';

header('Content-Type: application/json');

function respond($success, $message, $extra = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(false, 'Invalid request.');
}

$csrf_token = $input['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    respond(false, 'Invalid or expired session. Please refresh the page and try again.');
}

$action = $input['action'] ?? '';
$conn = getDBConnection();

if ($action === 'send_reset_code') {

    $email = trim($input['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(false, 'Please enter a valid email address.');
    }

    // 60-second cooldown, enforced server-side (client-side timer is just UX)
    if (!empty($_SESSION['pwreset_last_sent']) && (time() - $_SESSION['pwreset_last_sent']) < 60) {
        $remaining = 60 - (time() - $_SESSION['pwreset_last_sent']);
        respond(false, "Please wait {$remaining}s before requesting another code.", ['remaining' => $remaining]);
    }

    $stmt = $conn->prepare("SELECT id, first_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Always mark the cooldown, whether or not the email exists —
    // this avoids leaking which emails are registered via response timing.
    $_SESSION['pwreset_last_sent'] = time();

    if ($user) {
        $code = rand(100000, 999999);
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $stmt = $conn->prepare("INSERT INTO email_verification (user_id, email, code, purpose, expires_at) VALUES (?, ?, ?, 'password_reset', ?)");
        $stmt->execute([$user['id'], $email, $code, $expires]);

        sendCodeEmail($email, $user['first_name'], $code, 'password_reset');
    }

    // Same message either way — don't reveal whether the account exists.
    respond(true, "If an account exists with that email, we've sent a reset code.");

} elseif ($action === 'reset_password') {

    $email = trim($input['email'] ?? '');
    $code = trim($input['code'] ?? '');
    $new_password = $input['new_password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';

    if (empty($email) || empty($code) || empty($new_password) || empty($confirm_password)) {
        respond(false, 'Please fill in all fields.');
    }
    if ($new_password !== $confirm_password) {
        respond(false, 'Passwords do not match.');
    }
    if (strlen($new_password) < 8 || !preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        respond(false, 'Password must be 8+ characters and include a letter and a number.');
    }

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        respond(false, 'Invalid or expired code.');
    }

    $stmt = $conn->prepare("SELECT id FROM email_verification WHERE user_id = ? AND code = ? AND purpose = 'password_reset' AND is_used = 0 AND expires_at > CURRENT_TIMESTAMP");
    $stmt->execute([$user['id'], $code]);
    $verification = $stmt->fetch();

    if (!$verification) {
        respond(false, 'Invalid or expired code.');
    }

    if (password_verify($new_password, $user['password'])) {
        respond(false, 'New password must be different from your current password.');
    }

    $hashed = password_hash($new_password, PASSWORD_DEFAULT);

    $conn->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$hashed, $user['id']]);

    $conn->prepare("UPDATE email_verification SET is_used = 1, verified_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$verification['id']]);

    unset($_SESSION['pwreset_last_sent']);

    respond(true, 'Your password has been reset.');

} else {
    respond(false, 'Unknown action.');
}