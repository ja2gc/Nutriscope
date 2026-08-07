<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    'uploads' => env('UPLOADS_DISK', 'public'),
    'private_uploads' => env('PRIVATE_UPLOADS_DISK', 'private_uploads'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('UPLOADS_PUBLIC_URL') ?: rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        'backups' => [
            'driver' => env('BACKUP_FILESYSTEM_DRIVER', 's3'),
            'key' => env('BACKUP_ACCESS_KEY_ID'),
            'secret' => env('BACKUP_SECRET_ACCESS_KEY'),
            'region' => env('BACKUP_DEFAULT_REGION', 'auto'),
            'bucket' => env('BACKUP_BUCKET'),
            'endpoint' => env('BACKUP_ENDPOINT'),
            'use_path_style_endpoint' => env('BACKUP_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'private',
            'throw' => true,
            'report' => true,
        ],

        'private_uploads' => [
            'driver' => env('PRIVATE_UPLOADS_DRIVER', 'local'),
            'root' => storage_path('app/private-uploads'),
            'key' => env('PRIVATE_UPLOADS_ACCESS_KEY_ID'),
            'secret' => env('PRIVATE_UPLOADS_SECRET_ACCESS_KEY'),
            'region' => env('PRIVATE_UPLOADS_REGION', 'auto'),
            'bucket' => env('PRIVATE_UPLOADS_BUCKET'),
            'endpoint' => env('PRIVATE_UPLOADS_ENDPOINT'),
            'use_path_style_endpoint' => env('PRIVATE_UPLOADS_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'private',
            'throw' => true,
            'report' => true,
        ],

        'report_cache' => [
            'driver' => 'local',
            'root' => storage_path('app/report-cache'),
            'visibility' => 'private',
            'throw' => true,
            'report' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
