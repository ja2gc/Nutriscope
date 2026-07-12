<?php

namespace App\Http\Controllers\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreDietListCountRequest;
use App\Models\DietListCount;
use App\Services\Audit\AuditLogger;
use App\Services\FSS\AccomplishmentReportArchiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DietListCountController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function store(
        StoreDietListCountRequest $request,
        AccomplishmentReportArchiveService $archives
    ): JsonResponse {
        $validated = $request->validated();

        // Self-scoped write: force fss_user_id to the authenticated user; never accept from request.
        $count = $this->audited(function () use ($validated): DietListCount {
            $count = DietListCount::create([
                ...$validated,
                'fss_user_id' => Auth::id(),
            ]);
            $this->auditLogger->record(
                AuditAction::Created,
                AuditCategory::Operations,
                AuditDomain::FoodService,
                subject: $count,
                details: [
                    'changed_fields' => collect(array_keys($validated))
                        ->reject(fn (string $field): bool => in_array($field, ['ward', 'population'], true))
                        ->values()->all(),
                    'status' => 201,
                ],
            );

            return $count;
        });

        $archives->archiveCompletedWeek($request->user(), $count->service_date->toDateString());

        return response()->json(['data' => $count], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'menu_cycle_id' => ['nullable', 'integer'],
        ]);

        $counts = DietListCount::with('user:id,uuid,name')
            ->when($request->user()->isFss(), fn ($q) => $q->where('fss_user_id', Auth::id()))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->where('service_date', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->where('service_date', '<=', $d))
            ->when($data['menu_cycle_id'] ?? null, fn ($q, $id) => $q->where('menu_cycle_id', $id))
            ->orderByDesc('service_date')
            ->get();

        return response()->json(['data' => $counts]);
    }
}
