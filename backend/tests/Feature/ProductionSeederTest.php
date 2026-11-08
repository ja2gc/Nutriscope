<?php

namespace Tests\Feature;

use App\Models\AiUsageLimit;
use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
use App\Models\MealPlanTemplate;
use App\Models\MenuCycleTemplate;
use App\Models\Recipe;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Services\MenuCycleCostService;
use App\Support\UnitConverter;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InterventionMealPlanTemplateSeeder;
use Database\Seeders\RecipeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('filesystems.private_uploads'));
        Http::preventStrayRequests();
        config()->set('seeding.admin', [
            'first_name' => 'Initial', 'last_name' => 'Administrator',
            'email' => 'initial@example.test', 'password' => 'testing-password-42',
        ]);
    }

    public function test_default_seed_creates_only_an_admin_and_reference_data_without_operational_history(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(['Admin'], User::query()->pluck('role')->all());
        $admin = User::query()->sole();
        $this->assertSame('initial@example.test', $admin->email);
        $this->assertTrue(Hash::check('testing-password-42', $admin->password));
        $this->assertTrue($admin->must_change_password);
        $this->assertTrue($admin->must_set_recovery_email);
        $this->assertGreaterThan(0, Recipe::query()->count());
        $this->assertGreaterThan(0, MealPlanTemplate::query()->count());
        $this->assertGreaterThan(0, DB::table('food_items')->count());
        $this->assertGreaterThan(40, FoodServiceRecipe::query()->count());
        $this->assertSame(4, MenuCycleTemplate::query()->count());
        $vendor = DB::table('suppliers')->where('name', 'Noralyn Macaballug, RPDH-MPC')->value('id');
        $this->assertSame($vendor, FsItem::query()->where('name', 'Egg')->sole()->default_supplier_id);
        $this->assertNull(FsItem::query()->where('name', 'Ground beef')->sole()->default_supplier_id);
        foreach (['patients', 'ncp_records', 'menu_cycles', 'shopping_lists', 'purchase_orders', 'budgets', 'budget_ledger', 'announcements', 'notifications', 'sops', 'reports', 'stored_objects', 'activity_log'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table.' must not contain seeded operational history.');
        }
    }

    public function test_menu_templates_follow_the_posts_without_snack_lines_and_keep_product_names_specific(): void
    {
        $this->seed(DatabaseSeeder::class);
        $templates = MenuCycleTemplate::query()->with('days.recipe', 'days.fsItem')->orderBy('name')->get();
        $first = $templates->firstWhere('name', 'Hospital Menu — September 01–07');
        $second = $templates->firstWhere('name', 'Hospital Menu — September 08–14');
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $names = fn ($template, $day, $meal) => $template->days->where('day_of_week', $day)->where('meal_type', $meal)
            ->sortBy('line_order')->map(fn ($line) => $line->recipe?->name ?? $line->fsItem?->name)->values()->all();
        $this->assertContains('Ground Beef Picadillo', $names($first, 'Saturday', 'dinner'));
        $this->assertContains('Coffee 3-in-1', $names($first, 'Monday', 'breakfast'));
        $this->assertContains('Tuna Sandwich', $names($second, 'Friday', 'breakfast'));
        $this->assertContains('Fried Saba', $names($second, 'Saturday', 'dinner'));
        $this->assertContains('Bear Brand Swakto', $names($second, 'Sunday', 'breakfast'));
        foreach ($templates as $template) {
            $this->assertSame(['breakfast', 'dinner', 'lunch'], $template->days->pluck('meal_type')->unique()->sort()->values()->all());
            $this->assertSame(21, $template->days->map(fn ($line) => $line->day_of_week.'|'.$line->meal_type)->unique()->count());
            foreach ($template->days as $line) {
                $this->assertNotSame($line->recipe_id === null, $line->fs_item_id === null);
            }
        }
        foreach (['Rice', 'Cooking oil', 'Salt', 'Ground black pepper'] as $name) {
            $this->assertFalse(FsItem::query()->where('name', $name)->sole()->include_in_generated_lists);
        }
        foreach (['Soy sauce', 'Vinegar', 'Oyster sauce'] as $name) {
            $this->assertTrue(FsItem::query()->where('name', $name)->sole()->include_in_generated_lists);
        }
        $this->assertFalse(FsItem::query()->where('name', 'like', '%lemongrass%')->exists());
        $this->assertTrue(FoodServiceRecipe::query()->where('name', 'Chicken with Lemongrass')->exists());
        $this->assertSame([50], FoodServiceRecipe::query()->distinct()->pluck('servings')->all());
    }

    public function test_rerunning_production_seeds_preserves_admin_credentials_and_food_service_edits(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->sole();
        $admin->update(['first_name' => 'Renamed', 'password' => Hash::make('changed-password-42')]);
        $rice = FsItem::query()->where('name', 'Rice')->sole();
        $rice->update(['purchase_price' => 65]);
        $recipe = FoodServiceRecipe::query()->where('name', 'Tuna Sandwich')->sole();
        $ingredient = $recipe->ingredients()->firstOrFail();
        $ingredient->update(['quantity' => 123]);
        $template = MenuCycleTemplate::query()->orderBy('id')->firstOrFail();
        $template->update(['description' => 'Staff-edited rotation']);
        $limits = AiUsageLimit::current();
        $limits->update(['daily_token_limit' => 1234, 'input_cost_per_1m_tokens_usd' => 7]);
        $report = ReportTemplate::query()->where('type', 'inspection_report')->sole();
        $report->update(['signatories' => [['role' => 'inspected_by', 'name' => 'Institution Inspector']]]);
        $counts = fn () => collect(['users', 'fs_items', 'food_service_recipes', 'food_service_recipe_ingredients', 'menu_cycle_templates', 'menu_cycle_template_days', 'recipes', 'recipe_ingredients', 'meal_plan_templates', 'meal_plan_template_days', 'meal_plan_items'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
        $before = $counts();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($before, $counts());
        $this->assertSame('Renamed', $admin->fresh()->first_name);
        $this->assertTrue(Hash::check('changed-password-42', $admin->fresh()->password));
        $this->assertSame(65.0, (float) $rice->fresh()->purchase_price);
        $this->assertSame(123.0, (float) $ingredient->fresh()->quantity);
        $this->assertSame('Staff-edited rotation', $template->fresh()->description);
        $this->assertSame(1234, $limits->fresh()->daily_token_limit);
        $this->assertSame(7.0, $limits->fresh()->input_cost_per_1m_tokens_usd);
        $this->assertSame('Institution Inspector', $report->fresh()->signatories[0]['name']);
    }

    public function test_every_seeded_recipe_has_compatible_measured_units_and_scales_to_one_hundred(): void
    {
        $this->seed(DatabaseSeeder::class);
        foreach (FoodServiceRecipe::query()->with('ingredients.fsItem')->get() as $recipe) {
            foreach ($recipe->ingredients as $ingredient) {
                $this->assertGreaterThan(0, (float) $ingredient->quantity, $recipe->name);
                $this->assertTrue(UnitConverter::isCompatible($ingredient->unit, $ingredient->fsItem->base_unit), $recipe->name);
                $this->assertSame($ingredient->fsItem->base_unit, $ingredient->fsItem->purchase_unit);
            }
            $fifty = MenuCycleCostService::recipeProfile($recipe, 50);
            $hundred = MenuCycleCostService::recipeProfile($recipe, 100);
            $this->assertGreaterThan(0, $fifty['total_cost'], $recipe->name);
            $this->assertEqualsWithDelta($fifty['total_cost'] * 2, $hundred['total_cost'], 0.02, $recipe->name);
            foreach ($fifty['ingredient_usage'] as $index => $usage) {
                $this->assertEqualsWithDelta($usage['quantity'] * 2, $hundred['ingredient_usage'][$index]['quantity'], 0.000002);
                $this->assertNotContains($usage['name'], ['Rice', 'Cooking oil', 'Salt', 'Ground black pepper']);
            }
        }
        $mami = FoodServiceRecipe::query()->where('name', 'Mami')->sole();
        $this->assertTrue($mami->ingredients()->whereHas('fsItem', fn ($query) => $query->where('name', 'Fresh miki noodles'))->exists());
    }

    public function test_initial_admin_requires_supplied_details_and_never_uses_demo_credentials(): void
    {
        config()->set('seeding.admin', []);
        $this->expectException(ValidationException::class);
        try {
            $this->seed(AdminUserSeeder::class);
        } finally {
            $this->assertSame(0, User::query()->count());
        }
    }

    public function test_demo_entry_point_is_rejected_before_writing_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Demo seeding is disabled in production.');
        try {
            $this->artisan('db:seed', ['--class' => \Database\Seeders\Demo\DatabaseSeeder::class, '--force' => true]);
        } finally {
            $this->assertSame(0, User::query()->count());
        }
    }

    public function test_retained_clinical_references_can_be_seeded_for_a_named_real_rnd(): void
    {
        $this->seed(DatabaseSeeder::class);
        $rnd = User::factory()->rnd()->create(['email' => 'rnd@example.test']);
        config()->set('seeding.reference_email', $rnd->email);
        $this->seed([RecipeSeeder::class, InterventionMealPlanTemplateSeeder::class]);
        $this->assertGreaterThan(0, Recipe::query()->where('rnd_user_id', $rnd->id)->count());
        $this->assertGreaterThan(0, MealPlanTemplate::query()->where('rnd_user_id', $rnd->id)->count());
        $this->actingAs($rnd, 'sanctum')->getJson('/api/rnd/meal-plan-templates')->assertOk()
            ->assertJsonPath('meta.total', MealPlanTemplate::query()->where('rnd_user_id', $rnd->id)->count());
    }
}
