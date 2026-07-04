<?php
check_bearer();

function secret_value($key) : string {
    return trim(file_get_contents(outer_dir() . "/secret/$key"));
}

function check_int_id(string $id) : void {
    if (!filter_var($id, FILTER_VALIDATE_INT) || $id <= 0 || $id > 2147483647)
        error_exit(400, "Invalid id format");
}

function success_exit(array $response) : void {
    http_response_code(200);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($response);
    exit;
}

function error_exit(int $error_code, string $message) : void {
    http_response_code($error_code);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["ok" => false, "error" => $message]);
    exit;
}

function db_init() : PDO {
    $password = secret_value("dbuser.pswd");
    $pdo = new PDO("mysql:host=localhost;dbname=tcloud;charset=utf8mb4", "tcloud", $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    return $pdo;
}

function check_bearer() : void {
    $headers = getallheaders();
    if (!isset($headers["Authorization"]))
        error_exit(401, "Missing Authorization header");
    $token = $headers["Authorization"];
    $expected_token = secret_value("bearer.token");
    if ($token !== "Bearer $expected_token")
        error_exit(403, "Invalid token");
}

function storage_dir(): string {
    static $cached = null;
    if ($cached !== null)
        return $cached;
    $path = realpath(outer_dir() . "/storage");
    if ($path === false)
        error_exit(500, "Storage directory not found");
    $cached = $path;
    return $cached;
}

function outer_dir(): string {
    static $cached = null;
    if ($cached !== null)
        return $cached;
    $path = __DIR__;
    while (basename($path) !== "public_html") {
        if ($path === "/")
            error_exit(500, "Outer directory not found");
        $path = dirname($path);
    }
    $cached = dirname($path);
    return $cached;
}

function api_call(string $method, array $options = []) : mixed {
    $host = "https://api.telegram.org";
    $bot_name = "bot" . secret_value("tgbot.id");
    if ($method === "file") {
        $url = "$host/file/$bot_name/" . $options["path"];
        $options = [CURLOPT_RETURNTRANSFER => true];
        $is_json = false;
    } else {
        $url = "$host/$bot_name/$method";
        $is_json = true;
    }
    return curl_response($url, $is_json, $options);
}

function check_path(string $path, bool $exists) : string {
    $storage = storage_dir();
    if ($exists)
        $target = realpath("$storage/$path");
    else
        $target = realpath("$storage/" . dirname($path));
    if ($target === false || strpos($target, $storage) !== 0)
        error_exit(400, "Invalid path");
    if ($exists)
        return $target;
    $name = basename($path);
    if (!preg_match("/^[a-zA-Z0-9._-]+$/", $name))
        error_exit(400, "Invalid file name");
    $target .= "/$name";
    if (file_exists($target))
        error_exit(400, "File already exists");
    return $target;
}

function curl_response(string $url, bool $is_json, array $options) : mixed {
    $ch = curl_init($url);
    if ($is_json) {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    }
    foreach ($options as $key => $value) {
        curl_setopt($ch, $key, $value);
    }
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($response === false)
        error_exit(500, "Curl error: " . $error);
    if (!$is_json)
        return $response;
    $response = json_decode($response, true);
    if ($response === null)
        error_exit(500, "Invalid response from Telegram");
    if ($response["ok"] !== true)
        error_exit(500, "Telegram error: " . $response["description"]);
    return $response;
}
?>
