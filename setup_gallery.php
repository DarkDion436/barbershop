<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS gallery (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(255) NOT NULL,
        caption VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    
    echo "<h3>Gallery table created successfully!</h3>";
    echo "<p>You can now <a href='gallery.php'>go to the Gallery page</a>.</p>";
    echo "<p><em>You can delete this file now.</em></p>";

} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>