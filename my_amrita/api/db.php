<?php
/**
 * My Amrita – Database Connection
 * Uses PDO for secure MySQL connections.
 */

$db_host = 'localhost';
$db_name = 'my_amrita';
$db_user = 'root';
$db_pass = '';  // Default XAMPP/WAMP password is empty

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
