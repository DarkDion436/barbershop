<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = $_POST['email'];
    
    try {
        // Delete all appointments associated with this email
        // Since there is no dedicated customers table, this effectively "deletes" the customer profile
        $stmt = $pdo->prepare("DELETE FROM appointments WHERE client_email = ?");
        $stmt->execute([$email]);
        
        // Log activity
        $logStmt = $pdo->prepare("INSERT INTO activity_log (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $logStmt->execute([$_SESSION['admin_id'], 'Delete', 'Deleted customer history for: ' . $email, $_SERVER['REMOTE_ADDR']]);

        header("Location: customers.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: customers.php?err=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    header("Location: customers.php");
    exit;
}
?>