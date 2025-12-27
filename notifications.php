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

$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message']);
unset($_SESSION['error']);

// Handle Mark as Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $id = $_POST['id'] ?? '';
    if (!empty($id)) {
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: notifications.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database Error: " . $e->getMessage();
            header("Location: notifications.php");
            exit;
        }
    }
}

// Handle Mark All as Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
        $stmt->execute();
        header("Location: notifications.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database Error: " . $e->getMessage();
        header("Location: notifications.php");
        exit;
    }
}

// Handle Create Notification (Manual System Alert)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_notification'])) {
    $type = $_POST['type'] ?? 'info';
    $msg_text = trim($_POST['message'] ?? '');

    if (!empty($msg_text)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO notifications (type, message) VALUES (?, ?)");
            $stmt->execute([$type, $msg_text]);
            $_SESSION['message'] = "System alert created successfully.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Message cannot be empty.";
    }
    // Redirect
    header("Location: notifications.php");
    exit;
}

// Check for deletion messages
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = "Notification deleted.";
}

// Fetch Notifications
try {
    $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC");
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist yet
    $notifications = [];
    $error = "Could not fetch notifications. Please run <a href='setup_notifications.php'>setup_notifications.php</a> first.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Blade & Trim Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .notification-item {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
            display: flex;
            align-items: flex-start;
            gap: 15px;
            border-left: 4px solid transparent;
        }
        .notification-item.type-info { border-left-color: #3498db; }
        .notification-item.type-success { border-left-color: #2ecc71; }
        .notification-item.type-warning { border-left-color: #f1c40f; }
        .notification-item.type-danger { border-left-color: #e74c3c; }

        .notif-icon {
            font-size: 20px;
            margin-top: 2px;
        }
        .type-info .notif-icon { color: #3498db; }
        .type-success .notif-icon { color: #2ecc71; }
        .type-warning .notif-icon { color: #f1c40f; }
        .type-danger .notif-icon { color: #e74c3c; }

        .notif-content { flex: 1; }
        .notif-message { font-size: 15px; color: #333; margin-bottom: 5px; }
        .notif-time { font-size: 12px; color: #888; }

        .notification-item.read {
            opacity: 0.6;
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php $page_title = 'Notifications'; ?>
        <?php include 'header.php'; ?>

        <div class="content-container">
            
            <!-- Create Alert Box -->
            <div style="background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 30px;">
                <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 16px;">Create System Alert</h3>
                <form method="POST" action="notifications.php" style="display: flex; gap: 15px; align-items: flex-end;">
                    <div style="flex: 0 0 150px;">
                        <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">Type</label>
                        <select name="type" class="form-control">
                            <option value="info">Info</option>
                            <option value="success">Success</option>
                            <option value="warning">Warning</option>
                            <option value="danger">Danger</option>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">Message</label>
                        <input type="text" name="message" class="form-control" placeholder="e.g. Server maintenance scheduled for tonight." required>
                    </div>
                    <button type="submit" name="create_notification" class="btn-submit" style="width: auto; margin-top: 0;">Post Alert</button>
                </form>
            </div>

            <?php if ($message): ?>
                <div class="alert-message success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Mark All Read Button -->
            <?php if (!empty($notifications)): ?>
            <div style="margin-bottom: 20px; text-align: right;">
                <form method="POST" action="notifications.php" style="display: inline;">
                    <input type="hidden" name="mark_all_read" value="1">
                    <button type="submit" class="btn-action" style="color: #3498db; font-weight: 600; cursor: pointer; border: none; background: none;"><i class="fa-solid fa-check-double"></i> Mark All as Read</button>
                </form>
            </div>
            <?php endif; ?>

            <div class="notification-list">
                <?php if (empty($notifications)): ?>
                    <div style="text-align: center; color: #888; padding: 20px;">No notifications found.</div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item type-<?php echo htmlspecialchars($notif['type']); ?> <?php echo $notif['is_read'] ? 'read' : ''; ?>">
                            <div class="notif-icon"><i class="fa-solid fa-circle-info"></i></div>
                            <div class="notif-content">
                                <div class="notif-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <div class="notif-time"><?php echo date('M j, Y g:i A', strtotime($notif['created_at'])); ?></div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <?php if (!$notif['is_read']): ?>
                                <form method="POST" action="notifications.php">
                                    <input type="hidden" name="mark_read" value="1">
                                    <input type="hidden" name="id" value="<?php echo $notif['id']; ?>">
                                    <button type="submit" class="btn-action" title="Mark as Read" style="color: #2ecc71;"><i class="fa-solid fa-check"></i></button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" action="delete_notification.php" onsubmit="return confirm('Delete this notification?');">
                                    <input type="hidden" name="id" value="<?php echo $notif['id']; ?>">
                                    <button type="submit" class="btn-action btn-delete" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <script src="sidebar.js"></script>
</body>
</html>