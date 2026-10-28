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

        return $this->audited(function () use ($ncpRecord, $request) {
            $data = $this->prepareBuilder($ncpRecord, $request->validated());
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

        return $this->audited(function () use ($diagnosis, $ncpRecord, $request) {
            $data = $this->prepareBuilder($ncpRecord, $request->validated(), $diagnosis);
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
        NcpRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
        $domain = $data['domain'] ?? $existing?->domain;
        $others = $record->diagnoses()->when($existing, fn ($query) => $query->whereKeyNot($existing->id));
        if ((clone $others)->where('domain', 'NO')->exists() || ($domain !== 'NO' && $existing?->isNoDiagnosis())) {
            throw ValidationException::withMessages(['domain' => Diagnosis::NO_DIAGNOSIS_WARNING]);
        }
        if ($domain === 'NO' && $others->exists()) {
            throw ValidationException::withMessages(['domain' => 'No nutrition diagnosis at this time must be the only saved entry. Remove existing diagnoses before recording this decision.']);
        }
        if ($domain === 'NO') {
            if (($data['problem'] ?? $existing?->problem) !== Diagnosis::NO_DIAGNOSIS) {
                throw ValidationException::withMessages(['problem' => 'Choose No nutrition diagnosis at this time.']);
            }
            app(PesSuggestionService::class)->reopen($record, null, Diagnosis::NO_DIAGNOSIS, domain: 'NO');

            return array_merge($data, ['problem' => Diagnosis::NO_DIAGNOSIS, 'label' => Diagnosis::NO_DIAGNOSIS,
                'etiology' => '', 'signs_symptoms' => '', 'pes_statement' => Diagnosis::NO_DIAGNOSIS,
                'builder_data' => null, 'ai_generated' => false, 'ai_draft_id' => null, 'ai_provenance' => null]);
        }
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
            if ($domain === 'NI' && trim((string) ($data['builder_data']['problem_override'] ?? '')) === '' && (empty($data['builder_data']['ni_direction']) || trim((string) ($data['builder_data']['ni_nutrient'] ?? '')) === '')) {
                throw ValidationException::withMessages(['builder_data.ni_nutrient' => 'Choose an intake direction and specify the nutrient or substance.']);
            }
            $options = DiagnosisBuilder::options()[$domain];
            foreach (['problem_checks' => 'problems', 'etiology_checks' => 'etiologies', 'sign_checks' => 'signs'] as $key => $list) {
                $legacy = $existing?->domain === $domain ? ($existing->builder_data[$key] ?? []) : [];
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
        if (mb_strtolower(trim((string) ($data['problem'] ?? ''))) === mb_strtolower(Diagnosis::NO_DIAGNOSIS)) {
            throw ValidationException::withMessages(['domain' => 'Choose Other (NO) to record a no-diagnosis decision.']);
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
        if ($isLast && $locked && ! ($diagnosis->isNoDiagnosis() && $ncpRecord->status === 'active')) {
            return response()->json([
                'message' => 'Cannot remove the last diagnosis from an active care plan. Add a replacement diagnosis first, or reopen the workflow.',
            ], 422);
        }

        $this->audited(function () use ($ncpRecord, $diagnosis): void {
            app(PesSuggestionService::class)->reopen(
                $ncpRecord, $diagnosis->ai_provenance['candidate_id'] ?? null,
                $diagnosis->problem, $diagnosis->ai_provenance['draft_id'] ?? null,
                $diagnosis->domain,
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
