<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ReportConfigurationRevision extends Model
{
    use HasPublicId;

    protected $fillable = ['kind', 'report_template_id', 'created_by', 'origin', 'snapshot'];

    protected $casts = ['snapshot' => 'array'];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id');
    }

    public static function captureTemplate(ReportTemplate $template, string $origin): self
    {
        return self::create([
            'kind' => 'template', 'report_template_id' => $template->id,
            'created_by' => Auth::id(), 'origin' => $origin,
            'snapshot' => [
                'type' => $template->type, 'name' => $template->name,
                'description' => $template->description,
                'signatories' => $template->signatories ?? [],
            ],
        ]);
    }

    public static function captureBranding(ReportBranding $branding, string $origin): self
    {
        return self::create([
            'kind' => 'branding', 'created_by' => Auth::id(), 'origin' => $origin,
            'snapshot' => $branding->only([
                'hospital_name', 'address', 'accreditation', 'service_name', 'province', 'lgu',
                'logo_left_path', 'logo_right_path', 'logo_left_stored_object_id', 'logo_right_stored_object_id',
            ]),
        ]);
    }
}
