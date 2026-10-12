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
            AuditAction::Failed->value => "{$actorName} failed {$targetLower}.",
            AuditAction::Suspended->value => "{$actorName} suspended {$targetLower}.",
            AuditAction::Unsuspended->value => "{$actorName} unsuspended {$targetLower}.",
            AuditAction::Discontinued->value => "{$actorName} discontinued {$targetLower}.",
            AuditAction::Archived->value => "{$actorName} archived {$targetLower}.",
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
