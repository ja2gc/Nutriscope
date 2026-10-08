<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\HasPublicId;
use App\Services\Reports\ReportConfigurationSnapshot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealPlan extends Model
{
    use AuditsChanges;
    use HasFactory;
    use HasPublicId;

    /** Clinical — log field names only, redact PHI values (Spec 5 Decision A). */
    protected bool $auditRedactValues = true;

    protected $fillable = [
        'intervention_id', 'patient_id', 'week_start_date', 'generation_type', 'needs_rescaling', 'scaled_at', 'status',
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'needs_rescaling' => 'boolean',
        'scaled_at' => 'datetime',
        'report_configuration_snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $mealPlan): void {
            $mealPlan->report_configuration_snapshot = app(ReportConfigurationSnapshot::class)
                ->capture(['patient_menu_plan']);
        });
    }

    protected function auditAttributes(): array
    {
        return ['intervention_id', 'patient_id', 'week_start_date', 'generation_type', 'needs_rescaling', 'scaled_at', 'status'];
    }

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
