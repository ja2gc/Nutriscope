<?php

namespace App\Http\Controllers;

use App\Models\ReportBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The editable shared report header (Reports → Template Edit tab). Single row.
 */
class ReportBrandingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => ReportBranding::singleton()]);
    }

    public function update(Request $request): JsonResponse
    {
        $branding = ReportBranding::singleton();

        $data = $request->validate([
            'hospital_name' => ['sometimes', 'string', 'max:255'],
            'address'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'accreditation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'service_name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'province'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'lgu'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'logo_left'     => ['sometimes', 'nullable', 'image', 'max:2048'],
            'logo_right'    => ['sometimes', 'nullable', 'image', 'max:2048'],
        ]);

        foreach (['logo_left', 'logo_right'] as $field) {
            if ($request->hasFile($field)) {
                $data[$field . '_path'] = $request->file($field)->store('branding', 'public');
            }
            unset($data[$field]);
        }

        $branding->update($data);

        return response()->json(['data' => $branding->fresh()]);
    }
}
