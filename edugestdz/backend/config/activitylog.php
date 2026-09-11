<?php
return [
    'enabled' => env('ACTIVITY_LOGGER_ENABLED', true),
    'delete_records_older_than_days' => 365,
    'default_log_name' => 'default',
    'default_auth_driver' => 'api',
    'subject_returns_soft_deleted_models' => false,
    'activity_model' => \Spatie\Activitylog\Models\Activity::class,
    'table_name' => 'audit_logs',
    'database_connection' => env('ACTIVITY_LOGGER_DB_CONNECTION'),
];
