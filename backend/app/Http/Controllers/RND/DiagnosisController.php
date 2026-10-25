<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Requests\RND\StoreDiagnosisRequest;
use App\Http\Requests\RND\UpdateDiagnosisRequest;
use App\Http\Resources\DiagnosisResource;
use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Policies\AuditPolicy;
use App\Services\Diagnosis\PesSuggestionService;
use App\Services\NcpAppointmentWorkflow;
use App\Support\DiagnosisBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class DiagnosisController extends Controller
{
    public function __construct(
        private readonly AuditPolicy $auditPolicy,
        private readonly NcpAppointmentWorkflow $appointments,
    ) {}

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/diagnoses
     */
    public function index(PaginatedRequest $request, NcpRecord $ncpRecord): AnonymousResourceCollection
    {
        $this->authorizeNcp($ncpRecord);
        $diagnoses = $ncpRecord->diagnoses()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return DiagnosisResource::collection($diagnoses);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/diagnoses
     */
    public function store(StoreDiagnosisRequest $request, NcpRecord $ncpRecord)
    {
        $this->authorizeNcp($ncpRecord);
        // ADIME step order: the assessment must precede the diagnosis.
        if (! $ncpRecord->assessment()->exists()) {
            return response()->json([
                'message' => 'Record the nutrition assessment before adding a diagnosis.',
            ], 422);
        }

        $data = $this->prepareBuilder($ncpRecord, $request->validated());

        return $this->audited(function () use ($data, $ncpRecord, $request) {
            $diagnosis = new Diagnosis($data);
            $diagnosis->ncp_record_id = $ncpRecord->id;
            $diagnosis->pes_statement = $this->resolvePes($data, $diagnosis);
            $diagnosis->save();
            $freshNcp = $ncpRecord->fresh(['diagnoses']);
            $this->appointments->recordClinicalWork($request->user(), $freshNcp, 'diagnosis');

            return (new DiagnosisResource($diagnosis))->response()->setStatusCode(201);
        });
    }

    /**
     * PATCH /api/rnd/ncp-records/{ncpRecord}/diagnoses/{diagnosis}
     */
    public function update(UpdateDiagnosisRequest $request, NcpRecord $ncpRecord, Diagnosis $diagnosis): DiagnosisResource
    {
        $this->authorizeNcp($ncpRecord);
        // Scope check
        if ($diagnosis->ncp_record_id !== $ncpRecord->id) {
            abort(404);
        }

        $data = $this->prepareBuilder($ncpRecord, $request->validated(), $diagnosis);

        return $this->audited(function () use ($diagnosis, $data, $ncpRecord, $request) {
            $diagnosis->fill($data);
            if (array_key_exists('problem', $data)) {
                $diagnosis->label = $data['problem'];
            }
            $diagnosis->pes_statement = $this->resolvePes($data, $diagnosis);
            $diagnosis->save();
            $freshNcp = $ncpRecord->fresh(['diagnoses']);
            $this->appointments->recordClinicalWork($request->user(), $freshNcp, 'diagnosis');

            return new DiagnosisResource($diagnosis);
        });
    }

    private function prepareBuilder(NcpRecord $record, array $data, ?Diagnosis $existing = null): array
    {
        if (! empty($data['ai_draft_id'])) {
            try {
                $issued = app(PesSuggestionService::class)->resolveDraft($record, $data['ai_draft_id']);
                $data['ai_provenance'] = $issued['ai_provenance'];
                $data['ai_generated'] = true;
            } catch (\InvalidArgumentException $error) {
                throw ValidationException::withMessages(['ai_draft_id' => $error->getMessage()]);
            }
        } else {
            unset($data['ai_draft_id']);
            $data['ai_generated'] = $existing?->ai_generated ?? false;
        }
        if (is_array($data['builder_data'] ?? null)) {
            $domain = $data['domain'] ?? $existing?->domain;
            if ($domain === 'NI' && (empty($data['builder_data']['ni_direction']) || trim((string) ($data['builder_data']['ni_nutrient'] ?? '')) === '')) {
                throw ValidationException::withMessages(['builder_data.ni_nutrient' => 'Choose an intake direction and specify the nutrient or substance.']);
            }
            $options = DiagnosisBuilder::options()[$domain];
            foreach (['problem_checks' => 'problems', 'etiology_checks' => 'etiologies', 'sign_checks' => 'signs'] as $key => $list) {
                $legacy = $key === 'sign_checks' && $existing?->domain === $domain
                    ? array_intersect($existing->builder_data['sign_checks'] ?? [], ['BMI ≥ 25 (overweight / obesity)', 'BMI > 25 (overweight) or > 30 (obese)']) : [];
                if (array_diff($data['builder_data'][$key] ?? [], [...$options[$list], ...$legacy]) !== []) {
                    throw ValidationException::withMessages(["builder_data.{$key}" => 'Choose a listed option or use Other.']);
                }
            }
            $components = DiagnosisBuilder::components($domain, $data['builder_data']);
            foreach ($components as $key => $value) {
                if (trim($value) === '' || mb_strlen($value) > ($key === 'problem' ? 255 : 400)) {
                    throw ValidationException::withMessages([$key => 'Complete this component within its character limit.']);
                }
            }
            $data = array_merge($data, $components);
        }

        return $data;
    }

    /**
     * Preserve an explicit manual PES override; otherwise compose the selected components.
     */
    private function resolvePes(array $data, Diagnosis $diagnosis): string
    {
        $override = trim((string) ($data['pes_statement'] ?? ''));
        if ($override !== '') {
            return $override;
        }

        return Diagnosis::buildPes(
            $diagnosis->problem,
            $diagnosis->etiology,
            $diagnosis->signs_symptoms
        );
    }

    /**
     * DELETE /api/rnd/ncp-records/{ncpRecord}/diagnoses/{diagnosis}
     */
    public function destroy(NcpRecord $ncpRecord, Diagnosis $diagnosis)
    {
        $this->authorizeNcp($ncpRecord);
        // Scope check
        if ($diagnosis->ncp_record_id !== $ncpRecord->id) {
            abort(404);
        }

        // DP-04 / DI-02: an active or completed care plan must always retain at
        // least one PES — its intervention/monitoring would otherwise be orphaned.
        $isLast = $ncpRecord->diagnoses()->count() <= 1;
        $locked = in_array($ncpRecord->status, ['active', 'completed'], true);
        if ($isLast && $locked) {
            return response()->json([
                'message' => 'Cannot remove the last diagnosis from an active care plan. Add a replacement diagnosis first, or reopen the workflow.',
            ], 422);
        }

        $this->audited(function () use ($ncpRecord, $diagnosis): void {
            app(PesSuggestionService::class)->reopen(
                $ncpRecord, $diagnosis->ai_provenance['candidate_id'] ?? null,
                $diagnosis->problem, $diagnosis->ai_provenance['draft_id'] ?? null,
            );
            $diagnosis->delete();
        });

        return response()->noContent();
    }

    private function authorizeNcp(NcpRecord $ncpRecord): void
    {
        abort_unless($this->auditPolicy->viewNcpTrail(request()->user(), $ncpRecord), 403);
    }
}
