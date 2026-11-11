<?php
$host = 'localhost';
$dbname = 'sbdt_inventory';
$dbuser = 'root';
$dbpass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Check config.php and MySQL.');
}
