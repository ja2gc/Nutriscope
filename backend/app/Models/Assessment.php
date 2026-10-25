<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Assessment extends Model
{
    use AuditsChanges;
    use HasFactory;

    /** Clinical — log field names only, redact PHI values (Spec 5 Decision A). */
    protected bool $auditRedactValues = true;

    protected $fillable = [
        'ncp_record_id',
        'dietary_intake', 'food_recall', 'intake_nutrients', 'appetite_changes', 'dietary_restrictions', 'supplements', 'knowledge_notes',
        'weight', 'height', 'bmi', 'body_composition',
        'medical_history', 'social_history', 'religion', 'lifestyle',
        'allergies', 'food_dislikes', 'medications',
        'rnd_summary',
        'usual_weight', 'nutritional_status', 'weight_loss_percentage', 'weight_loss_period',
        'weight_change_period_value', 'weight_change_period_unit',
        'primary_diagnosis_category', 'primary_diagnosis_other',
        'functional_assessment', 'energy_intake_status', 'ibw_percentage', 'present_diet',
        'physical_assessment', 'chewing_swallowing_difficulties', 'constipation', 'diarrhea_notes',
        'chewing_swallowing_details', 'constipation_details', 'diarrhea_details',
        'food_intolerance', 'nutrient_drug_interaction', 'dietary_intake_method', 'dietary_record_file',
        // Clinical measurement fields (activity level + body measurements)
        'physical_activity_level', 'muac_mm', 'waist_cm', 'hip_cm',
        // Phase 5 — engine inputs
        'stress_factor', 'edema_present', 'dry_weight_kg', 'pregnancy_lactation_status',
    ];

    protected $casts = [
        'food_recall' => 'array',
        'intake_nutrients' => 'array',
        'allergies' => 'array',
        'food_dislikes' => 'array',
        'medications' => 'array',
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'bmi' => 'decimal:2',
        'muac_mm' => 'float',
        'waist_cm' => 'float',
        'hip_cm' => 'float',
        'stress_factor' => 'float',
        'edema_present' => 'boolean',
        'dry_weight_kg' => 'decimal:2',
        'weight_change_period_value' => 'integer',
    ];

    protected function auditAttributes(): array
    {
        return [
            'ncp_record_id', 'dietary_intake', 'food_recall', 'intake_nutrients', 'appetite_changes', 'dietary_restrictions',
            'supplements', 'knowledge_notes', 'weight', 'height', 'bmi', 'body_composition',
            'medical_history', 'social_history', 'religion', 'lifestyle', 'allergies',
            'food_dislikes', 'medications', 'rnd_summary', 'usual_weight', 'nutritional_status',
            'weight_loss_percentage', 'weight_loss_period', 'weight_change_period_value',
            'weight_change_period_unit', 'primary_diagnosis_category', 'primary_diagnosis_other',
            'functional_assessment',
            'energy_intake_status', 'ibw_percentage', 'present_diet', 'physical_assessment',
            'chewing_swallowing_difficulties', 'constipation', 'diarrhea_notes',
            'chewing_swallowing_details', 'constipation_details', 'diarrhea_details',
            'food_intolerance', 'nutrient_drug_interaction', 'dietary_intake_method',
            'dietary_record_file', 'physical_activity_level', 'muac_mm', 'waist_cm', 'hip_cm',
            'stress_factor', 'edema_present', 'dry_weight_kg', 'pregnancy_lactation_status',
        ];
    }

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
        'sedentary' => 'sedentary',
        'light' => 'light',
        'moderate' => 'moderate',
        'very_active' => 'very_active',
        'extra_active' => 'extra_active',
        // common UI/legacy variants
        'lightly_active' => 'light',
        'lightly active' => 'light',
        'light activity' => 'light',
        'moderately_active' => 'moderate',
        'moderately active' => 'moderate',
        'moderate activity' => 'moderate',
        'active' => 'moderate',
        'very active' => 'very_active',
        'very_active_activity' => 'very_active',
        'extra active' => 'extra_active',
        'extremely active' => 'extra_active',
        'extra_active_activity' => 'extra_active',
        'bedridden' => 'sedentary',
        'bed-ridden' => 'sedentary',
        'bed ridden' => 'sedentary',
        'desk job' => 'sedentary',
        'office work' => 'sedentary',
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

    public function screeningDocuments(): HasMany
    {
        return $this->hasMany(ScreeningDocument::class);
    }

    /**
     * Auto-calculate BMI when weight and height are set.
     */
    public function calculateBmi(?float $ageYears = null): ?float
    {
        if ($ageYears !== null && $ageYears < 5) {
            return null;
        }
        if ($this->weight && $this->height && $this->height > 0) {
            $heightM = $this->height / 100;

            return round($this->weight / ($heightM * $heightM), 2);
        }

        return null;
    }

    /** WHO monthly bands or Asia-Pacific adult bands; never a clinical malnutrition diagnosis. */
    public static function classifyBmi(?float $bmi, ?float $ageYears, bool $edemaPresent = false, ?string $maternalStatus = 'none', ?string $sex = null, ?int $ageMonths = null): ?string
    {
        if ($bmi === null || ! is_finite($bmi) || $bmi <= 0 || $ageYears === null || ! is_finite($ageYears) || $ageYears < 5
            || $edemaPresent || ($maternalStatus ?? 'none') !== 'none') {
            return null;
        }

        $months = $ageMonths ?? (int) floor($ageYears * 12);
        if ($months <= 228) {
            if ($months < 61 || ! in_array($sex, ['Male', 'Female'], true)) {
                return null;
            }
            static $reference;
            $reference ??= json_decode(file_get_contents(resource_path('who-bmi-for-age.json')), true, flags: JSON_THROW_ON_ERROR);
            [$severe, $thin, $overweight, $obesity] = $reference[$sex][$months - 61];

            return match (true) {
                $bmi < $severe => 'Severe thinness',
                $bmi < $thin => 'Thinness',
                $bmi > $obesity => 'Obesity',
                $bmi > $overweight => 'Overweight',
                default => 'Normal',
            };
        }

        return match (true) {
            $bmi < 18.5 => 'Underweight',
            $bmi < 23 => 'Normal',
            $bmi < 25 => 'Overweight',
            $bmi < 30 => 'Obese Class I',
            default => 'Obese Class II',
        };
    }
}
