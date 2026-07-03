<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diagnosis extends Model
{
    use HasFactory;
    use \App\Models\Concerns\AuditsChanges;
    use \App\Models\Concerns\HasPublicId;

    /** Clinical — log field names only, redact PHI values (Spec 5 Decision A). */
    protected bool $auditRedactValues = true;
    protected $fillable = [
        'ncp_record_id', 'domain', 'problem', 'label', 'etiology',
        'signs_symptoms', 'pes_statement', 'extra_notes', 'ai_generated',
    ];

    protected $casts = [
        'ai_generated' => 'boolean',
    ];

    public function ncpRecord(): BelongsTo
    {
        return $this->belongsTo(NcpRecord::class);
    }

    /**
     * Build PES statement from P, E, S components.
     */
    public static function buildPes(string $problem, string $etiology, string $signSymptoms): string
    {
        return "{$problem} related to {$etiology} as evidenced by {$signSymptoms}";
    }
}

