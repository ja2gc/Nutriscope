<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\DietListCount;
use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Models\FoodServiceSetting;
use App\Models\FsItem;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\ReportBranding;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Models\User;
use App\Services\FSS\AccomplishmentReportArchiveService;
use App\Services\FSS\PurchaseOrderLifecycleService;
use App\Services\FSS\ShoppingListPopulationService;
use App\Services\MenuCycleCostService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Decouple-correct demo data for the whole food-service loop, themed to the real
 * RPDH PPA menu so every report tab + the calculated budget-per-head has data.
 *
 * Produces a FULL MONTH of operations: three COMPLETED past weekly cycles plus the
 * current active cycle. Each week carries its own menu cycle, suggested shopping list
 * (with the manual total_served_population census), received vendor POs, completed
 * meal-prep (served) logs, and diet-list counts — so estimated AND actual
 * budget-per-head, the budget graph, accomplishment, dietary cash book, PPA and menu
 * calendar reports all render real figures.
 *
 * Past weeks are fully closed (actual per-head computes); the current week is still
 * running (served population not yet entered) so the "pending" state is demonstrable.
 *
 * Idempotent: truncates only the operational FS tables (never fs_items / food_items
 * / recipes / patients).
 */
class FoodServiceDemoSeeder extends Seeder
{
    private array $fs = [];      // fs_item name => id

    private array $recipes = []; // recipe name => FoodServiceRecipe

    private array $suppliers = [];

