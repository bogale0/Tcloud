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
$usetime_file = __DIR__ . "/downloads/usetime";
$fp = fopen("$usetime_file.lock", "c");
if (!flock($fp, LOCK_EX))
    error_exit(423, "Error with locking usetime");
$usetime = "";
if (is_file($usetime_file) && ($usetime = file_get_contents($usetime_file)) === false)
    error_exit(423, "Error with reading usetime");
$delay = (float)$usetime + 1 - microtime(true);
if ($delay > 0)
    usleep($delay * 1e6);
$chat_id = secret_value("tgchat.id");
$response = api_call("sendDocument", [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        "chat_id" => $chat_id,
        "document" => new CURLFile($_FILES["chunk"]["tmp_name"], null, "chunk{$file_id}_{$chunk_id}"),
    ],
])["result"];
file_put_contents($usetime_file, microtime(true));
$tg_msg_id = $response["message_id"];
$tg_file_id = $response["document"]["file_id"];
$file_size = $response["document"]["file_size"];
$stmt = $pdo->prepare("update files set file_size = file_size + ?, chunk_count = chunk_count + 1 where file_id = ?");
$stmt->execute([$file_size, $file_id]);
$stmt = $pdo->prepare("insert into chunks (file_id, chunk_id, chunk_hash, tg_file_id, tg_msg_id) values (?, ?, ?, ?, ?)");
$stmt->execute([$file_id, $chunk_id, $hash, $tg_file_id, $tg_msg_id]);
flock($fp, LOCK_UN);
fclose($fp);
unlink("$usetime_file.lock");
success_exit(["ok" => true]);
?>
