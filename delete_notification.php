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
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$id]);
        
        // Log activity
        $logStmt = $pdo->prepare("INSERT INTO activity_log (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $logStmt->execute([$_SESSION['admin_id'], 'Delete', 'Deleted notification ID: ' . $id, $_SERVER['REMOTE_ADDR']]);
        
        header("Location: notifications.php?msg=deleted");
    } catch (PDOException $e) {
        header("Location: notifications.php?err=" . urlencode($e->getMessage()));
    }
} else {
    header("Location: notifications.php");
}
?>