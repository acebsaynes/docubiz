
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| DocuBiz Mail Configuration
|--------------------------------------------------------------------------
*/

function createDocuBizMailer()
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;

    $mail->Username   = 'saynesalthea8@gmail.com';

    // Replace this with your NEW Google App Password.
    $mail->Password   = 'dbephmhpuvqfbblx';

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom($mail->Username, 'DocuBiz');

    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';

    return $mail;
}


/*
|--------------------------------------------------------------------------
| Forgot Password Email
|--------------------------------------------------------------------------
*/

function sendResetEmail($recipientEmail, $recipientName, $resetLink)
{
    try {
        $mail = createDocuBizMailer();

        $mail->addAddress($recipientEmail, $recipientName);

        $safeName = htmlspecialchars(
            $recipientName,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeLink = htmlspecialchars(
            $resetLink,
            ENT_QUOTES,
            'UTF-8'
        );

        $mail->Subject = 'DocuBiz - Password Reset Request';

        $mail->Body = "
            <h2>Password Reset Request</h2>

            <p>Hello {$safeName},</p>

            <p>We received a request to reset your DocuBiz password.</p>

            <p>Click the button below to create a new password:</p>

            <a href='{$safeLink}'
               style='display:inline-block;
                      padding:12px 20px;
                      background:#1655ff;
                      color:white;
                      text-decoration:none;
                      border-radius:6px;'>
                Reset My Password
            </a>

            <p>This link will expire in 30 minutes.</p>

            <p>If you did not request this, you can ignore this email.</p>
        ";

        $mail->AltBody =
            "Reset your DocuBiz password using this link: " . $resetLink;

        $mail->send();

        return true;

    } catch (Exception $e) {
        error_log("Password Reset Email Error: " . $e->getMessage());
        return false;
    }
}


/*
|--------------------------------------------------------------------------
| Project and Calendar Event Reminder Email
|--------------------------------------------------------------------------
*/

function sendReminderEmail(
    $recipientEmail,
    $recipientName,
    $eventName,
    $eventDate,
    $eventTime = '',
    $location = '',
    $daysUntil = 0,
    $sourceType = 'event'
) {
    try {
        $mail = createDocuBizMailer();

        $mail->addAddress($recipientEmail, $recipientName);

        $safeName = htmlspecialchars(
            $recipientName,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeEventName = htmlspecialchars(
            $eventName,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeDate = htmlspecialchars(
            $eventDate,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeTime = htmlspecialchars(
            $eventTime ?: 'Not specified',
            ENT_QUOTES,
            'UTF-8'
        );

        $safeLocation = htmlspecialchars(
            $location ?: 'Not specified',
            ENT_QUOTES,
            'UTF-8'
        );

        $typeLabel = strtolower($sourceType) === 'project'
            ? 'Project'
            : 'Calendar Event';

        if ($daysUntil <= 0) {
            $reminderMessage = "This is a reminder that your {$typeLabel} is scheduled for today!";
            $subjectLabel = "Today";
        } elseif ($daysUntil === 1) {
            $reminderMessage = "Your {$typeLabel} is scheduled for tomorrow!";
            $subjectLabel = "Tomorrow";
        } else {
            $reminderMessage = "Your {$typeLabel} is coming up in {$daysUntil} days.";
            $subjectLabel = "In {$daysUntil} Days";
        }

        $mail->Subject = "DocuBiz - {$typeLabel} Reminder: {$subjectLabel}";

        $mail->Body = "
            <div style='font-family:Arial,sans-serif;
                        max-width:600px;
                        margin:auto;
                        color:#263445;'>

                <h2 style='color:#1655ff;'>DocuBiz Reminder</h2>

                <p>Hello {$safeName},</p>

                <p>{$reminderMessage}</p>

                <div style='background:#f3f6fb;
                            padding:20px;
                            border-radius:10px;'>

                    <h3 style='margin-top:0;'>{$safeEventName}</h3>

                    <p><strong>Type:</strong> {$typeLabel}</p>

                    <p><strong>Date:</strong> {$safeDate}</p>

                    <p><strong>Time:</strong> {$safeTime}</p>

                    <p><strong>Location:</strong> {$safeLocation}</p>

                </div>

                <p>Please make the necessary preparations.</p>

                <p>Thank you,<br><strong>DocuBiz Team</strong></p>

            </div>
        ";

        $mail->AltBody =
            "Hello {$recipientName},\n\n" .
            "{$reminderMessage}\n\n" .
            "Name: {$eventName}\n" .
            "Type: {$typeLabel}\n" .
            "Date: {$eventDate}\n" .
            "Time: {$eventTime}\n" .
            "Location: {$location}\n\n" .
            "DocuBiz Team";

        $mail->send();

        return true;

  } catch (Exception $e) {

    $errorDetails = $e->getMessage();

    if (isset($mail)) {
        $errorDetails .= " | PHPMailer: " . $mail->ErrorInfo;
    }

    error_log("Reminder Email Error: " . $errorDetails);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "REMINDER EMAIL ERROR: " . $errorDetails . PHP_EOL);
    }

    return false;
}
}

