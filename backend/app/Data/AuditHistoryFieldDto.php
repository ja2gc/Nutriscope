<?php

namespace App\Data;

use InvalidArgumentException;

final readonly class AuditHistoryFieldDto
{
    public function __construct(
        public string $key,
        public string $label,
        public AuditValueDto $value,
        public ?string $change = null,
    ) {
        if (preg_match('/^[a-z0-9_.:-]{1,64}$/iD', $this->key) !== 1
            || trim($this->label) === ''
            || mb_strlen($this->label) > 80
            || ($this->change !== null && ! in_array($this->change, ['added', 'changed', 'removed'], true))) {
            throw new InvalidArgumentException('Invalid audit history field.');
        }
    }

    /** @return array{key: string, label: string, value: array<string, mixed>, change?: string} */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->value->toArray(),
            'change' => $this->change,
        ], fn (mixed $value): bool => $value !== null);
    }
}
