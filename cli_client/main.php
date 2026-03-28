<?php
require_once 'functions.php';
function set_config(string $key, string $value) : void {
    file_put_contents(tcloud_dir() . "/$key", $value);
}
if ($argc < 2) {
    error_exit("Usage: tcloud <command> [options]\nCommand is one of: ls mkdir rm download upload config");
}
if (!is_dir(tcloud_dir())) {
    mkdir(tcloud_dir(), 0700, true);
    mkdir(tcloud_dir() . "/tracking", 0700, true);
}
switch ($argv[1]) {
    case 'ls':
        if ($argc !== 3)
            error_exit("Usage: tcloud ls <remote_path>");
        ls($argv[2]);
        break;

    case 'mkdir':
        if ($argc !== 3)
            error_exit("Usage: tcloud mkdir <remote_path>");
        mkdir_remote($argv[2]);
        break;

    case 'rm':
        if ($argc == 2)
            error_exit("Usage: tcloud rm [-r] <remote_path>");
        if ($argv[2] == "-r")
            rm($argv[3], true);
        else
            rm($argv[2]);
        break;

    case 'download':
        if ($argc !== 4)
            error_exit("Usage: tcloud download <remote_path> <local_path>");
        download($argv[2], $argv[3]);
        break;

    case 'upload':
        if ($argc !== 4)
            error_exit("Usage: tcloud upload <local_path> <remote_path>");
        upload($argv[2], $argv[3]);
        break;

    case 'config':
        if ($argc < 3)
            error_exit("Usage: tcloud config [address | token | keygen]");
        switch ($argv[2]) {
            case "address":
                if ($argc !== 4)
                    error_exit("Usage: tcloud config address http(s)://<remote_address>");
                set_config($argv[2], $argv[3]);
                echo "Remote address set to {$argv[3]}\n";
                break;
            case "token":
                if ($argc !== 4)
                    error_exit("Usage: tcloud config token <token>");
                set_config($argv[2], $argv[3]);
                echo "Token saved\n";
                break;
            case "keygen":
                if ($argc !== 3)
                    error_exit("Usage: tcloud config keygen");
                set_config("key", random_bytes(32));
                echo "Encryption key generated\n";
                break;
            default:
                error_exit("Unknown config option: " . $argv[2]);
        }
        break;

    default:
        error_exit("Unknown command: " . $argv[1]);
}
?>
