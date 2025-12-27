<?php
require_once 'functions.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    error_exit(405, "Method not allowed");
if (!isset($_POST['path']))
    error_exit(400, "No path specified");
if (!isset($_FILES['file']))
    error_exit(400, "No file uploaded");
if ($_FILES['file']['error'] !== UPLOAD_ERR_OK)
    error_exit(400, "File upload error: " . $_FILES['file']['error']);
if ($_FILES['file']['size'] > 20 * 1024 * 1024)
    error_exit(400, "File size exceeds limit");

$path = $_POST['path'];
$storage = realpath(__DIR__ . '/../storage');
$dir = realpath($storage . '/' . dirname($path));
if ($dir === false || strpos($dir, $storage) !== 0)
    error_exit(400, "Invalid path");
$name = basename($path);
if (!preg_match('/^[a-zA-Z0-9._-]+$/', $name))
    error_exit(400, "Invalid file name");
$target = $dir . '/' . $name;
if (!move_uploaded_file($_FILES['file']['tmp_name'], $target))
    error_exit(500, "Failed to move uploaded file");

$bot_id = trim(file_get_contents(__DIR__ . '/../secret/tgbot.id'));
$chat_id = trim(file_get_contents(__DIR__ . '/../secret/tgchat.id'));
$ch = curl_init("https://api.telegram.org/bot$bot_id/sendDocument");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'chat_id' => $chat_id,
    'document' => new CURLFile($target),
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);
unlink($target);
if ($response === false)
    error_exit(500, "cURL error: " . $error);
$response = json_decode($response, true);
if ($response["ok"] !== true)
    error_exit(500, "Telegram error: " . $response["description"]);

file_put_contents($target . '.json', json_encode([
    "message_id" => $response["result"]["message_id"],
    "size" => $response["result"]["document"]["file_size"],
    "file_id" => $response["result"]["document"]["file_id"],
]));
http_response_code(200);
echo json_encode(["ok" => true, "message" => "File uploaded successfully"]);
?>
