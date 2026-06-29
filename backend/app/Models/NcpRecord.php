<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NcpRecord extends Model
{
    use HasFactory;
    use \App\Models\Concerns\AuditsChanges;

    /** Clinical — log field names only, redact PHI values (Spec 5 Decision A). */
    protected bool $auditRedactValues = true;
    protected $fillable = [
        'patient_id', 'rnd_user_id', 'type', 'status', 'risk_score',
        'risk_score_manual_override', 'risk_score_manual_factors',
    ];

    protected $casts = [
        'risk_score' => 'decimal:2',
        'risk_score_manual_override' => 'boolean',
        'risk_score_manual_factors' => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function rnd(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rnd_user_id');
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(Assessment::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    public function intervention(): HasOne
    {
        return $this->hasOne(Intervention::class);
    }

    public function monitorings(): HasMany
    {
        return $this->hasMany(Monitoring::class);
    }

}
