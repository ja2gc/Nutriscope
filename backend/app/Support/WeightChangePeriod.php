<?php

namespace App\Support;

final class WeightChangePeriod
{
    /**
     * @return array{value: int, unit: 'weeks'|'months'}|null
     */
    public static function parseLegacy(?string $value): ?array
    {
        if ($value === null || preg_match('/^\s*(\d+)\s+(week|weeks|month|months)\s*$/i', $value, $matches) !== 1) {
            return null;
        }

        $quantity = (int) $matches[1];
        if ($quantity < 1 || $quantity > 65535) {
            return null;
        }

        return [
            'value' => $quantity,
            'unit' => str_starts_with(strtolower($matches[2]), 'week') ? 'weeks' : 'months',
        ];
    }

    public static function format(?int $value, ?string $unit): ?string
    {
        if ($value === null || $value < 1 || ! in_array($unit, ['weeks', 'months'], true)) {
            return null;
        }

        $label = $value === 1 ? rtrim($unit, 's') : $unit;

        return "{$value} {$label}";
    }
}
