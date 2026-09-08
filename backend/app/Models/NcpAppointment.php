<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NcpAppointment extends Model
{
    use AuditsChanges;
    use HasFactory;
    use HasPublicId;

    protected bool $auditRedactValues = true;

    protected $fillable = [
        'patient_id', 'ncp_record_id', 'rnd_user_id', 'rescheduled_from_id',
        'source', 'status', 'purpose', 'scheduled_at', 'started_at', 'finished_at',
        'reason_code', 'completeness_at_start', 'worked_on', 'newly_completed',
    ];

    protected $attributes = ['status' => 'scheduled'];

    protected function auditAttributes(): array
    {
        return [
            'patient_id', 'ncp_record_id', 'source', 'status', 'scheduled_at',
            'started_at', 'finished_at', 'reason_code', 'worked_on', 'newly_completed',
        ];
    }

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'completeness_at_start' => 'array',
            'worked_on' => 'array',
            'newly_completed' => 'array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function ncpRecord(): BelongsTo
    {
        return $this->belongsTo(NcpRecord::class);
    }

    public function rnd(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rnd_user_id');
    }

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_id');
    }

    public function auditReference(): string
    {
        return 'VISIT-'.strtoupper(substr(hash('sha256', strtolower($this->uuid)), 0, 16));
    }
}
