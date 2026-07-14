<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClinicalRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'condition', 'stage', 'nutrient_or_food_tag', 'rule_type', 'threshold', 'unit', 'reason',
    ];

    protected $casts = [
        'threshold' => 'decimal:2',
    ];

    /**
     * Get all rules matching given conditions and stages.
     * Used exclusively by RecommendService â€” never hardcode logic in PHP.
     */
    public static function forConditions(array $conditions, ?array $stages = null): Collection
    {
        return static::query()
            ->whereIn('condition', $conditions)
            ->when($stages, fn ($q) => $q->where(function ($q) use ($stages) {
                $q->whereIn('stage', $stages)->orWhere('stage', 'all');
            }))
            ->orWhere('stage', 'all')
            ->whereIn('condition', $conditions)
            ->get();
    }
}
