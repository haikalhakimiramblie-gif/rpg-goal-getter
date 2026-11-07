<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$dbname = 'rpg_goal_getter';
$username = 'YOUR_DATABASE_USER';
$password = 'YOUR_DATABASE_PASSWORD';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed. Check your local configuration.');
}
