<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diagnosis extends Model
{
    use AuditsChanges;
    use HasFactory;
    use HasPublicId;

    public const NO_DIAGNOSIS = 'No nutrition diagnosis at this time';

    public const NO_DIAGNOSIS_WARNING = "Delete the saved 'No nutrition diagnosis at this time' entry before adding or generating a diagnosis.";

    public function isNoDiagnosis(): bool
    {
        return $this->domain === 'NO' && $this->problem === self::NO_DIAGNOSIS;
    }

    /** Clinical — log field names only, redact PHI values (Spec 5 Decision A). */
    protected bool $auditRedactValues = true;

    protected $fillable = [
        'ncp_record_id', 'domain', 'problem', 'label', 'etiology',
        'signs_symptoms', 'pes_statement', 'extra_notes', 'ai_generated', 'builder_data', 'ai_provenance', 'ai_draft_id',
    ];

    protected $casts = [
        'ai_generated' => 'boolean',
        'builder_data' => 'array',
        'ai_provenance' => 'array',
    ];

    protected function auditAttributes(): array
    {
        return [
            'ncp_record_id', 'domain', 'problem', 'label', 'etiology', 'signs_symptoms',
            'pes_statement', 'extra_notes', 'ai_generated',
        ];
    }

    public function ncpRecord(): BelongsTo
    {
        return $this->belongsTo(NcpRecord::class);
    }

    /**
     * Build PES statement from P, E, S components.
     */
    public static function buildPes(string $problem, string $etiology, string $signSymptoms): string
    {
        if ($problem === self::NO_DIAGNOSIS && $etiology === '' && $signSymptoms === '') {
            return $problem;
        }

        return "{$problem} related to {$etiology} as evidenced by {$signSymptoms}";
    }
}