    /**
     * Five genuinely different weekly menus keyed by week index.
     * Each day is [breakfast, am_snack, lunch, pm_snack, dinner].
     * Proteins are weighted per week (beef/pork-heavy vs chicken/fish-light) so
     * system-computed weekly cost and actual ₱/head differ meaningfully across cycles.
     */
    private array $plans = [
        // Week 0 — current/active: balanced mix.
        0 => [
            'Monday' => ['Cheezwhiz Sandwich', 'Yakult', 'Pork Pinakbet', 'Latundan banana', 'Chicken Sisig'],
            'Tuesday' => ['Sopas', 'Coffee', 'Chicken Fillet w/ Mushroom Sauce', 'Saba banana', 'Pork Picadillo'],
            'Wednesday' => ['Mami Noodle Soup', 'Fresh milk', 'Beef Caldereta', 'Ponkan', 'Paksiw na Bangus'],
            'Thursday' => ['Pandesal with Boiled Egg', 'Milo', 'Chicken with Lemongrass', 'Saba banana', 'Pork Strips Oriental with Corn'],
            'Friday' => ['Cheezwhiz Sandwich', 'Yakult', 'Pork Picadillo', 'Brownie bite', 'Chicken Fillet w/ Mushroom Sauce'],
            'Saturday' => ['Sopas', 'Coffee', 'Paksiw na Bangus', 'Chooey toffee', 'Beef Caldereta'],
            'Sunday' => ['Pandesal with Boiled Egg', 'Milo', 'Chicken Sisig', 'Latundan banana', 'Pork Pinakbet'],
        ],
        // Week 1 — beef/pork heavy: highest cost.
        1 => [
            'Monday' => ['Cheezwhiz Sandwich', 'Yakult', 'Beef Caldereta', 'Latundan banana', 'Pork Pinakbet'],
            'Tuesday' => ['Sopas', 'Coffee', 'Pork Strips Oriental with Corn', 'Saba banana', 'Beef Caldereta'],
            'Wednesday' => ['Mami Noodle Soup', 'Fresh milk', 'Pork Picadillo', 'Ponkan', 'Beef Caldereta'],
            'Thursday' => ['Pandesal with Boiled Egg', 'Milo', 'Beef Caldereta', 'Saba banana', 'Pork Strips Oriental with Corn'],
            'Friday' => ['Cheezwhiz Sandwich', 'Yakult', 'Pork Pinakbet', 'Brownie bite', 'Pork Picadillo'],
            'Saturday' => ['Sopas', 'Coffee', 'Beef Caldereta', 'Chooey toffee', 'Pork Strips Oriental with Corn'],
            'Sunday' => ['Pandesal with Boiled Egg', 'Milo', 'Pork Picadillo', 'Latundan banana', 'Pork Pinakbet'],
        ],
        // Week 2 — chicken/fish: lightest cost.
        2 => [
            'Monday' => ['Sopas', 'Coffee', 'Chicken Sisig', 'Latundan banana', 'Paksiw na Bangus'],
            'Tuesday' => ['Mami Noodle Soup', 'Fresh milk', 'Chicken with Lemongrass', 'Saba banana', 'Chicken Fillet w/ Mushroom Sauce'],
            'Wednesday' => ['Cheezwhiz Sandwich', 'Yakult', 'Paksiw na Bangus', 'Ponkan', 'Chicken Sisig'],
            'Thursday' => ['Pandesal with Boiled Egg', 'Milo', 'Chicken Fillet w/ Mushroom Sauce', 'Saba banana', 'Chicken with Lemongrass'],
            'Friday' => ['Sopas', 'Coffee', 'Chicken Sisig', 'Brownie bite', 'Paksiw na Bangus'],
            'Saturday' => ['Mami Noodle Soup', 'Fresh milk', 'Chicken with Lemongrass', 'Chooey toffee', 'Chicken Fillet w/ Mushroom Sauce'],
            'Sunday' => ['Pandesal with Boiled Egg', 'Milo', 'Paksiw na Bangus', 'Latundan banana', 'Chicken Sisig'],
        ],
        // Week 3 — mixed, distinct from week 0.
        3 => [
            'Monday' => ['Pandesal with Boiled Egg', 'Milo', 'Pork Strips Oriental with Corn', 'Saba banana', 'Chicken with Lemongrass'],
            'Tuesday' => ['Cheezwhiz Sandwich', 'Yakult', 'Beef Caldereta', 'Latundan banana', 'Chicken Sisig'],
            'Wednesday' => ['Sopas', 'Coffee', 'Chicken Fillet w/ Mushroom Sauce', 'Ponkan', 'Pork Pinakbet'],
            'Thursday' => ['Mami Noodle Soup', 'Fresh milk', 'Paksiw na Bangus', 'Saba banana', 'Beef Caldereta'],
            'Friday' => ['Pandesal with Boiled Egg', 'Milo', 'Pork Picadillo', 'Brownie bite', 'Chicken with Lemongrass'],
            'Saturday' => ['Cheezwhiz Sandwich', 'Yakult', 'Chicken Sisig', 'Chooey toffee', 'Pork Strips Oriental with Corn'],
            'Sunday' => ['Sopas', 'Coffee', 'Beef Caldereta', 'Latundan banana', 'Paksiw na Bangus'],
        ],
        // Week 4 — draft/next: breakfast-heavy and fish/chicken forward.
        4 => [
            'Monday' => ['Mami Noodle Soup', 'Fresh milk', 'Paksiw na Bangus', 'Ponkan', 'Chicken Fillet w/ Mushroom Sauce'],
            'Tuesday' => ['Pandesal with Boiled Egg', 'Milo', 'Pork Pinakbet', 'Latundan banana', 'Chicken with Lemongrass'],
            'Wednesday' => ['Cheezwhiz Sandwich', 'Yakult', 'Chicken Sisig', 'Saba banana', 'Pork Strips Oriental with Corn'],
            'Thursday' => ['Sopas', 'Coffee', 'Beef Caldereta', 'Brownie bite', 'Paksiw na Bangus'],
            'Friday' => ['Mami Noodle Soup', 'Fresh milk', 'Chicken with Lemongrass', 'Chooey toffee', 'Pork Picadillo'],
            'Saturday' => ['Pandesal with Boiled Egg', 'Milo', 'Pork Pinakbet', 'Ponkan', 'Chicken Fillet w/ Mushroom Sauce'],
            'Sunday' => ['Cheezwhiz Sandwich', 'Yakult', 'Pork Strips Oriental with Corn', 'Saba banana', 'Chicken Sisig'],
        ],
    ];

    private array $dayPop = [
        'Monday' => 175, 'Tuesday' => 168, 'Wednesday' => 182, 'Thursday' => 160,
        'Friday' => 190, 'Saturday' => 155, 'Sunday' => 172,
    ];

