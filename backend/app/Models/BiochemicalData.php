<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiochemicalData extends Model
{
    use HasFactory;

    protected $table = 'biochemical_data';

    protected $fillable = [
        'assessment_id',
        'albumin', 'hematocrit', 'bun', 'hemoglobin', 'calcium', 'ldl',
        'cholesterol', 'phosphate', 'magnesium', 'creatinine', 'potassium', 'glucose',
        'sodium', 'hba1c', 'triglycerides', 'hdl', 'urr', 'bp', 'abg', 'others',
    ];

    protected $casts = [
        'others' => 'array',
        'albumin' => 'decimal:2',
        'hematocrit' => 'decimal:2',
        'bun' => 'decimal:2',
        'hemoglobin' => 'decimal:2',
        'calcium' => 'decimal:2',
        'ldl' => 'decimal:2',
        'cholesterol' => 'decimal:2',
        'phosphate' => 'decimal:2',
        'magnesium' => 'decimal:2',
        'creatinine' => 'decimal:2',
        'potassium' => 'decimal:2',
        'glucose' => 'decimal:2',
        'sodium' => 'decimal:2',
        'hba1c' => 'decimal:2',
        'triglycerides' => 'decimal:2',
        'hdl' => 'decimal:2',
        'urr' => 'decimal:2',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
