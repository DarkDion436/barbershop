<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Fetch stale bookings to log them before deletion
        $stmt = $pdo->query("SELECT * FROM appointments WHERE status = 'pending' AND created_at <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $staleBookings = $stmt->fetchAll();
        
        $count = count($staleBookings);
        
        if ($count > 0) {
            // 2. Delete them
            $deleteStmt = $pdo->query("DELETE FROM appointments WHERE status = 'pending' AND created_at <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
            
            // 3. Log activity for each
            $logStmt = $pdo->prepare("INSERT INTO activity_log (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            
            foreach ($staleBookings as $booking) {
                $details = "Auto-deleted stale pending booking for " . $booking['client_name'] . " on " . $booking['appointment_date'];
                $logStmt->execute([$_SESSION['admin_id'], 'Delete', $details, $_SERVER['REMOTE_ADDR']]);
            }
            
            $_SESSION['message'] = "Successfully auto-cancelled (deleted) $count stale booking(s).";
        } else {
            $_SESSION['error'] = "No stale bookings found to cancel.";
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error processing stale bookings: " . $e->getMessage();
    }
}

header("Location: dashboard.php");
exit;
?>