    public function run(): void
    {
        $rnd = User::where('role', 'RND')->value('id');
        $fss = User::where('role', 'FSS')->value('id') ?? $rnd;
        if (! $rnd) {
            $this->command->warn('FoodServiceDemoSeeder: no RND user. Run AdminUserSeeder first.');

            return;
        }
        if (FsItem::count() === 0) {
            $this->command->warn('FoodServiceDemoSeeder: fs_items empty. Run FsCatalogSeeder first.');

            return;
        }

        $this->reset();
        $this->fs = FsItem::pluck('id', 'name')->all();

        $this->seedSuppliers();
        $this->seedRecipes($rnd);
        // Pin each catalog item's default vendor so the suggested list resolves a vendor
        // per ingredient and the PO conversion can group lines by supplier.
        $this->seedItemVendors();

        // Ensure a report branding row exists so PDF generation doesn't abort.
        ReportBranding::singleton();

        // Four Monday→Sunday weeks: 3 completed past cycles + the current active one.
        // Week index 0 = current; 3/2/1 = oldest→newest past.
        $currentWeekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $fssUser = User::find($fss);
        $archiveService = app(AccomplishmentReportArchiveService::class);
        $cycles = [];
        $cycleMeta = [];
        for ($w = 3; $w >= 0; $w--) {
            $weekStart = $currentWeekStart->copy()->subWeeks($w);
            $isCurrent = ($w === 0);
            $cycle = $this->seedCycleForWeek($rnd, $weekStart, $isCurrent, null, $w);
            $this->seedConsumptionForWeek($cycle, $fss, $weekStart, $isCurrent, $w);
            // The whole procurement record is produced by the real flow: suggested list
            // (system-extracted) → ONE PO with a vendor group per supplier → receipts.
            // Past weeks complete; the current week is left in open execution (pending).
            $this->seedProcurementForWeek($cycle, $fss, $weekStart, $isCurrent, $w);
            $cycles[] = $cycle;
            $cycleMeta[] = ['weekStart' => $weekStart->copy(), 'isCurrent' => $isCurrent, 'weekIndex' => $w];

        }

        // Direct seeding bypasses the save endpoint, so prepare each populated
        // semi-monthly period once after all daily records exist.
        if ($fssUser) {
            DietListCount::query()
                ->where('fss_user_id', $fssUser->id)
                ->where('ward', 'Accomplishment report')
                ->pluck('service_date')
                ->map(fn ($date) => Carbon::parse($date)->startOfDay())
                ->unique(fn (Carbon $date) => $date->format('Y-m-').($date->day <= 15 ? '01' : '16'))
                ->each(fn (Carbon $date) => $archiveService->preparePeriod($fssUser, $date));
        }

        // Next week's cycle as an UPCOMING plan, plus a DRAFT suggested shopping list with
        // its estimated population set — so the planner sees the live estimated budget per
        // head per day and editable, system-extracted ingredients before any conversion.
        $upcomingStart = $currentWeekStart->copy()->addWeek();
        $upcoming = $this->seedCycleForWeek($rnd, $upcomingStart, false, 'upcoming', 4);
        foreach ($cycleMeta as $meta) {
            $this->seedFridayToMondayProcurement($fss, $meta['weekStart'], $meta['isCurrent'], $meta['weekIndex']);
        }
        $this->seedDraftSuggestedList($upcoming, $fss, $upcomingStart, 4);

        $this->seedBudget($fss, end($cycles));

        $this->command->info('FoodServiceDemoSeeder: '.count($cycles).' weekly cycles (3 completed + 1 active) + 1 upcoming draft seeded.');
    }

    private function reset(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ([
            'purchase_order_item_corrections', 'program_project_activities',
            'purchase_order_attachments', 'purchase_order_items', 'purchase_order_vendor_groups', 'purchase_orders',
            'shopping_list_items', 'shopping_lists',
            'budget_ledger', 'budget_daily_logs', 'budgets',
            'meal_prep_log_lines', 'meal_prep_logs', 'diet_list_counts',
            'menu_cycle_days', 'menu_cycles',
            'food_service_recipe_ingredients', 'food_service_recipes',
            'inventory', 'suppliers',
        ] as $t) {
            if (Schema::hasTable($t)) {
                \DB::table($t)->truncate();
            }
        }
        Schema::enableForeignKeyConstraints();
    }

    private function id(string $name): ?int
    {
        return $this->fs[$name] ?? null;
    }

    private function planForWeek(int $weekIndex): array
    {
        return $this->plans[$weekIndex % count($this->plans)];
    }

    private function popFactor(int $weekIndex): float
    {
        return [0 => 1.00, 1 => 0.90, 2 => 1.12, 3 => 0.82][$weekIndex] ?? 1.0;
    }

