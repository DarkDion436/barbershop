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

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    $file = $_FILES['backup_file'];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "File upload failed with error code: " . $file['error'];
    } else {
        $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($fileType !== 'sql') {
            $error = "Invalid file type. Please upload a .sql file.";
        } else {
            // Read file content
            $sqlContent = file_get_contents($file['tmp_name']);
            if ($sqlContent === false) {
                $error = "Failed to read the uploaded file.";
            } else {
                try {
                    // Disable foreign key checks temporarily
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

                    // Split SQL file into individual queries
                    // Note: This is a basic split and might fail on complex SQL with semicolons inside strings
                    // For robust parsing, a more complex parser is needed, but this works for standard dumps
                    $queries = explode(';', $sqlContent);

                    foreach ($queries as $query) {
                        $query = trim($query);
                        if (!empty($query)) {
                            $pdo->exec($query);
                        }
                    }

                    // Re-enable foreign key checks
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

                    // Log activity
                    $logStmt = $pdo->prepare("INSERT INTO activity_log (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                    $logStmt->execute([$_SESSION['admin_id'], 'Restore', 'Database restored from backup', $_SERVER['REMOTE_ADDR']]);

                    $message = "Database restored successfully!";
                    
                    // Redirect back to settings with success message
                    header("Location: settings.php?msg=restored");
                    exit;

                } catch (PDOException $e) {
                    $error = "Database Error during restore: " . $e->getMessage();
                }
            }
        }
    }
}

// If we get here, there was an error or direct access
if ($error) {
    header("Location: settings.php?err=" . urlencode($error));
    exit;
} else {
    header("Location: settings.php");
    exit;
}
?>