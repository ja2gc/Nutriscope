<?php

namespace App\Support;

class InterventionGuidance
{
    public const FIELD_MAX = 1200;

    public const TOTAL_MAX = 2200;

    public const FIELDS = [
        'education_notes',
        'counseling_goals',
        'barriers',
        'strategies',
    ];

    /** @param array<string,mixed> $values */
    public static function characterCount(array $values): int
    {
        return collect(self::FIELDS)
            ->sum(fn (string $field): int => mb_strlen((string) ($values[$field] ?? '')));
    }
}