    // ── Suppliers (payees from the real Dietary Cash Book) ──────────────────
    private function seedSuppliers(): void
    {
        $rows = [
            ['Gloria T.M. General Merchandise', 'Vegetables & fruits', 'Poblacion, Floridablanca'],
            ['MACMA Trading', 'Meat & fish', 'San Fernando, Pampanga'],
            ['RPDH-MPC', 'Groceries & condiments', 'Hospital cooperative'],
            ['SAMEJ Rice Store', 'Rice & grains', 'San Jose, Floridablanca'],
            ['Lolita R. Cayanan', 'Fruits (saba, latundan)', 'Public market'],
            ['Pampanga Gas & Supplies Trading', 'LPG, disposables, cleaning', 'Floridablanca'],
        ];
        foreach ($rows as [$name, $category, $address]) {
            $this->suppliers[$name] = Supplier::create([
                'name' => $name, 'category' => $category, 'address' => $address,
                'payment_terms' => 'Cash on delivery',
            ]);
        }
    }

    // ── FS recipes (cost-only), themed to the PPA menu ──────────────────────
    private function seedRecipes(int $rnd): void
    {
        // FS recipes carry no category — name => [servings, [[fs_item, qty], ...]].
        $defs = [
            'Cheezwhiz Sandwich' => [50, [['Loaf bread', 100], ['Cheez Whiz', 750]]],
            'Pandesal with Boiled Egg' => [50, [['Pandesal', 100], ['Egg', 50]]],
            'Sopas' => [50, [['Macaroni', 2500], ['Chicken (whole)', 3000], ['Carrot', 1000], ['Assorted vegetables', 1500], ['Fresh milk', 2000]]],
            'Mami Noodle Soup' => [50, [['Macaroni', 3000], ['Chicken (whole)', 3000], ['Garlic', 200], ['Onion', 300]]],
            'Pork Pinakbet' => [50, [['Pork (kasim)', 3000], ['Pinakbet vegetables', 5000], ['Garlic', 200], ['Onion', 400], ['Tomato', 600]]],
            'Pork Picadillo' => [50, [['Ground pork', 3000], ['Potato', 2500], ['Carrot', 1500], ['Garlic', 200], ['Onion', 400]]],
            'Pork Strips Oriental with Corn' => [50, [['Pork (kasim)', 3000], ['Corn kernel', 2000], ['Soy sauce', 500], ['Garlic', 200]]],
            'Beef Caldereta' => [50, [['Beef (cubes)', 3500], ['Potato', 2500], ['Carrot', 1500], ['Tomato', 800], ['Garlic', 200], ['Onion', 400]]],
            'Chicken Fillet w/ Mushroom Sauce' => [50, [['Chicken fillet', 4000], ['Mushroom (canned)', 1600], ['Fresh milk', 2000], ['Onion', 400]]],
            'Chicken Sisig' => [50, [['Chicken fillet', 4000], ['Onion', 600], ['Soy sauce', 400], ['Cooking oil', 500]]],
            'Chicken with Lemongrass' => [50, [['Chicken (whole)', 5000], ['Ginger', 300], ['Garlic', 250], ['Salt', 150]]],
            'Paksiw na Bangus' => [50, [['Bangus (milkfish)', 5000], ['Vinegar', 800], ['Garlic', 250], ['Ginger', 300]]],
        ];

        foreach ($defs as $name => [$servings, $lines]) {
            $recipe = FoodServiceRecipe::create([
                'rnd_user_id' => $rnd, 'name' => $name, 'servings' => $servings,
            ]);
            foreach ($lines as [$itemName, $qty]) {
                $fsId = $this->id($itemName);
                if (! $fsId) {
                    $this->command->warn("  recipe '{$name}': fs_item '{$itemName}' missing");

                    continue;
                }
                // Recipe quantities are authored in fine units (g/mL/pc). The catalog item
                // carries a single coarse unit (kg/L/pc); the UnitConverter resolves the
                // rate, so a g quantity against a kg item costs correctly.
                FoodServiceRecipeIngredient::create([
                    'food_service_recipe_id' => $recipe->id,
                    'fs_item_id' => $fsId,
                    'quantity' => $qty,
                    'unit' => $this->recipeUnitFor(FsItem::find($fsId)->base_unit),
                ]);
            }
            $recipe->recalculateCost();
            $this->recipes[$name] = $recipe->fresh();
        }
    }

