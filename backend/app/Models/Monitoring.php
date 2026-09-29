<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Monitoring extends Model
{
    use AuditsChanges;
    use HasFactory;
    use HasPublicId;

    /** Clinical — log field names only, redact PHI values (Spec 5 Decision A). */
    protected bool $auditRedactValues = true;

    protected $fillable = [
        'ncp_record_id', 'intervention_revision_id', 'observed_at', 'visit_type',
        'weight', 'height', 'edema_present', 'dry_weight_kg', 'physical_activity_level',
        'pregnancy_lactation_status', 'allergies', 'dietary_restrictions', 'food_dislikes',
        'bmi', 'lab_values', 'intake_notes',
        'symptoms', 'goal_achievement', 'clinical_summary', 'ai_decision',
        'ai_review', 'ai_review_key', 'next_monitoring_date',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'dry_weight_kg' => 'decimal:2',
        'bmi' => 'decimal:2',
        'observed_at' => 'date',
        'edema_present' => 'boolean',
        'allergies' => 'array',
        'food_dislikes' => 'array',
        'lab_values' => 'array',
        'goal_achievement' => 'array',
        'next_monitoring_date' => 'date',
    ];

    protected function auditAttributes(): array
    {
        return [
            'ncp_record_id', 'observed_at', 'visit_type', 'weight', 'height', 'edema_present',
            'dry_weight_kg', 'physical_activity_level', 'pregnancy_lactation_status',
            'allergies', 'dietary_restrictions', 'food_dislikes', 'bmi', 'lab_values', 'intake_notes', 'symptoms',
            'goal_achievement', 'clinical_summary', 'ai_decision', 'ai_review', 'ai_review_key',
            'next_monitoring_date',
        ];
    }

    public function ncpRecord()
    {
        return $this->belongsTo(NcpRecord::class);
    }

    public function interventionRevision(): BelongsTo
    {
        return $this->belongsTo(InterventionRevision::class);
    }

    public function interventionPlans(): HasMany
    {
        return $this->hasMany(Intervention::class, 'source_monitoring_id');
    }
}
