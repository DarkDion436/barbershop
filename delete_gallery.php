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
        // Get file path first
        $stmt = $pdo->prepare("SELECT image_path FROM gallery WHERE id = ?");
        $stmt->execute([$id]);
        $image = $stmt->fetch();

        if ($image && file_exists($image['image_path'])) {
            unlink($image['image_path']); // Delete file from server
        }

        // Delete DB record
        $delStmt = $pdo->prepare("DELETE FROM gallery WHERE id = ?");
        $delStmt->execute([$id]);
        
        // Log activity
        $logStmt = $pdo->prepare("INSERT INTO activity_log (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $logStmt->execute([$_SESSION['admin_id'], 'Delete', 'Deleted gallery image: ' . ($image['caption'] ?: basename($image['image_path'])), $_SERVER['REMOTE_ADDR']]);

        header("Location: gallery.php?msg=deleted");
    } catch (PDOException $e) {
        header("Location: gallery.php?err=" . urlencode($e->getMessage()));
    }
} else {
    header("Location: gallery.php");
}
?>