    /** Fine recipe unit for a coarse catalog unit (kg→g, L→mL); counts stay as-is. */
    private function recipeUnitFor(string $baseUnit): string
    {
        return match ($baseUnit) {
            'kg' => 'g',
            'L' => 'mL',
            default => $baseUnit,
        };
    }

    // ── One weekly menu cycle for the given week ────────────────────────────
    private function seedCycleForWeek(int $rnd, Carbon $weekStart, bool $isCurrent, ?string $statusOverride = null, int $weekIndex = 0): MenuCycle
    {
        // States: completed | active | upcoming (the redesigned menu-cycle lifecycle).
        $status = $statusOverride ?? ($isCurrent ? 'active' : 'completed');
        $cycle = MenuCycle::create([
            'rnd_user_id' => $rnd,
            'name' => 'Subsistence Cycle — Week of '.$weekStart->format('M j'),
            'cycle_days' => 7,
            'is_active' => $isCurrent,
            'status' => $status,
            'week_start_date' => $weekStart->toDateString(),
            'activation_date' => $status === 'upcoming' ? null : $weekStart->toDateString(),
        ]);

        $plan = $this->planForWeek($weekIndex);
        $popFactor = $this->popFactor($weekIndex);
        $slots = ['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner'];
        foreach ($plan as $day => $items) {
            foreach ($slots as $i => $slot) {
                $name = $items[$i];
                $row = [
                    'menu_cycle_id' => $cycle->id, 'day_of_week' => $day, 'meal_type' => $slot,
                    'estimate_population' => null,
                ];
                if (isset($this->recipes[$name])) {
                    $row['recipe_id'] = $this->recipes[$name]->id;
                } elseif ($this->id($name)) {
                    $row['fs_item_id'] = $this->id($name);
                } else {
                    continue;
                }
                MenuCycleDay::create($row);
            }
        }

        $cycle = $cycle->fresh();

        // Freeze the cost snapshot so filed reports (PPA / menu calendar) keep their
        // cost even if catalog prices change later — matches the activate() flow.
        $cycle->update([
            'cost_snapshot' => MenuCycleCostService::forCycle($cycle),
            'cost_snapshot_at' => $weekStart->copy()->addDay(),
        ]);

        return $cycle->fresh();
    }

    /**
     * Completed meal-prep (served) logs + diet-list counts for each day of the week.
     * Past weeks are fully served; the current week only up to today. Returns the
     * total served population across the week (census denominator for per-head).
     */
    private function seedConsumptionForWeek(MenuCycle $cycle, int $fss, Carbon $weekStart, bool $isCurrent, int $weekIndex = 0): int
    {
        $cost = MenuCycleCostService::forCycle($cycle);
        $today = Carbon::now();
        $popFactor = $this->popFactor($weekIndex);
        $totalServed = 0;

        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $weekday = $date->format('l');
            if ($isCurrent && $date->gt($today)) {
                break; // current week: don't serve the future
            }

            $planned = (int) round($this->dayPop[$weekday] * $popFactor);
            $variance = (($weekIndex + 2) * ($i + 3)) % 13;
            $served = max(0, $planned - $variance);
            $dayCost = (float) ($cost['days'][$weekday]['cost'] ?? 0);
            $totalServed += $served;

            MealPrepLog::create([
                'menu_cycle_id' => $cycle->id,
                'service_date' => $date->toDateString(),
                'population' => $planned,
                'served_population' => $served,
                'population_variance' => $planned - $served,
                'status' => 'completed',
                'completed_by' => $fss,
                'completed_at' => $date->copy()->setTime(13, 0),
                'total_value' => round($dayCost, 2),
                'has_shortfall' => false,
            ]);

            // One current accomplishment-form record per staff member and date.
            DietListCount::create([
                'service_date' => $date->toDateString(),
                'menu_cycle_id' => $cycle->id,
                'ward' => 'Accomplishment report',
                'fss_user_id' => $fss,
                'population' => $served,
                'collected_ward_diet_lists' => 3,
                'apportioned_distributed_meals' => $served,
                'helped_food_prep' => true,
                'stored_supplies' => true,
                'collected_diet_list' => true,
                'apportioned_food' => true,
                'cleaned_utensils' => true,
                'assistant_cook' => $i % 3 === 0,
                'maintained_cleanliness' => true,
            ]);
        }

