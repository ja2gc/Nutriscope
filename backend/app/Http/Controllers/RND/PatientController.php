<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StorePatientRequest;
use App\Http\Requests\RND\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\NcpRecord;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PatientController extends Controller
{
    /**
     * GET /api/rnd/patients
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $patients = Patient::query()
            ->when($request->search, fn($q, $s) =>
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('physician', 'like', "%{$s}%")
                  ->orWhere('ward', 'like', "%{$s}%")
            )
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->with(['ncpRecords' => fn($q) => $q->latest()->with(['assessment', 'intervention'])])
            ->orderByDesc('created_at')
            ->paginate((int) min($request->query('per_page', 15), 100));

        return PatientResource::collection($patients);
    }

    /**
     * POST /api/rnd/patients
     */
    public function store(StorePatientRequest $request): JsonResponse
    {
        $patient = Patient::create($request->validated());
        return response()->json(new PatientResource($patient), 201);
    }

    /**
     * GET /api/rnd/patients/{id}
     */
    public function show(Patient $patient): JsonResponse
    {
        $patient->load([
            'ncpRecords' => fn($q) => $q->latest()->with(['assessment', 'diagnoses', 'intervention']),
        ]);
        return response()->json(new PatientResource($patient));
    }

    /**
     * PATCH /api/rnd/patients/{id}
     */
    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        $patient->update($request->validated());
        $patient->load([
            'ncpRecords' => fn($q) => $q->latest()->with(['assessment', 'intervention']),
        ]);
        return response()->json(new PatientResource($patient));
    }

    /**
     * GET /api/rnd/patients/{id}/ncp-records
     */
    public function ncpRecords(Patient $patient): JsonResponse
    {
        $records = $patient->ncpRecords()
            ->with(['rnd:id,name', 'assessment', 'diagnoses', 'intervention'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $records]);
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

        $patient->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/rnd/patients/{patient}/ncp-records
     */
    public function startNcpCycle(Request $request, Patient $patient): JsonResponse
    {
        $record = NcpRecord::create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $request->user()->id,
            'type' => 'new',
            'status' => 'draft',
        ]);

        return response()->json(['data' => $record], 201);
    }
}
