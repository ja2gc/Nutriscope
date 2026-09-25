<?php

namespace App\Http\Controllers\RND;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\RND\AiApproveDiagnosisRequest;
use App\Http\Requests\RND\AiSuggestDiagnosisRequest;
use App\Http\Resources\DiagnosisResource;
use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Policies\AuditPolicy;
use App\Services\Audit\AuditLogger;
use App\Services\Diagnosis\PesSuggestionService;
use Illuminate\Http\JsonResponse;

class AiDiagnosisController extends Controller
{
    public function __construct(
        private PesSuggestionService $pesSuggestions,
        private AuditPolicy $auditPolicy,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * GET AI-suggested diagnoses for an NCP record.
     */
    public function aiSuggest(AiSuggestDiagnosisRequest $request, NcpRecord $ncpRecord): JsonResponse
    {
        abort_unless($this->auditPolicy->viewNcpTrail($request->user(), $ncpRecord), 403);
        $data = $request->validated();

        try {
            $result = $this->pesSuggestions->suggest(
                $ncpRecord,
                $data['dismissed_candidate_id'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $this->auditLogger->record(
            AuditAction::Generated,
            AuditCategory::Clinical,
            AuditDomain::Ncp,
            subject: $ncpRecord,
            details: [
                'status' => 200,
                'cached' => $result['meta']['cached'],
                'draft_count' => count($result['data']),
            ],
        );

        return response()->json($result);
    }

    /**
     * Store an AI-approved diagnosis to the database.
     */
    public function aiApprove(AiApproveDiagnosisRequest $request, NcpRecord $ncpRecord): JsonResponse
    {
        abort_unless($this->auditPolicy->viewNcpTrail($request->user(), $ncpRecord), 403);
        // ADIME step order: the assessment must precede the diagnosis (same gate as the
        // manual create path, so AI-approve can't bypass it).
        if (! $ncpRecord->assessment()->exists()) {
            return response()->json([
                'message' => 'Record the nutrition assessment before adding a diagnosis.',
            ], 422);
        }

        $data = $request->validated();
        $problem = $this->cleanPesComponent($data['label']);
        $etiology = $this->cleanPesComponent($data['etiology'], 'related to');
        $signs = $this->cleanPesComponent($data['signs'], 'as evidenced by');

        return $this->audited(function () use ($ncpRecord, $data, $problem, $etiology, $signs) {
            $diagnosis = $this->auditLogger->withoutModelEvents(fn (): Diagnosis => Diagnosis::create([
                'ncp_record_id' => $ncpRecord->id,
                'domain' => $data['domain'],
                'problem' => $problem,
                'label' => $problem,
                'etiology' => $etiology,
                'signs_symptoms' => $signs,
                'pes_statement' => Diagnosis::buildPes($problem, $etiology, $signs),
                'ai_generated' => true,
            ]));
            $this->auditLogger->record(
                AuditAction::Approved,
                AuditCategory::Clinical,
                AuditDomain::Ncp,
                subject: $diagnosis,
                context: $ncpRecord,
                details: ['status' => 201, 'fields' => ['domain', 'label', 'etiology', 'signs_symptoms']],
            );

            return response()->json(['data' => new DiagnosisResource($diagnosis)], 201);
        });
    }

    private function cleanPesComponent(string $value, ?string $prefix = null): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        if ($prefix === null) {
            return $value;
        }

        return trim((string) preg_replace('/^'.preg_quote($prefix, '/').'\s+/i', '', $value));
    }
}
