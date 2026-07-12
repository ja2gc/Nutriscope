<?php

namespace App\Data;

final readonly class AuditEventDto
{
    /**
     * @param  array{id: ?string, kind: string, name: string, role: ?string}|null  $actor
     * @param  array{type: string, id: ?string, label: string}|null  $subject
     * @param  array{type: string, id: ?string, label: string}|null  $context
     * @param  list<array{key: string, label: string, kind: string, value: string|int|float|array|null}>  $details
     * @param  list<array{field: string, label: string, old_value: string|int|float|bool|null, new_value: string|int|float|bool|null, redacted: bool}>  $changes
     */
    public function __construct(
        public string $id,
        public string $category,
        public string $domain,
        public string $action,
        public string $actionLabel,
        public string $summary,
        public string $severity,
        public string $outcome,
        public ?array $actor,
        public ?array $subject,
        public ?array $context,
        public string $occurredAt,
        public array $details,
        public array $changes,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'domain' => $this->domain,
            'action' => $this->action,
            'action_label' => $this->actionLabel,
            'summary' => $this->summary,
            'severity' => $this->severity,
            'outcome' => $this->outcome,
            'actor' => $this->actor,
            'subject' => $this->subject,
            'context' => $this->context,
            'occurred_at' => $this->occurredAt,
            'details' => $this->details,
            'changes' => $this->changes,
        ];
    }
}
