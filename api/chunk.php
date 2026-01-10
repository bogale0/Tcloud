<?php
require_once 'functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET')
    error_exit(405, "Method not allowed");
$file_id = check_str_id($_GET['file_id']);
$chunk_id = check_str_id($_GET['chunk_id']);
$pdo = db_init();
$stmt = $pdo->prepare("select chunk_hash, tg_file_id from chunks where file_id = ? and chunk_id = ?");
$stmt->execute([$file_id, $chunk_id]);
$chunk = $stmt->fetch();
if ($chunk === false)
    error_exit(404, "Chunk not found");
$chunk_name = "/chunks/{$file_id}_{$chunk_id}";
$chunk_path = __DIR__ . $chunk_name;
if (file_exists($chunk_path) && hash_file('sha256', $chunk_path, true) === $chunk['chunk_hash']) {
    success_exit(["ok" => true, "path" => $chunk_name]);
}

$bot_id = trim(file_get_contents(__DIR__ . '/../secret/tgbot.id'));
$response = curl_response("https://api.telegram.org/bot$bot_id/getFile", true, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        'file_id' => $chunk['tg_file_id'],
    ],
]);
$fp = fopen("$chunk_path.lock", 'c');
if (!flock($fp, LOCK_EX | LOCK_NB))
    error_exit(423, "Chunk is being downloaded by another process");
$chunk_url = "https://api.telegram.org/file/bot$bot_id/" . $response["result"]["file_path"];
$retries = 0;
do {
    usleep($retries * 200000);
    $chunk_data = curl_response($chunk_url, false, [
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $chunk_hash = hash('sha256', $chunk_data, true);
} while ($chunk_hash !== $chunk['chunk_hash'] && ++$retries < 3);
if ($chunk_hash !== $chunk['chunk_hash'])
    error_exit(500, "Chunk hash mismatch after download");
file_put_contents($chunk_path, $chunk_data);
flock($fp, LOCK_UN);
fclose($fp);
unlink("$chunk_path.lock");
success_exit(["ok" => true, "path" => $chunk_name]);
?>
