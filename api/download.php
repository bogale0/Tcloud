<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET')
    error_exit(405, "Method not allowed");
if (!isset($_GET['path']))
    error_exit(400, "No path specified");

$path = $_GET['path'];
$storage = realpath(__DIR__ . '/../storage');
$target = realpath($storage . '/' . $path . '.json');
if ($target === false || strpos($target, $storage) !== 0)
    error_exit(400, "Invalid path");

$bot_id = trim(file_get_contents(__DIR__ . '/../secret/tgbot.id'));
$response = curl_query("https://api.telegram.org/bot$bot_id/getFile", [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        'file_id' => json_decode(file_get_contents($target), true)["file_id"],
    ],
]);
$response = json_decode($response, true);
if ($response["ok"] !== true)
    error_exit(500, "Telegram error: " . $response["description"]);

curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/file/bot$bot_id/" . $response["result"]["file_path"]);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, null);

http_response_code(200);
echo json_encode(["ok" => true, "message" => "File uploaded successfully"]);
?>
