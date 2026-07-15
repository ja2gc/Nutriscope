<?php

namespace App\Http\Controllers\RND;

use App\Actions\Identity\SynchronizePersonName;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StorePatientRequest;
use App\Http\Requests\RND\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Jobs\DeleteQuarantinedClinicalFile;
use App\Jobs\RestoreQuarantinedClinicalFile;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\ScreeningDocument;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\ClinicalAttributionService;
use App\Services\ClinicalDocumentStorage;
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
        private readonly SynchronizePersonName $synchronizePersonName,
    ) {}

    /**
     * GET /api/rnd/patients
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $patients = Patient::query()
            ->when($request->search, fn ($q, $s) => $q->where(fn ($search) => $search
                ->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name', 'like', "%{$s}%")
                ->orWhere('name', 'like', "%{$s}%")
                ->orWhere('physician', 'like', "%{$s}%")
                ->orWhere('ward', 'like', "%{$s}%")
                ->orWhere('hospital_number', 'like', "%{$s}%")
            ))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->with(['ncpRecords' => fn ($q) => $q->latest()->with(['rnd:id,uuid,name,first_name,last_name,role', 'assessment', 'intervention'])])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) min($request->query('per_page', 15), 100));
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
            'ncpRecords' => fn ($q) => $q->latest()->with(['rnd:id,uuid,name,first_name,last_name,role', 'assessment', 'diagnoses', 'intervention']),
        ]);
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
    public function ncpRecords(Patient $patient): JsonResponse
    {
        $records = $patient->ncpRecords()
            ->with(['rnd:id,uuid,name,first_name,last_name,role', 'assessment', 'diagnoses', 'intervention.mealPlans:id,uuid,intervention_id,week_start_date,generation_type'])
            ->orderByDesc('created_at')
            ->get();
        $this->clinicalAttribution->decorateNcpRecords($records);

        // Overlay the public uuid on the record's own identity (used to build
        // /ncp/{ncpId}/... nav links) without disturbing the rest of the payload shape.
        // patient_id must be overlaid too — toArray() still emits it as the raw internal
        // FK, and callers build /ncp/{patientId}/... nav links from it; the raw int then
        // 404s against the uuid-bound patients route (see NcpPatientHeader stuck loading).
        $data = $records->map(fn (NcpRecord $record) => array_merge(
            $record->toArray(),
            ['id' => $record->uuid, 'patient_id' => $patient->uuid],
        ));

        return response()->json(['data' => $data]);
    }

    /**
     * DELETE /api/rnd/patients/{patient}
     * Blocked when any NCP record has gone through Assessment → Diagnosis → Intervention.
     */
    public function destroy(Patient $patient): JsonResponse
    {
        $hasOfficialCycle = $patient->ncpRecords()
            ->whereHas('assessment')
            ->whereHas('diagnoses')
            ->whereHas('intervention')
            ->exists();

        if ($hasOfficialCycle) {
            return response()->json([
                'message' => 'This patient has clinical records with completed assessment, diagnosis, and intervention and cannot be deleted.',
            ], 422);
        }

        // screening_documents.patient_id has no DB cascade — purge the rows (and their stored
        // files) first, otherwise the patient delete hits an unhandled FK constraint violation.
        $this->auditLogger->assertAvailable();
        $documents = ScreeningDocument::where('patient_id', $patient->id)->get();
        $moves = [];

        try {
            foreach ($documents as $document) {
                $move = $this->documentStorage->quarantineIfPresent($document->file_path);
                if ($move !== null) {
                    $moves[] = $move;
                }
            }

            $this->audited(function () use ($patient, $documents, $moves): void {
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
