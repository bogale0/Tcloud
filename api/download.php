<?php
require_once 'functions.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    error_exit(405, "Method not allowed");
if (!isset($_POST['path']))
    error_exit(400, "No path specified");

$path = $_POST['path'];
$storage = realpath(__DIR__ . '/../storage');
$target = realpath($storage . '/' . $path . '.json');
if ($target === false || strpos($target, $storage) !== 0)
    error_exit(400, "Invalid path");
$file_path = substr($target, 0, -5);
if (file_exists($file_path))
    error_exit(404, "File already downloaded");

$bot_id = trim(file_get_contents(__DIR__ . '/../secret/tgbot.id'));
$response = curl_response("https://api.telegram.org/bot$bot_id/getFile", [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        'file_id' => json_decode(file_get_contents($target), true)["file_id"],
    ],
]);
$response = json_decode($response, true);
if ($response["ok"] !== true)
    error_exit(500, "Telegram error: " . $response["description"]);

$ch = curl_init("https://api.telegram.org/file/bot$bot_id/" . $response["result"]["file_path"]);
$fd = fopen($file_path, 'wb');
curl_setopt($ch, CURLOPT_FILE, $fd);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
$result = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);
fclose($fd);
if ($result === false)
    error_exit(500, "cURL error: " . $error);
http_response_code(200);
echo json_encode(["ok" => true, "message" => "File downloaded successfully"]);
?>
