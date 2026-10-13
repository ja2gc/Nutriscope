<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Http\Requests\PaginatedRequest;
use App\Models\ReportConfigurationRevision;
use App\Models\ReportTemplate;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Per-report-type config (Reports → Template Edit tab): editable signatory blocks.
 * The "prepared by" name still auto-fills from the logged-in user at generate time;
 * these are the fallback + the other signatories.
 */
class ReportTemplateController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

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
            && ! $this->sameSignatories($data['signatories'], $reportTemplate->signatories ?? [])
        ) {
            throw ValidationException::withMessages([
                'signatories' => 'Patient report signatories are filled from the responsible care-cycle RND and attending physician.',
            ]);
        }

        if (isset($data['signatories']) && $this->sameSignatories($data['signatories'], $reportTemplate->signatories ?? [])) {
            unset($data['signatories']);
        }
        $reportTemplate->fill($data);
        $changedFields = array_keys($reportTemplate->getDirty());
        if ($changedFields !== []) {
            $this->audited(function () use ($reportTemplate, $changedFields): void {
                $reportTemplate->save();
                $this->auditLogger->recordMutation(
                    AuditAction::Updated,
                    AuditDomain::System,
                    $reportTemplate,
                    $changedFields,
                    ['configuration' => 'report_template', 'report_type' => $reportTemplate->type],
                );
            });
        }

        return response()->json(['data' => array_merge($reportTemplate->fresh()->toArray(), ['id' => $reportTemplate->uuid])]);
    }

    public function history(PaginatedRequest $request, ReportTemplate $reportTemplate): JsonResponse
    {
        $history = $reportTemplate->revisions()
            ->with('editor:id,name,first_name,last_name')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($request->perPage());

        return response()->json([
            'data' => $history->getCollection()->map(function (ReportConfigurationRevision $revision): array {
                return [
                    'id' => $revision->uuid,
                    'created_at' => $revision->created_at?->toISOString(),
                    'origin' => $revision->origin,
                    'editor' => $revision->editor ? ['name' => $revision->editor->display_name] : null,
                    ...($revision->snapshot ?? []),
                ];
            })->values(),
            'meta' => [
                'current_page' => $history->currentPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
                'last_page' => $history->lastPage(),
            ],
        ]);
    }

    private function sameSignatories(array $left, array $right): bool
    {
        $normalize = static function (array $signatories): array {
            return array_map(static function (array $signatory): array {
                ksort($signatory);

                return $signatory;
            }, $signatories);
        };

        return json_encode($normalize($left), JSON_THROW_ON_ERROR)
            === json_encode($normalize($right), JSON_THROW_ON_ERROR);
    }
}
