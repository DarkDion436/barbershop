<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    
    try {
        // Fetch staff details first for logging and file deletion
        $stmt = $pdo->prepare("SELECT name, profile_image FROM staff WHERE id = ?");
        $stmt->execute([$id]);
        $staff = $stmt->fetch();

        if ($staff) {
            // Delete profile image if exists
            if (!empty($staff['profile_image']) && file_exists($staff['profile_image'])) {
                unlink($staff['profile_image']);
            }

            $stmt = $pdo->prepare("DELETE FROM staff WHERE id = ?");
            $stmt->execute([$id]);

            // Log activity
            $logStmt = $pdo->prepare("INSERT INTO activity_log (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$_SESSION['admin_id'], 'Delete', 'Deleted staff member: ' . $staff['name'], $_SERVER['REMOTE_ADDR']]);
        }

        header("Location: staff.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: staff.php?err=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    header("Location: staff.php");
    exit;
}
?>