        return $totalServed;
    }

    // ── Catalog default vendors (drives suggested-list vendor + PO grouping) ──
    private function seedItemVendors(): void
    {
        // item name => supplier name (single source for the suggested list's vendor).
        $map = [
            // MACMA Trading — meat & fish
            'Pork (kasim)' => 'MACMA Trading', 'Ground pork' => 'MACMA Trading',
            'Chicken (whole)' => 'MACMA Trading', 'Chicken fillet' => 'MACMA Trading',
            'Beef (cubes)' => 'MACMA Trading', 'Bangus (milkfish)' => 'MACMA Trading',
            // Gloria T.M. — vegetables & fruits
            'Pinakbet vegetables' => 'Gloria T.M. General Merchandise',
            'Assorted vegetables' => 'Gloria T.M. General Merchandise',
            'Potato' => 'Gloria T.M. General Merchandise', 'Carrot' => 'Gloria T.M. General Merchandise',
            'Onion' => 'Gloria T.M. General Merchandise', 'Garlic' => 'Gloria T.M. General Merchandise',
            'Tomato' => 'Gloria T.M. General Merchandise', 'Ginger' => 'Gloria T.M. General Merchandise',
            'Corn kernel' => 'Gloria T.M. General Merchandise',
            // RPDH-MPC — groceries & condiments
            'Cooking oil' => 'RPDH-MPC', 'Soy sauce' => 'RPDH-MPC', 'Vinegar' => 'RPDH-MPC',
            'Macaroni' => 'RPDH-MPC', 'Fresh milk' => 'RPDH-MPC', 'Egg' => 'RPDH-MPC',
            'Cheez Whiz' => 'RPDH-MPC', 'Loaf bread' => 'RPDH-MPC', 'Pandesal' => 'RPDH-MPC',
            'Mushroom (canned)' => 'RPDH-MPC', 'Salt' => 'RPDH-MPC',
            'Coffee' => 'RPDH-MPC', 'Milo' => 'RPDH-MPC', 'Yakult' => 'RPDH-MPC',
            'Brownie bite' => 'RPDH-MPC', 'Chooey toffee' => 'RPDH-MPC',
            // SAMEJ Rice Store — rice & grains
            'Rice' => 'SAMEJ Rice Store',
            // Lolita R. Cayanan — fruits
            'Latundan banana' => 'Lolita R. Cayanan', 'Saba banana' => 'Lolita R. Cayanan',
            'Ponkan' => 'Lolita R. Cayanan',
            // Pampanga Gas & Supplies — supplies
            'LPG (cooking gas)' => 'Pampanga Gas & Supplies Trading',
            'Paper meal box' => 'Pampanga Gas & Supplies Trading',
            'Roll bag (garbage)' => 'Pampanga Gas & Supplies Trading',
            'Dishwashing liquid' => 'Pampanga Gas & Supplies Trading',
            'Disposable spoon' => 'Pampanga Gas & Supplies Trading',
            'Plastic cup' => 'Pampanga Gas & Supplies Trading',
        ];

        foreach ($map as $itemName => $vendorName) {
            $itemId = $this->id($itemName);
            $vendor = $this->suppliers[$vendorName] ?? null;
            if ($itemId && $vendor) {
                FsItem::whereKey($itemId)->update(['default_supplier_id' => $vendor->id]);
            }
        }
    }

    /** Representative head-count for a week's suggested list (avg planned day pop). */
    private function listEstimatePopulation(int $weekIndex): int
    {
        $factor = $this->popFactor($weekIndex);
        $avg = array_sum($this->dayPop) / count($this->dayPop);

        return (int) round($avg * $factor);
    }

    /**
     * Build the week's procurement exactly as the system would: a system-extracted
     * suggested shopping list, then ONE purchase order with a vendor group per
     * supplier. Past weeks are receipted in full; the current week is left in open
     * execution with one vendor group still awaiting a receipt (Pending PO demo).
     */
    private function seedProcurementForWeek(MenuCycle $cycle, int $fss, Carbon $weekStart, bool $isCurrent, int $weekIndex): void
    {
        $start = $weekStart->copy()->addDay()->toDateString();
        $end = $weekStart->copy()->addDays(3)->toDateString();

        $list = $this->buildSuggestedList($fss, $weekStart, $start, $end, $weekIndex, 'Marketing - Tue-Thu '.$weekStart->format('M j'));
        if (! $list) {
            return; // no covered/planned days — nothing to procure
        }

        $this->convertListToPurchaseOrder($list, $weekStart, $isCurrent);
    }

    private function seedFridayToMondayProcurement(int $fss, Carbon $weekStart, bool $isCurrent, int $weekIndex): void
    {
        $start = $weekStart->copy()->addDays(4)->toDateString();
        $end = $weekStart->copy()->addDays(7)->toDateString();

        $list = $this->buildSuggestedList($fss, $weekStart, $start, $end, $weekIndex, 'Marketing - Fri-Mon '.$weekStart->format('M j'));
        if (! $list) {
            return;
        }

        $this->convertListToPurchaseOrder($list, Carbon::parse($start), $isCurrent);
    }

    /**
     * Create a suggested, food-track shopping list whose items are extracted by the
     * real planner (scaled to each day's estimated population) with the list-level
     * estimated population set so the estimated budget per head per day is live.
     */
    private function buildSuggestedList(int $fss, Carbon $weekStart, string $start, string $end, int $weekIndex, string $name): ?ShoppingList
    {
        $estimate = $this->listEstimatePopulation($weekIndex);
        $plan = app(ShoppingListPopulationService::class)->planRange($start, $end, $estimate);
        if ($plan['items'] === []) {
            return null;
        }

        $list = ShoppingList::create([
            'rnd_user_id' => $fss,
            'name' => $name,
            'list_date' => $start,
            'period_start' => $start,
            'period_end' => $end,
            'days_span' => Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1,
            'list_type' => 'suggested',
            'procurement_track' => 'food',
            'coverage_status' => 'full',
            'status' => 'draft',
            'estimate_population' => $estimate,
            'estimate_population_updated_at' => $weekStart->copy(),
        ]);

        foreach ($plan['items'] as $row) {
            $list->items()->create($row);
        }

        app(ShoppingListPopulationService::class)->cascadeMenuDays($start, $end, $estimate);

        return $list->fresh('items');
    }

    /**
     * Convert a suggested list into ONE purchase order with a vendor group per supplier
     * — mirroring PurchaseOrderController::approve: structural lock at conversion, PPA
     * snapshot, frozen menu-cycle day snapshots, then receipts/proof per vendor group.
     */
    private function convertListToPurchaseOrder(ShoppingList $list, Carbon $weekStart, bool $isCurrent): void
    {
        $list->loadMissing('items');
        $lifecycle = app(PurchaseOrderLifecycleService::class);
        $orderDate = $list->period_start ? Carbon::parse($list->period_start) : $weekStart->copy();
        $endDate = $list->period_end ? Carbon::parse($list->period_end) : $orderDate->copy();
        $weekTag = $orderDate->format('md').'-'.$endDate->format('mdy');

        $po = PurchaseOrder::create([
            'rnd_user_id' => $list->rnd_user_id,
            'shopping_list_id' => $list->id,
            'supplier_id' => null,
            'po_number' => 'PO-'.$weekTag,
            'order_date' => $orderDate->toDateString(),
            'total_amount' => round((float) $list->items->where('included_in_po', true)->sum(fn ($i) => (float) $i->total), 2),
            'status' => 'draft',
            'lifecycle_status' => 'open_execution',
            'procurement_track' => 'food',
            'converted_at' => $orderDate,
            'structural_locked_at' => $orderDate,
        ]);

        foreach ($list->items->where('included_in_po', true)->groupBy('supplier_id') as $supplierId => $items) {
            $group = $po->vendorGroups()->create([
                'supplier_id' => $supplierId !== '' ? (int) $supplierId : null,
                'status' => 'pending',
                'total_amount' => round((float) $items->sum(fn ($i) => (float) $i->total), 2),
            ]);

            foreach ($items as $it) {
                $po->items()->create([
                    'vendor_group_id' => $group->id,
                    'fs_item_id' => $it->fs_item_id,
                    'description' => $it->ingredient_name,
                    'qty' => $it->qty,
                    'unit' => $it->unit,
                    'unit_price' => $it->unit_price,
                    'total_value' => $it->total,
                    'purchase_qty' => $it->purchase_qty,
                    'purchase_unit' => $it->purchase_unit,
                    'purchase_price' => $it->purchase_price,
                ]);
            }
        }

        $po->recalcTotal();
        $lifecycle->createPpaSnapshot($po, $list);
        $lifecycle->writeMenuCycleSnapshots($po->fresh('items'), $list);
        $list->update(['status' => 'converted']);

        // Receipts + proof + OR per vendor group. The current week leaves its last group
        // un-receipted so the PO stays in open execution (the Pending PO demo case).
        $groups = $po->vendorGroups()->get()->values();
        foreach ($groups as $idx => $group) {
            $leaveOpen = $isCurrent && $idx === $groups->count() - 1;
            if ($leaveOpen) {
                continue;
            }

            $orNumber = $idx === 0 ? null : 'OR-'.$weekTag.'-'.sprintf('%02d', $idx + 1);
            $group->items()->each(function ($item) use ($idx): void {
                $plannedQty = (float) ($item->purchase_qty ?? $item->qty);
                $actualQty = $item->description === 'Chicken (whole)' ? round($plannedQty + 0.125, 3) : $plannedQty;
                $item->update([
                    'actual_qty' => $actualQty,
                    'actual_unit_price' => (float) ($item->purchase_price ?? $item->unit_price) + ($idx % 2 === 0 ? 0 : 0.50),
                ]);
            });
            $group->forceFill([
                'or_number' => $orNumber,
                'status' => 'received',
                'received_at' => $orderDate->copy()->addDay(),
            ])->save();

            foreach (['receipt', 'proof'] as $type) {
                PurchaseOrderAttachment::create([
                    'purchase_order_id' => $po->id,
                    'vendor_group_id' => $group->id,
                    'type' => $type,
                    'path' => "demo/{$type}s/{$po->po_number}-{$group->id}.jpg",
                    'caption' => ucfirst($type).' — '.($group->supplier?->name ?? 'vendor'),
                ]);
            }
        }
    }

    /**
     * A standalone DRAFT suggested list for an upcoming week — system-extracted items
     * with the estimated population set, so the planner sees the live estimated budget
     * per head per day and editable ingredients before any conversion.
     */
    private function seedDraftSuggestedList(MenuCycle $cycle, int $fss, Carbon $weekStart, int $weekIndex): void
    {
        $start = $weekStart->toDateString();
        $end = $weekStart->copy()->addDays(6)->toDateString();
        $this->buildSuggestedList($fss, $weekStart, $start, $end, $weekIndex, 'Draft marketing — week of '.$weekStart->format('M j'));
    }

    // ── Fiscal-year budget + ledger covering the demo period ────────────────
    private function seedBudget(int $fss, MenuCycle $currentCycle): void
    {
        $cost = MenuCycleCostService::forCycle($currentCycle);
        $dayCosts = array_values(array_map(fn ($d) => $d['cost'], $cost['days']));
        $avgDay = $dayCosts ? array_sum($dayCosts) / count($dayCosts) : 18000;

        $fiscalYear = (int) Carbon::now()->year;

        // One budget row per fiscal year (unified ledger model). Per-head/day limit is
        // configured separately in Food Service settings now, not on the budget row.
        Budget::updateOrCreate(
            ['fiscal_year' => $fiscalYear],
            ['allocated_amount' => round($avgDay * 30, -2)],
        );

        // Budget per head per day lives in the shared Food Service settings.
        FoodServiceSetting::singleton()->update([
            'per_head_day_limit' => 150,
            'updated_by' => $fss,
        ]);

        // Manual entries make the add/deduct audit trail visible. PO deductions are
        // produced by the normal lifecycle + PurchaseOrderCompleted listener below.
        BudgetLedger::where('fiscal_year', $fiscalYear)->delete();

        BudgetLedger::create([
            'fiscal_year' => $fiscalYear, 'type' => 'manual_addition', 'source' => 'manual', 'amount' => 25000,
            'reason' => 'Request for additional subsistence funds', 'created_by' => $fss,
        ]);
        BudgetLedger::create([
            'fiscal_year' => $fiscalYear, 'type' => 'manual_deduction', 'source' => 'manual', 'amount' => 4000,
            'reason' => 'Budget correction', 'created_by' => $fss,
        ]);

        // Drive completion through the real lifecycle: past-week food POs (full receipts
        // + served population) complete and fire the po_deduction ledger entry. The
        // current week's PO stays open (a vendor group still awaiting a receipt).
        $lifecycle = app(PurchaseOrderLifecycleService::class);
        PurchaseOrder::with(['vendorGroups.attachments', 'shoppingList', 'programProjectActivity'])
            ->where('lifecycle_status', 'open_execution')
            ->get()
            ->each(fn (PurchaseOrder $po) => $lifecycle->refresh($po));
    }
}
