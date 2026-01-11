<?php
$ttl = 60 * 60;
$max_total_size = 256 * 1024 * 1024;

$files = [];
$time = time();
foreach (scandir(__DIR__) as $entry) {
    if ($entry === '.' || $entry === '..' || $entry === 'cleanup.php' || !is_file($entry = __DIR__ . "/$entry"))
        continue;
    $delay = $time - filemtime($entry);
    $files[] = ["path" => $entry, "delay" => $delay];
}
usort($files, fn($a, $b) => $a["delay"] <=> $b["delay"]);
$total_size = 0;
foreach ($files as $file) {
    if ($file["delay"] > $ttl || $total_size > $max_total_size)
        unlink($file["path"]);
    else
        $total_size += filesize($file["path"]);
}
?>
