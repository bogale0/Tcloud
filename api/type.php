<?php
require_once 'functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET')
    error_exit(405, "Method not allowed");
$target = check_path($_GET['path'], true);
if (is_dir($target)) {
    $type = 'dir';
} elseif (is_file($target)) {
    $type = 'file';
} else {
    error_exit(404, "File or directory not found");
}
success_exit(["ok" => true, "type" => $type]);
?>
