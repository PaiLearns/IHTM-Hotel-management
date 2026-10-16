<?php

ini_set('display_errors', '0');

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

$dbHost = 'localhost';
$dbName = 'hotel_db';
$dbUser = 'root';
$dbPassword = '';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPassword, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ));
} catch (PDOException $exception) {
    error_log('IHTM database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('The application could not connect to its database. Please check the local MySQL service and try again.');
}
