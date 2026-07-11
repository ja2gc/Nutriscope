<?php

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;

return [
    'log_name' => 'audit',

    /*
    | Taxonomy is defined once on the backend. API presenters can expose these
    | values to clients without requiring a second hard-coded frontend list.
    */
    'taxonomy' => [
        'actions' => AuditAction::class,
        'categories' => AuditCategory::class,
        'domains' => AuditDomain::class,
        'outcomes' => AuditOutcome::class,
        'severities' => AuditSeverity::class,

        'category_actions' => [
            AuditCategory::Security->value => [
                AuditAction::LoginSucceeded->value,
                AuditAction::LoginFailed->value,
                AuditAction::AuthenticationFailed->value,
                AuditAction::Logout->value,
                AuditAction::PasswordChanged->value,
                AuditAction::PasswordReset->value,
                AuditAction::RecoveryEmailChanged->value,
                AuditAction::RecoveryEmailVerified->value,
                AuditAction::RateLimitExceeded->value,
                AuditAction::AuthorizationDenied->value,
                AuditAction::AuditLogViewed->value,
                AuditAction::AccountBlocked->value,
                AuditAction::AccountUnblocked->value,
                AuditAction::IpBlocked->value,
                AuditAction::IpUnblocked->value,
            ],
            AuditCategory::Clinical->value => [
                AuditAction::Created->value,
                AuditAction::Updated->value,
                AuditAction::Deleted->value,
                AuditAction::Viewed->value,
                AuditAction::Downloaded->value,
                AuditAction::Exported->value,
                AuditAction::Uploaded->value,
                AuditAction::Generated->value,
                AuditAction::Approved->value,
            ],
            AuditCategory::Operations->value => [
                AuditAction::Created->value,
                AuditAction::Updated->value,
                AuditAction::Deleted->value,
                AuditAction::Viewed->value,
                AuditAction::Downloaded->value,
                AuditAction::Exported->value,
                AuditAction::Approved->value,
                AuditAction::Received->value,
                AuditAction::Reversed->value,
                AuditAction::Archived->value,
                AuditAction::Adjusted->value,
                AuditAction::Uploaded->value,
                AuditAction::Generated->value,
                AuditAction::Completed->value,
                AuditAction::PriceCorrected->value,
                AuditAction::ProfileChanged->value,
                AuditAction::SettingsChanged->value,
            ],
        ],
    ],

    /* Retention values are defaults pending privacy-owner approval. */
    'retention' => [
        AuditCategory::Security->value => ['days' => 365, 'legal_hold' => false],
        AuditCategory::Clinical->value => ['days' => 2190, 'legal_hold' => false],
        AuditCategory::Operations->value => ['days' => 1095, 'legal_hold' => false],
        'legacy' => ['days' => 90, 'legal_hold' => false],
    ],

    'deduplication' => [
        'chart_view_seconds' => 15 * 60,
        'security_failure_seconds' => 5 * 60,
    ],

    'features' => [
        'export' => env('AUDIT_EXPORT_ENABLED', false),
        'ip_blocking' => env('AUDIT_SECURITY_BLOCKS_ENABLED', false),
    ],
];
