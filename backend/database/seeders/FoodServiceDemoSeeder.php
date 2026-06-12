<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\BudgetDailyLog;
use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Models\FsItem;
use App\Models\Inventory;
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
 * RPDH PPA menu so every report tab has something to generate:
 *   suppliers → FS recipes (cost-only) → inventory stock → an active menu cycle
 *   → a monthly budget with daily logs → a suggested shopping list → received POs.
 *
 * Reads the live `fs_items` catalog (seeded by FsCatalogSeeder) by name. Builds on
 * the current schema — fs_item_id / item_type(ingredient|supply|recipe), lowercase
 * meal_type enum, or_number on POs, etc. — NOT the pre-decouple food_item_id shape.
 *
 * Idempotent: truncates only the operational FS tables (never fs_items / food_items
 * / recipes / patients).
 */
class FoodServiceDemoSeeder extends Seeder
{
    private array $fs = [];      // fs_item name => id
    private array $recipes = []; // recipe name => FoodServiceRecipe
    private array $suppliers = [];

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
        $cycle = $this->seedMenuCycle($rnd);
        $this->seedBudget($fss, $cycle);
        $this->seedProcurement($fss, $cycle);

        $this->command->info('FoodServiceDemoSeeder: FS demo data seeded.');
    }

    private function reset(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ([
            'purchase_order_attachments', 'purchase_order_items', 'purchase_orders',
            'shopping_list_items', 'shopping_lists',
            'budget_daily_logs', 'budgets',
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
        // name => [category, servings, [[fs_item name, qty in base_unit], ...]]
        $defs = [
            'Cheezwhiz Sandwich'             => ['Sandwich', 50, [['Loaf bread', 100], ['Cheez Whiz', 750]]],
            'Pandesal with Boiled Egg'       => ['Breakfast', 50, [['Pandesal', 100], ['Egg', 50]]],
            'Sopas'                          => ['Soup', 50, [['Macaroni', 2500], ['Chicken (whole)', 3000], ['Carrot', 1000], ['Assorted vegetables', 1500], ['Fresh milk', 2000]]],
            'Mami Noodle Soup'               => ['Soup', 50, [['Macaroni', 3000], ['Chicken (whole)', 3000], ['Garlic', 200], ['Onion', 300]]],
            'Pork Pinakbet'                  => ['Main', 50, [['Pork (kasim)', 3000], ['Pinakbet vegetables', 5000], ['Garlic', 200], ['Onion', 400], ['Tomato', 600]]],
            'Pork Picadillo'                 => ['Main', 50, [['Ground pork', 3000], ['Potato', 2500], ['Carrot', 1500], ['Garlic', 200], ['Onion', 400]]],
            'Pork Strips Oriental with Corn' => ['Main', 50, [['Pork (kasim)', 3000], ['Corn kernel', 2000], ['Soy sauce', 500], ['Garlic', 200]]],
            'Beef Caldereta'                 => ['Main', 50, [['Beef (cubes)', 3500], ['Potato', 2500], ['Carrot', 1500], ['Tomato', 800], ['Garlic', 200], ['Onion', 400]]],
            'Chicken Fillet w/ Mushroom Sauce' => ['Main', 50, [['Chicken fillet', 4000], ['Mushroom (canned)', 1600], ['Fresh milk', 2000], ['Onion', 400]]],
            'Chicken Sisig'                  => ['Main', 50, [['Chicken fillet', 4000], ['Onion', 600], ['Soy sauce', 400], ['Cooking oil', 500]]],
            'Chicken with Lemongrass'        => ['Main', 50, [['Chicken (whole)', 5000], ['Ginger', 300], ['Garlic', 250], ['Salt', 150]]],
            'Paksiw na Bangus'               => ['Main', 50, [['Bangus (milkfish)', 5000], ['Vinegar', 800], ['Garlic', 250], ['Ginger', 300]]],
        ];

        foreach ($defs as $name => [$category, $servings, $lines]) {
            $recipe = FoodServiceRecipe::create([
                'rnd_user_id' => $rnd, 'name' => $name, 'category' => $category, 'servings' => $servings,
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
        $today = Carbon::today();
        // [fs_item name, qty in base_unit, threshold, expiry offset days|null, notes]
        $stock = [
            ['Rice', 80000, 20000, 60, null],
            ['Pork (kasim)', 12000, 5000, 4, null],
            ['Ground pork', 3000, 5000, 3, 'Low — reorder'],
            ['Chicken (whole)', 18000, 6000, 5, null],
            ['Chicken fillet', 2000, 5000, 4, 'Low stock'],
            ['Beef (cubes)', 0, 4000, null, 'Out of stock'],
            ['Bangus (milkfish)', 9000, 4000, 2, 'Use soon'],
            ['Egg', 600, 200, 14, null],
            ['Assorted vegetables', 15000, 5000, 5, null],
            ['Pinakbet vegetables', 8000, 4000, 3, null],
            ['Potato', 10000, 3000, 20, null],
            ['Carrot', 7000, 2000, 20, null],
            ['Onion', 6000, 2000, 30, null],
            ['Garlic', 3000, 1000, 30, null],
            ['Latundan banana', 200, 80, 5, null],
            ['Macaroni', 5000, 2000, 120, null],
            ['Fresh milk', 8000, 3000, 7, null],
            ['Cooking oil', 6000, 2000, 120, null],
        ];
        foreach ($stock as [$name, $qty, $min, $exp, $notes]) {
            $fsId = $this->id($name);
            if (! $fsId) continue;
            $item = FsItem::find($fsId);
            Inventory::create([
                'item_type' => 'ingredient', 'fs_item_id' => $fsId,
                'quantity_in_stock' => $qty, 'unit' => $item->base_unit,
                'minimum_stock_threshold' => $min, 'unit_price' => $item->purchase_price,
                'notes' => $notes,
            ]);
        }

        $supplies = [
            ['LPG (cooking gas)', 33, 11, null],            // kg in stock
            ['Paper meal box', 600, 100, null],
            ['Roll bag (garbage)', 100, 50, 'Low'],
            ['Dishwashing liquid', 4000, 1000, null],
            ['Disposable spoon', 800, 200, null],
            ['Plastic cup', 50, 100, 'Reorder'],
        ];
        foreach ($supplies as [$name, $qty, $min, $notes]) {
            $fsId = $this->id($name);
            if (! $fsId) continue;
            $item = FsItem::find($fsId);
            Inventory::create([
                'item_type' => 'supply', 'fs_item_id' => $fsId,
                'quantity_in_stock' => $qty, 'unit' => $item->base_unit,
                'minimum_stock_threshold' => $min, 'unit_price' => $item->purchase_price, 'notes' => $notes,
            ]);
        }

        // A couple of prepared-recipe stock rows.
        foreach (['Sopas' => [40, 10], 'Pork Pinakbet' => [25, 10]] as $rname => [$qty, $min]) {
            if (isset($this->recipes[$rname])) {
                Inventory::create([
                    'item_type' => 'recipe', 'recipe_id' => $this->recipes[$rname]->id,
                    'quantity_in_stock' => $qty, 'unit' => 'servings', 'minimum_stock_threshold' => $min,
                ]);
            }
        }
    }

    // ── Active weekly menu cycle ────────────────────────────────────────────
    private function seedMenuCycle(int $rnd): MenuCycle
    {
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $cycle = MenuCycle::create([
            'rnd_user_id' => $rnd, 'name' => 'June Subsistence Cycle — Week 1',
            'population' => 549, 'budget_per_head_per_day' => 130, 'cycle_days' => 7,
            'is_active' => true, 'status' => 'active',
            'week_start_date' => $weekStart->toDateString(), 'activation_date' => $weekStart->toDateString(),
        ]);

        // Per weekday: breakfast/lunch/dinner = recipes; am_snack/pm_snack = ready fs_items.
        $plan = [
            'Monday'    => ['Cheezwhiz Sandwich', 'Yakult', 'Pork Pinakbet', 'Latundan banana', 'Paksiw na Bangus'],
            'Tuesday'   => ['Sopas', 'Coffee', 'Pork Picadillo', 'Latundan banana', 'Chicken Fillet w/ Mushroom Sauce'],
            'Wednesday' => ['Mami Noodle Soup', 'Fresh milk', 'Pork Strips Oriental with Corn', 'Saba banana', 'Chicken Sisig'],
            'Thursday'  => ['Pandesal with Boiled Egg', 'Milo', 'Beef Caldereta', 'Saba banana', 'Chicken with Lemongrass'],
            'Friday'    => ['Cheezwhiz Sandwich', 'Yakult', 'Pork Picadillo', 'Ponkan', 'Paksiw na Bangus'],
            'Saturday'  => ['Sopas', 'Coffee', 'Beef Caldereta', 'Brownie bite', 'Chicken Sisig'],
            'Sunday'    => ['Pandesal with Boiled Egg', 'Milo', 'Pork Pinakbet', 'Chooey toffee', 'Chicken Fillet w/ Mushroom Sauce'],
        ];
        $slots = ['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner'];

        foreach ($plan as $day => $items) {
            foreach ($slots as $i => $slot) {
                $name = $items[$i];
                $row = ['menu_cycle_id' => $cycle->id, 'day_of_week' => $day, 'meal_type' => $slot];
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

        return $cycle->fresh();
    }

    // ── Monthly budget + daily logs (planned from the cycle) ────────────────
    private function seedBudget(int $fss, MenuCycle $cycle): void
    {
        $cost      = MenuCycleCostService::forCycle($cycle);
        $dayCosts  = array_values(array_map(fn ($d) => $d['cost'], $cost['days']));
        $avgDay    = $dayCosts ? array_sum($dayCosts) / count($dayCosts) : ($cycle->population * 110);

        $start = Carbon::now()->startOfMonth();
        $end   = Carbon::now()->endOfMonth();

        $budget = Budget::create([
            'fss_user_id' => $fss, 'scope' => 'monthly', 'name' => $start->format('F Y') . ' Food Subsistence',
            'allocated_amount' => round($avgDay * 30, -2), 'population' => $cycle->population,
            'cost_per_person' => $cycle->budget_per_head_per_day, 'budget_per_head_day' => $cycle->budget_per_head_per_day,
            'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
        ]);

        // Log every day from the 1st up to today.
        for ($d = $start->copy(); $d->lte(Carbon::now()); $d->addDay()) {
            $planned = round($avgDay, 2);
            $actual  = round($avgDay * (mt_rand(88, 109) / 100), 2);
            BudgetDailyLog::create([
                'budget_id' => $budget->id, 'date' => $d->toDateString(),
                'planned' => $planned, 'actual' => $actual, 'variance' => round($actual - $planned, 2),
            ]);
        }
    }

    // ── Suggested shopping list + received POs split by vendor ──────────────
    private function seedProcurement(int $fss, MenuCycle $cycle): void
    {
        $orderDate = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $list = ShoppingList::create([
            'fss_user_id' => $fss, 'menu_cycle_id' => $cycle->id,
            'name' => 'Marketing — ' . $orderDate->format('M j') . ' (Tue→Fri)',
            'list_date' => $orderDate->toDateString(),
            'period_start' => $orderDate->toDateString(), 'period_end' => $orderDate->copy()->addDays(3)->toDateString(),
            'days_span' => 3, 'list_type' => 'suggested', 'status' => 'finalized',
        ]);

        // [vendor, or_no, [ [fs_item name, qty, unit, unit_price], ... ] ]
        $orders = [
            ['MACMA Trading', 'OR-2026-0512', [
                ['Pork (kasim)', 25, 'kg', 280], ['Ground pork', 12, 'kg', 290],
                ['Chicken (whole)', 20, 'kg', 200], ['Chicken fillet', 15, 'kg', 260],
                ['Beef (cubes)', 10, 'kg', 360], ['Bangus (milkfish)', 18, 'kg', 180],
            ]],
            ['Gloria T.M. General Merchandise', 'OR-2026-0513', [
                ['Pinakbet vegetables', 20, 'kg', 90], ['Assorted vegetables', 15, 'kg', 80],
                ['Potato', 12, 'kg', 90], ['Carrot', 8, 'kg', 90], ['Onion', 10, 'kg', 120],
                ['Garlic', 5, 'kg', 140], ['Tomato', 6, 'kg', 80], ['Latundan banana', 200, 'pc', 5],
            ]],
            ['RPDH-MPC', 'OR-2026-0514', [
                ['Cooking oil', 10, 'L', 75], ['Soy sauce', 6, 'L', 60], ['Vinegar', 6, 'L', 45],
                ['Macaroni', 15, 'kg', 85], ['Fresh milk', 12, 'L', 90], ['Egg', 10, 'tray', 240],
                ['Cheez Whiz', 12, 'jar', 85], ['Loaf bread', 20, 'pack', 65],
            ]],
        ];

        $poSeq = 1;
        foreach ($orders as [$vendorName, $orNo, $items]) {
            $vendor = $this->suppliers[$vendorName] ?? null;
            $total  = array_sum(array_map(fn ($i) => $i[1] * $i[3], $items));

            $po = PurchaseOrder::create([
                'fss_user_id' => $fss, 'shopping_list_id' => $list->id, 'supplier_id' => $vendor?->id,
                'po_number' => 'PO-2026-' . str_pad((string) $poSeq++, 4, '0', STR_PAD_LEFT),
                'or_number' => $orNo, 'order_date' => $orderDate->toDateString(),
                'total_amount' => round($total, 2), 'status' => 'received',
                'notes' => $vendor?->category,
            ]);

            foreach ($items as [$itemName, $qty, $unit, $price]) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id, 'fs_item_id' => $this->id($itemName),
                    'description' => $itemName, 'qty' => $qty, 'unit' => $unit,
                    'unit_price' => $price, 'total_value' => round($qty * $price, 2),
                ]);
                // mirror onto the shopping list for the records view
                ShoppingListItem::create([
                    'shopping_list_id' => $list->id, 'fs_item_id' => $this->id($itemName),
                    'ingredient_name' => $itemName, 'qty' => $qty, 'unit' => $unit,
                    'supplier_id' => $vendor?->id, 'unit_price' => $price, 'total' => round($qty * $price, 2),
                ]);
            }
        }
    }
}
