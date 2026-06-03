<?php

$dir = 'database/factories';
if (!is_dir($dir)) {
    die("Directory $dir not found.\n");
}

$replacements = [
    'User::factory()' => '\App\Models\User::factory()',
    'User::factory()->fss()' => '\App\Models\User::factory()->fss()',
    'User::factory()->rnd()' => '\App\Models\User::factory()->rnd()',
    'Intervention::factory()' => '\App\Models\Intervention::factory()',
    'Patient::factory()' => '\App\Models\Patient::factory()',
    'NcpRecord::factory()' => '\App\Models\NcpRecord::factory()',
    'Supplier::factory()' => '\App\Models\Supplier::factory()',
];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getRealPath();
        $content = file_get_contents($path);
        $original = $content;
        
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }
        
        if ($content !== $original) {
            echo "Fixed references in $path\n";
            file_put_contents($path, $content);
        }
    }
}
echo "Factory namespace fixes completed.\n";
