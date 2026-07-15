<?php

namespace App\Data;

use InvalidArgumentException;

final readonly class AuditHistorySnapshotDto
{
    /**
     * @param  list<AuditHistoryFieldDto>  $fields
     * @param  list<AuditHistoryTableDto>  $tables
     */
    public function __construct(
        public string $type,
        public string $title,
        public string $reference,
        public array $fields,
        public array $tables,
    ) {
        if (preg_match('/^[a-z0-9_.:-]{1,64}$/iD', $this->type) !== 1
            || trim($this->title) === ''
            || mb_strlen($this->title) > 255
            || trim($this->reference) === ''
            || mb_strlen($this->reference) > 100
            || ! array_is_list($this->fields)
            || ! array_is_list($this->tables)
            || collect($this->fields)->contains(fn (mixed $field): bool => ! $field instanceof AuditHistoryFieldDto)
            || collect($this->tables)->contains(fn (mixed $table): bool => ! $table instanceof AuditHistoryTableDto)) {
            throw new InvalidArgumentException('Invalid audit history snapshot.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'reference' => $this->reference,
            'fields' => array_map(fn (AuditHistoryFieldDto $field): array => $field->toArray(), $this->fields),
            'tables' => array_map(fn (AuditHistoryTableDto $table): array => $table->toArray(), $this->tables),
        ];
    }
}
