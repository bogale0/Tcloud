<?php
function progress(int $current, int $total): void {
    echo "\rProgress: $current/$total";
}

function log_write(string $line) : void {
    $path = getenv('HOME') . "/.tcloud/log";
    if (file_put_contents($path, "$line\n", FILE_APPEND) === false)
        error_exit("Logging error");
}

function error_exit(string $message): void {
    $fp = fopen('php://stderr', 'w');
    fwrite($fp, "\n$message\n");
    fclose($fp);
    exit(1);
}

function get_config(string $key) : string {
    $path = getenv('HOME') . "/.tcloud/$key";
    if (!is_file($path))
        error_exit("No $key set");
    $value = file_get_contents($path);
    if ($value === false)
        error_exit("No $key set");
    return $value;
}

function encrypt(string $data): string {
    $key = get_config("key");
    $iv = random_bytes(12);
    $encrypted = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($encrypted === false)
        error_exit("Encryption error");
    return $iv . $tag . $encrypted;
}

function decrypt(string $data): string|false {
    $key = get_config("key");
    $iv = substr($data, 0, 12);
    $tag = substr($data, 12, 16);
    $encrypted = substr($data, 28);
    $decrypted = openssl_decrypt($encrypted, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $decrypted;
}

function curl_response(string $url, bool $is_json = true, array $options = []) : mixed {
    $ch = curl_init($url);
    if ($is_json) {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . get_config("token")]);
    }
    foreach ($options as $key => $value) {
        curl_setopt($ch, $key, $value);
    }
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($response === false)
        error_exit("Curl error: " . $error);
    if (!$is_json)
        return $response;
    $response = json_decode($response, true);
    if ($response === null)
        error_exit("Invalid response from $url");
    if ($response['ok'] !== true)
        error_exit("Error from $url: " . $response['error']);
    return $response;
}

function ls(string $remote_path): void {
    $response = curl_response(get_config("address") . '/ls.php?path=' . urlencode($remote_path));
    $max_name_size = 0;
    foreach ($response['dirs'] as $dir) {
        echo "dir:   $dir\n";
        if (($len = strlen($dir)) > $max_name_size)
            $max_name_size = $len;
    }
    foreach ($response['files'] as $file) {
        if (($len = strlen($file['name'])) > $max_name_size)
            $max_name_size = $len;
    }
    foreach ($response['files'] as $file) {
        $filename = $file['name'] . str_repeat(" ", $max_name_size - strlen($file['name']));
        $filesize = (double)$file['size'];
        $unit_index = 0;
        while ($filesize >= 1024) {
            $filesize /= 1024;
            $unit_index++;
        }
        $filesize = round($filesize, 2);
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $unit = $units[$unit_index];
        echo "file:  $filename  $filesize $unit\n";
    }
}

function mkdir_remote(string $remote_path): void {
    $response = curl_response(get_config("address") . '/mkfile.php', true, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'path' => $remote_path,
            'type' => 'dir',
        ],
    ]);
    echo "Directory created successfully\n";
}

function download(string $remote_from, string $local_to): void {
    if (file_exists($local_to))
        error_exit("Local file already exist");
    $fp = fopen($local_to, 'wb');
    if ($fp === false)
        error_exit("Cannot open local file");
    $address = get_config("address");
    $metadata = curl_response("$address/meta.php?path=$remote_from");
    $file_id = $metadata['file']['id'];
    $chunk_count = $metadata['file']['chunk_count'];
    $chunk_number = 0;
    foreach ($metadata['chunks'] as $chunk) {
        $chunk_id = $chunk['id'];
        progress($chunk_number++, $chunk_count);
        $chunk_url = curl_response("$address/chunk.php?file_id=$file_id&chunk_id=$chunk_id");
        $chunk_url = $address . $chunk_url['path'];
        $retries = 0;
        do {
            if ($retries !== 0)
                log_write("Attempt downloading from endpoint #$retries: file_id=$file_id, chunk_id=$chunk_id");
            $chunk_data = curl_response($chunk_url, false, [
                CURLOPT_RETURNTRANSFER => true,
            ]);
            $data = decrypt($chunk_data);
        } while ($data === false && ++$retries < 3);
        if ($data === false)
            error_exit("Chunk is corrupted");
        if (fwrite($fp, $data) === false)
            error_exit("Error writing local file");
        usleep(500000);
    }
    fclose($fp);
    progress($chunk_count, $chunk_count);
    echo "\nSuccess downloading file\n";
}

function upload(string $local_from, string $remote_to): void {
    if (!is_file($local_from))
        error_exit("Local file does not exist");
    $fp = fopen($local_from, 'rb');
    if ($fp === false)
        error_exit("Cannot open local file");
    $address = get_config("address");
    $file_id = curl_response("$address/mkfile.php", true, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'path' => $remote_to,
            'type' => 'file',
        ],
    ]);
    $file_id = $file_id['file_id'];
    $chunk_id = 0;
    $chunk_size = 20 * 1024 * 1024 - 28;
    $chunk_count = ceil(filesize($local_from) / $chunk_size);
    while (!feof($fp)) {
        $data = fread($fp, $chunk_size);
        if ($data === false)
            error_exit("Error reading local file");
        if (strlen($data) === 0)
            break;
        progress($chunk_id++, $chunk_count);
        $data = encrypt($data);
        curl_response("$address/add_chunk.php", true, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'file_id' => $file_id,
                'chunk_id' => $chunk_id,
                'hash' => base64_encode(hash('sha256', $data, true)),
                'chunk' => new CURLStringFile($data, 'chunk'),
            ],
        ]);
        usleep(500000);
    }
    fclose($fp);
    progress($chunk_count, $chunk_count);
    echo "\nSuccess uploading file\n";
}

function remove(string $remote_path): void {
    echo "remove\n";
}
?>
