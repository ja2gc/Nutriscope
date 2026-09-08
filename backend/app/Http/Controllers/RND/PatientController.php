<?php

namespace App\Http\Controllers\RND;

use App\Actions\Identity\SynchronizePersonName;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Requests\RND\StorePatientRequest;
use App\Http\Requests\RND\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Jobs\DeleteQuarantinedClinicalFile;
use App\Jobs\RestoreQuarantinedClinicalFile;
use App\Models\NcpAppointment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\ScreeningDocument;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\ClinicalAttributionService;
use App\Services\ClinicalCompletenessService;
use App\Services\ClinicalDocumentStorage;
use App\Support\Search\RankedSearch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class PatientController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ClinicalAttributionService $clinicalAttribution,
        private readonly ClinicalDocumentStorage $documentStorage,
        private readonly ClinicalCompletenessService $completeness,
        private readonly SynchronizePersonName $synchronizePersonName,
    ) {}

    /**
     * GET /api/rnd/patients
     */
    public function index(PaginatedRequest $request): AnonymousResourceCollection
    {
        $query = Patient::query()
            ->addSelect([
                'next_appointment_at' => NcpAppointment::query()
                    ->select('scheduled_at')
                    ->whereColumn('patient_id', 'patients.id')
                    ->where('rnd_user_id', $request->user()->id)
                    ->where('status', 'scheduled')
                    ->orderBy('scheduled_at')
                    ->limit(1),
            ])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('upcoming_followups'), fn ($q) => $q->whereHas(
                'appointments',
                fn ($appointments) => $appointments
                    ->where('rnd_user_id', $request->user()->id)
                    ->where('status', 'scheduled'),
            ));

        RankedSearch::apply($query, $request->string('search')->toString(), [
            'name', 'first_name', 'last_name', 'physician', 'ward', 'hospital_number',
        ]);

        $patients = $query
            ->with(['ncpRecords' => fn ($q) => $q->latest()->with(['rnd:id,uuid,name,first_name,last_name,role', 'assessment', 'intervention'])])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();
        $this->clinicalAttribution->decoratePatients($patients->getCollection());

        return PatientResource::collection($patients);
    }

    /**
     * POST /api/rnd/patients
     */
    public function store(StorePatientRequest $request): JsonResponse
    {
        return $this->audited(function () use ($request): JsonResponse {
            $patient = Patient::create($this->synchronizePersonName->forCreate($request->validated()));

            return response()->json(new PatientResource($patient), 201);
        });
    }

    /**
     * GET /api/rnd/patients/{id}
     */
    public function show(Request $request, Patient $patient): JsonResponse
    {
        $patient->load([
            'ncpRecords' => fn ($q) => $q->latest()->with(['rnd:id,uuid,name,first_name,last_name,role', 'assessment', 'diagnoses', 'intervention', 'monitorings']),
        ]);
        $patient->setAttribute('next_appointment_at', $patient->appointments()
            ->where('rnd_user_id', $request->user()->id)
            ->where('status', 'scheduled')
            ->orderBy('scheduled_at')
            ->value('scheduled_at'));
        $patient->setAttribute('can_delete', $patient->ncpRecords->every(
            fn (NcpRecord $record): bool => ! $this->completeness->initialAdiComplete($record),
        ));
        $this->clinicalAttribution->decoratePatients(new Collection([$patient]));
        $key = "patient-chart-view:{$request->user()->id}:{$patient->id}";
        if (Cache::add($key, true, (int) config('audit.deduplication.chart_view_seconds', 900))) {
            try {
                $this->auditLogger->record(
                    AuditAction::Viewed,
                    AuditCategory::Clinical,
                    AuditDomain::Patients,
                    subject: $patient,
                    details: ['status' => 200],
                );
            } catch (\Throwable $exception) {
                Cache::forget($key);
                throw $exception;
            }
        }

        return response()->json(new PatientResource($patient));
    }

    /**
     * PATCH /api/rnd/patients/{id}
     */
    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        return $this->audited(function () use ($request, $patient): JsonResponse {
            $patient->update($this->synchronizePersonName->forUpdate($patient, $request->validated()));
            $patient->load([
                'ncpRecords' => fn ($q) => $q->latest()->with(['rnd:id,uuid,name,first_name,last_name,role', 'assessment', 'intervention']),
            ]);
            $this->clinicalAttribution->decoratePatients(new Collection([$patient]));

            return response()->json(new PatientResource($patient));
        });
    }

    /**
     * GET /api/rnd/patients/{id}/ncp-records
     */
    public function ncpRecords(PaginatedRequest $request, Patient $patient): JsonResponse
    {
        $scope = $request->string('scope')->toString();
        $records = $patient->ncpRecords()
            ->when($request->string('ncp_record_id')->toString(), fn ($query, $id) => $query->where('uuid', $id))
            ->when($scope === 'current', fn ($query) => $query->whereIn('status', ['draft', 'active']))
            ->when($scope === 'past', fn ($query) => $query->whereIn('status', ['completed', 'discontinued', 'discharged']))
            ->with(['rnd:id,uuid,name,first_name,last_name,role', 'assessment', 'diagnoses', 'monitorings', 'intervention.mealPlans:id,uuid,intervention_id,week_start_date,generation_type'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($scope === 'past' ? 2 : $request->perPage())
            ->withQueryString();
        $this->clinicalAttribution->decorateNcpRecords($records->getCollection());

        $records->through(fn (NcpRecord $record): array => [
            'id' => $record->uuid,
            'patient_id' => $patient->uuid,
            'type' => $record->type,
            'status' => $record->status,
            'discontinuation_reason_code' => $record->discontinuation_reason_code,
            'created_at' => $record->created_at?->toIso8601String(),
            'updated_at' => $record->updated_at?->toIso8601String(),
            'created_by' => $record->getAttribute('created_by'),
            'last_clinical_action' => $record->getAttribute('last_clinical_action'),
            'can_delete' => in_array($record->status, ['draft', 'active'], true)
                && ! $this->completeness->initialAdiComplete($record),
            'assessment' => $record->assessment === null ? null : [
                'rnd_summary' => $record->assessment->rnd_summary,
                'allergies' => $record->assessment->allergies,
            ],
            'diagnoses' => $record->diagnoses->map(fn ($diagnosis): array => [
                'pes_statement' => $diagnosis->pes_statement,
            ])->values()->all(),
            'monitorings' => $record->monitorings->map(fn ($monitoring): array => [
                'id' => $monitoring->uuid,
                'clinical_summary' => $monitoring->clinical_summary,
                'created_at' => $monitoring->created_at?->toIso8601String(),
            ])->values()->all(),
            'intervention' => $record->intervention === null ? null : [
                'goal_type' => $record->intervention->goal_type,
                'meal_plans' => $record->intervention->mealPlans->map(fn ($mealPlan): array => [
                    'id' => $mealPlan->uuid,
                    'week_start_date' => $mealPlan->week_start_date?->toDateString(),
                    'generation_type' => $mealPlan->generation_type,
                ])->values()->all(),
            ],
        ]);

        return response()->json([
            'data' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    /**
     * DELETE /api/rnd/patients/{patient}
     * Blocked when any NCP record has gone through Assessment → Diagnosis → Intervention.
     */
    public function destroy(Patient $patient): JsonResponse
    {
        $hasOfficialCycle = $patient->ncpRecords()
            ->with(['assessment', 'diagnoses', 'intervention'])
            ->get()
            ->contains(fn (NcpRecord $record): bool => $this->completeness->initialAdiComplete($record));

        if ($hasOfficialCycle) {
            return response()->json([
                'message' => 'This patient has clinical records with completed assessment, diagnosis, and intervention and cannot be deleted.',
            ], 422);
        }

        // screening_documents.patient_id has no DB cascade — purge the rows (and their stored
        // files) first, otherwise the patient delete hits an unhandled FK constraint violation.
        $this->auditLogger->assertAvailable();
        $documents = ScreeningDocument::where('patient_id', $patient->id)->get();
        $mealPlans = $patient->mealPlans()->get();
        $moves = [];

        try {
            foreach ($documents as $document) {
                $move = $this->documentStorage->quarantineIfPresent($document->file_path);
                if ($move !== null) {
                    $moves[] = $move;
                }
            }

            $this->audited(function () use ($patient, $documents, $mealPlans, $moves): void {
                foreach ($documents as $document) {
                    $this->auditLogger->withoutModelEvents(fn () => $document->delete());
                    $this->auditLogger->record(
                        AuditAction::Deleted,
                        AuditCategory::Clinical,
                        AuditDomain::Patients,
                        subject: $document,
                        context: $patient,
                        details: ['status' => 204],
                    );
                }
                // meal_plans.patient_id does not cascade, so remove plans before
                // the patient after the clinical-completeness guard has approved deletion.
                foreach ($mealPlans as $mealPlan) {
                    $mealPlan->delete();
                }
                $patient->delete();
                foreach ($moves as $move) {
                    DeleteQuarantinedClinicalFile::dispatch($move['quarantine'])->afterCommit();
                }
            });
        } catch (\Throwable $exception) {
            $compensationFailures = [];
            foreach (array_reverse($moves) as $move) {
                try {
                    $this->documentStorage->restore($move);
                } catch (\Throwable $restoreException) {
                    $compensationFailures[] = $restoreException;
                    try {
                        RestoreQuarantinedClinicalFile::dispatch($move);
                    } catch (\Throwable $dispatchException) {
                        $compensationFailures[] = $dispatchException;
                    }
                }
            }
            if ($compensationFailures !== []) {
                report(new \RuntimeException(
                    sprintf('Clinical file compensation encountered %d failure(s).', count($compensationFailures)),
                    previous: $compensationFailures[0],
                ));
            }
            throw $exception;
        }

        return response()->json(null, 204);
    }

    /**
     * POST /api/rnd/patients/{patient}/ncp-records
     */
    public function startNcpCycle(Request $request, Patient $patient): JsonResponse
    {
        // SL-04: a discharged or transferred patient has no active episode of care,
        // so a new NCP cycle must not be started for them.
        if (in_array($patient->status, ['Discharged', 'Transferred'], true)) {
            return response()->json([
                'message' => "Cannot start a new NCP cycle for a {$patient->status} patient.",
            ], 422);
        }

        // SL-03: only one open cycle per patient. A draft/active cycle must be
        // completed (or removed) before another is started, so reports and patient
        // selection stay coherent.
        if ($patient->ncpRecords()->whereIn('status', ['draft', 'active'])->exists()) {
            return response()->json([
                'message' => 'This patient already has an open NCP cycle. Complete it before starting a new one.',
            ], 409);
        }

        $record = $this->audited(fn () => NcpRecord::create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $request->user()->id,
            'type' => 'new',
            'status' => 'draft',
        ]));

        return response()->json(['data' => array_merge(
            $record->toArray(),
            ['id' => $record->uuid, 'patient_id' => $patient->uuid],
        )], 201);
    }
}
