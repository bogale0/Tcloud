<?php
function tcloud_dir() : string {
    return getenv("HOME") . "/.tcloud";
}

function echo_progress(int $current, int $total): void {
    echo "\rProgress: $current/$total";
    usleep(1000000);
}

function api_call(string $method, array $options = []) : array {
    return curl_response(get_config("address") . $method, true, $options);
}

function error_exit(string $message): void {
    $fp = fopen("php://stderr", "w");
    fwrite($fp, "\n$message\n");
    fclose($fp);
    exit(1);
}

function get_config(string $key) : string {
    $path = tcloud_dir() . "/$key";
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
    $encrypted = openssl_encrypt($data, "aes-256-gcm", $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($encrypted === false)
        error_exit("Encryption error");
    return $iv . $tag . $encrypted;
}

function decrypt(string $data): string {
    $key = get_config("key");
    $iv = substr($data, 0, 12);
    $tag = substr($data, 12, 16);
    $encrypted = substr($data, 28);
    $decrypted = openssl_decrypt($encrypted, "aes-256-gcm", $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($decrypted === false)
        error_exit("Decryption error");
    return $decrypted;
}

function execv(string $path, array $args) : bool {
    $pid = pcntl_fork();
    if ($pid === -1)
        error_exit("Can't create child process");
    elseif ($pid === 0) {
        if (!pcntl_exec($path, $args))
            error_exit("Process wasn't executed");
    } else {
        if (pcntl_waitpid($pid, $status) !== $pid)
            error_exit("Error waiting child");
        if (!pcntl_wifexited($status))
            error_exit("Child didn't exit normally");
        if (pcntl_wexitstatus($status) !== 0)
            return false;
    }
    return true;
}

function get_working_info(string $call_info, array &$files, int &$progress) : string {
    $tcloud_dir = tcloud_dir() . "/tracking/" . hash("sha256", $call_info);
    $files = [
        "data" => "$tcloud_dir/data.tar.gz",
        "info" => "$tcloud_dir/info",
        "metadata" => "$tcloud_dir/metadata",
        "progress" => "$tcloud_dir/progress",
    ];
    if (!is_dir($tcloud_dir) && !mkdir($tcloud_dir, 0700))
        error_exit("Can't create directory $tcloud_dir");
    if (!is_file($files["info"]))
        file_put_contents($files["info"], $call_info);
    if (!is_file($files["progress"]))
        file_put_contents($files["progress"], $progress);
    else
        $progress = (int)file_get_contents($files["progress"]);
    return $tcloud_dir;
}

function curl_response(string $url, bool $is_json, array $options) : mixed {
    $ch = curl_init($url);
    if ($is_json)
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer " . get_config("token")]
        ]);
    curl_setopt_array($ch, $options);
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
    if ($response["ok"] !== true)
        error_exit("Error from $url: " . $response["error"]);
    return $response;
}

function ls(string $remote_path): void {
    $response = api_call("/ls.php?path=" . urlencode($remote_path));
    $max_name_size = 0;
    foreach ($response["dirs"] as $dir) {
        echo "dir:   $dir\n";
        if (($len = strlen($dir)) > $max_name_size)
            $max_name_size = $len;
    }
    foreach ($response["files"] as $file) {
        if (($len = strlen($file["name"])) > $max_name_size)
            $max_name_size = $len;
    }

    foreach ($response["files"] as $file) {
        $filename = $file["name"] . str_repeat(" ", $max_name_size - strlen($file["name"]));
        $filesize = (double)$file["size"];
        $unit_index = 0;
        while ($filesize >= 1024) {
            $filesize /= 1024;
            $unit_index++;
        }
        $filesize = round($filesize, 2);
        $units = ["B", "KiB", "MiB", "GiB", "TiB"];
        $unit = $units[$unit_index];
        echo "file:  $filename  $filesize $unit\n";
    }
}

function mkdir_remote(string $remote_path): void {
    $response = api_call("/mkfile.php", [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            "path" => $remote_path,
            "type" => "dir",
        ],
    ]);
    echo "Directory created successfully\n";
}

