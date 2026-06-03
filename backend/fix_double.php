<?php

$dir = 'database/factories';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getRealPath();
        $content = file_get_contents($path);
        $newContent = preg_replace('/\\\\App\\\\Models\\\\+App\\\\Models\\\\/', '\\App\\Models\\', $content);
        if ($newContent !== $content) {
            echo "Cleaned double namespace in $path\n";
            file_put_contents($path, $newContent);
        }
    }
}
echo "Double namespace cleanup completed.\n";
