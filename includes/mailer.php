<?php
/**
 * Email sending — driver-based, fully controlled via .env.
 *
 * Add to your .env:
 *
 *   MAIL_DRIVER=brevo          # or: smtp
 *
 *   # Used by BOTH drivers as the "From" address:
 *   MAIL_FROM_ADDRESS=you@example.com
 *   MAIL_FROM_NAME="Social Manager"
 *
 *   # Only needed when MAIL_DRIVER=brevo
 *   BREVO_API_KEY=xxxxx
 *
 *   # Only needed when MAIL_DRIVER=smtp (e.g. your Ethereal creds for local dev)
 *   MAIL_HOST=smtp.ethereal.email
 *   MAIL_PORT=587
 *   MAIL_USERNAME=xxxx@ethereal.email
 *   MAIL_PASSWORD=xxxxx
 *   MAIL_ENCRYPTION=tls        # tls (STARTTLS, usually port 587) or ssl (port 465)
 *
 * On Render: set MAIL_DRIVER=brevo + BREVO_API_KEY + MAIL_FROM_ADDRESS in the
 * dashboard env vars, exactly like you're already doing.
 *
 * Locally: set MAIL_DRIVER=smtp and point MAIL_HOST/USERNAME/PASSWORD at
 * Ethereal (or Mailtrap, or anything else) — nothing in the code changes.
 */

/**
 * Reads an env var the same way the rest of the app does
 * (getenv -> $_ENV -> $_SERVER -> default).
 */
function envVar($key, $default = '') {
    $value = getenv($key);
    if ($value !== false && $value !== '') return $value;
    if (!empty($_ENV[$key])) return $_ENV[$key];
    if (!empty($_SERVER[$key])) return $_SERVER[$key];
    return $default;
}

/**
 * Public entry point — unchanged signature, so nothing calling
 * sendCodeEmail() elsewhere in the app needs to change.
 */
function sendCodeEmail($toEmail, $toName, $code, $purpose = 'email_verify') {
    $subject = ($purpose === 'email_verify') ? "Verify Account: $code" : "Reset Password: $code";
    $title   = ($purpose === 'email_verify') ? "Welcome to Social Manager!" : "Password Reset Request";
    $text    = ($purpose === 'email_verify') ? "Use this code to complete your registration:" : "Use this code to reset your password:";

    $htmlContent = "
        <div style='font-family:sans-serif; padding:20px; border:1px solid #eee; border-radius:10px; text-align:center;'>
            <h2>$title</h2>
            <p>$text</p>
            <div style='font-size:32px; font-weight:bold; color:#007bff; letter-spacing:5px; margin:20px;'>$code</div>
            <p>This code expires in 15 minutes.</p>
        </div>";

    $driver = strtolower(envVar('MAIL_DRIVER', 'brevo'));

    if ($driver === 'smtp') {
        return sendViaSmtp($toEmail, $toName, $subject, $htmlContent);
    }

    return sendViaBrevo($toEmail, $toName, $subject, $htmlContent);
}

/* ============================================================
   Driver: Brevo API  (what you already have on Render)
   ============================================================ */
function sendViaBrevo($toEmail, $toName, $subject, $htmlContent) {
    $apiKey = envVar('BREVO_API_KEY');
    if (empty($apiKey)) {
        error_log("Mailer(brevo) Error: BREVO_API_KEY is not set!");
        return false;
    }

    $senderEmail = envVar('MAIL_FROM_ADDRESS');
    $senderName  = envVar('MAIL_FROM_NAME', 'Social Manager');

    if (empty($senderEmail)) {
        error_log("Mailer(brevo) Error: MAIL_FROM_ADDRESS is not set!");
        return false;
    }

    $data = [
        "sender"      => ["name" => $senderName, "email" => $senderEmail],
        "to"          => [["email" => $toEmail, "name" => $toName]],
        "subject"     => $subject,
        "htmlContent" => $htmlContent,
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'api-key: ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log("Mailer(brevo) cURL Error: " . $curlErr);
        return false;
    }
    if ($httpCode >= 400) {
        error_log("Mailer(brevo) API Error (HTTP {$httpCode}): " . $response);
        return false;
    }

    return true;
}

