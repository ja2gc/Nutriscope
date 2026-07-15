<?php

namespace App\Data;

final readonly class AuditHistoryDto
{
    public function __construct(
        public string $id,
        public AuditEventDto $event,
        public string $serializer,
        public int $schemaVersion,
        public string $occurredAt,
        public ?AuditHistorySnapshotDto $before,
        public ?AuditHistorySnapshotDto $after,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event->toArray(),
            'version' => [
                'serializer' => $this->serializer,
                'schema_version' => $this->schemaVersion,
                'occurred_at' => $this->occurredAt,
            ],
            'before' => $this->before?->toArray(),
            'after' => $this->after?->toArray(),
            'read_only' => true,
        ];
    }
}
