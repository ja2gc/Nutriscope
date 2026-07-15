<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use Illuminate\Support\Str;

class AuditEventSummaryFormatter
{
    /**
     * @param  array{kind: string, name: string}|null  $actor
     * @param  array{label: string}|null  $subject
     * @param  array{label: string}|null  $context
     * @param  list<array{label: string}>  $changes
     */
    public function format(
        string $action,
        string $actionLabel,
        ?array $actor,
        ?array $subject,
        ?array $context,
        array $changes,
    ): string {
        $actorName = $actor['name'] ?? 'Unknown actor';
        $target = $subject['label'] ?? $context['label'] ?? 'Audit event';
        $targetLower = Str::lcfirst($target);
        $fieldList = $this->fieldList(array_column($changes, 'label'));

        return match ($action) {
            AuditAction::Created->value => "{$actorName} created {$targetLower}.",
            AuditAction::Updated->value => $fieldList === null
                ? "{$actorName} updated {$targetLower}."
                : "{$actorName} changed {$fieldList} for {$targetLower}.",
            AuditAction::Deleted->value => "{$actorName} deleted {$targetLower}.",
            AuditAction::Viewed->value => "{$actorName} viewed {$targetLower}.",
            AuditAction::Downloaded->value => "{$actorName} downloaded {$targetLower}.",
            AuditAction::Exported->value => "{$actorName} exported {$targetLower}.",
            AuditAction::Approved->value => "{$actorName} approved {$targetLower}.",
            AuditAction::Ordered->value => "{$actorName} ordered {$targetLower}.",
            AuditAction::Received->value => "{$actorName} received {$targetLower}.",
            AuditAction::Reversed->value => "{$actorName} reversed {$targetLower}.",
            AuditAction::Archived->value => "{$actorName} archived {$targetLower}.",
            AuditAction::Adjusted->value => "{$actorName} adjusted {$targetLower}.",
            AuditAction::Uploaded->value => "{$actorName} uploaded {$targetLower}.",
            AuditAction::Imported->value => "{$actorName} imported {$targetLower}.",
            AuditAction::Generated->value => "{$actorName} generated {$targetLower}.",
            AuditAction::Completed->value => "{$actorName} completed {$targetLower}.",
            AuditAction::PriceCorrected->value => "{$actorName} corrected the price for {$targetLower}.",
            AuditAction::ProfileChanged->value => "{$actorName} changed {$targetLower} profile.",
            AuditAction::SettingsChanged->value => "{$actorName} changed {$targetLower}.",
            AuditAction::LoginSucceeded->value => "{$actorName} logged in through {$target}.",
            AuditAction::LoginFailed->value => "{$actorName} login failed through {$target}.",
            AuditAction::AuthenticationFailed->value => "{$actorName} authentication failed through {$target}.",
            AuditAction::Logout->value => "{$actorName} logged out through {$target}.",
            AuditAction::PasswordChanged->value => "{$actorName} changed a password through {$target}.",
            AuditAction::PasswordReset->value => "{$actorName} reset a password through {$target}.",
            AuditAction::RecoveryEmailChanged->value => "{$actorName} changed a recovery email through {$target}.",
            AuditAction::RecoveryEmailVerified->value => "{$actorName} verified a recovery email through {$target}.",
            AuditAction::RateLimitExceeded->value => "{$actorName} exceeded a rate limit on {$targetLower}.",
            AuditAction::AuthorizationDenied->value => "{$actorName} was denied access to {$targetLower}.",
            AuditAction::AuditLogViewed->value => "{$actorName} viewed {$targetLower}.",
            AuditAction::AccountBlocked->value => "{$actorName} blocked {$targetLower}.",
            AuditAction::AccountUnblocked->value => "{$actorName} unblocked {$targetLower}.",
            default => "{$actorName} recorded {$actionLabel} for {$targetLower}.",
        };
    }

    /** @param list<string> $labels */
    private function fieldList(array $labels): ?string
    {
        $labels = array_values(array_unique(array_filter($labels)));

        return match (count($labels)) {
            0 => null,
            1 => $labels[0],
            2 => $labels[0].' and '.$labels[1],
            default => implode(', ', array_slice($labels, 0, -1)).', and '.$labels[array_key_last($labels)],
        };
    }
}
