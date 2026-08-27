<?php

namespace App\Http\Controllers\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreDietListCountRequest;
use App\Models\DietListCount;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\FSS\AccomplishmentReportArchiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DietListCountController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function store(
        StoreDietListCountRequest $request,
        AccomplishmentReportArchiveService $archives
    ): JsonResponse {
        $validated = $request->validated();
        $isExactEntry = array_key_exists('collected_ward_diet_lists', $validated)
            || array_key_exists('apportioned_distributed_meals', $validated)
            || ! array_key_exists('ward', $validated);

        // Self-scoped write: force fss_user_id to the authenticated user; never accept from request.
        [$count, $created] = $this->audited(function () use ($validated, $isExactEntry): array {
            User::query()->whereKey(Auth::id())->lockForUpdate()->firstOrFail();
            if ($isExactEntry) {
                [$count, $oldValues] = $this->saveExactEntry($validated);
            } else {
                $count = $this->saveLegacyEntry($validated);
                $oldValues = null;
            }
            $values = $this->auditValues($count);
            $fields = array_keys(array_filter($values, fn (mixed $value): bool => $value !== null));
            $this->auditLogger->record(
                $count->wasRecentlyCreated ? AuditAction::Created : AuditAction::Updated,
                AuditCategory::Operations,
                AuditDomain::FoodService,
                subject: $count,
                details: [
                    'changed_fields' => $fields,
                    'status' => 201,
                ],
                oldValues: $oldValues ?? array_fill_keys(array_keys($values), null),
                newValues: $values,
            );

            return [$count, $count->wasRecentlyCreated];
        });

        if ($isExactEntry) {
            $archives->preparePeriod($request->user(), $count->service_date->toDateString());
        }

        return response()->json(['data' => $count], $created ? 201 : 200);
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
            'collected_ward_diet_lists' => $count->collected_ward_diet_lists,
            'apportioned_distributed_meals' => $count->apportioned_distributed_meals,
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array{DietListCount, array<string, string|int|bool|null>|null}
     */
    private function saveExactEntry(array $validated): array
    {
        $legacyExists = DietListCount::query()->where('fss_user_id', Auth::id())->whereDate('service_date', $validated['service_date'])->where('ward', '!=', 'Accomplishment report')->lockForUpdate()->exists();
        if ($legacyExists) {
            throw ValidationException::withMessages(['service_date' => 'A legacy entry already exists for this date.']);
        }
        $entry = DietListCount::query()->where('fss_user_id', Auth::id())->whereDate('service_date', $validated['service_date'])->where('ward', 'Accomplishment report')->lockForUpdate()->first();
        $values = [...$validated, 'ward' => 'Accomplishment report', 'population' => $validated['apportioned_distributed_meals'] ?? 0, 'fss_user_id' => Auth::id(), 'apportioned_food' => false, 'collected_diet_list' => false];
        if ($entry === null) {
            return [DietListCount::create($values), null];
        }
        $oldValues = $this->auditValues($entry);
        $entry->fill($values)->save();

        return [$entry->fresh(), $oldValues];
    }

    /** @param array<string, mixed> $validated */
    private function saveLegacyEntry(array $validated): DietListCount
    {
        $this->ensureCompatibleDayStatus($validated);

        return DietListCount::create([...$validated, 'fss_user_id' => Auth::id()]);
    }

    /** @param array<string, mixed> $validated */
    private function ensureCompatibleDayStatus(array $validated): void
    {
        $sameDay = DietListCount::query()
            ->where('fss_user_id', Auth::id())
            ->whereDate('service_date', $validated['service_date']);

        $offDuty = (bool) ($validated['off_duty'] ?? false);
        $conflict = $offDuty
            ? (clone $sameDay)->exists()
            : (clone $sameDay)->where('off_duty', true)->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'off_duty' => $offDuty
                    ? 'Off duty cannot be recorded after work was logged for this date.'
                    : 'Work cannot be recorded after this date was marked off duty.',
            ]);
        }
    }
}