/* ============================================================
   Driver: plain SMTP (no libraries) — for Ethereal / local dev,
   or any real SMTP server if you ever need one.
   Supports STARTTLS (port 587) and implicit SSL (port 465),
   with AUTH LOGIN.
   ============================================================ */
function sendViaSmtp($toEmail, $toName, $subject, $htmlContent) {
    $host       = envVar('MAIL_HOST');
    $port       = (int) envVar('MAIL_PORT', 587);
    $username   = envVar('MAIL_USERNAME');
    $password   = envVar('MAIL_PASSWORD');
    $encryption = strtolower(envVar('MAIL_ENCRYPTION', 'tls')); // tls | ssl | none

    $fromEmail = envVar('MAIL_FROM_ADDRESS');
    $fromName  = envVar('MAIL_FROM_NAME', 'Social Manager');

    if (empty($host) || empty($username) || empty($password) || empty($fromEmail)) {
        error_log("Mailer(smtp) Error: MAIL_HOST/MAIL_USERNAME/MAIL_PASSWORD/MAIL_FROM_ADDRESS must all be set in .env");
        return false;
    }

    $timeout = 15;
    $transport = ($encryption === 'ssl') ? 'ssl://' : '';

    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        error_log("Mailer(smtp) Connection Error: [{$errno}] {$errstr}");
        return false;
    }
    stream_set_timeout($socket, $timeout);

    try {
        smtpExpect($socket, '220'); // server greeting

        $localDomain = 'localhost';
        smtpCommand($socket, "EHLO {$localDomain}", '250');

        if ($encryption === 'tls') {
            smtpCommand($socket, "STARTTLS", '220');
            $cryptoOk = @stream_socket_enable_crypto(
                $socket,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
            );
            if (!$cryptoOk) {
                throw new Exception("STARTTLS negotiation failed.");
            }
            // Must re-greet after STARTTLS
            smtpCommand($socket, "EHLO {$localDomain}", '250');
        }

        smtpCommand($socket, "AUTH LOGIN", '334');
        smtpCommand($socket, base64_encode($username), '334');
        smtpCommand($socket, base64_encode($password), '235');

        smtpCommand($socket, "MAIL FROM:<{$fromEmail}>", '250');
        smtpCommand($socket, "RCPT TO:<{$toEmail}>", ['250', '251']);
        smtpCommand($socket, "DATA", '354');

        $boundaryHeaders = [
            "From: " . mimeEncodeName($fromName) . " <{$fromEmail}>",
            "To: " . mimeEncodeName($toName) . " <{$toEmail}>",
            "Subject: " . mimeEncodeHeader($subject),
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
            "Date: " . date('r'),
        ];

        // Dot-stuff any line that starts with a lone "." per SMTP spec
        $body = preg_replace('/^\./m', '..', $htmlContent);

        $message = implode("\r\n", $boundaryHeaders) . "\r\n\r\n" . $body . "\r\n.";
        fwrite($socket, $message . "\r\n");
        smtpExpect($socket, '250');

        smtpCommand($socket, "QUIT", '221');
        fclose($socket);

        return true;

    } catch (Exception $e) {
        error_log("Mailer(smtp) Error: " . $e->getMessage());
        if (is_resource($socket)) fclose($socket);
        return false;
    }
}

/* ---- tiny SMTP helpers (used only by sendViaSmtp) ---- */

function smtpReadResponse($socket) {
    $data = '';
    while (($line = fgets($socket, 515)) !== false) {
        $data .= $line;
        // Multi-line responses look like "250-...", final line is "250 ..."
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    return $data;
}

function smtpExpect($socket, $expectedCode) {
    $response = smtpReadResponse($socket);
    $codes = (array) $expectedCode;
    $actual = substr($response, 0, 3);
    if (!in_array($actual, $codes, true)) {
        throw new Exception("Unexpected SMTP response (wanted " . implode('/', $codes) . "): " . trim($response));
    }
    return $response;
}

function smtpCommand($socket, $command, $expectedCode) {
    fwrite($socket, $command . "\r\n");
    return smtpExpect($socket, $expectedCode);
}

function mimeEncodeHeader($text) {
    // Simple UTF-8 header encoding, safe even for plain ASCII subjects
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function mimeEncodeName($name) {
    if ($name === '' || $name === null) return '';
    return preg_match('/[^\x20-\x7E]/', $name) ? mimeEncodeHeader($name) : $name;
}