<?php

namespace Tests\Feature;

use App\Models\AiUsageLimit;
use App\Models\Announcement;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\ClinicalRule;
use App\Models\FoodItem;
use App\Models\FoodServiceRecipe;
use App\Models\MenuCycle;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\PurchaseOrder;
use App\Models\Recipe;
use App\Models\ReportTemplate;
use App\Models\ShoppingList;
use App\Models\Sop;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\AiUsageLimitSeeder;
use Database\Seeders\AnnouncementSeeder;
use Database\Seeders\ClinicalRulesSeeder;
use Database\Seeders\FoodItemsSeeder;
use Database\Seeders\FoodServiceDemoSeeder;
use Database\Seeders\FsCatalogSeeder;
use Database\Seeders\NotificationSeeder;
use Database\Seeders\PatientSeeder;
use Database\Seeders\RecipeSeeder;
use Database\Seeders\ReportTemplateSeeder;
use Database\Seeders\SopSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
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

    public function test_patient_and_meal_plan_demo_graph_matches_current_contract(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seedClinicalFoodFixtures();
        $this->seed(RecipeSeeder::class);
        $this->seed(PatientSeeder::class);

        $patients = Patient::query()
            ->whereIn('hospital_number', ['HN-2026-0042', 'HN-2026-0078'])
            ->with(['ncpRecords.assessment', 'ncpRecords.diagnoses', 'ncpRecords.intervention.mealPlans.days'])
            ->get();

        $this->assertCount(2, $patients);
        foreach ($patients as $patient) {
            $this->assertNotSame('', trim((string) $patient->first_name));
            $this->assertNotSame('', trim((string) $patient->last_name));
            $this->assertSame($patient->first_name.' '.$patient->last_name, $patient->display_name);
            $this->assertContains($patient->sex, ['Male', 'Female']);
            $this->assertSame('Active', $patient->status);
            $this->assertSame('adult', $patient->screening_type);
            $this->assertCount(1, $patient->ncpRecords);

            $record = $patient->ncpRecords->sole();
            $this->assertContains($record->type, ['new', 'continuing']);
            $this->assertContains($record->status, ['draft', 'active', 'completed', 'discharged']);
            $this->assertNotNull($record->assessment);
            $this->assertNotNull($record->intervention);
            $this->assertNotEmpty($record->diagnoses);
            $this->assertNotEmpty($record->intervention->mealPlans);

            foreach ($record->intervention->mealPlans as $mealPlan) {
                $this->assertContains($mealPlan->status, ['draft', 'active']);
                $this->assertCount(35, $mealPlan->days);
            }
        }
    }

    public function test_food_service_demo_is_repeatable_and_uses_current_status_values(): void
    {
        CarbonImmutable::setTestNow('2026-07-16 12:00:00');
        $this->seed(AdminUserSeeder::class);
        $this->seed(FsCatalogSeeder::class);

        $this->seed(FoodServiceDemoSeeder::class);
        $first = $this->foodServiceCounts();
        $this->seed(FoodServiceDemoSeeder::class);
        $second = $this->foodServiceCounts();

        $this->assertSame($first, $second);
        $this->assertSame(5, $second['menu_cycles']);
        $this->assertGreaterThan(0, $second['recipes']);
        $this->assertGreaterThan(0, $second['suppliers']);
        $this->assertGreaterThan(0, $second['shopping_lists']);
        $this->assertGreaterThan(0, $second['purchase_orders']);
        $this->assertSame(1, $second['budgets']);

        $this->assertEmpty(MenuCycle::query()->whereNotIn('status', ['completed', 'active', 'upcoming'])->pluck('status'));
        $this->assertEmpty(ShoppingList::query()->whereNotIn('status', ['draft', 'converted'])->pluck('status'));
        $this->assertEmpty(PurchaseOrder::query()->whereNotIn('status', ['draft', 'ordered', 'received'])->pluck('status'));
        $this->assertEmpty(PurchaseOrder::query()->whereNotIn('lifecycle_status', ['open_execution', 'completed', 'archived'])->pluck('lifecycle_status'));
        $this->assertEmpty(BudgetLedger::query()->whereNotIn('type', ['po_deduction', 'manual_addition', 'manual_deduction'])->pluck('type'));
        $this->assertEmpty(BudgetLedger::query()->whereNotIn('source', ['system', 'manual'])->pluck('source'));
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
            ->where('nutrient_or_food_tag', 'carbs')
            ->where('rule_type', 'limit')
            ->update(['threshold' => -1, 'reason' => 'STALE RULE']);
        foreach ($seeders as $seeder) {
            $this->seed($seeder);
        }

        $this->assertSame($first, $this->remainingSeederCounts());
        $this->assertSame(3, Sop::query()->count());
        $this->assertEmpty(Announcement::query()->whereNotIn('category', ['General', 'Event', 'Operational', 'Urgent', 'Memo'])->pluck('category'));
        $this->assertEmpty(Announcement::query()->whereNotIn('visibility', ['All', 'RND', 'FSS', 'Admin'])->pluck('visibility'));
        $this->assertEmpty(ClinicalRule::query()->whereNotIn('rule_type', ['limit', 'avoid', 'recommend'])->pluck('rule_type'));
        $dmCarbs = ClinicalRule::query()
            ->where('condition', 'DM')
            ->where('stage', 'all')
            ->where('nutrient_or_food_tag', 'carbs')
            ->where('rule_type', 'limit')
            ->sole();
        $this->assertSame(180.0, (float) $dmCarbs->threshold);
        $this->assertSame('Carbohydrate restriction for glycemic control in diabetes mellitus', $dmCarbs->reason);
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
        $names = array_keys((new ReflectionClass(FoodItemsSeeder::class))->getConstant('INGREDIENTS'));
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
