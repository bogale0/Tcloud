<?php
require_once 'include/db.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    error_exit(405, "Method not allowed");
if (!isset($_POST['file_id']))
    error_exit(400, "No id specified");
if (!isset($_FILES['chunk']))
    error_exit(400, "No chunk uploaded");
if ($_FILES['chunk']['error'] !== UPLOAD_ERR_OK)
    error_exit(400, "Chunk upload error: " . $_FILES['chunk']['error']);
if ($_FILES['chunk']['size'] > 20 * 1024 * 1024)
    error_exit(400, "Chunk size exceeds limit of 20MB");

$file_id = $_POST['file_id'];
$fp = fopen('/tmp/file' . $file_id . '.lock', 'c');
if (!flock($fp, LOCK_EX | LOCK_NB))
    error_exit(423, "File is locked, try again later");
$stmt = $db->prepare("select chunk_count from files where id = ?");
$stmt->execute([$file_id]);
$chunk_count = $stmt->fetchColumn();
if ($chunk_count === false)
    error_exit(404, "File not found");

$bot_id = trim(file_get_contents(__DIR__ . '/../secret/tgbot.id'));
$chat_id = trim(file_get_contents(__DIR__ . '/../secret/tgchat.id'));
$target = $_FILES['chunk']['tmp_name'];
$response = curl_response("https://api.telegram.org/bot$bot_id/sendDocument", [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        'chat_id' => $chat_id,
        'document' => new CURLFile($target),
    ],
], fn() => unlink($target));
$response = json_decode($response, true);
if ($response["ok"] !== true)
    error_exit(500, "Telegram error: " . $response["description"]);

$tg_msg_id = $response["result"]["message_id"];
$tg_file_id = $response["result"]["document"]["file_id"];
$size_t = $response["result"]["document"]["file_size"];
$stmt = $db->prepare("update files set size_t = size_t + ?, chunk_count = chunk_count + 1 where id = ?");
$stmt->execute([$size_t, $file_id]);
$stmt = $db->prepare("insert into chunks values (?, ?, ?, ?)");
$stmt->execute([$file_id, ++$chunk_count, $tg_file_id, $tg_msg_id]);
flock($fp, LOCK_UN);
fclose($fp);
http_response_code(200);
echo json_encode(["ok" => true, "chunk_number" => $chunk_count]);
?>
