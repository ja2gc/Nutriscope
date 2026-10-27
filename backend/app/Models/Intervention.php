<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class Intervention extends Model
{
    use AuditsChanges;
    use HasFactory;
    use HasPublicId;

    /** Clinical — log field names only, redact PHI values (Spec 5 Decision A). */
    protected bool $auditRedactValues = true;

    protected $fillable = [
        'ncp_record_id', 'source_monitoring_id', 'goal_type', 'disease_stage', 'displayed_nutrients',
        'energy_kcal', 'protein_g', 'carbs_g', 'fat_g', 'fluid_ml',
        'micronutrient_limits', 'education_notes', 'counseling_goals',
        'nutrient_operators', 'weight_target',
        'barriers', 'strategies', 'session_type',
        'next_followup_date', 'energy_adjustment_mode', 'energy_adjustment_kcal', 'fluid_mode',
        'calculation_options', 'calculation_snapshot', 'nutrition_support', 'formula_items',
    ];

    protected $casts = [
        'displayed_nutrients' => 'array',
        'formula_items' => 'array',
        'micronutrient_limits' => 'array',
        'nutrient_operators' => 'array',
        'weight_target' => 'array',
        'energy_adjustment_kcal' => 'integer',
        'calculation_options' => 'array',
        'calculation_snapshot' => 'array',
        'next_followup_date' => 'date',
        'energy_kcal' => 'decimal:2',
        'protein_g' => 'decimal:2',
        'carbs_g' => 'decimal:2',
        'fat_g' => 'decimal:2',
        'fluid_ml' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $plan): void {
            if ((int) $plan->ncpRecord->latestIntervention()->value('id') !== $plan->id) {
                throw ValidationException::withMessages([
                    'intervention' => ['Historical Intervention Plans are view only.'],
                ]);
            }
        });
    }

    protected function auditAttributes(): array
    {
        return [
            'ncp_record_id', 'goal_type', 'disease_stage', 'displayed_nutrients',
            'energy_kcal', 'protein_g', 'carbs_g', 'fat_g', 'fluid_ml',
            'micronutrient_limits', 'education_notes', 'counseling_goals', 'barriers',
            'nutrient_operators', 'weight_target',
            'strategies', 'session_type', 'next_followup_date',
            'energy_adjustment_mode', 'energy_adjustment_kcal', 'fluid_mode', 'calculation_options', 'calculation_snapshot', 'nutrition_support', 'formula_items',
        ];
    }

    public function ncpRecord(): BelongsTo
    {
        return $this->belongsTo(NcpRecord::class);
    }

    public function sourceMonitoring(): BelongsTo
    {
        return $this->belongsTo(Monitoring::class, 'source_monitoring_id');
    }

    public function mealPlans(): HasMany
    {
        return $this->hasMany(MealPlan::class);
    }

    public function mealPlan(): HasOne
    {
        return $this->hasOne(MealPlan::class);
    }

    public function reportPlanAvailable(): bool
    {
        return match ($this->nutrition_support ?? 'oral') {
            'tube_feeding' => ! empty($this->formula_items),
            'no_food_menu' => true,
            default => $this->mealPlan !== null,
        };
    }

    public function reportUnavailableReason(): ?string
    {
        return $this->reportPlanAvailable() ? null : ($this->nutrition_support === 'tube_feeding'
            ? 'Save at least one formula before preparing this report.' : 'Save a menu plan before preparing this report.');
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

        if ($target === null) {
            return true;
        }

        $field = ['energy' => 'energy_kcal', 'protein' => 'protein_g', 'carbs' => 'carbs_g', 'fat' => 'fat_g'][$nutrient] ?? $nutrient;

        return match ($this->nutrient_operators[$field] ?? null) {
            'max' => $actual <= $target,
            'min' => $actual >= $target,
            default => $target === 0.0 || abs($actual - $target) / $target <= 0.10,
        };
    }

    public function foodNutrientLimits(): array
    {
        $limits = $this->micronutrient_limits ?? [];
        foreach (['energy_kcal' => 'energy', 'protein_g' => 'protein', 'carbs_g' => 'carbs', 'fat_g' => 'fat'] as $field => $key) {
            $operator = $this->nutrient_operators[$field] ?? null;
            if (in_array($operator, ['min', 'max'], true) && $this->{$field} !== null) {
                $limits[$key] = [$operator => (float) $this->{$field}, 'unit' => $key === 'energy' ? 'kcal' : 'g'];
            }
        }

        return $limits;
    }
}
