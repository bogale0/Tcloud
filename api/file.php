<?php
require_once 'functions.php';
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment');
if ($_SERVER['REQUEST_METHOD'] !== 'GET')
    error_exit(405, "Method not allowed");
if (!isset($_GET['path']))
    error_exit(400, "No path specified");

$path = $_GET['path'];
$storage = realpath(__DIR__ . '/../storage');
$target = realpath($storage . '/' . $path);
if ($target === false || strpos($target, $storage) !== 0)
    error_exit(400, "Invalid path");
http_response_code(200);
$data = file_get_contents($target);
header('Content-Length: ' . strlen($data));
echo $data;
?>
