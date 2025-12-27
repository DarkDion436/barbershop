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
        // Fetch name for log
        $stmt = $pdo->prepare("SELECT name FROM services WHERE id = ?");
        $stmt->execute([$id]);
        $service = $stmt->fetch();

        if ($service) {
            $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
            $stmt->execute([$id]);

            // Log activity
            $logStmt = $pdo->prepare("INSERT INTO activity_log (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$_SESSION['admin_id'], 'Delete', 'Deleted service: ' . $service['name'], $_SERVER['REMOTE_ADDR']]);
        }

        header("Location: services.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: services.php?err=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    header("Location: services.php");
    exit;
}
?>