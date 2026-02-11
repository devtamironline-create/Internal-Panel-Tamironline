<?php
$dir = __DIR__ . '/../storage/framework/views';

$files = glob($dir . '/*.php');
$count = 0;
foreach ($files as $file) {
    if (unlink($file)) $count++;
}

echo "Deleted {$count} compiled view files.<br>";
echo "Owner: " . posix_getpwuid(posix_geteuid())['name'] . "<br>";
echo "Dir writable: " . (is_writable($dir) ? 'YES' : 'NO') . "<br>";
echo "Done! Now delete this file.";
