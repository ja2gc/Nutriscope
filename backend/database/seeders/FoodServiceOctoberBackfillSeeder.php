<?php

namespace Database\Seeders;

use App\Http\Controllers\FSS\PurchaseOrderController;
use App\Models\DietListCount;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\PurchaseOrderVendorGroup;
use App\Models\ShoppingList;
use App\Models\StoredObject;
use App\Models\User;
use App\Services\FSS\AccomplishmentReportArchiveService;
use App\Services\FSS\ConsumptionService;
use App\Services\FSS\PurchaseOrderLifecycleService;
use App\Services\FSS\ShoppingListPopulationService;
use App\Services\MenuCycleCostService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** One-off, additive fictional backfill for the October 15, 2026 exhibition recording. */
class FoodServiceOctoberBackfillSeeder extends Seeder
{
    private const FIRST_DAY = '2026-09-15';

    private const LAST_LOGGED_DAY = '2026-10-14';

    private const RECEIPT_NAME = 'nutriscope-seeded-pexels-receipt-14647295.jpg';

    private const POPULATION = [
        'Monday' => 175, 'Tuesday' => 168, 'Wednesday' => 182, 'Thursday' => 160,
        'Friday' => 190, 'Saturday' => 155, 'Sunday' => 172,
    ];

    public function run(): void
    {
        $rnd = User::query()->where('role', 'RND')->firstOrFail();
        $fss = User::query()->where('role', 'FSS')->firstOrFail();
        $today = CarbonImmutable::now('Asia/Manila')->toDateString();
        if ($today < self::LAST_LOGGED_DAY) {
            throw new RuntimeException('October demo backfill cannot run before October 14, 2026.');
        }
        if (! MenuCycle::query()->whereDate('week_start_date', '2026-09-21')->whereHas('days')->exists()) {
            throw new RuntimeException('The September 21 source menu is required for the October demo backfill.');
        }

        $previousUser = Auth::user();
        try {
            Auth::login($rnd);
            DB::transaction(fn () => $this->fillMenus($rnd));
            Auth::login($fss);
            $this->fillDailyLogs($fss);
            Auth::login($rnd);
            $this->fillProcurement($rnd);
        } finally {
            $previousUser ? Auth::login($previousUser) : Auth::logout();
        }
    }

    private function fillMenus(User $rnd): void
    {
        foreach (['2026-09-21', '2026-09-28', '2026-10-05', '2026-10-12'] as $week) {
            $cycle = MenuCycle::query()->whereDate('week_start_date', $week)->first();
            if (! $cycle) {
                $cycle = MenuCycle::create([
                    'rnd_user_id' => $rnd->id,
                    'name' => MenuCycle::defaultName($week),
                    'cycle_days' => 7,
                    'week_start_date' => $week,
                    'status' => $week === '2026-10-12' ? 'active' : 'completed',
                    'is_active' => $week === '2026-10-12',
                    'activation_date' => $week,
                ]);
            }
            if (! $cycle->days()->exists()) {
                $source = MenuCycle::query()->whereDate('week_start_date', '<', $week)
                    ->whereHas('days')->orderByDesc('week_start_date')->firstOrFail();
                foreach ($source->days as $day) {
                    $cycle->days()->create($day->only([
                        'day_of_week', 'meal_type', 'line_order', 'recipe_id', 'fs_item_id',
                        'quantity', 'servings_override', 'recipe_override', 'estimate_population',
                        'is_event', 'event_allocation',
                    ]));
                }
            }
            $state = $week === '2026-10-12' ? 'active' : 'completed';
            $changes = [];
            if ($cycle->status !== $state || $cycle->is_active !== ($state === 'active')) {
                $changes += ['status' => $state, 'is_active' => $state === 'active'];
            }
            if (! $cycle->activation_date) {
                $changes['activation_date'] = $week;
            }
            if (! $cycle->cost_snapshot) {
                $changes += [
                    'cost_snapshot' => MenuCycleCostService::forCycle($cycle->fresh()),
                    'cost_snapshot_at' => now(),
                ];
            }
            if ($changes !== []) {
                $cycle->update($changes);
            }
        }
    }

    private function fillDailyLogs(User $fss): void
    {
        $changedPeriods = [];
        for ($day = CarbonImmutable::parse(self::FIRST_DAY); $day->toDateString() <= self::LAST_LOGGED_DAY; $day = $day->addDay()) {
            $date = $day->toDateString();
            $cycle = MenuCycle::coveringDate($date);
            if (! $cycle || ! $cycle->days()->where('day_of_week', $day->format('l'))->exists()) {
                throw new RuntimeException("No planned menu for {$date}.");
            }
            $log = MealPrepLog::query()->where('menu_cycle_id', $cycle->id)->whereDate('service_date', $date)->first();
            if (! $log) {
                $population = self::POPULATION[$day->format('l')];
                $log = app(ConsumptionService::class)->completeDay($cycle, $date, $population, $population - ($day->day % 7));
                app(PurchaseOrderLifecycleService::class)->refreshForServiceDate($date);
            }
            if (DietListCount::query()->where('fss_user_id', $fss->id)->whereDate('service_date', $date)->exists()) {
                continue;
            }
            $served = (int) $log->served_population;
            if ($served < 1) {
                throw new RuntimeException("Served population is missing for {$date}.");
            }
            DietListCount::create([
                'service_date' => $date,
                'menu_cycle_id' => $cycle->id,
                'ward' => 'Accomplishment report',
                'fss_user_id' => $fss->id,
                'population' => $served,
                'collected_ward_diet_lists' => 3,
                'apportioned_distributed_meals' => $served,
                'helped_food_prep' => true,
                'stored_supplies' => true,
                'cleaned_utensils' => true,
                'maintained_cleanliness' => true,
            ]);
            [$periodStart] = AccomplishmentReportArchiveService::periodBounds($date);
            $changedPeriods[$periodStart] = true;
        }
        foreach (array_keys($changedPeriods) as $start) {
            app(AccomplishmentReportArchiveService::class)->preparePeriod($fss, $start);
        }
    }

