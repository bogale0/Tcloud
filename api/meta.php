<?php
require_once 'include/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET')
    error_exit(405, "Method not allowed");
if (!isset($_GET['path']))
    error_exit(400, "No path specified");
$target = check_path($_GET['path'], true);
$file_id = file_get_contents($target);

$stmt = $db->prepare("select size_t, chunk_count, created_at from files where file_id = ?");
$stmt->execute([$file_id]);
$meta = $stmt->fetch();
if ($meta === false)
    error_exit(404, "File not found");
$meta["ok"] = true;
http_response_code(200);
header('Content-Type: application/json');
echo json_encode($meta);
?>
