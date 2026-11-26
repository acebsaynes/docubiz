
<?php
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = 'Please enter a valid email address.';
        $messageType = 'error';

    } else {

        $stmt = $conn->prepare(
            "SELECT id, full_name, email
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $stmt->close();

        $message = 'If your email is registered, you will receive a password reset link shortly.';
        $messageType = 'success';

        if ($user) {

            // Generate secure reset token
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            // Remove previous unused tokens
            $deleteStmt = $conn->prepare(
                "DELETE FROM password_resets
                 WHERE user_id = ?
                 AND used_at IS NULL"
            );

            $deleteStmt->bind_param("i", $user['id']);
            $deleteStmt->execute();
            $deleteStmt->close();

            // Save token with a 30-minute expiration using UTC
            $insertStmt = $conn->prepare(
                "INSERT INTO password_resets
                 (user_id, token_hash, expires_at)
                 VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))"
            );

            $insertStmt->bind_param(
                "is",
                $user['id'],
                $tokenHash
            );

            if ($insertStmt->execute()) {

                $resetLink = 'http://localhost/docubiz/reset-password.php?token='
                    . urlencode($token);

                // Send reset email
                $sent = sendResetEmail(
                    $user['email'],
                    $user['full_name'],
                    $resetLink
                );

                if (!$sent) {

                    // Remove token if email delivery fails
                    $cleanup = $conn->prepare(
                        "DELETE FROM password_resets
                         WHERE token_hash = ?"
                    );

                    $cleanup->bind_param("s", $tokenHash);
                    $cleanup->execute();
                    $cleanup->close();

                    error_log("Password reset email could not be sent.");
                }

            } else {

                error_log("Password reset token could not be saved.");
            }

            $insertStmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | DocuBiz</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(115deg, #0755df, #659df4);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 40px 9%;
        }

        .brand-section {
            color: white;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }

        .brand-section h1 {
            font-size: 76px;
            font-weight: 800;
            letter-spacing: -3px;
        }

        .forgot-card {
            width: 450px;
            background: #ffffff;
            padding: 35px 36px;
            border-radius: 19px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
        }

        .forgot-card h2 {
            text-align: center;
            color: #111827;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .description {
            text-align: center;
            color: #64748b;
            font-size: 13px;
            line-height: 1.7;
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            height: 50px;
            border: 1px solid #dce3ed;
            border-radius: 9px;
            padding: 0 16px;
            font-size: 14px;
            color: #1e293b;
            outline: none;
            transition: 0.2s;
        }

        .form-group input::placeholder {
            color: #9aabc1;
        }

        .form-group input:focus {
            border-color: #3567ff;
            box-shadow: 0 0 0 3px rgba(53, 103, 255, 0.10);
        }

        .submit-btn {
            width: 100%;
            height: 48px;
            border: none;
            border-radius: 9px;
            background: #111827;
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .submit-btn:hover {
            background: #243247;
        }

        .message {
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 12px;
            line-height: 1.6;
            margin-bottom: 20px;
            text-align: center;
        }

        .message.success {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .message.error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .back-login {
            text-align: center;
            margin-top: 25px;
            font-size: 13px;
            color: #64748b;
        }

        .back-login a {
            color: #1655ff;
            text-decoration: none;
            font-weight: 500;
        }

        .back-login a:hover {
            text-decoration: underline;
        }

        @media (max-width: 900px) {
            body {
                padding: 40px 5%;
                justify-content: center;
            }

            .brand-section {
                display: none;
            }

            .forgot-card {
                width: 100%;
                max-width: 450px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 20px;
            }

            .forgot-card {
                padding: 30px 24px;
            }
        }
    </style>
</head>

<body>

    <div class="brand-section">
        <h1>DocuBiz</h1>
    </div>

    <div class="forgot-card">

        <h2>Forgot Password?</h2>

        <p class="description">
            Don't worry! It happens. Enter your registered email address
            and we'll send you a link to reset your password.
        </p>

        <?php if (!empty($message)): ?>
            <div class="message <?= htmlspecialchars($messageType) ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="form-group">
                <label for="email">Email Address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="email@gmail.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
            </div>

            <button type="submit" class="submit-btn">
                Send Reset Link
            </button>

        </form>

        <div class="back-login">
            Remember your password?
            <a href="login.php">Back to Log In</a>
        </div>

    </div>

</body>
</html>
