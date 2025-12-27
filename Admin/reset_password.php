<?php
session_start();
require_once '../db_connect.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if (empty($token)) {
    die("Invalid request.");
}

$token_hash = hash('sha256', $token);

// Verify Token
$stmt = $pdo->prepare("SELECT * FROM admins WHERE reset_token_hash = ? AND reset_token_expires_at > NOW()");
$stmt->execute([$token_hash]);
$user = $stmt->fetch();

if (!$user) {
    $error = "This password reset link is invalid or has expired.";
}

// Handle Password Update
if ($user && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Update password and clear token
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE admins SET password = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id = ?");
        $update->execute([$new_hash, $user['id']]);
        
        $success = "Password has been reset successfully! Redirecting to login...";
        header("refresh:3;url=../login.php");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - Blade & Trim</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="brand-logo"><i class="fa-solid fa-scissors"></i></div>
            <div class="brand-name">Blade<span>&</span>Trim</div>

            <?php if ($success): ?>
                <div style="background-color: rgba(46, 204, 113, 0.1); color: #2ecc71; padding: 12px; border-radius: 5px; margin-bottom: 20px; font-size: 14px; border: 1px solid rgba(46, 204, 113, 0.2);">
                    <i class="fa-solid fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php elseif ($error): ?>
                <div class="error-message"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
                <?php if (strpos($error, 'invalid') !== false): ?>
                    <div class="footer-links"><a href="forgot_password.php">Request a new link</a></div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($user && !$success): ?>
                <h3 style="color: #fff; margin-top: 0;">Set New Password</h3>
                <form method="POST" action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>">
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" class="form-control" required>
                            <i class="fa-solid fa-eye toggle-password"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            <i class="fa-solid fa-eye toggle-password"></i>
                        </div>
                    </div>
                    <button type="submit" class="btn-login">Update Password</button>
                </form>
            <?php endif; ?>

            <?php if (!$user && $error): ?>
                 <div class="footer-links">
                    <a href="../login.php">Back to Login</a>
                </div>
            <?php endif; ?>
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