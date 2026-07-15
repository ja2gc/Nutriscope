<?php

namespace App\Data;

use InvalidArgumentException;

final readonly class AuditHistoryTableDto
{
    /**
     * @param  array<string, string>  $columns
     * @param  list<AuditHistoryTableRowDto>  $rows
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $columns,
        public array $rows,
    ) {
        if (preg_match('/^[a-z0-9_.:-]{1,64}$/iD', $this->key) !== 1
            || trim($this->label) === ''
            || mb_strlen($this->label) > 80
            || ! array_is_list($this->rows)
            || collect($this->rows)->contains(fn (mixed $row): bool => ! $row instanceof AuditHistoryTableRowDto)
            || collect($this->columns)->contains(fn (mixed $label, mixed $key): bool => ! is_string($key)
                || preg_match('/^[a-z0-9_.:-]{1,64}$/iD', $key) !== 1
                || ! is_string($label)
                || trim($label) === '')) {
            throw new InvalidArgumentException('Invalid audit history table.');
        }
    }

    /** @return array{key: string, label: string, columns: array<string, string>, rows: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'columns' => $this->columns,
            'rows' => array_map(
                fn (AuditHistoryTableRowDto $row): array => $row->toArray(),
                $this->rows,
            ),
        ];
    }
}
