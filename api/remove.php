<?php
require_once "functions.php";
if ($_SERVER["REQUEST_METHOD"] !== "DELETE")
    error_exit(405, "Method not allowed");
if (!isset($_GET["path"]))
    error_exit(400, "No path specified");
$target = check_path($_GET["path"], true);
if ($target == __DIR__ . "/../storage")
    error_exit(400, "Cannot remove root directory");
$pdo = db_init();

if (is_dir($target)) {
    foreach (scandir($target) as $entry) {
        if ($entry === "." || $entry === "..")
            continue;
        if (is_dir("$target/$entry"))
            error_exit(409, "Cannot remove directory with subdirectories");
    }
    $fp = fopen("$target/.lock", "c");
    if (!flock($fp, LOCK_EX | LOCK_NB))
        error_exit(423, "Directory is being removed by another process");
    foreach (scandir($target) as $entry) {
        if ($entry === "." || $entry === ".." || $entry === ".lock")
            continue;
        $path = "$target/$entry";
        $stmt = $pdo->prepare("delete from files where file_id = ?");
        $stmt->execute([file_get_contents($path)]);
        unlink($path);
    }
    flock($fp, LOCK_UN);
    fclose($fp);
    unlink("$target/.lock");
    if (!rmdir($target))
        error_exit(500, "Cannot remove directory");
}
elseif (is_file($target)) {
    $stmt = $pdo->prepare("delete from files where file_id = ?");
    $stmt->execute([file_get_contents($target)]);
    unlink($target);
} else {
    error_exit(400, "Unknown file type");
}
success_exit(["ok" => true]);
?>
