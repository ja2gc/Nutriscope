<?php

namespace App\Enums;

enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Viewed = 'viewed';
    case Downloaded = 'downloaded';
    case Exported = 'exported';
    case Approved = 'approved';
    case Ordered = 'ordered';
    case Received = 'received';
    case Reversed = 'reversed';
    case Archived = 'archived';
    case Adjusted = 'adjusted';
    case Uploaded = 'uploaded';
    case Imported = 'imported';
    case Generated = 'generated';
    case Completed = 'completed';
    case PriceCorrected = 'price_corrected';
    case ProfileChanged = 'profile_changed';
    case SettingsChanged = 'settings_changed';
    case LoginSucceeded = 'login_succeeded';
    case LoginFailed = 'login_failed';
    case AuthenticationFailed = 'authentication_failed';
    case Logout = 'logout';
    case PasswordChanged = 'password_changed';
    case PasswordReset = 'password_reset';
    case RecoveryEmailChanged = 'recovery_email_changed';
    case RecoveryEmailVerified = 'recovery_email_verified';
    case RateLimitExceeded = 'rate_limit_exceeded';
    case AuthorizationDenied = 'authorization_denied';
    case AuditLogViewed = 'audit_log_viewed';
    case AccountBlocked = 'account_blocked';
    case AccountUnblocked = 'account_unblocked';

    public function label(): string
    {
        return match ($this) {
            self::PriceCorrected => 'Price corrected',
            self::ProfileChanged => 'Profile changed',
            self::SettingsChanged => 'Settings changed',
            self::LoginSucceeded => 'Login succeeded',
            self::LoginFailed => 'Login failed',
            self::AuthenticationFailed => 'Authentication failed',
            self::PasswordChanged => 'Password changed',
            self::PasswordReset => 'Password reset',
            self::RecoveryEmailChanged => 'Recovery email changed',
            self::RecoveryEmailVerified => 'Recovery email verified',
            self::RateLimitExceeded => 'Rate limit exceeded',
            self::AuthorizationDenied => 'Authorization denied',
            self::AuditLogViewed => 'Audit log viewed',
            self::AccountBlocked => 'Account blocked',
            self::AccountUnblocked => 'Account unblocked',
            default => ucfirst($this->value),
        };
    }
}
