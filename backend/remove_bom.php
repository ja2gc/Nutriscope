<?php

$dirs = ['app/Models', 'database/factories', 'database/migrations', 'tests'];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $path = $file->getRealPath();
            $content = file_get_contents($path);
            if (substr($content, 0, 3) === "\xef\xbb\xbf") {
                echo "Removing BOM from $path\n";
                file_put_contents($path, substr($content, 3));
            }
        }
    }
}
echo "BOM check completed.\n";
