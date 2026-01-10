<?php
check_bearer();

function success_exit(array $response) : void {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit;
}

function error_exit(int $error_code, string $message) : void {
    http_response_code($error_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["ok" => false, "error" => $message]);
    exit;
}

function check_str_id(string $id) : string {
    if (!isset($id))
        error_exit(400, "No id specified");
    if (!filter_var($id, FILTER_VALIDATE_INT) || $id <= 0 || $id > 2147483647)
        error_exit(400, "Invalid id format");
    return $id;
}

function db_init() : PDO {
    $password = trim(file_get_contents(__DIR__ . '/../secret/dbuser.pswd'));
    $pdo = new PDO('mysql:host=localhost;dbname=tcloud;charset=utf8mb4', 'tcloud', $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    return $pdo;
}

function check_bearer() : void {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        error_exit(401, "Missing Authorization header");
    }
    $token = $headers['Authorization'];
    $expected_token = trim(file_get_contents(__DIR__ . '/../secret/bearer.token'));
    if ($token !== "Bearer $expected_token") {
        error_exit(403, "Invalid token");
    }
}

function curl_response(string $url, bool $is_json = true, array $options = []) : mixed {
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
        error_exit("Curl error: " . $error);
    if (!$is_json)
        return $response;
    $response = json_decode($response, true);
    if ($response === null)
        error_exit(500, "Invalid response from Telegram");
    if ($response["ok"] !== true)
        error_exit(500, "Telegram error: " . $response["description"]);
    return $response;
}

function check_path(string $path, bool $exists) : string {
    if (!isset($path))
        error_exit(400, "No path specified");
    $storage = realpath(__DIR__ . '/../storage');
    if ($exists)
        $target = realpath("$storage/$path");
    else
        $target = realpath("$storage/" . dirname($path));
    if ($target === false || strpos($target, $storage) !== 0)
        error_exit(400, "Invalid path");
    if ($exists)
        return $target;
    $name = basename($path);
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $name))
        error_exit(400, "Invalid file name");
    $target .= "/$name";
    if (file_exists($target))
        error_exit(400, "File already exists");
    return $target;
}
?>
