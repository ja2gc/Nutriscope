<?php

namespace App\Http\Controllers;

use App\Models\ReportTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            ->where('type', '!=', 'demographic_census')
            ->orderBy('name')
            ->get(['id', 'uuid', 'type', 'name', 'description', 'signatories']);

        return response()->json(['data' => $templates->map(fn ($t) => [
            'id' => $t->uuid, 'type' => $t->type, 'name' => $t->name,
            'description' => $t->description, 'signatories' => $t->signatories,
        ])]);
    }

    public function update(Request $request, ReportTemplate $reportTemplate): JsonResponse
    {
        abort_if($reportTemplate->type === 'demographic_census', 410, 'Demographic Census no longer uses a PDF template.');
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'signatories' => ['sometimes', 'array'],
            'signatories.*.role' => ['required', 'string', 'max:60'],
            'signatories.*.label' => ['required', 'string', 'max:120'],
            'signatories.*.name' => ['nullable', 'string', 'max:255'],
            'signatories.*.title' => ['nullable', 'string', 'max:255'],
        ]);

        if (
            in_array($reportTemplate->type, ['patient_menu_plan', 'ncp_summary'], true)
            && array_key_exists('signatories', $data)
            && $data['signatories'] !== ($reportTemplate->signatories ?? [])
        ) {
            throw ValidationException::withMessages([
                'signatories' => 'Patient report signatories are filled from the responsible care-cycle RND and attending physician.',
            ]);
        }

        DB::transaction(function () use ($reportTemplate, $data): void {
            $reportTemplate->update($data);
        });

        return response()->json(['data' => array_merge($reportTemplate->fresh()->toArray(), ['id' => $reportTemplate->uuid])]);
    }
}
