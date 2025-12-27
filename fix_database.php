<?php
// Run this script once to fix the missing columns in your database
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php';

try {
    echo "<h3>Database Repair</h3>";

    // 1. Add 'email' column if missing
    $stmt = $pdo->query("SHOW COLUMNS FROM admins LIKE 'email'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN email VARCHAR(100) UNIQUE AFTER username");
        echo "<p style='color:green'>&#10004; Added missing column: <strong>email</strong></p>";
    } else {
        echo "<p style='color:gray'>&#10004; Column <strong>email</strong> already exists.</p>";
    }

    // 2. Add 'reset_token_hash' if missing (for password reset)
    $stmt = $pdo->query("SHOW COLUMNS FROM admins LIKE 'reset_token_hash'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN reset_token_hash VARCHAR(64) NULL AFTER full_name");
        echo "<p style='color:green'>&#10004; Added missing column: <strong>reset_token_hash</strong></p>";
    }

    // 3. Add 'reset_token_expires_at' if missing
    $stmt = $pdo->query("SHOW COLUMNS FROM admins LIKE 'reset_token_expires_at'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN reset_token_expires_at DATETIME NULL AFTER reset_token_hash");
        echo "<p style='color:green'>&#10004; Added missing column: <strong>reset_token_expires_at</strong></p>";
    }

    echo "<p><strong>Database structure is now correct. You can delete this file.</strong></p>";

} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>