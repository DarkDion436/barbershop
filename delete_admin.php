<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $current_admin_id = $_SESSION['admin_id'];

    // Prevent deleting self
    if ($id == $current_admin_id) {
        header("Location: settings.php?err=" . urlencode("You cannot delete your own account."));
        exit;
    }

    // Prevent deleting the main admin (ID 1)
    if ($id == 1) {
        header("Location: settings.php?err=" . urlencode("The main admin account cannot be deleted."));
        exit;
    }
    
    try {
        // Fetch username for log
        $stmt = $pdo->prepare("SELECT username FROM admins WHERE id = ?");
        $stmt->execute([$id]);
        $admin = $stmt->fetch();

        if ($admin) {
            $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
            $stmt->execute([$id]);

            // Log activity
            $logStmt = $pdo->prepare("INSERT INTO activity_log (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$_SESSION['admin_id'], 'Delete', 'Deleted admin: ' . $admin['username'], $_SERVER['REMOTE_ADDR']]);
        }

        header("Location: settings.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: settings.php?err=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    header("Location: settings.php");
    exit;
}
?>