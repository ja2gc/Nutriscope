<?php

namespace App\Http\Requests\Concerns;

use App\Rules\PersonName;
use App\Support\PersonNameRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

trait ValidatesPersonNameChanges
{
    /** @return array{first_name: array<int, mixed>, last_name: array<int, mixed>} */
    protected function splitNameUpdateRules(Model $person): array
    {
        return [
            'first_name' => $this->namePartUpdateRules('first_name', $person),
            'last_name' => $this->namePartUpdateRules('last_name', $person),
        ];
    }

    /** @return array<int, mixed> */
    private function namePartUpdateRules(string $attribute, Model $person): array
    {
        return [
            Rule::requiredIf($this->requiresNamePart($attribute, $person)),
            'string',
            new PersonName,
        ];
    }

    private function requiresNamePart(string $attribute, Model $person): bool
    {
        $splitChange = $this->exists('first_name') || $this->exists('last_name');
        $legacyChange = $this->exists('name')
            && $this->input('name') !== $person->getAttribute('name');

        if (! $splitChange && ! $legacyChange) {
            return false;
        }

        if ($this->exists($attribute) || $legacyChange) {
            return true;
        }

        return PersonNameRules::normalize($person->getAttribute('first_name')) === null
            || PersonNameRules::normalize($person->getAttribute('last_name')) === null;
    }
}
