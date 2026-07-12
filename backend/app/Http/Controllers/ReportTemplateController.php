<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Models\ReportTemplate;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ->orderBy('name')
            ->get(['id', 'uuid', 'type', 'name', 'description', 'signatories']);

        return response()->json(['data' => $templates->map(fn ($t) => [
            'id' => $t->uuid, 'type' => $t->type, 'name' => $t->name,
            'description' => $t->description, 'signatories' => $t->signatories,
        ])]);
    }

    public function update(Request $request, ReportTemplate $reportTemplate): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'signatories' => ['sometimes', 'array'],
            'signatories.*.role' => ['required', 'string', 'max:60'],
            'signatories.*.label' => ['required', 'string', 'max:120'],
            'signatories.*.name' => ['nullable', 'string', 'max:255'],
            'signatories.*.title' => ['nullable', 'string', 'max:255'],
        ]);

        $changedFields = [];
        if (array_key_exists('name', $data) && $data['name'] !== $reportTemplate->name) {
            $changedFields[] = 'name';
        }
        if (array_key_exists('signatories', $data)) {
            $old = $reportTemplate->signatories ?? [];
            $count = max(count($old), count($data['signatories']));
            foreach (range(0, max(0, $count - 1)) as $index) {
                foreach (['role', 'label', 'name', 'title'] as $field) {
                    $oldValue = $old[$index][$field] ?? null;
                    $newValue = $data['signatories'][$index][$field] ?? null;
                    if ($oldValue !== $newValue) {
                        $changedFields[] = "signatories.{$index}.{$field}";
                    }
                }
            }
        }
        $safeNames = collect($data['signatories'] ?? [])
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name) && trim($name) !== '')
            ->map(fn (string $name): string => trim($name))
            ->reject(fn (string $name): bool => preg_match('/^[a-z][a-z0-9+.-]*:/iD', $name) === 1)
            ->unique()
            ->values()
            ->all();

        $this->audited(function () use ($reportTemplate, $data, $changedFields, $safeNames): void {
            $reportTemplate->update($data);
            if ($changedFields !== []) {
                $this->auditLogger->record(
                    AuditAction::Updated,
                    AuditCategory::Operations,
                    AuditDomain::Reports,
                    subject: $reportTemplate,
                    details: [
                        'changed_fields' => array_values(array_unique($changedFields)),
                        'configuration' => 'report_template',
                        'report_type' => $reportTemplate->type,
                        'template_public_id' => $reportTemplate->uuid,
                        'signatory_names' => $safeNames,
                    ],
                );
            }
        });

        return response()->json(['data' => array_merge($reportTemplate->fresh()->toArray(), ['id' => $reportTemplate->uuid])]);
    }
}
