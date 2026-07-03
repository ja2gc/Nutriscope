<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Adds a secondary, publicly-exposed UUID identifier to a model without touching
 * its integer auto-increment primary key (which remains the sole FK/join target).
 * Routes bind on this column via getRouteKeyName(); API Resources expose it as
 * 'id' instead of the raw integer, closing sequential-ID enumeration (IDOR).
 */
trait HasPublicId
{
    protected static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Resolve a public uuid back to the internal integer id, for the small set of
     * write endpoints that still take this model's id as a plain foreign key in
     * the request body (e.g. supplier_id, food_item_id) rather than via route binding.
     */
    public static function idFromUuid(string $uuid): ?int
    {
        return static::where('uuid', $uuid)->value('id');
    }
}
