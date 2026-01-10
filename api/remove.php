<?php
require_once 'functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE')
    error_exit(405, "Method not allowed");
$target = check_path($_GET['path'], true);
if (is_dir($target)) {
    if (count(scandir($target)) > 2)
        error_exit(400, "Directory is not empty");
    if (!rmdir($target))
        error_exit(500, "Cannot remove directory");
} else if (is_file($target)) {
    $file_id = file_get_contents($target);
    $pdo = db_init();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("delete from files where file_id = ?");
    $stmt->execute([$file_id]);
    if (!unlink($target))
        error_exit(500, "Cannot remove file");
    $pdo->commit();
} else {
    error_exit(500, "Unknown file type");
}
success_exit(["ok" => true]);
?>