function download(string $remote_from, string $local_to): void {
    if (file_exists($local_to))
        error_exit("Local file already exist");
    $local_to = realpath($local_to);
    $files = [];
    $progress = 0;
    $tcloud_dir = get_working_info("download\nfrom=$remote_from\nto=$local_to", $files, $progress);

    if (!is_file($files["metadata"])) {
        $metadata = api_call("/meta.php?path=" . urlencode($remote_from));
        file_put_contents($files["metadata"], json_encode($metadata));
    } else {
        $metadata = json_decode(file_get_contents($files["metadata"]), true);
    }

    $fp = fopen($files["data"], "ab");
    if ($fp === false)
        error_exit("Error opening local file");
    $file_id = $metadata["file"]["id"];
    $chunk_count = $metadata["file"]["chunk_count"];
    $chunk_number = 0;

    foreach ($metadata["chunks"] as $chunk) {
        echo_progress($chunk_number++, $chunk_count);
        if ($chunk_number <= $progress)
            continue;
        $chunk_id = $chunk["id"];
        $chunk_path = api_call("/chunk.php?file_id=$file_id&chunk_id=$chunk_id")["path"];
        $chunk_data = curl_response(get_config("address") . $chunk_path, false, [
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $data = decrypt($chunk_data);
        if (fwrite($fp, $data) === false)
            error_exit("Error writing local file");
        file_put_contents($files["progress"], $chunk_number);
    }

    fclose($fp);
    echo_progress($chunk_count, $chunk_count);
    $unpacked_dir = "$tcloud_dir/unpacked";
    if (!mkdir($unpacked_dir, 0700, true))
        error_exit("Can't create directory $unpacked_dir");
    if (!execv("/usr/bin/tar", ["xzf", $files["data"], "-C", $unpacked_dir]))
        error_exit("Extracting error");
    foreach (scandir($unpacked_dir) as $file) {
        if ($file !== "." && $file !== "..") {
            $unpacked_file = "$unpacked_dir/$file";
        }
    }
    if (!rename($unpacked_file, $local_to))
        error_exit("Error moving file to destination");

    foreach ($files as $file) {
        unlink($file);
    }
    rmdir($unpacked_dir);
    rmdir($tcloud_dir);
    echo "\nSuccess downloading\n";
}

function upload(string $local_from, string $remote_to): void {
    if (!file_exists($local_from))
        error_exit("Local file does not exist");
    $local_from = realpath($local_from);
    $files = [];
    $progress = 0;
    $tcloud_dir = get_working_info("upload\nfrom=$local_from\nto=$remote_to", $files, $progress);

    if (is_file($files["data"]) && !execv("/usr/bin/tar", ["tf", $files["data"]]))
        unlink($files["data"]);
    if (!is_file($files["data"])) {
        if (!execv("/usr/bin/tar", ["chzf", $files["data"], "-C", dirname($local_from), basename($local_from)]))
            error_exit("Archiving error");
    }
    if (!is_file($files["metadata"])) {
        $file_id = api_call("/mkfile.php", [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                "path" => $remote_to,
                "type" => "file",
            ],
        ])["file_id"];
        file_put_contents($files["metadata"], $file_id);
    } else {
        $file_id = file_get_contents($files["metadata"]);
    }

    $fp = fopen($files["data"], "rb");
    if ($fp === false)
        error_exit("Error opening local file");
    $chunk_size = 20 * 1024 ** 2 - 28;
    fseek($fp, $progress * $chunk_size);
    $chunk_id = $progress;
    $chunk_count = ceil(filesize($files["data"]) / $chunk_size);

    while (!feof($fp)) {
        $data = fread($fp, $chunk_size);
        if ($data === false)
            error_exit("Error reading local file");
        if (strlen($data) === 0)
            break;
        echo_progress($chunk_id++, $chunk_count);
        $data = encrypt($data);
        api_call("/add_chunk.php", [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                "file_id" => $file_id,
                "chunk_id" => $chunk_id,
                "hash" => base64_encode(hash("sha256", $data, true)),
                "chunk" => new CURLStringFile($data, "chunk"),
            ],
        ]);
        file_put_contents($files["progress"], $chunk_id);
    }

    fclose($fp);
    echo_progress($chunk_count, $chunk_count);
    foreach ($files as $file) {
        unlink($file);
    }
    rmdir($tcloud_dir);
    echo "\nSuccess uploading\n";
}

function rm(string $remote_path, bool $is_recursive = false): void {
    api_call("/remove.php?path=" . urlencode($remote_path) . "&recursive=$is_recursive", [
        CURLOPT_CUSTOMREQUEST => "DELETE",
    ]);
    echo "Success removing\n";
}
?>
