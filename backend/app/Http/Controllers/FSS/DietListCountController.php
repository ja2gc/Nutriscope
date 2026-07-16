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
            $values = $this->auditValues($count);
            $fields = array_keys(array_filter($values, fn (mixed $value): bool => $value !== null));
            $this->auditLogger->record(
                AuditAction::Created,
                AuditCategory::Operations,
                AuditDomain::FoodService,
                subject: $count,
                details: [
                    'changed_fields' => $fields,
                    'status' => 201,
                ],
                oldValues: array_fill_keys(array_keys($values), null),
                newValues: $values,
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

        $counts = DietListCount::with('user:id,uuid,name,first_name,last_name')
            ->when($request->user()->isFss(), fn ($q) => $q->where('fss_user_id', Auth::id()))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->where('service_date', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->where('service_date', '<=', $d))
            ->when($data['menu_cycle_id'] ?? null, fn ($q, $id) => $q->where('menu_cycle_id', $id))
            ->orderByDesc('service_date')
            ->get();

        return response()->json(['data' => $counts]);
    }

    /** @return array<string, string|int|bool|null> */
    private function auditValues(DietListCount $count): array
    {
        return [
            'service_date' => $count->service_date->toDateString(),
            'population' => $count->population,
            'helped_food_prep' => $count->helped_food_prep,
            'stored_supplies' => $count->stored_supplies,
            'collected_diet_list' => $count->collected_diet_list,
            'apportioned_food' => $count->apportioned_food,
            'cleaned_utensils' => $count->cleaned_utensils,
            'assistant_cook' => $count->assistant_cook,
            'maintained_cleanliness' => $count->maintained_cleanliness,
            'off_duty' => $count->off_duty,
        ];
    }
}
