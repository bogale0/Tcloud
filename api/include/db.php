<?php
require_once 'functions.php';
$password = trim(file_get_contents(__DIR__ . '/../secret/db.pswd'));
$pdo = new PDO('mysql:host=localhost;dbname=storage;charset=utf8mb4', 'storage', $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
?>