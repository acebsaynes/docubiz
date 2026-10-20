
<?php
session_start();

require_once "db.php";

$loginError = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $adminId = trim($_POST["admin_id"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($adminId === "" || $password === "") {
        $loginError = "Please enter your admin ID and password.";
    } elseif (!ctype_digit($adminId)) {
        $loginError = "Invalid admin ID or password.";
    } else {
        $stmt = $conn->prepare(
            "SELECT id, full_name, email, password, role
             FROM users
             WHERE id = ? AND role = 'admin'
             LIMIT 1"
        );

        if ($stmt === false) {
            error_log("Admin login prepare failed: " . $conn->error);
            $loginError = "Something went wrong. Please try again.";
        } else {
            $adminIdNumber = (int) $adminId;
            $stmt->bind_param("i", $adminIdNumber);

            if (!$stmt->execute()) {
                error_log("Admin login query failed: " . $stmt->error);
                $loginError = "Something went wrong. Please try again.";
                $stmt->close();
            } else {
                $stmt->bind_result(
                    $userId,
                    $fullName,
                    $userEmail,
                    $hashedPassword,
                    $userRole
                );

                $adminFound = $stmt->fetch();

                if (
                    $adminFound &&
                    $userRole === "admin" &&
                    password_verify($password, $hashedPassword)
                ) {
                    $stmt->close();

                    session_regenerate_id(true);

                    $_SESSION["user_id"] = $userId;
                    $_SESSION["user_name"] = $fullName;
                    $_SESSION["full_name"] = $fullName;
                    $_SESSION["user_email"] = $userEmail;
                    $_SESSION["user_role"] = "admin";
                    $_SESSION["role"] = "admin";

                    header("Location: dashboard.php");
                    exit;
                }

                $stmt->close();
                $loginError = "Invalid admin ID or password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DocuBiz - Admin Log In</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }
    body {
      background: linear-gradient(135deg, #0b2246 0%, #3a7bd5 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .container {
      display: flex;
      width: 100%;
      max-width: 1000px;
      justify-content: space-between;
      align-items: center;
      padding: 20px;
    }
    .brand-section {
      color: #ffffff;
      font-size: 3.5rem;
      font-weight: 700;
      letter-spacing: -1px;
      margin-left: 50px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .brand-logo-icon {
      width: 45px;
      height: 45px;
      background: #ffffff;
      border-radius: 8px;
    }
    .login-card {
      background: #ffffff;
      padding: 40px;
      border-radius: 16px;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    .login-card h2 {
      font-size: 1.4rem;
      font-weight: 600;
      color: #1a202c;
      margin-bottom: 28px;
      text-align: center;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      display: block;
      font-size: 0.8rem;
      font-weight: 500;
      color: #4a5568;
      margin-bottom: 6px;
    }
    .input-wrapper {
      position: relative;
    }
    .input-wrapper input {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #cbd5e0;
      border-radius: 8px;
      font-size: 0.85rem;
      outline: none;
      color: #2d3748;
    }
    .input-wrapper input::placeholder {
      color: #a0aec0;
    }
    .input-wrapper input:focus {
      border-color: #3182ce;
    }
    .eye-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: #a0aec0;
      display: flex;
      align-items: center;
    }
    .btn-submit {
      width: 100%;
      padding: 11px;
      background-color: #122b49;
      color: #ffffff;
      border: none;
      border-radius: 8px;
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      margin-top: 10px;
      margin-bottom: 12px;
      transition: background-color 0.2s ease;
    }
    .btn-submit:hover {
      background-color: #1a3c63;
    }
    .btn-back {
      width: 100%;
      padding: 10px;
      background-color: #ffffff;
      border: 1px solid #cbd5e0;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 500;
      color: #2d3748;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      text-decoration: none;
      transition: background-color 0.2s ease;
    }
    .btn-back:hover {
      background-color: #f7fafc;
    }

    .error-message {
      background: #fed7d7;
      color: #c53030;
      padding: 8px;
      border-radius: 6px;
      font-size: 0.8rem;
      text-align: center;
      margin-bottom: 15px;
    }

    @media (max-width: 900px) {
      .container {
        flex-direction: column;
        gap: 30px;
      }
      .brand-section {
        margin-left: 0;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="brand-section">
      DocuBiz
    </div>
    <div class="login-card">
      <h2>Admin Log In</h2>

      <?php if (!empty($loginError)): ?>
        <div class="error-message">
          <?php echo htmlspecialchars($loginError, ENT_QUOTES, "UTF-8"); ?>
        </div>
      <?php endif; ?>

      <form action="admin_login.php" method="POST">
        <div class="form-group">
          <label for="admin_id">ID Number</label>
          <div class="input-wrapper">
            <input type="text" id="admin_id" name="admin_id" placeholder="00000000" inputmode="numeric" required>
          </div>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrapper">
            <input type="password" id="password" name="password" placeholder="••••••••" required>
            <button type="button" class="eye-toggle" title="Toggle password visibility">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
            </button>
          </div>
        </div>
        <button type="submit" class="btn-submit">Log In</button>
        <a href="login.php" class="btn-back">
          <span>← Back to SSO</span>
        </a>
      </form>
    </div>
  </div>
  <script>
    const passwordInput = document.getElementById('password');
    const eyeToggle = document.querySelector('.eye-toggle');

    eyeToggle.addEventListener('click', function () {
      const isPassword = passwordInput.type === 'password';
      passwordInput.type = isPassword ? 'text' : 'password';
    });
  </script>
</body>
</html>
