<?php
require_once 'include/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    error_exit(405, "Method not allowed");
if (!isset($_POST['path']))
    error_exit(400, "No path specified");
if (!isset($_POST['type']))
    error_exit(400, "No type specified");
$target = check_path($_POST['path'], false);
$result = ["ok" => true];

switch ($_POST['type']) {
    case 'd':
        if (!mkdir($target, 0755))
            error_exit(500, "Failed to create directory");
        break;
    case 'f':
        require_once 'include/db.php';
        $stmt = $pdo->query("insert into files () values ()");
        $file_id = $pdo->lastInsertId();
        if ($file_id === false || file_put_contents($target, $file_id) === false)
            error_exit(500, "Failed to create file");
        $result['file_id'] = $file_id;
        break;
    default:
        error_exit(400, "Invalid type specified");
}

http_response_code(200);
header('Content-Type: application/json');
echo json_encode($result);
?>