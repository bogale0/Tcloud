<?php
$ttl = 5 * 60;
$time = time();
foreach (scandir(__DIR__) as $entry) {
    if ($entry === "." || $entry === ".." || $entry === "cleanup.php")
        continue;
    $entry = __DIR__ . "/$entry";
    if (!is_file($entry))
        continue;
    $delay = $time - filectime($entry);
    if ($delay > $ttl)
        unlink($entry);
}
?>
