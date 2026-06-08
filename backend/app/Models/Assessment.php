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
    ];

    protected $casts = [
        'allergies'    => 'array',
        'food_dislikes'=> 'array',
        'medications'  => 'array',
        'weight'       => 'decimal:2',
        'height'       => 'decimal:2',
        'bmi'          => 'decimal:2',
        'muac_mm'      => 'float',
        'waist_cm'     => 'float',
        'hip_cm'       => 'float',
    ];

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

