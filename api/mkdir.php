<?php
require_once 'include/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    error_exit(405, "Method not allowed");
if (!isset($_POST['path']))
    error_exit(400, "No path specified");
$target = check_path($_POST['path'], false);

if (!mkdir($target, 0755))
    error_exit(500, "Failed to create directory");
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(["ok" => true]);
?>