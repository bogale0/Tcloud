<?php
require_once 'include/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    error_exit(405, "Method not allowed");
if (!isset($_POST['path']))
    error_exit(400, "No path specified");
$target = check_path($_POST['path'], false);

$stmt = $db->query("insert into files () values ()");
$stmt->execute();
$file_id = $db->lastInsertId();
if (!file_put_contents($target, $file_id))
    error_exit(500, "Failed to create file");
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(["ok" => true, "file_id" => $file_id]);
?>