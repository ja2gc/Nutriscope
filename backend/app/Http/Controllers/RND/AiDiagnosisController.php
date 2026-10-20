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
use App\Services\NcpAppointmentWorkflow;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AiDiagnosisController extends Controller
{
    public function __construct(
        private PesSuggestionService $pesSuggestions,
        private AuditPolicy $auditPolicy,
        private AuditLogger $auditLogger,
        private NcpAppointmentWorkflow $appointments,
    ) {}

    public function aiStatus(Request $request, NcpRecord $ncpRecord): JsonResponse
    {
        abort_unless($this->auditPolicy->viewNcpTrail($request->user(), $ncpRecord), 403);

        return response()->json($this->pesSuggestions->status($ncpRecord));
    }

    public function aiSuggest(AiSuggestDiagnosisRequest $request, NcpRecord $ncpRecord): JsonResponse
    {
        abort_unless($this->auditPolicy->viewNcpTrail($request->user(), $ncpRecord), 403);
        $data = $request->validated();
        try {
            $result = $this->pesSuggestions->suggest($ncpRecord, $data['dismissed_candidate_id'] ?? null);
        } catch (HttpResponseException $error) {
            throw $error;
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'Another request for this patient is in progress. Please try again shortly.'], 409);
        } catch (\InvalidArgumentException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        } catch (\RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 502);
        }

        return response()->json($result);
    }

    public function aiApprove(AiApproveDiagnosisRequest $request, NcpRecord $ncpRecord): JsonResponse
    {
        abort_unless($this->auditPolicy->viewNcpTrail($request->user(), $ncpRecord), 403);
        if (! $ncpRecord->assessment()->exists()) {
            return response()->json(['message' => 'Record the nutrition assessment before adding a diagnosis.'], 422);
        }
        try {
            return Cache::lock('pes-patient-'.$ncpRecord->patient_id, 40)->block(1, function () use ($request, $ncpRecord): JsonResponse {
                $data = $this->pesSuggestions->resolveDraft($ncpRecord, $request->validated('draft_id'));

                return $this->audited(function () use ($data, $request, $ncpRecord): JsonResponse {
                    $diagnosis = $this->auditLogger->withoutModelEvents(fn (): Diagnosis => Diagnosis::create(['ncp_record_id' => $ncpRecord->id, ...$data]));
                    $this->auditLogger->record(
                        AuditAction::Created, AuditCategory::Clinical, AuditDomain::Ncp,
                        subject: $diagnosis, context: $ncpRecord,
                        details: ['subject_label' => 'AI diagnosis', 'status' => 201, 'fields' => ['domain', 'label', 'etiology', 'signs_symptoms', 'extra_notes']],
                    );
                    $this->appointments->recordClinicalWork($request->user(), $ncpRecord->fresh(['diagnoses']), 'diagnosis');

                    return response()->json(['data' => new DiagnosisResource($diagnosis)], 201);
                });
            });
        } catch (\InvalidArgumentException $error) {
            throw ValidationException::withMessages(['draft_id' => $error->getMessage()]);
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'Another request for this patient is in progress. Please try again shortly.'], 409);
        }
    }
}
