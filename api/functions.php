<?php
function error_exit(int $error_code, string $message) : void {
    http_response_code($error_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["ok" => false, "error" => $message]);
    exit;
}

function curl_response(string $url, array $options = [], ?callable $after_exec = null) : string {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    foreach ($options as $key => $value) {
        curl_setopt($ch, $key, $value);
    }
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($after_exec !== null)
        $after_exec();
    if ($response === false)
        error_exit(500, "curl error: " . $error);
    return $response;
}
?>