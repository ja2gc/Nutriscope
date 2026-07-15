<?php

namespace App\Data;

use InvalidArgumentException;

final readonly class AuditValueDto
{
    private const TYPES = [
        'text', 'number', 'currency', 'quantity', 'boolean', 'date', 'datetime',
        'enum', 'reference', 'field_list', 'redacted',
    ];

    /**
     * @param  string|int|float|bool|list<string>|null  $value
     */
    public function __construct(
        public string $type,
        public string|int|float|bool|array|null $value,
        public ?string $unit = null,
        public ?string $currency = null,
    ) {
        if (! in_array($this->type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unsupported audit value type.');
        }
        if (is_array($this->value)
            && ($this->type !== 'field_list'
                || ! array_is_list($this->value)
                || collect($this->value)->contains(fn (mixed $item): bool => ! is_string($item)
                    || preg_match('/^[a-z0-9_.:-]+$/iD', $item) !== 1))) {
            throw new InvalidArgumentException('Unsupported audit value payload.');
        }
        if ($this->type === 'field_list' && $this->value !== null && ! is_array($this->value)) {
            throw new InvalidArgumentException('Unsupported audit value payload.');
        }
        if ($this->unit !== null && preg_match('/^[A-Za-z0-9%µ/ .-]{1,32}$/D', $this->unit) !== 1) {
            throw new InvalidArgumentException('Unsupported audit value unit.');
        }
        if ($this->currency !== null && preg_match('/^[A-Z]{3}$/D', $this->currency) !== 1) {
            throw new InvalidArgumentException('Unsupported audit value currency.');
        }
    }

    /** @return array{type: string, value: string|int|float|bool|list<string>|null, unit?: string, currency?: string} */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'value' => $this->value,
            'unit' => $this->unit,
            'currency' => $this->currency,
        ], fn (mixed $value, string $key): bool => ! in_array($key, ['unit', 'currency'], true) || $value !== null, ARRAY_FILTER_USE_BOTH);
    }
}
