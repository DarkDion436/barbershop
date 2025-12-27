<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

// Configuration
$db_name = 'barber_shop_spa'; // Or get from db_connect if available
$backup_name = $db_name . "_backup_" . date("Y-m-d_H-i-s") . ".sql";

// Set headers for download
header('Content-Type: application/octet-stream');
header("Content-Transfer-Encoding: Binary");
header("Content-disposition: attachment; filename=\"" . $backup_name . "\"");

// Start output buffering
ob_start();

try {
    // Get all tables
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    // Disable foreign key checks
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // Get create table syntax
        $stmt = $pdo->query("SHOW CREATE TABLE $table");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        echo "\n\n" . $row[1] . ";\n\n";

        // Get data
        $stmt = $pdo->query("SELECT * FROM $table");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sql = "INSERT INTO $table (";
            $keys = array_keys($row);
            $sql .= implode(", ", $keys) . ") VALUES (";
            
            $values = array_values($row);
            $escaped_values = array_map(function($value) use ($pdo) {
                if ($value === null) return "NULL";
                return $pdo->quote($value);
            }, $values);
            
            $sql .= implode(", ", $escaped_values) . ");\n";
            echo $sql;
        }
    }

    // Re-enable foreign key checks
    echo "\nSET FOREIGN_KEY_CHECKS=1;\n";

    // Log activity
    $logStmt = $pdo->prepare("INSERT INTO activity_log (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $logStmt->execute([$_SESSION['admin_id'], 'Backup', 'Database backup downloaded', $_SERVER['REMOTE_ADDR']]);

} catch (PDOException $e) {
    // If error occurs, clear buffer and show error (though headers are already sent)
    ob_end_clean();
    echo "Error creating backup: " . $e->getMessage();
    exit;
}

// Flush buffer to output file
ob_end_flush();
exit;
?>