<?php

namespace App\Rules;

use App\Support\PersonNameRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PersonName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (PersonNameRules::containsControlCharacters($value)) {
            $fail('The :attribute field must not contain control characters.');

            return;
        }

        $normalized = PersonNameRules::normalize($value);
        if ($normalized !== null && PersonNameRules::exceedsMaximumLength($normalized)) {
            $fail('The :attribute field must not exceed '.PersonNameRules::MAX_LENGTH.' characters.');
        }
    }
}
