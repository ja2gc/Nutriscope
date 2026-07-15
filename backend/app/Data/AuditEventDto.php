<?php

namespace App\Data;

final readonly class AuditEventDto
{
    /**
     * @param  array{id: ?string, kind: string, name: string, role: ?string}|null  $actor
     * @param  array{type: string, id: ?string, label: string}|null  $subject
     * @param  array{type: string, id: ?string, label: string}|null  $context
     * @param  array{display_name: string}|null  $patient
     * @param  list<array{key: string, label: string, value: AuditValueDto}>  $details
     * @param  list<array{field: string, label: string, before: AuditValueDto, after: AuditValueDto, redacted: bool}>  $changes
     */
    public function __construct(
        public string $id,
        public string $module,
        public string $category,
        public string $domain,
        public string $recordType,
        public string $action,
        public string $actionLabel,
        public string $summary,
        public string $severity,
        public string $outcome,
        public ?array $actor,
        public ?array $subject,
        public ?array $context,
        public ?array $patient,
        public ?string $ncpReference,
        public string $detailMode,
        public ?string $reason,
        public ?AuditHistoryLinkDto $history,
        public ?string $currentRecordUrl,
        public string $occurredAt,
        public array $details,
        public array $changes,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'module' => $this->module,
            'category' => $this->category,
            'domain' => $this->domain,
            'record_type' => $this->recordType,
            'action' => $this->action,
            'action_label' => $this->actionLabel,
            'summary' => $this->summary,
            'severity' => $this->severity,
            'outcome' => $this->outcome,
            'actor' => $this->actor,
            'subject' => $this->subject,
            'context' => $this->context,
            'patient' => $this->patient,
            'ncp_reference' => $this->ncpReference,
            'detail_mode' => $this->detailMode,
            'reason' => $this->reason,
            'history' => $this->history?->toArray(),
            'current_record_url' => $this->currentRecordUrl,
            'occurred_at' => $this->occurredAt,
            'details' => array_map(fn (array $detail): array => [
                'key' => $detail['key'],
                'label' => $detail['label'],
                'kind' => $detail['value']->type,
                'value' => $detail['value']->value,
                'typed_value' => $detail['value']->toArray(),
            ], $this->details),
            'changes' => array_map(fn (array $change): array => [
                'field' => $change['field'],
                'label' => $change['label'],
                'old_value' => $change['before']->value,
                'new_value' => $change['after']->value,
                'before' => $change['before']->toArray(),
                'after' => $change['after']->toArray(),
                'redacted' => $change['redacted'],
            ], $this->changes),
        ];
    }
}
