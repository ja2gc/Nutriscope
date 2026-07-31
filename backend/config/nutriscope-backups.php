<?php

return [
    'disk' => env('BACKUP_DISK', 'backups'),
    'queue' => env('BACKUP_QUEUE', 'backups'),
    'timezone' => env('BACKUP_TIMEZONE', 'Asia/Manila'),
    'recoverable_hours' => 48,
    'manual_rate_limit_per_hour' => 2,
    'alert_email' => env('BACKUP_ALERT_EMAIL'),
    'retention' => [
        'daily' => 3,
        'weekly' => 2,
        'monthly' => 3,
    ],
];
