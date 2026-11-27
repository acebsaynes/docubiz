
<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

function sendResponse($success, $message, $statusCode = 200)
{
    http_response_code($statusCode);

    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method.', 405);
}

$firstName = trim($_POST['firstName'] ?? '');
$lastName  = trim($_POST['lastName'] ?? '');
$email     = trim($_POST['email'] ?? '');
$message   = trim($_POST['message'] ?? '');

if (
    $firstName === '' ||
    $lastName === '' ||
    $email === '' ||
    $message === ''
) {
    sendResponse(false, 'Please complete all required fields.', 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Please enter a valid email address.', 422);
}

if (
    strlen($firstName) > 100 ||
    strlen($lastName) > 100 ||
    strlen($email) > 255 ||
    strlen($message) > 10000
) {
    sendResponse(false, 'Some fields exceed the allowed length.', 422);
}

// Save inquiry to database first.
$stmt = $conn->prepare(
    "INSERT INTO contact_messages
    (first_name, last_name, email, message)
    VALUES (?, ?, ?, ?)"
);

if (!$stmt) {
    error_log('Contact form database error: ' . $conn->error);
    sendResponse(false, 'Unable to save your inquiry right now.', 500);
}

$stmt->bind_param(
    "ssss",
    $firstName,
    $lastName,
    $email,
    $message
);

if (!$stmt->execute()) {
    error_log('Contact form insert error: ' . $stmt->error);
    $stmt->close();

    sendResponse(false, 'Unable to save your inquiry right now.', 500);
}

$stmt->close();

// Send email notification.
try {
    $mail = createDocuBizMailer();

    $fullName = $firstName . ' ' . $lastName;

    $mail->addAddress('saynesalthea8@gmail.com', 'DocuBiz');
    $mail->addReplyTo($email, $fullName);

    $mail->isHTML(true);
    $mail->Subject = 'New DocuBiz Contact Form Inquiry';

    $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(
        htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
    );

    $mail->Body = "
        <h2>New Contact Form Inquiry</h2>
        <p><strong>Name:</strong> {$safeName}</p>
        <p><strong>Email:</strong> {$safeEmail}</p>
        <p><strong>Message:</strong></p>
        <p>{$safeMessage}</p>
        <hr>
        <p>Sent through the DocuBiz landing page.</p>
    ";

    $mail->AltBody =
        "New DocuBiz Inquiry\n\n" .
        "Name: {$fullName}\n" .
        "Email: {$email}\n\n" .
        "Message:\n{$message}";

    $mail->send();

    sendResponse(
        true,
        'Your inquiry has been submitted successfully! We will get back to you soon.'
    );

} catch (Throwable $e) {

    error_log('Contact form email error: ' . $e->getMessage());

    // Inquiry is already saved, even if email notification fails.
    sendResponse(
        true,
        'Your inquiry was saved successfully, but the email notification could not be sent. Thank you!'
    );
}
