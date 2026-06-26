<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\BudgetDailyLog;
use App\Models\DietListCount;
use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Models\FsItem;
use App\Models\Inventory;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\Supplier;
use App\Models\User;
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
            'Monday'    => ['Cheezwhiz Sandwich', 'Yakult', 'Pork Pinakbet', 'Latundan banana', 'Chicken Sisig'],
            'Tuesday'   => ['Sopas', 'Coffee', 'Chicken Fillet w/ Mushroom Sauce', 'Saba banana', 'Pork Picadillo'],
            'Wednesday' => ['Mami Noodle Soup', 'Fresh milk', 'Beef Caldereta', 'Ponkan', 'Paksiw na Bangus'],
            'Thursday'  => ['Pandesal with Boiled Egg', 'Milo', 'Chicken with Lemongrass', 'Saba banana', 'Pork Strips Oriental with Corn'],
            'Friday'    => ['Cheezwhiz Sandwich', 'Yakult', 'Pork Picadillo', 'Brownie bite', 'Chicken Fillet w/ Mushroom Sauce'],
            'Saturday'  => ['Sopas', 'Coffee', 'Paksiw na Bangus', 'Chooey toffee', 'Beef Caldereta'],
            'Sunday'    => ['Pandesal with Boiled Egg', 'Milo', 'Chicken Sisig', 'Latundan banana', 'Pork Pinakbet'],
        ],
        // Week 1 — beef/pork heavy: highest cost.
        1 => [
            'Monday'    => ['Cheezwhiz Sandwich', 'Yakult', 'Beef Caldereta', 'Latundan banana', 'Pork Pinakbet'],
            'Tuesday'   => ['Sopas', 'Coffee', 'Pork Strips Oriental with Corn', 'Saba banana', 'Beef Caldereta'],
            'Wednesday' => ['Mami Noodle Soup', 'Fresh milk', 'Pork Picadillo', 'Ponkan', 'Beef Caldereta'],
            'Thursday'  => ['Pandesal with Boiled Egg', 'Milo', 'Beef Caldereta', 'Saba banana', 'Pork Strips Oriental with Corn'],
            'Friday'    => ['Cheezwhiz Sandwich', 'Yakult', 'Pork Pinakbet', 'Brownie bite', 'Pork Picadillo'],
            'Saturday'  => ['Sopas', 'Coffee', 'Beef Caldereta', 'Chooey toffee', 'Pork Strips Oriental with Corn'],
            'Sunday'    => ['Pandesal with Boiled Egg', 'Milo', 'Pork Picadillo', 'Latundan banana', 'Pork Pinakbet'],
        ],
        // Week 2 — chicken/fish: lightest cost.
        2 => [
            'Monday'    => ['Sopas', 'Coffee', 'Chicken Sisig', 'Latundan banana', 'Paksiw na Bangus'],
            'Tuesday'   => ['Mami Noodle Soup', 'Fresh milk', 'Chicken with Lemongrass', 'Saba banana', 'Chicken Fillet w/ Mushroom Sauce'],
            'Wednesday' => ['Cheezwhiz Sandwich', 'Yakult', 'Paksiw na Bangus', 'Ponkan', 'Chicken Sisig'],
            'Thursday'  => ['Pandesal with Boiled Egg', 'Milo', 'Chicken Fillet w/ Mushroom Sauce', 'Saba banana', 'Chicken with Lemongrass'],
            'Friday'    => ['Sopas', 'Coffee', 'Chicken Sisig', 'Brownie bite', 'Paksiw na Bangus'],
            'Saturday'  => ['Mami Noodle Soup', 'Fresh milk', 'Chicken with Lemongrass', 'Chooey toffee', 'Chicken Fillet w/ Mushroom Sauce'],
            'Sunday'    => ['Pandesal with Boiled Egg', 'Milo', 'Paksiw na Bangus', 'Latundan banana', 'Chicken Sisig'],
        ],
        // Week 3 — mixed, distinct from week 0.
        3 => [
            'Monday'    => ['Pandesal with Boiled Egg', 'Milo', 'Pork Strips Oriental with Corn', 'Saba banana', 'Chicken with Lemongrass'],
            'Tuesday'   => ['Cheezwhiz Sandwich', 'Yakult', 'Beef Caldereta', 'Latundan banana', 'Chicken Sisig'],
            'Wednesday' => ['Sopas', 'Coffee', 'Chicken Fillet w/ Mushroom Sauce', 'Ponkan', 'Pork Pinakbet'],
            'Thursday'  => ['Mami Noodle Soup', 'Fresh milk', 'Paksiw na Bangus', 'Saba banana', 'Beef Caldereta'],
            'Friday'    => ['Pandesal with Boiled Egg', 'Milo', 'Pork Picadillo', 'Brownie bite', 'Chicken with Lemongrass'],
            'Saturday'  => ['Cheezwhiz Sandwich', 'Yakult', 'Chicken Sisig', 'Chooey toffee', 'Pork Strips Oriental with Corn'],
            'Sunday'    => ['Sopas', 'Coffee', 'Beef Caldereta', 'Latundan banana', 'Paksiw na Bangus'],
        ],
        // Week 4 — draft/next: breakfast-heavy and fish/chicken forward.
        4 => [
            'Monday'    => ['Mami Noodle Soup', 'Fresh milk', 'Paksiw na Bangus', 'Ponkan', 'Chicken Fillet w/ Mushroom Sauce'],
            'Tuesday'   => ['Pandesal with Boiled Egg', 'Milo', 'Pork Pinakbet', 'Latundan banana', 'Chicken with Lemongrass'],
            'Wednesday' => ['Cheezwhiz Sandwich', 'Yakult', 'Chicken Sisig', 'Saba banana', 'Pork Strips Oriental with Corn'],
            'Thursday'  => ['Sopas', 'Coffee', 'Beef Caldereta', 'Brownie bite', 'Paksiw na Bangus'],
            'Friday'    => ['Mami Noodle Soup', 'Fresh milk', 'Chicken with Lemongrass', 'Chooey toffee', 'Pork Picadillo'],
            'Saturday'  => ['Pandesal with Boiled Egg', 'Milo', 'Pork Pinakbet', 'Ponkan', 'Chicken Fillet w/ Mushroom Sauce'],
            'Sunday'    => ['Cheezwhiz Sandwich', 'Yakult', 'Pork Strips Oriental with Corn', 'Saba banana', 'Chicken Sisig'],
        ],
    ];

    private array $dayPop = [
        'Monday' => 175, 'Tuesday' => 168, 'Wednesday' => 182, 'Thursday' => 160,
        'Friday' => 190, 'Saturday' => 155, 'Sunday' => 172,
    ];

    /**
     * The base PO quantities below are written per-line as round vendor amounts; on their
     * own they procure far too little food for a ~1,200 head-meal week (e.g. 25 kg pork),
     * so the derived ACTUAL average meal cost would land at ~₱36/head. Scaling the whole
     * week's purchase by this factor lifts procurement to realistic hospital volumes and
     * brings actual cost into the ₱100–130/head band that matches RPDH subsistence rates.
     */
    private const PROCUREMENT_SCALE = 3.2;

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
        $this->seedInventory();

        // Four weeks: 3 completed past cycles + the current active one. Week index 0 =
        // current; 3/2/1 = oldest→newest past. All start Monday so spans are clean.
        $currentWeekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $cycles = [];
        for ($w = 3; $w >= 0; $w--) {
            $weekStart = $currentWeekStart->copy()->subWeeks($w);
            $isCurrent = ($w === 0);
            $cycle = $this->seedCycleForWeek($rnd, $weekStart, $isCurrent, null, $w);
            $served = $this->seedConsumptionForWeek($cycle, $fss, $weekStart, $isCurrent, $w);
            $this->seedProcurementForWeek($cycle, $fss, $weekStart, $isCurrent, $served);
            $cycles[] = $cycle;
        }

        // Next week's cycle as a DRAFT plan (no consumption/procurement yet) so the
        // client's Fri→Mon procurement run can resolve Monday from the upcoming cycle.
        // Demonstrates date-driven, multi-cycle shopping-list generation.
        $this->seedCycleForWeek($rnd, $currentWeekStart->copy()->addWeek(), false, 'draft', 4);

        $this->seedBudget($fss, end($cycles));

        $this->command->info('FoodServiceDemoSeeder: ' . count($cycles) . ' weekly cycles (3 past + current) seeded.');
    }

    private function reset(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ([
            'purchase_order_attachments', 'purchase_order_items', 'purchase_orders',
            'shopping_list_items', 'shopping_lists',
            'budget_daily_logs', 'budgets',
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
            'Cheezwhiz Sandwich'             => [50, [['Loaf bread', 100], ['Cheez Whiz', 750]]],
            'Pandesal with Boiled Egg'       => [50, [['Pandesal', 100], ['Egg', 50]]],
            'Sopas'                          => [50, [['Macaroni', 2500], ['Chicken (whole)', 3000], ['Carrot', 1000], ['Assorted vegetables', 1500], ['Fresh milk', 2000]]],
            'Mami Noodle Soup'               => [50, [['Macaroni', 3000], ['Chicken (whole)', 3000], ['Garlic', 200], ['Onion', 300]]],
            'Pork Pinakbet'                  => [50, [['Pork (kasim)', 3000], ['Pinakbet vegetables', 5000], ['Garlic', 200], ['Onion', 400], ['Tomato', 600]]],
            'Pork Picadillo'                 => [50, [['Ground pork', 3000], ['Potato', 2500], ['Carrot', 1500], ['Garlic', 200], ['Onion', 400]]],
            'Pork Strips Oriental with Corn' => [50, [['Pork (kasim)', 3000], ['Corn kernel', 2000], ['Soy sauce', 500], ['Garlic', 200]]],
            'Beef Caldereta'                 => [50, [['Beef (cubes)', 3500], ['Potato', 2500], ['Carrot', 1500], ['Tomato', 800], ['Garlic', 200], ['Onion', 400]]],
            'Chicken Fillet w/ Mushroom Sauce' => [50, [['Chicken fillet', 4000], ['Mushroom (canned)', 1600], ['Fresh milk', 2000], ['Onion', 400]]],
            'Chicken Sisig'                  => [50, [['Chicken fillet', 4000], ['Onion', 600], ['Soy sauce', 400], ['Cooking oil', 500]]],
            'Chicken with Lemongrass'        => [50, [['Chicken (whole)', 5000], ['Ginger', 300], ['Garlic', 250], ['Salt', 150]]],
            'Paksiw na Bangus'               => [50, [['Bangus (milkfish)', 5000], ['Vinegar', 800], ['Garlic', 250], ['Ginger', 300]]],
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
                FoodServiceRecipeIngredient::create([
                    'food_service_recipe_id' => $recipe->id,
                    'fs_item_id'             => $fsId,
                    'quantity'               => $qty,
                    'unit'                   => FsItem::find($fsId)->base_unit,
                ]);
            }
            $recipe->recalculateCost();
            $this->recipes[$name] = $recipe->fresh();
        }
    }

    // ── Inventory: ingredient + supply stock + a couple prepared recipes ────
    private function seedInventory(): void
    {
        $stock = [
            'Rice' => [80000, null],
            'Pork (kasim)' => [12000, null],
            'Ground pork' => [3000, null],
            'Chicken (whole)' => [18000, null],
            'Chicken fillet' => [2000, null],
            'Beef (cubes)' => [0, 'Out of stock'],
            'Bangus (milkfish)' => [9000, 'Use soon'],
            'Egg' => [600, null],
            'Assorted vegetables' => [15000, null],
            'Pinakbet vegetables' => [8000, null],
            'Potato' => [10000, null],
            'Carrot' => [7000, null],
            'Onion' => [6000, null],
            'Garlic' => [3000, null],
            'Latundan banana' => [200, null],
            'Macaroni' => [5000, null],
            'Fresh milk' => [8000, null],
            'Cooking oil' => [6000, null],
            'LPG (cooking gas)' => [33, null],
            'Paper meal box' => [600, null],
            'Roll bag (garbage)' => [100, null],
            'Dishwashing liquid' => [4000, null],
            'Disposable spoon' => [800, null],
            'Plastic cup' => [50, null],
        ];

        foreach (FsItem::all() as $item) {
            [$qty, $notes] = $stock[$item->name] ?? [$this->defaultQty($item->base_unit), null];
            Inventory::create([
                'item_type'         => $item->kind,
                'fs_item_id'        => $item->id,
                'quantity_in_stock' => $qty,
                'unit'              => $item->base_unit,
                'unit_price'        => $item->purchase_price,
                'notes'             => $notes,
            ]);
        }

        foreach (['Sopas' => 40, 'Pork Pinakbet' => 25] as $rname => $qty) {
            if (isset($this->recipes[$rname])) {
                Inventory::create([
                    'item_type' => 'recipe', 'recipe_id' => $this->recipes[$rname]->id,
                    'quantity_in_stock' => $qty, 'unit' => 'servings',
                ]);
            }
        }
    }

    private function defaultQty(string $baseUnit): float
    {
        return match ($baseUnit) {
            'g', 'mL' => 5000,
            'kg'      => 20,
            'pc'      => 100,
            default   => 50,
        };
    }

    // ── One weekly menu cycle for the given week ────────────────────────────
    private function seedCycleForWeek(int $rnd, Carbon $weekStart, bool $isCurrent, ?string $statusOverride = null, int $weekIndex = 0): MenuCycle
    {
        $status = $statusOverride ?? ($isCurrent ? 'active' : 'archived');
        $cycle = MenuCycle::create([
            'rnd_user_id'     => $rnd,
            'name'            => 'Subsistence Cycle — Week of ' . $weekStart->format('M j'),
            'cycle_days'      => 7,
            'is_active'       => $isCurrent,
            'status'          => $status,
            'week_start_date' => $weekStart->toDateString(),
            'activation_date' => $status === 'draft' ? null : $weekStart->toDateString(),
        ]);

        $plan      = $this->planForWeek($weekIndex);
        $popFactor = $this->popFactor($weekIndex);
        $slots = ['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner'];
        foreach ($plan as $day => $items) {
            foreach ($slots as $i => $slot) {
                $name = $items[$i];
                $row = [
                    'menu_cycle_id' => $cycle->id, 'day_of_week' => $day, 'meal_type' => $slot,
                    'estimate_population' => (int) round($this->dayPop[$day] * $popFactor),
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
            'cost_snapshot'    => MenuCycleCostService::forCycle($cycle),
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
        $cost      = MenuCycleCostService::forCycle($cycle);
        $today     = Carbon::now();
        $popFactor = $this->popFactor($weekIndex);
        $totalServed = 0;

        for ($i = 0; $i < 7; $i++) {
            $date    = $weekStart->copy()->addDays($i);
            $weekday = $date->format('l');
            if ($isCurrent && $date->gt($today)) {
                break; // current week: don't serve the future
            }

            $planned  = (int) round($this->dayPop[$weekday] * $popFactor);
            $variance = (($weekIndex + 2) * ($i + 3)) % 13;
            $served   = max(0, $planned - $variance);
            $dayCost  = (float) ($cost['days'][$weekday]['cost'] ?? 0);
            $totalServed += $served;

            MealPrepLog::create([
                'menu_cycle_id'       => $cycle->id,
                'service_date'        => $date->toDateString(),
                'population'          => $planned,
                'served_population'   => $served,
                'population_variance' => $planned - $served,
                'status'              => 'completed',
                'completed_by'        => $fss,
                'completed_at'        => $date->copy()->setTime(13, 0),
                'total_value'         => round($dayCost, 2),
                'has_shortfall'       => false,
            ]);

            // Diet-list counts (accomplishment report) — a couple of wards per day.
            foreach (['Ward A' => 0.45, 'Ward B' => 0.35, 'ICU' => 0.20] as $ward => $share) {
                DietListCount::create([
                    'service_date'           => $date->toDateString(),
                    'menu_cycle_id'          => $cycle->id,
                    'ward'                   => $ward,
                    'fss_user_id'            => $fss,
                    'population'             => (int) round($served * $share),
                    'helped_food_prep'       => true,
                    'collected_diet_list'    => true,
                    'apportioned_food'       => true,
                    'cleaned_utensils'       => $ward !== 'ICU',
                    'maintained_cleanliness' => true,
                ]);
            }
        }

        return $totalServed;
    }

    // ── Suggested shopping list + received POs split by vendor ──────────────
    private function seedProcurementForWeek(MenuCycle $cycle, int $fss, Carbon $weekStart, bool $isCurrent, int $totalServed): void
    {
        $list = ShoppingList::create([
            'rnd_user_id'  => $fss,
            'name'         => 'Marketing — week of ' . $weekStart->format('M j'),
            'list_date'    => $weekStart->toDateString(),
            'period_start' => $weekStart->toDateString(),
            'period_end'   => $weekStart->copy()->addDays(6)->toDateString(),
            'days_span'    => 7,
            'coverage_status' => 'full',
            // Past weeks: the census headcount is in → actual budget-per-head computes.
            // Current week is still running → left null so "pending" is demonstrable.
            'total_served_population' => $isCurrent ? null : $totalServed,
            'list_type'    => 'suggested',
            'status'       => $isCurrent ? 'draft' : 'finalized',
        ]);

        // [vendor, or_no, [ [fs_item name, qty, unit, unit_price], ... ] ]
        $orders = [
            ['MACMA Trading', [
                ['Pork (kasim)', 25, 'kg', 280], ['Ground pork', 12, 'kg', 290],
                ['Chicken (whole)', 20, 'kg', 200], ['Chicken fillet', 15, 'kg', 260],
                ['Beef (cubes)', 10, 'kg', 360], ['Bangus (milkfish)', 18, 'kg', 180],
            ]],
            ['Gloria T.M. General Merchandise', [
                ['Pinakbet vegetables', 20, 'kg', 90], ['Assorted vegetables', 15, 'kg', 80],
                ['Potato', 12, 'kg', 90], ['Carrot', 8, 'kg', 90], ['Onion', 10, 'kg', 120],
                ['Garlic', 5, 'kg', 140], ['Tomato', 6, 'kg', 80], ['Latundan banana', 200, 'pc', 5],
            ]],
            ['RPDH-MPC', [
                ['Cooking oil', 10, 'L', 75], ['Soy sauce', 6, 'L', 60], ['Vinegar', 6, 'L', 45],
                ['Macaroni', 15, 'kg', 85], ['Fresh milk', 12, 'L', 90], ['Egg', 10, 'tray', 240],
                ['Cheez Whiz', 12, 'jar', 85], ['Loaf bread', 20, 'pack', 65],
            ]],
        ];

        // Current week: one PO still "ordered" (not received) so the all-received gate
        // is visible; past weeks all received.
        $orderDate = $weekStart->copy();
        $weekTag   = $weekStart->format('mdy');
        $seq = 1;
        foreach ($orders as $idx => [$vendorName, $items]) {
            $vendor = $this->suppliers[$vendorName] ?? null;
            // Scale every line to realistic weekly volume for the served headcount.
            $items  = array_map(fn ($i) => [$i[0], round($i[1] * self::PROCUREMENT_SCALE), $i[2], $i[3]], $items);
            $total  = array_sum(array_map(fn ($i) => $i[1] * $i[3], $items));
            $status = ($isCurrent && $idx === count($orders) - 1) ? 'ordered' : 'received';

            $po = PurchaseOrder::create([
                'rnd_user_id'  => $fss, 'shopping_list_id' => $list->id, 'supplier_id' => $vendor?->id,
                'po_number'    => 'PO-' . $weekTag . '-' . str_pad((string) $seq++, 2, '0', STR_PAD_LEFT),
                'or_number'    => $status === 'received' ? 'OR-' . $weekTag . '-' . $idx : null,
                'order_date'   => $orderDate->toDateString(),
                'received_date' => $status === 'received' ? $orderDate->copy()->addDay()->toDateString() : null,
                'total_amount' => round($total, 2), 'status' => $status,
                'notes'        => $vendor?->category,
            ]);

            foreach ($items as [$itemName, $qty, $unit, $price]) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id, 'fs_item_id' => $this->id($itemName),
                    'description' => $itemName, 'qty' => $qty, 'unit' => $unit,
                    'unit_price' => $price, 'total_value' => round($qty * $price, 2),
                ]);
                ShoppingListItem::create([
                    'shopping_list_id' => $list->id, 'fs_item_id' => $this->id($itemName),
                    'ingredient_name' => $itemName, 'qty' => $qty, 'unit' => $unit,
                    'supplier_id' => $vendor?->id, 'unit_price' => $price, 'total' => round($qty * $price, 2),
                ]);
            }
        }
    }

    // ── Monthly budget covering the whole demo period ───────────────────────
    private function seedBudget(int $fss, MenuCycle $currentCycle): void
    {
        $cost          = MenuCycleCostService::forCycle($currentCycle);
        $dayCosts      = array_values(array_map(fn ($d) => $d['cost'], $cost['days']));
        $avgPopulation = (int) round($currentCycle->days->whereNotNull('estimate_population')->avg('estimate_population') ?? 0);
        $avgDay        = $dayCosts ? array_sum($dayCosts) / count($dayCosts) : ($avgPopulation * 110);

        $start = Carbon::now()->startOfMonth();
        $end   = Carbon::now()->endOfMonth();

        $perHeadCap = 150;
        $budget = Budget::create([
            'rnd_user_id' => $fss, 'scope' => 'monthly', 'name' => $start->format('F Y') . ' Food Subsistence',
            'menu_cycle_id' => $currentCycle->id,
            'allocated_amount' => round($avgDay * 30, -2), 'population' => $avgPopulation,
            'cost_per_person' => $perHeadCap, 'budget_per_head_day' => $perHeadCap,
            'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
        ]);

        // Sample allocation-adjustment ledger (approved top-up + a correction) so the
        // add/deduct audit trail is visible in both the budget page and the report.
        foreach ([
            ['addition', 25000, 'Request for additional funds', null],
            ['deduction', 4000, 'Budget correction', null],
        ] as [$adjType, $adjAmount, $adjCat, $adjReason]) {
            \App\Models\BudgetAdjustment::create([
                'budget_id' => $budget->id, 'type' => $adjType, 'amount' => $adjAmount,
                'reason_category' => $adjCat, 'reason' => $adjReason, 'created_by' => $fss,
            ]);
        }

        for ($d = $start->copy(); $d->lte(Carbon::now()); $d->addDay()) {
            BudgetDailyLog::create([
                'budget_id' => $budget->id,
                'log_date'  => $d->toDateString(),
                'spent'     => round($avgDay * (mt_rand(88, 109) / 100), 2),
            ]);
        }
    }
}
