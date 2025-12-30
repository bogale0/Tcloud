<?php
require_once 'functions.php';
require_once 'db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET')
    error_exit(405, "Method not allowed");
if (!isset($_GET['path']))
    error_exit(400, "No path specified");

$path = $_GET['path'];
$storage = realpath(__DIR__ . '/../storage');
$target = realpath($storage . '/' . $path);
if ($target === false || strpos($target, $storage) !== 0)
    error_exit(400, "Invalid path");

$file_id = file_get_contents($target);
$stmt = $db->prepare("select size, created_at from files where file_id = ?");
$stmt->execute([$file_id]);
$meta = $stmt->fetch();
if ($meta === false)
    error_exit(404, "File not found");

$stmt = $db->prepare("select chunk_id, tg_file_id, tg_msg_id from chunks where file_id = ?");
$stmt->execute([$file_id]);
$meta["chunks"] = $stmt->fetchAll();
$meta["ok"] = true;
http_response_code(200);
header('Content-Type: application/json');
echo json_encode($meta);
?>
