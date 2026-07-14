<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Intervention extends Model
{
    use AuditsChanges;
    use HasFactory;

    /** Clinical — log field names only, redact PHI values (Spec 5 Decision A). */
    protected bool $auditRedactValues = true;

    protected $fillable = [
        'ncp_record_id', 'goal_type', 'disease_stage', 'displayed_nutrients',
        'energy_kcal', 'protein_g', 'carbs_g', 'fat_g', 'fluid_ml',
        'micronutrient_limits', 'education_notes', 'counseling_goals',
        'barriers', 'strategies', 'session_type',
        'next_followup_date',
    ];

    protected $casts = [
        'displayed_nutrients' => 'array',
        'micronutrient_limits' => 'array',
        'next_followup_date' => 'date',
        'energy_kcal' => 'decimal:2',
        'protein_g' => 'decimal:2',
        'carbs_g' => 'decimal:2',
        'fat_g' => 'decimal:2',
        'fluid_ml' => 'decimal:2',
    ];

    protected function auditAttributes(): array
    {
        return [
            'ncp_record_id', 'goal_type', 'disease_stage', 'displayed_nutrients',
            'energy_kcal', 'protein_g', 'carbs_g', 'fat_g', 'fluid_ml',
            'micronutrient_limits', 'education_notes', 'counseling_goals', 'barriers',
            'strategies', 'session_type', 'next_followup_date',
        ];
    }

    public function ncpRecord(): BelongsTo
    {
        return $this->belongsTo(NcpRecord::class);
    }

    public function mealPlans(): HasMany
    {
        return $this->hasMany(MealPlan::class);
    }

    /**
     * Check if a given daily total is within 10% of the target.
     */
    public function isWithinTarget(string $nutrient, float $actual): bool
    {
        $target = match ($nutrient) {
            'energy' => (float) $this->energy_kcal,
            'protein' => (float) $this->protein_g,
            'carbs' => (float) $this->carbs_g,
            'fat' => (float) $this->fat_g,
            default => null,
        };

        if (! $target || $target === 0.0) {
            return true;
        }

        return abs($actual - $target) / $target <= 0.10;
    }
}
