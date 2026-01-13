<?php
require_once "functions.php";
if ($_SERVER["REQUEST_METHOD"] !== "GET")
    error_exit(405, "Method not allowed");
if (!isset($_GET["file_id"]) || !isset($_GET["chunk_id"]))
    error_exit(400, "No id specified");
$file_id = $_GET["file_id"];
$chunk_id = $_GET["chunk_id"];
check_int_id($file_id);
check_int_id($chunk_id);

$pdo = db_init();
$stmt = $pdo->prepare("select chunk_hash, tg_file_id from chunks where file_id = ? and chunk_id = ?");
$stmt->execute([$file_id, $chunk_id]);
$chunk = $stmt->fetch();
if ($chunk === false)
    error_exit(404, "Chunk not found");
$chunk_name = "/downloads/{$file_id}_{$chunk_id}";
$chunk_path = __DIR__ . $chunk_name;
if (file_exists($chunk_path) && hash_file("sha256", $chunk_path, true) === $chunk["chunk_hash"])
    success_exit(["ok" => true, "path" => $chunk_name]);

$response = api_call("getFile", [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        "file_id" => $chunk["tg_file_id"],
    ],
]);
$fp = fopen("$chunk_path.lock", "c");
if (!flock($fp, LOCK_EX | LOCK_NB))
    error_exit(423, "Chunk is being downloaded by another process");
$chunk_data = api_call("file", ["path" => $response["result"]["file_path"]]);
if (hash("sha256", $chunk_data, true) !== $chunk["chunk_hash"])
    error_exit(500, "Chunk hash mismatch after receiving " . strlen($chunk_data) . " for $chunk_name");
file_put_contents($chunk_path, $chunk_data);
flock($fp, LOCK_UN);
fclose($fp);
unlink("$chunk_path.lock");
success_exit(["ok" => true, "path" => $chunk_name]);
?>
