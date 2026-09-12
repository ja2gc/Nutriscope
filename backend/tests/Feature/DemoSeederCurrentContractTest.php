<?php

namespace Tests\Feature;

use App\Models\AiUsageLimit;
use App\Models\Announcement;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\ClinicalRule;
use App\Models\FoodItem;
use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceSetting;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleTemplate;
use App\Models\NcpAppointment;
use App\Models\NcpRecord;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\PurchaseOrder;
use App\Models\Recipe;
use App\Models\ReportTemplate;
use App\Models\ShoppingList;
use App\Models\Sop;
use App\Models\StoredObject;
use App\Models\Supplier;
use App\Models\User;
use App\Services\FSS\PurchaseOrderLifecycleService;
use App\Services\FSS\ShoppingListPopulationService;
use Carbon\CarbonImmutable;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\AiUsageLimitSeeder;
use Database\Seeders\AnnouncementSeeder;
use Database\Seeders\ClinicalRulesSeeder;
use Database\Seeders\FoodServiceDemoSeeder;
use Database\Seeders\FoodServiceMenuTemplateSeeder;
use Database\Seeders\FsCatalogSeeder;
use Database\Seeders\NotificationSeeder;
use Database\Seeders\PatientSeeder;
use Database\Seeders\RecipeSeeder;
use Database\Seeders\ReportTemplateSeeder;
use Database\Seeders\SopSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DemoSeederCurrentContractTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_recipe_seeder_preserves_unrelated_recipes_and_refreshes_named_demo_data(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seedClinicalFoodFixtures();

        $rnd = User::query()->where('role', 'RND')->firstOrFail();
        $food = FoodItem::query()->where('name', 'Steamed White Rice')->firstOrFail();
        $custom = Recipe::factory()->for($rnd, 'rnd')->create(['name' => 'Ward Custom Recipe']);
        $custom->ingredients()->create([
            'food_item_id' => $food->id,
            'quantity' => 75,
            'unit' => 'g',
        ]);

        $this->seed(RecipeSeeder::class);
        $seeded = Recipe::query()->where('name', 'Plain White Rice Meal')->firstOrFail();
        $seeded->update(['category' => 'STALE CATEGORY']);
        $firstCount = Recipe::query()->count();

        $this->seed(RecipeSeeder::class);

        $this->assertTrue(Recipe::query()->whereKey($custom->id)->where('name', 'Ward Custom Recipe')->exists());
        $this->assertSame($firstCount, Recipe::query()->count());
        $this->assertSame(1, Recipe::query()->where('name', 'Plain White Rice Meal')->count());
        $this->assertSame('Staple', $seeded->fresh()->category);
        $this->assertSame(1, $seeded->fresh()->ingredients()->count());
    }

    public function test_recipe_seeder_fails_before_writing_partial_recipes(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seedClinicalFoodFixtures();
        FoodItem::query()->where('name', 'Steamed White Rice')->delete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Recipe seeding requires every referenced food item.');

        try {
            $this->seed(RecipeSeeder::class);
        } finally {
            $this->assertDatabaseMissing('recipes', ['name' => 'Plain White Rice Meal']);
        }
    }

    public function test_patient_and_meal_plan_demo_graph_matches_current_contract(): void
    {
        CarbonImmutable::setTestNow('2026-09-09 12:00:00');
        $this->seed(AdminUserSeeder::class);
        $this->seedClinicalFoodFixtures();
        $this->seed(RecipeSeeder::class);
        $this->seed(PatientSeeder::class);

        $patients = Patient::query()
            ->whereIn('hospital_number', ['HN-2026-0042', 'HN-2026-0078'])
            ->with(['ncpRecords.assessment', 'ncpRecords.diagnoses', 'ncpRecords.intervention.mealPlans.days.items'])
            ->get();

        $this->assertCount(2, $patients);
        foreach ($patients as $patient) {
            $this->assertNotSame('', trim((string) $patient->first_name));
            $this->assertNotSame('', trim((string) $patient->last_name));
            $this->assertSame($patient->first_name.' '.$patient->last_name, $patient->display_name);
            $this->assertContains($patient->sex, ['Male', 'Female']);
            $this->assertSame('Active', $patient->status);
            $this->assertSame('adult', $patient->screening_type);
            foreach ($patient->ncpRecords as $record) {
                $this->assertContains($record->type, ['new', 'continuing']);
                $this->assertContains($record->status, ['draft', 'active', 'completed', 'discontinued', 'discharged']);
                $this->assertNotNull($record->assessment);
                $this->assertNotNull($record->intervention);
                $this->assertNotEmpty($record->diagnoses);
                $this->assertNotEmpty($record->intervention->mealPlans);

                foreach ($record->intervention->mealPlans as $mealPlan) {
                    $this->assertContains($mealPlan->status, ['draft', 'active']);
                    $this->assertCount(35, $mealPlan->days);
                    $this->assertTrue(
                        $mealPlan->days->flatMap->items->every(
                            fn ($item) => $item->created_at->isSameDay($mealPlan->created_at),
                        ),
                        'Generated meal-plan item timestamps must match their historical plan.',
                    );
                }
            }
        }

        $maria = $patients->firstWhere('hospital_number', 'HN-2026-0042');
        $this->assertCount(1, $maria->ncpRecords);
        $this->assertTrue($maria->ncpRecords->sole()->created_at->isSameDay(now()->subDays(90)));
        $this->assertTrue(
            $maria->ncpRecords->sole()->intervention->mealPlans->sole()->week_start_date->isSameDay(
                now()->subDays(83)->addWeek()->startOfWeek(),
            ),
        );
        $this->assertSame('Normal', $maria->ncpRecords->sole()->assessment->nutritional_status);
        $this->assertSame(1.0, (float) $maria->ncpRecords->sole()->risk_score);
        $this->assertGreaterThan(25, (float) $maria->ncpRecords->sole()->assessment->bmi);
        $this->assertLessThan(27, (float) $maria->ncpRecords->sole()->assessment->bmi);

        $roberto = $patients->firstWhere('hospital_number', 'HN-2026-0078');
        $this->assertCount(2, $roberto->ncpRecords);
        $this->assertCount(1, $roberto->ncpRecords->where('status', 'completed'));
        $this->assertCount(1, $roberto->ncpRecords->where('status', 'active'));
        $this->assertNotEmpty($roberto->ncpRecords->firstWhere('status', 'completed')->monitorings);
        $robertoCurrent = $roberto->ncpRecords->firstWhere('status', 'active');
        $robertoPast = $roberto->ncpRecords->firstWhere('status', 'completed');
        $this->assertTrue($robertoCurrent->created_at->isSameDay(now()->subDays(30)));
        $this->assertTrue($robertoCurrent->intervention->mealPlans->sole()->created_at->isSameDay(now()->subDays(30)));
        $this->assertTrue($robertoPast->intervention->mealPlans->sole()->created_at->lt(
            $robertoCurrent->intervention->mealPlans->sole()->created_at,
        ));

        $mariaAppointments = NcpAppointment::query()->whereBelongsTo($maria)->get();
        $this->assertTrue($mariaAppointments->contains(fn ($visit) => $visit->status === 'completed'
            && $visit->source === 'scheduled' && $visit->worked_on === ['assessment'] && $visit->newly_completed === ['assessment']));
        $this->assertTrue($mariaAppointments->contains(fn ($visit) => $visit->status === 'completed'
            && $visit->worked_on === ['diagnosis', 'intervention'] && $visit->newly_completed === ['diagnosis', 'intervention']));
        $this->assertTrue($mariaAppointments->contains(fn ($visit) => $visit->status === 'cancelled'
            && $visit->reason_code !== null));
        $this->assertTrue($mariaAppointments->contains(fn ($visit) => $visit->status === 'completed'
            && $visit->source === 'walk_in' && $visit->worked_on === ['monitoring']));
        $this->assertTrue($mariaAppointments->contains(fn ($visit) => $visit->status === 'scheduled'
            && $visit->purpose === 'Monitoring follow-up' && $visit->ncp_record_id === null
            && $visit->scheduled_at->isSameDay(now()->addDays(30))));
        $this->assertTrue($mariaAppointments->contains(fn ($visit) => $visit->status === 'scheduled'
            && $visit->scheduled_at->isSameDay(now()->addDay())));

        $robertoAppointments = NcpAppointment::query()->whereBelongsTo($roberto)->get();
        $this->assertTrue($robertoAppointments->where('status', 'completed')->contains(
            fn ($visit) => $visit->ncp_record_id === $roberto->ncpRecords->firstWhere('status', 'completed')->id
        ));
        $this->assertTrue($robertoAppointments->where('status', 'completed')->contains(
            fn ($visit) => $visit->ncp_record_id === $roberto->ncpRecords->firstWhere('status', 'active')->id
        ));
        $this->assertTrue($robertoAppointments->contains(fn ($visit) => $visit->status === 'scheduled'
            && $visit->purpose === 'Monitoring follow-up' && $visit->ncp_record_id === null
            && $visit->scheduled_at->isSameDay(now()->addDays(50))));

        $currentCycles = NcpRecord::query()->whereIn('status', ['draft', 'active'])->get()->groupBy('patient_id');
        $this->assertTrue($currentCycles->every(fn ($cycles) => $cycles->count() === 1));
    }

    public function test_patient_seeder_removes_only_stale_notifications_for_replaced_demo_appointments(): void
    {
        CarbonImmutable::setTestNow('2026-09-09 12:00:00');
        $this->seed(AdminUserSeeder::class);
        $this->seedClinicalFoodFixtures();
        $this->seed(RecipeSeeder::class);
        $this->seed(PatientSeeder::class);

        $demoAppointment = NcpAppointment::query()
            ->whereHas('patient', fn ($query) => $query->where('hospital_number', 'HN-2026-0042'))
            ->where('status', 'scheduled')
            ->firstOrFail();
        $stale = Notification::factory()->create([
            'user_id' => $demoAppointment->rnd_user_id,
            'type' => 'appointment_due',
            'source_module' => 'ncp_appointment',
            'source_id' => $demoAppointment->id,
        ]);
        $alreadyOrphaned = Notification::factory()->create([
            'user_id' => $demoAppointment->rnd_user_id,
            'type' => 'appointment_due',
            'source_module' => 'ncp_appointment',
            'source_id' => 999_999_999,
        ]);

        $unrelatedAppointment = NcpAppointment::factory()->create();
        $unrelated = Notification::factory()->create([
            'user_id' => $unrelatedAppointment->rnd_user_id,
            'type' => 'appointment_due',
            'source_module' => 'ncp_appointment',
            'source_id' => $unrelatedAppointment->id,
        ]);

        $this->seed(PatientSeeder::class);

        $this->assertDatabaseMissing('notifications', ['id' => $stale->id]);
        $this->assertDatabaseMissing('notifications', ['id' => $alreadyOrphaned->id]);
        $this->assertDatabaseHas('notifications', ['id' => $unrelated->id]);
    }

    public function test_food_service_demo_is_repeatable_and_uses_current_status_values(): void
    {
        CarbonImmutable::setTestNow('2026-07-16 12:00:00');
        $this->seed(AdminUserSeeder::class);
        $this->seed(FsCatalogSeeder::class);

        $this->seed(FoodServiceDemoSeeder::class);
        $this->seed(FoodServiceMenuTemplateSeeder::class);
        $first = $this->foodServiceCounts();
        $this->seed(FoodServiceDemoSeeder::class);
        $this->seed(FoodServiceMenuTemplateSeeder::class);
        $second = $this->foodServiceCounts();

        $this->assertSame($first, $second);
        $this->assertSame(3, $second['menu_cycles']);
        $this->assertSame(3, $second['menu_cycle_templates']);
        $this->assertGreaterThan(0, $second['recipes']);
        $this->assertGreaterThan(0, $second['suppliers']);
        $this->assertGreaterThan(0, $second['shopping_lists']);
        $this->assertGreaterThan(0, $second['purchase_orders']);
        $this->assertSame(1, $second['budgets']);

        $this->assertFalse(FoodServiceRecipe::query()->where('name', 'Cooked Rice')->exists());
        foreach (MenuCycleTemplate::query()->with('days.recipe')->get() as $template) {
            $this->assertSame(35, $template->days->count());
            $this->assertSame(0, $template->days->where('line_order', '>', 1)->count());
        }

        $this->assertEmpty(MenuCycle::query()->whereNotIn('status', ['completed', 'active', 'upcoming'])->pluck('status'));
        $this->assertEmpty(ShoppingList::query()->whereNotIn('status', ['draft', 'converted'])->pluck('status'));
        $this->assertEmpty(PurchaseOrder::query()->whereNotIn('status', ['draft', 'ordered', 'received'])->pluck('status'));
        $this->assertEmpty(PurchaseOrder::query()->whereNotIn('lifecycle_status', ['open_execution', 'completed', 'archived'])->pluck('lifecycle_status'));
        $this->assertEmpty(BudgetLedger::query()->whereNotIn('type', ['po_deduction', 'manual_addition', 'manual_deduction'])->pluck('type'));
        $this->assertEmpty(BudgetLedger::query()->whereNotIn('source', ['system', 'manual'])->pluck('source'));

        $past = MenuCycle::query()->where('status', 'completed')->sole();
        $this->assertGreaterThan(0, (int) data_get($past->cost_snapshot, 'population'));
        $this->assertGreaterThan(0, (float) data_get($past->cost_snapshot, 'total_cost'));
        $limit = (float) FoodServiceSetting::singleton()->per_head_day_limit;
        $this->assertSame(150.0, $limit);
        $this->assertLessThanOrEqual($limit, (float) data_get($past->cost_snapshot, 'cost_per_head'));
        foreach ($past->days()->pluck('estimate_population')->unique() as $population) {
            $this->assertGreaterThanOrEqual(150, (int) $population);
            $this->assertLessThanOrEqual(200, (int) $population);
        }
        foreach (MealPrepLog::query()->where('menu_cycle_id', $past->id)->get() as $log) {
            $this->assertGreaterThanOrEqual(150, (int) $log->population);
            $this->assertLessThanOrEqual(200, (int) $log->population);
            $this->assertGreaterThanOrEqual(150, (int) $log->served_population);
            $this->assertLessThanOrEqual(200, (int) $log->served_population);
        }
        $this->assertStringContainsString(
            "number_format(\$cost['population'])",
            file_get_contents(resource_path('views/reports/menu-calendar.blade.php')),
        );
        $weekStart = CarbonImmutable::parse($past->week_start_date);
        $lists = ShoppingList::query()
            ->whereIn('period_start', [
                $weekStart->addDay()->toDateString(),
                $weekStart->addDays(4)->toDateString(),
            ])
            ->whereIn('period_end', [
                $weekStart->addDays(3)->toDateString(),
                $weekStart->addDays(7)->toDateString(),
            ])
            ->get();
        $this->assertCount(2, $lists);

        $historicalOrders = PurchaseOrder::query()
            ->whereIn('shopping_list_id', $lists->pluck('id'))
            ->with(['items', 'vendorGroups.attachments.storedObject', 'programProjectActivity'])
            ->get();
        $this->assertCount(2, $historicalOrders);
        foreach ($historicalOrders as $order) {
            $progress = app(PurchaseOrderLifecycleService::class)->servedPopulationProgress($order->shoppingList);
            $state = "{$order->po_number}; lifecycle={$order->lifecycle_status}; total={$order->total_amount}; budget=".Budget::query()->firstOrFail()->remainingBalance().'; population='.json_encode($progress).'; groups='.$order->vendorGroups->map(
                fn ($group) => $group->status.':'.$group->attachments->pluck('type')->implode(',')
            )->implode('|');
            $this->assertSame('received', $order->status, $state);
            $this->assertSame('completed', $order->lifecycle_status, $state);
            $this->assertNotNull($order->completed_at);
            $this->assertLessThanOrEqual($limit, (float) $order->actual_budget_per_head_per_day);
            $this->assertNotNull($order->programProjectActivity);
            $this->assertTrue($order->items->every(fn ($item) => $item->actual_qty !== null && $item->actual_unit_price !== null));
            foreach ($order->vendorGroups as $group) {
                $this->assertSame('received', $group->status);
                $this->assertNotNull($group->or_number);
                $receipt = $group->attachments->firstWhere('type', 'receipt');
                $proof = $group->attachments->firstWhere('type', 'proof');
                $this->assertNotNull($receipt?->storedObject);
                $this->assertSame($receipt?->stored_object_id, $proof?->stored_object_id);
                $this->assertTrue(Storage::disk($receipt->storedObject->storage_disk)->exists($receipt->storedObject->object_key));
            }
        }
        $this->assertCount(2, BudgetLedger::query()->whereIn('purchase_order_id', $historicalOrders->pluck('id'))->get());
    }

    public function test_food_service_demo_preserves_unrelated_operational_records(): void
    {
        CarbonImmutable::setTestNow('2026-07-16 12:00:00');
        $this->seed(AdminUserSeeder::class);
        $this->seed(FsCatalogSeeder::class);
        $rnd = User::query()->where('role', 'RND')->firstOrFail();

        $supplier = Supplier::factory()->create(['name' => 'Independent Community Supplier']);
        $recipe = FoodServiceRecipe::query()->create([
            'rnd_user_id' => $rnd->id,
            'name' => 'Independent Staff Recipe',
            'servings' => 10,
            'cost' => 0,
        ]);
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $rnd->id,
            'name' => 'Independent Staff Menu',
            'week_start_date' => now()->addWeeks(8)->startOfWeek(),
        ]);

        $this->seed(FoodServiceDemoSeeder::class);

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'Independent Community Supplier']);
        $this->assertDatabaseHas('food_service_recipes', ['id' => $recipe->id, 'name' => 'Independent Staff Recipe']);
        $this->assertDatabaseHas('menu_cycles', ['id' => $cycle->id, 'name' => 'Independent Staff Menu']);
    }

    public function test_food_service_demo_removes_the_new_private_receipt_if_seeding_fails(): void
    {
        CarbonImmutable::setTestNow('2026-07-16 12:00:00');
        Storage::fake((string) config('filesystems.private_uploads'));
        $this->seed(AdminUserSeeder::class);
        $this->seed(FsCatalogSeeder::class);
        $this->seed(FoodServiceDemoSeeder::class);

        $cycleIds = MenuCycle::query()->orderBy('id')->pluck('id')->all();
        $purchaseOrderIds = PurchaseOrder::query()->orderBy('id')->pluck('id')->all();
        $receipt = StoredObject::query()
            ->where('purpose', 'purchase_order')
            ->where('original_name', 'nutriscope-seeded-pexels-receipt-14647295.jpg')
            ->sole();
        $receiptFiles = Storage::disk((string) config('filesystems.private_uploads'))->allFiles('purchase_order');

        $planner = \Mockery::mock(ShoppingListPopulationService::class);
        $planner->shouldReceive('planRange')->once()->andThrow(new RuntimeException('Seed planner failure'));
        $this->app->instance(ShoppingListPopulationService::class, $planner);

        try {
            $this->seed(FoodServiceDemoSeeder::class);
            $this->fail('Expected the injected planner failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Seed planner failure', $exception->getMessage());
        }

        $this->assertSame($cycleIds, MenuCycle::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame($purchaseOrderIds, PurchaseOrder::query()->orderBy('id')->pluck('id')->all());
        $this->assertDatabaseHas('stored_objects', ['id' => $receipt->id]);
        $this->assertSame($receiptFiles, Storage::disk((string) config('filesystems.private_uploads'))->allFiles('purchase_order'));
    }

    public function test_remaining_base_seeders_are_repeatable_and_use_current_contract_values(): void
    {
        CarbonImmutable::setTestNow('2026-07-16 12:00:00');
        $this->seed(AdminUserSeeder::class);
        $seeders = [
            AiUsageLimitSeeder::class,
            ClinicalRulesSeeder::class,
            AnnouncementSeeder::class,
            NotificationSeeder::class,
            SopSeeder::class,
            ReportTemplateSeeder::class,
        ];

        foreach ($seeders as $seeder) {
            $this->seed($seeder);
        }
        $first = $this->remainingSeederCounts();
        ClinicalRule::query()
            ->where('condition', 'DM')
            ->where('stage', 'all')
            ->where('nutrient_or_food_tag', 'fiber')
            ->where('rule_type', 'recommend')
            ->update(['threshold' => -1, 'reason' => 'STALE RULE']);
        foreach ($seeders as $seeder) {
            $this->seed($seeder);
        }

        $this->assertSame($first, $this->remainingSeederCounts());
        $this->assertSame(3, Sop::query()->count());
        $this->assertEmpty(Announcement::query()->whereNotIn('category', ['General', 'Event', 'Operational', 'Urgent', 'Memo'])->pluck('category'));
        $this->assertEmpty(Announcement::query()->whereNotIn('visibility', ['All', 'RND', 'FSS', 'Admin'])->pluck('visibility'));
        $photo = Announcement::query()->whereNotNull('attachment')->firstOrFail()->attachment;
        $this->assertStringStartsWith('data:image/jpeg;base64,', $photo);
        $this->assertNotFalse(base64_decode(substr($photo, strpos($photo, ',') + 1), true));
        $this->assertEmpty(ClinicalRule::query()->whereNotIn('rule_type', ['limit', 'avoid', 'recommend'])->pluck('rule_type'));
        $dmFiber = ClinicalRule::query()
            ->where('condition', 'DM')
            ->where('stage', 'all')
            ->where('nutrient_or_food_tag', 'fiber')
            ->where('rule_type', 'recommend')
            ->sole();
        $this->assertSame(25.0, (float) $dmFiber->threshold);
        $this->assertSame('Dietary fiber supports glycemic control and should be individualized within the prescription.', $dmFiber->reason);
        $this->assertFalse(ClinicalRule::query()
            ->where('condition', 'DM')
            ->where('nutrient_or_food_tag', 'carbs')
            ->where('rule_type', 'limit')
            ->exists());
        $this->assertSame([
            'demographic_census',
            'inspection_report',
            'marketing_statement',
            'marketing_summary',
            'menu_calendar',
            'ncp_summary',
            'patient_menu_plan',
            'procurement_pack',
            'program_project_activity',
        ], ReportTemplate::query()->orderBy('type')->pluck('type')->all());
        $this->assertSame(35_000, AiUsageLimit::current()->daily_token_limit);
        $this->assertSame(1_000_000, AiUsageLimit::current()->monthly_token_limit);
    }

    private function seedClinicalFoodFixtures(): void
    {
        $dataset = json_decode(file_get_contents(database_path('seeders/data/clinical-foods.json')), true, 512, JSON_THROW_ON_ERROR);
        $names = collect($dataset['foods'])->pluck('name')->all();
        foreach ($names as $index => $name) {
            FoodItem::factory()->create([
                'name' => $name,
                'usda_fdc_id' => 8_000_000 + $index,
                'serving_size' => 100,
                'serving_unit' => 'g',
                'calories' => 150,
                'protein' => 8,
                'carbs' => 20,
                'fat' => 4,
                'water_g' => 50,
            ]);
        }
    }

    /** @return array<string, int> */
    private function foodServiceCounts(): array
    {
        return [
            'recipes' => FoodServiceRecipe::query()->count(),
            'suppliers' => Supplier::query()->count(),
            'menu_cycles' => MenuCycle::query()->count(),
            'menu_cycle_templates' => MenuCycleTemplate::query()->count(),
            'shopping_lists' => ShoppingList::query()->count(),
            'purchase_orders' => PurchaseOrder::query()->count(),
            'budgets' => Budget::query()->count(),
        ];
    }

    /** @return array<string, int> */
    private function remainingSeederCounts(): array
    {
        return [
            'ai_limits' => AiUsageLimit::query()->count(),
            'clinical_rules' => ClinicalRule::query()->count(),
            'announcements' => Announcement::query()->count(),
            'notifications' => Notification::query()->count(),
            'sops' => Sop::query()->count(),
            'report_templates' => ReportTemplate::query()->count(),
        ];
    }
}
