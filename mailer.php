
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

function sendResetEmail($recipientEmail, $recipientName, $resetLink)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        // Your Gmail account
        $mail->Username   = 'saynesalthea8@gmail.com';

        // Your Google App Password
        $mail->Password   = 'dbephmhpuvqfbblx';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($mail->Username, 'DocuBiz');
        $mail->addAddress($recipientEmail, $recipientName);

        $mail->isHTML(true);
        $mail->Subject = 'DocuBiz - Password Reset Request';

        $mail->Body = "
            <h2>Password Reset Request</h2>
            <p>Hello " . htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8') . ",</p>
            <p>We received a request to reset your DocuBiz password.</p>
            <p>Click the button below to create a new password:</p>
            <a href='" . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . "'>
                Reset My Password
            </a>
            <p>This link will expire in 30 minutes.</p>
            <p>If you did not request this, you can ignore this email.</p>
        ";

        $mail->AltBody = "Reset your DocuBiz password using this link: " . $resetLink;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
