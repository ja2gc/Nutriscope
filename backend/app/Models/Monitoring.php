<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Monitoring extends Model
{
    use HasFactory;
    use \App\Models\Concerns\AuditsChanges;

    /** Clinical — log field names only, redact PHI values (Spec 5 Decision A). */
    protected bool $auditRedactValues = true;
    
    protected $fillable = [
        'ncp_record_id', 'weight', 'bmi', 'lab_values', 'intake_notes',
        'symptoms', 'goal_achievement', 'clinical_summary', 'ai_decision',
        'ai_review', 'ai_review_key', 'next_monitoring_date'
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'bmi' => 'decimal:2',
        'lab_values' => 'array',
        'goal_achievement' => 'array',
        'next_monitoring_date' => 'date',
    ];

    public function ncpRecord()
    {
        return $this->belongsTo(NcpRecord::class);
    }

}

