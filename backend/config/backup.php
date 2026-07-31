<?php

use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

return [
    'backup' => [
        'name' => env('BACKUP_NAME', 'nutriscope-database'),
        'source' => [
            'files' => [
                'include' => [],
                'exclude' => [],
                'follow_links' => false,
                'ignore_unreadable_directories' => true,
                'relative_path' => null,
            ],
            'databases' => ['mysql'],
        ],
        'database_dump_compressor' => null,
        'database_dump_file_timestamp_format' => 'Y-m-d-H-i-s',
        'database_dump_filename_base' => 'database',
        'database_dump_file_extension' => 'sql',
        'destination' => [
            'compression_method' => ZipArchive::CM_DEFLATE,
            'compression_level' => 6,
            'filename_prefix' => 'database-',
            'disks' => [env('BACKUP_DISK', 'backups')],
            'continue_on_failure' => false,
        ],
        'temporary_directory' => storage_path('app/backup-temp'),
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'encryption' => 'aes256',
        'verify_backup' => true,
        'tries' => 1,
        'retry_delay' => 0,
    ],
    'notifications' => [
        'notifications' => [],
        'mail' => [
            'to' => env('BACKUP_ALERT_EMAIL') ?: env('MAIL_FROM_ADDRESS', 'no-reply@nutriscope.local'),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'no-reply@nutriscope.local'),
                'name' => env('MAIL_FROM_NAME', 'NutriScope'),
            ],
        ],
    ],
    'monitor_backups' => [
        [
            'name' => env('BACKUP_NAME', 'nutriscope-database'),
            'disks' => [env('BACKUP_DISK', 'backups')],
            'health_checks' => [
                MaximumAgeInDays::class => 2,
                MaximumStorageInMegabytes::class => 2048,
            ],
        ],
    ],
    'cleanup' => [
        'strategy' => DefaultStrategy::class,
        'default_strategy' => [
            'keep_all_backups_for_days' => 3,
            'keep_daily_backups_for_days' => 3,
            'keep_weekly_backups_for_weeks' => 2,
            'keep_monthly_backups_for_months' => 3,
            'keep_yearly_backups_for_years' => 0,
            'delete_oldest_backups_when_using_more_megabytes_than' => 2048,
        ],
        'tries' => 1,
        'retry_delay' => 0,
    ],
];
