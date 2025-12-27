<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

$admin_id = $_SESSION['admin_id'];
$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message']);
unset($_SESSION['error']);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($full_name) || empty($username) || empty($email)) {
        $_SESSION['error'] = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
    } else {
        try {
            // Check if username or email already exists for OTHER users
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->execute([$username, $email, $admin_id]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['error'] = "Username or Email already taken.";
            } else {
                // Update Admin Profile
                $sql = "UPDATE admins SET full_name = ?, username = ?, email = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$full_name, $username, $email, $admin_id]);
                
                // Update Session
                $_SESSION['admin_name'] = $full_name;
                $_SESSION['admin_username'] = $username;
                
                $_SESSION['message'] = "Profile updated successfully!";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database Error: " . $e->getMessage();
        }
    }
    // Redirect
    header("Location: profile.php");
    exit;
}

// Fetch Current Admin Details
try {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();
} catch (PDOException $e) {
    $error = "Error fetching profile: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Blade & Trim Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php $page_title = 'My Profile'; ?>
        <?php include 'header.php'; ?>

        <div class="content-container">
            
            <?php if ($message): ?>
                <div class="alert-message success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div style="background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); max-width: 600px;">
                <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                    <div style="width: 80px; height: 80px; background-color: var(--accent-gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold; color: #000;">
                        <?php 
                            $initials = 'AD';
                            $parts = explode(' ', $admin['full_name'] ?? 'Admin');
                            if (count($parts) >= 2) {
                                $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
                            } elseif (!empty($admin['full_name'])) {
                                $initials = strtoupper(substr($admin['full_name'], 0, 2));
                            }
                            echo $initials;
                        ?>
                    </div>
                    <div>
                        <h2 style="margin: 0; font-size: 24px;"><?php echo htmlspecialchars($admin['full_name'] ?? ''); ?></h2>
                        <p style="margin: 5px 0 0; color: #666;">Administrator</p>
                        <p style="margin: 5px 0 0; color: #888; font-size: 13px;">Last Login: <?php echo $admin['last_login'] ? date('M j, Y g:i A', strtotime($admin['last_login'])) : 'Never'; ?></p>
                    </div>
                </div>

                <form method="POST" action="profile.php">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($admin['full_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" required>
                    </div>

                    <button type="submit" class="btn-submit">Update Profile</button>
                </form>
            </div>
        </div>
    </main>

    <script src="sidebar.js"></script>
</body>
</html>