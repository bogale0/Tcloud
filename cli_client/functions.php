<?php
function error_exit(string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function get_remote_address() : string {
    $path = getenv('HOME') . '/.tcloud_remote';
    if (!is_file($path))
        error_exit("No remote address set");
    $remote = file_get_contents($path);
    if ($remote === false)
        error_exit("No remote address set");
    return $remote;
}

function ls(string $remote_path): void {
    echo "ls\n";
}

function download(string $remote_from, string $local_to): void {
    echo "download\n";
}

function upload(string $local_from, string $remote_to): void {
    echo "upload\n";
}

function remove(string $remote_path): void {
    echo "remove\n";
}
?>
