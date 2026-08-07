<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single-row table holding the shared report header (logo, hospital name, address,
 * accreditation, service). Editable from the Reports → Template Edit tab so the
 * hospital can fix branding without a code change. NOT multi-tenancy (§2.9).
 */
class ReportBranding extends Model
{
    use HasFactory;

    protected $table = 'report_branding';

    protected $fillable = [
        'hospital_name', 'address', 'accreditation', 'service_name',
        'province', 'lgu', 'logo_left_path', 'logo_right_path',
        'logo_left_stored_object_id', 'logo_right_stored_object_id',
    ];

    /** The one-and-only branding row, created on demand if missing. */
    public static function singleton(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function logoLeftObject(): BelongsTo
    {
        return $this->belongsTo(StoredObject::class, 'logo_left_stored_object_id');
    }

    public function logoRightObject(): BelongsTo
    {
        return $this->belongsTo(StoredObject::class, 'logo_right_stored_object_id');
    }
}
