<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Assessment extends Model
{
    use HasFactory;
    protected $fillable = [
        'ncp_record_id',
        'dietary_intake', 'appetite_changes', 'dietary_restrictions', 'supplements', 'knowledge_notes',
        'weight', 'height', 'bmi', 'body_composition',
        'medical_history', 'social_history', 'religion', 'lifestyle',
        'allergies', 'food_dislikes', 'medications',
        'rnd_summary',
        'usual_weight', 'nutritional_status', 'weight_loss_percentage', 'weight_loss_period',
        'functional_assessment', 'energy_intake_status', 'ibw_percentage', 'present_diet',
        'physical_assessment', 'chewing_swallowing_difficulties', 'constipation', 'diarrhea_notes',
        'food_intolerance', 'nutrient_drug_interaction', 'dietary_intake_method', 'dietary_record_file',
        // Clinical measurement fields (activity level + body measurements)
        'physical_activity_level', 'muac_mm', 'waist_cm', 'hip_cm',
        // Phase 5 — engine inputs
        'stress_factor', 'edema_present', 'pregnancy_lactation_status', 'calf_circumference_cm',
    ];

    protected $casts = [
        'allergies'                    => 'array',
        'food_dislikes'                => 'array',
        'medications'                  => 'array',
        'weight'                       => 'decimal:2',
        'height'                       => 'decimal:2',
        'bmi'                          => 'decimal:2',
        'muac_mm'                      => 'float',
        'waist_cm'                     => 'float',
        'hip_cm'                       => 'float',
        'stress_factor'                => 'float',
        'edema_present'                => 'boolean',
        'calf_circumference_cm'        => 'float',
    ];

    /**
     * Normalise physical_activity_level free-text / legacy values to the keys
     * that NutritionPrescriptionService::ACTIVITY_FACTORS expects.
     *
     * The assessment UI may store human-readable or alternate spellings;
     * this map canonicalises them before they reach the engine.
     *
     * Phase 5.2 — ensures the PAL dropdown actually drives TEE.
     */
    public const ACTIVITY_LEVEL_MAP = [
        // canonical keys (pass-through)
        'sedentary'    => 'sedentary',
        'light'        => 'light',
        'moderate'     => 'moderate',
        'very_active'  => 'very_active',
        'extra_active' => 'extra_active',
        // common UI/legacy variants
        'lightly_active'       => 'light',
        'lightly active'       => 'light',
        'light activity'       => 'light',
        'moderately_active'    => 'moderate',
        'moderately active'    => 'moderate',
        'moderate activity'    => 'moderate',
        'active'               => 'moderate',
        'very active'          => 'very_active',
        'very_active_activity' => 'very_active',
        'extra active'         => 'extra_active',
        'extremely active'     => 'extra_active',
        'extra_active_activity'=> 'extra_active',
        'bedridden'            => 'sedentary',
        'bed-ridden'           => 'sedentary',
        'bed ridden'           => 'sedentary',
        'desk job'             => 'sedentary',
        'office work'          => 'sedentary',
    ];

    /**
     * Return the canonical ACTIVITY_FACTORS key for this assessment's PAL,
     * or 'sedentary' as a safe default if unknown.
     */
    public function normalizedActivityLevel(): string
    {
        $raw = strtolower(trim((string) $this->physical_activity_level));
        return self::ACTIVITY_LEVEL_MAP[$raw] ?? 'sedentary';
    }

    public function ncpRecord(): BelongsTo
    {
        return $this->belongsTo(NcpRecord::class);
    }

    public function biochemicalData(): HasOne
    {
        return $this->hasOne(BiochemicalData::class);
    }

    /**
     * Auto-calculate BMI when weight and height are set.
     */
    public function calculateBmi(): ?float
    {
        if ($this->weight && $this->height && $this->height > 0) {
            $heightM = $this->height / 100;
            return round($this->weight / ($heightM * $heightM), 2);
        }
        return null;
    }
}

