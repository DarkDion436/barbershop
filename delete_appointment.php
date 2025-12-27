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
        // Fetch details for log
        $stmt = $pdo->prepare("SELECT client_name, appointment_date FROM appointments WHERE id = ?");
        $stmt->execute([$id]);
        $appt = $stmt->fetch();

        if ($appt) {
            $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
            $stmt->execute([$id]);
            
            // Log activity
            $logStmt = $pdo->prepare("INSERT INTO activity_log (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$_SESSION['admin_id'], 'Delete', 'Deleted appointment for ' . $appt['client_name'] . ' on ' . $appt['appointment_date'], $_SERVER['REMOTE_ADDR']]);
            
            $_SESSION['message'] = "Appointment deleted successfully.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting appointment: " . $e->getMessage();
    }
}

header("Location: appointments.php");
exit;
?>