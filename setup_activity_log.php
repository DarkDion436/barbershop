<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        action VARCHAR(255) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "<h3>Activity Log table created successfully!</h3>";
    echo "<p>You can now <a href='view_activity_log.php'>go to the Activity Log page</a>.</p>";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>