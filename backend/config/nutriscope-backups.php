<?php

return [
    'disk' => env('BACKUP_DISK', 'backups'),
    'queue' => env('BACKUP_QUEUE', 'backups'),
    'timezone' => env('BACKUP_TIMEZONE', 'Asia/Manila'),
    'scheduler_heartbeat_key' => 'nutriscope:backups:scheduler-heartbeat',
    'multiple_instances' => (bool) env('APP_MULTIPLE_INSTANCES', false),
    'recoverable_hours' => 48,
    'manual_rate_limit_per_hour' => 2,
    'manual_retention_days' => 7,
    'alert_email' => env('BACKUP_ALERT_EMAIL'),
    'mysql_binary' => env('MYSQL_BINARY', 'mysql'),
    'retention' => [
        'daily' => 3,
        'weekly' => 2,
        'monthly' => 3,
    ],
];