    private function fillProcurement(User $rnd): void
    {
        $spans = [
            ['2026-09-15', '2026-09-17'], ['2026-09-18', '2026-09-21'],
            ['2026-09-22', '2026-09-24'], ['2026-09-25', '2026-09-28'],
            ['2026-09-29', '2026-10-01'], ['2026-10-02', '2026-10-05'],
            ['2026-10-06', '2026-10-08'], ['2026-10-09', '2026-10-12'],
            ['2026-10-13', '2026-10-15'],
        ];
        $controller = app(PurchaseOrderController::class);
        $lifecycle = app(PurchaseOrderLifecycleService::class);

        foreach ($spans as [$start, $end]) {
            $list = ShoppingList::query()->whereDate('period_start', $start)->whereDate('period_end', $end)
                ->where('procurement_track', 'food')->first();
            if (! $list) {
                $plan = app(ShoppingListPopulationService::class)->planRange($start, $end, 170);
                if ($plan['items'] === [] || $plan['uncovered_dates'] !== [] || $plan['missing_population_dates'] !== []) {
                    throw new RuntimeException("Menu coverage is incomplete for {$start} through {$end}.");
                }
                $list = DB::transaction(function () use ($rnd, $start, $end, $plan): ShoppingList {
                    $list = ShoppingList::create([
                        'rnd_user_id' => $rnd->id,
                        'name' => 'Demo Marketing — '.CarbonImmutable::parse($start)->format('M j').'–'.CarbonImmutable::parse($end)->format('M j, Y'),
                        'list_date' => $start,
                        'period_start' => $start,
                        'period_end' => $end,
                        'days_span' => CarbonImmutable::parse($start)->diffInDays($end) + 1,
                        'list_type' => 'suggested',
                        'procurement_track' => 'food',
                        'coverage_status' => 'full',
                        'status' => 'draft',
                        'estimate_population' => 170,
                        'estimate_population_updated_at' => now(),
                    ]);
                    foreach ($plan['items'] as $row) {
                        $list->items()->create($row);
                    }
                    app(ShoppingListPopulationService::class)->cascadeMenuDays($start, $end, 170);

                    return $list;
                });
            }
            $po = PurchaseOrder::query()->where('shopping_list_id', $list->id)->first();
            if (! $po) {
                $readiness = $lifecycle->releaseReadiness($list);
                if (! $readiness['ready']) {
                    throw new RuntimeException("Shopping list {$start} is not ready: ".json_encode($readiness['blockers']));
                }
                $controller->approve($list, $lifecycle);
                $po = PurchaseOrder::query()->where('shopping_list_id', $list->id)->firstOrFail();
            }
            if (in_array($po->lifecycle_status, ['completed', 'archived'], true)) {
                continue;
            }
            $groups = $po->vendorGroups()->orderBy('id')->get();
            foreach ($groups as $index => $group) {
                if ($end === '2026-10-15' && $index === $groups->count() - 1) {
                    continue;
                }
                if ($group->status !== 'received') {
                    $this->receiveGroup($controller, $lifecycle, $group);
                }
            }
            $lifecycle->refresh($po);
        }
    }

    private function receiveGroup(PurchaseOrderController $controller, PurchaseOrderLifecycleService $lifecycle, PurchaseOrderVendorGroup $group): void
    {
        $proof = StoredObject::query()->where('purpose', 'purchase_order')->where('original_name', self::RECEIPT_NAME)->firstOrFail();
        if (! Storage::disk($proof->storage_disk)->exists($proof->object_key)) {
            throw new RuntimeException('The seeded fictional receipt image is missing from private storage.');
        }
        if (! $group->supplier_id) {
            throw new RuntimeException("Vendor group {$group->id} has no supplier.");
        }
        foreach (['receipt', 'proof'] as $type) {
            if (! $group->attachments()->where('type', $type)->exists()) {
                PurchaseOrderAttachment::create([
                    'purchase_order_id' => $group->purchase_order_id,
                    'vendor_group_id' => $group->id,
                    'stored_object_id' => $proof->id,
                    'type' => $type,
                    'caption' => "Fictional demo {$type}",
                ]);
            }
        }
        $items = $group->items()->get()->map(fn ($item): array => [
            'id' => $item->id,
            'actual_qty' => (float) ($item->purchase_qty ?? $item->qty),
            'actual_unit_price' => (float) ($item->purchase_price ?? $item->unit_price),
        ])->all();
        $request = Request::create('/', 'PATCH', [
            'or_number' => 'DEMO-'.$group->purchaseOrder->order_date->format('Ymd').'-'.$group->id,
            'status' => 'received',
            'items' => $items,
        ]);
        $controller->updateVendorGroup($request, $group, $lifecycle);
    }
}
