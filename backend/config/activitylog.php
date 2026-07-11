<?php

use App\Models\AuditActivity;

return [
    'enabled' => env('ACTIVITY_LOGGER_ENABLED', true),
    // Category retention and legal holds are enforced by the audit pruner.
    'delete_records_older_than_days' => null,
    'default_log_name' => 'default',
    'default_auth_driver' => null,
    'subject_returns_soft_deleted_models' => false,
    'activity_model' => AuditActivity::class,
    'table_name' => env('ACTIVITY_LOGGER_TABLE_NAME', 'activity_log'),
    'database_connection' => env('ACTIVITY_LOGGER_DB_CONNECTION'),
];
