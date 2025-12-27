<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "<h3>Notifications table created successfully!</h3>";
    echo "<p>You can now <a href='notifications.php'>go to the Notifications page</a>.</p>";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>