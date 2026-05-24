<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StorePatientRequest;
use App\Http\Requests\RND\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
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
            ->with(['ncpRecords' => fn($q) => $q->latest()->limit(1)])
            ->orderByDesc('created_at')
            ->paginate(20);

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
        $patient->load(['ncpRecords.assessment', 'ncpRecords.diagnoses', 'ncpRecords.intervention']);
        return response()->json(new PatientResource($patient));
    }

    /**
     * PATCH /api/rnd/patients/{id}
     */
    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        $patient->update($request->validated());
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
}
