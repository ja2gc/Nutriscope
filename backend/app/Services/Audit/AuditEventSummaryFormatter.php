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
     */
    public function format(
        string $action,
        string $actionLabel,
        ?array $actor,
        ?array $subject,
        ?array $context,
    ): string {
        $actorName = $actor['name'] ?? 'Unknown actor';
        $target = $subject['label'] ?? $context['label'] ?? 'Audit event';
        $targetLower = Str::lcfirst($target);

        return match ($action) {
            AuditAction::Created->value => "{$actorName} created {$targetLower}.",
            AuditAction::Updated->value => "{$actorName} updated {$targetLower}.",
            AuditAction::Deleted->value => "{$actorName} deleted {$targetLower}.",
            AuditAction::Viewed->value => "{$actorName} viewed {$targetLower}.",
            AuditAction::Downloaded->value => "{$actorName} downloaded {$targetLower}.",
            AuditAction::Failed->value => "{$actorName} failed {$targetLower}.",
            AuditAction::Suspended->value => "{$actorName} suspended {$targetLower}.",
            AuditAction::Unsuspended->value => "{$actorName} unsuspended {$targetLower}.",
            AuditAction::Discontinued->value => "{$actorName} discontinued {$targetLower}.",
            AuditAction::Archived->value => "{$actorName} archived {$targetLower}.",
            default => "{$actorName} recorded {$actionLabel} for {$targetLower}.",
        };
    }
}
