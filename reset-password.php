
<?php
session_start();

require_once __DIR__ . '/db.php';

$message = '';
$messageType = '';
$validToken = false;
$reset = null;

$token = trim($_POST['token'] ?? $_GET['token'] ?? '');

if (strlen($token) !== 64 || !ctype_xdigit($token)) {

    $message = 'Invalid or missing reset link.';
    $messageType = 'error';

} else {

    $tokenHash = hash('sha256', $token);

    // Validate token using UTC time
    $stmt = $conn->prepare(
        "SELECT user_id
         FROM password_resets
         WHERE token_hash = ?
         AND expires_at > UTC_TIMESTAMP()
         AND used_at IS NULL
         LIMIT 1"
    );

    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();

    $result = $stmt->get_result();
    $reset = $result->fetch_assoc();

    $stmt->close();

    if ($reset) {

        $validToken = true;

    } else {

        $message = 'This password reset link is invalid or has expired. Please request a new one.';
        $messageType = 'error';
    }
}


// Process new password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {

        $message = 'Password must be at least 8 characters long.';
        $messageType = 'error';

    } elseif ($password !== $confirmPassword) {

        $message = 'Passwords do not match.';
        $messageType = 'error';

    } else {

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        // Start transaction
        $conn->begin_transaction();

        try {

            // Consume token only if still valid
            $useStmt = $conn->prepare(
                "UPDATE password_resets
                 SET used_at = UTC_TIMESTAMP()
                 WHERE token_hash = ?
                 AND used_at IS NULL
                 AND expires_at > UTC_TIMESTAMP()"
            );

            $useStmt->bind_param("s", $tokenHash);

            if (!$useStmt->execute() || $useStmt->affected_rows !== 1) {
                throw new Exception('Reset token is no longer valid.');
            }

            $useStmt->close();

            // Update user's password
            $updateStmt = $conn->prepare(
                "UPDATE users
                 SET password = ?
                 WHERE id = ?"
            );

            $updateStmt->bind_param(
                "si",
                $hashedPassword,
                $reset['user_id']
            );

            if (!$updateStmt->execute() || $updateStmt->affected_rows !== 1) {
                throw new Exception('Password update failed.');
            }

            $updateStmt->close();

            // Commit changes
            $conn->commit();

            $validToken = false;

            $message = 'Your password has been successfully updated! You can now log in using your new password.';
            $messageType = 'success';

        } catch (Exception $e) {

            $conn->rollback();

            error_log("Password reset error: " . $e->getMessage());

            $message = 'Something went wrong. Please try again.';
            $messageType = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | DocuBiz</title>

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

        .reset-card {
            width: 450px;
            background: #ffffff;
            padding: 35px 36px;
            border-radius: 19px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
        }

        .reset-card h2 {
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
            margin-bottom: 20px;
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
            outline: none;
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

        @media (max-width: 900px) {
            body {
                padding: 40px 5%;
                justify-content: center;
            }

            .brand-section {
                display: none;
            }

            .reset-card {
                width: 100%;
                max-width: 450px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 20px;
            }

            .reset-card {
                padding: 30px 24px;
            }
        }
    </style>
</head>

<body>

    <div class="brand-section">
        <h1>DocuBiz</h1>
    </div>

    <div class="reset-card">

        <h2>Reset Password</h2>

        <p class="description">
            Create a new password for your DocuBiz account.
            Make sure it's at least 8 characters long.
        </p>

        <?php if (!empty($message)): ?>
            <div class="message <?= htmlspecialchars($messageType) ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($validToken): ?>

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="token"
                    value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="form-group">
                    <label for="password">New Password</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter new password"
                        minlength="8"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm new password"
                        minlength="8"
                        required
                    >
                </div>

                <button type="submit" class="submit-btn">
                    Reset Password
                </button>

            </form>

        <?php endif; ?>

        <div class="back-login">
            <a href="login.php">Back to Log In</a>
        </div>

    </div>

</body>
</html>
