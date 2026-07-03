<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealPlan extends Model
{
    use HasFactory;
    use \App\Models\Concerns\AuditsChanges;
    use \App\Models\Concerns\HasPublicId;

    /** Clinical — log field names only, redact PHI values (Spec 5 Decision A). */
    protected bool $auditRedactValues = true;
    protected $fillable = [
        'intervention_id', 'patient_id', 'week_start_date', 'generation_type', 'status',
    ];

    protected $casts = [
        'week_start_date' => 'date',
    ];

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(MealPlanDay::class);
    }
}

