<?php
require_once 'functions.php';
require_once 'db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET')
    error_exit(405, "Method not allowed");
if (!isset($_GET['path']))
    error_exit(400, "No path specified");

$path = $_GET['path'];
$storage = realpath(__DIR__ . '/../storage');
$target = realpath($storage . '/' . $path);
if ($target === false || strpos($target, $storage) !== 0)
    error_exit(400, "Invalid path");

$file_id = file_get_contents($target);
$stmt = $db->prepare("select size, created_at from files where file_id = ?");
$stmt->execute([$file_id]);
$meta = $stmt->fetch();
if ($meta === false)
    error_exit(404, "File not found");

$stmt = $db->prepare("select chunk_id, tg_file_id, tg_msg_id from chunks where file_id = ?");
$stmt->execute([$file_id]);
$meta["chunks"] = $stmt->fetchAll();
$meta["ok"] = true;
http_response_code(200);
header('Content-Type: application/json');
echo json_encode($meta);
/*
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
echo json_encode(["ok" => true, "message" => "File downloaded successfully"]);*/
?>
