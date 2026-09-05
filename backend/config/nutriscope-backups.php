<?php

return [
    'disk' => env('BACKUP_DISK', 'backups'),
    'queue' => env('BACKUP_QUEUE', 'backups'),
    'timezone' => env('BACKUP_TIMEZONE', 'Asia/Manila'),
    'scheduler_heartbeat_key' => 'nutriscope:backups:scheduler-heartbeat',
    'multiple_instances' => (bool) env('APP_MULTIPLE_INSTANCES', false),
    'recoverable_hours' => 48,
    'manual_rate_limit_per_hour' => 2,
    'alert_email' => env('BACKUP_ALERT_EMAIL'),
    'mysql_binary' => env('MYSQL_BINARY', 'mysql'),
    'restore_enabled' => (bool) env('BACKUP_RESTORE_ENABLED', false),
    'recovery_control_tables' => [
        'activity_log',
        'audit_revisions',
        'backup_manifest_objects',
        'backup_manifests',
        'backup_runs',
        'backup_schedule_periods',
        'backup_schedule_settings',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'recovery_requests',
        'recovery_tests',
    ],
    'retention' => [
        'daily' => 3,
        'weekly' => 2,
        'monthly' => 3,
    ],
];
