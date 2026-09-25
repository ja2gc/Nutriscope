<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterventionRevision extends Model
{
    use AuditsChanges;
    use HasFactory;
    use HasPublicId;

    protected bool $auditRedactValues = true;

    protected $fillable = [
        'intervention_id', 'monitoring_id', 'version', 'effective_at', 'reason',
        'actor_user_id', 'source', 'snapshot',
    ];

    protected $casts = [
        'version' => 'integer',
        'effective_at' => 'datetime',
        'snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (InterventionRevision $revision): void {
            $hasMonitoring = $revision->intervention
                ->ncpRecord()
                ->first()
                ?->monitorings()
                ->exists() ?? false;

            if ($revision->version > 1 || $hasMonitoring) {
                throw new \LogicException('Intervention revision history is immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Intervention revision history is immutable.'));
    }

    protected function auditAttributes(): array
    {
        return ['version', 'effective_at', 'reason', 'source', 'snapshot'];
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class);
    }

    public function monitoring(): BelongsTo
    {
        return $this->belongsTo(Monitoring::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
