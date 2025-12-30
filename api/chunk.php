<?php
require_once 'include/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET')
    error_exit(405, "Method not allowed");
if (!isset($_GET['file_id']) || !isset($_GET['chunk_id']))
    error_exit(400, "No id specified");

require_once 'include/db.php';
$stmt = $pdo->prepare("select tg_file_id from chunks where file_id = ? and chunk_id = ?");
$stmt->execute([$_GET['file_id'], $_GET['chunk_id']]);
$tg_file_id = $stmt->fetchColumn();
if ($tg_file_id === false)
    error_exit(404, "Chunk not found");

$bot_id = trim(file_get_contents(__DIR__ . '/../secret/tgbot.id'));
$response = curl_response("https://api.telegram.org/bot$bot_id/getFile", [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        'file_id' => $tg_file_id,
    ],
]);
$response = json_decode($response, true);
if ($response["ok"] !== true)
    error_exit(500, "Telegram error: " . $response["description"]);

http_response_code(200);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment');
curl_response("https://api.telegram.org/file/bot$bot_id/" . $response["result"]["file_path"], [
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_FILE => STDOUT,
]);
?>
