<?php
require_once 'functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET')
    error_exit(405, "Method not allowed");
if (!isset($_GET['path']))
    error_exit(400, "No path specified");
$target = check_path($_GET['path'], true);
if (!is_dir($target))
    error_exit(400, "Not a directory");

$dirs = $files = [];
foreach (scandir($target) as $entry) {
    if ($entry === '.' || $entry === '..')
        continue;
    $path = $target . '/' . $entry;
    if (is_dir($path)) {
        $dirs[] = $entry;
    } else if (is_file($path)) {
        $files[] = $entry;
    }
}
success_exit(["ok" => true, "files" => $files, "dirs" => $dirs]);
?>