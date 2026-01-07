<?php
require_once 'functions.php';
if ($argc < 2) {
    error_exit("Usage: tcloud <command> [options]\nCommand is one of: ls download upload remove remote");
}
if (!is_dir(getenv('HOME') . '/.tcloud')) {
    mkdir(getenv('HOME') . '/.tcloud', 0700, true);
}
switch ($argv[1]) {
    case 'ls':
        if ($argc !== 3)
            error_exit("Usage: tcloud ls <remote_path>");
        ls($argv[2]);
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

    case 'remove':
        if ($argc !== 3)
            error_exit("Usage: tcloud remove <remote_path>");
        remove($argv[2]);
        break;

    case 'remote':
        if ($argc < 3)
            error_exit("Usage: tcloud remote [get set]");
        if ($argv[2] === 'get') {
            if ($argc !== 3)
                error_exit("Usage: tcloud remote get");
            echo "Remote address: " . get_remote_address() . "\n";
        } elseif ($argv[2] === 'set') {
            if ($argc !== 4)
                error_exit("Usage: tcloud remote set http(s)://<remote_address>");
            file_put_contents(getenv('HOME') . '/.tcloud/remote', $argv[3]);
            echo "Remote address set to " . $argv[3] . "\n";
            echo "Enter your token: ";
            $token = trim(fgets(STDIN));
            file_put_contents(getenv('HOME') . '/.tcloud/token', $token);
            echo "Token saved.\n";
        } else {
            error_exit("Unknown remote command: " . $argv[2]);
        }
        break;

    default:
        error_exit("Unknown command: " . $argv[1]);
}
?>
