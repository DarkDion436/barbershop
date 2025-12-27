<?php
$host = 'localhost';
$db   = 'barber_shop_spa';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Set timezone to Africa/Nairobi (or your preferred timezone)
    $pdo->exec("SET time_zone = '+03:00'"); // Offset for EAT (East Africa Time)
    date_default_timezone_set('Africa/Nairobi');
    
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>