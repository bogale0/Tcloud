<?php
require_once "functions.php";
if ($_SERVER["REQUEST_METHOD"] !== "GET")
    error_exit(405, "Method not allowed");
if (!isset($_GET["path"]))
    error_exit(400, "No path specified");
$target = check_path($_GET["path"], true);
if (!is_dir($target))
    error_exit(400, "Not a directory");

$dirs = $files = $filenames = $ids = [];
foreach (scandir($target) as $entry) {
    if ($entry === "." || $entry === "..")
        continue;
    $path = "$target/$entry";
    if (is_dir($path)) {
        $dirs[] = $entry;
    } elseif (is_file($path)) {
        $id = file_get_contents("$target/$entry");
        $filenames[$id] = $entry;
        $ids[] = $id;
    }
}

if (!empty($ids)) {
    $pdo = db_init();
    $stmt = $pdo->prepare("select file_id, file_size from files where file_id in (?" . str_repeat(", ?", count($ids) - 1) . ")");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $record) {
        $files[] = ["name" => $filenames[$record["file_id"]], "size" => $record["file_size"]];
    }
}
success_exit(["ok" => true, "dirs" => $dirs, "files" => $files]);
?>
