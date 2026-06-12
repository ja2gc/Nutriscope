<?php

namespace App\Http\Controllers;

use App\Models\ReportTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-report-type config (Reports → Template Edit tab): editable signatory blocks.
 * The "prepared by" name still auto-fills from the logged-in user at generate time;
 * these are the fallback + the other signatories.
 */
class ReportTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = ReportTemplate::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'type', 'name', 'description', 'signatories']);

        return response()->json(['data' => $templates]);
    }

    public function update(Request $request, ReportTemplate $reportTemplate): JsonResponse
    {
        $data = $request->validate([
            'name'                  => ['sometimes', 'string', 'max:255'],
            'signatories'           => ['sometimes', 'array'],
            'signatories.*.role'    => ['required', 'string', 'max:60'],
            'signatories.*.label'   => ['required', 'string', 'max:120'],
            'signatories.*.name'    => ['nullable', 'string', 'max:255'],
            'signatories.*.title'   => ['nullable', 'string', 'max:255'],
        ]);

        $reportTemplate->update($data);

        return response()->json(['data' => $reportTemplate->fresh()]);
    }
}
