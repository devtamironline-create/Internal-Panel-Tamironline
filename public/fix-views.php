<?php
$dirs = [
    __DIR__ . '/../storage/framework/views',
    __DIR__ . '/../storage/framework/cache',
    __DIR__ . '/../storage/framework/sessions',
    __DIR__ . '/../storage/logs',
];

echo "<pre>";
echo "Web server user: " . posix_getpwuid(posix_geteuid())['name'] . "\n\n";

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        echo "SKIP: $dir (not found)\n";
        continue;
    }

    // Delete all files
    $files = glob($dir . '/*');
    $count = 0;
    foreach ($files as $file) {
        if (is_file($file) && @unlink($file)) $count++;
    }

    $writable = is_writable($dir);
    echo basename($dir) . ": deleted $count files, writable=" . ($writable ? 'YES' : 'NO') . "\n";
}

// Try to fix via shell
$storageDir = __DIR__ . '/../storage';
$result = shell_exec("chmod -R 777 " . escapeshellarg($storageDir) . " 2>&1");
echo "\nchmod result: " . ($result ?: "OK (no output)") . "\n";

// Verify
echo "\nAfter fix:\n";
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        echo basename($dir) . ": writable=" . (is_writable($dir) ? 'YES' : 'NO') . "\n";
    }
}

echo "\nDone! DELETE THIS FILE NOW.\n";
echo "</pre>";
