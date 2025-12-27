<?php
/************************************
 * Secure Admin Login
 * Blade & Trim Barbershop
 ************************************/

/* ---------------- SESSION SECURITY ---------------- */
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);

if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

session_start();

/* ---------------- ERROR HANDLING ---------------- */
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

/* ---------------- REDIRECT IF LOGGED IN ---------------- */
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

/* ---------------- DB CONNECTION ---------------- */
require_once 'db_connect.php';

$error = '';

/* ---------------- LOGIN ATTEMPTS (ANTI-BRUTE FORCE) ---------------- */
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if ($_SESSION['login_attempts'] >= 5) {
    $error = "Too many failed login attempts. Please try again later.";
}

/* ---------------- HANDLE LOGIN ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT id, username, password, full_name
                FROM admins
                WHERE username = :username
                LIMIT 1
            ");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {

                /* --- SUCCESSFUL LOGIN --- */
                session_regenerate_id(true);

                $_SESSION['admin_id']       = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_name']     = $user['full_name'];
                $_SESSION['login_attempts'] = 0;

                /* Update last login */
                $update = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                $update->execute([$user['id']]);

                /* Log Activity */
                $logStmt = $pdo->prepare("INSERT INTO activity_log (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $logStmt->execute([$user['id'], 'Login', 'Successful login', $_SERVER['REMOTE_ADDR']]);

                header("Location: dashboard.php");
                exit;

            } else {
                $_SESSION['login_attempts']++;
                $error = "Invalid username or password.";
            }

        } catch (PDOException $e) {
            error_log("Admin login error: " . $e->getMessage());
            $error = "System error. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Blade & Trim Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="login.css">
</head>
<body>

    <div class="login-container">
        <div class="login-card">
            <div class="brand-logo">
                <i class="fa-solid fa-scissors"></i>
            </div>
            <div class="brand-name">
                Blade<span>&</span>Trim
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                        <i class="fa-solid fa-eye toggle-password"></i>
                    </div>
                </div>

                <button type="submit" class="btn-login">Sign In</button>
            </form>

            <div class="footer-links">
                <a href="Admin/forgot_password.php">Forgot Password?</a>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.toggle-password').forEach(icon => {
            icon.addEventListener('click', function() {
                const input = this.previousElementSibling;
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>
</html>