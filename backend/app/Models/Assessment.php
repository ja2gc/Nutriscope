<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Assessment extends Model
{
    protected $fillable = [
        'ncp_record_id',
        'dietary_intake', 'appetite_changes', 'dietary_restrictions', 'supplements', 'knowledge_notes',
        'weight', 'height', 'bmi', 'body_composition',
        'medical_history', 'social_history', 'lifestyle',
        'allergies', 'food_dislikes', 'medications',
        'rnd_summary',
    ];

    protected $casts = [
        'allergies'    => 'array',
        'food_dislikes'=> 'array',
        'medications'  => 'array',
        'weight'       => 'decimal:2',
        'height'       => 'decimal:2',
        'bmi'          => 'decimal:2',
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
