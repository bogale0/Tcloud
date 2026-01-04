<?php
require_once 'functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET')
    error_exit(405, "Method not allowed");
if (!isset($_GET['path']))
    error_exit(400, "No path specified");
$target = check_path($_GET['path'], true);
if (!is_file($target))
    error_exit(400, "Not a file");
$file_id = file_get_contents($target);
$meta = ["ok" => true];

$pdo = db_init();
$stmt = $pdo->prepare("select * from files where file_id = ?");
$stmt->execute([$file_id]);
$meta['file'] = $stmt->fetch();
if ($meta['file'] === false)
    error_exit(404, "File not found");
$stmt = $pdo->prepare("select chunk_id from chunks where file_id = ? order by chunk_id asc");
$stmt->execute([$file_id]);
$meta['chunks'] = $stmt->fetchAll();
success_exit($meta);
?>
