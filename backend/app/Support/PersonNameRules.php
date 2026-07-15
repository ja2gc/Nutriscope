<?php

namespace App\Support;

use Illuminate\Support\Str;

final class PersonNameRules
{
    public const MAX_LENGTH = 255;

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::squish($value);

        return $normalized === '' ? null : $normalized;
    }

    public static function containsControlCharacters(string $value): bool
    {
        return preg_match('/\p{Cc}/u', $value) !== 0;
    }

    public static function exceedsMaximumLength(string $value): bool
    {
        return Str::length($value) > self::MAX_LENGTH;
    }

    public static function display(?string $firstName, ?string $lastName, string $legacyName): string
    {
        $firstName = self::normalize($firstName);
        $lastName = self::normalize($lastName);

        if ($firstName === null || $lastName === null) {
            return $legacyName;
        }

        return "{$firstName} {$lastName}";
    }
}
