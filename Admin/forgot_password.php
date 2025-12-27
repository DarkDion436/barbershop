<?php
session_start();
require_once '../db_connect.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate Token
            $token = bin2hex(random_bytes(16));
            $token_hash = hash('sha256', $token);
            $expiry = date('Y-m-d H:i:s', time() + 60 * 30); // 30 minutes from now

            // Save to DB
            $update = $pdo->prepare("UPDATE admins SET reset_token_hash = ?, reset_token_expires_at = ? WHERE id = ?");
            $update->execute([$token_hash, $expiry, $user['id']]);

            // Prepare Email
            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
            $subject = "Reset Your Password - Blade & Trim";
            $msg = "Hello,\n\nWe received a request to reset your password. Click the link below to proceed:\n\n" . $resetLink . "\n\nThis link expires in 30 minutes.\n\nIf you did not request this, please ignore this email.";
            $headers = "From: no-reply@bladeandtrim.com";

            // Send Email (Ensure your server is configured to send mail)
            if (mail($email, $subject, $msg, $headers)) {
                $message = "A reset link has been sent to your email address.";
            } else {
                // Fallback for local development without SMTP
                $message = "Simulation: Email sent to " . htmlspecialchars($email) . ". <br><small>(Check logs or use the link: <a href='$resetLink'>Reset Link</a>)</small>";
            }
        } else {
            // Security: Don't reveal if email exists
            $message = "If an account exists for that email, a reset link has been sent.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Blade & Trim</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="brand-logo"><i class="fa-solid fa-scissors"></i></div>
            <div class="brand-name">Blade<span>&</span>Trim</div>
            
            <h3 style="color: #fff; margin-top: 0;">Reset Password</h3>
            <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 20px;">Enter your email address and we'll send you a link to reset your password.</p>

            <?php if ($error): ?>
                <div class="error-message"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div style="background-color: rgba(46, 204, 113, 0.1); color: #2ecc71; padding: 12px; border-radius: 5px; margin-bottom: 20px; font-size: 14px; border: 1px solid rgba(46, 204, 113, 0.2);">
                    <i class="fa-solid fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="forgot_password.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="admin@example.com" required>
                </div>
                <button type="submit" class="btn-login">Send Reset Link</button>
            </form>

            <div class="footer-links">
                <a href="../login.php"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>