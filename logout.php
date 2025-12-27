<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

// Log logout activity if user was logged in
if (isset($_SESSION['admin_id'])) {
    try {
        $logStmt = $pdo->prepare("INSERT INTO activity_log (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $logStmt->execute([$_SESSION['admin_id'], 'Logout', 'Successful logout', $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        // Silent fail for logging during logout
    }
}

// Unset all of the session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
header("Location: login.php");
exit;
?>