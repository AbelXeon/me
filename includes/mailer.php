<?php
function sendCodeEmail($toEmail, $toName, $code, $purpose = 'email_verify') {
    $apiKey = getenv('BREVO_API_KEY');
    
    $subject = ($purpose === 'email_verify') ? "Verify Your Account" : "Reset Your Password";
    $title = ($purpose === 'email_verify') ? "Welcome to Social Manager!" : "Password Reset Request";
    $text = ($purpose === 'email_verify') ? "Use this code to complete your registration:" : "Use this code to reset your password:";

    $data = [
        "sender" => ["name" => "Social Manager", "email" => "noreply@gmail.com"],
        "to" => [["email" => $toEmail, "name" => $toName]],
        "subject" => $subject,
        "htmlContent" => "
            <div style='font-family:sans-serif; padding:20px; border:1px solid #eee; border-radius:10px; text-align:center;'>
                <h2>$title</h2>
                <p>$text</p>
                <div style='font-size:32px; font-weight:bold; color:#007bff; letter-spacing:5px; margin:20px;'>$code</div>
                <p>This code expires in 15 minutes.</p>
            </div>"
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'api-key: ' . $apiKey,
        'Content-Type: application/json'
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}
?>