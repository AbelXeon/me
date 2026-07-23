<?php
session_start();
require_once 'config/database.php';
require_once 'includes/mailer.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$conn = getDBConnection();

if ($action === 'resend_registration_code') {
    $userId = $_SESSION['temp_user_id'] ?? null;

    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please refresh page.']);
        exit();
    }

    // Get user details
    $stmt = $conn->prepare("SELECT email, first_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user) {
        $code = rand(100000, 999999);
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Invalidate old unused codes
        $conn->prepare("UPDATE email_verification SET is_used = 1 WHERE user_id = ? AND purpose = 'email_verify'")->execute([$userId]);

        // Insert new code
        $stmt = $conn->prepare("INSERT INTO email_verification (user_id, email, code, purpose, expires_at) VALUES (?, ?, ?, 'email_verify', ?)");
        $stmt->execute([$userId, $user['email'], $code, $expires]);

        // Send email
        $sent = sendCodeEmail($user['email'], $user['first_name'], $code, 'email_verify');

        if ($sent) {
            echo json_encode(['success' => true, 'message' => 'New code sent successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send email. Check Brevo API Key & Sender Email.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
    }
    exit();
}