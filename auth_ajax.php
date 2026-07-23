<?php
// auth_ajax.php

// 1. Force error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show HTML errors, we want JSON

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/mailer.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    $conn = getDBConnection();

    // ACTION: RESEND REGISTRATION CODE
    if ($action === 'resend_registration_code') {
        $userId = $_SESSION['temp_user_id'] ?? null;
        if (!$userId) {
            echo json_encode(['success' => false, 'message' => 'Session expired. Please register again.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT email, first_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }

        $code = rand(100000, 999999);
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $conn->prepare("UPDATE email_verification SET is_used = 1 WHERE user_id = ? AND purpose = 'email_verify'")->execute([$userId]);
        $conn->prepare("INSERT INTO email_verification (user_id, email, code, purpose, expires_at) VALUES (?, ?, ?, 'email_verify', ?)")
             ->execute([$userId, $user['email'], $code, $expires]);

        if (sendCodeEmail($user['email'], $user['first_name'], $code, 'email_verify')) {
            echo json_encode(['success' => true, 'message' => 'New code sent!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Mailer failed. Check Brevo settings.']);
        }
        exit;
    }

    // ACTION: SEND PASSWORD RESET CODE
    if ($action === 'send_reset') {
        $email = $_GET['email'] ?? '';
        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Please enter your email.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, first_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $code = rand(100000, 999999);
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Mark previous reset attempts as used
            $conn->prepare("UPDATE email_verification SET is_used = 1 WHERE email = ? AND purpose = 'password_reset'")->execute([$email]);
            
            // Insert new reset code
            $conn->prepare("INSERT INTO email_verification (user_id, email, code, purpose, expires_at) VALUES (?, ?, ?, 'password_reset', ?)")
                 ->execute([$user['id'], $email, $code, $expires]);

            if (sendCodeEmail($email, $user['first_name'], $code, 'password_reset')) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to send email. Check Brevo Key.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No account found with that email address.']);
        }
        exit;
    }

    // ACTION: COMPLETE PASSWORD RESET
    if ($action === 'complete_reset') {
        $email = $_POST['email'] ?? '';
        $code = $_POST['code'] ?? '';
        $pass = $_POST['password'] ?? '';

        if (empty($code) || empty($pass)) {
            echo json_encode(['success' => false, 'message' => 'Fill in all fields.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT * FROM email_verification WHERE email = ? AND code = ? AND purpose = 'password_reset' AND is_used = 0 AND expires_at > CURRENT_TIMESTAMP");
        $stmt->execute([$email, $code]);
        $verify = $stmt->fetch();

        if ($verify) {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $conn->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $verify['user_id']]);
            $conn->prepare("UPDATE email_verification SET is_used = 1 WHERE id = ?")->execute([$verify['id']]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired code.']);
        }
        exit;
    }

    // Add this inside the try block in auth_ajax.php

if ($action === 'change_password') {
    // 1. Ensure user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $currentInput = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';

    // 2. Fetch current password from DB
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user && password_verify($currentInput, $user['password'])) {
        // 3. Current password is correct, hash and update
        $newHashed = password_hash($newPass, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([$newHashed, $userId]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
    }
    exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}