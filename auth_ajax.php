<?php
session_start();
require_once 'config/database.php';
require_once 'includes/mailer.php';

$action = $_GET['action'] ?? '';
$conn = getDBConnection();

if ($action === 'send_reset') {
    $email = $_GET['email'];
    $stmt = $conn->prepare("SELECT id, first_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $code = rand(100000, 999999);
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $conn->prepare("INSERT INTO email_verification (user_id, email, code, purpose, expires_at) VALUES (?, ?, ?, 'password_reset', ?)")
             ->execute([$user['id'], $email, $code, $expires]);

        sendCodeEmail($email, $user['first_name'], $code, 'password_reset');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Email not found.']);
    }
}

if ($action === 'complete_reset') {
    $email = $_POST['email'];
    $code = $_POST['code'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("SELECT * FROM email_verification WHERE email = ? AND code = ? AND purpose = 'password_reset' AND is_used = 0 AND expires_at > CURRENT_TIMESTAMP");
    $stmt->execute([$email, $code]);
    $verify = $stmt->fetch();

    if ($verify) {
        $conn->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$pass, $verify['user_id']]);
        $conn->prepare("UPDATE email_verification SET is_used = 1 WHERE id = ?")->execute([$verify['id']]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired code.']);
    }
}