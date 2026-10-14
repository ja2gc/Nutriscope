<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'official_file_stored_object_id', 'source_fingerprint', 'content_hash',
        'template_version', 'appearance_version', 'cache_path', 'cache_expires_at',
        'archived_at', 'retention_expires_at', 'report_covered_until', 'report_covered_from',
        'review_state', 'review_version', 'submitted_at', 'reviewed_at', 'reviewed_by', 'rejection_reason',
    ];

    protected $casts = [
        'filters' => 'array',
        'parameters' => 'array',
        'snapshot' => 'array',
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
        'cache_expires_at' => 'datetime',
        'archived_at' => 'datetime',
        'retention_expires_at' => 'datetime',
        'report_covered_until' => 'date',
        'report_covered_from' => 'date',
        'review_version' => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function officialFile(): BelongsTo
    {
        return $this->belongsTo(StoredObject::class, 'official_file_stored_object_id');
    }

    public function hasFinalizedPurchaseOrderFile(): bool
    {
        $poId = $this->parameters['purchase_order_id'] ?? null;

        return $this->official_file_stored_object_id !== null
            && in_array($this->type, ['procurement_pack', 'program_project_activity'], true)
            && $poId !== null
            && PurchaseOrder::query()->whereKey($poId)->whereIn('lifecycle_status', ['completed', 'archived'])->exists();
    }
}
