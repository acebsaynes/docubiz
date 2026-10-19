
<?php
session_start();

require_once "db.php";

$loginError = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $loginError = "Please enter your email and password.";
    } else {
        $stmt = $conn->prepare(
            "SELECT id, full_name, email, password
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        if ($stmt === false) {
            error_log("Login prepare failed: " . $conn->error);
            $loginError = "Something went wrong. Please try again.";
        } else {
            $stmt->bind_param("s", $email);

            if (!$stmt->execute()) {
                error_log("Login query failed: " . $stmt->error);
                $loginError = "Something went wrong. Please try again.";
            } else {
                $stmt->bind_result(
                    $userId,
                    $fullName,
                    $userEmail,
                    $hashedPassword
                );

                if (
                    $stmt->fetch() &&
                    password_verify($password, $hashedPassword)
                ) {
                    $stmt->close();

                    session_regenerate_id(true);

                    $_SESSION["user_id"] = $userId;
                    $_SESSION["user_name"] = $fullName;
                    $_SESSION["user_email"] = $userEmail;

                    header("Location: dashboard.php");
                    exit;
                }

                $loginError = "Invalid email or password.";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DocuBiz - Log In</title>
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
      font-size: 1.5rem;
      font-weight: 600;
      color: #1a202c;
      margin-bottom: 24px;
      text-align: center;
    }
    .form-group {
      margin-bottom: 16px;
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
    .forgot-link-wrapper {
      text-align: right;
      margin-top: 6px;
    }
    .forgot-link {
      font-size: 0.75rem;
      color: #a0aec0;
      text-decoration: none;
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
    }
    .divider {
      text-align: center;
      font-size: 0.75rem;
      color: #a0aec0;
      margin: 16px 0;
      position: relative;
    }
    .btn-google {
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
      gap: 10px;
      margin-bottom: 10px;
    }
    .btn-admin {
      width: 100%;
      padding: 10px;
      background-color: #ffffff;
      border: 1px solid #cbd5e0;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 500;
      color: #2d3748;
      cursor: pointer;
      text-decoration: none;
      display: block;
      text-align: center;
    }
    .google-icon {
      width: 18px;
      height: 18px;
    }
    .signup-prompt {
      text-align: center;
      font-size: 0.78rem;
      color: #718096;
      margin-top: 20px;
    }
    .signup-prompt a {
      color: #3182ce;
      text-decoration: none;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="brand-section">
      DocuBiz
    </div>
    <div class="login-card">
      <h2>Log In</h2>
      <form action="login.php" method="POST">
        <div class="form-group">
          <label for="email">Email or phone</label>
          <div class="input-wrapper">
            <input type="text" id="email" name="email" placeholder="email@gmail.com" required>
          </div>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrapper">
            <input type="password" id="password" name="password" placeholder="••••••••" required>
            <button type="button" class="eye-toggle" title="Toggle password">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
            </button>
          </div>
          <div class="forgot-link-wrapper">
            <a href="#" class="forgot-link">Forgot Password?</a>
          </div>
        </div>
        <button type="submit" class="btn-submit">Log In</button>
        <div class="divider">or</div>
        <button type="button" class="btn-google">
          <svg class="google-icon" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
          </svg>
          Log In with Google
        </button>
        <a href="admin_login.php" class="btn-admin">
          Log In as Admin
        </a>
        <p class="signup-prompt">
          Don't have an account? <a href="signup.html">Create one</a>
        </p>
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