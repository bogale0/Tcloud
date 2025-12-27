<?php
function error_exit($error_code, $message) {
    http_response_code($error_code);
    echo json_encode(["ok" => false, "error" => $message]);
    exit;
}

function curl_query($url, $options = []) : string {
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
    if ($response === false)
        error_exit(500, "cURL error: " . $error);
    return $response;
}
?>