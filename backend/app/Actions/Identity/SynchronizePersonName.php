<?php

namespace App\Actions\Identity;

use App\Support\PersonNameRules;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class SynchronizePersonName
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function forCreate(array $attributes): array
    {
        return $this->synchronize($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function forUpdate(Model $person, array $attributes): array
    {
        return $this->synchronize($attributes, $person);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function synchronize(array $attributes, ?Model $person = null): array
    {
        if (! array_key_exists('first_name', $attributes) && ! array_key_exists('last_name', $attributes)) {
            return $attributes;
        }

        $firstName = array_key_exists('first_name', $attributes)
            ? $attributes['first_name']
            : $person?->getAttribute('first_name');
        $lastName = array_key_exists('last_name', $attributes)
            ? $attributes['last_name']
            : $person?->getAttribute('last_name');

        foreach ([$firstName, $lastName] as $value) {
            if (is_string($value) && PersonNameRules::containsControlCharacters($value)) {
                throw new InvalidArgumentException('Names must not contain control characters.');
            }

        }

        $firstName = PersonNameRules::normalize(is_string($firstName) ? $firstName : null);
        $lastName = PersonNameRules::normalize(is_string($lastName) ? $lastName : null);

        foreach ([$firstName, $lastName] as $value) {
            if (is_string($value) && PersonNameRules::exceedsMaximumLength($value)) {
                throw new InvalidArgumentException('Names must not exceed 255 characters.');
            }
        }

        if ($firstName === null || $lastName === null) {
            throw new InvalidArgumentException('A complete first and last name is required.');
        }

        return [
            ...$attributes,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => "{$firstName} {$lastName}",
        ];
    }
}
