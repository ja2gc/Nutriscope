<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalRule extends Model
{
    protected $fillable = [
        'condition', 'stage', 'nutrient_or_food_tag', 'rule_type', 'threshold', 'unit', 'reason',
    ];

    protected $casts = [
        'threshold' => 'decimal:2',
    ];

    /**
     * Get all rules matching given conditions and stages.
     * Used exclusively by RecommendService — never hardcode logic in PHP.
     */
    public static function forConditions(array $conditions, ?array $stages = null): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()
            ->whereIn('condition', $conditions)
            ->when($stages, fn($q) => $q->where(function ($q) use ($stages) {
                $q->whereIn('stage', $stages)->orWhere('stage', 'all');
            }))
            ->orWhere('stage', 'all')
            ->whereIn('condition', $conditions)
            ->get();
    }
}
