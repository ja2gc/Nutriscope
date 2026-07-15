<?php

namespace App\Data;

use InvalidArgumentException;

final readonly class AuditHistoryTableRowDto
{
    /** @param array<string, AuditValueDto> $values */
    public function __construct(
        public string $key,
        public array $values,
        public ?string $change = null,
    ) {
        if (trim($this->key) === '' || mb_strlen($this->key) > 100
            || ($this->change !== null && ! in_array($this->change, ['added', 'changed', 'removed'], true))
            || collect($this->values)->contains(fn (mixed $value, mixed $key): bool => ! is_string($key)
                || preg_match('/^[a-z0-9_.:-]{1,64}$/iD', $key) !== 1
                || ! $value instanceof AuditValueDto)) {
            throw new InvalidArgumentException('Invalid audit history table row.');
        }
    }

    /** @return array{key: string, values: array<string, array<string, mixed>>, change?: string} */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'values' => collect($this->values)
                ->map(fn (AuditValueDto $value): array => $value->toArray())
                ->all(),
            'change' => $this->change,
        ], fn (mixed $value): bool => $value !== null);
    }
}
