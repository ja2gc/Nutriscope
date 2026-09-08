<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StoreNcpAppointmentRequest;
use App\Http\Requests\RND\TransitionNcpAppointmentRequest;
use App\Http\Resources\NcpAppointmentResource;
use App\Models\NcpAppointment;
use App\Models\Patient;
use App\Services\NcpAppointmentWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NcpAppointmentController extends Controller
{
    public function __construct(private readonly NcpAppointmentWorkflow $workflow) {}

    public function active(Request $request): JsonResponse
    {
        $appointment = NcpAppointment::query()
            ->whereBelongsTo($request->user(), 'rnd')
            ->where('status', 'in_progress')
            ->with(['patient', 'ncpRecord', 'rnd', 'rescheduledFrom:id,uuid'])
            ->first();

        return response()->json(['data' => $appointment ? new NcpAppointmentResource($appointment) : null]);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $appointments = NcpAppointment::query()
            ->whereBelongsTo($request->user(), 'rnd')
            ->where('status', 'scheduled')
            ->with(['patient', 'ncpRecord', 'rnd', 'rescheduledFrom:id,uuid'])
            ->orderBy('scheduled_at')
            ->paginate(min(max($request->integer('per_page', 3), 1), 20));

        return response()->json([
            'data' => NcpAppointmentResource::collection($appointments->getCollection()),
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
                'last_page' => $appointments->lastPage(),
            ],
        ]);
    }

    public function index(Request $request, Patient $patient): JsonResponse
    {
        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(['upcoming', 'past'])],
            'cycle_scope' => ['nullable', Rule::in(['current', 'past', 'unassigned'])],
            'appointment_id' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);
        $scope = $filters['scope'] ?? null;
        $cycleScope = $filters['cycle_scope'] ?? null;
        $appointments = $patient->appointments()
            ->whereBelongsTo($request->user(), 'rnd')
            ->when($scope === 'upcoming', fn ($query) => $query->whereIn('status', ['scheduled', 'in_progress']))
            ->when($scope === 'past', fn ($query) => $query->whereIn('status', ['completed', 'ended_early', 'no_show', 'cancelled', 'rescheduled']))
            ->when($cycleScope === 'current', fn ($query) => $query->whereHas('ncpRecord', fn ($ncp) => $ncp->whereIn('status', ['draft', 'active'])))
            ->when($cycleScope === 'past', fn ($query) => $query->whereHas('ncpRecord', fn ($ncp) => $ncp->whereIn('status', ['completed', 'discontinued', 'discharged'])))
            ->when($cycleScope === 'unassigned', fn ($query) => $query->whereNull('ncp_record_id'))
            ->when($filters['appointment_id'] ?? null, fn ($query, $uuid) => $query->where('uuid', $uuid))
            ->with(['patient', 'ncpRecord', 'rnd', 'rescheduledFrom:id,uuid'])
            ->when($scope === 'upcoming', fn ($query) => $query->orderBy('scheduled_at'), fn ($query) => $query->latest('scheduled_at'))
            ->paginate(min(max($request->integer('per_page', 5), 1), 20));

        return response()->json([
            'data' => NcpAppointmentResource::collection($appointments->getCollection()),
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
                'last_page' => $appointments->lastPage(),
            ],
        ]);
    }

    public function store(StoreNcpAppointmentRequest $request, Patient $patient): JsonResponse
    {
        $appointment = $this->workflow->create($request->user(), $patient, $request->validated());

        return response()->json([
            'data' => new NcpAppointmentResource($appointment->load(['patient', 'ncpRecord', 'rnd', 'rescheduledFrom:id,uuid'])),
        ], 201);
    }

    public function transition(TransitionNcpAppointmentRequest $request, NcpAppointment $ncpAppointment): JsonResponse
    {
        abort_unless($ncpAppointment->rnd_user_id === $request->user()->id, 404);
        $result = $this->workflow->transition($request->user(), $ncpAppointment, $request->validated());
        if ($result === null) {
            return response()->json(null, 204);
        }
        if (is_array($result)) {
            return response()->json(['data' => [
                'original' => new NcpAppointmentResource($result['original']->load(['patient', 'ncpRecord', 'rnd', 'rescheduledFrom:id,uuid'])),
                'replacement' => new NcpAppointmentResource($result['replacement']->load(['patient', 'ncpRecord', 'rnd', 'rescheduledFrom:id,uuid'])),
            ]]);
        }

        return response()->json([
            'data' => new NcpAppointmentResource($result->load(['patient', 'ncpRecord', 'rnd', 'rescheduledFrom:id,uuid'])),
        ]);
    }
}
