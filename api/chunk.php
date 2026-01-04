<?php
require_once 'functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET')
    error_exit(405, "Method not allowed");
$file_id = check_str_id($_GET['file_id']);
$chunk_id = check_str_id($_GET['chunk_id']);
$pdo = db_init();
$stmt = $pdo->prepare("select tg_file_id from chunks where file_id = ? and chunk_id = ?");
$stmt->execute([$file_id, $chunk_id]);
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
if ($response === null)
    error_exit(500, "Invalid response from Telegram");
if ($response["ok"] !== true)
    error_exit(500, "Telegram error: " . $response["description"]);

http_response_code(200);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment');
$fp = fopen('php://output', 'wb');
curl_response("https://api.telegram.org/file/bot$bot_id/" . $response["result"]["file_path"], [
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_FILE => $fp,
]);
fclose($fp);
exit;
?>
