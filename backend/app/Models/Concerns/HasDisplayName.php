<?php

namespace App\Models\Concerns;

use App\Support\PersonNameRules;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasDisplayName
{
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => PersonNameRules::display(
                $attributes['first_name'] ?? null,
                $attributes['last_name'] ?? null,
                (string) ($attributes['name'] ?? ''),
            ),
        );
    }
}
