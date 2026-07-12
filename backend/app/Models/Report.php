<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class Report extends Model
{
    use HasFactory;
    use HasPublicId;

    public const ADMIN_ALLOWED_TYPES = [
        'demographic_census',
        'program_project_activity',
        'menu_calendar',
        'procurement_pack',
        'accomplishment_report',
    ];

    protected $fillable = [
        'user_id', 'audit_patient_id', 'audit_ncp_record_id', 'audit_owner_id',
        'title', 'type', 'archive_identity', 'filters', 'parameters', 'snapshot',
        'file_path', 'status', 'generated_at', 'expires_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'parameters' => 'array',
        'snapshot' => 'array',
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $report): void {
            if ($report->getOriginal('status') === 'archived') {
                throw new RuntimeException('Archived reports are immutable.');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
