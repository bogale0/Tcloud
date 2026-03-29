<?php
require_once "functions.php";
if ($_SERVER["REQUEST_METHOD"] !== "POST")
    error_exit(405, "Method not allowed");
if (!isset($_POST["path"]))
    error_exit(400, "No path specified");
if (!isset($_POST["type"]))
    error_exit(400, "No type specified");
$target = check_path($_POST["path"], false);
$result = ["ok" => true];

switch ($_POST["type"]) {
    case "dir":
        if (!mkdir($target, 0700))
            error_exit(500, "Failed to create directory");
        break;
    case "file":
        $fp = fopen($target, "w");
        if (!flock($fp, LOCK_EX | LOCK_NB))
            error_exit(423, "File is being created by another process");
        $pdo = db_init();
        $pdo->exec("insert into files () values ()");
        $file_id = $pdo->lastInsertId();
        fwrite($fp, $file_id);
        flock($fp, LOCK_UN);
        fclose($fp);
        chmod($target, 0600);
        $result["file_id"] = $file_id;
        break;
    default:
        error_exit(400, "Invalid type specified");
}
success_exit($result);
?>
