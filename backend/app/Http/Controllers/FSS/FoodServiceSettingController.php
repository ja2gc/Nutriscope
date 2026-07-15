<?php

namespace App\Http\Controllers\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Models\FoodServiceSetting;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FoodServiceSettingController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** Shared Food Service settings (per-head/day budget limit). */
    public function show(): JsonResponse
    {
        $setting = FoodServiceSetting::singleton();

        return response()->json(['data' => [
            'per_head_day_limit' => $setting->per_head_day_limit,
            'updated_by' => $setting->updatedBy?->display_name,
            'updated_at' => $setting->updated_at?->toDateTimeString(),
        ]]);
    }

    /** RND/Admin sets the budget per head per day. */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'per_head_day_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $setting = FoodServiceSetting::singleton();
        $oldLimit = $setting->per_head_day_limit === null ? null : (float) $setting->per_head_day_limit;
        $newLimit = array_key_exists('per_head_day_limit', $data)
            ? ($data['per_head_day_limit'] === null ? null : (float) $data['per_head_day_limit'])
            : $oldLimit;

        if ($oldLimit !== $newLimit) {
            $this->audited(function () use ($setting, $oldLimit, $newLimit): void {
                $setting->update([
                    'per_head_day_limit' => $newLimit,
                    'updated_by' => Auth::id(),
                ]);
                $this->auditLogger->record(
                    AuditAction::SettingsChanged,
                    AuditCategory::Operations,
                    AuditDomain::FoodService,
                    subject: $setting,
                    details: [
                        'changed_fields' => ['per_head_day_limit'],
                        'old_limit' => $oldLimit,
                        'new_limit' => $newLimit,
                    ],
                    actor: Auth::user(),
                );
            });
        }

        return response()->json(['data' => [
            'per_head_day_limit' => $setting->per_head_day_limit,
            'updated_by' => $setting->updatedBy?->display_name,
            'updated_at' => $setting->updated_at?->toDateTimeString(),
        ]]);
    }
}
