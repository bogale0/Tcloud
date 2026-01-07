<?php
$MAX_CHUNK_SIZE = 1024 * 1024;

function error_exit(string $message): void {
    $fp = fopen('php://stderr', 'w');
    fwrite($fp, $message . "\n");
    fclose($fp);
    exit(1);
}

function get_remote_address() : string {
    $path = getenv('HOME') . '/.tcloud/remote';
    if (!is_file($path))
        error_exit("No remote address set");
    $remote = file_get_contents($path);
    if ($remote === false)
        error_exit("No remote address set");
    return trim($remote);
}

function curl_response(string $url, array $options = []) : string {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . trim(file_get_contents(getenv('HOME') . '/.tcloud/token'))]);
    foreach ($options as $key => $value) {
        curl_setopt($ch, $key, $value);
    }
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($response === false)
        error_exit("Curl error: " . $error);
    return $response;
}

function ls(string $remote_path): void {
    echo curl_response(get_remote_address() . '/ls.php?path=' . urlencode($remote_path));
}

function download(string $remote_from, string $local_to): void {
    echo "download\t$remote_from\t$local_to\n";
}

function upload(string $local_from, string $remote_to): void {
    if (!file_exists($local_from))
        error_exit("Local file does not exist");
    if (!is_file($local_from))
        error_exit("Directory upload in development");
    $fp = fopen($local_from, 'rb');
    if ($fp === false)
        error_exit("Cannot open local file");
    $response = curl_response(get_remote_address() . '/mkfile.php', [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'path' => $remote_to,
            'type' => 'file',
        ],
    ]);
    $response = json_decode($response, true);
    if ($response === null)
        error_exit("Invalid response from endpoint");
    if ($response['ok'] !== true)
        error_exit("Endpoint error: " . $response['error']);

    $file_id = $response['file_id'];
    $chunk_id = 1;
    $chunk_size = $GLOBALS['MAX_CHUNK_SIZE'];
    while (!feof($fp)) {
        $data = fread($fp, $chunk_size);
        if ($data === false)
            error_exit("Error reading local file");
        $chunk_name = '/tmp/chunk' . $file_id . '_' . $chunk_id;
        file_put_contents($chunk_name, $data);
        $response = curl_response(get_remote_address() . '/add_chunk.php', [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'file_id' => $file_id,
                'chunk_id' => $chunk_id,
                'chunk' => new CURLFile($chunk_name),
            ],
        ]);
        unlink($chunk_name);
        $result = json_decode($response, true);
        if ($result === null)
            error_exit("Invalid response from endpoint");
        if ($result['ok'] !== true)
            error_exit("Endpoint error: " . $result['error']);
        echo "Uploaded chunk $chunk_id\n";
        $chunk_id++;
        sleep(1);
    }
    fclose($fp);
    echo "Success uploading file\n";
}

function remove(string $remote_path): void {
    echo "remove\n";
}
?>
