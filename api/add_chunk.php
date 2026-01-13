<?php
require_once "functions.php";
$MAX_CHUNK_SIZE = 20 * 1024 * 1024;
if ($_SERVER["REQUEST_METHOD"] !== "POST")
    error_exit(405, "Method not allowed");
if (!isset($_POST["file_id"]) || !isset($_POST["chunk_id"]))
    error_exit(400, "No id specified");
$file_id = $_POST["file_id"];
$chunk_id = $_POST["chunk_id"];
check_int_id($file_id);
check_int_id($chunk_id);
if (!isset($_POST["hash"]))
    error_exit(400, "No hash provided");
if (!isset($_FILES["chunk"]))
    error_exit(400, "No chunk uploaded");
if ($_FILES["chunk"]["error"] !== UPLOAD_ERR_OK)
    error_exit(400, "Chunk upload error: " . $_FILES["chunk"]["error"]);
if ($_FILES["chunk"]["size"] > $MAX_CHUNK_SIZE || $_FILES["chunk"]["size"] <= 0)
    error_exit(400, "Chunk size exceeds limits");
$hash = hash_file("sha256", $_FILES["chunk"]["tmp_name"], true);
if ($hash !== base64_decode($_POST["hash"], true))
    error_exit(400, "Hash mismatch");

$pdo = db_init();
$stmt = $pdo->prepare("select 1 from files where file_id = ?");
$stmt->execute([$file_id]);
if ($stmt->fetch() === false)
    error_exit(404, "File not found");
$stmt = $pdo->prepare("select 1 from chunks where file_id = ? and chunk_id = ?");
$stmt->execute([$file_id, $chunk_id]);
if ($stmt->fetch() !== false)
    error_exit(409, "Chunk already exists");
$chunk_name = "chunk{$file_id}_{$chunk_id}";
$lock_file = "/tmp/$chunk_name.lock";
$fp = fopen($lock_file, "c");
if (!flock($fp, LOCK_EX | LOCK_NB))
    error_exit(423, "Chunk is being uploaded by another process");

$chat_id = trim(file_get_contents(__DIR__ . "/../secret/tgchat.id"));
$response = api_call("sendDocument", [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        "chat_id" => $chat_id,
        "document" => new CURLFile($_FILES["chunk"]["tmp_name"], null, $chunk_name),
    ],
])["result"];
$tg_msg_id = $response["message_id"];
$tg_file_id = $response["document"]["file_id"];
$file_size = $response["document"]["file_size"];
$stmt = $pdo->prepare("update files set file_size = file_size + ?, chunk_count = chunk_count + 1 where file_id = ?");
$stmt->execute([$file_size, $file_id]);
$stmt = $pdo->prepare("insert into chunks (file_id, chunk_id, chunk_hash, tg_file_id, tg_msg_id) values (?, ?, ?, ?, ?)");
$stmt->execute([$file_id, $chunk_id, $hash, $tg_file_id, $tg_msg_id]);
flock($fp, LOCK_UN);
fclose($fp);
unlink($lock_file);
success_exit(["ok" => true]);
?>
